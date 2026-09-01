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
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchResults = [];

// If search term is provided, search for users
if (!empty($searchTerm)) {
    $query = "SELECT id, firstname, lastname, username, profile_picture 
              FROM users 
              WHERE id != ? 
              AND (firstname LIKE ? OR lastname LIKE ? OR username LIKE ?) 
              ORDER BY firstname 
              LIMIT 20";
    
    $searchParam = "%{$searchTerm}%";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $userId, $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $searchResults[] = $row;
    }
}

// Get book buddies for quick access
$bookBuddies = [];
$buddyQuery = "SELECT u.id, u.firstname, u.lastname, u.profile_picture
               FROM book_buddies b
               JOIN users u ON (b.follower_id = u.id OR b.following_id = u.id)
               WHERE (b.follower_id = ? OR b.following_id = ?)
               AND u.id != ?
               AND b.status = 'accepted'
               ORDER BY u.firstname
               LIMIT 10";
$stmt = $conn->prepare($buddyQuery);
$stmt->bind_param("iii", $userId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $bookBuddies[] = $row;
}

// Get recent conversations for quick access
$recentContacts = [];
$recentQuery = "SELECT u.id, u.firstname, u.lastname, u.profile_picture
               FROM conversations c
               JOIN conversation_participants cp ON c.id = cp.conversation_id
               JOIN users u ON cp.user_id = u.id
               WHERE c.id IN (SELECT conversation_id FROM conversation_participants WHERE user_id = ?)
               AND u.id != ?
               ORDER BY c.updated_at DESC
               LIMIT 5";
$stmt = $conn->prepare($recentQuery);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recentContacts[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Users - BookWagon</title>
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
        
        .search-container {
            max-width: 800px;
            margin: 30px auto;
        }
        
        .search-header {
            margin-bottom: 25px;
        }
        
        .search-form {
            margin-bottom: 30px;
        }
        
        .search-input {
            border-radius: 50px;
            padding: 12px 20px;
            border: 1px solid var(--border-color);
            font-size: 1rem;
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(248, 161, 0, 0.25);
        }
        
        .search-btn {
            border-radius: 50px;
            padding: 12px 25px;
            background-color: var(--primary-color);
            border: none;
            color: white;
            font-weight: 500;
        }
        
        .search-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .user-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            text-align: center;
            padding: 20px 15px;
            text-decoration: none;
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }
        
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 15px;
            background-color: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-avatar img {
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
            background-color: var(--primary-dark);
            color: white;
            font-weight: bold;
            font-size: 1.8rem;
        }
        
        .user-name {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        
        .user-username {
            color: var(--text-light);
            font-size: 0.8rem;
            margin-bottom: 12px;
        }
        
        .message-btn {
            background-color: var(--primary-light);
            color: var(--primary-dark);
            border: none;
            border-radius: 50px;
            padding: 5px 15px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .message-btn:hover {
            background-color: var(--primary-dark);
            color: white;
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
        
        .empty-results {
            text-align: center;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .empty-results i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .empty-results p {
            color: var(--text-light);
            margin-bottom: 5px;
        }
        
        .user-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .user-list-item {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--text-dark);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        
        .user-list-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .user-list-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .user-list-info {
            flex-grow: 1;
        }
        
        .user-list-name {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .user-list-username {
            color: var(--text-light);
            font-size: 0.8rem;
        }
        
        .loading-indicator {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--primary-light);
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include("include/user_header.php"); ?>
    
    <!-- Loading indicator -->
    <div class="loading-indicator" id="loadingIndicator">
        <div class="spinner"></div>
    </div>

    <div class="container search-container">
        <a href="messages.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Messages
        </a>
        
        <div class="search-header">
            <h1 class="h3 mb-3">Find People to Message</h1>
            <form action="" method="get" class="search-form">
                <div class="input-group">
                    <input type="text" name="search" class="form-control search-input" placeholder="Search by name or username" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn search-btn">
                        <i class="fas fa-search me-2"></i> Search
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Search Results -->
        <?php if (!empty($searchTerm)): ?>
            <h2 class="section-title">Search Results for "<?php echo htmlspecialchars($searchTerm); ?>"</h2>
            
            <?php if (count($searchResults) > 0): ?>
                <div class="user-list mb-4">
                    <?php foreach ($searchResults as $user): ?>
                        <div class="user-list-item" data-user-id="<?php echo $user['id']; ?>">
                            <div class="user-list-avatar">
                                <?php if ($user['profile_picture'] && file_exists($user['profile_picture'])): ?>
                                    <img src="<?php echo $user['profile_picture']; ?>" alt="Avatar">
                                <?php else: ?>
                                    <div class="default-avatar">
                                        <?php echo substr($user['firstname'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="user-list-info">
                                <div class="user-list-name"><?php echo $user['firstname'] . ' ' . $user['lastname']; ?></div>
                                <?php if (!empty($user['username'])): ?>
                                    <div class="user-list-username">@<?php echo $user['username']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <button class="btn message-btn">
                                <i class="fas fa-comment-dots me-1"></i> Message
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-results">
                    <i class="fas fa-search"></i>
                    <p>No users found matching "<?php echo htmlspecialchars($searchTerm); ?>"</p>
                    <small>Try a different search term or check the spelling</small>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Book Buddies Section -->
        <?php if (count($bookBuddies) > 0): ?>
            <h2 class="section-title">Your Book Buddies</h2>
            <div class="user-grid">
                <?php foreach ($bookBuddies as $buddy): ?>
                    <div class="user-card" data-user-id="<?php echo $buddy['id']; ?>">
                        <div class="user-avatar">
                            <?php if ($buddy['profile_picture'] && file_exists($buddy['profile_picture'])): ?>
                                <img src="<?php echo $buddy['profile_picture']; ?>" alt="Avatar">
                            <?php else: ?>
                                <div class="default-avatar">
                                    <?php echo substr($buddy['firstname'], 0, 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h3 class="user-name"><?php echo $buddy['firstname'] . ' ' . $buddy['lastname']; ?></h3>
                        <button class="message-btn">
                            <i class="fas fa-comment-dots me-1"></i> Message
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Recent Contacts Section -->
        <?php if (count($recentContacts) > 0): ?>
            <h2 class="section-title">Recent Conversations</h2>
            <div class="user-grid">
                <?php foreach ($recentContacts as $contact): ?>
                    <div class="user-card" data-user-id="<?php echo $contact['id']; ?>">
                        <div class="user-avatar">
                            <?php if ($contact['profile_picture'] && file_exists($contact['profile_picture'])): ?>
                                <img src="<?php echo $contact['profile_picture']; ?>" alt="Avatar">
                            <?php else: ?>
                                <div class="default-avatar">
                                    <?php echo substr($contact['firstname'], 0, 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <h3 class="user-name"><?php echo $contact['firstname'] . ' ' . $contact['lastname']; ?></h3>
                        <button class="message-btn">
                            <i class="fas fa-comment-dots me-1"></i> Message
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const loadingIndicator = document.getElementById('loadingIndicator');
        
        // Handle click on any user item
        document.querySelectorAll('.user-list-item, .user-card').forEach(item => {
            item.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                if (!userId) return;
                
                // Show loading indicator
                loadingIndicator.style.display = 'flex';
                
                // Check if conversation exists or create new one
                fetch(`ajax_handlers/get_or_create_conversation.php?user_id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Redirect to the conversation
                            window.location.href = `messages.php?id=${data.conversation_id}`;
                        } else {
                            alert('Error: ' + data.message);
                            loadingIndicator.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while processing your request.');
                        loadingIndicator.style.display = 'none';
                    });
            });
        });
    });
    </script>
</body>
</html> 