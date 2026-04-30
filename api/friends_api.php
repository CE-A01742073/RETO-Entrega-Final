<?php

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit();
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');
$me     = (int) $_SESSION['user_id'];

function jsonOut(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit();
}


if ($action === 'send_request') {
    $target = (int)($body['target_id'] ?? 0);

    if ($target <= 0 || $target === $me) {
        jsonOut(false, 'ID de usuario inválido.');
    }



    $chk = $conn->prepare("
        SELECT friendship_id, status
        FROM friendships
        WHERE (requester_id = ? AND addressee_id = ?)
           OR (requester_id = ? AND addressee_id = ?)
        LIMIT 1
    ");
    $chk->bind_param("iiii", $me, $target, $target, $me);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($existing) {
        $map = ['pending' => 'Ya existe una solicitud pendiente.', 'accepted' => 'Ya son amigos.', 'rejected' => 'La solicitud fue rechazada anteriormente.'];
        jsonOut(false, $map[$existing['status']] ?? 'Ya existe una relación.');
    }

    $ins = $conn->prepare("INSERT INTO friendships (requester_id, addressee_id, status) VALUES (?, ?, 'pending')");
    $ins->bind_param("ii", $me, $target);
    $ins->execute();
    $ins->close();

    jsonOut(true, 'Solicitud enviada correctamente.');
}

if ($action === 'accept') {
    $fid = (int)($body['friendship_id'] ?? 0);

    $upd = $conn->prepare("
        UPDATE friendships SET status = 'accepted'
        WHERE friendship_id = ? AND addressee_id = ? AND status = 'pending'
    ");
    $upd->bind_param("ii", $fid, $me);
    $upd->execute();
    $affected = $upd->affected_rows;
    $upd->close();

    jsonOut($affected > 0, $affected > 0 ? 'Solicitud aceptada.' : 'No se pudo aceptar la solicitud.');
}

if ($action === 'reject') {
    $fid = (int)($body['friendship_id'] ?? 0);

    $upd = $conn->prepare("
        UPDATE friendships SET status = 'rejected'
        WHERE friendship_id = ? AND addressee_id = ? AND status = 'pending'
    ");
    $upd->bind_param("ii", $fid, $me);
    $upd->execute();
    $affected = $upd->affected_rows;
    $upd->close();

    jsonOut($affected > 0, $affected > 0 ? 'Solicitud rechazada.' : 'No se encontró la solicitud.');
}

if ($action === 'cancel') {
    $fid = (int)($body['friendship_id'] ?? 0);

    $del = $conn->prepare("
        DELETE FROM friendships
        WHERE friendship_id = ? AND requester_id = ? AND status = 'pending'
    ");
    $del->bind_param("ii", $fid, $me);
    $del->execute();
    $affected = $del->affected_rows;
    $del->close();

    jsonOut($affected > 0, $affected > 0 ? 'Solicitud cancelada.' : 'No se encontró la solicitud.');
}

if ($action === 'get_pending_count') {
    $cnt = $conn->prepare("
        SELECT COUNT(*) AS total FROM friendships
        WHERE addressee_id = ? AND status = 'pending'
    ");
    $cnt->bind_param("i", $me);
    $cnt->execute();
    $total = (int)$cnt->get_result()->fetch_assoc()['total'];
    $cnt->close();

    jsonOut(true, '', ['count' => $total]);
}

jsonOut(false, 'Acción no reconocida.');