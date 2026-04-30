<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
    exit;
}

require_once '../config/database.php';

$user_id    = $_SESSION['user_id'];
$comment_id = intval($_GET['comment_id'] ?? 0);
$post_id    = intval($_GET['post_id']    ?? 0);

if (!$comment_id || !$post_id) {
    echo json_encode(['success' => false, 'replies' => []]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        c.comment_id,
        c.content,
        c.likes_count,
        c.created_at,
        CONCAT(u.first_name, ' ', u.last_name) AS author_name
    FROM blog_comments c
    JOIN users u ON c.user_id = u.user_id
    WHERE c.parent_id = ? AND c.post_id = ? AND c.status = 'active'
    ORDER BY c.created_at ASC
");
$stmt->bind_param("ii", $comment_id, $post_id);
$stmt->execute();
$replies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
foreach ($replies as &$r) {
    $r['created_at'] = date('d M Y, H:i', strtotime($r['created_at']));
}

echo json_encode(['success' => true, 'replies' => $replies]);