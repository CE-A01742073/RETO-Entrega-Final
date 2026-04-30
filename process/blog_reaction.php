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

$type = $body['type'] ?? '';  
$id   = intval($body['id']  ?? 0);

if (!in_array($type, ['post', 'comment']) || !$id) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
    exit;
}
$check = $conn->prepare("
    SELECT reaction_id FROM blog_reactions
    WHERE user_id = ? AND target_type = ? AND target_id = ?
");
$check->bind_param("isi", $user_id, $type, $id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

$liked = false;

if ($existing) {
    $del = $conn->prepare("DELETE FROM blog_reactions WHERE reaction_id = ?");
    $del->bind_param("i", $existing['reaction_id']);
    $del->execute();
    $del->close();
    if ($type === 'post') {
        $conn->query("UPDATE blog_posts SET likes_count = GREATEST(0, likes_count - 1) WHERE post_id = $id");
    } else {
        $conn->query("UPDATE blog_comments SET likes_count = GREATEST(0, likes_count - 1) WHERE comment_id = $id");
    }

    $liked = false;
} else {
    $ins = $conn->prepare("
        INSERT INTO blog_reactions (user_id, target_type, target_id)
        VALUES (?, ?, ?)
    ");
    $ins->bind_param("isi", $user_id, $type, $id);
    $ins->execute();
    $ins->close();
    if ($type === 'post') {
        $conn->query("UPDATE blog_posts SET likes_count = likes_count + 1 WHERE post_id = $id");
    } else {
        $conn->query("UPDATE blog_comments SET likes_count = likes_count + 1 WHERE comment_id = $id");
    }

    $liked = true;
}
if ($type === 'post') {
    $count_res  = $conn->query("SELECT likes_count FROM blog_posts    WHERE post_id    = $id");
} else {
    $count_res  = $conn->query("SELECT likes_count FROM blog_comments WHERE comment_id = $id");
}

$likes_count = $count_res->fetch_assoc()['likes_count'];
$conn->close();

echo json_encode([
    'success'     => true,
    'liked'       => $liked,
    'likes_count' => $likes_count,
]);