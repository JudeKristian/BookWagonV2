<?php
session_start();
include("../connect.php");

// Set response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to add book buddies'
    ]);
    exit;
}

// Get current user ID
$currentUserId = $_SESSION['id'];

// Validate input
if (!isset($_POST['user_id']) || !isset($_POST['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$targetUserId = intval($_POST['user_id']);
$action = $_POST['action'];

// Validate action
if (!in_array($action, ['request', 'accept', 'reject', 'cancel', 'unfollow'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
    exit;
}

// Don't allow users to follow themselves
if ($currentUserId === $targetUserId) {
    echo json_encode([
        'success' => false,
        'message' => 'You cannot add yourself as a book buddy'
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

// Create book_buddies table if it doesn't exist
$checkTableQuery = "SHOW TABLES LIKE 'book_buddies'";
$tableExists = $conn->query($checkTableQuery);

if ($tableExists->num_rows == 0) {
    $createTableQuery = "CREATE TABLE book_buddies (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        follower_id INT(11) NOT NULL,
        following_id INT(11) NOT NULL,
        status ENUM('pending', 'accepted') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_relationship (follower_id, following_id)
    )";
    
    if (!$conn->query($createTableQuery)) {
        echo json_encode([
            'success' => false,
            'message' => 'Error creating book buddies table: ' . $conn->error
        ]);
        exit;
    }
}

// Create notifications table if it doesn't exist
$checkNotifTableQuery = "SHOW TABLES LIKE 'notifications'";
$notifTableExists = $conn->query($checkNotifTableQuery);

if ($notifTableExists->num_rows == 0) {
    $createNotifTableQuery = "CREATE TABLE notifications (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        sender_id INT(11) NOT NULL,
        type VARCHAR(50) NOT NULL,
        content TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($createNotifTableQuery)) {
        echo json_encode([
            'success' => false,
            'message' => 'Error creating notifications table: ' . $conn->error
        ]);
        exit;
    }
}

// Handle the action
switch ($action) {
    case 'request':
        // Check if a request already exists
        $checkQuery = "SELECT id, status FROM book_buddies WHERE follower_id = ? AND following_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("ii", $currentUserId, $targetUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $relationship = $result->fetch_assoc();
            if ($relationship['status'] === 'accepted') {
                echo json_encode([
                    'success' => true,
                    'message' => 'Already a book buddy',
                    'status' => 'following'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Request already sent',
                    'status' => 'pending'
                ]);
            }
            exit;
        }
        
        // Also check if the target user has already requested to follow the current user
        $checkReverseQuery = "SELECT id FROM book_buddies WHERE follower_id = ? AND following_id = ?";
        $stmt = $conn->prepare($checkReverseQuery);
        $stmt->bind_param("ii", $targetUserId, $currentUserId);
        $stmt->execute();
        $reverseResult = $stmt->get_result();
        
        if ($reverseResult->num_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'This user has already sent you a buddy request. Check your notifications.',
                'status' => 'reverse_pending'
            ]);
            exit;
        }
        
        // Send buddy request
        $conn->begin_transaction();
        
        try {
            // Add pending book buddy relationship
            $requestQuery = "INSERT INTO book_buddies (follower_id, following_id, status) VALUES (?, ?, 'pending')";
            $stmt = $conn->prepare($requestQuery);
            $stmt->bind_param("ii", $currentUserId, $targetUserId);
            $stmt->execute();
            
            // Get sender's name for notification
            $senderQuery = "SELECT firstname, lastname FROM users WHERE id = ?";
            $stmt = $conn->prepare($senderQuery);
            $stmt->bind_param("i", $currentUserId);
            $stmt->execute();
            $senderResult = $stmt->get_result();
            $sender = $senderResult->fetch_assoc();
            
            // Create notification
            $notificationContent = $sender['firstname'] . ' ' . $sender['lastname'] . ' has sent you a book buddy request';
            $notifQuery = "INSERT INTO notifications (user_id, sender_id, type, content) VALUES (?, ?, 'buddy_request', ?)";
            $stmt = $conn->prepare($notifQuery);
            $stmt->bind_param("iis", $targetUserId, $currentUserId, $notificationContent);
            $stmt->execute();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Book buddy request sent',
                'status' => 'pending'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Error sending book buddy request: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'accept':
        // Check if request exists
        $checkQuery = "SELECT id FROM book_buddies WHERE follower_id = ? AND following_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("ii", $targetUserId, $currentUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No pending request found'
            ]);
            exit;
        }
        
        $conn->begin_transaction();
        
        try {
            // Update buddy status to accepted
            $acceptQuery = "UPDATE book_buddies SET status = 'accepted' WHERE follower_id = ? AND following_id = ?";
            $stmt = $conn->prepare($acceptQuery);
            $stmt->bind_param("ii", $targetUserId, $currentUserId);
            $stmt->execute();
            
            // Get current user's name for notification
            $userQuery = "SELECT firstname, lastname FROM users WHERE id = ?";
            $stmt = $conn->prepare($userQuery);
            $stmt->bind_param("i", $currentUserId);
            $stmt->execute();
            $userResult = $stmt->get_result();
            $user = $userResult->fetch_assoc();
            
            // Create notification
            $notificationContent = $user['firstname'] . ' ' . $user['lastname'] . ' has accepted your book buddy request';
            $notifQuery = "INSERT INTO notifications (user_id, sender_id, type, content) VALUES (?, ?, 'buddy_accepted', ?)";
            $stmt = $conn->prepare($notifQuery);
            $stmt->bind_param("iis", $targetUserId, $currentUserId, $notificationContent);
            $stmt->execute();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Book buddy request accepted',
                'status' => 'accepted'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Error accepting book buddy request: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'reject':
        // Delete the pending request
        $rejectQuery = "DELETE FROM book_buddies WHERE follower_id = ? AND following_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($rejectQuery);
        $stmt->bind_param("ii", $targetUserId, $currentUserId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Book buddy request rejected',
                'status' => 'rejected'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error rejecting book buddy request: ' . $conn->error
            ]);
        }
        break;
        
    case 'cancel':
        // Cancel a pending request
        $cancelQuery = "DELETE FROM book_buddies WHERE follower_id = ? AND following_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($cancelQuery);
        $stmt->bind_param("ii", $currentUserId, $targetUserId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Book buddy request cancelled',
                'status' => 'cancelled'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error cancelling book buddy request: ' . $conn->error
            ]);
        }
        break;
        
    case 'unfollow':
        // Remove book buddy relationship
        $unfollowQuery = "DELETE FROM book_buddies WHERE follower_id = ? AND following_id = ? AND status = 'accepted'";
        $stmt = $conn->prepare($unfollowQuery);
        $stmt->bind_param("ii", $currentUserId, $targetUserId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Book buddy removed',
                'status' => 'unfollowed'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error removing book buddy: ' . $conn->error
            ]);
        }
        break;
}
?> 