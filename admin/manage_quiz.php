<?php
// API: gestión de quizzes (get / save / delete)

session_start();
require_once '../config/database.php';
require_once 'auth-check.php';

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_exception_handler(function($e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

if ($action === 'get') {
    $lesson_id = intval($body['lesson_id'] ?? 0);
    if (!$lesson_id) { echo json_encode(['success'=>false,'error'=>'lesson_id requerido']); exit(); }

    $q = $conn->prepare("SELECT * FROM lesson_quizzes WHERE lesson_id = ? LIMIT 1");
    $q->bind_param('i', $lesson_id);
    $q->execute();
    $quiz = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$quiz) { echo json_encode(['success'=>true,'quiz'=>null]); exit(); }

    $questions = [];
    $qr = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY display_order");
    $qr->bind_param('i', $quiz['quiz_id']);
    $qr->execute();
    $rows = $qr->get_result()->fetch_all(MYSQLI_ASSOC);
    $qr->close();

    foreach ($rows as $row) {
        $or = $conn->prepare("SELECT * FROM quiz_options WHERE question_id = ? ORDER BY display_order");
        $or->bind_param('i', $row['question_id']);
        $or->execute();
        $row['options'] = $or->get_result()->fetch_all(MYSQLI_ASSOC);
        $or->close();
        $questions[] = $row;
    }

    $quiz['questions'] = $questions;
    echo json_encode(['success' => true, 'quiz' => $quiz]);
    exit();
}

if ($action === 'save') {
    $lesson_id   = intval($body['lesson_id']      ?? 0);
    $course_id   = intval($body['course_id']      ?? 0);
    $quiz_title  = trim($body['quiz_title']       ?? 'Quiz de la Lección');
    $passing     = intval($body['passing_score']  ?? 70);
    $max_att     = intval($body['max_attempts']   ?? 3);
    $time_lim    = intval($body['time_limit_min'] ?? 0);
    $is_required = intval($body['is_required']    ?? 0);
    $questions   = $body['questions']             ?? [];

    if (!$lesson_id || !$course_id) {
        echo json_encode(['success'=>false,'error'=>'lesson_id y course_id son requeridos']); exit();
    }
    if (empty($questions)) {
        echo json_encode(['success'=>false,'error'=>'El quiz debe tener al menos una pregunta']); exit();
    }

    $ex = $conn->prepare("SELECT quiz_id FROM lesson_quizzes WHERE lesson_id = ?");
    $ex->bind_param('i', $lesson_id);
    $ex->execute();
    $ex_row = $ex->get_result()->fetch_assoc();
    $ex->close();

    if ($ex_row) {
        $quiz_id = $ex_row['quiz_id'];
        $upd = $conn->prepare("UPDATE lesson_quizzes SET quiz_title=?,passing_score=?,max_attempts=?,time_limit_min=?,is_required=? WHERE quiz_id=?");
        $upd->bind_param('siiiii', $quiz_title, $passing, $max_att, $time_lim, $is_required, $quiz_id);
        $upd->execute();
        $upd->close();
        $del = $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id=?");
        $del->bind_param('i', $quiz_id);
        $del->execute();
        $del->close();
    } else {
        $ins = $conn->prepare("INSERT INTO lesson_quizzes (lesson_id,course_id,quiz_title,passing_score,max_attempts,time_limit_min,is_required) VALUES (?,?,?,?,?,?,?)");
        $ins->bind_param('iisiiii', $lesson_id, $course_id, $quiz_title, $passing, $max_att, $time_lim, $is_required);
        $ins->execute();
        $quiz_id = $conn->insert_id;
        $ins->close();
    }

    foreach ($questions as $qi => $question) {
        $qtext = trim($question['question_text'] ?? '');
        $qtype = $question['question_type']      ?? 'single';
        $qpts  = intval($question['points']      ?? 1);
        if (!$qtext) continue;

        $qi_stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id,question_text,question_type,display_order,points) VALUES (?,?,?,?,?)");
        $qi_stmt->bind_param('issii', $quiz_id, $qtext, $qtype, $qi, $qpts);
        $qi_stmt->execute();
        $question_id = $conn->insert_id;
        $qi_stmt->close();

        foreach (($question['options'] ?? []) as $oi => $opt) {
            $otext      = trim($opt['option_text'] ?? '');
            $is_correct = intval($opt['is_correct']  ?? 0);
            if (!$otext) continue;
            $oi_stmt = $conn->prepare("INSERT INTO quiz_options (question_id,option_text,is_correct,display_order) VALUES (?,?,?,?)");
            $oi_stmt->bind_param('isii', $question_id, $otext, $is_correct, $oi);
            $oi_stmt->execute();
            $oi_stmt->close();
        }
    }

    $conn->close();
    echo json_encode(['success' => true, 'quiz_id' => $quiz_id]);
    exit();
}

if ($action === 'delete') {
    $lesson_id = intval($body['lesson_id'] ?? 0);
    if (!$lesson_id) { echo json_encode(['success'=>false,'error'=>'lesson_id requerido']); exit(); }
    $del = $conn->prepare("DELETE FROM lesson_quizzes WHERE lesson_id=?");
    $del->bind_param('i', $lesson_id);
    $del->execute();
    $del->close();
    $conn->close();
    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Acción no reconocida: ' . htmlspecialchars($action)]);