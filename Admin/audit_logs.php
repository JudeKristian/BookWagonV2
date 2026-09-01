<?php
session_start();
if(!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true){
    header("location: index.php");
    exit;
}

require_once "db_connect.php";

// Get pending count for sidebar badge
$pendingSellers = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM sellers WHERE status = 'pending'");
if ($res && $row = $res->fetch_assoc()) $pendingSellers = $row['cnt'];

// Fetch logs
$logs = [];
$sql = "SELECT a.id AS log_id, a.user_id, a.activity, a.details, a.created_at, 
               COALESCE(u.email, 'Admin/Unknown') as user_email
        FROM audit_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        ORDER BY a.created_at DESC LIMIT 100";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - BookWagon Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .activity-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            background: var(--primary-light);
            color: var(--primary-dark);
        }
    </style>
</head>
<body>

    <?php include "admin_sidebar.php"; ?>

    <main class="main-content">
        <div class="topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title">
                    <h1>Audit & Security Logs</h1>
                    <p>Track user and admin activities for security compliance</p>
                </div>
            </div>
            <div class="topbar-date">
                <i class="fa-regular fa-calendar"></i>
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <div class="page-content">
            <div class="content-card">
                <div class="card-header-bar">
                    <h3><i class="fa-solid fa-shield-halved" style="color: var(--primary); margin-right: 8px;"></i>Recent Security Activities</h3>
                    <span style="font-size: 12px; color: var(--text-muted);"><?php echo count($logs); ?> entries</span>
                </div>

                <?php if(empty($logs)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <h3 style="font-size: 15px; font-weight: 600; color: var(--text-dark); margin-bottom: 4px;">No audit logs found</h3>
                        <p>No security activities have been recorded yet.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Activity</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;">
                                    <div style="font-size: 13px;"><?php echo date('M j, Y', strtotime($log['created_at'])); ?></div>
                                    <div style="font-size: 11px; color: var(--text-light); margin-top: 2px;"><?php echo date('g:i:s A', strtotime($log['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 13px;">#<?php echo htmlspecialchars($log['user_id']); ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($log['user_email']); ?></div>
                                </td>
                                <td>
                                    <span class="activity-type-badge">
                                        <i class="fa-solid fa-bolt" style="font-size: 10px;"></i>
                                        <?php echo htmlspecialchars($log['activity']); ?>
                                    </span>
                                </td>
                                <td style="color: var(--text-muted); font-size: 13px; max-width: 300px;">
                                    <?php echo htmlspecialchars($log['details']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
