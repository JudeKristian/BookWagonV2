<?php
session_start();
include("../connect.php");

// Set response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to manage notifications'
    ]);
    exit;
}

// Get current user ID
$currentUserId = $_SESSION['id'];

// Mark all notifications as read
$updateQuery = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("i", $currentUserId);

if ($stmt->execute()) {
    $affectedRows = $stmt->affected_rows;
    
    echo json_encode([
        'success' => true,
        'message' => $affectedRows . ' notification(s) marked as read',
        'count' => $affectedRows
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error marking notifications as read: ' . $conn->error
    ]);
}
?> 