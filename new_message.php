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

// Get recipient ID from URL
$recipientId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// If no recipient ID provided, redirect to messages page
if ($recipientId === 0) {
    header("Location: messages.php");
    exit();
}

// Check if recipient exists
$userQuery = "SELECT id, firstname, lastname, username, profile_picture FROM users WHERE id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $recipientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found
    header("Location: messages.php");
    exit();
}

$recipient = $result->fetch_assoc();

// Check if conversation already exists between these users
$conversationQuery = "SELECT c.id FROM conversations c
                     JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
                     JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
                     WHERE cp1.user_id = ? AND cp2.user_id = ?
                     LIMIT 1";
$stmt = $conn->prepare($conversationQuery);
$stmt->bind_param("ii", $userId, $recipientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Conversation already exists, redirect to it
    $conversation = $result->fetch_assoc();
    header("Location: messages.php?id=" . $conversation['id']);
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty(trim($_POST['message']))) {
    $messageText = trim($_POST['message']);
    
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
        $stmt->bind_param("ii", $conversationId, $userId);
        $stmt->execute();
        
        // Add recipient
        $stmt = $conn->prepare($addParticipantQuery);
        $stmt->bind_param("ii", $conversationId, $recipientId);
        $stmt->execute();
        
        // Insert message
        $insertMessageQuery = "INSERT INTO messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertMessageQuery);
        $stmt->bind_param("iis", $conversationId, $userId, $messageText);
        $stmt->execute();
        
        $conn->commit();
        
        // Redirect to the conversation
        header("Location: messages.php?id=" . $conversationId);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error creating conversation: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Message - BookWagon</title>
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
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: var(--text-dark);
        }
        
        .message-container {
            max-width: 650px;
            margin: 30px auto;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .message-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: #fff;
        }
        
        .message-title {
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .recipient-info {
            display: flex;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background-color: var(--bg-light);
            border-radius: 10px;
        }
        
        .recipient-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            overflow: hidden;
            background-color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .recipient-avatar img {
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
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .recipient-name {
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .message-form {
            padding: 20px;
        }
        
        .message-textarea {
            width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            min-height: 150px;
            margin-bottom: 20px;
            outline: none;
            resize: vertical;
        }
        
        .message-textarea:focus {
            border-color: var(--primary-color);
        }
        
        .message-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .message-submit:hover {
            background-color: var(--primary-dark);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--text-light);
            text-decoration: none;
        }
        
        .back-link:hover {
            color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <?php include("include/user_header.php"); ?>

    <div class="container">
        <a href="messages.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Messages
        </a>
        
        <div class="message-container">
            <div class="message-header">
                <h5 class="message-title">New Message</h5>
            </div>
            
            <div class="message-body p-4">
                <div class="recipient-info">
                    <div class="recipient-avatar">
                        <?php if ($recipient['profile_picture'] && file_exists($recipient['profile_picture'])): ?>
                            <img src="<?php echo $recipient['profile_picture']; ?>" alt="Avatar">
                        <?php else: ?>
                            <div class="default-avatar">
                                <?php echo substr($recipient['firstname'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h6 class="recipient-name"><?php echo $recipient['firstname'] . ' ' . $recipient['lastname']; ?></h6>
                        <?php if (isset($recipient['username']) && !empty($recipient['username'])): ?>
                            <small class="text-muted">@<?php echo $recipient['username']; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post" action="" class="message-form">
                    <textarea class="message-textarea" name="message" placeholder="Write your message here..." required></textarea>
                    
                    <div class="text-end">
                        <button type="submit" class="message-submit">
                            <i class="fas fa-paper-plane me-2"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 