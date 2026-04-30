<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
$_allowed_roles = ['admin', 'instructor'];
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $_allowed_roles)) {
    header("Location: ../courses.php");
    exit();
}
?>