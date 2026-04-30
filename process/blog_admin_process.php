<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

require_once '../config/database.php';

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$action  = $body['action']  ?? '';
$post_id = intval($body['post_id'] ?? 0);

if (!$post_id || !in_array($action, ['publish', 'unpublish', 'delete'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}

switch ($action) {

    case 'publish':
        $stmt = $conn->prepare("UPDATE blog_posts SET status = 'published' WHERE post_id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        break;

    case 'unpublish':
        $stmt = $conn->prepare("UPDATE blog_posts SET status = 'pending' WHERE post_id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        break;

    case 'delete':
        // Eliminación lógica: cambia estado a rejected
        $stmt = $conn->prepare("UPDATE blog_posts SET status = 'rejected' WHERE post_id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        break;
}

$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

echo json_encode(['success' => $affected > 0]);