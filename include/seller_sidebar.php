<?php
/**
 * Seller Sidebar Component
 * Shared across all seller pages for consistent navigation.
 * 
 * Required: $currentPage must be set before including this file.
 */

if (!isset($currentPage)) $currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Seller Shared Styles -->
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
        --primary-orange: #f8a100;
        --primary-light: #fef7e8;
        --primary-dark: #d97706;
        --sidebar-w: 250px;
        --bg: #f4f6f9;
        --card-bg: #ffffff;
        --border: #e8ecf1;
        --text-dark: #1a1d29;
        --text-muted: #6b7280;
        --text-light: #9ca3af;
    }

    body {
        background: var(--bg);
        color: var(--text-dark);
        min-height: 100vh;
        margin: 0;
        padding: 0;
    }

    /* ===== SIDEBAR ===== */
    .sidebar {
        width: var(--sidebar-w);
        background: var(--card-bg);
        border-right: 1px solid var(--border);
        height: 100vh;
        position: fixed;
        top: 0; left: 0;
        display: flex;
        flex-direction: column;
        z-index: 100;
        transition: transform 0.3s ease;
    }

    .sidebar-brand {
        padding: 24px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 1px solid var(--border);
    }

    .sidebar-brand img { height: 36px; object-fit: contain; }
    .sidebar-brand span { font-weight: 700; font-size: 18px; color: var(--text-dark); }

    .sidebar-nav {
        flex: 1;
        padding: 16px 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        overflow-y: auto;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 16px;
        border-radius: 10px;
        text-decoration: none;
        color: var(--text-muted);
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .nav-item:hover { background: var(--bg); color: var(--text-dark); }

    .nav-item.active {
        background: var(--primary-orange);
        color: #ffffff;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(248, 161, 0, 0.3);
    }

    .nav-item.active i { color: #ffffff; }
    .nav-item i { width: 20px; text-align: center; font-size: 16px; }

    .sidebar-footer {
        padding: 16px 12px;
        border-top: 1px solid var(--border);
    }

    .sidebar-user {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 10px;
        background: var(--bg);
    }

    .sidebar-avatar {
        width: 36px; height: 36px;
        border-radius: 50%;
        background: var(--primary-orange);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
        overflow: hidden;
    }

    .sidebar-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .sidebar-user-info { flex: 1; min-width: 0; }

    .sidebar-user-name {
        font-size: 13px; font-weight: 600;
        color: var(--text-dark);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .sidebar-user-role { font-size: 11px; color: var(--text-muted); }

    /* ===== MAIN CONTENT ===== */
    .main-content {
        margin-left: var(--sidebar-w);
        min-height: calc(100vh - 70px);
        width: calc(100% - var(--sidebar-w));
    }

    .topbar {
        background: var(--card-bg);
        border-bottom: 1px solid var(--border);
        padding: 16px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 50;
        margin-left: var(--sidebar-w);
        width: calc(100% - var(--sidebar-w));
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .topbar-icon {
        color: var(--text-muted);
        font-size: 18px;
        text-decoration: none;
        position: relative;
    }

    .topbar-icon:hover { color: var(--primary-orange); }

    .page-content { padding: 28px 32px; }

    /* Mobile Toggle */
    .sidebar-toggle {
        display: none;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 8px 10px;
        cursor: pointer;
        font-size: 18px;
        color: var(--text-dark);
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.4);
        z-index: 90;
    }

    @media (max-width: 768px) {
        .sidebar { transform: translateX(-100%); }
        .sidebar.open { transform: translateX(0); }
        .sidebar-overlay.show { display: block; }
        .main-content { margin-left: 0; width: 100%; }
        .topbar { margin-left: 0; width: 100%; padding: 14px 18px; }
        .sidebar-toggle { display: block; }
        .page-content { padding: 20px 18px; }
    }
</style>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="images/logo.png" alt="BookWagon">
        <span>BookWagon</span>
    </div>

    <nav class="sidebar-nav">
        <a href="seller_dashboard.php" class="nav-item <?php echo ($currentPage === 'seller_dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-gauge"></i>
            Dashboard
        </a>
        <a href="Manage_books.php" class="nav-item <?php echo ($currentPage === 'Manage_books.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-book"></i>
            Manage Books
        </a>
        <a href="order.php" class="nav-item <?php echo ($currentPage === 'order.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-cart-shopping"></i>
            Orders
        </a>
        <a href="renter.php" class="nav-item <?php echo ($currentPage === 'renter.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-hand-holding-dollar"></i>
            Rentals
        </a>
        <a href="rental_request.php" class="nav-item <?php echo ($currentPage === 'rental_request.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-arrow-rotate-left"></i>
            Return Requests
        </a>
        <a href="customers.php" class="nav-item <?php echo ($currentPage === 'customers.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-users"></i>
            Customers
        </a>
        <a href="sales_report.php" class="nav-item <?php echo ($currentPage === 'sales_report.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-line"></i>
            Reports
        </a>
        <a href="seller_account.php" class="nav-item <?php echo ($currentPage === 'seller_account.php' || $currentPage === 'seller_settings.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-gear"></i>
            Settings
        </a>
        <a href="home.php" class="nav-item" style="margin-top: auto; border-top: 1px solid var(--border); padding-top: 15px; color: #dc2626;">
            <i class="fa-solid fa-store"></i>
            Switch to Buyer Mode
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <?php 
                $photo = '';
                $query = "SELECT profile_picture FROM users WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $_SESSION['id']);
                $stmt->execute();
                $stmt->bind_result($photo);
                $stmt->fetch();
                $stmt->close();
                
                if ($photo && file_exists($photo)) {
                    echo '<img src="'.$photo.'" alt="Profile">';
                } else {
                    echo strtoupper(substr($_SESSION["firstname"] ?? 'S', 0, 1));
                }
                ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION["firstname"] ?? 'Seller'); ?></div>
                <div class="sidebar-user-role">Seller</div>
            </div>
            <a href="logout.php" title="Sign Out" style="color: var(--text-muted); font-size: 14px; margin-left: auto;">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Topbar -->
<div class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div style="flex: 1;">
        <h4 class="mb-0 fw-bold" style="font-size: 18px; color: var(--text-dark);">Seller Portal</h4>
    </div>
    <div class="topbar-right">
        <a href="home.php" class="btn btn-outline-warning btn-sm fw-semibold d-flex align-items-center gap-2" style="border-color: var(--primary-orange); color: var(--primary-orange); border-radius: 20px; padding: 6px 16px; font-size: 13px; text-decoration: none;">
            <i class="fa-solid fa-store"></i>
            <span class="d-none d-sm-inline">Switch to Buyer Mode</span>
            <span class="d-sm-none">Shop</span>
        </a>
        <a href="notifications.php?mode=seller" class="topbar-icon" title="Notifications"><i class="fa-solid fa-bell"></i></a>
        <a href="messages.php" class="topbar-icon" title="Messages"><i class="fa-solid fa-envelope"></i></a>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('show');
    }
</script>
