<?php
session_start();
include("../connect.php");

// Set response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to send messages'
    ]);
    exit;
}

// Get current user ID
$userId = $_SESSION['id'];

// Validate input
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID'
    ]);
    exit;
}

$targetUserId = intval($_GET['user_id']);

// Don't allow users to message themselves
if ($userId === $targetUserId) {
    echo json_encode([
        'success' => false,
        'message' => 'You cannot message yourself'
    ]);
    exit;
}

// Check if target user exists
$checkUserQuery = "SELECT id FROM users WHERE id = ?";
$stmt = $conn->prepare($checkUserQuery);
$stmt->bind_param("i", $targetUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'User not found'
    ]);
    exit;
}

// Check if conversation already exists between these users
$conversationQuery = "SELECT c.id FROM conversations c
                     JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
                     JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
                     WHERE cp1.user_id = ? AND cp2.user_id = ?
                     LIMIT 1";
$stmt = $conn->prepare($conversationQuery);
$stmt->bind_param("ii", $userId, $targetUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Conversation already exists, return the ID
    $conversation = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversation['id'],
        'exists' => true
    ]);
    exit;
}

// Create new conversation
$conn->begin_transaction();

try {
    // Insert conversation
    $createConversationQuery = "INSERT INTO conversations (created_at, updated_at) VALUES (NOW(), NOW())";
    $stmt = $conn->prepare($createConversationQuery);
    $stmt->execute();
    $conversationId = $conn->insert_id;
    
    // Add participants
    $addParticipantQuery = "INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)";
    
    // Add current user
    $stmt = $conn->prepare($addParticipantQuery);
    $stmt->bind_param("ii", $conversationId, $userId);
    $stmt->execute();
    
    // Add target user
    $stmt = $conn->prepare($addParticipantQuery);
    $stmt->bind_param("ii", $conversationId, $targetUserId);
    $stmt->execute();
    
    $conn->commit();
    
    // Return the new conversation ID
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversationId,
        'exists' => false
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error creating conversation: ' . $e->getMessage()
    ]);
} 