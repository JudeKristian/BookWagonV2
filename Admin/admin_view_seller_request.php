<?php
// Initialize the session
session_start();
 
// Check if the user is logged in as admin, if not then redirect to admin login page
if(!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Include database connection
require_once "db_connect.php";

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: admin_seller_requests.php");
    exit;
}

$id = $_GET['id'];

// Fetch seller request details
$sql = "SELECT s.*, u.email, u.username, u.created_at as user_created_at 
        FROM sellers s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows != 1) {
    // If no record found, redirect back to seller list
    header("location: admin_seller_requests.php");
    exit;
}

$seller = $result->fetch_assoc();

// Process approval/rejection actions
if(isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if($action == 'approve') {
        // Start a transaction
        $conn->begin_transaction();
        
        try {
            // Update seller status to approved
            $update_seller = "UPDATE sellers SET status = 'approved' WHERE id = ?";
            $stmt = $conn->prepare($update_seller);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            // Update user type to seller
            $update_user = "UPDATE users SET usertype = 'seller' WHERE id = ?";
            $stmt = $conn->prepare($update_user);
            $stmt->bind_param("i", $seller['user_id']);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Seller request approved successfully!";
            
            // Refresh seller data
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $seller = $result->fetch_assoc();
            
        } catch (Exception $e) {
            // Roll back transaction on error
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    } elseif($action == 'reject') {
        // Update seller status to rejected
        $update_seller = "UPDATE sellers SET status = 'rejected' WHERE id = ?";
        $stmt = $conn->prepare($update_seller);
        $stmt->bind_param("i", $id);
        
        if($stmt->execute()) {
            $success_message = "Seller request rejected.";
            
            // Refresh seller data
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $seller = $result->fetch_assoc();
        } else {
            $error_message = "Error updating record: " . $conn->error;
        }
    }
}

// Get pending count for sidebar badge
$pendingSellers = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM sellers WHERE status = 'pending'");
if ($res && $row = $res->fetch_assoc()) $pendingSellers = $row['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Seller Request - BookWagon Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        .detail-section {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
        }
        .detail-section h4 {
            margin: 0 0 16px 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .detail-row:last-child {
            margin-bottom: 0;
        }
        .detail-label {
            width: 130px;
            color: var(--text-muted);
            font-weight: 500;
        }
        .detail-value {
            flex: 1;
            color: var(--text-dark);
            font-weight: 500;
        }
        .shop-logo-large {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--border);
            margin-bottom: 16px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-badge.pending { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .status-badge.approved { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .status-badge.rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        
        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .doc-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            text-align: center;
            background: #fafafa;
        }
        .doc-card img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
            transition: transform 0.2s;
        }
        .doc-card img:hover {
            transform: scale(1.02);
        }
        .doc-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
        }
        .doc-subtitle {
            font-size: 11px;
            color: var(--text-muted);
        }
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }
        .btn-approve {
            background: #10b981;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-approve:hover { background: #059669; }
        .btn-reject {
            background: #ef4444;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-reject:hover { background: #dc2626; }
    </style>
</head>
<body>

    <?php include "admin_sidebar.php"; ?>

    <main class="main-content">
        <div class="topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <a href="admin_seller_requests.php" style="color: var(--text-muted);"><i class="fa-solid fa-arrow-left"></i></a>
                        <h1>Review Seller Request</h1>
                    </div>
                    <p>Review documents and approve or reject this application</p>
                </div>
            </div>
        </div>

        <div class="page-content">
            <?php if(isset($success_message)): ?>
                <div class="alert-bar success" style="margin-bottom: 20px;"><i class="fa-solid fa-check-circle" style="margin-right: 6px;"></i><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if(isset($error_message)): ?>
                <div class="alert-bar error" style="margin-bottom: 20px;"><i class="fa-solid fa-exclamation-circle" style="margin-right: 6px;"></i><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="content-card" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <?php if(!empty($seller['shop_logo']) && file_exists('../' . $seller['shop_logo'])): ?>
                        <img src="../<?php echo $seller['shop_logo']; ?>" alt="Logo" class="shop-logo-large">
                    <?php else: ?>
                        <div class="shop-logo-large" style="display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #94a3b8; font-size: 24px;">
                            <i class="fa-solid fa-store"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h2 style="margin: 0 0 6px 0; font-size: 20px; color: var(--text-dark);"><?php echo htmlspecialchars($seller['shop_name']); ?></h2>
                        <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 8px;">Applied on <?php echo date('M d, Y', strtotime($seller['created_at'])); ?></div>
                        <span class="status-badge <?php echo $seller['status']; ?>">
                            <?php if($seller['status'] == 'pending') echo '<i class="fa-solid fa-clock"></i> Pending Review'; ?>
                            <?php if($seller['status'] == 'approved') echo '<i class="fa-solid fa-check"></i> Approved'; ?>
                            <?php if($seller['status'] == 'rejected') echo '<i class="fa-solid fa-xmark"></i> Rejected'; ?>
                        </span>
                    </div>
                </div>
                
                <?php if($seller['status'] == 'pending'): ?>
                    <div class="action-buttons" style="margin: 0;">
                        <form method="post" action="" style="display: inline;">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn-approve" onclick="return confirm('Are you sure you want to approve this seller request?')"><i class="fa-solid fa-check me-2"></i> Approve</button>
                        </form>
                        <form method="post" action="" style="display: inline;">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn-reject" onclick="return confirm('Are you sure you want to reject this seller request?')"><i class="fa-solid fa-xmark me-2"></i> Reject</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="detail-grid">
                <div class="detail-section">
                    <h4><i class="fa-solid fa-user" style="color: var(--primary); margin-right: 8px;"></i> Owner Information</h4>
                    <div class="detail-row">
                        <div class="detail-label">Full Name:</div>
                        <div class="detail-value"><?php echo htmlspecialchars(trim($seller['first_name'] . ' ' . $seller['middle_name'] . ' ' . $seller['last_name'])); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Email:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($seller['email']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Phone:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($seller['business_phone'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Member Since:</div>
                        <div class="detail-value"><?php echo date('M d, Y', strtotime($seller['user_created_at'])); ?></div>
                    </div>
                </div>

                <div class="detail-section">
                    <h4><i class="fa-solid fa-location-dot" style="color: var(--primary); margin-right: 8px;"></i> Location & Address</h4>
                    <div class="detail-row">
                        <div class="detail-label">Region/City:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($seller['location'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Full Address:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($seller['address'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Zip Code:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($seller['zip_code'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <h3 style="margin: 0 0 20px 0; font-size: 16px; color: var(--text-dark); border-bottom: 1px solid var(--border); padding-bottom: 12px;">
                    <i class="fa-solid fa-id-card" style="color: var(--primary); margin-right: 8px;"></i> Verification Documents
                </h3>
                
                <div class="doc-grid">
                    <div class="doc-card">
                        <?php if (!empty($seller['primary_id_front']) && file_exists('../' . $seller['primary_id_front'])): ?>
                            <a href="../<?php echo $seller['primary_id_front']; ?>" target="_blank">
                                <img src="../<?php echo $seller['primary_id_front']; ?>" alt="Primary ID Front">
                            </a>
                        <?php else: ?>
                            <div style="height: 140px; background: #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #94a3b8; margin-bottom: 12px;">No Image</div>
                        <?php endif; ?>
                        <div class="doc-title">Primary ID (Front)</div>
                        <div class="doc-subtitle"><?php echo htmlspecialchars($seller['primary_id_type'] ?? 'ID'); ?></div>
                    </div>

                    <div class="doc-card">
                        <?php if (!empty($seller['primary_id_back']) && file_exists('../' . $seller['primary_id_back'])): ?>
                            <a href="../<?php echo $seller['primary_id_back']; ?>" target="_blank">
                                <img src="../<?php echo $seller['primary_id_back']; ?>" alt="Primary ID Back">
                            </a>
                        <?php else: ?>
                            <div style="height: 140px; background: #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #94a3b8; margin-bottom: 12px;">No Image</div>
                        <?php endif; ?>
                        <div class="doc-title">Primary ID (Back)</div>
                        <div class="doc-subtitle"><?php echo htmlspecialchars($seller['primary_id_type'] ?? 'ID'); ?></div>
                    </div>

                    <div class="doc-card">
                        <?php if (!empty($seller['secondary_id_front']) && file_exists('../' . $seller['secondary_id_front'])): ?>
                            <a href="../<?php echo $seller['secondary_id_front']; ?>" target="_blank">
                                <img src="../<?php echo $seller['secondary_id_front']; ?>" alt="Secondary ID Front">
                            </a>
                        <?php else: ?>
                            <div style="height: 140px; background: #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #94a3b8; margin-bottom: 12px;">No Image</div>
                        <?php endif; ?>
                        <div class="doc-title">Secondary ID (Front)</div>
                        <div class="doc-subtitle"><?php echo htmlspecialchars($seller['secondary_id_type'] ?? 'ID'); ?></div>
                    </div>

                    <div class="doc-card">
                        <?php if (!empty($seller['selfie_image']) && file_exists('../' . $seller['selfie_image'])): ?>
                            <a href="../<?php echo $seller['selfie_image']; ?>" target="_blank">
                                <img src="../<?php echo $seller['selfie_image']; ?>" alt="Selfie">
                            </a>
                        <?php else: ?>
                            <div style="height: 140px; background: #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #94a3b8; margin-bottom: 12px;">No Image</div>
                        <?php endif; ?>
                        <div class="doc-title">Selfie Verification</div>
                        <div class="doc-subtitle">Identity Match</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>