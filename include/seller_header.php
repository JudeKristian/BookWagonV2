<nav class="navbar navbar-expand-md navbar-light">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <img src="images/logo.png" alt="BookWagon">
        </a>
        
        <div class="d-flex align-items-center">
            <a href="manage_books.php" class="nav-link me-4">Manage Books</a>
            <a href="order.php" class="nav-link me-4">Manage Orders</a>
            
            <?php
            // Get unread notifications count
            $unreadCount = 0;
            if(isset($_SESSION['id'])) {
                $notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
                $stmt = $conn->prepare($notifQuery);
                $stmt->bind_param("i", $_SESSION['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if($row = $result->fetch_assoc()) {
                    $unreadCount = $row['count'];
                }
            }
            
            // Get recent notifications
            $notifications = [];
            if(isset($_SESSION['id'])) {
                $recentNotifQuery = "SELECT n.id, n.sender_id, n.type, n.content, n.is_read, n.created_at, u.firstname, u.lastname, u.profile_picture 
                                    FROM notifications n 
                                    LEFT JOIN users u ON n.sender_id = u.id 
                                    WHERE n.user_id = ? 
                                    ORDER BY n.created_at DESC 
                                    LIMIT 5";
                $stmt = $conn->prepare($recentNotifQuery);
                $stmt->bind_param("i", $_SESSION['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                while($row = $result->fetch_assoc()) {
                    $notifications[] = $row;
                }
            }
            ?>
            
            <!-- Notification Bell -->
            <div class="dropdown notification-dropdown me-3">
                <button type="button" class="btn nav-link position-relative p-0" data-bs-toggle="dropdown" aria-expanded="false" id="notificationDropdown" style="background: none; border: none;">
                    <i class="fa-regular fa-bell"></i>
                    <?php if($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 0.25rem 0.4rem;">
                        <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                    </span>
                    <?php endif; ?>
                </button>
                
                <ul class="dropdown-menu dropdown-menu-end shadow notifications-dropdown p-0" aria-labelledby="notificationDropdown">
                    <li class="dropdown-header d-flex justify-content-between align-items-center">
                        <span>Notifications</span>
                        <?php if(count($notifications) > 0): ?>
                        <a href="javascript:void(0);" class="mark-all-read text-primary small" style="text-decoration: none;">Mark all as read</a>
                        <?php endif; ?>
                    </li>
                    
                    <?php if(count($notifications) > 0): ?>
                        <li class="notifications-list">
                            <?php foreach($notifications as $notification): ?>
                                <a href="<?php echo getNotificationUrl($notification); ?>" class="dropdown-item notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-notification-id="<?php echo $notification['id']; ?>">
                                    <div class="notification-img">
                                        <?php if($notification['profile_picture'] && file_exists($notification['profile_picture'])): ?>
                                            <img src="<?php echo $notification['profile_picture']; ?>" alt="Profile">
                                        <?php else: ?>
                                            <div class="default-avatar"><?php echo substr($notification['firstname'], 0, 1); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-content">
                                        <p class="notification-text"><?php echo $notification['content']; ?></p>
                            
                                    </div>
                                    <?php if(!$notification['is_read']): ?>
                                        <span class="unread-indicator"></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </li>
                        <li><hr class="dropdown-divider m-0"></li>
                        <li class="dropdown-footer">
                            <a href="notifications.php" class="text-center d-block py-2">View all notifications</a>
                        </li>
                    <?php else: ?>
                        <li class="empty-notifications p-4 text-center">
                            <i class="far fa-bell-slash mb-2" style="font-size: 2rem; color: #ced4da;"></i>
                            <p class="mb-0">No notifications yet</p>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <?php
            // Get unread messages count for the message icon
            $unreadMessagesCount = 0;
            if(isset($_SESSION['id'])) {
                $msgCountQuery = "SELECT COUNT(*) as count FROM messages m 
                                JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id 
                                WHERE cp.user_id = ? 
                                AND m.sender_id != ? 
                                AND m.is_read = 0";
                $stmt = $conn->prepare($msgCountQuery);
                $stmt->bind_param("ii", $_SESSION['id'], $_SESSION['id']);
                $stmt->execute();
                $msgResult = $stmt->get_result();
                if($row = $msgResult->fetch_assoc()) {
                    $unreadMessagesCount = $row['count'];
                }
            }
            ?>
            
            <a href="messages.php" class="nav-link me-3 position-relative">
                <i class="fa-regular fa-envelope"></i>
                <?php if($unreadMessagesCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 0.25rem 0.4rem;">
                    <?php echo $unreadMessagesCount > 9 ? '9+' : $unreadMessagesCount; ?>
                </span>
                <?php endif; ?>
            </a>
            
            <!-- Dropdown Menu -->
            <div class="dropdown">
                <button class="nav-link dropdown-toggle border-0 bg-transparent d-flex align-items-center" 
                        id="userDropdown" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="me-1"><?php echo isset($_SESSION['firstname']) ? $_SESSION['firstname'] : $_SESSION['email']; ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 p-0" style="min-width: 280px;" aria-labelledby="userDropdown">
                    <!-- User Profile Header -->
                    <div class="p-3 border-bottom">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <?php 
                                // Check if user has a profile picture
                                $photo = '';
                                $query = "SELECT profile_picture FROM users WHERE id = ?";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("i", $_SESSION['id']);
                                $stmt->execute();
                                $stmt->bind_result($photo);
                                $stmt->fetch();
                                $stmt->close();
                                
                                if ($photo && file_exists($photo)) {
                                    // Display profile picture
                                    echo '<img src="'.$photo.'" alt="photo" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">';
                                } else {
                                    // Display initial letter if no profile picture
                                    echo '<span class="text-white fw-bold">'.substr(isset($_SESSION['firstname']) ? $_SESSION['firstname'] : $_SESSION['email'], 0, 1).'</span>';
                                }
                                ?>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold"><?php echo isset($_SESSION['firstname']) ? $_SESSION['firstname'] . ' ' . $_SESSION['lastname'] : $_SESSION['email']; ?></h6>
                                <?php
                                // Get shop name from database for seller
                                $shop_name = '';
                                if (isset($_SESSION['id'])) {
                                    $shop_query = "SELECT shop_name FROM sellers WHERE user_id = ?";
                                    $stmt = $conn->prepare($shop_query);
                                    $stmt->bind_param("i", $_SESSION['id']);
                                    $stmt->execute();
                                    $stmt->bind_result($shop_name);
                                    $stmt->fetch();
                                    $stmt->close();
                                }
                                ?>
                                <small class="text-muted d-block"><?php echo !empty($shop_name) ? $shop_name : 'Seller Account'; ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Menu Items -->
                    <a class="dropdown-item py-2 d-flex align-items-center" href="seller_account.php">
                        <i class="fa-solid fa-user me-3"></i> Account
                        <i class="fa-solid fa-chevron-right ms-auto text-muted"></i>
                    </a>
                    <a class="dropdown-item py-2 d-flex align-items-center" href="manage_books.php">
                        <i class="fa-solid fa-book me-3"></i> Manage Books
                        <i class="fa-solid fa-chevron-right ms-auto text-muted"></i>
                    </a>
                    <a class="dropdown-item py-2 d-flex align-items-center" href="order.php">
                        <i class="fa-solid fa-shopping-cart me-3"></i> Manage Orders
                        <i class="fa-solid fa-chevron-right ms-auto text-muted"></i>
                    </a>
                    <a class="dropdown-item py-2 d-flex align-items-center" href="sales_report.php">
                        <i class="fa-solid fa-chart-line me-3"></i> Sales Report
                        <i class="fa-solid fa-chevron-right ms-auto text-muted"></i>
                    </a>
                    <a class="dropdown-item py-2 d-flex align-items-center" href="seller_settings.php">
                        <i class="fa-solid fa-cog me-3"></i> Shop Settings
                        <i class="fa-solid fa-chevron-right ms-auto text-muted"></i>
                    </a>
                    
                    <!-- Logout -->
                    <a class="dropdown-item py-2 d-flex align-items-center border-top mt-2" href="logout.php">
                        <i class="fa-solid fa-sign-out-alt me-3"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Notification Styles -->
<style>
.notifications-dropdown {
    width: 350px;
    max-height: 500px;
    overflow-y: auto;
    padding: 0;
}

/* Mobile responsive adjustments for notifications */
@media (max-width: 576px) {
    .notifications-dropdown {
        width: 300px;
        max-height: 400px;
    }

    .notification-item {
        padding: 10px 12px;
        font-size: 0.9rem;
    }

    .notification-img {
        width: 35px;
        height: 35px;
    }

    .notification-text {
        font-size: 0.85rem;
        line-height: 1.3;
    }

    .dropdown-menu {
        min-width: 280px;
        font-size: 0.9rem;
    }

    .user-menu .dropdown-menu {
        min-width: 260px;
    }
}

@media (max-width: 480px) {
    .notifications-dropdown {
        width: 280px;
        max-height: 350px;
    }

    .notification-item {
        padding: 8px 10px;
        gap: 10px;
    }

    .notification-img {
        width: 32px;
        height: 32px;
    }

    .notification-text {
        font-size: 0.8rem;
    }

    .dropdown-menu {
        min-width: 250px;
        font-size: 0.85rem;
    }

    .user-menu .dropdown-menu {
        min-width: 240px;
    }
}

.dropdown-header {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
}

.notifications-list {
    max-height: 350px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 15px;
    border-bottom: 1px solid #f1f1f1;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    position: relative;
    text-decoration: none;
    color: #333;
    transition: background-color 0.2s;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #f0f7ff;
}

.notification-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-img img {
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
    margin-bottom: 3px;
    font-size: 0.9rem;
    line-height: 1.4;
}

.notification-time {
    font-size: 0.75rem;
    color: #6c757d;
    display: block;
}

.unread-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #736029;
    position: absolute;
    top: 15px;
    right: 15px;
}

.dropdown-footer {
    border-top: 1px solid #e9ecef;
}

.dropdown-footer a {
    color: #007bff;
    font-size: 0.9rem;
    text-decoration: none;
}

.dropdown-footer a:hover {
    text-decoration: underline;
}

.empty-notifications {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px 15px;
    color: #6c757d;
}
</style>

<!-- Notification Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dropdowns
    var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
    var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });
    
    // Direct initialization of notification dropdown
    var notificationDropdownElement = document.getElementById('notificationDropdown');
    if (notificationDropdownElement) {
        var notificationDropdown = new bootstrap.Dropdown(notificationDropdownElement);
        
        // Optionally handle click manually
        notificationDropdownElement.addEventListener('click', function(e) {
            e.preventDefault();
            notificationDropdown.toggle();
        });
    }
    
    // Mark individual notification as read when clicked
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const notificationId = this.getAttribute('data-notification-id');
            
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
                    this.classList.remove('unread');
                    const unreadIndicator = this.querySelector('.unread-indicator');
                    if (unreadIndicator) {
                        unreadIndicator.remove();
                    }
                    
                    // Update the badge count
                    updateNotificationBadge();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
    
    // Mark all notifications as read
    const markAllReadBtn = document.querySelector('.mark-all-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            fetch('ajax_handlers/mark_all_notifications_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove unread styling from all notifications
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        const unreadIndicator = item.querySelector('.unread-indicator');
                        if (unreadIndicator) {
                            unreadIndicator.remove();
                        }
                    });
                    
                    // Hide the notification badge
                    const badge = document.querySelector('.notification-dropdown .badge');
                    if (badge) {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    }
    
    function updateNotificationBadge() {
        const unreadItems = document.querySelectorAll('.notification-item.unread').length;
        const badge = document.querySelector('.notification-dropdown .badge');
        
        if (badge) {
            if (unreadItems > 0) {
                badge.textContent = unreadItems > 9 ? '9+' : unreadItems;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }
});
</script>

<?php
// Helper function to determine the notification URL based on type
function getNotificationUrl($notification) {
    switch ($notification['type']) {
        case 'buddy_request':
            return 'profile.php?id=' . $_SESSION['id']; // Show own profile with requests
        case 'buddy_accepted':
            return 'profile.php?id=' . $notification['sender_id']; // Show the sender's profile
        case 'order_placed':
            return 'order.php'; // Show orders page for sellers
        case 'order_update':
            return 'order.php'; // Show orders page for sellers
        default:
            return 'notifications.php';
    }
}

// Helper function for time ago formatting
?>