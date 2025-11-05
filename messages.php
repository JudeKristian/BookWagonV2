<?php
include("session.php");
include("connect.php");

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['id'];
$userType = $_SESSION['usertype'] ?? '';

// Get all conversations for the current user
$conversations = [];
$query = "SELECT c.id, c.updated_at, 
          cp.user_id as participant_id,
          u.firstname, u.lastname, u.profile_picture,
          (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND is_read = 0) as unread_count,
          (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
          (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
          FROM conversations c
          JOIN conversation_participants cp ON c.id = cp.conversation_id
          JOIN users u ON cp.user_id = u.id
          WHERE c.id IN (SELECT conversation_id FROM conversation_participants WHERE user_id = ?)
          AND cp.user_id != ?
          ORDER BY c.updated_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
}

// Get book buddies who don't have active conversations
$bookBuddies = [];
$buddyQuery = "SELECT u.id, u.firstname, u.lastname, u.profile_picture
               FROM book_buddies b
               JOIN users u ON (b.follower_id = u.id OR b.following_id = u.id)
               WHERE (b.follower_id = ? OR b.following_id = ?)
               AND u.id != ?
               AND b.status = 'accepted'
               AND u.id NOT IN (
                   SELECT cp.user_id
                   FROM conversation_participants cp
                   JOIN conversations c ON cp.conversation_id = c.id
                   WHERE c.id IN (SELECT conversation_id FROM conversation_participants WHERE user_id = ?)
                   AND cp.user_id != ?
               )";
$stmt = $conn->prepare($buddyQuery);
$stmt->bind_param("iiiii", $userId, $userId, $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $bookBuddies[] = $row;
}

// Get active conversation if there's a conversation_id in URL
$activeConversation = null;
$messages = [];
$otherUser = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $conversationId = $_GET['id'];
    
    // Verify user belongs to this conversation
    $checkQuery = "SELECT COUNT(*) as count FROM conversation_participants 
                  WHERE conversation_id = ? AND user_id = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $conversationId, $userId);
    $stmt->execute();
    $checkResult = $stmt->get_result()->fetch_assoc();
    
    if ($checkResult['count'] > 0) {
        // Get conversation details
        $activeConversation = $conversationId;
        
        // Get other participant details
        $participantQuery = "SELECT u.id, u.firstname, u.lastname, u.profile_picture, u.username 
                           FROM users u
                           JOIN conversation_participants cp ON u.id = cp.user_id
                           WHERE cp.conversation_id = ? AND u.id != ?";
        $stmt = $conn->prepare($participantQuery);
        $stmt->bind_param("ii", $conversationId, $userId);
        $stmt->execute();
        $otherUser = $stmt->get_result()->fetch_assoc();
        
        // Get messages
        $messagesQuery = "SELECT m.*, u.firstname, u.lastname, u.profile_picture 
                        FROM messages m
                        JOIN users u ON m.sender_id = u.id
                        WHERE m.conversation_id = ?
                        ORDER BY m.created_at ASC";
        $stmt = $conn->prepare($messagesQuery);
        $stmt->bind_param("i", $conversationId);
        $stmt->execute();
        $messagesResult = $stmt->get_result();
        
        while ($message = $messagesResult->fetch_assoc()) {
            $messages[] = $message;
        }
        
        // Mark messages as read
        $markReadQuery = "UPDATE messages SET is_read = 1 
                        WHERE conversation_id = ? AND sender_id != ? AND is_read = 0";
        $stmt = $conn->prepare($markReadQuery);
        $stmt->bind_param("ii", $conversationId, $userId);
        $stmt->execute();
    }
}

// Helper function for time formatting
function timeAgo($dateString) {
    if (empty($dateString)) return '';
    
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $date->getTimestamp();
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ($minutes == 1 ? ' min' : ' mins');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ($hours == 1 ? ' hour' : ' hours');
    } elseif ($diff < 604800) { // 7 days
        $days = floor($diff / 86400);
        return $days . ($days == 1 ? ' day' : ' days');
    } else {
        return $date->format('M j');
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - BookWagon</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #f8a100;
            --primary-dark: #e09000;
            --primary-light: #fff4e0;
            --text-dark: #333333;
            --text-light: #6c757d;
            --border-color: #e9ecef;
            --bg-light: #f8f9fa;
            --sent-bg: #dcf8c6;
            --received-bg: #f0f0f0;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: var(--text-dark);
        }
         .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            z-index: 1050; /* Higher z-index than other elements */
        }
        
        .navbar-brand img {
            height: 60px;
        }
        
        .messages-container {
            max-width: 1200px;
            height: calc(100vh - 130px);
            margin: 0 auto;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .sidebar {
            border-right: 1px solid var(--border-color);
            height: 100%;
            overflow-y: auto;
        }
        
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: #fff;
        }
        
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .conversation-item:hover {
            background-color: var(--bg-light);
        }
        
        .conversation-item.active {
            background-color: var(--primary-light);
        }
        
        .conversation-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            margin-right: 15px;
            overflow: hidden;
            background-color: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .default-avatar {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #736029;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .conversation-info {
            flex-grow: 1;
            overflow: hidden;
        }
        
        .conversation-name {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .conversation-last-message {
            color: var(--text-light);
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-time {
            color: var(--text-light);
            font-size: 0.75rem;
            white-space: nowrap;
            margin-left: 10px;
        }
        
        .unread-badge {
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            margin-left: 10px;
        }
        
        .chat-area {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-light);
            padding: 20px;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        .message-list {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .message-item {
            max-width: 70%;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        
        .message-item.sent {
            align-self: flex-end;
        }
        
        .message-item.received {
            align-self: flex-start;
        }
        
        .message-bubble {
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }
        
        .message-item.sent .message-bubble {
            background-color: var(--sent-bg);
            border-bottom-right-radius: 5px;
        }
        
        .message-item.received .message-bubble {
            background-color: var(--received-bg);
            border-bottom-left-radius: 5px;
        }
        
        .message-time {
            font-size: 0.7rem;
            color: var(--text-light);
            align-self: flex-end;
            margin-top: 2px;
        }
        
        .message-input-container {
            padding: 15px;
            border-top: 1px solid var(--border-color);
            background-color: #fff;
            display: flex;
            align-items: center;
        }
        
        .message-input {
            flex-grow: 1;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 10px 15px;
            outline: none;
            resize: none;
            max-height: 100px;
            overflow-y: auto;
        }
        
        .send-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .send-button:hover {
            background-color: var(--primary-dark);
        }
        
        .chat-header-info {
            display: flex;
            align-items: center;
        }
        
        .chat-header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .chat-header-name {
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .no-conversations {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-light);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .chat-area {
                width: 100%;
            }
            
            .message-item {
                max-width: 85%;
            }
        }
        
        .sidebar-section-header {
            background-color: var(--bg-light);
            font-weight: 600;
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
            margin-top: 10px;
        }
        
        .buddy-item {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .buddy-item:hover {
            background-color: var(--bg-light);
        }
        
        .buddy-item.active {
            background-color: var(--primary-light);
        }
        
        .buddy-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
            overflow: hidden;
            background-color: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
        }
        
        .buddy-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .buddy-info {
            flex-grow: 1;
            overflow: hidden;
        }
        
        .buddy-name {
            font-weight: 600;
            margin-bottom: 2px;
            font-size: 0.9rem;
        }
        
        .buddy-action {
            color: var(--text-light);
            font-size: 0.75rem;
        }
        
        .online-status {
            color: #28a745;
            font-size: 0.75rem;
        }
        
        .offline-status {
            color: var(--text-light);
            font-size: 0.75rem;
        }
        
        .new-message-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .new-message-btn:hover {
            background-color: var(--primary-dark);
            color: white;
        }
        
        .new-message-dropdown {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .search-users-input {
            border-radius: 20px;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            font-size: 0.9rem;
        }
        
        .search-users-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.15rem rgba(248, 161, 0, 0.25);
        }
        
        .quick-search-user-item {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .quick-search-user-item:hover {
            background-color: var(--bg-light);
        }
        
        .quick-search-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 10px;
            overflow: hidden;
            background-color: var(--bg-light);
            flex-shrink: 0;
        }
        
        .quick-search-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .quick-search-info {
            flex-grow: 1;
            overflow: hidden;
        }
        
        .quick-search-name {
            font-weight: 500;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .quick-search-username {
            color: var(--text-light);
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php 
    if ($userType == 'user') {
        include("include/user_header.php");
    } elseif ($userType == 'seller') {
        include("include/seller_header.php");
    }
    ?>

    <div class="container messages-container mt-4">
        <div class="row h-100">
            <!-- Conversations Sidebar -->
            <div class="col-md-4 col-lg-3 p-0 sidebar">
                <div class="chat-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Messages</h5>
                    <div class="dropdown">
                        <button class="new-message-btn" type="button" id="newMessageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-plus"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-3 new-message-dropdown" aria-labelledby="newMessageDropdown" style="width: 300px;">
                            <h6 class="dropdown-header">New Message</h6>
                            <div class="mb-3">
                                <input type="text" class="form-control form-control-sm search-users-input" id="quickUserSearch" placeholder="Search for people...">
                            </div>
                            <div class="quick-search-results" style="max-height: 300px; overflow-y: auto;">
                                <!-- Results will be loaded here via AJAX -->
                                <div class="text-muted text-center py-2 small">
                                    Type to search for people
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (count($conversations) > 0): ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <a href="messages.php?id=<?php echo $conversation['id']; ?>" class="text-decoration-none">
                            <div class="conversation-item <?php echo ($activeConversation == $conversation['id']) ? 'active' : ''; ?>">
                                <div class="conversation-avatar">
                                    <?php if ($conversation['profile_picture'] && file_exists($conversation['profile_picture'])): ?>
                                        <img src="<?php echo $conversation['profile_picture']; ?>" alt="Avatar">
                                    <?php else: ?>
                                        <div class="default-avatar">
                                            <?php echo substr($conversation['firstname'], 0, 1); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="conversation-info">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="conversation-name"><?php echo $conversation['firstname'] . ' ' . $conversation['lastname']; ?></div>
                                        <div class="conversation-time"><?php echo timeAgo($conversation['last_message_time']); ?></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="conversation-last-message"><?php echo htmlspecialchars(substr($conversation['last_message'], 0, 30)) . (strlen($conversation['last_message']) > 30 ? '...' : ''); ?></div>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <div class="unread-badge"><?php echo $conversation['unread_count']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Book Buddies Section -->
                <?php if (count($bookBuddies) > 0): ?>
                    <div class="sidebar-section-header">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2">
                            <h6 class="mb-0">Book Buddies</h6>
                            <a href="find_users.php" class="text-muted" title="Find more buddies">
                                <i class="fas fa-search"></i>
                            </a>
                        </div>
                    </div>
                    
                    <?php foreach ($bookBuddies as $buddy): ?>
                        <div class="buddy-item" data-user-id="<?php echo $buddy['id']; ?>">
                            <div class="buddy-avatar">
                                <?php if ($buddy['profile_picture'] && file_exists($buddy['profile_picture'])): ?>
                                    <img src="<?php echo $buddy['profile_picture']; ?>" alt="Avatar">
                                <?php else: ?>
                                    <div class="default-avatar">
                                        <?php echo substr($buddy['firstname'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="buddy-info">
                                <div class="buddy-name"><?php echo $buddy['firstname'] . ' ' . $buddy['lastname']; ?></div>
                                <div class="buddy-action">
                                    <?php 
                                    // Check if online - this is just a placeholder, you may implement actual online status
                                    $isOnline = (rand(0, 1) == 1); 
                                    if ($isOnline): 
                                    ?>
                                        <span class="online-status">
                                            <i class="fas fa-circle text-success me-1"></i> Online
                                        </span>
                                    <?php else: ?>
                                        <span class="offline-status text-muted">
                                            <i class="far fa-clock me-1"></i> Last seen recently
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (count($conversations) == 0 && count($bookBuddies) == 0): ?>
                    <div class="no-conversations">
                        <p>No conversations yet</p>
                        <small>Your messages with other users will appear here</small>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Chat Area -->
            <div class="col-md-8 col-lg-9 p-0 chat-area">
                <?php if ($activeConversation): ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="chat-header-info">
                            <div class="chat-header-avatar">
                                <?php if ($otherUser['profile_picture'] && file_exists($otherUser['profile_picture'])): ?>
                                    <img src="<?php echo $otherUser['profile_picture']; ?>" alt="Avatar" class="img-fluid rounded-circle">
                                <?php else: ?>
                                    <div class="default-avatar">
                                        <?php echo substr($otherUser['firstname'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h6 class="chat-header-name"><?php echo $otherUser['firstname'] . ' ' . $otherUser['lastname']; ?></h6>
                                <?php if (isset($otherUser['username']) && !empty($otherUser['username'])): ?>
                                    <small class="text-muted">@<?php echo $otherUser['username']; ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Messages List -->
                    <div class="message-list" id="messageList">
                        <?php foreach ($messages as $message): ?>
                            <div class="message-item <?php echo ($message['sender_id'] == $userId) ? 'sent' : 'received'; ?>">
                                <div class="message-bubble">
                                    <?php echo nl2br(htmlspecialchars($message['message_text'])); ?>
                                </div>
                                <div class="message-time">
                                    <?php echo date('g:i a', strtotime($message['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Message Input -->
                    <div class="message-input-container">
                        <form id="messageForm" method="post" action="ajax_handlers/send_message.php" class="d-flex w-100">
                            <input type="hidden" name="conversation_id" value="<?php echo $activeConversation; ?>">
                            <input type="hidden" name="recipient_id" value="<?php echo $otherUser['id']; ?>">
                            <textarea class="message-input" name="message" placeholder="Type a message..." rows="1" required></textarea>
                            <button type="submit" class="send-button">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <i class="far fa-comments"></i>
                        <h4>No conversation selected</h4>
                        <p>Select a conversation from the sidebar or start a new one</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Scroll to bottom of message list
        const messageList = document.getElementById('messageList');
        if (messageList) {
            messageList.scrollTop = messageList.scrollHeight;
        }
        
        // Auto-resize textarea
        const messageInput = document.querySelector('.message-input');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }
        
        // Handle message submission with AJAX
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear input
                        messageInput.value = '';
                        messageInput.style.height = 'auto';
                        
                        // Append new message to chat
                        const messageItem = document.createElement('div');
                        messageItem.className = 'message-item sent';
                        
                        const messageBubble = document.createElement('div');
                        messageBubble.className = 'message-bubble';
                        messageBubble.textContent = data.message;
                        
                        const messageTime = document.createElement('div');
                        messageTime.className = 'message-time';
                        messageTime.textContent = data.time;
                        
                        messageItem.appendChild(messageBubble);
                        messageItem.appendChild(messageTime);
                        messageList.appendChild(messageItem);
                        
                        // Scroll to the new message
                        messageList.scrollTop = messageList.scrollHeight;
                        
                        // Update last message ID for real-time updates
                        lastMessageId = data.message_id || lastMessageId;
                    } else {
                        alert('Error sending message: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while sending the message.');
                });
            });
        }
        
        // Handle Book Buddy click to start conversation
        document.querySelectorAll('.buddy-item').forEach(item => {
            item.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                if (!userId) return;
                
                // Show loading overlay or indicator
                document.body.style.cursor = 'wait';
                
                // Get or create conversation with this user
                fetch(`ajax_handlers/get_or_create_conversation.php?user_id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Redirect to the conversation
                            window.location.href = `messages.php?id=${data.conversation_id}`;
                        } else {
                            alert('Error: ' + data.message);
                            document.body.style.cursor = 'default';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while processing your request.');
                        document.body.style.cursor = 'default';
                    });
            });
        });
        
        // Real-time updates
        <?php if ($activeConversation && count($messages) > 0): ?>
        let lastMessageId = <?php echo end($messages)['id'] ?? 0; ?>;
        let isActive = true;
        let checkInterval;
        
        // Check for new messages every 5 seconds
        function startMessageChecking() {
            checkInterval = setInterval(checkNewMessages, 5000);
        }
        
        function checkNewMessages() {
            if (!isActive) return;
            
            fetch(`ajax_handlers/check_messages.php?conversation_id=<?php echo $activeConversation; ?>&last_message_id=${lastMessageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.count > 0) {
                    // Add new messages to the chat
                    data.messages.forEach(message => {
                        const messageItem = document.createElement('div');
                        messageItem.className = `message-item ${message.is_current_user ? 'sent' : 'received'}`;
                        
                        const messageBubble = document.createElement('div');
                        messageBubble.className = 'message-bubble';
                        messageBubble.textContent = message.text;
                        
                        const messageTime = document.createElement('div');
                        messageTime.className = 'message-time';
                        messageTime.textContent = message.time;
                        
                        messageItem.appendChild(messageBubble);
                        messageItem.appendChild(messageTime);
                        messageList.appendChild(messageItem);
                        
                        // Update last message ID
                        lastMessageId = message.id;
                    });
                    
                    // Scroll to bottom if user is near the bottom already
                    const isNearBottom = messageList.scrollHeight - messageList.clientHeight - messageList.scrollTop < 100;
                    if (isNearBottom) {
                        messageList.scrollTop = messageList.scrollHeight;
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for new messages:', error);
            });
        }
        
        // Handle visibility change to save resources
        document.addEventListener('visibilitychange', function() {
            isActive = document.visibilityState === 'visible';
        });
        
        // Start checking for messages
        startMessageChecking();
        <?php endif; ?>
        
        // Quick search functionality
        const quickUserSearch = document.getElementById('quickUserSearch');
        const quickSearchResults = document.querySelector('.quick-search-results');
        let searchTimeout;
        
        if (quickUserSearch && quickSearchResults) {
            quickUserSearch.addEventListener('input', function() {
                const searchTerm = this.value.trim();
                
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                if (searchTerm.length < 2) {
                    quickSearchResults.innerHTML = '<div class="text-muted text-center py-2 small">Type at least 2 characters to search</div>';
                    return;
                }
                
                // Show loading indicator
                quickSearchResults.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div>';
                
                // Debounce search
                searchTimeout = setTimeout(() => {
                    // Fetch users
                    fetch(`ajax_handlers/search_users.php?term=${encodeURIComponent(searchTerm)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (data.users.length > 0) {
                                    // Display users
                                    let html = '';
                                    data.users.forEach(user => {
                                        let avatarHtml = '';
                                        if (user.profile_picture) {
                                            avatarHtml = `<img src="${user.profile_picture}" alt="${user.firstname}">`;
                                        } else {
                                            avatarHtml = `<div class="default-avatar">${user.firstname.charAt(0)}</div>`;
                                        }
                                        
                                        html += `
                                        <div class="quick-search-user-item" data-user-id="${user.id}">
                                            <div class="quick-search-avatar">
                                                ${avatarHtml}
                                            </div>
                                            <div class="quick-search-info">
                                                <div class="quick-search-name">${user.firstname} ${user.lastname}</div>
                                                ${user.username ? `<div class="quick-search-username">@${user.username}</div>` : ''}
                                            </div>
                                        </div>`;
                                    });
                                    quickSearchResults.innerHTML = html;
                                    
                                    // Add click event to user items
                                    document.querySelectorAll('.quick-search-user-item').forEach(item => {
                                        item.addEventListener('click', function() {
                                            const userId = this.getAttribute('data-user-id');
                                            document.body.style.cursor = 'wait';
                                            
                                            // Get or create conversation
                                            fetch(`ajax_handlers/get_or_create_conversation.php?user_id=${userId}`)
                                                .then(response => response.json())
                                                .then(data => {
                                                    if (data.success) {
                                                        window.location.href = `messages.php?id=${data.conversation_id}`;
                                                    } else {
                                                        alert('Error: ' + data.message);
                                                        document.body.style.cursor = 'default';
                                                    }
                                                })
                                                .catch(error => {
                                                    console.error('Error:', error);
                                                    document.body.style.cursor = 'default';
                                                });
                                        });
                                    });
                                } else {
                                    quickSearchResults.innerHTML = '<div class="text-muted text-center py-2 small">No users found</div>';
                                }
                            } else {
                                quickSearchResults.innerHTML = `<div class="text-danger text-center py-2 small">${data.message}</div>`;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            quickSearchResults.innerHTML = '<div class="text-danger text-center py-2 small">Error searching users</div>';
                        });
                }, 300);
            });
        }
    });
    </script>
</body>
</html> 