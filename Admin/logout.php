<?php
// Initialize the session
session_start();
 
if (isset($_SESSION['admin_id'])) {
    require_once 'db_connect.php';
    require_once '../includes/audit_logger.php';
    log_activity($_SESSION['admin_id'], 'Admin Logout', 'Admin logged out.');
}
 
// Unset all of the session variables
$_SESSION = array();
 
// Destroy the session.
session_destroy();
 
// Redirect to login page
header("location: index.php");
exit;
?>