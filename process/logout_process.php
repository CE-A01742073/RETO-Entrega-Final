<?php
session_start();
require_once '../config/database.php';
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    $stmt = $conn->prepare("UPDATE user_sessions SET is_active = FALSE WHERE session_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}
session_unset();
session_destroy();
header("Location: ../index.php");
exit();
?>