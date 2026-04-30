<?php
/**
 * API: Envío y calificación de quiz (alumno)
 * POST { quiz_id, lesson_id, course_id, answers: [{question_id, option_ids:[]}], time_spent_sec }
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'error'=>'Sesión no válida']); exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit();
}

$user_id   = $_SESSION['user_id'];
$body      = json_decode(file_get_contents('php://input'), true);
$quiz_id   = intval($body['quiz_id']       ?? 0);
$lesson_id = intval($body['lesson_id']     ?? 0);
$course_id = intval($body['course_id']     ?? 0);
$answers   = $body['answers']              ?? [];   // [{question_id, option_ids:[int,...]}]
$time_sec  = intval($body['time_spent_sec']?? 0);

if (!$quiz_id || !$lesson_id || !$course_id) {
    echo json_encode(['success'=>false,'error'=>'Parámetros requeridos faltantes']); exit();
}

// ── Verificar número de intentos ────────────────────────────────
$quiz_row = $conn->prepare("SELECT * FROM lesson_quizzes WHERE quiz_id = ?");
$quiz_row->bind_param('i', $quiz_id);
$quiz_row->execute();
$quiz = $quiz_row->get_result()->fetch_assoc();
$quiz_row->close();

if (!$quiz) { echo json_encode(['success'=>false,'error'=>'Quiz no encontrado']); exit(); }

$att_count_q = $conn->prepare("SELECT COUNT(*) AS cnt FROM quiz_attempts WHERE quiz_id=? AND user_id=? AND completed_at IS NOT NULL");
$att_count_q->bind_param('ii', $quiz_id, $user_id);
$att_count_q->execute();
$att_count = $att_count_q->get_result()->fetch_assoc()['cnt'];
$att_count_q->close();

if ($quiz['max_attempts'] > 0 && $att_count >= $quiz['max_attempts']) {
    echo json_encode(['success'=>false,'error'=>'Has alcanzado el número máximo de intentos']); exit();
}

// ── Crear intento ────────────────────────────────────────────────
$ins_att = $conn->prepare("INSERT INTO quiz_attempts (quiz_id,user_id,lesson_id,course_id,started_at) VALUES (?,?,?,?,NOW())");
$ins_att->bind_param('iiii', $quiz_id, $user_id, $lesson_id, $course_id);
$ins_att->execute();
$attempt_id = $conn->insert_id;
$ins_att->close();

// ── Obtener preguntas y opciones correctas ───────────────────────
$all_q = $conn->query("SELECT * FROM quiz_questions WHERE quiz_id={$quiz_id} ORDER BY display_order");
$questions = $all_q->fetch_all(MYSQLI_ASSOC);

$total_questions  = count($questions);
$correct_questions = 0;
$question_results  = [];

// Cada pregunta vale igual: 100 / total_preguntas
// Así 5 preguntas = 20 pts c/u, 10 preguntas = 10 pts c/u, etc.
$pts_per_question = $total_questions > 0 ? round(100 / $total_questions, 4) : 0;

foreach ($questions as $q) {
    $qid   = intval($q['question_id']);
    $qtype = $q['question_type'];
    $pts   = $pts_per_question;

    // Opciones correctas de la BD
    $cor_r = $conn->query("SELECT option_id FROM quiz_options WHERE question_id={$qid} AND is_correct=1");
    $correct_ids = array_map('intval', array_column($cor_r->fetch_all(MYSQLI_ASSOC), 'option_id'));

    // Opciones elegidas por el usuario
    $chosen_ids = [];
    foreach ($answers as $ans) {
        if (intval($ans['question_id']) == $qid) {
            $chosen_ids = array_map('intval', $ans['option_ids'] ?? []);
        }
    }

    // Guardar respuestas del usuario
    foreach ($chosen_ids as $opt_id) {
        $ins_ans = $conn->prepare("INSERT INTO quiz_attempt_answers (attempt_id,question_id,option_id) VALUES (?,?,?)");
        $ins_ans->bind_param('iii', $attempt_id, $qid, $opt_id);
        $ins_ans->execute(); $ins_ans->close();
    }

    // Calcular puntaje
    $is_correct = false;
    if ($qtype === 'single' || $qtype === 'truefalse') {
        $is_correct = (count($chosen_ids) === 1 && in_array($chosen_ids[0], $correct_ids));
    } elseif ($qtype === 'multiple') {
        sort($correct_ids); sort($chosen_ids);
        $is_correct = ($correct_ids === $chosen_ids);
    }

    if ($is_correct) $correct_questions++;

    // Todas las opciones con flag de correcto/elegido para el resultado
    $all_opts_r = $conn->query("SELECT * FROM quiz_options WHERE question_id={$qid} ORDER BY display_order");
    $all_opts   = $all_opts_r->fetch_all(MYSQLI_ASSOC);
    foreach ($all_opts as &$o) {
        $o['is_correct']  = intval($o['is_correct']);
        $o['was_chosen']  = in_array($o['option_id'], $chosen_ids) ? 1 : 0;
    }
    unset($o);

    $question_results[] = [
        'question_id'   => $qid,
        'question_text' => $q['question_text'],
        'question_type' => $qtype,
        'points'        => $pts,
        'is_correct'    => $is_correct,
        'correct_ids'   => $correct_ids,
        'chosen_ids'    => $chosen_ids,
        'options'       => $all_opts,
    ];
}

// ── Calcular score ───────────────────────────────────────────────
// Score = % de preguntas correctas (independiente de puntos en BD)
$score  = $total_questions > 0 ? round(($correct_questions / $total_questions) * 100, 2) : 0;
$passed = $score >= $quiz['passing_score'] ? 1 : 0;

// ── Completar intento ────────────────────────────────────────────
$upd_att = $conn->prepare("UPDATE quiz_attempts SET score=?,passed=?,completed_at=NOW(),time_spent_sec=? WHERE attempt_id=?");
$upd_att->bind_param('diii', $score, $passed, $time_sec, $attempt_id);
$upd_att->execute(); $upd_att->close();

// ── Si aprobó y el quiz lo requiere, marcar lección completa automáticamente ──
if ($passed && $quiz['is_required']) {
    // Verificar si ya existe progreso
    $chk = $conn->prepare("SELECT progress_id FROM lesson_progress WHERE user_id=? AND lesson_id=?");
    $chk->bind_param('ii', $user_id, $lesson_id);
    $chk->execute();
    $chk_row = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($chk_row) {
        $upd_prog = $conn->prepare("UPDATE lesson_progress SET is_completed=1,completed_at=NOW() WHERE progress_id=?");
        $upd_prog->bind_param('i', $chk_row['progress_id']);
        $upd_prog->execute(); $upd_prog->close();
    } else {
        $ins_prog = $conn->prepare("INSERT INTO lesson_progress (user_id,lesson_id,course_id,is_completed,completed_at) VALUES (?,?,?,1,NOW())");
        $ins_prog->bind_param('iii', $user_id, $lesson_id, $course_id);
        $ins_prog->execute(); $ins_prog->close();
    }

    // Recalcular % del curso
    $total_q = $conn->prepare("SELECT COUNT(*) AS t FROM course_lessons cl INNER JOIN course_modules cm ON cl.module_id=cm.module_id WHERE cm.course_id=?");
    $total_q->bind_param('i', $course_id);
    $total_q->execute();
    $total_l = $total_q->get_result()->fetch_assoc()['t'];
    $total_q->close();

    $comp_q = $conn->prepare("SELECT COUNT(*) AS c FROM lesson_progress lp INNER JOIN course_lessons cl ON lp.lesson_id=cl.lesson_id INNER JOIN course_modules cm ON cl.module_id=cm.module_id WHERE lp.user_id=? AND cm.course_id=? AND lp.is_completed=1");
    $comp_q->bind_param('ii', $user_id, $course_id);
    $comp_q->execute();
    $comp_l = $comp_q->get_result()->fetch_assoc()['c'];
    $comp_q->close();

    $pct = $total_l > 0 ? round(($comp_l / $total_l) * 100, 2) : 0;
    $course_completed = $pct >= 100;

    $enr_upd = $conn->prepare("UPDATE course_enrollments SET progress_percentage=?,last_accessed=NOW()" . ($course_completed ? ",status='completed',completion_date=NOW()" : "") . " WHERE user_id=? AND course_id=?");
    $enr_upd->bind_param('dii', $pct, $user_id, $course_id);
    $enr_upd->execute(); $enr_upd->close();
}

$attempts_left = $quiz['max_attempts'] > 0 ? max(0, $quiz['max_attempts'] - ($att_count + 1)) : 999;

$conn->close();

echo json_encode([
    'success'        => true,
    'attempt_id'     => $attempt_id,
    'score'          => $score,
    'passed'         => (bool)$passed,
    'passing_score'  => $quiz['passing_score'],
    'earned_points'  => $correct_questions,
    'total_points'   => $total_questions,
    'attempts_left'  => $attempts_left,
    'questions'      => $question_results,
]);