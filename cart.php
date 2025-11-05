<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? '';
$userId = $_SESSION['id'] ?? 0;

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Handle add to cart if coming from a book page
if (isset($_GET['book_id']) && isset($_GET['action']) && $_GET['action'] == 'add') {
    $bookId = $_GET['book_id'];
    $purchaseType = $_GET['purchase_type'] ?? 'buy'; // Default to buy
    $rentalWeeks = $_GET['rental_weeks'] ?? 1; // Default to 1 week
    
    error_log("Cart Add - Book ID: $bookId, Purchase Type: $purchaseType, Rental Weeks: $rentalWeeks");
    // First check if book exists and has stock available
    $bookStmt = $conn->prepare("SELECT stock FROM books WHERE book_id = ?");
    $bookStmt->bind_param("i", $bookId);
    $bookStmt->execute();
    $bookResult = $bookStmt->get_result();
    
    if ($bookResult->num_rows > 0) {
        $bookData = $bookResult->fetch_assoc();
        $availableStock = $bookData['stock'];
        
        // Check if book already in cart
        $checkStmt = $conn->prepare("SELECT * FROM cart WHERE user_id = ? AND book_id = ? AND purchase_type = ?");
        $checkStmt->bind_param("iis", $userId, $bookId, $purchaseType);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // Book already in cart, update quantity and rental weeks if there's enough stock
            $cartItem = $checkResult->fetch_assoc();
            $newQuantity = $cartItem['quantity'] + 1;
            
            // Check if the new quantity exceeds available stock
            if ($newQuantity <= $availableStock) {
                $updateStmt = $conn->prepare("UPDATE cart SET quantity = ?, rental_weeks = ? WHERE cart_id = ?");
                $updateStmt->bind_param("iii", $newQuantity, $rentalWeeks, $cartItem['cart_id']);
                $updateStmt->execute();
                
                // Success message
                $_SESSION['cart_message'] = "Item added to cart successfully!";
                $_SESSION['cart_message_type'] = "success";
            } else {
                // Not enough stock
                $_SESSION['cart_message'] = "Sorry, only {$availableStock} item(s) available in stock.";
                $_SESSION['cart_message_type'] = "warning";
            }
        } else {
            // Add new item to cart if stock is available
            if ($availableStock > 0) {
                $insertStmt = $conn->prepare("INSERT INTO cart (user_id, book_id, quantity, purchase_type, rental_weeks) VALUES (?, ?, 1, ?, ?)");
                $insertStmt->bind_param("iisi", $userId, $bookId, $purchaseType, $rentalWeeks);
                $insertStmt->execute();
                
                // Success message
                $_SESSION['cart_message'] = "Item added to cart successfully!";
                $_SESSION['cart_message_type'] = "success";
            } else {
                // Out of stock
                $_SESSION['cart_message'] = "Sorry, this item is out of stock.";
                $_SESSION['cart_message_type'] = "danger";
            }
        }
    } else {
        // Book not found
        $_SESSION['cart_message'] = "Book not found.";
        $_SESSION['cart_message_type'] = "danger";
    }
    
    // Redirect to cart
    header("Location: cart.php");
    exit();
}

// Handle remove from cart
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['cart_id'])) {
    $cartId = $_GET['cart_id'];
    
    $deleteStmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
    $deleteStmt->bind_param("ii", $cartId, $userId);
    $deleteStmt->execute();
    
    header("Location: cart.php");
    exit();
}

// Handle update cart item
if (isset($_POST['update_item']) && isset($_POST['cart_id'])) {
    $cartId = $_POST['cart_id'];
    $quantity = $_POST['quantity'];
    $purchaseType = $_POST['purchase_type'];
    $rentalWeeks = $purchaseType == 'rent' ? $_POST['rental_weeks'] : NULL;
    
    // Debug logging
    error_log("Updating cart item: ID=$cartId, Quantity=$quantity, Type=$purchaseType, Weeks=" . ($rentalWeeks ?? 'NULL'));
    
    // Get book_id from cart item
    $getCartStmt = $conn->prepare("SELECT book_id FROM cart WHERE cart_id = ? AND user_id = ?");
    $getCartStmt->bind_param("ii", $cartId, $userId);
    $getCartStmt->execute();
    $cartResult = $getCartStmt->get_result();
    
    if ($cartResult->num_rows > 0) {
        $cartData = $cartResult->fetch_assoc();
        $bookId = $cartData['book_id'];
        
        // Check available stock
        $stockStmt = $conn->prepare("SELECT stock FROM books WHERE book_id = ?");
        $stockStmt->bind_param("i", $bookId);
        $stockStmt->execute();
        $stockResult = $stockStmt->get_result();
        $stockData = $stockResult->fetch_assoc();
        $availableStock = $stockData['stock'];
        
        // Validate quantity against stock
        if ($quantity <= $availableStock) {
            $updateStmt = $conn->prepare("UPDATE cart SET quantity = ?, purchase_type = ?, rental_weeks = ? WHERE cart_id = ? AND user_id = ?");
            $updateStmt->bind_param("isiii", $quantity, $purchaseType, $rentalWeeks, $cartId, $userId);
            
            if ($updateStmt->execute()) {
                $_SESSION['cart_message'] = "Cart updated successfully.";
                $_SESSION['cart_message_type'] = "success";
                
                // Debug logging of success
                error_log("Successfully updated cart: cart_id=$cartId, rental_weeks=$rentalWeeks");
            } else {
                // Log error if update fails
                error_log("Error updating cart: " . $updateStmt->error);
                $_SESSION['cart_message'] = "Error updating cart. Please try again.";
                $_SESSION['cart_message_type'] = "danger";
            }
        } else {
            $_SESSION['cart_message'] = "Sorry, only {$availableStock} item(s) available in stock.";
            $_SESSION['cart_message_type'] = "warning";
        }
    }
    
    // Force reset of any cached cart data
    unset($_SESSION['cart_subtotal']);
    unset($_SESSION['cart_tax']);
    unset($_SESSION['cart_shipping']);
    unset($_SESSION['cart_total']);
    
    header("Location: cart.php");
    exit();
}

// After all the cart update/remove/add operations, fetch the latest cart data
// Fetch user's cart items
$cartStmt = $conn->prepare("SELECT c.*, b.title, b.author, b.cover_image, b.price, b.rent_price, b.stock 
                           FROM cart c 
                           JOIN books b ON c.book_id = b.book_id 
                           WHERE c.user_id = ? 
                           ORDER BY c.created_at DESC");
$cartStmt->bind_param("i", $userId);
$cartStmt->execute();
$cartResult = $cartStmt->get_result();
$cartItems = $cartResult->fetch_all(MYSQLI_ASSOC);

// Debug log cart items
if (!empty($cartItems)) {
    foreach ($cartItems as $item) {
        error_log("Cart Item: ID={$item['cart_id']}, Book={$item['title']}, Type={$item['purchase_type']}, Weeks={$item['rental_weeks']}");
    }
}

// Calculate cart totals
$subtotal = 0;
$shipping = 0;
$discount = 0;

foreach ($cartItems as $item) {
    if ($item['purchase_type'] == 'buy') {
        $subtotal += $item['price'] * $item['quantity'];
    } else {
        $subtotal += $item['rent_price'] * $item['rental_weeks'] * $item['quantity'];
    }
}

// Calculate total (without tax and shipping for now)
// Shipping fee will be added only for Cash on Delivery at checkout
$total = $subtotal - $discount;

// Calculate amount needed for free shipping
$freeShippingThreshold = 50;
$amountForFreeShipping = max(0, $freeShippingThreshold - $subtotal);

// Store in session for cart page display
$_SESSION['cart_subtotal'] = $subtotal;
$_SESSION['cart_shipping'] = 0; // Will be determined at checkout based on payment method
$_SESSION['cart_total'] = $total;

// Store cart details in session for checkout page
$_SESSION['cart_details'] = [
    'subtotal' => $subtotal,
    'shipping' => 0, // Will be determined at checkout based on payment method
    'discount' => $discount,
    'total' => $total,
    'itemCount' => count($cartItems),
    'freeShippingThreshold' => $freeShippingThreshold,
    'amountForFreeShipping' => $amountForFreeShipping
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Shopping Cart - BookWagon</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
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
            background-color: #fff;
        }

        .dropdown-item {
            padding: 0.75rem 1.5rem;
            transition: background-color 0.2s;
        }

        .dropdown-item:hover {
            background-color: rgba(0,0,0,0.05);
        }

        .dropdown-item:active {
            background-color: rgba(0,0,0,0.1);
        }

        /* Fix dropdown toggle arrow */
        .dropdown-toggle::after {
            margin-left: 0.5em;
        }
        
        /* Header styles */
        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .navbar-brand img {
            height: 60px;
        }
        
        /* Cart styles */
        .cart-container {
            margin: 30px 0;
        }
        
        .cart-item {
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .cart-item-image {
            width: 100px;
            height: 130px;
            object-fit: cover;
        }
        
        .cart-item-details {
            padding-left: 20px;
        }
        
        .cart-item-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .cart-item-attr {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 3px;
        }
        
        .quantity-selector {
            width: 100px;
        }
        
        .cart-actions {
            margin-top: 10px;
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
        }
        
        .cart-action {
            color: var(--text-dark);
            text-decoration: none;
            cursor: pointer;
        }
        
        .cart-action:hover {
            color: var(--primary-color);
        }
        
        .cart-summary {
            background-color: var(--secondary-color);
            border-radius: 10px;
            padding: 20px;
        }
        
        .cart-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .free-shipping-message {
            background-color: #e9f7ef;
            border-radius: 10px;
            padding: 10px 15px;
            margin: 15px 0;
            display: flex;
            align-items: center;
        }
        
        .free-shipping-bubble {
            background-color: #f8d85a;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
        }
        
        .checkout-button {
            background-color: #000;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px;
            width: 100%;
            font-weight: 600;
            margin-top: 15px;
        }
        
        .checkout-button:hover {
            background-color: #333;
        }
        
        .promo-code-form {
            display: flex;
            margin-bottom: 20px;
        }
        
        .promo-code-input {
            flex-grow: 1;
            border: 1px solid var(--border-color);
            border-radius: 5px 0 0 5px;
            padding: 8px 15px;
        }
        
        .promo-code-button {
            background-color: #000;
            color: white;
            border: none;
            border-radius: 0 5px 5px 0;
            padding: 8px 15px;
        }
        
        /* Purchase type toggle */
        .purchase-type-container {
            margin-top: 10px;
        }
        
        .purchase-type-label {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .purchase-type-toggle {
            display: flex;
            gap: 10px;
        }
        
        .purchase-option {
            flex: 1;
            text-align: center;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .purchase-option.active {
            background-color: #e9f7ef;
            border-color: #28a745;
            color: #28a745;
        }
        
        .rent-duration {
            margin-top: 10px;
            display: none;
        }
        
        .rent-duration.active {
            display: block;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px 0;
            height: 100%;
        }
        
        .sidebar-link {
            display: block;
            padding: 12px 20px;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-link:hover, .sidebar-link.active {
            background-color: rgba(0, 123, 255, 0.05);
            color: #4a6cf7;
            border-left: 3px solid #4a6cf7;
        }
        
        .sidebar-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        /* Empty cart styles */
.empty-cart-container {
    padding: 80px 0;
    text-align: center;
    max-width: 500px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 50vh; /* Ensure it takes up at least half the viewport height */
}

.empty-cart-image {
    max-width: 180px;
    margin-bottom: 30px;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
    100% {
        transform: translateY(0px);
    }
}

.empty-cart-title {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #333;
}

.empty-cart-message {
    color: #6c757d;
    font-size: 16px;
    margin-bottom: 30px;
    line-height: 1.5;
}

.continue-shopping-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 5px;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.continue-shopping-btn:hover {
    background-color: #e69400;
    transform: translateY(-2px);
    box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
    color: white;
    text-decoration: none;
}

.continue-shopping-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .container {
        max-width: 100%;
        padding-left: 20px;
        padding-right: 20px;
    }

    .cart-item {
        padding: 20px;
    }

    .cart-summary {
        padding: 20px;
    }
}

@media (max-width: 992px) {
    .row {
        --bs-gutter-x: 15px;
    }

    .sidebar {
        margin-bottom: 30px;
    }

    .cart-container {
        margin-bottom: 20px;
    }

    .cart-item {
        padding: 15px;
    }

    .cart-item-image {
        width: 80px;
        height: 120px;
        object-fit: cover;
    }

    .cart-item-title {
        font-size: 1.1rem;
    }

    .purchase-type-toggle {
        flex-direction: column;
        gap: 8px;
    }

    .purchase-option {
        padding: 6px 12px;
        font-size: 0.9rem;
    }

    .cart-summary {
        margin-top: 0;
    }
}

@media (max-width: 768px) {
    .container {
        padding-left: 15px;
        padding-right: 15px;
    }

    .py-5 {
        padding-top: 2rem !important;
        padding-bottom: 2rem !important;
    }

    .sidebar {
        padding: 15px 0;
    }

    .sidebar h4 {
        font-size: 1.25rem;
        margin-bottom: 1rem;
    }

    .sidebar-link {
        padding: 10px 15px;
        font-size: 0.95rem;
    }

    h2 {
        font-size: 1.75rem;
        margin-bottom: 1.5rem;
    }

    .cart-item {
        padding: 15px;
        margin-bottom: 15px;
    }

    .cart-item-image {
        width: 70px;
        height: 100px;
        margin-bottom: 10px;
    }

    .cart-item-title {
        font-size: 1rem;
        margin-bottom: 8px;
    }

    .cart-item-attr {
        font-size: 0.9rem;
        margin-bottom: 5px;
    }

    .purchase-type-container {
        margin-top: 15px;
    }

    .purchase-type-label {
        font-size: 0.9rem;
        margin-bottom: 8px;
    }

    .purchase-type-toggle {
        gap: 6px;
    }

    .purchase-option {
        padding: 8px;
        font-size: 0.85rem;
    }

    .form-select {
        padding: 0.375rem 0.75rem;
        font-size: 0.9rem;
    }

    .cart-actions {
        margin-top: 10px;
    }

    .cart-action {
        font-size: 0.9rem;
        padding: 6px 12px;
    }

    .cart-summary {
        padding: 20px;
        margin-top: 20px;
    }

    .cart-summary h4 {
        font-size: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .promo-code-form {
        margin-bottom: 20px;
    }

    .promo-code-input {
        padding: 10px;
        font-size: 0.9rem;
    }

    .promo-code-button {
        padding: 10px 15px;
        font-size: 0.9rem;
    }

    .cart-summary-row {
        padding: 8px 0;
        font-size: 0.95rem;
    }

    .checkout-button {
        padding: 12px;
        font-size: 1rem;
    }

    .empty-cart-title {
        font-size: 1.5rem;
    }

    .empty-cart-message {
        font-size: 0.95rem;
    }

    .continue-shopping-btn {
        padding: 10px 25px;
        font-size: 0.95rem;
    }

    .cart-summary-mobile {
        display: block !important;
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-top: 20px;
    }

    .cart-summary-mobile .cart-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #dee2e6;
    }

    .cart-summary-mobile .cart-summary-row:last-child {
        border-bottom: none;
    }
}

@media (max-width: 576px) {
    .container {
        padding-left: 10px;
        padding-right: 10px;
    }

    .py-5 {
        padding-top: 1.5rem !important;
        padding-bottom: 1.5rem !important;
    }

    h2 {
        font-size: 1.5rem;
        text-align: center;
    }

    .sidebar {
        padding: 12px 0;
        border-radius: 6px;
    }

    .sidebar h4 {
        font-size: 1.1rem;
        padding: 0 15px;
        margin-bottom: 0.75rem;
    }

    .sidebar-link {
        padding: 8px 15px;
        font-size: 0.9rem;
    }

    .sidebar-link i {
        width: 18px;
        margin-right: 8px;
    }

    .cart-item {
        padding: 12px;
        border-radius: 8px;
    }

    .cart-item-image {
        width: 60px;
        height: 90px;
    }

    .cart-item-title {
        font-size: 0.95rem;
        line-height: 1.3;
    }

    .cart-item-attr {
        font-size: 0.85rem;
    }

    .purchase-type-container {
        margin-top: 12px;
    }

    .purchase-type-toggle {
        gap: 5px;
    }

    .purchase-option {
        padding: 6px;
        font-size: 0.8rem;
        border-radius: 4px;
    }

    .rent-duration select {
        font-size: 0.85rem;
    }

    .cart-actions {
        margin-top: 8px;
    }

    .cart-action {
        font-size: 0.85rem;
        padding: 5px 10px;
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }

    .cart-summary {
        padding: 16px;
        border-radius: 8px;
    }

    .cart-summary h4 {
        font-size: 1.1rem;
        margin-bottom: 1.25rem;
    }

    .promo-code-form {
        margin-bottom: 16px;
    }

    .promo-code-input {
        padding: 8px;
        font-size: 0.85rem;
    }

    .promo-code-button {
        padding: 8px 12px;
        font-size: 0.85rem;
    }

    .cart-summary-row {
        padding: 6px 0;
        font-size: 0.9rem;
    }

    .checkout-button {
        padding: 10px;
        font-size: 0.95rem;
    }

    .free-shipping-message {
        font-size: 0.85rem;
    }

    .empty-cart-container {
        padding: 40px 0;
        min-height: 40vh;
    }

    .empty-cart-image {
        max-width: 120px;
        margin-bottom: 20px;
    }

    .empty-cart-title {
        font-size: 1.25rem;
        margin-bottom: 12px;
    }

    .empty-cart-message {
        font-size: 0.9rem;
        margin-bottom: 20px;
    }

    .continue-shopping-btn {
        padding: 8px 20px;
        font-size: 0.9rem;
    }

    .cart-summary-mobile {
        padding: 12px;
        margin-top: 15px;
    }

    .cart-summary-mobile .cart-summary-row {
        padding: 6px 0;
        font-size: 0.9rem;
    }
}

@media (max-width: 480px) {
    .container {
        padding-left: 8px;
        padding-right: 8px;
    }

    .py-5 {
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
    }

    .sidebar {
        margin-bottom: 20px;
    }

    .cart-item {
        padding: 10px;
    }

    .cart-item-image {
        width: 50px;
        height: 75px;
    }

    .purchase-type-toggle {
        flex-direction: column;
        align-items: stretch;
    }

    .purchase-option {
        text-align: center;
        padding: 8px 4px;
        font-size: 0.75rem;
    }

    .cart-summary {
        padding: 14px;
    }

    .cart-summary h4 {
        font-size: 1rem;
    }

    .checkout-button {
        padding: 8px;
        font-size: 0.9rem;
    }

    .empty-cart-container {
        padding: 30px 0;
    }

    .empty-cart-image {
        max-width: 100px;
        margin-bottom: 15px;
    }
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    visibility: hidden;
    opacity: 0;
    transition: visibility 0s, opacity 0.3s;
}

.loading-overlay.active {
    visibility: visible;
    opacity: 1;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid var(--border-color);
    border-top-color: var(--primary-color);
    border-radius: 50%;
    animation: spin 1s infinite linear;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Include Header -->
    <?php 
    if ($userType == 'user') {
        include("include/user_header.php");
    } elseif ($userType == 'seller') {
        include("include/seller_header.php");
    }
    ?>

    <div class="container py-5">
        <div class="row">
            <!-- Sidebar Column -->
            <div class="col-md-3 mb-4">
                <div class="sidebar">
                    <h4 class="px-4 mb-4">My Profile</h4>
                    <a href="account.php" class="sidebar-link">
                        <i class="fa-solid fa-user"></i> Account
                    </a>
                    <a href="cart.php" class="sidebar-link active">
                        <i class="fa-solid fa-shopping-cart"></i> Cart
                    </a>
                    <a href="rented_books.php" class="sidebar-link">
                        <i class="fa-solid fa-book"></i> Rented books
                    </a>

                    <a href="collections.php" class="sidebar-link">
                        <i class="fa-solid fa-bookmark"></i> My Collections
                    </a>
                    <a href="history.php" class="sidebar-link">
                        <i class="fa-solid fa-clock-rotate-left"></i> History
                    </a>
                </div>
            </div>
            
            <!-- Main Content Column -->
            <div class="col-md-9">
                <h2 class="mb-4">Shopping Cart</h2>
                

                <?php if (empty($cartItems)): ?>
                <div class="empty-cart-container">
                    <div>
                        <svg width="180" height="180" viewBox="0 0 200 200" class="empty-cart-image">
                            <!-- Background circle -->
                            <circle cx="100" cy="100" r="80" fill="#f5f5f5" />
                            
                            <!-- Shopping cart -->
                            <path d="M50 80 L65 130 L140 130 L155 80 Z" fill="none" stroke="#333" stroke-width="3" />
                            <line x1="55" y1="90" x2="150" y2="90" stroke="#333" stroke-width="1.5" stroke-dasharray="4" />
                            <line x1="65" y1="105" x2="140" y2="105" stroke="#333" stroke-width="1.5" stroke-dasharray="4" />
                            <line x1="65" y1="120" x2="140" y2="120" stroke="#333" stroke-width="1.5" stroke-dasharray="4" />
                            
                            <!-- Cart handle -->
                            <path d="M45 80 L45 65 L60 65" fill="none" stroke="#333" stroke-width="3" />
                            
                            <!-- Wheels -->
                            <circle cx="75" cy="140" r="6" fill="#f84d4d" />
                            <circle cx="105" cy="140" r="6" fill="#f84d4d" />
                            <circle cx="135" cy="140" r="6" fill="#f84d4d" />
                            
                            <!-- Decorative confetti -->
                            <path d="M40 50 Q45 45, 50 50" stroke="#f84d4d" stroke-width="2" fill="none" />
                            <path d="M160 40 Q165 45, 160 50" stroke="#95d259" stroke-width="2" fill="none" />
                            <path d="M170 85 Q175 80, 180 85" stroke="#f5a623" stroke-width="2" fill="none" />
                            <path d="M30 100 Q25 105, 30 110" stroke="#50b7e0" stroke-width="2" fill="none" />
                            
                            <circle cx="45" cy="30" r="3" fill="#f84d4d" />
                            <circle cx="165" cy="65" r="3" fill="#50b7e0" />
                            <circle cx="25" cy="80" r="3" fill="#95d259" />
                            <circle cx="155" cy="30" r="3" fill="#f5a623" />
                        </svg>
                    </div>
                    <h3 class="empty-cart-title">Your cart is empty</h3>
                    <p class="empty-cart-message">Browse and find the best books fit in your mood.</p>
                    <a href="rentbooks.php" class="continue-shopping-btn">Continue shopping</a>
                </div>
                <?php else: ?>
                <div class="row">
                    <!-- Cart Items -->
                    <div class="col-lg-8">
                        <div class="cart-container">
                            <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <div class="row">
                                    <div class="col-md-3">
                                        <img src="<?php echo $item['cover_image']; ?>" alt="<?php echo $item['title']; ?>" class="cart-item-image">
                                    </div>
                                    <div class="col-md-9">
                                        <div class="cart-item-details">
                                            <h5 class="cart-item-title"><?php echo $item['title']; ?></h5>
                                            <div class="cart-item-attr">Author: <?php echo $item['author']; ?></div>
                                            
                                <?php if ($item['purchase_type'] == 'rent'): ?>
                                    <div>Rent Price: ₱<?php echo number_format($item['rent_price'] * $item['rental_weeks'], 2); ?> 
                                        (for <?php echo $item['rental_weeks']; ?> week<?php echo $item['rental_weeks'] > 1 ? 's' : ''; ?>)
                                    </div>
                                <?php else: ?>
                                    <div>Buy Price: ₱<?php echo number_format($item['price'], 2); ?></div>
                                <?php endif; ?>

                                            <form action="cart.php" method="post" class="mt-3">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                
                                                <div class="row align-items-center mb-3">
                                                    <div class="col-sm-4">
                                                        <label class="form-label">Quantity</label>
                                                        <select name="quantity" class="form-select quantity-selector">
                                                            <?php 
                                                            // Get the book stock
                                                            $stockStmt = $conn->prepare("SELECT stock FROM books WHERE book_id = ?");
                                                            $stockStmt->bind_param("i", $item['book_id']);
                                                            $stockStmt->execute();
                                                            $stockResult = $stockStmt->get_result();
                                                            $stockData = $stockResult->fetch_assoc();
                                                            $maxStock = $stockData['stock'];
                                                            
                                                            // Set max to either 10 or the available stock, whichever is smaller
                                                            $maxQuantity = min(10, $maxStock);
                                                            
                                                            for ($i = 1; $i <= $maxQuantity; $i++): 
                                                            ?>
                                                            <option value="<?php echo $i; ?>" <?php echo ($item['quantity'] == $i) ? 'selected' : ''; ?>>
                                                                <?php echo $i; ?>
                                                            </option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-sm-8">
                                                        <div class="purchase-type-container">
                                                            <div class="purchase-type-label">Purchase Type</div>
                                                            <div class="purchase-type-toggle">
                                                                <div class="purchase-option <?php echo ($item['purchase_type'] == 'buy') ? 'active' : ''; ?>" 
                                                                     data-type="buy" data-cart-id="<?php echo $item['cart_id']; ?>">
                                                                    Buy (₱<?php echo number_format($item['price'], 2); ?>)
                                                                </div>
                                                                <div class="purchase-option <?php echo ($item['purchase_type'] == 'rent') ? 'active' : ''; ?>" 
                                                                     data-type="rent" data-cart-id="<?php echo $item['cart_id']; ?>">
                                                                    Rent (₱<?php echo number_format($item['rent_price'], 2); ?>/week)
                                                                </div>
                                                            </div>
                                                            <input type="hidden" name="purchase_type" id="purchase_type_<?php echo $item['cart_id']; ?>" 
                                                                   value="<?php echo $item['purchase_type']; ?>">
                                                            
                                                            <div class="rent-duration <?php echo ($item['purchase_type'] == 'rent') ? 'active' : ''; ?>" 
                                                                 id="rent_duration_<?php echo $item['cart_id']; ?>">
                                                                <label class="form-label mt-2">Rental Duration (weeks)</label>
                                                                <select name="rental_weeks" class="form-select">
                                                                    <?php for ($i = 1; $i <= 16; $i++): ?>
                                                                    <option value="<?php echo $i; ?>" <?php echo ($item['rental_weeks'] == $i) ? 'selected' : ''; ?>>
                                                                        <?php echo $i; ?> week<?php echo ($i > 1) ? 's' : ''; ?>
                                                                    </option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="cart-actions">
                                                        <a href="cart.php?action=remove&cart_id=<?php echo $item['cart_id']; ?>" class="cart-action">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </a>
                                                    </div>
                                                    
                                                    <button type="submit" name="update_item" class="btn btn-sm btn-outline-secondary">
                                                        Update
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="cart-summary-mobile d-md-none mt-4">
                                <div class="cart-summary-row">
                                    <span>Subtotal (<?php echo count($cartItems); ?> items)</span>
                                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cart Summary -->
                    <div class="col-lg-4">
                        <div class="cart-summary">
                            <h4 class="mb-4">Order Summary</h4>
                            
                            <div class="promo-code-form">
                                <input type="text" class="promo-code-input" placeholder="Promo Code">
                                <button type="button" class="promo-code-button">Submit</button>
                            </div>
                            
                            <div class="cart-summary-row">
                                <span>Subtotal</span>
                                <span>₱<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            
                            <div class="cart-summary-row">
                                <span>Shipping cost</span>
                                <span>To be determined at checkout</span>
                            </div>
                            
                            <?php if ($discount > 0): ?>
                            <div class="cart-summary-row">
                                <span>Discount</span>
                                <span>-₱<?php echo number_format($discount, 2); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="cart-summary-row border-top pt-2">
                                <span class="fw-bold">Estimated Total</span>
                                <span class="fw-bold">₱<?php echo number_format($total, 2); ?></span>
                            </div>
                            
                            <div class="free-shipping-message">
                                <div>Note: ₱60 shipping fee will be added for Cash on Delivery</div>
                                <div class="free-shipping-bubble">
                                    <i class="fas fa-truck"></i>
                                </div>
                            </div>
                            
                            <a href="checkout.php" class="checkout-button text-center text-decoration-none <?php echo empty($cartItems) ? 'disabled' : ''; ?>">
                                <i class="fas fa-lock me-2"></i> Checkout
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Document ready handler
        document.addEventListener('DOMContentLoaded', function() {
            // Code was moved to the main script below
        });
    </script>
    <script>
    // Purchase type toggle and dynamic price updates
    document.addEventListener('DOMContentLoaded', function() {
        const purchaseOptions = document.querySelectorAll('.purchase-option');
        
        // Function to recalculate cart totals
        function recalculateCart() {
            let subtotal = 0;
            let discount = 0;
            
            // Loop through all cart items to calculate new subtotal
            document.querySelectorAll('.cart-item').forEach(item => {
                const cartId = item.querySelector('input[name="cart_id"]').value;
                const quantity = parseInt(item.querySelector('select[name="quantity"]').value);
                const purchaseType = document.getElementById(`purchase_type_${cartId}`).value;
                
                // Get price values (stored as data attributes or find them in the UI)
                let itemPrice = 0;
                if (purchaseType === 'buy') {
                    // Get buy price from the UI
                    const buyPriceText = item.querySelector('.purchase-option[data-type="buy"]').textContent;
                    itemPrice = parseFloat(buyPriceText.replace(/[^\d.]/g, ''));
                } else {
                    // Get rent price from the UI
                    const rentPriceText = item.querySelector('.purchase-option[data-type="rent"]').textContent;
                    const weeklyPrice = parseFloat(rentPriceText.replace(/[^\d.]/g, ''));
                    const rentalWeeks = parseInt(item.querySelector('select[name="rental_weeks"]').value);
                    itemPrice = weeklyPrice * rentalWeeks;
                }
                
                subtotal += itemPrice * quantity;
            });
            
            // Calculate total (no tax, no shipping fee now)
            const total = subtotal - discount;
            
            // Update UI with new totals
            document.querySelector('.cart-summary-row:nth-child(3) span:last-child').textContent = 
                subtotal > 0 ? `₱${subtotal.toFixed(2)}` : '₱0.00';
                
            // Update total
            document.querySelector('.cart-summary-row:nth-child(5) span:last-child').textContent = 
                `₱${total.toFixed(2)}`;
            
            // Also update mobile summary if it exists
            const mobileSummary = document.querySelector('.cart-summary-mobile');
            if (mobileSummary) {
                mobileSummary.querySelector('.cart-summary-row span:last-child').textContent = 
                    `₱${subtotal.toFixed(2)}`;
            }
        }
        
        // Purchase type toggle handler
        purchaseOptions.forEach(option => {
            option.addEventListener('click', function() {
                const cartId = this.getAttribute('data-cart-id');
                const type = this.getAttribute('data-type');
                const purchaseTypeInput = document.getElementById(`purchase_type_${cartId}`);
                const rentDuration = document.getElementById(`rent_duration_${cartId}`);
                
                // Update active state
                document.querySelectorAll(`.purchase-option[data-cart-id="${cartId}"]`).forEach(opt => {
                    opt.classList.remove('active');
                });
                this.classList.add('active');
                
                // Update hidden input
                purchaseTypeInput.value = type;
                
                // Show/hide rent duration
                if (type === 'rent') {
                    rentDuration.classList.add('active');
                    
                    // Make sure rental weeks is set to a valid value when switching to rent
                    const rentalWeeksSelect = rentDuration.querySelector('select[name="rental_weeks"]');
                    if (rentalWeeksSelect && rentalWeeksSelect.value === '') {
                        rentalWeeksSelect.value = '1';
                    }
                    
                    console.log(`Switched to rent: cart_id=${cartId}, rental_weeks=${rentalWeeksSelect ? rentalWeeksSelect.value : 'unknown'}`);
                } else {
                    rentDuration.classList.remove('active');
                }
                
                // Recalculate cart totals for visual feedback
                recalculateCart();
                
                // Auto-submit the form when purchase type changes
                // Find the closest form and submit it
                const form = this.closest('form');
                if (form) {
                    // Show loading overlay
                    document.getElementById('loadingOverlay').classList.add('active');
                    
                    // Add a hidden input for update_item
                    const updateItemInput = document.createElement('input');
                    updateItemInput.type = 'hidden';
                    updateItemInput.name = 'update_item';
                    updateItemInput.value = '1';
                    form.appendChild(updateItemInput);
                    
                    // Add a small delay to allow the user to see the change before form submission
                    setTimeout(() => {
                        form.submit();
                    }, 300);
                }
            });
        });
        
        // Also add event listeners to quantity and rental weeks dropdowns
        document.querySelectorAll('select[name="quantity"], select[name="rental_weeks"]').forEach(select => {
            select.addEventListener('change', function() {
                // Recalculate cart totals for visual feedback
                recalculateCart();
                
                // Auto-submit the form when rental weeks or quantity changes
                // Find the closest form and submit it
                const form = this.closest('form');
                if (form) {
                    // Show loading overlay
                    document.getElementById('loadingOverlay').classList.add('active');
                    
                    // Make sure the rental_weeks value is properly set if this is a rental
                    const cartId = form.querySelector('input[name="cart_id"]').value;
                    const purchaseType = document.getElementById(`purchase_type_${cartId}`).value;
                    
                    if (purchaseType === 'rent') {
                        // Ensure the value is passed correctly
                        const rentalWeeks = this.value;
                        console.log(`Submitting form: cart_id=${cartId}, purchase_type=${purchaseType}, rental_weeks=${rentalWeeks}`);
                    }
                    
                    // Add a hidden input for update_item
                    const updateItemInput = document.createElement('input');
                    updateItemInput.type = 'hidden';
                    updateItemInput.name = 'update_item';
                    updateItemInput.value = '1';
                    form.appendChild(updateItemInput);
                    
                    // Add a small delay to allow the user to see the change before form submission
                    setTimeout(() => {
                        form.submit();
                    }, 300);
                }
            });
        });
        
        // Initialize all forms to show loading overlay on submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                document.getElementById('loadingOverlay').classList.add('active');
            });
        });
    });
</script>
</body>
</html>