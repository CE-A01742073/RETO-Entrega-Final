<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$body    = json_decode(file_get_contents('php://input'), true) ?? [];

$post_id   = intval($body['post_id']   ?? 0);
$content   = trim($body['content']     ?? '');
$parent_id = isset($body['parent_id']) && $body['parent_id'] !== null
             ? intval($body['parent_id'])
             : null;

if (!$post_id || !$content) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
    exit;
}

if (mb_strlen($content) > 2000) {
    echo json_encode(['success' => false, 'message' => 'El comentario excede el límite de 2000 caracteres.']);
    exit;
}
$check = $conn->prepare("SELECT post_id FROM blog_posts WHERE post_id = ? AND status = 'published'");
$check->bind_param("i", $post_id);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Publicación no encontrada.']);
    exit;
}
$check->close();
if ($parent_id !== null) {
    $parent_check = $conn->prepare("SELECT comment_id FROM blog_comments WHERE comment_id = ? AND post_id = ?");
    $parent_check->bind_param("ii", $parent_id, $post_id);
    $parent_check->execute();
    if (!$parent_check->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Comentario padre inválido.']);
        exit;
    }
    $parent_check->close();
}
$stmt = $conn->prepare("
    INSERT INTO blog_comments (post_id, user_id, parent_id, content, status)
    VALUES (?, ?, ?, ?, 'active')
");
$stmt->bind_param("iiis", $post_id, $user_id, $parent_id, $content);
$stmt->execute();
$comment_id = $stmt->insert_id;
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'comment' => [
        'comment_id' => $comment_id,
        'content'    => $content,
        'parent_id'  => $parent_id,
        'created_at' => date('d M Y, H:i'),
    ]
]);