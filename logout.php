<?php
// Initialize the session
session_start();

if (isset($_SESSION['user_id'])) {
    require_once 'connect.php';
    require_once 'includes/audit_logger.php';
    log_activity($_SESSION['user_id'], 'Logout', 'User logged out.');
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("location: index.php");
exit;
?>