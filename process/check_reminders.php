<?php
// Cron diario: notifica usuarios inactivos en cursos en progreso.
// 0 9 * * * php /home/u873778731/public_html/process/check_reminders.php

$cli = (php_sapi_name() === 'cli');

if (!$cli) {
    session_start();
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso denegado.']);
        exit();
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/notify.php';

$dias_inactivo = 7; // días sin acceso para disparar recordatorio

$sql = "
    SELECT
        ce.user_id,
        ce.course_id,
        c.course_title,
        ce.progress_percentage,
        ce.last_accessed
    FROM course_enrollments ce
    INNER JOIN courses c ON c.course_id = ce.course_id
    WHERE ce.status = 'active'
      AND ce.progress_percentage > 0
      AND ce.last_accessed < NOW() - INTERVAL ? DAY
      AND NOT EXISTS (
          SELECT 1 FROM notifications n
          WHERE n.user_id    = ce.user_id
            AND n.type       = 'reminder'
            AND n.message    LIKE CONCAT('%', c.course_title, '%')
            AND n.created_at > NOW() - INTERVAL 1 DAY
      )
    ORDER BY ce.user_id
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $dias_inactivo);
$stmt->execute();
$res  = $stmt->get_result();

$total = 0;
while ($row = $res->fetch_assoc()) {
    $progreso = round($row['progress_percentage']);
    $titulo   = "¡Continúa donde lo dejaste!";
    $mensaje  = "Llevas varios días sin avanzar en «{$row['course_title']}». Vas {$progreso}% — ¡ya casi!";
    $link     = "course-view.php?id={$row['course_id']}";

    notify($conn, (int) $row['user_id'], 'reminder', $titulo, $mensaje, $link);
    $total++;
}
$stmt->close();

if ($cli) {
    echo "Recordatorios generados: $total\n";
} else {
    echo json_encode(['success' => true, 'enviados' => $total]);
}

$conn->close();