<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? '';
$userId = $_SESSION['id'] ?? 0;
$firstName = $_SESSION['firstname'] ?? '';
$lastName = $_SESSION['lastname'] ?? '';

// Ensure only sellers can access this page
if ($userType !== 'seller') {
    header("Location: login.php");
    exit();
}

// Fetch basic seller stats
$shopName = 'My Store';
$sellerStmt = $conn->prepare("SELECT shop_name FROM sellers WHERE user_id = ?");
if ($sellerStmt) {
    $sellerStmt->bind_param("i", $userId);
    $sellerStmt->execute();
    $sellerRes = $sellerStmt->get_result();
    if ($row = $sellerRes->fetch_assoc()) {
        $shopName = $row['shop_name'];
    }
}

// Stats placeholders (In a real app, these would be queries)
$totalRevenue = 0;
$totalOrders = 0;
$activeBooks = 0;
$totalRenters = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($shopName); ?></title>
    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-orange: #f8a100;
            --primary-dark: #d97706;
            --bg-neutral: #f8fafc;
            --card-border: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-neutral);
            color: var(--text-dark);
        }

        /* Dashboard Cards */
        .dashboard-container {
            padding: 30px 0;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--card-border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.04);
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .stat-icon.revenue { background: #fffbeb; color: #d97706; }
        .stat-icon.orders { background: #eff6ff; color: #2563eb; }
        .stat-icon.books { background: #ecfdf5; color: #059669; }
        .stat-icon.renters { background: #fef2f2; color: #dc2626; }

        .stat-details h6 {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-details h3 {
            margin: 4px 0 0 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
        }

        /* Section Panels */
        .dashboard-panel {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--card-border);
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .panel-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 17px;
        }

        .view-all-link {
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-orange);
            text-decoration: none;
        }

        .view-all-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }
        .empty-state h6 {
            font-weight: 700;
            margin-bottom: 8px;
        }
        .empty-state p {
            font-size: 14px;
            color: var(--text-muted);
            max-width: 300px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

    <!-- Include the fixed sidebar -->
    <?php include("include/seller_sidebar.php"); ?>

    <!-- Main Content wrapper matching the sidebar layout -->
    <div class="main-content">
        <div class="page-content">
            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($firstName); ?>!</h3>
                    <p class="text-muted mb-0">Here's what's happening in <strong><?php echo htmlspecialchars($shopName); ?></strong> today.</p>
                </div>
                <a href="Manage_books.php" class="btn btn-warning fw-bold text-white px-4 py-2 rounded-pill" style="background: var(--primary-orange); border: none;">
                    <i class="fa-solid fa-plus me-2"></i> Add New Book
                </a>
            </div>

            <!-- 4 Stat Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon revenue"><i class="fa-solid fa-peso-sign"></i></div>
                        <div class="stat-details">
                            <h6>Total Revenue</h6>
                            <h3>₱<?php echo number_format($totalRevenue, 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon orders"><i class="fa-solid fa-cart-shopping"></i></div>
                        <div class="stat-details">
                            <h6>Active Orders</h6>
                            <h3><?php echo $totalOrders; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon books"><i class="fa-solid fa-book-open"></i></div>
                        <div class="stat-details">
                            <h6>Listed Books</h6>
                            <h3><?php echo $activeBooks; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon renters"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-details">
                            <h6>Total Renters</h6>
                            <h3><?php echo $totalRenters; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Orders Panel -->
                <div class="col-lg-8">
                    <div class="dashboard-panel h-100">
                        <div class="panel-header">
                            <h5>Recent Orders</h5>
                            <a href="order.php" class="view-all-link">View All Orders <i class="fa-solid fa-arrow-right ms-1"></i></a>
                        </div>
                        <div class="empty-state">
                            <i class="fa-solid fa-box-open"></i>
                            <h6>No orders yet</h6>
                            <p>When customers rent or buy your books, their orders will appear here.</p>
                        </div>
                    </div>
                </div>

                <!-- Top Books Panel -->
                <div class="col-lg-4">
                    <div class="dashboard-panel h-100">
                        <div class="panel-header">
                            <h5>Most Popular Books</h5>
                            <a href="Manage_books.php" class="view-all-link">Manage <i class="fa-solid fa-arrow-right ms-1"></i></a>
                        </div>
                        <div class="empty-state">
                            <i class="fa-solid fa-book"></i>
                            <h6>No books listed</h6>
                            <p>Start listing your books to see which ones are the most popular!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>