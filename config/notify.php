<?php

function notify(mysqli $conn, int $user_id, string $type, string $title, string $message, string $link = ''): void
{
    if ($user_id <= 0) return;

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, link)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('issss', $user_id, $type, $title, $message, $link);
    $stmt->execute();
    $stmt->close();
}