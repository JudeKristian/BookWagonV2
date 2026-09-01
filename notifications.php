<?php
include("session.php");
include("connect.php");

// Determine mode: 'seller' or 'buyer' (default)
$mode = isset($_GET['mode']) && $_GET['mode'] === 'seller' ? 'seller' : 'buyer';

// Define which notification types belong to each mode
$buyerTypes = ['buddy_request', 'buddy_accepted', 'order_shipped', 'order_delivered', 'rental_approved', 'rental_due', 'payment_confirmed'];
$sellerTypes = ['order_placed', 'order_update', 'rental_request', 'return_request', 'book_review', 'new_inquiry'];
$activeTypes = $mode === 'seller' ? $sellerTypes : $buyerTypes;
$typeList = "'" . implode("','", $activeTypes) . "'";

// Get notifications for the current user filtered by mode
$notifications = [];
if(isset($_SESSION['id'])) {
    $notifQuery = "SELECT n.id, n.sender_id, n.type, n.content, n.is_read, n.created_at, u.firstname, u.lastname, u.profile_picture 
                  FROM notifications n 
                  LEFT JOIN users u ON n.sender_id = u.id 
                  WHERE n.user_id = ? AND n.type IN ($typeList)
                  ORDER BY n.created_at DESC 
                  LIMIT 50";
    $stmt = $conn->prepare($notifQuery);
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Mark all as read if requested (only for current mode's types)
if(isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    $updateQuery = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0 AND type IN ($typeList)";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    
    // Redirect to remove the query parameter but keep mode
    header("Location: notifications.php?mode=$mode");
    exit();
}

// Helper function to determine the notification URL based on type
function getNotificationUrl($notification) {
    switch ($notification['type']) {
        // Buyer types
        case 'buddy_request':
            return 'profile.php?id=' . $_SESSION['id'];
        case 'buddy_accepted':
            return 'profile.php?id=' . $notification['sender_id'];
        case 'order_shipped':
        case 'order_delivered':
        case 'payment_confirmed':
            return 'history.php';
        case 'rental_approved':
        case 'rental_due':
            return 'rented_books.php';
        // Seller types
        case 'order_placed':
        case 'order_update':
            return 'order.php';
        case 'rental_request':
            return 'renter.php';
        case 'return_request':
            return 'rental_request.php';
        case 'new_inquiry':
            return 'messages.php';
        case 'book_review':
            return 'Manage_books.php';
        default:
            return '#';
    }
}

// Helper function for time ago formatting
function timeAgo($dateString) {
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $date->getTimestamp();
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ($minutes == 1 ? ' minute ago' : ' minutes ago');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ($hours == 1 ? ' hour ago' : ' hours ago');
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ($days == 1 ? ' day ago' : ' days ago');
    } else {
        return date('M j, Y', $date->getTimestamp());
    }
}

// Helper function to format date into readable format
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('F j, Y, g:i a');
}

// Count unread notifications
$unreadCount = 0;
foreach($notifications as $notification) {
    if(!$notification['is_read']) {
        $unreadCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - BookWagon</title>
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
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --bg-color: #f8f8f8;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-dark);
        }
        
        .container.notifications-container {
            max-width: 800px;
            padding: 40px 20px;
        }
        
        .page-title {
            margin-bottom: 1.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-weight: 600;
            margin-bottom: 0;
            font-size: 1.1rem;
        }
        
        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: flex-start;
            transition: background-color 0.2s;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background-color: #f9f9f9;
        }
        
        .notification-item.unread {
            background-color: #f0f7ff;
        }
        
        .notification-item.unread:hover {
            background-color: #e6f0ff;
        }
        
        .notification-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .notification-avatar img {
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
        
        .notification-content {
            flex-grow: 1;
        }
        
        .notification-text {
            margin-bottom: 5px;
            font-size: 0.95rem;
        }
        
        .notification-time {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        .notification-actions {
            display: flex;
            margin-top: 8px;
        }
        
        .notification-actions a {
            color: var(--primary-dark);
            margin-right: 15px;
            font-size: 0.85rem;
            text-decoration: none;
        }
        
        .notification-actions a:hover {
            text-decoration: underline;
        }
        
        .mark-all-btn {
            color: var(--primary-dark);
            font-size: 0.9rem;
            text-decoration: none;
        }
        
        .mark-all-btn:hover {
            text-decoration: underline;
        }
        
        .notification-badge {
            background-color: var(--primary-dark);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            margin-left: 0.5rem;
            font-size: 0.75rem;
        }
        
        .unread-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--primary-dark);
            display: inline-block;
            margin-right: 10px;
        }
        
        .empty-notifications {
            padding: 50px 20px;
            text-align: center;
            color: var(--text-muted);
        }
        
        .empty-notifications i {
            font-size: 3rem;
            color: #ced4da;
            margin-bottom: 1rem;
        }
        
        .empty-notifications p {
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <?php 
    $userType = $_SESSION['usertype'] ?? '';
    include("include/user_header.php");
    ?>

    <div class="container notifications-container">
        <div class="d-flex align-items-center gap-3 mb-3">
            <h1 class="page-title mb-0">Notifications</h1>
            <div class="d-flex gap-2">
                <a href="notifications.php?mode=buyer" class="btn btn-sm <?php echo $mode === 'buyer' ? 'btn-warning' : 'btn-outline-secondary'; ?> rounded-pill" style="font-size: 12px; font-weight: 600;"><i class="fa-solid fa-user me-1"></i> Buyer</a>
                <?php if (isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'seller'): ?>
                <a href="notifications.php?mode=seller" class="btn btn-sm <?php echo $mode === 'seller' ? 'btn-dark' : 'btn-outline-secondary'; ?> rounded-pill" style="font-size: 12px; font-weight: 600;"><i class="fa-solid fa-gauge me-1"></i> Seller</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    All Notifications
                    <?php if($unreadCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadCount; ?> new</span>
                    <?php endif; ?>
                </h5>
                <?php if(count($notifications) > 0): ?>
                <a href="notifications.php?mark_all_read=1&mode=<?php echo $mode; ?>" class="mark-all-btn">Mark all as read</a>
                <?php endif; ?>
            </div>
            
            <div class="card-body p-0">
                <?php if(count($notifications) > 0): ?>
                    <?php foreach($notifications as $notification): ?>
                        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" data-notification-id="<?php echo $notification['id']; ?>">
                            <div class="notification-avatar">
                                <?php if($notification['profile_picture'] && file_exists($notification['profile_picture'])): ?>
                                    <img src="<?php echo $notification['profile_picture']; ?>" alt="Avatar">
                                <?php else: ?>
                                    <div class="default-avatar">
                                        <?php echo substr($notification['firstname'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notification-content">
                                <?php if(!$notification['is_read']): ?>
                                    <span class="unread-indicator"></span>
                                <?php endif; ?>
                                
                                <div class="notification-text"><?php echo $notification['content']; ?></div>
                                <div class="notification-time" title="<?php echo formatDate($notification['created_at']); ?>">
                                    <?php echo timeAgo($notification['created_at']); ?>
                                </div>
                                
                                <div class="notification-actions">
                                    <a href="<?php echo getNotificationUrl($notification); ?>">View</a>
                                    <?php if(!$notification['is_read']): ?>
                                    <a href="javascript:void(0);" class="mark-read-btn" data-notification-id="<?php echo $notification['id']; ?>">Mark as read</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-notifications">
                        <i class="far fa-bell-slash"></i>
                        <p>You don't have any notifications yet</p>
                        <small>Notifications about your book buddy requests and other activities will appear here</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mark notification as read when "Mark as read" is clicked
        const markReadButtons = document.querySelectorAll('.mark-read-btn');
        markReadButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const notificationId = this.getAttribute('data-notification-id');
                const notificationItem = this.closest('.notification-item');
                
                fetch('ajax_handlers/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `notification_id=${notificationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        notificationItem.classList.remove('unread');
                        this.parentNode.removeChild(this);
                        
                        // Update unread count in badge
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            const currentCount = parseInt(badge.textContent);
                            if (currentCount > 1) {
                                badge.textContent = (currentCount - 1) + ' new';
                            } else {
                                badge.remove();
                            }
                        }
                        
                        // Remove the unread indicator
                        const indicator = notificationItem.querySelector('.unread-indicator');
                        if (indicator) {
                            indicator.remove();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        });
        
        // Mark notification as read when clicked on View
        const notificationItems = document.querySelectorAll('.notification-item');
        notificationItems.forEach(item => {
            const viewLink = item.querySelector('.notification-actions a:first-child');
            if (viewLink && item.classList.contains('unread')) {
                viewLink.addEventListener('click', function() {
                    const notificationId = item.getAttribute('data-notification-id');
                    
                    fetch('ajax_handlers/mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `notification_id=${notificationId}`
                    });
                    // No need to wait for response as we're navigating away
                });
            }
        });
    });
    </script>
</body>
</html> 