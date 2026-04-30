<?php
// GET ?action=list|count  /  POST action=mark_read|create

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado.']);
    exit();
}

$me     = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? ($_POST['action'] ?? ''));

if ($method === 'GET' && $action === 'list') {
    $stmt = $conn->prepare("
        SELECT notification_id, type, title, message, link, is_read, created_at
        FROM   notifications
        WHERE  user_id = ?
        ORDER  BY created_at DESC
        LIMIT  20
    ");
    $stmt->bind_param('i', $me);
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    echo json_encode(['success' => true, 'notifications' => $rows]);
    exit();
}

if ($method === 'GET' && $action === 'count') {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param('i', $me);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    echo json_encode(['success' => true, 'count' => $count]);
    exit();
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = trim($body['action'] ?? $action);

    if ($action === 'mark_read') {
        $nid = isset($body['notification_id']) ? (int) $body['notification_id'] : 0;

        if ($nid > 0) {
            $stmt = $conn->prepare("
                UPDATE notifications SET is_read = 1
                WHERE notification_id = ? AND user_id = ?
            ");
            $stmt->bind_param('ii', $nid, $me);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                UPDATE notifications SET is_read = 1
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->bind_param('i', $me);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'create') {
        $target_id = (int) ($body['user_id']  ?? 0);
        $type      = trim($body['type']        ?? '');
        $title     = trim($body['title']       ?? '');
        $message   = trim($body['message']     ?? '');
        $link      = trim($body['link']        ?? '');

        if (!$target_id || !$type || !$title || !$message) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos.']);
            exit();
        }

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, link)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issss', $target_id, $type, $title, $message, $link);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        exit();
    }
}

echo json_encode(['success' => false, 'error' => 'Acción no reconocida.']);