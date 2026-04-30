<?php

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit();
}

$me = (int) $_SESSION['user_id'];

$pending = [];
$stmt = $conn->prepare("
    SELECT f.friendship_id,
           CONCAT(u.first_name,' ',u.last_name) AS name
    FROM friendships f
    JOIN users u ON u.user_id = f.requester_id
    WHERE f.addressee_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $me);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $pending[] = $r;
$stmt->close();

$friends = [];
$stmt = $conn->prepare("
    SELECT
        u.user_id,
        CONCAT(u.first_name,' ',u.last_name) AS name,
        COUNT(DISTINCT CASE WHEN e.status='completed' THEN e.course_id END) AS completed,
        COALESCE(ROUND(AVG(e.progress_percentage),0), 0)                    AS avg_progress
    FROM friendships f
    JOIN users u ON u.user_id = CASE
        WHEN f.requester_id = ? THEN f.addressee_id
        ELSE f.requester_id
    END
    LEFT JOIN course_enrollments e ON e.user_id = u.user_id
    WHERE (f.requester_id = ? OR f.addressee_id = ?) AND f.status = 'accepted'
    GROUP BY u.user_id
    ORDER BY name ASC
    LIMIT 20
");
$stmt->bind_param("iii", $me, $me, $me);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $friends[] = $r;
$stmt->close();

echo json_encode([
    'success' => true,
    'pending' => $pending,
    'friends' => $friends,
]);