<?php
// Historial del chatbot: agrupa mensajes en sesiones (gap > 30 min)

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT log_id, user_message, bot_response, created_at
    FROM chatbot_logs
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 120
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res  = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

$sessions   = [];
$current    = [];
$GAP_SECS   = 30 * 60;

foreach (array_reverse($rows) as $row) {
    if (empty($current)) {
        $current[] = $row;
    } else {
        $last = end($current);
        $diff = strtotime($row['created_at']) - strtotime($last['created_at']);
        if ($diff > $GAP_SECS) {
            $sessions[] = $current;
            $current    = [$row];
        } else {
            $current[] = $row;
        }
    }
}
if (!empty($current)) $sessions[] = $current;

$sessions = array_slice(array_reverse($sessions), 0, 3);

$output = [];
foreach ($sessions as $idx => $msgs) {
    $first     = $msgs[0];
    $last      = end($msgs);
    $date_raw  = $first['created_at'];
    $date_fmt  = date('d/m/Y', strtotime($date_raw));
    $time_fmt  = date('H:i',   strtotime($date_raw));

    $title = mb_substr($first['user_message'], 0, 45);
    if (mb_strlen($first['user_message']) > 45) $title .= '…';

    $messages = [];
    foreach ($msgs as $m) {
        $messages[] = [
            'user'      => $m['user_message'],
            'bot'       => $m['bot_response'],
            'time'      => date('H:i', strtotime($m['created_at'])),
        ];
    }

    $output[] = [
        'session_index' => $idx,
        'title'         => $title,
        'date'          => $date_fmt,
        'time'          => $time_fmt,
        'msg_count'     => count($msgs),
        'messages'      => $messages,
    ];
}

echo json_encode(['success' => true, 'sessions' => $output]);