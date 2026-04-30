<?php
session_start();
require_once '../config/database.php';
require_once '../config/notify.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$user_id   = $_SESSION['user_id'];
$lesson_id = isset($_POST['lesson_id']) ? intval($_POST['lesson_id']) : 0;
$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

if ($lesson_id === 0 || $course_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros no válidos']);
    exit();
}

try {
    $check_enrollment = $conn->prepare("
        SELECT enrollment_id 
        FROM course_enrollments 
        WHERE user_id = ? AND course_id = ?
    ");
    $check_enrollment->bind_param("ii", $user_id, $course_id);
    $check_enrollment->execute();
    $enrollment_result = $check_enrollment->get_result();
    
    if ($enrollment_result->num_rows === 0) {
        throw new Exception('No estás inscrito en este curso');
    }
    
    $enrollment = $enrollment_result->fetch_assoc();
    $check_enrollment->close();

    // Bloquear si hay quiz requerido no aprobado
    $qz_check = $conn->prepare("SELECT quiz_id, is_required FROM lesson_quizzes WHERE lesson_id = ? AND is_required = 1 LIMIT 1");
    $qz_check->bind_param('i', $lesson_id);
    $qz_check->execute();
    $qz_row = $qz_check->get_result()->fetch_assoc();
    $qz_check->close();
    if ($qz_row) {
        $passed_check = $conn->prepare("SELECT COUNT(*) as cnt FROM quiz_attempts WHERE quiz_id=? AND user_id=? AND passed=1 AND completed_at IS NOT NULL");
        $passed_check->bind_param('ii', $qz_row['quiz_id'], $user_id);
        $passed_check->execute();
        $passed_cnt = intval($passed_check->get_result()->fetch_assoc()['cnt']);
        $passed_check->close();
        if ($passed_cnt === 0) {
            echo json_encode(['success' => false, 'message' => 'Debes aprobar el quiz para completar esta lección']);
            exit();
        }
    }

    $check_progress = $conn->prepare("
        SELECT progress_id, is_completed 
        FROM lesson_progress 
        WHERE user_id = ? AND lesson_id = ?
    ");
    $check_progress->bind_param("ii", $user_id, $lesson_id);
    $check_progress->execute();
    $progress_result = $check_progress->get_result();
    
    $is_completed = false;
    
    if ($progress_result->num_rows > 0) {
        $progress   = $progress_result->fetch_assoc();
        $new_status = !$progress['is_completed'];
        $is_completed = $new_status;
        
        if ($new_status) {
            $update_progress = $conn->prepare("
                UPDATE lesson_progress 
                SET is_completed = 1, completed_at = NOW(), updated_at = NOW()
                WHERE progress_id = ?
            ");
            $update_progress->bind_param("i", $progress['progress_id']);
        } else {
            $update_progress = $conn->prepare("
                UPDATE lesson_progress 
                SET is_completed = 0, completed_at = NULL, updated_at = NOW()
                WHERE progress_id = ?
            ");
            $update_progress->bind_param("i", $progress['progress_id']);
        }
        
        $update_progress->execute();
        $update_progress->close();
    } else {
        $insert_progress = $conn->prepare("
            INSERT INTO lesson_progress (user_id, lesson_id, course_id, is_completed, completed_at) 
            VALUES (?, ?, ?, 1, NOW())
        ");
        $insert_progress->bind_param("iii", $user_id, $lesson_id, $course_id);
        $insert_progress->execute();
        $insert_progress->close();
        $is_completed = true;
    }
    
    $check_progress->close();

    $total_lessons_query = $conn->prepare("
        SELECT COUNT(*) as total
        FROM course_lessons cl
        INNER JOIN course_modules cm ON cl.module_id = cm.module_id
        WHERE cm.course_id = ?
    ");
    $total_lessons_query->bind_param("i", $course_id);
    $total_lessons_query->execute();
    $total_lessons = $total_lessons_query->get_result()->fetch_assoc()['total'];
    $total_lessons_query->close();
    
    $completed_lessons_query = $conn->prepare("
        SELECT COUNT(*) as completed
        FROM lesson_progress lp
        INNER JOIN course_lessons cl ON lp.lesson_id = cl.lesson_id
        INNER JOIN course_modules cm ON cl.module_id = cm.module_id
        WHERE lp.user_id = ? AND cm.course_id = ? AND lp.is_completed = 1
    ");
    $completed_lessons_query->bind_param("ii", $user_id, $course_id);
    $completed_lessons_query->execute();
    $completed_lessons = $completed_lessons_query->get_result()->fetch_assoc()['completed'];
    $completed_lessons_query->close();
    
    $progress_percentage = $total_lessons > 0 ? ($completed_lessons / $total_lessons) * 100 : 0;
    $course_completed    = $progress_percentage >= 100;
    
    if ($course_completed) {
        $update_enrollment = $conn->prepare("
            UPDATE course_enrollments 
            SET progress_percentage = ?, 
                status = 'completed', 
                completion_date = NOW(),
                last_accessed = NOW()
            WHERE enrollment_id = ?
        ");
        $update_enrollment->bind_param("di", $progress_percentage, $enrollment['enrollment_id']);
    } else {
        $update_enrollment = $conn->prepare("
            UPDATE course_enrollments 
            SET progress_percentage = ?,
                last_accessed = NOW()
            WHERE enrollment_id = ?
        ");
        $update_enrollment->bind_param("di", $progress_percentage, $enrollment['enrollment_id']);
    }
    
    $update_enrollment->execute();
    $update_enrollment->close();

    // Gamificación
    if ($is_completed) {
        $xp_check = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM user_xp
             WHERE user_id = ? AND source_type = 'lesson_completed' AND source_id = ?"
        );
        $xp_check->bind_param("ii", $user_id, $lesson_id);
        $xp_check->execute();
        $already_awarded = (int) $xp_check->get_result()->fetch_assoc()['cnt'];
        $xp_check->close();

        if ($already_awarded === 0) {
            $xp_insert = $conn->prepare(
                "INSERT INTO user_xp (user_id, xp_points, source_type, source_id)
                 VALUES (?, 10, 'lesson_completed', ?)"
            );
            $xp_insert->bind_param("ii", $user_id, $lesson_id);
            $xp_insert->execute();
            $xp_insert->close();
        }

        $conn->query("CALL sp_update_streak($user_id)");

        if ($course_completed) {
            $conn->query("CALL sp_award_course_xp($user_id, $course_id)");

            // ── Notificar a amigos que el usuario completó el curso
            $course_info = $conn->prepare("SELECT course_title FROM courses WHERE course_id = ?");
            $course_info->bind_param('i', $course_id);
            $course_info->execute();
            $course_title = $course_info->get_result()->fetch_assoc()['course_title'] ?? 'un curso';
            $course_info->close();

            $user_name = $_SESSION['user_name'] ?? 'Tu amigo';

            // Obtener todos los amigos aceptados
            $friends_q = $conn->prepare("
                SELECT CASE
                    WHEN requester_id = ? THEN addressee_id
                    ELSE requester_id
                END AS friend_id
                FROM friendships
                WHERE (requester_id = ? OR addressee_id = ?)
                  AND status = 'accepted'
            ");
            $friends_q->bind_param('iii', $user_id, $user_id, $user_id);
            $friends_q->execute();
            $friends_res = $friends_q->get_result();
            while ($f = $friends_res->fetch_assoc()) {
                notify(
                    $conn,
                    (int) $f['friend_id'],
                    'friend_course',
                    '¡Tu amigo completó un curso!',
                    "{$user_name} completó «{$course_title}»",
                    "courses.php"
                );
            }
            $friends_q->close();
        }
    }

    echo json_encode([
        'success'            => true,
        'is_completed'       => $is_completed,
        'progress_percentage'=> round($progress_percentage, 2),
        'course_completed'   => $course_completed,
        'completed_lessons'  => $completed_lessons,
        'total_lessons'      => $total_lessons
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>