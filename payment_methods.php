<?php
include("session.php");
include("connect.php");
include_once('order_utils.php');

$userType = $_SESSION['usertype'] ?? '';
$userId = $_SESSION['id'] ?? 0;

// Redirect if not logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Fetch the order ID from the URL or session
$orderId = $_GET['order_id'] ?? ($_SESSION['order_id'] ?? 0);

// Get selected payment method from URL or order data
$selectedPaymentMethod = $_GET['payment_method'] ?? '';

// Fetch basic order details
$orderQuery = "SELECT * FROM orders WHERE order_id = ? AND user_id = ?";
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

// If payment method wasn't in URL, get it from the order
if (empty($selectedPaymentMethod) && isset($order['payment_method'])) {
    $selectedPaymentMethod = $order['payment_method'];
}

// If still no payment method, redirect back to checkout
if (empty($selectedPaymentMethod)) {
    $_SESSION['checkout_error'] = "Please select a payment method.";
    header("Location: checkout.php");
    exit();
}

if ($order['total_amount'] <= 0) {
    // If the total amount is not set or zero, recalculate it
    $orderTotal = calculateOrderTotal($conn, $orderId);
} else {
    $orderTotal = $order['total_amount'];
}
error_log("Order Total in payment_methods.php: " . $orderTotal);

// Calculate subtotal and shipping fee
$subtotal = $order['total_amount'];
$shippingFee = $selectedPaymentMethod === 'cod' ? 60 : 0;
$total = $subtotal + $shippingFee;

// Fetch order items
$itemsQuery = "SELECT oi.*, b.title, b.author 
              FROM order_items oi 
              JOIN books b ON oi.book_id = b.book_id 
              WHERE oi.order_id = ?";
$itemsStmt = $conn->prepare($itemsQuery);
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$orderItems = $itemsResult->fetch_all(MYSQLI_ASSOC);

// Fetch available pickup locations from sellers table
$pickupLocationsQuery = "SELECT DISTINCT s.shop_name, s.address, s.location as city
                         FROM sellers s 
                         JOIN users u ON s.user_id = u.id
                         JOIN books b ON u.id = b.user_id
                         JOIN order_items oi ON b.book_id = oi.book_id
                         WHERE oi.order_id = ? AND s.status = 'approved'";
$pickupStmt = $conn->prepare($pickupLocationsQuery);
$pickupStmt->bind_param("i", $orderId);
$pickupStmt->execute();
$pickupResult = $pickupStmt->get_result();
$pickupLocations = $pickupResult->fetch_all(MYSQLI_ASSOC);

// Handle form submission for confirming payment details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Different handling based on the payment method
    switch ($selectedPaymentMethod) {
        case 'cod':
            // Update order payment status
            $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'pending' WHERE order_id = ? AND user_id = ?");
            $updateStmt->bind_param("ii", $orderId, $userId);
            $updateStmt->execute();
            
            // Update order_items statuses
            $updateItemsStmt = $conn->prepare("UPDATE order_items SET status = 'processing' WHERE order_id = ?");
            $updateItemsStmt->bind_param("i", $orderId);
            $updateItemsStmt->execute();
            break;
            
        case 'pickup':
            // Save pickup details
            $pickupLocation = $_POST['pickup_location'] ?? '';
            $pickupDate = $_POST['pickup_date'] ?? '';
            
            // Validate pickup details
            if (empty($pickupLocation) || empty($pickupDate)) {
                $_SESSION['payment_error'] = "Please provide pickup location and date.";
                header("Location: payment_methods.php?order_id=$orderId&payment_method=$selectedPaymentMethod");
                exit();
            }
            
            // Update the order with pickup details
            $updateStmt = $conn->prepare("UPDATE orders SET pickup_location = ?, pickup_date = ?, payment_status = 'pending' WHERE order_id = ? AND user_id = ?");
            $updateStmt->bind_param("ssii", $pickupLocation, $pickupDate, $orderId, $userId);
            $updateStmt->execute();
            
            // Update order_items statuses
            $updateItemsStmt = $conn->prepare("UPDATE order_items SET status = 'processing' WHERE order_id = ?");
            $updateItemsStmt->bind_param("i", $orderId);
            $updateItemsStmt->execute();
            break;
            
        case 'bank_transfer':
            // Update order payment status
            $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'awaiting_payment' WHERE order_id = ? AND user_id = ?");
            $updateStmt->bind_param("ii", $orderId, $userId);
            $updateStmt->execute();
            break;
    }
    
    // Fix any empty purchase_type values in order_items
    $fixPurchaseTypeStmt = $conn->prepare("
        UPDATE order_items oi 
        JOIN books b ON oi.book_id = b.book_id
        SET oi.purchase_type = 
            CASE 
                WHEN oi.rental_weeks > 0 THEN 'rent'
                WHEN oi.unit_price < (b.price * 0.9) THEN 'rent'
                ELSE 'buy' 
            END
        WHERE oi.order_id = ? AND (oi.purchase_type = '' OR oi.purchase_type IS NULL)
    ");
    $fixPurchaseTypeStmt->bind_param("i", $orderId);
    $fixPurchaseTypeStmt->execute();
    
    // Log the order completion
    $logStmt = $conn->prepare("
        INSERT INTO payment_logs (order_id, user_id, action, status, amount, details)
        VALUES (?, ?, 'order_completion', 'success', ?, ?)
    ");
    $details = "Order completed with payment method: " . $selectedPaymentMethod;
    $logStmt->bind_param("iids", $orderId, $userId, $total, $details);
    $logStmt->execute();
    
    // Redirect to order confirmation
    $_SESSION['payment_success'] = true;
    $_SESSION['order_completed'] = true;
    header("Location: order_confirmation.php?order_id=$orderId");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Payment Details - BookWagon</title>
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
        
        .payment-container {
            max-width: 800px;
            margin: 40px auto;
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .payment-methods {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 20px;
        }
        
        .order-summary {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .order-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .order-summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
        }
        
        .continue-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.2s;
            width: 100%;
            margin-top: 20px;
        }
        
        .continue-btn:hover {
            background-color: #e69400;
            transform: translateY(-2px);
        }
        
        .bank-details {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .bank-account-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .bank-account-label {
            width: 150px;
            font-weight: 600;
        }
        
        .bank-account-value {
            flex: 1;
        }
        
        .payment-icon {
            font-size: 24px;
            margin-right: 15px;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Include Header -->
    <?php include("include/user_header.php"); ?>

    <div class="container payment-container">
        <div class="payment-header">
            <h2>Payment Details</h2>
            <p>Order #<?php echo $orderId; ?></p>
        </div>
        
        <?php if (isset($_SESSION['payment_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['payment_error']; unset($_SESSION['payment_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="order-summary">
            <h4 class="mb-3">Order Summary</h4>
            <div class="order-summary-row">
                <span>Subtotal</span>
                <span>₱<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <?php if ($shippingFee > 0): ?>
            <div class="order-summary-row">
                <span>Shipping Fee</span>
                <span>₱<?php echo number_format($shippingFee, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="order-summary-row">
                <span>Total</span>
                <span>₱<?php echo number_format($total, 2); ?></span>
            </div>
        </div>
        
        <div class="payment-methods">
            <?php if ($selectedPaymentMethod == 'cod'): ?>
            <!-- Cash on Delivery Payment Details -->
            <div class="d-flex align-items-center mb-4">
                <i class="fas fa-money-bill-wave payment-icon"></i>
                <div>
                    <h5 class="mb-1">Cash on Delivery</h5>
                    <p class="text-muted mb-0">Pay when you receive your books</p>
                </div>
            </div>
            
            <form action="payment_methods.php?order_id=<?php echo $orderId; ?>&payment_method=<?php echo $selectedPaymentMethod; ?>" method="post" class="payment-form">
                <p>Your order will be delivered to your address. You'll pay the delivery person when you receive your items.</p>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirm-cod" required>
                    <label class="form-check-label" for="confirm-cod">
                        I confirm that I will pay ₱<?php echo number_format($total, 2); ?> upon delivery
                    </label>
                </div>
                
                <button type="submit" class="continue-btn submit-btn">
                    <span class="btn-text">Complete Order</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
            </form>
            
            <?php elseif ($selectedPaymentMethod == 'pickup'): ?>
            <!-- Pickup / Meet-up Payment Details -->
            <div class="d-flex align-items-center mb-4">
                <i class="fas fa-store payment-icon"></i>
                <div>
                    <h5 class="mb-1">Pickup / Meet-up</h5>
                    <p class="text-muted mb-0">Meet the seller and pay in person</p>
                </div>
            </div>
            
            <form action="payment_methods.php?order_id=<?php echo $orderId; ?>&payment_method=<?php echo $selectedPaymentMethod; ?>" method="post" class="payment-form">
                <div class="mb-3">
                    <label for="pickup-location" class="form-label">Pickup Location</label>
                    <select class="form-select" id="pickup-location" name="pickup_location" required>
                        <option value="">Select a pickup location</option>
                        <?php foreach ($pickupLocations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location['address'] . ', ' . $location['city']); ?>">
                            <?php echo htmlspecialchars($location['shop_name'] . ' - ' . $location['address'] . ', ' . $location['city']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="pickup-date" class="form-label">Preferred Pickup Date</label>
                    <input type="date" class="form-control" id="pickup-date" name="pickup_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirm-pickup" required>
                    <label class="form-check-label" for="confirm-pickup">
                        I confirm that I will pay ₱<?php echo number_format($total, 2); ?> at pickup
                    </label>
                </div>
                
                <button type="submit" class="continue-btn submit-btn">
                    <span class="btn-text">Complete Order</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
            </form>
            
            <?php elseif ($selectedPaymentMethod == 'bank_transfer'): ?>
            <!-- Bank Transfer Payment Details -->
            <div class="d-flex align-items-center mb-4">
                <i class="fas fa-university payment-icon"></i>
                <div>
                    <h5 class="mb-1">Bank Transfer</h5>
                    <p class="text-muted mb-0">Pay via bank transfer</p>
                </div>
            </div>
            
            <form action="payment_methods.php?order_id=<?php echo $orderId; ?>&payment_method=<?php echo $selectedPaymentMethod; ?>" method="post" class="payment-form">
                <p>Please transfer the total amount to the following bank account:</p>
                
                <div class="bank-details">
                    <div class="bank-account-row">
                        <div class="bank-account-label">Bank Name:</div>
                        <div class="bank-account-value">BookWagon Bank</div>
                    </div>
                    <div class="bank-account-row">
                        <div class="bank-account-label">Account Name:</div>
                        <div class="bank-account-value">BookWagon, Inc.</div>
                    </div>
                    <div class="bank-account-row">
                        <div class="bank-account-label">Account Number:</div>
                        <div class="bank-account-value">1234-5678-9012-3456</div>
                    </div>
                    <div class="bank-account-row">
                        <div class="bank-account-label">Reference:</div>
                        <div class="bank-account-value">Order #<?php echo $orderId; ?></div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    After completing your bank transfer, you'll need to upload a receipt of the transaction.
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirm-transfer" required>
                    <label class="form-check-label" for="confirm-transfer">
                        I understand that my order will be processed after payment verification
                    </label>
                </div>
                
                <button type="submit" class="continue-btn submit-btn">
                    <span class="btn-text">Complete Order</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date for pickup to tomorrow
            const pickupDateInput = document.getElementById('pickup-date');
            if (pickupDateInput) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                pickupDateInput.min = tomorrow.toISOString().split('T')[0];
            }
            
            // Add form submission handling with loading animation
            const paymentForms = document.querySelectorAll('.payment-form');
            
            paymentForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Get the submit button in this form
                    const submitBtn = this.querySelector('.submit-btn');
                    const btnText = submitBtn.querySelector('.btn-text');
                    const spinner = submitBtn.querySelector('.spinner-border');
                    
                    // Show loading state
                    submitBtn.disabled = true;
                    btnText.textContent = 'Processing...';
                    spinner.classList.remove('d-none');
                    
                    // Add overlay to prevent further interaction
                    const overlay = document.createElement('div');
                    overlay.style.position = 'fixed';
                    overlay.style.top = '0';
                    overlay.style.left = '0';
                    overlay.style.width = '100%';
                    overlay.style.height = '100%';
                    overlay.style.backgroundColor = 'rgba(255, 255, 255, 0.7)';
                    overlay.style.display = 'flex';
                    overlay.style.justifyContent = 'center';
                    overlay.style.alignItems = 'center';
                    overlay.style.zIndex = '9999';
                    
                    const loadingMessage = document.createElement('div');
                    loadingMessage.innerHTML = `<div class="text-center">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h5 class="mt-3">Processing your order...</h5>
                        <p>Please do not close this page.</p>
                    </div>`;
                    
                    overlay.appendChild(loadingMessage);
                    document.body.appendChild(overlay);
                    
                    // Submit the form
                    return true;
                });
            });
        });
    </script>
</body>
</html>