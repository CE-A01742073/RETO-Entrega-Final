<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

require_once '../config/database.php';

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$comment_id = intval($body['comment_id'] ?? 0);
$action     = $body['action'] ?? '';

if (!$comment_id || !in_array($action, ['hide', 'restore'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}

$new_status = $action === 'hide' ? 'hidden' : 'active';

$stmt = $conn->prepare("UPDATE blog_comments SET status = ? WHERE comment_id = ?");
$stmt->bind_param("si", $new_status, $comment_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

echo json_encode([
    'success' => $affected > 0,
    'status'  => $new_status,
]);