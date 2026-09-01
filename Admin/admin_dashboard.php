<?php
session_start();
if(!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true){
    header("location: index.php");
    exit;
}

require_once "db_connect.php";

$totalBooks = 0;
$totalUsers = 0;
$pendingSellers = 0;

if ($conn) {
    $res = $conn->query("SELECT COUNT(*) as cnt FROM books");
    if ($res && $row = $res->fetch_assoc()) $totalBooks = $row['cnt'];
    
    $res = $conn->query("SELECT COUNT(*) as cnt FROM users");
    if ($res && $row = $res->fetch_assoc()) $totalUsers = $row['cnt'];
    
    $res = $conn->query("SELECT COUNT(*) as cnt FROM sellers WHERE status = 'pending'");
    if ($res && $row = $res->fetch_assoc()) $pendingSellers = $row['cnt'];
}

// Recent seller requests
$recentSellers = [];
if ($conn) {
    $sql = "SELECT s.id, s.shop_name, s.first_name, s.last_name, s.status, s.created_at, 
                   COALESCE(u.email, '-') as email
            FROM sellers s LEFT JOIN users u ON s.user_id = u.id 
            ORDER BY s.created_at DESC LIMIT 5";
    $result = $conn->query($sql);
    if ($result) { while ($row = $result->fetch_assoc()) $recentSellers[] = $row; }
}

// Recent audit logs
$recentLogs = [];
if ($conn) {
    $sql = "SELECT a.id, a.activity, a.details, a.created_at, 
                   COALESCE(u.email, 'System') as user_email
            FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id 
            ORDER BY a.created_at DESC LIMIT 5";
    $result = $conn->query($sql);
    if ($result) { while ($row = $result->fetch_assoc()) $recentLogs[] = $row; }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookWagon - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

    <?php include "admin_sidebar.php"; ?>

    <main class="main-content">
        <div class="topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title"><h1>Overview</h1></div>
            </div>
            <div class="topbar-date">
                <i class="fa-regular fa-calendar"></i>
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <div class="page-content">
            <!-- Stat Cards -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 28px;">
                <div class="content-card" style="padding: 22px 24px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                        <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">Pending Sellers</span>
                        <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; background: #fff7ed; color: #ea580c;">
                            <i class="fa-solid fa-user-clock"></i>
                        </div>
                    </div>
                    <div style="font-size: 28px; font-weight: 700; color: var(--text-dark);"><?php echo $pendingSellers; ?></div>
                </div>

                <div class="content-card" style="padding: 22px 24px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                        <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">Total Books</span>
                        <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; background: #eff6ff; color: #2563eb;">
                            <i class="fa-solid fa-book"></i>
                        </div>
                    </div>
                    <div style="font-size: 28px; font-weight: 700; color: var(--text-dark);"><?php echo number_format($totalBooks); ?></div>
                </div>

                <div class="content-card" style="padding: 22px 24px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                        <span style="font-size: 13px; font-weight: 500; color: var(--text-muted);">Registered Users</span>
                        <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; background: #ecfdf5; color: #059669;">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <div style="font-size: 28px; font-weight: 700; color: var(--text-dark);"><?php echo number_format($totalUsers); ?></div>
                </div>
            </div>

            <!-- Content Grid -->
            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px;">
                <!-- Recent Seller Requests -->
                <div class="content-card">
                    <div class="card-header-bar">
                        <h3>Recent Seller Requests</h3>
                        <a href="admin_seller_requests.php" style="font-size: 12px; font-weight: 600; color: var(--primary-dark); text-decoration: none;">View All <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <?php if (empty($recentSellers)): ?>
                        <div class="empty-state"><i class="fa-solid fa-store-slash"></i>No seller requests yet.</div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead><tr><th>Seller</th><th>Shop</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentSellers as $seller): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?></div>
                                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($seller['email']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($seller['shop_name']); ?></td>
                                    <td><span class="status-badge <?php echo $seller['status']; ?>"><span class="status-dot <?php echo $seller['status']; ?>"></span><?php echo ucfirst($seller['status']); ?></span></td>
                                    <td style="color: var(--text-muted); font-size: 12px;"><?php echo date('M j, Y', strtotime($seller['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="content-card">
                    <div class="card-header-bar">
                        <h3>Recent Activity</h3>
                        <a href="audit_logs.php" style="font-size: 12px; font-weight: 600; color: var(--primary-dark); text-decoration: none;">View All <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <?php if (empty($recentLogs)): ?>
                        <div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>No activity recorded yet.</div>
                    <?php else: ?>
                        <div>
                            <?php foreach ($recentLogs as $i => $log): ?>
                            <div style="display: flex; gap: 14px; padding: 16px 22px; border-bottom: 1px solid var(--border);<?php if ($i === count($recentLogs) - 1) echo 'border-bottom:none;'; ?>">
                                <div style="display: flex; flex-direction: column; align-items: center; padding-top: 4px;">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--primary); flex-shrink: 0;"></div>
                                    <?php if ($i < count($recentLogs) - 1): ?>
                                        <div style="width: 2px; flex: 1; background: var(--border); margin-top: 6px;"></div>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 13px; font-weight: 600; margin-bottom: 3px;"><?php echo htmlspecialchars($log['activity']); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); line-height: 1.4; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?php echo htmlspecialchars($log['details']); ?></div>
                                    <div style="font-size: 11px; color: var(--text-light); margin-top: 4px;">
                                        <i class="fa-regular fa-clock"></i>
                                        <?php 
                                            $diff = time() - strtotime($log['created_at']);
                                            if ($diff < 60) echo 'Just now';
                                            elseif ($diff < 3600) echo floor($diff / 60) . 'm ago';
                                            elseif ($diff < 86400) echo floor($diff / 3600) . 'h ago';
                                            else echo date('M j, g:i A', strtotime($log['created_at']));
                                        ?>
                                        &middot; <?php echo htmlspecialchars($log['user_email']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>