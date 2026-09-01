<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? '';
$userId = $_SESSION['id'] ?? 0;

// Redirect if not logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Get order ID from URL
$orderId = $_GET['order_id'] ?? 0;

// Fetch order details
$orderQuery = "SELECT o.*, 
               COALESCE(o.total_amount, 
                   (SELECT SUM(oi.quantity * oi.unit_price) 
                    FROM order_items oi 
                    WHERE oi.order_id = o.order_id)
               ) AS total_amount,
               (SELECT GROUP_CONCAT(DISTINCT CONCAT(u2.firstname, ' ', u2.lastname) SEPARATOR ', ') 
                FROM order_items oi2 
                JOIN books b2 ON oi2.book_id = b2.book_id 
                JOIN users u2 ON b2.user_id = u2.id 
                WHERE oi2.order_id = o.order_id) AS sellers
               FROM orders o
               WHERE o.order_id = ? AND o.user_id = ?
               GROUP BY o.order_id";
               
$orderStmt = $conn->prepare($orderQuery);
$orderStmt->bind_param("ii", $orderId, $userId);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();

if ($orderResult->num_rows === 0) {
    // Order not found or doesn't belong to this user
    header("Location: cart.php");
    exit();
}

$order = $orderResult->fetch_assoc();

// If total amount is still 0, try to update it from order items
if ($order['total_amount'] <= 0) {
    $recalculateQuery = "
    SELECT SUM(oi.unit_price * oi.quantity) AS total_amount
    FROM order_items oi
    WHERE oi.order_id = ?
    ";
    $recalculateStmt = $conn->prepare($recalculateQuery);
    $recalculateStmt->bind_param("i", $orderId);
    $recalculateStmt->execute();
    $recalculateResult = $recalculateStmt->get_result();
    $recalculatedData = $recalculateResult->fetch_assoc();
    $recalculatedTotal = $recalculatedData['total_amount'] ?? 0;
    
    if ($recalculatedTotal > 0) {
        // Update the order with the correct total
        $updateTotalStmt = $conn->prepare("UPDATE orders SET total_amount = ? WHERE order_id = ?");
        $updateTotalStmt->bind_param("di", $recalculatedTotal, $orderId);
        $updateTotalStmt->execute();
        
        $order['total_amount'] = $recalculatedTotal;
    }
}

// Fetch order items
$itemsQuery = "SELECT oi.*, oi.unit_price AS item_unit_price, b.title, b.author, b.cover_image, b.price, b.rent_price
               FROM order_items oi
               JOIN books b ON oi.book_id = b.book_id
               WHERE oi.order_id = ?";
$itemsStmt = $conn->prepare($itemsQuery);
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$orderItems = $itemsResult->fetch_all(MYSQLI_ASSOC);

// Determine order status text and next steps based on payment method
$statusText = "";
$nextStepsHtml = "";

switch($order['payment_method']) {
    case 'cod':
        $statusText = "Pending";
        $statusIcon = "fa-clock";
        $statusClass = "text-warning";
        $nextStepsHtml = '
            <div class="next-step">
                <div class="step-icon bg-warning text-white">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="step-content">
                    <h5>Prepare for Delivery</h5>
                    <p>Your order will be delivered to your address. Please prepare the exact amount for payment upon delivery.</p>
                </div>
            </div>
        ';
        break;
        
    case 'pickup':
        $statusText = "Ready for Pickup";
        $statusIcon = "fa-store";
        $statusClass = "text-info";
        $nextStepsHtml = '
            <div class="next-step">
                <div class="step-icon bg-info text-white">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="step-content">
                    <h5>Visit the Pickup Location</h5>
                    <p>Please visit the selected location on your chosen date and bring the exact amount for payment.</p>
                    <div class="pickup-details">
                        <div class="pickup-detail-row">
                            <div class="pickup-label">Location:</div>
                            <div class="pickup-value">' . htmlspecialchars($order['pickup_location'] ?? 'To be confirmed') . '</div>
                        </div>
                        <div class="pickup-detail-row">
                            <div class="pickup-label">Date:</div>
                            <div class="pickup-value">' . date('F j, Y', strtotime($order['pickup_date'] ?? 'now')) . '</div>
                        </div>
                    </div>
                </div>
            </div>
        ';
        break;
        
    case 'bank_transfer':
        if ($order['payment_status'] == 'awaiting_payment') {
            $statusText = "Awaiting Payment";
            $statusIcon = "fa-university";
            $statusClass = "text-warning";
            $nextStepsHtml = '
                <div class="next-step">
                    <div class="step-icon bg-warning text-white">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="step-content">
                        <h5>Complete Your Bank Transfer</h5>
                        <p>Please complete your bank transfer and upload the receipt to process your order.</p>
                        <a href="bank_transfer_instructions.php?order_id=' . $orderId . '" class="btn btn-primary mt-2">
                            <i class="fas fa-credit-card me-2"></i>Go to Payment Instructions
                        </a>
                    </div>
                </div>
            ';
        } elseif ($order['payment_status'] == 'verification_pending') {
            $statusText = "Payment Verification";
            $statusIcon = "fa-check-circle";
            $statusClass = "text-info";
            $nextStepsHtml = '
                <div class="next-step">
                    <div class="step-icon bg-info text-white">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="step-content">
                        <h5>Payment Being Verified</h5>
                        <p>Your payment is being verified. This typically takes 1-2 business days. We\'ll notify you once verification is complete.</p>
                    </div>
                </div>
            ';
        }
        break;
        
    default:
        $statusText = "Processing";
        $statusIcon = "fa-spinner fa-spin";
        $statusClass = "text-primary";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - BookWagon</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #f8a100;
            --secondary-color: #f8f9fa;
            --text-dark: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            color: var(--text-dark);
            background-color: #f4f6f9;
        }
        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .navbar-brand img {
            height: 60px;
        }
        
        .confirmation-container {
            max-width: 800px;
            margin: 40px auto;
        }
        
        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #28a745;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
        }
        
        .confirmation-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .confirmation-section {
            padding: 25px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .confirmation-section:last-child {
            border-bottom: none;
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .order-info-item {
            margin-bottom: 0;
        }
        
        .order-info-label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .order-info-value {
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .status-icon {
            margin-right: 8px;
        }
        
        .order-items {
            margin-top: 20px;
        }
        
        .order-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-image {
            width: 70px;
            height: 100px;
            object-fit: cover;
            margin-right: 15px;
            border-radius: 5px;
        }
        
        .order-item-details {
            flex-grow: 1;
        }
        
        .order-item-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .order-item-author {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 5px;
        }
        
        .order-item-type {
            font-size: 0.8rem;
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            background-color: #e9ecef;
            margin-right: 10px;
        }
        
        .order-item-price {
            font-weight: 600;
            text-align: right;
        }
        
        .order-item-quantity {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-align: right;
        }
        
        .order-summary {
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .summary-row:last-child {
            margin-bottom: 0;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
            font-weight: bold;
        }
        
        .next-steps {
            margin-top: 20px;
        }
        
        .next-step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .step-content {
            flex-grow: 1;
        }
        
        .pickup-details {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .pickup-detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .pickup-detail-row:last-child {
            margin-bottom: 0;
        }
        
        .pickup-label {
            width: 80px;
            font-weight: 600;
        }
        
        .pickup-value {
            flex: 1;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .action-btn {
            padding: 12px 20px;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .primary-btn {
            background-color: var(--primary-color);
            color: white;
        }
        
        .primary-btn:hover {
            background-color: #e69400;
            color: white;
            transform: translateY(-2px);
        }
        
        .secondary-btn {
            background-color: var(--secondary-color);
            color: var(--text-dark);
        }
        
        .secondary-btn:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Include Header -->
    <?php include("include/user_header.php"); ?>

    <div class="container confirmation-container">
        <?php if (isset($_SESSION['upload_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['upload_success']; unset($_SESSION['upload_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['payment_success']) || isset($_SESSION['order_completed'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php if (isset($_SESSION['order_completed'])): ?>
                <strong>Order Completed!</strong> Your order has been processed successfully.
                <?php unset($_SESSION['order_completed']); ?>
            <?php else: ?>
                Your order has been placed successfully!
                <?php unset($_SESSION['payment_success']); ?>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="confirmation-header">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2>Thank You for Your Order!</h2>
            <p>Your order has been received and is being processed.</p>
        </div>
        
        <div class="confirmation-card">
            <div class="confirmation-section">
                <h4 class="mb-4">Order Information</h4>
                
                <div class="order-info">
                    <div class="order-info-item">
                        <span class="order-info-label">Order Number</span>
                        <span class="order-info-value">#<?php echo $orderId; ?></span>
                    </div>
                    
                    <div class="order-info-item">
                        <span class="order-info-label">Order Date</span>
                        <span class="order-info-value"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></span>
                    </div>
                    
                    <div class="order-info-item">
                        <span class="order-info-label">Payment Method</span>
                        <span class="order-info-value">
                            <?php
                            switch($order['payment_method']) {
                                case 'cod':
                                    echo 'Cash on Delivery';
                                    break;
                                case 'pickup':
                                    echo 'Pickup/Meet-up';
                                    break;
                                case 'bank_transfer':
                                    echo 'Bank Transfer';
                                    break;
                                default:
                                    echo ucfirst($order['payment_method'] ?? 'Not specified');
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="order-info-item">
                        <span class="order-info-label">Seller(s)</span>
                        <span class="order-info-value"><?php echo htmlspecialchars($order['sellers'] ?? 'Various sellers'); ?></span>
                    </div>
                </div>
                
                <div class="status-badge <?php echo $statusClass; ?> bg-light">
                    <i class="fas <?php echo $statusIcon; ?> status-icon"></i>
                    <?php echo $statusText; ?>
                </div>
            </div>
            
            <div class="confirmation-section">
                <h4 class="mb-4">Order Items</h4>
                
                <div class="order-items">
                    <?php foreach ($orderItems as $item): ?>
                        <?php 
                        // Set default purchase type based on price comparison if empty
                        if (empty($item['purchase_type'])) {
                            $item['purchase_type'] = ($item['item_unit_price'] < $item['price']) ? 'rent' : 'buy';
                        }
                        ?>
                        <div class="order-item">
                    <img src="<?php echo $item['cover_image']; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="order-item-image">
                    
                    <div class="order-item-details">
                        <div class="order-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="order-item-author">by <?php echo htmlspecialchars($item['author']); ?></div>
                        
                        <span class="order-item-type">
                            <?php if ($item['purchase_type'] == 'rent'): ?>
                                Rented for <?php echo $item['rental_weeks']; ?> week<?php echo $item['rental_weeks'] > 1 ? 's' : ''; ?>
                            <?php else: ?>
                                Purchased
                            <?php endif; ?>
                        </span>
                    </div>
                        
                    <div class="ms-auto">
                        <div class="order-item-price">
                            ₱<?php echo number_format($item['item_unit_price'], 2); ?>
                        </div>
                        <div class="order-item-quantity">Qty: <?php echo $item['quantity']; ?></div>
                    </div>
                </div>
                    <?php endforeach; ?>
                    
                    <div class="order-summary">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Total</span>
                            <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="confirmation-section">
                <h4 class="mb-4">Next Steps</h4>
                
                <div class="next-steps">
                    <?php echo $nextStepsHtml; ?>
                    
                    <div class="next-step">
                        <div class="step-icon bg-primary text-white">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="step-content">
                            <h5>Check Your Email</h5>
                            <p>We've sent a confirmation email to your registered email address with all the details of your order.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="order_history.php" class="action-btn secondary-btn">
                <i class="fas fa-list me-2"></i>View My Orders
            </a>
            <a href="rentbooks.php" class="action-btn primary-btn">
                <i class="fas fa-book me-2"></i>Continue Shopping
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>