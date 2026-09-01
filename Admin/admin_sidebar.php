<?php
/**
 * Admin Sidebar Component
 * Shared across all admin pages for consistent navigation.
 * 
 * Required: $currentPage must be set before including this file.
 * Optional: $pendingSellers (defaults to 0)
 */

if (!isset($currentPage)) $currentPage = basename($_SERVER['PHP_SELF']);
if (!isset($pendingSellers)) $pendingSellers = 0;
?>

<!-- Admin Shared Styles -->
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
        --primary: #f8a100;
        --primary-light: #fef7e8;
        --primary-dark: #d97706;
        --sidebar-w: 250px;
        --bg: #f4f6f9;
        --card-bg: #ffffff;
        --border: #e8ecf1;
        --text-dark: #1a1d29;
        --text-muted: #6b7280;
        --text-light: #9ca3af;
        --success: #10b981;
        --success-bg: #ecfdf5;
        --warning: #f59e0b;
        --warning-bg: #fffbeb;
        --danger: #ef4444;
        --danger-bg: #fef2f2;
    }

    body {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        background: var(--bg);
        color: var(--text-dark);
        min-height: 100vh;
        display: flex;
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

    .sidebar-nav {
        flex: 1;
        padding: 16px 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
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
        background: var(--primary);
        color: #ffffff;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(248, 161, 0, 0.3);
    }

    .nav-item.active i { color: #ffffff; }
    .nav-item i { width: 20px; text-align: center; font-size: 16px; }

    .nav-badge {
        margin-left: auto;
        background: var(--danger);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 50px;
        min-width: 22px;
        text-align: center;
    }

    .nav-item.active .nav-badge { background: rgba(255,255,255,0.3); }

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
        background: var(--primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
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
        flex: 1;
        min-height: 100vh;
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
    }

    .topbar-title h1 { font-size: 20px; font-weight: 700; color: var(--text-dark); }
    .topbar-title p { font-size: 13px; color: var(--text-muted); margin-top: 2px; }

    .topbar-date {
        font-size: 13px;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .page-content { padding: 28px 32px; }

    /* ===== SHARED COMPONENTS ===== */
    .content-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
    }

    .card-header-bar {
        padding: 18px 22px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-header-bar h3 { font-size: 15px; font-weight: 700; color: var(--text-dark); }

    /* Data Tables */
    .data-table { width: 100%; border-collapse: collapse; }

    .data-table th {
        padding: 12px 22px;
        text-align: left;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #fafbfc;
        border-bottom: 1px solid var(--border);
    }

    .data-table td {
        padding: 14px 22px;
        font-size: 13px;
        color: var(--text-dark);
        border-bottom: 1px solid var(--border);
    }

    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: #fafbfc; }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-badge.pending { background: var(--warning-bg); color: #b45309; }
    .status-badge.approved { background: var(--success-bg); color: #047857; }
    .status-badge.rejected { background: var(--danger-bg); color: #b91c1c; }
    .status-badge.admin { background: #fef2f2; color: #b91c1c; }
    .status-badge.user { background: #eff6ff; color: #1d4ed8; }
    .status-badge.seller { background: var(--success-bg); color: #047857; }
    .status-badge.buyer { background: #f4f4f5; color: #52525b; }

    .status-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
    }

    .status-dot.pending { background: var(--warning); }
    .status-dot.approved { background: var(--success); }
    .status-dot.rejected { background: var(--danger); }

    /* Action Buttons */
    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .action-btn.view { background: #eff6ff; color: #2563eb; }
    .action-btn.view:hover { background: #dbeafe; }
    .action-btn.approve { background: var(--success-bg); color: #047857; }
    .action-btn.approve:hover { background: #d1fae5; }
    .action-btn.reject { background: var(--danger-bg); color: #b91c1c; }
    .action-btn.reject:hover { background: #fee2e2; }
    .action-btn.promote { background: #fffbeb; color: #b45309; }
    .action-btn.promote:hover { background: #fef3c7; }
    .action-btn.delete { background: var(--danger-bg); color: #b91c1c; }
    .action-btn.delete:hover { background: #fee2e2; }

    /* Alerts */
    .alert-bar {
        margin: 0 22px 0 22px;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        margin-top: 16px;
    }

    .alert-bar.success { background: var(--success-bg); color: #047857; border: 1px solid #a7f3d0; }
    .alert-bar.error { background: var(--danger-bg); color: #b91c1c; border: 1px solid #fecaca; }

    /* Empty State */
    .empty-state {
        padding: 50px 20px;
        text-align: center;
        color: var(--text-light);
    }

    .empty-state i { font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.4; }
    .empty-state p { font-size: 13px; margin-top: 4px; }

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
        .main-content { margin-left: 0; }
        .sidebar-toggle { display: block; }
        .topbar { padding: 14px 18px; }
        .page-content { padding: 20px 18px; }
    }
</style>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="<?php echo (strpos($currentPage, 'admin_users') !== false) ? 'images/logo.png' : '../images/logo.png'; ?>" alt="BookWagon">
    </div>

    <nav class="sidebar-nav">
        <a href="<?php echo (strpos($currentPage, 'admin_users') !== false) ? 'Admin/admin_dashboard.php' : 'admin_dashboard.php'; ?>" class="nav-item <?php echo ($currentPage === 'admin_dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-gauge"></i>
            Dashboard
        </a>
        <a href="<?php echo (strpos($currentPage, 'admin_users') !== false) ? 'Admin/admin_seller_requests.php' : 'admin_seller_requests.php'; ?>" class="nav-item <?php echo ($currentPage === 'admin_seller_requests.php' || $currentPage === 'admin_view_seller_request.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-store"></i>
            Seller Requests
            <?php if($pendingSellers > 0): ?>
                <span class="nav-badge"><?php echo $pendingSellers; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo (strpos($currentPage, 'admin_users') !== false) ? 'admin_users.php' : '../admin_users.php'; ?>" class="nav-item <?php echo ($currentPage === 'admin_users.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-users"></i>
            Manage Users
        </a>
        <a href="<?php echo (strpos($currentPage, 'admin_users') !== false) ? 'Admin/audit_logs.php' : 'audit_logs.php'; ?>" class="nav-item <?php echo ($currentPage === 'audit_logs.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-shield-halved"></i>
            Audit Logs
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <?php echo strtoupper(substr($_SESSION["admin_username"] ?? 'A', 0, 1)); ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION["admin_username"] ?? 'Admin'); ?></div>
                <div class="sidebar-user-role">Administrator</div>
            </div>
            <a href="<?php echo (strpos($currentPage, 'admin_users') !== false) ? 'Admin/logout.php' : 'logout.php'; ?>" title="Sign Out" style="color: var(--text-muted); font-size: 14px; margin-left: auto;">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>

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
