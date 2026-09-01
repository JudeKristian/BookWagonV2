<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Synchronize session user ID aliases for compatibility
if (isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    $_SESSION['id'] = $_SESSION['user_id'];
} elseif (isset($_SESSION['id']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['id'];
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    // Store the requested page in session to redirect back after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}
?>