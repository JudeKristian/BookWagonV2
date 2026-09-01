<nav class="navbar navbar-expand-md navbar-light">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <img src="images/logo.png" alt="BookWagon">
        </a>
        
        <div class="d-flex align-items-center">
            <a href="manage_books.php" class="nav-link me-4">Manage Books</a>
            <a href="order.php" class="nav-link me-4">Manage Orders</a>
            
            <?php
            // Seller-mode notification types
            $sellerNotifTypes = "'order_placed','order_update','rental_request','return_request','book_review','new_inquiry'";

            // Get unread notifications count (seller types only)
            $unreadCount = 0;
            if(isset($_SESSION['id'])) {
                $notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND type IN ($sellerNotifTypes)";
                $stmt = $conn->prepare($notifQuery);
                $stmt->bind_param("i", $_SESSION['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if($row = $result->fetch_assoc()) {
                    $unreadCount = $row['count'];
                }
            }
            
            // Get recent notifications (seller types only)
            $notifications = [];
            if(isset($_SESSION['id'])) {
                $recentNotifQuery = "SELECT n.id, n.sender_id, n.type, n.content, n.is_read, n.created_at, u.firstname, u.lastname, u.profile_picture 
                                    FROM notifications n 
                                    LEFT JOIN users u ON n.sender_id = u.id 
                                    WHERE n.user_id = ? AND n.type IN ($sellerNotifTypes)
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
            <a href="#notificationsOffcanvas" data-bs-toggle="offcanvas" role="button" aria-controls="notificationsOffcanvas" class="nav-link position-relative p-0 me-3" style="background: none; border: none; outline: none; box-shadow: none;">
                <i class="fa-regular fa-bell"></i>
                <?php if($unreadCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 0.25rem 0.4rem;">
                    <?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?>
                </span>
                <?php endif; ?>
            </a>
            
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
                
                // Fetch recent messages for the offcanvas
                $recentMessages = [];
                $recentMsgQuery = "SELECT m.id, m.conversation_id, m.sender_id, m.message_text, m.created_at, m.is_read, u.firstname, u.lastname, u.profile_picture
                                   FROM messages m
                                   JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
                                   JOIN users u ON m.sender_id = u.id
                                   WHERE cp.user_id = ? AND m.sender_id != ?
                                   ORDER BY m.created_at DESC LIMIT 5";
                $stmtMsg = $conn->prepare($recentMsgQuery);
                $stmtMsg->bind_param("ii", $_SESSION['id'], $_SESSION['id']);
                $stmtMsg->execute();
                $resMsg = $stmtMsg->get_result();
                while($mRow = $resMsg->fetch_assoc()) {
                    $recentMessages[] = $mRow;
                }
            }
            ?>
            
            <!-- Message Bell -->
            <a href="#messagesOffcanvas" data-bs-toggle="offcanvas" role="button" aria-controls="messagesOffcanvas" class="nav-link position-relative p-0 me-3" style="background: none; border: none; outline: none; box-shadow: none;">
                <i class="fa-regular fa-envelope"></i>
                <?php if($unreadMessagesCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 0.25rem 0.4rem;">
                    <?php echo $unreadMessagesCount > 9 ? '9+' : $unreadMessagesCount; ?>
                </span>
                <?php endif; ?>
            </a>
            
            <!-- Profile Sidebar Trigger -->
            <a href="#sellerAccountOffcanvas" data-bs-toggle="offcanvas" role="button" aria-controls="sellerAccountOffcanvas" class="nav-link border-0 bg-transparent d-flex align-items-center text-dark p-0 gap-2 text-decoration-none">
                <span class="fw-medium text-dark" style="font-size: 14px;"><?php echo isset($_SESSION['firstname']) ? $_SESSION['firstname'] : $_SESSION['email']; ?></span>
            </a>
        </div>
    </div>
</nav>

<!-- Notifications Offcanvas Sidebar -->
<div class="offcanvas offcanvas-end shadow-sm" tabindex="-1" id="notificationsOffcanvas" aria-labelledby="notificationsOffcanvasLabel" style="width: 350px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold" id="notificationsOffcanvasLabel">
            <i class="fa-regular fa-bell me-2 text-warning"></i> Notifications
        </h5>
        <button type="button" class="btn-close text-reset shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php if(count($notifications) > 0): ?>
            <div class="list-group list-group-flush">
                <?php foreach($notifications as $notification): ?>
                    <a href="<?php echo getNotificationUrl($notification); ?>" class="list-group-item list-group-item-action py-3 <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                        <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 text-dark" style="font-size: 0.9rem; font-weight: 600;">BookWagon</h6>
                            <small class="text-muted" style="font-size: 0.75rem;"><?php echo timeAgo($notification['created_at']); ?></small>
                        </div>
                        <p class="mb-0 text-secondary" style="font-size: 0.85rem; line-height: 1.4;">
                            <?php echo htmlspecialchars($notification['content']); ?>
                        </p>
                        <?php if(!$notification['is_read']): ?>
                            <span class="badge bg-primary rounded-pill mt-2" style="font-size: 0.65rem;">New</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-muted p-5 mt-4">
                <i class="fa-regular fa-bell-slash mb-3" style="font-size: 3rem; color: #e2e8f0;"></i>
                <h5>All caught up!</h5>
                <p class="small">You have no new notifications right now.</p>
            </div>
        <?php endif; ?>
    </div>
    <div class="offcanvas-footer border-top p-3 text-center bg-light">
        <a href="notifications.php" class="text-decoration-none text-primary fw-bold" style="font-size: 0.9rem;">
            View All Notifications <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>
</div>

<!-- Messages Offcanvas Sidebar -->
<div class="offcanvas offcanvas-end shadow-sm" tabindex="-1" id="messagesOffcanvas" aria-labelledby="messagesOffcanvasLabel" style="width: 350px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title fw-bold" id="messagesOffcanvasLabel">
            <i class="fa-regular fa-envelope me-2 text-primary"></i> Messages
        </h5>
        <button type="button" class="btn-close text-reset shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php if(isset($recentMessages) && count($recentMessages) > 0): ?>
            <div class="list-group list-group-flush">
                <?php foreach($recentMessages as $msg): ?>
                    <a href="messages.php?conversation_id=<?php echo $msg['conversation_id']; ?>" class="list-group-item list-group-item-action py-3 <?php echo $msg['is_read'] ? '' : 'bg-light'; ?>">
                        <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 text-dark" style="font-size: 0.9rem; font-weight: 600;">
                                <?php echo htmlspecialchars($msg['firstname'] . ' ' . $msg['lastname']); ?>
                            </h6>
                            <small class="text-muted" style="font-size: 0.75rem;"><?php echo timeAgo($msg['created_at']); ?></small>
                        </div>
                        <p class="mb-0 text-secondary text-truncate" style="font-size: 0.85rem; line-height: 1.4; max-width: 250px;">
                            <?php echo htmlspecialchars($msg['message_text']); ?>
                        </p>
                        <?php if(!$msg['is_read']): ?>
                            <span class="badge bg-primary rounded-pill mt-2" style="font-size: 0.65rem;">New</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-muted p-5 mt-4">
                <i class="fa-regular fa-envelope-open mb-3" style="font-size: 3rem; color: #e2e8f0;"></i>
                <h5>No new messages</h5>
                <p class="small">When you get a message, it will show up here.</p>
            </div>
        <?php endif; ?>
    </div>
    <div class="offcanvas-footer border-top p-3 text-center bg-light">
        <a href="messages.php" class="text-decoration-none text-primary fw-bold" style="font-size: 0.9rem;">
            Open Messages <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>
</div>

<!-- Seller Account Offcanvas Sidebar -->
<div class="offcanvas offcanvas-end shadow-sm" tabindex="-1" id="sellerAccountOffcanvas" aria-labelledby="sellerAccountOffcanvasLabel" style="width: 350px;">
    <div class="offcanvas-header border-bottom py-4 bg-light">
        <div class="d-flex align-items-center">
            <div class="rounded-circle d-flex align-items-center justify-content-center me-3 text-white fw-bold shadow-sm" style="width: 55px; height: 55px; font-size: 22px; background-color: #2b4162;">
                <?php echo substr(isset($_SESSION['firstname']) ? $_SESSION['firstname'] : $_SESSION['email'], 0, 1); ?>
            </div>
            <div>
                <h5 class="mb-0 fw-bold text-dark"><?php echo isset($_SESSION['firstname']) ? $_SESSION['firstname'] . ' ' . $_SESSION['lastname'] : $_SESSION['email']; ?></h5>
                <small class="text-muted d-block fw-bold text-primary">Seller Account</small>
            </div>
        </div>
        <button type="button" class="btn-close text-reset shadow-none position-absolute top-0 end-0 m-3" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    
    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush mt-2">
            <a href="seller_account.php" class="list-group-item list-group-item-action py-3 d-flex align-items-center">
                <i class="fa-solid fa-user text-primary me-3 fs-5" style="width: 24px; text-align: center;"></i> 
                <span class="fw-medium text-dark">My Account</span>
                <i class="fa-solid fa-chevron-right ms-auto text-muted small"></i>
            </a>
            <a href="manage_books.php" class="list-group-item list-group-item-action py-3 d-flex align-items-center">
                <i class="fa-solid fa-book text-success me-3 fs-5" style="width: 24px; text-align: center;"></i> 
                <span class="fw-medium text-dark">Manage Books</span>
                <i class="fa-solid fa-chevron-right ms-auto text-muted small"></i>
            </a>
            <a href="order.php" class="list-group-item list-group-item-action py-3 d-flex align-items-center">
                <i class="fa-solid fa-shopping-cart text-warning me-3 fs-5" style="width: 24px; text-align: center;"></i> 
                <span class="fw-medium text-dark">Manage Orders</span>
                <i class="fa-solid fa-chevron-right ms-auto text-muted small"></i>
            </a>
            <a href="sales_report.php" class="list-group-item list-group-item-action py-3 d-flex align-items-center">
                <i class="fa-solid fa-chart-line text-info me-3 fs-5" style="width: 24px; text-align: center;"></i> 
                <span class="fw-medium text-dark">Sales Report</span>
                <i class="fa-solid fa-chevron-right ms-auto text-muted small"></i>
            </a>
            <a href="seller_settings.php" class="list-group-item list-group-item-action py-3 d-flex align-items-center">
                <i class="fa-solid fa-cog text-secondary me-3 fs-5" style="width: 24px; text-align: center;"></i> 
                <span class="fw-medium text-dark">Shop Settings</span>
                <i class="fa-solid fa-chevron-right ms-auto text-muted small"></i>
            </a>
            <a href="security.php" class="list-group-item list-group-item-action py-3 d-flex align-items-center">
                <i class="fa-solid fa-shield-halved text-dark me-3 fs-5" style="width: 24px; text-align: center;"></i> 
                <span class="fw-medium text-dark">Security Settings</span>
                <i class="fa-solid fa-chevron-right ms-auto text-muted small"></i>
            </a>
        </div>
    </div>
    
    <div class="offcanvas-footer border-top p-3 text-center">
        <a href="logout.php" class="btn btn-outline-danger w-100 py-2 fw-bold">
            <i class="fa-solid fa-sign-out-alt me-2"></i> Log Out
        </a>
    </div>
</div>
</div>

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
if (!function_exists('getNotificationUrl')) {
    function getNotificationUrl($notification) {
        switch ($notification['type']) {
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
                return 'notifications.php?mode=seller';
        }
    }
}

// Helper function for time ago formatting
?>