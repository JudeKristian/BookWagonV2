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

// At the beginning of checkout.php, add this check
// Always recalculate cart totals from the database to ensure they're current
$cartQuery = "SELECT 
    SUM(CASE 
        WHEN c.purchase_type = 'rent' 
        THEN b.rent_price * c.rental_weeks * c.quantity 
        ELSE b.price * c.quantity 
    END) as total_amount,
    COUNT(*) as item_count
FROM cart c
JOIN books b ON c.book_id = b.book_id
WHERE c.user_id = ?";
$cartTotalStmt = $conn->prepare($cartQuery);
$cartTotalStmt->bind_param("i", $userId);
$cartTotalStmt->execute();
$cartTotalResult = $cartTotalStmt->get_result();
$cartTotalData = $cartTotalResult->fetch_assoc();

$subtotal = $cartTotalData['total_amount'] ?? 0;
$itemCount = $cartTotalData['item_count'] ?? 0;
$discount = 0;

// No tax, and no shipping fee yet - will be added based on payment method
$total = $subtotal - $discount;

// Update cart_details in session with fresh data
$_SESSION['cart_details'] = [
    'subtotal' => $subtotal,
    'shipping' => 0, // Will be calculated based on payment method
    'discount' => $discount,
    'total' => $total,
    'itemCount' => $itemCount
];

// Process checkout form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate shipping address information
    $firstName = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lastName = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $postalCode = mysqli_real_escape_string($conn, $_POST['postal_code']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    $paymentMethod = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? 'cod');
    
    // Validation - simple check that required fields are filled
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($address) || empty($city) || empty($postalCode)) {
        $_SESSION['checkout_error'] = "Please fill in all required fields.";
        header("Location: checkout.php");
        exit();
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // 1. Calculate cart total BEFORE creating the order
        $cartTotalQuery = "SELECT 
        SUM(CASE 
            WHEN c.purchase_type = 'rent' 
            THEN c.rental_weeks * b.rent_price * c.quantity 
            ELSE b.price * c.quantity 
        END) as total_amount
        FROM cart c
        JOIN books b ON c.book_id = b.book_id
        WHERE c.user_id = ?";
        $cartTotalStmt = $conn->prepare($cartTotalQuery);
        $cartTotalStmt->bind_param("i", $userId);
        $cartTotalStmt->execute();
        $cartTotalResult = $cartTotalStmt->get_result();
        $cartTotalData = $cartTotalResult->fetch_assoc();
        $subtotal = $cartTotalData['total_amount'] ?? 0;
        
        // Add shipping fee only for Cash on Delivery
        $shippingFee = ($paymentMethod === 'cod') ? 60 : 0;
        $totalAmount = $subtotal + $shippingFee;
    
        // Debugging
        error_log("Payment Method: " . $paymentMethod);
        error_log("Subtotal: " . $subtotal);
        error_log("Shipping Fee: " . $shippingFee);
        error_log("Total Amount: " . $totalAmount);
        
        if ($totalAmount <= 0) {
            throw new Exception("Your cart appears to be empty or has invalid items.");
        }

        // 2. Create new order with the correct total amount
        $orderStmt = $conn->prepare("INSERT INTO orders (user_id, first_name, last_name, email, phone, address, city, postal_code, notes, payment_method, shipping_fee, order_date, total_amount) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $orderStmt->bind_param("isssssssssid", $userId, $firstName, $lastName, $email, $phone, $address, $city, $postalCode, $notes, $paymentMethod, $shippingFee, $totalAmount);
            $orderStmt->execute();
            $orderId = $conn->insert_id;
        
        // 3. Get cart items
        $cartStmt = $conn->prepare("SELECT c.*, b.user_id as seller_id, b.price, b.rent_price, 
                         c.rental_weeks as rental_weeks, c.purchase_type as purchase_type 
                         FROM cart c 
                         JOIN books b ON c.book_id = b.book_id 
                         WHERE c.user_id = ?");
        $cartStmt->bind_param("i", $userId);
        $cartStmt->execute();
        $cartResult = $cartStmt->get_result();
        
        if ($cartResult->num_rows === 0) {
            // No items in cart
            throw new Exception("Your cart is empty.");
        }
        
        // 4. Add items to order_items table
        $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, book_id, seller_id, quantity, purchase_type, rental_weeks, unit_price) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        while ($cartItem = $cartResult->fetch_assoc()) {
            $bookId = $cartItem['book_id'];
            $sellerId = $cartItem['seller_id'];
            $quantity = $cartItem['quantity'];
            $purchaseType = $cartItem['purchase_type'];
            $rentalWeeks = $cartItem['rental_weeks'] ?? 1;
            
            // Fetch book details to get accurate pricing
            $bookStmt = $conn->prepare("SELECT price, rent_price FROM books WHERE book_id = ?");
            $bookStmt->bind_param("i", $bookId);
            $bookStmt->execute();
            $bookResult = $bookStmt->get_result();
            $bookDetails = $bookResult->fetch_assoc();
            
            // Make sure purchase_type is never empty
            if (empty($purchaseType)) {
                // Determine purchase type based on price and rental weeks
                if ($rentalWeeks > 0) {
                    $purchaseType = 'rent';
                    $unitPrice = $bookDetails['rent_price'] * $rentalWeeks;
                } else {
                    // Compare unit price with book price to determine if it's a rental or purchase
                    $unitPrice = $cartItem['unit_price'] ?? 0;
                    if ($unitPrice > 0 && $unitPrice < $bookDetails['price'] * 0.9) {
                        $purchaseType = 'rent';
                    } else {
                        $purchaseType = 'buy';
                        $unitPrice = $bookDetails['price'];
                        $rentalWeeks = NULL;
                    }
                }
            } else {
                // Calculate unit price based on purchase type
                if ($purchaseType == 'rent') {
                    $unitPrice = $bookDetails['rent_price'] * $rentalWeeks;
                } else {
                    $unitPrice = $bookDetails['price'];
                    // For buy items, set rental_weeks to NULL
                    $rentalWeeks = NULL;
                }
            }
            
            // Detailed error logging
            error_log("Checkout Order Item - Book ID: $bookId, Purchase Type: $purchaseType, Rental Weeks: " . ($rentalWeeks ?? 'NULL') . ", Unit Price: $unitPrice");
            
            $itemStmt->bind_param("iiisiid", $orderId, $bookId, $sellerId, $quantity, $purchaseType, $rentalWeeks, $unitPrice);
            
            if (!$itemStmt->execute()) {
                error_log("Error inserting order item: " . $itemStmt->error);
                throw new Exception("Failed to insert order item for book ID: $bookId");
            }
        }

                // After inserting all items, calculate and update the final order total
                calculateOrderTotal($conn, $orderId);
            
            // Update book stock
            $stockStmt = $conn->prepare("UPDATE books SET stock = stock - ? WHERE book_id = ? AND stock >= ?");
            $stockStmt->bind_param("iii", $quantity, $bookId, $quantity);
            $stockStmt->execute();
            
            if ($stockStmt->affected_rows === 0) {
                // Not enough stock
                throw new Exception("Sorry, one or more items in your cart are no longer available in the requested quantity.");
            }
        
        
        // 5. Clear the user's cart
        $clearCartStmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $clearCartStmt->bind_param("i", $userId);
        $clearCartStmt->execute();
        
        // 6. Store the order total in session for payment page
        $_SESSION['order_total'] = $totalAmount;
        $_SESSION['order_id'] = $orderId;
        
        // 7. Commit transaction
        $conn->commit();
        
        // 8. Redirect to payment methods page
        header("Location: payment_methods.php?order_id=$orderId");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['checkout_error'] = $e->getMessage();
        header("Location: checkout.php");
        exit();
    }
}

// Get user information to pre-fill form
$userQuery = "SELECT * FROM users WHERE id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();

$subtotal = $_SESSION['cart_details']['subtotal'] ?? 0;
$discount = $_SESSION['cart_details']['discount'] ?? 0;
$total = $_SESSION['cart_details']['total'] ?? 0;

// Get cart total
if ($subtotal <= 0) {
    // Recalculate totals from cart items
    $cartQuery = "SELECT 
        SUM(CASE 
            WHEN c.purchase_type = 'rent' 
            THEN b.rent_price * c.rental_weeks * c.quantity 
            ELSE b.price * c.quantity 
        END) as total_amount
    FROM cart c
    JOIN books b ON c.book_id = b.book_id
    WHERE c.user_id = ?";
    $cartTotalStmt = $conn->prepare($cartQuery);
    $cartTotalStmt->bind_param("i", $userId);
    $cartTotalStmt->execute();
    $cartTotalResult = $cartTotalStmt->get_result();
    $cartTotalData = $cartTotalResult->fetch_assoc();
    
    $subtotal = $cartTotalData['total_amount'] ?? 0;
    $discount = 0;
    $total = $subtotal - $discount;
}

unset($_SESSION['cart_subtotal']);
unset($_SESSION['cart_tax']);
unset($_SESSION['cart_shipping']);
unset($_SESSION['cart_total']);

error_log("Subtotal: $subtotal");
error_log("Discount: $discount");
error_log("Total: $total");

// Redirect to cart if empty
$cartCountStmt = $conn->prepare("SELECT COUNT(*) as cart_count FROM cart WHERE user_id = ?");
$cartCountStmt->bind_param("i", $userId);
$cartCountStmt->execute();
$cartCountResult = $cartCountStmt->get_result();
$cartCount = $cartCountResult->fetch_assoc()['cart_count'];

if ($cartCount == 0) {
    $_SESSION['cart_message'] = "Your cart is empty.";
    $_SESSION['cart_message_type'] = "warning";
    header("Location: cart.php");
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
    <title>Checkout - BookWagon</title>
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
        
        .checkout-container {
            max-width: 1000px;
            margin: 40px auto;
        }
        
        .checkout-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .checkout-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 150px;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: var(--text-muted);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .step-number.active {
            background-color: var(--primary-color);
        }
        
        .step-line {
            height: 2px;
            width: 100px;
            background-color: var(--text-muted);
            margin: 15px 0;
        }
        
        .step-title {
            font-size: 0.9rem;
            text-align: center;
            color: var(--text-muted);
        }
        
        .step-title.active {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .checkout-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .checkout-section {
            padding: 25px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .checkout-section:last-child {
            border-bottom: none;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .summary-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 25px;
            position: sticky;
            top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .summary-row:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .summary-total {
            font-weight: bold;
            font-size: 1.1rem;
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
    </style>
</head>
<body>
    <!-- Include Header -->
    <?php include("include/user_header.php"); ?>

    <div class="container checkout-container">
        <div class="checkout-header">
            <h2>Checkout</h2>
        </div>
        
        <div class="checkout-steps">
            <div class="step">
                <div class="step-number active">1</div>
                <div class="step-title active">Shipping</div>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-title">Payment</div>
            </div>
            <div class="step-line"></div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-title">Confirmation</div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['checkout_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['checkout_error']; unset($_SESSION['checkout_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Checkout Form Column -->
            <div class="col-lg-8">
                <div class="checkout-card">
                    <form action="checkout.php" method="post">
                        <div class="checkout-section">
                            <h4 class="mb-4">Shipping Information</h4>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userData['firstname'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userData['lastname'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($userData['address'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="postal_code" class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($userData['postal_code'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkout-section">
                            <h4 class="mb-4">Payment Method</h4>
                            
                            <div class="mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input payment-method" type="radio" name="payment_method" id="payment_cod" value="cod" checked>
                                    <label class="form-check-label d-flex align-items-center" for="payment_cod">
                                        <i class="fas fa-truck me-2 text-secondary"></i>
                                        Cash on Delivery
                                        <span class="badge bg-warning text-dark ms-2">+ ₱60 shipping fee</span>
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input payment-method" type="radio" name="payment_method" id="payment_pickup" value="pickup">
                                    <label class="form-check-label d-flex align-items-center" for="payment_pickup">
                                        <i class="fas fa-store me-2 text-secondary"></i>
                                        Pickup / Meet-up
                                    </label>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input payment-method" type="radio" name="payment_method" id="payment_bank" value="bank">
                                    <label class="form-check-label d-flex align-items-center" for="payment_bank">
                                        <i class="fas fa-university me-2 text-secondary"></i>
                                        Bank Transfer
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkout-section">
                            <h4 class="mb-4">Additional Information</h4>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Order Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Notes about your order, e.g. special delivery instructions"></textarea>
                            </div>
                        </div>
                        
                        <div class="checkout-section">
                            <button type="submit" class="continue-btn">
                                <i class="fas fa-credit-card me-2"></i>Proceed to Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Order Summary Column -->
            <div class="col-lg-4">
                <div class="summary-card">
                    <h4 class="mb-4">Order Summary</h4>


                    <div class="summary-row">
                        <span>Items (<?php echo $_SESSION['cart_details']['itemCount'] ?? 0; ?>)</span>
                        <span>₱<?php echo number_format($_SESSION['cart_details']['subtotal'] ?? 0, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Discount</span>
                        <span>₱<?php echo number_format($_SESSION['cart_details']['discount'] ?? 0, 2); ?></span>
                    </div>
                    
                    <div class="summary-row shipping-row" style="display: flex;">
                        <span>Shipping fee (COD)</span>
                        <span>₱60.00</span>
                    </div>
                    
                    <div class="summary-row summary-total">
                        <span>Total</span>
                        <span id="final-total">₱<?php echo number_format(($_SESSION['cart_details']['total'] ?? 0) + 60, 2); ?></span>
                    </div>
                    <div class="mt-4">
                        <h5>Payment Method Selected:</h5>
                        <div id="selected-payment-method" class="alert alert-info mt-2">
                            <i class="fas fa-truck me-2"></i> Cash on Delivery
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get elements
            const paymentMethods = document.querySelectorAll('.payment-method');
            const shippingRow = document.querySelector('.shipping-row');
            const finalTotal = document.getElementById('final-total');
            const selectedPaymentMethod = document.getElementById('selected-payment-method');
            
            // Base total without shipping
            const baseTotal = <?php echo $_SESSION['cart_details']['total'] ?? 0; ?>;
            
            // Function to update totals based on payment method
            function updateTotals() {
                const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
                
                let newTotal = baseTotal;
                let methodDisplay = '';
                
                if (selectedMethod === 'cod') {
                    shippingRow.style.display = 'flex';
                    newTotal += 60; // Add shipping fee for COD
                    methodDisplay = '<i class="fas fa-truck me-2"></i> Cash on Delivery';
                } else {
                    shippingRow.style.display = 'none';
                    
                    if (selectedMethod === 'pickup') {
                        methodDisplay = '<i class="fas fa-store me-2"></i> Pickup / Meet-up';
                    } else if (selectedMethod === 'bank') {
                        methodDisplay = '<i class="fas fa-university me-2"></i> Bank Transfer';
                    }
                }
                
                // Update the total display
                finalTotal.textContent = '₱' + newTotal.toFixed(2);
                
                // Update selected method display
                selectedPaymentMethod.innerHTML = methodDisplay;
            }
            
            // Add event listeners to payment method radios
            paymentMethods.forEach(method => {
                method.addEventListener('change', updateTotals);
            });
            
            // Initialize totals on page load
            updateTotals();
        });
    </script>
</body>
</html>