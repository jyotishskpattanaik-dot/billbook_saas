<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in, redirect to login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    header("Location: /billbook.in/public/login.php");
    exit;
}

// For easy access in all files
$logged_in_user_id   = $_SESSION['user_id'];
$logged_in_user_name = $_SESSION['user_name'];
$logged_in_user_role = $_SESSION['user_module'];
?>
