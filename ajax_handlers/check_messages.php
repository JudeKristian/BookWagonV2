<?php
session_start();
include("../connect.php");

// Set response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to check messages'
    ]);
    exit;
}

$userId = $_SESSION['id'];
$conversationId = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
$lastMessageId = isset($_GET['last_message_id']) ? intval($_GET['last_message_id']) : 0;

// Validate input
if ($conversationId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid conversation ID'
    ]);
    exit;
}

// Verify user belongs to this conversation
$checkQuery = "SELECT COUNT(*) as count FROM conversation_participants 
              WHERE conversation_id = ? AND user_id = ?";
$stmt = $conn->prepare($checkQuery);
$stmt->bind_param("ii", $conversationId, $userId);
$stmt->execute();
$checkResult = $stmt->get_result()->fetch_assoc();

if ($checkResult['count'] == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'You are not part of this conversation'
    ]);
    exit;
}

// Get new messages
$newMessages = [];
$query = "SELECT m.*, u.firstname, u.lastname 
          FROM messages m
          JOIN users u ON m.sender_id = u.id
          WHERE m.conversation_id = ? AND m.id > ?
          ORDER BY m.created_at ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $conversationId, $lastMessageId);
$stmt->execute();
$result = $stmt->get_result();

while ($message = $result->fetch_assoc()) {
    $newMessages[] = [
        'id' => $message['id'],
        'text' => $message['message_text'],
        'sender_id' => $message['sender_id'],
        'sender_name' => $message['firstname'] . ' ' . $message['lastname'],
        'is_current_user' => ($message['sender_id'] == $userId),
        'time' => date('g:i a', strtotime($message['created_at'])),
        'created_at' => $message['created_at']
    ];
}

// Mark messages as read
if (count($newMessages) > 0) {
    $markReadQuery = "UPDATE messages SET is_read = 1 
                    WHERE conversation_id = ? AND sender_id != ? AND is_read = 0";
    $stmt = $conn->prepare($markReadQuery);
    $stmt->bind_param("ii", $conversationId, $userId);
    $stmt->execute();
}

echo json_encode([
    'success' => true,
    'messages' => $newMessages,
    'count' => count($newMessages)
]);
?> 