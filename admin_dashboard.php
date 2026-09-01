<?php
session_start();
require_once 'connect.php';

// Authorization: Check if user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// We need to fetch the user's role from the DB to be secure (don't just trust session)
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin') {
    // If not admin, redirect to normal dashboard or show access denied
    die("Access Denied. You do not have permission to view this page.");
}

// Fetch basic stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_sellers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_seller = 1")->fetchColumn();

// Fetch recent audit logs
$logs = $pdo->query("
    SELECT a.*, u.email 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 20
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BookWagon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #f8a100;
            --secondary-color: #f8f9fa;
        }
        body { background-color: var(--secondary-color); }
        .admin-header {
            background-color: #343a40; /* Darker theme for admin */
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
    </style>
</head>
<body>

<div class="admin-header">
    <div class="container d-flex justify-content-between align-items-center">
        <h4>BookWagon Admin Panel</h4>
        <div>
            <a href="admin_dashboard.php" class="text-white text-decoration-none me-3">Dashboard</a>
            <a href="admin_users.php" class="text-white text-decoration-none me-3">Manage Users</a>
            <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
        </div>
    </div>
</div>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <h3><?php echo $total_users; ?></h3>
                <p class="text-muted mb-0">Total Registered Users</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <h3><?php echo $total_sellers; ?></h3>
                <p class="text-muted mb-0">Registered Sellers</p>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Recent Audit Logs</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>User Email</th>
                            <th>Activity</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['email'] ?? 'Unknown/Deleted'); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['activity']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-3">No logs found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
