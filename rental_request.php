<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? '';
$userId = $_SESSION['id'] ?? 0;

// Ensure only sellers can access this page
if ($userType !== 'seller') {
    header("Location: login.php");
    exit();
}

// First, get the seller's ID
$sellerStmt = $conn->prepare("SELECT id FROM sellers WHERE user_id = ?");
$sellerStmt->bind_param("i", $userId);
$sellerStmt->execute();
$sellerResult = $sellerStmt->get_result();
$sellerData = $sellerResult->fetch_assoc();

if (!$sellerData) {
    $_SESSION['error_message'] = "Seller profile not found.";
    header("Location: dashboard.php");
    exit();
}

$sellerId = $sellerData['id'];

// Process return request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $returnId = $_POST['return_id'] ?? 0;

    // Receive return
    if ($action === 'receive_return') {
        $receiveStmt = $conn->prepare("
            UPDATE book_returns 
            SET 
                status = 'received', 
                received_date = NOW()
            WHERE return_id = ? AND seller_id = ?
        ");
        $receiveStmt->bind_param("ii", $returnId, $sellerId);
        
        if ($receiveStmt->execute()) {
            $_SESSION['success_message'] = "Return request has been received.";
        } else {
            $_SESSION['error_message'] = "Failed to receive return request.";
        }
        header("Location: rental_request.php");
        exit();
    }

    // Inspect return
    if ($action === 'inspect_return') {
        $bookCondition = $_POST['book_condition'] ?? 'good';
        $damageDescription = $_POST['damage_description'] ?? '';
        $damageFee = floatval($_POST['damage_fee'] ?? 0);
        $additionalFee = floatval($_POST['additional_fee'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        $lateFee = floatval($_POST['late_fee'] ?? 0);

        $conn->begin_transaction();

        try {
            // Update return details
            $updateReturnStmt = $conn->prepare("
                UPDATE book_returns 
                SET 
                    status = 'completed', 
                    book_condition = ?, 
                    damage_description = ?, 
                    damage_fee = ?,
                    additional_fee = ?,
                    late_fee = ?,
                    notes = ?,
                    completed_date = NOW()
                WHERE return_id = ? AND seller_id = ?
            ");
            $updateReturnStmt->bind_param("ssdddsii", 
                $bookCondition, 
                $damageDescription, 
                $damageFee,
                $additionalFee,
                $lateFee,
                $notes,
                $returnId, 
                $sellerId
            );
            $updateReturnStmt->execute();

            // Update rental status
            $updateRentalStmt = $conn->prepare("
                UPDATE book_rentals r
                JOIN book_returns br ON r.rental_id = br.rental_id
                SET 
                    r.status = 'returned', 
                    r.return_date = NOW(),
                    r.book_condition = ?
                WHERE br.return_id = ?
            ");
            $updateRentalStmt->bind_param("si", $bookCondition, $returnId);
            $updateRentalStmt->execute();

            // Update book stock
            $updateBookStmt = $conn->prepare("
                UPDATE books b
                JOIN book_returns br ON b.book_id = br.book_id
                SET b.stock = b.stock + 1
                WHERE br.return_id = ?
            ");
            $updateBookStmt->bind_param("i", $returnId);
            $updateBookStmt->execute();

            $conn->commit();
            $_SESSION['success_message'] = "Return has been processed successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Failed to process return: " . $e->getMessage();
        }

        header("Location: rental_request.php");
        exit();
    }
}

// Fetch return requests from book_returns table with additional fields
$returnsQuery = "
    SELECT 
        br.return_id,
        br.rental_id,
        br.return_method,
        br.return_details,
        br.status,
        br.request_date,
        br.received_date,
        br.is_overdue,
        br.days_overdue,
        br.late_fee,
        br.additional_fee,
        br.damage_fee,
        br.book_condition,
        br.notes,
        b.title as book_title,
        b.author as book_author,
        b.cover_image,
        b.ISBN,
        u.firstname as renter_firstname,
        u.lastname as renter_lastname,
        u.email as renter_email,
        r.rental_weeks,
        r.total_price,
        b.stock
    FROM book_returns br
    JOIN book_rentals r ON br.rental_id = r.rental_id
    JOIN books b ON br.book_id = b.book_id
    JOIN users u ON br.user_id = u.id
    WHERE br.seller_id = ? AND br.status IN ('pending', 'received')
    ORDER BY br.request_date DESC
";

$stmt = $conn->prepare($returnsQuery);
$stmt->bind_param("i", $sellerId);
$stmt->execute();
$result = $stmt->get_result();
$returnRequests = $result->fetch_all(MYSQLI_ASSOC);

$totalReturnRequests = count($returnRequests);

// Get statistics
$statsQuery = "
    SELECT
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
        COUNT(CASE WHEN status = 'received' THEN 1 END) AS received_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count,
        COUNT(CASE WHEN is_overdue = 1 THEN 1 END) AS overdue_count,
        SUM(late_fee) AS total_late_fees,
        SUM(damage_fee) AS total_damage_fees
    FROM book_returns
    WHERE seller_id = ?
";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $sellerId);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = $statsResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Requests - BookWagon</title>
    
    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
        }
        
        .main-content {
            padding: 20px;
            min-height: 100vh;
        }
        
        /* Stats Container */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 24px;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
            border: 1px solid #e2e8f0;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }
        
        .stat-icon {
            border-radius: 12px;
            margin-right: 15px;
            font-size: 24px;
        }
        
        .stat-pending .stat-icon {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .stat-received .stat-icon {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }
        
        .stat-completed .stat-icon {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--secondary-color);
        }
        
        .stat-overdue .stat-icon {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .stat-details {
            flex: 1;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 14px;
            margin: 0;
        }
        
        .stat-bg-icon {
            position: absolute;
            right: -15px;
            bottom: -15px;
            font-size: 80px;
            opacity: 0.05;
        }
        
        /* Return cards */
        .rental-card {
            background-color: #ffffff;
            border-radius: var(--card-border-radius);
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .rental-card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .rental-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .rental-id {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .rental-id h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .rental-timestamp {
            font-size: 13px;
            color: #6b7280;
        }
        
        .rental-body {
            display: flex;
            padding: 20px;
        }
        
        .book-thumbnail {
            width: 120px;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 20px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        
        .book-thumbnail:hover {
            transform: scale(1.03);
            box-shadow: var(--shadow-md);
        }
        
        .rental-details {
            flex-grow: 1;
        }
        
        .book-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #1f2937;
        }
        
        .book-author {
            color: #6b7280;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 3px;
        }
        
        .detail-value {
            font-weight: 500;
            color: #1f2937;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .badge-received {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }
        
        .badge-overdue {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .return-method-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 12px;
            background-color: #f3f4f6;
            color: #4b5563;
        }
        
        .rental-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .rental-actions .btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-success {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-success:hover {
            background-color: #0ea271;
            border-color: #0ea271;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 0;
            background-color: #ffffff;
            border-radius: var(--card-border-radius);
            box-shadow: var(--shadow-sm);
            margin-top: 20px;
        }
        
        .empty-state-icon {
            font-size: 80px;
            color: #d1d5db;
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            font-weight: 600;
            margin-bottom: 10px;
            color: #1f2937;
        }
        
        /* Modal styling */
        .modal-content {
            border-radius: var(--card-border-radius);
            border: none;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;
            padding: 15px 20px;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .fee-calculator {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .fee-total {
            font-size: 18px;
            font-weight: 600;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
        }
        
        .condition-options {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .condition-option {
            flex: 1;
            text-align: center;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .condition-option.active {
            border-color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.05);
        }
        
        .condition-option:hover {
            border-color: var(--primary-light);
        }
        
        .condition-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .condition-text {
            font-weight: 500;
            font-size: 14px;
        }
        
        /* Toasts and alerts */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .custom-toast {
            background-color: white;
            color: #1f2937;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 300px;
        }
        
        .toast-success {
            border-left: 5px solid var(--secondary-color);
        }
        
        .toast-error {
            border-left: 5px solid var(--danger-color);
        }
        
        .toast-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .toast-success .toast-icon {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--secondary-color);
        }
        
        .toast-error .toast-icon {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .toast-message {
            font-size: 14px;
            color: #6b7280;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 18px;
        }
        
        /* Tab navigation */
        .tab-navigation {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }
        
        .tab-item {
            padding: 10px 20px;
            border-radius: 8px 8px 0 0;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .tab-item.active {
            color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.05);
        }
        
        .tab-item.active::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 10px 10px 0 0;
        }
        
        .tab-item:hover {
            color: var(--primary-dark);
        }
        
        .tab-counter {
            margin-left: 8px;
            background-color: #e5e7eb;
            color: #4b5563;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .tab-item.active .tab-counter {
            background-color: var(--primary-light);
            color: white;
        }
        
        /* Responsive tweaks */
        @media (max-width: 991px) {
            :root {
                --sidebar-width: 70px;
            }
            
            .sidebar {
                overflow: hidden;
            }
            
            .sidebar-menu li span,
            .sidebar-logo span {
                display: none;
            }
            
            .sidebar-menu li {
                justify-content: center;
                padding: 12px;
            }
            
            .sidebar-menu li.active, 
            .sidebar-menu li:hover {
                padding-left: 12px;
            }
            
            .sidebar-menu li i {
                margin-right: 0;
                font-size: 20px;
            }
            
            .stats-container {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 767px) {
            .rental-body {
                flex-direction: column;
            }
            
            .book-thumbnail {
                margin-right: 0;
                margin-bottom: 15px;
                width: 100%;
                height: 200px;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .tab-navigation {
                overflow-x: auto;
                padding-bottom: 5px;
            }
            
            .tab-item {
                white-space: nowrap;
                padding: 8px 15px;
            }
        }
        
        /* Progress tracker */
        .return-progress {
            display: flex;
            align-items: center;
            margin: 20px 0;
            position: relative;
        }
        
        .progress-line-fill {
            position: absolute;
            top: 25px;
            left: 50px;
            height: 4px;
            background-color: var(--primary-color);
            z-index: 0;
            transition: width 0.5s ease;
        }
        
        .step-complete .step-icon {
            background-color: var(--primary-color);
            color: white;
        }
        
        .step-complete .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .step-active .step-icon {
            background-color: var(--primary-light);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 0 0 5px rgba(99, 102, 241, 0.2);
        }
        
        .step-active .step-label {
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        /* Return details formatting */
        .return-details-panel {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .return-details-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .return-details-content {
            font-size: 14px;
            color: #4b5563;
            overflow-wrap: break-word;
        }
        
        .return-json-formatted {
            background-color: #f3f4f6;
            padding: 10px;
            border-radius: 6px;
            font-family: monospace;
            max-height: 150px;
            overflow-y: auto;
        }
        
        /* Timeline component */
        .return-timeline {
            position: relative;
            margin: 20px 0;
            padding-left: 30px;
        }
        
        .timeline-track {
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #e5e7eb;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .timeline-point {
            position: absolute;
            left: -30px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: white;
            border: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            z-index: 1;
        }
        
        .timeline-item.active .timeline-point {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .timeline-content {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: var(--shadow-sm);
        }
        
        .timeline-title {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .timeline-date {
            font-size: 12px;
            color: #6b7280;
        }
        
        .timeline-description {
            font-size: 14px;
            color: #4b5563;
        }
        
        /* Fees summary */
        .fees-summary {
            background-color: #f9fafb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .fee-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .fee-label {
            color: #4b5563;
        }
        
        .fee-value {
            font-weight: 500;
        }
        
        .fee-total {
            font-size: 16px;
            font-weight: 600;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <!-- Toast Container for Notifications -->
    <?php if (isset($_SESSION['success_message']) || isset($_SESSION['error_message'])): ?>
    <div class="toast-container">
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="custom-toast toast-success">
            <div class="toast-icon">
                <i class="fas fa-check"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">Success</div>
                <div class="toast-message"><?php echo $_SESSION['success_message']; ?></div>
            </div>
            <button class="toast-close">&times;</button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="custom-toast toast-error">
            <div class="toast-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">Error</div>
                <div class="toast-message"><?php echo $_SESSION['error_message']; ?></div>
            </div>
            <button class="toast-close">&times;</button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Include the fixed sidebar -->
    <?php include("include/seller_sidebar.php"); ?>

    <!-- Main Content wrapper matching the sidebar layout -->
    <div class="main-content">
        <div class="page-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Return Requests</h2>
                    <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportDataModal">
                        <i class="fas fa-download me-2"></i>Export Data
                    </a>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-container">
                    <div class="stat-card stat-pending">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-details">
                            <h3 class="stat-value"><?php echo $stats['pending_count'] ?? 0; ?></h3>
                            <p class="stat-label">Pending Returns</p>
                        </div>
                        <i class="fas fa-clock stat-bg-icon"></i>
                    </div>
                    
                    <div class="stat-card stat-received">
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-details">
                            <h3 class="stat-value"><?php echo $stats['received_count'] ?? 0; ?></h3>
                            <p class="stat-label">Received Returns</p>
                        </div>
                        <i class="fas fa-box stat-bg-icon"></i>
                    </div>
                    
                    <div class="stat-card stat-completed">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3 class="stat-value"><?php echo $stats['completed_count'] ?? 0; ?></h3>
                            <p class="stat-label">Completed Returns</p>
                        </div>
                        <i class="fas fa-check-circle stat-bg-icon"></i>
                    </div>
                    
                    <div class="stat-card stat-overdue">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3 class="stat-value"><?php echo $stats['overdue_count'] ?? 0; ?></h3>
                            <p class="stat-label">Overdue Returns</p>
                        </div>
                        <i class="fas fa-exclamation-circle stat-bg-icon"></i>
                    </div>
                </div>
                
                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <div class="tab-item active" data-filter="all">
                        All Returns <span class="tab-counter"><?php echo $totalReturnRequests; ?></span>
                    </div>
                    <div class="tab-item" data-filter="pending">
                        Pending <span class="tab-counter"><?php echo count(array_filter($returnRequests, function($r) { return $r['status'] === 'pending'; })); ?></span>
                    </div>
                    <div class="tab-item" data-filter="received">
                        Received <span class="tab-counter"><?php echo count(array_filter($returnRequests, function($r) { return $r['status'] === 'received'; })); ?></span>
                    </div>
                    <div class="tab-item" data-filter="overdue">
                        Overdue <span class="tab-counter"><?php echo count(array_filter($returnRequests, function($r) { return $r['is_overdue'] == 1; })); ?></span>
                    </div>
                </div>
                
                <!-- Return Requests List -->
                <?php if (empty($returnRequests)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h4>No Return Requests</h4>
                        <p class="text-muted">You don't have any pending return requests at the moment.</p>
                        <a href="rentals.php" class="btn btn-primary mt-3">
                            <i class="fas fa-exchange-alt me-2"></i>View Active Rentals
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($returnRequests as $request): ?>
                        <div class="rental-card" data-status="<?php echo $request['status']; ?>" data-overdue="<?php echo $request['is_overdue']; ?>">
                            <div class="rental-header">
                                <div class="rental-id">
                                    <i class="fas fa-undo-alt text-primary"></i>
                                    <h5>Return Request #<?php echo $request['return_id']; ?></h5>
                                    <span class="rental-timestamp ms-2">
                                        <i class="far fa-clock"></i> 
                                        <?php echo date('M j, Y, g:i a', strtotime($request['request_date'])); ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <?php if ($request['is_overdue'] == 1): ?>
                                        <span class="status-badge badge-overdue me-2">
                                            <i class="fas fa-exclamation-circle me-1"></i>
                                            Overdue <?php echo $request['days_overdue']; ?> days
                                        </span>
                                    <?php endif; ?>
                                    <span class="status-badge 
                                        <?php echo $request['status'] === 'pending' ? 'badge-pending' : 'badge-received'; ?>">
                                        <i class="fas <?php echo $request['status'] === 'pending' ? 'fa-clock' : 'fa-box-check'; ?> me-1"></i>
                                        <?php echo ucwords(str_replace('_', ' ', $request['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="rental-body">
                                <img 
                                    src="<?php echo !empty($request['cover_image']) ? $request['cover_image'] : 'img/default-book-cover.jpg'; ?>" 
                                    alt="<?php echo htmlspecialchars($request['book_title']); ?>" 
                                    class="book-thumbnail"
                                    onerror="this.src='img/default-book-cover.jpg';"
                                >
                                <div class="rental-details">
                                    <h4 class="book-title"><?php echo htmlspecialchars($request['book_title']); ?></h4>
                                    <p class="book-author">by <?php echo htmlspecialchars($request['book_author']); ?></p>
                                    
                                    <!-- Return Progress Tracker -->
                                    <div class="return-progress">
                                        <div class="progress-line"></div>
                                        <div class="progress-line-fill" style="width: <?php echo $request['status'] === 'pending' ? '33%' : '66%'; ?>"></div>
                                        
                                        <div class="progress-step <?php echo 'step-complete'; ?>">
                                            <div class="step-icon">
                                                <i class="fas fa-file-alt"></i>
                                            </div>
                                            <div class="step-label">Requested</div>
                                        </div>
                                        
                                        <div class="progress-step <?php echo $request['status'] === 'pending' ? '' : 'step-complete'; ?> <?php echo $request['status'] === 'received' ? 'step-active' : ''; ?>">
                                            <div class="step-icon">
                                                <i class="fas fa-box"></i>
                                            </div>
                                            <div class="step-label">Received</div>
                                        </div>
                                        
                                        <div class="progress-step <?php echo $request['status'] === 'completed' ? 'step-complete' : ''; ?>">
                                            <div class="step-icon">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="step-label">Completed</div>
                                        </div>
                                    </div>
                                    
                                    <div class="details-grid">
                                        <div class="detail-item">
                                            <div class="detail-label">Renter</div>
                                            <div class="detail-value">
                                                <?php echo htmlspecialchars($request['renter_firstname'] . ' ' . $request['renter_lastname']); ?>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Renter Email</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($request['renter_email']); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">ISBN</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($request['ISBN']); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Return Method</div>
                                            <div class="detail-value">
                                                <span class="return-method-badge">
                                                    <i class="fas <?php echo $request['return_method'] === 'dropoff' ? 'fa-store' : 'fa-truck'; ?>"></i>
                                                    <?php echo ucfirst($request['return_method']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Rental Period</div>
                                            <div class="detail-value">
                                                <?php echo $request['rental_weeks']; ?> week<?php echo $request['rental_weeks'] > 1 ? 's' : ''; ?>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Request Date</div>
                                            <div class="detail-value">
                                                <?php echo date('M j, Y', strtotime($request['request_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($request['return_details'])): ?>
                                    <div class="return-details-panel">
                                        <div class="return-details-title">
                                            <i class="fas fa-info-circle text-primary"></i> Return Details
                                        </div>
                                        <div class="return-details-content">
                                            <?php 
                                            // Try to parse JSON
                                            $detailsJson = json_decode($request['return_details'], true);
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($detailsJson)) {
                                                echo '<div class="return-json-formatted">';
                                                foreach ($detailsJson as $key => $value) {
                                                    echo '<div><strong>' . htmlspecialchars(str_replace('_', ' ', ucfirst($key))) . ':</strong> ' . htmlspecialchars($value) . '</div>';
                                                }
                                                echo '</div>';
                                            } else {
                                                echo htmlspecialchars($request['return_details']);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="rental-actions">
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <form action="rental_request.php" method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="receive_return">
                                                <input type="hidden" name="return_id" value="<?php echo $request['return_id']; ?>">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-box-check"></i> Receive Return
                                                </button>
                                            </form>
                                            
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#viewDetailsModal<?php echo $request['return_id']; ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($request['status'] === 'received'): ?>
                                            <button class="btn btn-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#inspectReturnModal<?php echo $request['return_id']; ?>">
                                                <i class="fas fa-search"></i> Inspect Return
                                            </button>
                                            
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#viewDetailsModal<?php echo $request['return_id']; ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Inspect Return Modal -->
                        <div class="modal fade" id="inspectReturnModal<?php echo $request['return_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-search me-2"></i>Inspect Return #<?php echo $request['return_id']; ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="rental_request.php" method="POST">
                                        <input type="hidden" name="action" value="inspect_return">
                                        <input type="hidden" name="return_id" value="<?php echo $request['return_id']; ?>">
                                        <div class="modal-body">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <h6>Book Information</h6>
                                                    <p><strong>Title:</strong> <?php echo htmlspecialchars($request['book_title']); ?></p>
                                                    <p><strong>Author:</strong> <?php echo htmlspecialchars($request['book_author']); ?></p>
                                                    <p><strong>ISBN:</strong> <?php echo htmlspecialchars($request['ISBN']); ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Renter Information</h6>
                                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($request['renter_firstname'] . ' ' . $request['renter_lastname']); ?></p>
                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($request['renter_email']); ?></p>
                                                    <p><strong>Return Method:</strong> <?php echo ucfirst($request['return_method']); ?></p>
                                                </div>
                                            </div>
                                            
                                            <h6 class="mb-3">Condition Assessment</h6>
                                            <div class="condition-options">
                                                <div class="condition-option" data-condition="excellent">
                                                    <div class="condition-icon text-success">
                                                        <i class="fas fa-star"></i>
                                                    </div>
                                                    <div class="condition-text">Excellent</div>
                                                </div>
                                                <div class="condition-option active" data-condition="good">
                                                    <div class="condition-icon text-primary">
                                                        <i class="fas fa-thumbs-up"></i>
                                                    </div>
                                                    <div class="condition-text">Good</div>
                                                </div>
                                                <div class="condition-option" data-condition="fair">
                                                    <div class="condition-icon text-warning">
                                                        <i class="fas fa-meh"></i>
                                                    </div>
                                                    <div class="condition-text">Fair</div>
                                                </div>
                                                <div class="condition-option" data-condition="damaged">
                                                    <div class="condition-icon text-danger">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                    </div>
                                                    <div class="condition-text">Damaged</div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <select name="book_condition" class="form-select d-none" required id="conditionSelect<?php echo $request['return_id']; ?>">
                                                    <option value="excellent">Excellent</option>
                                                    <option value="good" selected>Good</option>
                                                    <option value="fair">Fair</option>
                                                    <option value="damaged">Damaged</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Damage Description (if applicable)</label>
                                                <textarea name="damage_description" class="form-control" rows="3" placeholder="Describe any damage or wear observed..."></textarea>
                                            </div>
                                            
                                            <div class="fee-calculator">
                                                <h6 class="mb-3">Fee Calculator</h6>
                                                
                                                <div class="row mb-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label">Damage Fee (₱)</label>
                                                        <input type="number" name="damage_fee" class="form-control" min="0" step="0.01" value="0" id="damageFee<?php echo $request['return_id']; ?>">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Late Fee (₱)</label>
                                                        <input type="number" name="late_fee" class="form-control" min="0" step="0.01" value="<?php echo $request['is_overdue'] ? $request['late_fee'] : 0; ?>" <?php echo $request['is_overdue'] ? 'readonly' : ''; ?>>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Additional Fee (₱)</label>
                                                        <input type="number" name="additional_fee" class="form-control" min="0" step="0.01" value="0">
                                                    </div>
                                                </div>
                                                
                                                <div class="fee-total">
                                                    <span>Total Fees:</span>
                                                    <span>₱ <span id="totalFees<?php echo $request['return_id']; ?>">0.00</span></span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3 mt-3">
                                                <label class="form-label">Notes</label>
                                                <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes about this return..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-check me-1"></i>Complete Return
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- View Details Modal -->
                        <div class="modal fade" id="viewDetailsModal<?php echo $request['return_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-info-circle me-2"></i>Return Request Details #<?php echo $request['return_id']; ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <!-- Return Timeline -->
                                        <div class="return-timeline">
                                            <div class="timeline-track"></div>
                                            
                                            <div class="timeline-item active">
                                                <div class="timeline-point">
                                                    <i class="fas fa-file-alt"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <div class="timeline-title">
                                                        Return Requested
                                                        <span class="timeline-date">
                                                            <?php echo date('M j, Y, g:i a', strtotime($request['request_date'])); ?>
                                                        </span>
                                                    </div>
                                                    <p class="timeline-description">
                                                        Customer initiated a return request for "<?php echo htmlspecialchars($request['book_title']); ?>".
                                                        Return method: <?php echo ucfirst($request['return_method']); ?>.
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <?php if ($request['status'] === 'received' || $request['status'] === 'completed'): ?>
                                            <div class="timeline-item active">
                                                <div class="timeline-point">
                                                    <i class="fas fa-box"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <div class="timeline-title">
                                                        Return Received
                                                        <span class="timeline-date">
                                                            <?php echo date('M j, Y, g:i a', strtotime($request['received_date'])); ?>
                                                        </span>
                                                    </div>
                                                    <p class="timeline-description">
                                                        Book was received by the seller and is pending inspection.
                                                    </p>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <div class="timeline-item">
                                                <div class="timeline-point">
                                                    <i class="fas fa-box"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <div class="timeline-title">
                                                        Awaiting Receipt
                                                    </div>
                                                    <p class="timeline-description">
                                                        Waiting for the book to be returned via <?php echo ucfirst($request['return_method']); ?>.
                                                    </p>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($request['status'] === 'completed'): ?>
                                            <div class="timeline-item active">
                                                <div class="timeline-point">
                                                    <i class="fas fa-check-circle"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <div class="timeline-title">
                                                        Return Completed
                                                        <span class="timeline-date">
                                                            <?php echo date('M j, Y, g:i a', strtotime($request['completed_date'])); ?>
                                                        </span>
                                                    </div>
                                                    <p class="timeline-description">
                                                        Book was inspected and return was completed. 
                                                        Condition: <?php echo ucfirst($request['book_condition']); ?>.
                                                        <?php if (!empty($request['damage_description'])): ?>
                                                        Damage noted: <?php echo htmlspecialchars($request['damage_description']); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <div class="timeline-item">
                                                <div class="timeline-point">
                                                    <i class="fas fa-check-circle"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <div class="timeline-title">
                                                        Pending Completion
                                                    </div>
                                                    <p class="timeline-description">
                                                        The return is pending inspection and completion.
                                                    </p>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Book and Return Details -->
                                        <div class="row mt-4">
                                            <div class="col-md-6">
                                                <h6 class="mb-3">Book Details</h6>
                                                <div class="return-details-panel">
                                                    <div class="d-flex mb-3">
                                                        <img 
                                                            src="<?php echo !empty($request['cover_image']) ? $request['cover_image'] : 'img/default-book-cover.jpg'; ?>" 
                                                            alt="<?php echo htmlspecialchars($request['book_title']); ?>" 
                                                            class="me-3"
                                                            style="width: 80px; height: 120px; object-fit: cover; border-radius: 4px;"
                                                            onerror="this.src='img/default-book-cover.jpg';"
                                                        >
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($request['book_title']); ?></h6>
                                                            <p class="text-muted mb-1">by <?php echo htmlspecialchars($request['book_author']); ?></p>
                                                            <p class="mb-1"><small><strong>ISBN:</strong> <?php echo htmlspecialchars($request['ISBN']); ?></small></p>
                                                            <p class="mb-0"><small><strong>Current Stock:</strong> <?php echo $request['stock']; ?></small></p>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <p class="mb-1"><small><strong>Rental Period:</strong> <?php echo $request['rental_weeks']; ?> week<?php echo $request['rental_weeks'] > 1 ? 's' : ''; ?></small></p>
                                                        </div>
                                                        <div class="col-6">
                                                            <p class="mb-1"><small><strong>Rental Price:</strong> ₱<?php echo number_format($request['total_price'], 2); ?></small></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <h6 class="mb-3">Return Details</h6>
                                                <div class="return-details-panel">
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <p class="mb-1"><small><strong>Return ID:</strong> #<?php echo $request['return_id']; ?></small></p>
                                                            <p class="mb-1"><small><strong>Rental ID:</strong> #<?php echo $request['rental_id']; ?></small></p>
                                                            <p class="mb-1">
                                                                <small><strong>Status:</strong> 
                                                                <span class="badge <?php echo $request['status'] === 'pending' ? 'bg-warning' : 'bg-info'; ?>">
                                                                    <?php echo ucwords(str_replace('_', ' ', $request['status'])); ?>
                                                                </span>
                                                                </small>
                                                            </p>
                                                        </div>
                                                        <div class="col-6">
                                                            <p class="mb-1"><small><strong>Method:</strong> <?php echo ucfirst($request['return_method']); ?></small></p>
                                                            <p class="mb-1"><small><strong>Request Date:</strong> <?php echo date('M j, Y', strtotime($request['request_date'])); ?></small></p>
                                                            <?php if (!empty($request['received_date'])): ?>
                                                            <p class="mb-1"><small><strong>Received Date:</strong> <?php echo date('M j, Y', strtotime($request['received_date'])); ?></small></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($request['return_details'])): ?>
                                                    <div class="mt-2">
                                                        <p class="mb-1"><small><strong>Return Details:</strong></small></p>
                                                        <div class="return-json-formatted mt-1">
                                                            <?php 
                                                            // Try to parse JSON
                                                            $detailsJson = json_decode($request['return_details'], true);
                                                            if (json_last_error() === JSON_ERROR_NONE && is_array($detailsJson)) {
                                                                foreach ($detailsJson as $key => $value) {
                                                                    echo '<div><small><strong>' . htmlspecialchars(str_replace('_', ' ', ucfirst($key))) . ':</strong> ' . htmlspecialchars($value) . '</small></div>';
                                                                }
                                                            } else {
                                                                echo '<small>' . htmlspecialchars($request['return_details']) . '</small>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($request['is_overdue'] == 1): ?>
                                        <div class="alert alert-warning mt-3">
                                            <div class="d-flex">
                                                <div class="me-3">
                                                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">Overdue Return</h6>
                                                    <p class="mb-0">This return is <?php echo $request['days_overdue']; ?> days overdue. 
                                                    Late fee of ₱<?php echo number_format($request['late_fee'], 2); ?> has been applied.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($request['status'] === 'completed'): ?>
                                        <div class="fees-summary mt-3">
                                            <h6 class="mb-3">Fee Summary</h6>
                                            <div class="fee-item">
                                                <span class="fee-label">Late Fee:</span>
                                                <span class="fee-value">₱<?php echo number_format($request['late_fee'], 2); ?></span>
                                            </div>
                                            <div class="fee-item">
                                                <span class="fee-label">Damage Fee:</span>
                                                <span class="fee-value">₱<?php echo number_format($request['damage_fee'], 2); ?></span>
                                            </div>
                                            <div class="fee-item">
                                                <span class="fee-label">Additional Fee:</span>
                                                <span class="fee-value">₱<?php echo number_format($request['additional_fee'], 2); ?></span>
                                            </div>
                                            <div class="fee-total">
                                                <span>Total:</span>
                                                <span>₱<?php echo number_format($request['late_fee'] + $request['damage_fee'] + $request['additional_fee'], 2); ?></span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                        <?php if ($request['status'] === 'pending'): ?>
                                        <form action="rental_request.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="receive_return">
                                            <input type="hidden" name="return_id" value="<?php echo $request['return_id']; ?>">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-box-check me-1"></i>Receive Return
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Export Data Modal -->
    <div class="modal fade" id="exportDataModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-download me-2"></i>Export Return Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date Range</label>
                        <select class="form-select" id="exportDateRange">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month" selected>This Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="row mb-3 d-none" id="customDateRange">
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="exportStartDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="exportEndDate">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="exportStatus">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="received">Received</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <select class="form-select" id="exportFormat">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        // Initialize toasts
        document.addEventListener('DOMContentLoaded', function() {
            // Toast close functionality
            document.querySelectorAll('.toast-close').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    this.closest('.custom-toast').remove();
                });
            });
            
            // Auto-hide toasts after 5 seconds
            setTimeout(function() {
                document.querySelectorAll('.custom-toast').forEach(function(toast) {
                    toast.remove();
                });
            }, 5000);
            
            // Tab navigation
            document.querySelectorAll('.tab-item').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    document.querySelectorAll('.tab-item').forEach(function(t) {
                        t.classList.remove('active');
                    });
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Filter rental cards
                    const filter = this.getAttribute('data-filter');
                    
                    document.querySelectorAll('.rental-card').forEach(function(card) {
                        if (filter === 'all') {
                            card.style.display = 'block';
                        } else if (filter === 'overdue') {
                            card.style.display = card.getAttribute('data-overdue') === '1' ? 'block' : 'none';
                        } else {
                            card.style.display = card.getAttribute('data-status') === filter ? 'block' : 'none';
                        }
                    });
                });
            });
            
            // Search functionality
            document.getElementById('returnSearch').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                
                document.querySelectorAll('.rental-card').forEach(function(card) {
                    const cardText = card.textContent.toLowerCase();
                    
                    if (cardText.includes(searchTerm)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
            
            // Condition option selection
            document.querySelectorAll('.condition-option').forEach(function(option) {
                option.addEventListener('click', function() {
                    const modalId = this.closest('.modal').id;
                    const returnId = modalId.replace('inspectReturnModal', '');
                    
                    // Remove active class from all options in this modal
                    this.closest('.condition-options').querySelectorAll('.condition-option').forEach(function(opt) {
                        opt.classList.remove('active');
                    });
                    
                    // Add active class to clicked option
                    this.classList.add('active');
                    
                    // Update hidden select
                    const condition = this.getAttribute('data-condition');
                    document.getElementById('conditionSelect' + returnId).value = condition;
                    
                    // Update damage fee if condition is damaged
                    if (condition === 'damaged') {
                        document.getElementById('damageFee' + returnId).value = '100.00';
                    } else if (condition === 'fair') {
                        document.getElementById('damageFee' + returnId).value = '50.00';
                    } else {
                        document.getElementById('damageFee' + returnId).value = '0.00';
                    }
                    
                    // Update total
                    updateTotalFees(returnId);
                });
            });
            
            // Fee calculator
            function updateTotalFees(returnId) {
                const damageFee = parseFloat(document.getElementById('damageFee' + returnId).value) || 0;
                const lateFeeInput = document.querySelector('#inspectReturnModal' + returnId + ' input[name="late_fee"]');
                const additionalFeeInput = document.querySelector('#inspectReturnModal' + returnId + ' input[name="additional_fee"]');
                
                const lateFee = parseFloat(lateFeeInput ? lateFeeInput.value : 0) || 0;
                const additionalFee = parseFloat(additionalFeeInput ? additionalFeeInput.value : 0) || 0;
                
                const total = damageFee + lateFee + additionalFee;
                const totalElement = document.getElementById('totalFees' + returnId);
                if (totalElement) {
                    totalElement.textContent = total.toFixed(2);
                }
            }
            
            // Add event listeners to fee inputs
            document.querySelectorAll('input[name="damage_fee"], input[name="late_fee"], input[name="additional_fee"]').forEach(function(input) {
                input.addEventListener('input', function() {
                    const modalId = this.closest('.modal').id;
                    const returnId = modalId.replace('inspectReturnModal', '');
                    updateTotalFees(returnId);
                });
            });
            
            // Custom date range toggle
            const exportDateRange = document.getElementById('exportDateRange');
            if (exportDateRange) {
                exportDateRange.addEventListener('change', function() {
                    const customRangeDiv = document.getElementById('customDateRange');
                    if (this.value === 'custom') {
                        customRangeDiv.classList.remove('d-none');
                    } else {
                        customRangeDiv.classList.add('d-none');
                    }
                });
            }
            
            // Initialize all fee calculators on page load
            document.querySelectorAll('[id^="inspectReturnModal"]').forEach(function(modal) {
                const returnId = modal.id.replace('inspectReturnModal', '');
                updateTotalFees(returnId);
            });
        });
    </script>
</body>
</html>