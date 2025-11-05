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

// Validate input
if (!isset($_POST['notification_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing notification ID'
    ]);
    exit;
}

$notificationId = intval($_POST['notification_id']);

// Make sure the notification belongs to the current user
$checkQuery = "SELECT id FROM notifications WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($checkQuery);
$stmt->bind_param("ii", $notificationId, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Notification not found or does not belong to you'
    ]);
    exit;
}

// Mark notification as read
$updateQuery = "UPDATE notifications SET is_read = 1 WHERE id = ?";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("i", $notificationId);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error marking notification as read: ' . $conn->error
    ]);
}
?> 