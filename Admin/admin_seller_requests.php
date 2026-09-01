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

// Process approval/rejection
if(isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $seller_id = $_GET['id'];
    
    if($action == 'approve') {
        $user_query = "SELECT user_id FROM sellers WHERE id = ?";
        $stmt = $conn->prepare($user_query);
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($row = $result->fetch_assoc()) {
            $user_id = $row['user_id'];
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE sellers SET status = 'approved' WHERE id = ?");
                $stmt->bind_param("i", $seller_id);
                $stmt->execute();
                
                $stmt = $conn->prepare("UPDATE users SET usertype = 'seller' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                $conn->commit();
                $success_message = "Seller request approved successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } elseif($action == 'reject') {
        $stmt = $conn->prepare("UPDATE sellers SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $seller_id);
        if($stmt->execute()) {
            $success_message = "Seller request rejected.";
        } else {
            $error_message = "Error updating record: " . $conn->error;
        }
    }
}

// Fetch all seller requests
$sql = "SELECT s.*, u.email, u.username FROM sellers s 
        JOIN users u ON s.user_id = u.id 
        ORDER BY s.created_at DESC";
$result = $conn->query($sql);

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Requests - BookWagon Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .shop-logo {
            width: 42px; height: 42px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .shop-logo-placeholder {
            width: 42px; height: 42px;
            border-radius: 8px;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 16px;
        }
        .filter-tabs {
            display: flex;
            gap: 6px;
        }
        .filter-tab {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text-muted);
            background: var(--card-bg);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .filter-tab:hover { border-color: var(--primary); color: var(--primary); }
        .filter-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
    </style>
</head>
<body>

    <?php include "admin_sidebar.php"; ?>

    <main class="main-content">
        <div class="topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title">
                    <h1>Seller Requests</h1>
                    <p>Review and manage seller verification applications</p>
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
                    <h3><i class="fa-solid fa-store" style="color: var(--primary); margin-right: 8px;"></i>All Seller Requests</h3>
                    <div class="filter-tabs">
                        <span class="filter-tab active" onclick="filterTable('all', this)">All</span>
                        <span class="filter-tab" onclick="filterTable('pending', this)">Pending</span>
                        <span class="filter-tab" onclick="filterTable('approved', this)">Approved</span>
                        <span class="filter-tab" onclick="filterTable('rejected', this)">Rejected</span>
                    </div>
                </div>

                <?php if(isset($success_message)): ?>
                    <div class="alert-bar success"><i class="fa-solid fa-check-circle" style="margin-right: 6px;"></i><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if(isset($error_message)): ?>
                    <div class="alert-bar error"><i class="fa-solid fa-exclamation-circle" style="margin-right: 6px;"></i><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if($result->num_rows > 0): ?>
                    <table class="data-table" id="sellerTable">
                        <thead>
                            <tr>
                                <th>Shop</th>
                                <th>Owner</th>
                                <th>Email</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr data-status="<?php echo $row['status']; ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <?php if(!empty($row['shop_logo']) && file_exists($row['shop_logo'])): ?>
                                            <img src="<?php echo $row['shop_logo']; ?>" class="shop-logo" alt="Logo">
                                        <?php else: ?>
                                            <div class="shop-logo-placeholder"><i class="fa-solid fa-store"></i></div>
                                        <?php endif; ?>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($row['shop_name']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                <td style="color: var(--text-muted);"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td style="color: var(--text-muted); font-size: 12px;"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $row['status']; ?>">
                                        <span class="status-dot <?php echo $row['status']; ?>"></span>
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <a href="admin_view_seller_request.php?id=<?php echo $row['id']; ?>" class="action-btn view">
                                            <i class="fa-solid fa-eye"></i> View
                                        </a>
                                        <?php if($row['status'] == 'pending'): ?>
                                            <a href="admin_seller_requests.php?action=approve&id=<?php echo $row['id']; ?>" class="action-btn approve" onclick="return confirm('Approve this seller?')">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </a>
                                            <a href="admin_seller_requests.php?action=reject&id=<?php echo $row['id']; ?>" class="action-btn reject" onclick="return confirm('Reject this seller?')">
                                                <i class="fa-solid fa-xmark"></i> Reject
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-store-slash"></i>
                        <h3 style="font-size: 15px; font-weight: 600; color: var(--text-dark); margin-bottom: 4px;">No seller requests found</h3>
                        <p>There are currently no seller requests to review.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function filterTable(status, el) {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            el.classList.add('active');

            document.querySelectorAll('#sellerTable tbody tr').forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>