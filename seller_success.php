<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("connect.php");

$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$firstName = $_SESSION['firstname'] ?? 'Book Lover';
$userType = $_SESSION['usertype'] ?? 'user';

// Check seller status from database
$sellerStatus = 'pending';
$shopName = '';
if ($userId) {
    $stmt = $conn->prepare("SELECT shop_name, status, created_at FROM sellers WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $sellerStatus = $row['status'];
            $shopName = $row['shop_name'];
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status - BookWagon</title>
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #f8a100;
            --text-dark: #212529;
            --border-color: #dee2e6;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            background: white;
        }
        
        .navbar-brand img {
            height: 60px;
        }

        .status-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 40px;
            text-align: center;
            max-width: 600px;
            margin: 60px auto;
            border: 1px solid var(--border-color);
        }

        .status-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        .status-icon.pending {
            color: #f8a100;
        }
        
        .status-icon.approved {
            color: #198754;
        }

        .btn-primary-custom {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-primary-custom:hover {
            background-color: #e08f00;
            color: white;
        }

        .steps-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            margin-top: 30px;
            border: 1px solid #e9ecef;
        }
    </style>
</head>
<body>

    <!-- Include Header -->
    <?php 
    if ($userType == 'user') {
        include("include/user_header.php");
    } elseif ($userType == 'seller') {
        include("include/seller_header.php");
    } else {
        include("include/user_header.php");
    }
    ?>

    <div class="container">
        <div class="status-card">
            <?php if ($sellerStatus === 'approved'): ?>
                <div class="status-icon approved">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h3 class="fw-bold mb-3">You are an Approved Seller!</h3>
                <p class="text-muted mb-4">
                    Your store <strong><?php echo htmlspecialchars($shopName ?: 'BookWagon Store'); ?></strong> is live! You can now start adding books to your collection.
                </p>
                <a href="seller_dashboard.php" class="btn btn-primary-custom">
                    <i class="fa-solid fa-store me-2"></i> Go to Seller Dashboard
                </a>
            <?php else: ?>
                <div class="status-icon pending">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <h3 class="fw-bold mb-3">Application Under Review</h3>
                <p class="text-muted mb-4">
                    Thank you, <strong><?php echo htmlspecialchars($firstName); ?></strong>! Your seller application and documents have been received and are currently waiting for admin approval.
                </p>

                <div class="steps-box">
                    <h6 class="fw-bold mb-3">What happens next?</h6>
                    <div class="d-flex mb-3">
                        <i class="fa-solid fa-id-card text-muted mt-1 me-3"></i>
                        <div>
                            <strong>1. Identity Verification</strong><br>
                            <small class="text-muted">Our team reviews your submitted IDs.</small>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i class="fa-solid fa-check-double text-muted mt-1 me-3"></i>
                        <div>
                            <strong>2. Store Approval</strong><br>
                            <small class="text-muted">Your account will be upgraded to a Seller Account.</small>
                        </div>
                    </div>
                    <div class="d-flex">
                        <i class="fa-solid fa-book-open text-muted mt-1 me-3"></i>
                        <div>
                            <strong>3. Start Selling</strong><br>
                            <small class="text-muted">You can begin uploading books for rent or sale!</small>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="account.php" class="btn btn-primary-custom">
                        <i class="fa-solid fa-arrow-left me-2"></i> Back to My Account
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>