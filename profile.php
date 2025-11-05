<?php
include("session.php");
include("connect.php");

// Get profile user ID from URL
$profileUserId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no user ID provided, redirect to dashboard
if ($profileUserId === 0) {
    header("Location: dashboard.php");
    exit();
}

// Check if user exists
$userQuery = "SELECT id, firstname, lastname, username, bio, profile_picture, country, city_state 
              FROM users WHERE id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found
    header("Location: dashboard.php");
    exit();
}

$profileUser = $result->fetch_assoc();

// Get the active tab from URL parameter
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'done_reading';

// Get user's book collections grouped by type
$collectionsQuery = "SELECT * FROM book_collections WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($collectionsQuery);
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$result = $stmt->get_result();

// Initialize collections array
$collections = [
    'done_reading' => [],
    'wishlist' => [],
    'looking_for' => [],
    'book_hunt' => [],
    'need_to_read' => []
];

// Organize books by collection type
while ($book = $result->fetch_assoc()) {
    $collections[$book['collection_type']][] = $book;
}

// Check if the logged-in user has any pending buddy requests
$pendingRequests = [];
if (isset($_SESSION['id'])) {
    $requestQuery = "SELECT b.id, b.follower_id, b.created_at, u.firstname, u.lastname, u.profile_picture 
                    FROM book_buddies b 
                    JOIN users u ON b.follower_id = u.id 
                    WHERE b.following_id = ? AND b.status = 'pending'";
    $stmt = $conn->prepare($requestQuery);
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $requestResult = $stmt->get_result();
    
    while ($request = $requestResult->fetch_assoc()) {
        $pendingRequests[] = $request;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $profileUser['firstname'] . ' ' . $profileUser['lastname']; ?>'s Profile - BookWagon</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #f8a100;
            --primary-dark: #e09000;
            --primary-light: #fff4e0;
            --secondary-color: #f8f9fa;
            --text-dark: #333333;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --bg-color: #f8f8f8;
            --card-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            background-color: var(--bg-color);
            line-height: 1.6;
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
        
        .container.profile-container {
            max-width: 1200px;
            padding: 40px 20px;
        }
        
        .profile-header {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 80px;
            background-color:rgb(255, 255, 255);
            z-index: 0;
        }
        
        .profile-header-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding-top: 20px;
        }
        
        .profile-picture-container {
            position: relative;
            width: 130px;
            height: 130px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 4px solid #fff;
            background-color: #fff;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .profile-picture {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-picture-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-color);
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .profile-info {
            flex-grow: 1;
        }
        
        .profile-name {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: #333;
            line-height: 1.2;
        }
        
        .profile-username {
            color: var(--text-muted);
            font-size: 1rem;
            margin-bottom: 10px;
        }
        
        .profile-location {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .profile-bio {
            margin-top: 15px;
            font-size: 0.95rem;
            color: #555;
        }
        
        .profile-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .action-button {
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-button i {
            margin-right: 6px;
        }
        
        .action-button.primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .action-button.primary:hover {
            background-color: var(--primary-dark);
        }
        
        .action-button.secondary {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .action-button.secondary:hover {
            background-color: #e0e0e0;
        }
        
        .profile-bio-short {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .profile-bio-full {
            display: none;
        }
        
        .see-more {
            color: #736029;
            cursor: pointer;
            font-weight: 500;
            display: inline-block;
            margin-top: 5px;
        }
        
        .buddy-btn {
            background-color: #fff;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.2s;
            font-size: 0.95rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .buddy-btn:hover {
            background-color: #f8f8f8;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        }
        
        .buddy-btn.following {
            background-color: #736029;
            color: #fff;
            border-color: #736029;
        }
        
        .buddy-btn i {
            margin-right: 6px;
        }
        
        .profile-stats {
            display: flex;
            gap: 40px;
            justify-content: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid var(--border-color);
        }
        
        .stat-item {
            text-align: center;
            min-width: 80px;
        }
        
        .stat-count {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .collection-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .book-collections-header {
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 1.6rem;
            color: #333;
        }
        
        .nav-tabs {
            border-bottom: none;
            margin-bottom: 25px;
            background-color: #736029;
            border-radius: 12px;
            padding: 15px 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .nav-tabs .nav-link {
            color: #fff;
            border: 1px solid #fff;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s;
            background-color: transparent;
        }
        
        .nav-tabs .nav-link.active {
            color: #000;
            background-color: #ffed54;
            border: 1px solid #ffed54;
        }
        
        .nav-tabs .nav-link:hover:not(.active) {
            border-color: #fff;
            background-color: rgba(214, 135, 135, 0.1);
        }
        
        .book-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
        }
        
        .book-item {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(255, 255, 255, 0.08);
            transition: all 0.3s;
            position: relative;
            border: 1px solid #f0f0f0;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .book-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .book-image {
            width: 100%;
            height: 280px;
            object-fit: cover;
            display: block;
            background-color: #f9f9f9;
        }
        
        .book-info {
            padding: 18px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .book-title {
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 1.05rem;
            line-height: 1.3;
            color: #333;
        }
        
        .book-author {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 10px;
            font-weight: 400;
        }
        
        .book-notes {
            font-size: 0.85rem;
            margin-top: 5px;
            color: #666;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        
        .book-date {
            font-size: 0.75rem;
            color: #999;
            margin-top: auto;
        }
        
        .empty-collection {
            padding: 60px 30px;
            text-align: center;
            color: var(--text-muted);
        }
        
        .empty-collection i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-collection h5 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #555;
        }
        
        .empty-collection p {
            color: #888;
            max-width: 400px;
            margin: 0 auto;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .profile-header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-picture {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .profile-bio {
                max-width: 100%;
            }
            
            .profile-stats {
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .book-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
            
            .stat-item {
                min-width: 70px;
            }
        }

        /* Notification styles */
        .notification-badge {
            position: relative;
            display: inline-block;
        }
        
        .notification-badge .badge {
            position: absolute;
            top: -5px;
            right: -8px;
            border-radius: 50%;
            background-color: #ff4757;
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notifications-dropdown {
            width: 320px;
            padding: 0;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notifications-dropdown .dropdown-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f1f1;
            white-space: normal;
        }
        
        .notifications-dropdown .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .notifications-dropdown .dropdown-header {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 10px 15px;
        }
        
        .notification-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
        }
        
        .notification-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-text {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .notification-actions {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }
        
        .notification-actions button {
            padding: 2px 10px;
            font-size: 0.8rem;
        }

        /* Request section styles */
        .buddy-request-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }
        
        .buddy-request-section h4 {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #333;
        }
        
        .buddy-request-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .buddy-request-item {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 8px;
            padding: 10px 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .buddy-request-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .buddy-request-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .buddy-request-info {
            flex: 1;
        }
        
        .buddy-request-name {
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .buddy-request-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .buddy-request-actions {
            display: flex;
            gap: 10px;
        }
        
        .buddy-request-actions button {
            padding: 5px 10px;
            font-size: 0.85rem;
        }
        
        .buddy-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .pending-status {
            background-color: #ffefd5;
            color: #e09000;
        }
        
        .accepted-status {
            background-color: #e6f7e9;
            color: #28a745;
        }
        
        .no-requests {
            text-align: center;
            color: #6c757d;
            padding: 20px;
        }
    </style>
</head>
<body>
    <!-- Include Header -->
    <?php 
    $userType = $_SESSION['usertype'] ?? '';
    if ($userType == 'user') {
        include("include/user_header.php");
    } elseif ($userType == 'seller') {
        include("include/seller_header.php");
    }
    ?>

    <div class="container profile-container">
        <?php if (isset($_SESSION['id']) && count($pendingRequests) > 0 && $_SESSION['id'] == $profileUserId): ?>
        <div class="buddy-request-section">
            <h4><i class="fas fa-user-friends me-2"></i> Book Buddy Requests (<?php echo count($pendingRequests); ?>)</h4>
            <div class="buddy-request-list">
                <?php foreach ($pendingRequests as $request): ?>
                <div class="buddy-request-item" id="request-<?php echo $request['id']; ?>">
                    <div class="buddy-request-avatar">
                        <?php if ($request['profile_picture'] && file_exists($request['profile_picture'])): ?>
                            <img src="<?php echo $request['profile_picture']; ?>" alt="Profile Picture">
                        <?php else: ?>
                            <div class="profile-picture-placeholder">
                                <?php echo substr($request['firstname'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="buddy-request-info">
                        <p class="buddy-request-name"><?php echo $request['firstname'] . ' ' . $request['lastname']; ?></p>
                        <p class="buddy-request-time">Requested <?php echo timeAgo($request['created_at']); ?></p>
                    </div>
                    <div class="buddy-request-actions">
                        <button class="btn btn-sm btn-primary accept-request" data-user-id="<?php echo $request['follower_id']; ?>">
                            Accept
                        </button>
                        <button class="btn btn-sm btn-outline-secondary reject-request" data-user-id="<?php echo $request['follower_id']; ?>">
                            Decline
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row">
                <div class="col-md-12">
                    <div class="profile-header-content">
                        <div class="profile-picture-container">
                            <?php if ($profileUser['profile_picture'] && file_exists($profileUser['profile_picture'])): ?>
                                <img src="<?php echo $profileUser['profile_picture']; ?>" alt="Profile Picture" class="profile-picture">
                            <?php else: ?>
                                <div class="profile-picture-placeholder">
                                    <?php echo substr($profileUser['firstname'], 0, 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="profile-info">
                            <h1 class="profile-name"><?php echo $profileUser['firstname'] . ' ' . $profileUser['lastname']; ?></h1>
                            
                            <?php if (!empty($profileUser['city_state']) || !empty($profileUser['country'])): ?>
                                <div class="profile-location">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo $profileUser['city_state'] && $profileUser['country'] ? 
                                        $profileUser['city_state'] . ', ' . $profileUser['country'] : 
                                        ($profileUser['city_state'] ?: $profileUser['country']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($profileUser['bio'])): ?>
                                <p class="profile-bio">
                                    <span class="profile-bio-short"><?php echo htmlspecialchars($profileUser['bio']); ?></span>
                                    <span class="profile-bio-full"><?php echo htmlspecialchars($profileUser['bio']); ?></span>
                                    <a class="see-more">See more</a>
                                </p>
                            <?php endif; ?>
                            
                            <div class="profile-actions">
                                <?php if(isset($_SESSION['id']) && $_SESSION['id'] !== $profileUserId): ?>
                                    <?php
                                    // Check if already book buddies
                                    $buddyStatus = "none";
                                    $buddyQuery = "SELECT status FROM book_buddies 
                                                  WHERE (follower_id = ? AND following_id = ?) 
                                                  OR (follower_id = ? AND following_id = ?)";
                                    $stmt = $conn->prepare($buddyQuery);
                                    $stmt->bind_param("iiii", $_SESSION['id'], $profileUserId, $profileUserId, $_SESSION['id']);
                                    $stmt->execute();
                                    $buddyResult = $stmt->get_result();
                                    
                                    if ($buddyResult->num_rows > 0) {
                                        $buddyData = $buddyResult->fetch_assoc();
                                        $buddyStatus = $buddyData['status'];
                                    }
                                    ?>
                                    
                                    <!-- Book Buddy Button -->
                                    <button class="action-button primary book-buddy-btn" data-user-id="<?php echo $profileUserId; ?>" data-status="<?php echo $buddyStatus; ?>">
                                        <i class="fas fa-user-plus"></i>
                                        <span class="buddy-text">
                                            <?php
                                            if ($buddyStatus === "accepted") {
                                                echo "Book Buddies";
                                            } elseif ($buddyStatus === "pending") {
                                                echo "Request Sent";
                                            } else {
                                                echo "Add Book Buddy";
                                            }
                                            ?>
                                        </span>
                                    </button>
                                    
                                    <!-- Message Button -->
                                    <a href="new_message.php?user_id=<?php echo $profileUserId; ?>" class="action-button secondary text-decoration-none">
                                        <i class="fas fa-envelope"></i> Message
                                    </a>
                                <?php endif; ?>
                                
                                <?php if(isset($_SESSION['id']) && $_SESSION['id'] === $profileUserId): ?>
                                    <a href="account.php" class="action-button secondary text-decoration-none">
                                        <i class="fas fa-edit"></i> Edit Profile
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-12">
                    <div class="profile-stats">
                        <?php
                        // Count books in each collection
                        $totalBooks = 0;
                        foreach ($collections as $type => $books) {
                            $totalBooks += count($books);
                        }
                        
                        $readCount = count($collections['done_reading']);
                        $wishlistCount = count($collections['wishlist']);
                        
                        // Count book buddies (followers)
                        $buddyCountQuery = "SELECT COUNT(*) as count FROM book_buddies WHERE following_id = ?";
                        $buddyStmt = $conn->prepare($buddyCountQuery);
                        $buddyStmt->bind_param("i", $profileUserId);
                        $buddyStmt->execute();
                        $buddyResult = $buddyStmt->get_result();
                        $buddyCount = $buddyResult->fetch_assoc()['count'];
                        ?>
                        <div class="stat-item">
                            <div class="stat-count"><?php echo $totalBooks; ?></div>
                            <div class="stat-label">Total Books</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-count"><?php echo $readCount; ?></div>
                            <div class="stat-label">Read</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-count"><?php echo $wishlistCount; ?></div>
                            <div class="stat-label">Wishlist</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-count"><?php echo $buddyCount; ?></div>
                            <div class="stat-label">Book Buddies</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Collection Tabs -->
        <h3 class="book-collections-header">Book Collections</h3>
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab == 'done_reading' ? 'active' : ''; ?>" 
                   href="profile.php?id=<?php echo $profileUserId; ?>&tab=done_reading">
                    Done Reading
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab == 'wishlist' ? 'active' : ''; ?>" 
                   href="profile.php?id=<?php echo $profileUserId; ?>&tab=wishlist">
                    Wishlist
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab == 'looking_for' ? 'active' : ''; ?>" 
                   href="profile.php?id=<?php echo $profileUserId; ?>&tab=looking_for">
                    Looking For
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab == 'book_hunt' ? 'active' : ''; ?>" 
                   href="profile.php?id=<?php echo $profileUserId; ?>&tab=book_hunt">
                    Book Hunt
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab == 'need_to_read' ? 'active' : ''; ?>" 
                   href="profile.php?id=<?php echo $profileUserId; ?>&tab=need_to_read">
                    Need to Read
                </a>
            </li>
        </ul>
        
        <!-- Collection Content -->
        <div class="tab-content">
            <div class="tab-pane fade show active">
                <div class="collection-card">
                    <?php if (empty($collections[$activeTab])): ?>
                        <div class="empty-collection">
                            <i class="fas fa-book-open"></i>
                            <h5>No books in this collection</h5>
                            <p><?php echo $profileUser['firstname']; ?> hasn't added any books to their <?php echo str_replace('_', ' ', $activeTab); ?> collection yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="book-grid">
                            <?php foreach ($collections[$activeTab] as $book): ?>
                                <div class="book-item">
                                    <?php if (!empty($book['book_image']) && file_exists($book['book_image'])): ?>
                                        <img src="<?php echo $book['book_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="book-image">
                                    <?php else: ?>
                                        <div class="book-image d-flex align-items-center justify-content-center">
                                            <i class="fas fa-book" style="font-size: 3rem; opacity: 0.2;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="book-info">
                                        <h5 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                                        <p class="book-author">by <?php echo htmlspecialchars($book['author'] ?: 'Unknown Author'); ?></p>
                                        <?php if (!empty($book['notes'])): ?>
                                            <p class="book-notes"><?php echo htmlspecialchars($book['notes']); ?></p>
                                        <?php endif; ?>
                                        <div class="book-date">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            Added on <?php echo date('M d, Y', strtotime($book['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    
    <!-- Book Buddies Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Bio "See more" functionality
            const seeMoreBtn = document.querySelector('.see-more');
            if (seeMoreBtn) {
                seeMoreBtn.addEventListener('click', function() {
                    const bioShort = document.querySelector('.profile-bio-short');
                    const bioFull = document.querySelector('.profile-bio-full');
                    
                    if (bioFull.style.display === 'none' || bioFull.style.display === '') {
                        bioShort.style.display = 'none';
                        bioFull.style.display = 'block';
                        this.textContent = 'See less';
                    } else {
                        bioShort.style.display = 'block';
                        bioFull.style.display = 'none';
                        this.textContent = 'See more';
                    }
                });
            }
            
            // Book Buddy toggle functionality
            const buddyBtn = document.getElementById('buddyBtn');
            if (buddyBtn) {
                buddyBtn.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const action = this.getAttribute('data-action') || 'request';
                    
                    fetch('ajax_handlers/toggle_book_buddy.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `user_id=${userId}&action=${action}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (action === 'unfollow') {
                                // Now unfollowed
                                buddyBtn.classList.remove('following');
                                buddyBtn.innerHTML = '<i class="fas fa-user-plus"></i> Add Book Buddy';
                                buddyBtn.setAttribute('data-action', 'request');
                                
                                // Update buddy count
                                const buddyCountElement = document.querySelector('.stat-count:last-child');
                                if (buddyCountElement) {
                                    let count = parseInt(buddyCountElement.textContent);
                                    buddyCountElement.textContent = count - 1;
                                }
                            } else if (action === 'request') {
                                // Request sent
                                buddyBtn.parentNode.innerHTML = `
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="buddy-status pending-status">
                                            <i class="fas fa-clock"></i> Request Sent
                                        </span>
                                        <button id="buddyBtn" class="btn btn-sm btn-outline-secondary" data-user-id="${userId}" data-action="cancel">
                                            Cancel Request
                                        </button>
                                    </div>
                                `;
                                
                                // Reattach event listener to the new button
                                setTimeout(() => {
                                    const newBuddyBtn = document.getElementById('buddyBtn');
                                    if (newBuddyBtn) {
                                        newBuddyBtn.addEventListener('click', function() {
                                            const userId = this.getAttribute('data-user-id');
                                            const action = this.getAttribute('data-action');
                                            
                                            fetch('ajax_handlers/toggle_book_buddy.php', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/x-www-form-urlencoded',
                                                },
                                                body: `user_id=${userId}&action=${action}`
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                if (data.success) {
                                                    location.reload();
                                                } else {
                                                    alert(data.message || 'An error occurred');
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error:', error);
                                                alert('An unexpected error occurred');
                                            });
                                        });
                                    }
                                }, 100);
                            } else if (action === 'cancel') {
                                location.reload();
                            }
                        } else {
                            alert(data.message || 'An error occurred');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred');
                    });
                });
            }
            
            // Accept/Reject request functionality
            const acceptButtons = document.querySelectorAll('.accept-request');
            const rejectButtons = document.querySelectorAll('.reject-request');
            
            acceptButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    
                    fetch('ajax_handlers/toggle_book_buddy.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `user_id=${userId}&action=accept`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // If this is in the request section, remove the item
                            const requestItem = this.closest('.buddy-request-item');
                            if (requestItem) {
                                requestItem.remove();
                            }
                            
                            // If on profile page of the requester, update the button
                            if (window.location.href.includes(`id=${userId}`)) {
                                const buttonContainer = this.closest('.d-flex');
                                if (buttonContainer) {
                                    buttonContainer.innerHTML = `
                                        <button id="buddyBtn" class="buddy-btn following" data-user-id="${userId}" data-action="unfollow">
                                            <i class="fas fa-user-check"></i> Book Buddy
                                        </button>
                                    `;
                                }
                            }
                            
                            // Update buddy count if available
                            const buddyCountElement = document.querySelector('.stat-count:last-child');
                            if (buddyCountElement) {
                                let count = parseInt(buddyCountElement.textContent);
                                buddyCountElement.textContent = count + 1;
                            }
                            
                            // If all requests are handled, remove the section
                            const requestSection = document.querySelector('.buddy-request-section');
                            if (requestSection && requestSection.querySelectorAll('.buddy-request-item').length === 0) {
                                requestSection.remove();
                            }
                        } else {
                            alert(data.message || 'An error occurred');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred');
                    });
                });
            });
            
            rejectButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    
                    fetch('ajax_handlers/toggle_book_buddy.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `user_id=${userId}&action=reject`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // If this is in the request section, remove the item
                            const requestItem = this.closest('.buddy-request-item');
                            if (requestItem) {
                                requestItem.remove();
                            }
                            
                            // If on profile page of the requester, update the button
                            if (window.location.href.includes(`id=${userId}`)) {
                                const buttonContainer = this.closest('.d-flex');
                                if (buttonContainer) {
                                    buttonContainer.innerHTML = `
                                        <button id="buddyBtn" class="buddy-btn" data-user-id="${userId}" data-action="request">
                                            <i class="fas fa-user-plus"></i> Add Book Buddy
                                        </button>
                                    `;
                                }
                            }
                            
                            // If all requests are handled, remove the section
                            const requestSection = document.querySelector('.buddy-request-section');
                            if (requestSection && requestSection.querySelectorAll('.buddy-request-item').length === 0) {
                                requestSection.remove();
                            }
                        } else {
                            alert(data.message || 'An error occurred');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred');
                    });
                });
            });
        });
        
        // Helper function for time ago formatting
        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            let interval = Math.floor(seconds / 31536000);
            if (interval >= 1) {
                return interval + ' year' + (interval === 1 ? '' : 's') + ' ago';
            }
            
            interval = Math.floor(seconds / 2592000);
            if (interval >= 1) {
                return interval + ' month' + (interval === 1 ? '' : 's') + ' ago';
            }
            
            interval = Math.floor(seconds / 86400);
            if (interval >= 1) {
                return interval + ' day' + (interval === 1 ? '' : 's') + ' ago';
            }
            
            interval = Math.floor(seconds / 3600);
            if (interval >= 1) {
                return interval + ' hour' + (interval === 1 ? '' : 's') + ' ago';
            }
            
            interval = Math.floor(seconds / 60);
            if (interval >= 1) {
                return interval + ' minute' + (interval === 1 ? '' : 's') + ' ago';
            }
            
            return 'Just now';
        }
    </script>
</body>
</html> 