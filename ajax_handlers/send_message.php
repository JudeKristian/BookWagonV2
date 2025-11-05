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
$senderId = $_SESSION['id'];

// Validate input
if (!isset($_POST['message']) || trim($_POST['message']) === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Message cannot be empty'
    ]);
    exit;
}

$messageText = trim($_POST['message']);
$conversationId = null;
$recipientId = null;

// Check if this is a new conversation or existing one
if (isset($_POST['conversation_id']) && is_numeric($_POST['conversation_id'])) {
    // Existing conversation
    $conversationId = intval($_POST['conversation_id']);
    
    // Verify user belongs to this conversation
    $checkQuery = "SELECT COUNT(*) as count FROM conversation_participants 
                  WHERE conversation_id = ? AND user_id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $conversationId, $senderId);
    $stmt->execute();
    $checkResult = $stmt->get_result()->fetch_assoc();
    
    if ($checkResult['count'] == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'You are not part of this conversation'
        ]);
        exit;
    }
} else if (isset($_POST['recipient_id']) && is_numeric($_POST['recipient_id'])) {
    // New conversation
    $recipientId = intval($_POST['recipient_id']);
    
    // Check if recipient exists
    $checkUserQuery = "SELECT id FROM users WHERE id = ?";
    $stmt = $conn->prepare($checkUserQuery);
    $stmt->bind_param("i", $recipientId);
    $stmt->execute();
    $userResult = $stmt->get_result();
    
    if ($userResult->num_rows == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Recipient not found'
        ]);
        exit;
    }
    
    // Check if conversation already exists between these users
    $checkConversationQuery = "SELECT c.id FROM conversations c
                             JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
                             JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
                             WHERE cp1.user_id = ? AND cp2.user_id = ?
                             LIMIT 1";
    $stmt = $conn->prepare($checkConversationQuery);
    $stmt->bind_param("ii", $senderId, $recipientId);
    $stmt->execute();
    $conversationResult = $stmt->get_result();
    
    if ($conversationResult->num_rows > 0) {
        // Conversation already exists
        $conversation = $conversationResult->fetch_assoc();
        $conversationId = $conversation['id'];
    } else {
        // Create new conversation
        $conn->begin_transaction();
        
        try {
            // Insert conversation
            $createConversationQuery = "INSERT INTO conversations (created_at) VALUES (NOW())";
            $stmt = $conn->prepare($createConversationQuery);
            $stmt->execute();
            $conversationId = $conn->insert_id;
            
            // Add participants
            $addParticipantQuery = "INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)";
            
            // Add sender
            $stmt = $conn->prepare($addParticipantQuery);
            $stmt->bind_param("ii", $conversationId, $senderId);
            $stmt->execute();
            
            // Add recipient
            $stmt = $conn->prepare($addParticipantQuery);
            $stmt->bind_param("ii", $conversationId, $recipientId);
            $stmt->execute();
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Error creating conversation: ' . $e->getMessage()
            ]);
            exit;
        }
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing conversation or recipient information'
    ]);
    exit;
}

// Insert the message
$insertMessageQuery = "INSERT INTO messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)";
$stmt = $conn->prepare($insertMessageQuery);
$stmt->bind_param("iis", $conversationId, $senderId, $messageText);

if ($stmt->execute()) {
    // Get the message ID
    $messageId = $conn->insert_id;
    
    // Update conversation timestamp
    $updateConversationQuery = "UPDATE conversations SET updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($updateConversationQuery);
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => $messageText,
        'time' => date('g:i a'),
        'conversation_id' => $conversationId,
        'message_id' => $messageId
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error sending message: ' . $conn->error
    ]);
}
?> 