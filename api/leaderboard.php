<?php

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$tab     = $_GET['tab']   ?? 'global';   // 'global' | 'friends'
$limit   = min((int)($_GET['limit'] ?? 5), 20);

$streak_sql = "
    SELECT
        COALESCE(current_streak, 0)  AS current_streak,
        COALESCE(longest_streak, 0)  AS longest_streak,
        last_activity_date
    FROM user_streaks
    WHERE user_id = ?
";
$s = $conn->prepare($streak_sql);
$s->bind_param('i', $user_id);
$s->execute();
$streak = $s->get_result()->fetch_assoc() ?? [
    'current_streak'     => 0,
    'longest_streak'     => 0,
    'last_activity_date' => null,
];
$s->close();

$week_activity = array_fill(0, 7, false); // índice 0=lunes … 6=domingo
$week_sql = "
    SELECT DISTINCT DATE(awarded_at) AS act_date
    FROM user_xp
    WHERE user_id = ?
      AND awarded_at >= DATE_FORMAT(CURDATE(), '%Y-%m-%d') - INTERVAL WEEKDAY(CURDATE()) DAY
";
$w = $conn->prepare($week_sql);
$w->bind_param('i', $user_id);
$w->execute();
$week_res = $w->get_result();
while ($row = $week_res->fetch_assoc()) {
    $dow = (int) date('N', strtotime($row['act_date'])) - 1; // 0=lun
    if ($dow >= 0 && $dow <= 6) {
        $week_activity[$dow] = true;
    }
}
$w->close();

if ($tab === 'friends') {
    $ranking_sql = "
        SELECT
            u.user_id,
            CONCAT(u.first_name, ' ', u.last_name) AS full_name,
            COALESCE(SUM(x.xp_points), 0) AS total_xp,
            COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.course_id END) AS completed_courses,
            COALESCE(st.current_streak, 0) AS current_streak
        FROM users u
        LEFT JOIN user_xp x             ON u.user_id = x.user_id
        LEFT JOIN course_enrollments e  ON u.user_id = e.user_id
        LEFT JOIN user_streaks st       ON u.user_id = st.user_id
        WHERE u.user_id = ?
           OR u.user_id IN (
               SELECT CASE
                   WHEN sender_id   = ? THEN receiver_id
                   WHEN receiver_id = ? THEN sender_id
               END
               FROM friendships
               WHERE (sender_id = ? OR receiver_id = ?)
                 AND status = 'accepted'
           )
        GROUP BY u.user_id, u.first_name, u.last_name, st.current_streak
        ORDER BY total_xp DESC, completed_courses DESC
        LIMIT ?
    ";
    $r = $conn->prepare($ranking_sql);
    $r->bind_param('iiiiii', $user_id, $user_id, $user_id, $user_id, $user_id, $limit);
} else {
    $ranking_sql = "
        SELECT
            u.user_id,
            CONCAT(u.first_name, ' ', u.last_name) AS full_name,
            COALESCE(SUM(x.xp_points), 0) AS total_xp,
            COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.course_id END) AS completed_courses,
            COALESCE(st.current_streak, 0) AS current_streak
        FROM users u
        LEFT JOIN user_xp x             ON u.user_id = x.user_id
        LEFT JOIN course_enrollments e  ON u.user_id = e.user_id
        LEFT JOIN user_streaks st       ON u.user_id = st.user_id
        WHERE u.role != 'admin'
        GROUP BY u.user_id, u.first_name, u.last_name, st.current_streak
        ORDER BY total_xp DESC, completed_courses DESC
        LIMIT ?
    ";
    $r = $conn->prepare($ranking_sql);
    $r->bind_param('i', $limit);
}

$r->execute();
$ranking_result = $r->get_result();
$ranking = [];
$position = 1;
$my_position = null;

while ($row = $ranking_result->fetch_assoc()) {
    $is_me = ((int)$row['user_id'] === $user_id);
    if ($is_me) $my_position = $position;

    $parts    = explode(' ', trim($row['full_name']));
    $initials = strtoupper(
        substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1)
    );

    $ranking[] = [
        'position'          => $position,
        'user_id'           => (int) $row['user_id'],
        'full_name'         => $row['full_name'],
        'initials'          => $initials,
        'total_xp'          => (int) $row['total_xp'],
        'completed_courses' => (int) $row['completed_courses'],
        'current_streak'    => (int) $row['current_streak'],
        'is_me'             => $is_me,
    ];
    $position++;
}
$r->close();
$conn->close();

echo json_encode([
    'streak'      => [
        'current'       => (int) $streak['current_streak'],
        'longest'       => (int) $streak['longest_streak'],
        'week_activity' => $week_activity,
    ],
    'ranking'     => $ranking,
    'my_position' => $my_position,
    'tab'         => $tab,
]);