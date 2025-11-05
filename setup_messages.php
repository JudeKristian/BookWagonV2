<?php
include("connect.php");

// Check if the user is logged in as admin
session_start();
if (!isset($_SESSION['id']) || $_SESSION['usertype'] !== 'admin') {
    echo "You must be logged in as an admin to run this script.";
    exit;
}

echo "<h2>Setting up messaging system tables...</h2>";

// Read and execute SQL from messages.sql file
try {
    $sql = file_get_contents('messages.sql');
    
    // Split SQL by semicolon to execute multiple statements
    $statements = array_filter(array_map('trim', explode(';', $sql)), 'strlen');
    
    foreach ($statements as $statement) {
        $result = $conn->query($statement);
        if (!$result) {
            throw new Exception($conn->error);
        }
    }
    
    echo "<p>Successfully created messaging system tables.</p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Add sample data if requested
if (isset($_GET['sample_data']) && $_GET['sample_data'] == 1) {
    try {
        echo "<p>Adding sample conversation data...</p>";
        
        // Create a conversation between user ID 1 and 2
        // (Update these IDs based on your actual user IDs)
        $userId1 = 1;
        $userId2 = 2;
        
        // Check if users exist
        $userCheckQuery = "SELECT COUNT(*) as count FROM users WHERE id IN (?, ?)";
        $stmt = $conn->prepare($userCheckQuery);
        $stmt->bind_param("ii", $userId1, $userId2);
        $stmt->execute();
        $userResult = $stmt->get_result()->fetch_assoc();
        
        if ($userResult['count'] < 2) {
            echo "<p>Error: Users with IDs $userId1 and $userId2 do not exist. Please use valid user IDs.</p>";
        } else {
            // Create conversation
            $conn->query("INSERT INTO conversations (created_at, updated_at) VALUES (NOW(), NOW())");
            $conversationId = $conn->insert_id;
            
            // Add participants
            $stmt = $conn->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $conversationId, $userId1);
            $stmt->execute();
            
            $stmt = $conn->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $conversationId, $userId2);
            $stmt->execute();
            
            // Add sample messages
            $messages = [
                [$userId1, "Hi there! I saw you're interested in books by J.K. Rowling?"],
                [$userId2, "Yes! I'm a big Harry Potter fan. Are you selling any of the books?"],
                [$userId1, "I have the complete set in hardcover, excellent condition."],
                [$userId2, "That sounds great! How much are you asking for the full set?"],
                [$userId1, "I'm thinking $65 for all 7 books. They're in really good shape."],
                [$userId2, "That's a fair price. Could I come take a look at them?"]
            ];
            
            $time = time() - (count($messages) * 300); // Start from 5 minutes ago per message
            
            $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, message_text, created_at) VALUES (?, ?, ?, FROM_UNIXTIME(?))");
            
            foreach ($messages as $message) {
                $stmt->bind_param("iisi", $conversationId, $message[0], $message[1], $time);
                $stmt->execute();
                $time += 300; // Add 5 minutes between messages
            }
            
            echo "<p>Sample conversation created successfully between users $userId1 and $userId2.</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>Error creating sample data: " . $e->getMessage() . "</p>";
    }
}

echo "<p><a href='index.php'>Return to homepage</a></p>";
?> 