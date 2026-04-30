<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

if ($course_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Curso no válido']);
    exit();
}

try {
    $check_course = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ? AND status = 'published'");
    $check_course->bind_param("i", $course_id);
    $check_course->execute();
    $result = $check_course->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('El curso no está disponible');
    }
    $check_course->close();
    $check_enrollment = $conn->prepare("SELECT enrollment_id FROM course_enrollments WHERE user_id = ? AND course_id = ?");
    $check_enrollment->bind_param("ii", $user_id, $course_id);
    $check_enrollment->execute();
    $result = $check_enrollment->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Ya estás inscrito en este curso']);
        exit();
    }
    $check_enrollment->close();
    $enroll = $conn->prepare("
        INSERT INTO course_enrollments (user_id, course_id, enrollment_date, status) 
        VALUES (?, ?, NOW(), 'active')
    ");
    $enroll->bind_param("ii", $user_id, $course_id);
    
    if ($enroll->execute()) {
        $update_count = $conn->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE course_id = ?");
        $update_count->bind_param("i", $course_id);
        $update_count->execute();
        $update_count->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Inscripción exitosa',
            'redirect' => "course-view.php?id={$course_id}"
        ]);
    } else {
        throw new Exception('Error al procesar la inscripción');
    }
    
    $enroll->close();
    
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