<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? '';
$userId = $_SESSION['id'] ?? 0;

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Debug mode
if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    error_log("Debug mode enabled in rented_books.php");

    if (isset($_GET['test_db'])) {
        try {
            $testQuery = "SELECT * FROM sellers LIMIT 1";
            $testResult = $conn->query($testQuery);
            $testData = $testResult->fetch_assoc();
            error_log("DB Connection test: " . print_r($testData, true));
        } catch (Exception $e) {
            error_log("DB Connection test error: " . $e->getMessage());
        }
    }
}

// Handle rental actions
if (isset($_GET['action']) && isset($_GET['rental_id'])) {
    $rentalId = intval($_GET['rental_id']);
    $action = $_GET['action'];

    if ($action == 'return') {
        // Fetch rental details including order_id for comprehensive return process
        $rentalStmt = $conn->prepare("
            SELECT 
                br.rental_id, 
                br.book_id, 
                br.user_id, 
                br.seller_id, 
                br.total_price, 
                br.rental_weeks, 
                br.rental_date, 
                br.due_date,
                br.order_id,  /* Make sure to fetch the order_id */
                b.title as book_title,
                b.author as book_author,
                b.rent_price
            FROM book_rentals br
            JOIN books b ON br.book_id = b.book_id
            WHERE br.rental_id = ? AND br.user_id = ?
        ");
        $rentalStmt->bind_param("ii", $rentalId, $userId);
        $rentalStmt->execute();
        $rentalResult = $rentalStmt->get_result();
        $rentalDetails = $rentalResult->fetch_assoc();

        if ($rentalDetails) {
            // Check if the book is overdue
            $dueDate = strtotime($rentalDetails['due_date']);
            $returnDate = time();
            $isOverdue = $returnDate > $dueDate;

            // Calculate late fee if overdue
            $lateFee = 0;
            $daysOverdue = 0;
            if ($isOverdue) {
                $daysOverdue = ceil(($returnDate - $dueDate) / (60 * 60 * 24));
                $dailyLateFee = $rentalDetails['rent_price'] * 0.2; // 20% of daily rental price per day
                $lateFee = $daysOverdue * $dailyLateFee;
            }

            // Start a transaction to ensure data consistency
            $conn->begin_transaction();

            try {
                // Update rental status with return details
                $updateRentalStmt = $conn->prepare("
                    UPDATE book_rentals 
                    SET 
                        status = 'returned', 
                        return_date = NOW(),
                        late_fee = ?,
                        days_overdue = ?
                    WHERE rental_id = ? AND user_id = ?
                ");
                $updateRentalStmt->bind_param("diii", $lateFee, $daysOverdue, $rentalId, $userId);
                $updateRentalStmt->execute();

                // Update book availability (increase stock)
                $updateBookStmt = $conn->prepare("
                    UPDATE books 
                    SET stock = stock + 1 
                    WHERE book_id = ?
                ");
                $updateBookStmt->bind_param("i", $rentalDetails['book_id']);
                $updateBookStmt->execute();

                // Use the actual order_id from the rental record or a default if needed
                // If order_id is NULL in database but column cannot be NULL, use a placeholder ID like 0 or 999999
                $orderIdForLog = $rentalDetails['order_id'] ?? 999999; // Use a placeholder ID if NULL

                // Log the return with a valid order_id
                $logStmt = $conn->prepare("
                    INSERT INTO payment_logs (
                        order_id, 
                        user_id, 
                        action, 
                        status, 
                        amount, 
                        details
                    ) VALUES (
                        ?, 
                        ?, 
                        'book_return', 
                        'success', 
                        ?, 
                        ?
                    )
                ");
                $logDetails = "Book returned: " . $rentalDetails['book_title'] . 
                              ($isOverdue ? " (Overdue: $daysOverdue days)" : "");
                $logStmt->bind_param("iids", $orderIdForLog, $userId, $lateFee, $logDetails);
                $logStmt->execute();

                // Commit the transaction
                $conn->commit();

                // Prepare success message
                $successMessage = "Book '{$rentalDetails['book_title']}' has been returned successfully.";
                if ($isOverdue) {
                    $successMessage .= " A late fee of ₱" . number_format($lateFee, 2) . " has been applied.";
                }

                $_SESSION['success_message'] = $successMessage;
            } catch (Exception $e) {
                // Rollback the transaction in case of error
                $conn->rollback();
                $_SESSION['error_message'] = "Failed to process book return: " . $e->getMessage();
                error_log("Book return error: " . $e->getMessage());
            }
        } else {
            $_SESSION['error_message'] = "Invalid rental record.";
        }

        // Redirect back to the rentals page
        header("Location: rented_books.php?tab=rentals");
        exit();
    } elseif ($action == 'extend') {
        // Default extension to 1 week
        $extendWeeks = 1;

        // Fetch current rental details
        $rentalStmt = $conn->prepare("
            SELECT 
                due_date, 
                rental_weeks, 
                total_price, 
                book_id,
                status
            FROM book_rentals 
            WHERE rental_id = ? AND user_id = ?
        ");
        $rentalStmt->bind_param("ii", $rentalId, $userId);
        $rentalStmt->execute();
        $rentalResult = $rentalStmt->get_result();

        if ($rental = $rentalResult->fetch_assoc()) {
            // Check if rental is already overdue
            $currentDate = new DateTime();
            $dueDate = new DateTime($rental['due_date']);
            
            if ($currentDate > $dueDate) {
                $_SESSION['error_message'] = "Cannot extend an overdue rental. Please return the book.";
                header("Location: rented_books.php?tab=rentals");
                exit();
            }

            // Check remaining extensions
            $extensionQuery = $conn->prepare("
                SELECT COUNT(*) as extension_count 
                FROM book_rentals 
                WHERE rental_id = ? AND user_id = ? AND extensions_used > 0
            ");
            $extensionQuery->bind_param("ii", $rentalId, $userId);
            $extensionQuery->execute();
            $extensionResult = $extensionQuery->get_result()->fetch_assoc();
            
            // Limit to 2 total extensions
            if ($extensionResult['extension_count'] >= 2) {
                $_SESSION['error_message'] = "You have reached the maximum number of rental extensions.";
                header("Location: rented_books.php?tab=rentals");
                exit();
            }

            // Fetch book details for rental price
            $bookStmt = $conn->prepare("SELECT rent_price FROM books WHERE book_id = ?");
            $bookStmt->bind_param("i", $rental['book_id']);
            $bookStmt->execute();
            $book = $bookStmt->get_result()->fetch_assoc();

            // Calculate new dates and prices
            $currentDueDate = new DateTime($rental['due_date']);
            $currentDueDate->modify("+{$extendWeeks} week");
            $newDueDate = $currentDueDate->format('Y-m-d H:i:s');

            $newRentalWeeks = $rental['rental_weeks'] + $extendWeeks;
            $newTotalPrice = $rental['total_price'] + ($book['rent_price'] * $extendWeeks);

            // Start transaction for extension
            $conn->begin_transaction();

            try {
                // Update rental details
                $updateStmt = $conn->prepare("
                    UPDATE book_rentals 
                    SET 
                        due_date = ?, 
                        rental_weeks = ?, 
                        total_price = ?,
                        extensions_used = COALESCE(extensions_used, 0) + 1
                    WHERE rental_id = ? AND user_id = ?
                ");
                $updateStmt->bind_param("sidii", $newDueDate, $newRentalWeeks, $newTotalPrice, $rentalId, $userId);
                $updateStmt->execute();

                // Log the extension
                $logStmt = $conn->prepare("
                    INSERT INTO payment_logs (
                        order_id, 
                        user_id, 
                        action, 
                        status, 
                        amount, 
                        details
                    ) VALUES (
                        NULL, 
                        ?, 
                        'rental_extension', 
                        'success', 
                        ?, 
                        ?
                    )
                ");
                $logDetails = "Rental extended by $extendWeeks week(s)";
                $logStmt->bind_param("ids", $userId, $book['rent_price'] * $extendWeeks, $logDetails);
                $logStmt->execute();

                // Commit transaction
                $conn->commit();

                $_SESSION['success_message'] = "Rental successfully extended by $extendWeeks week(s). New due date is " . $newDueDate;
            } catch (Exception $e) {
                // Rollback transaction
                $conn->rollback();
                $_SESSION['error_message'] = "Failed to extend rental. Please try again.";
                error_log("Rental extension error: " . $e->getMessage());
            }
        } else {
            $_SESSION['error_message'] = "Invalid rental record.";
        }

        // Redirect back to rentals page
        header("Location: rented_books.php?tab=rentals");
        exit();
    }
}

// Confirm receipt of item (Rental or Purchase)
if (isset($_GET['action']) && $_GET['action'] == 'confirm_receipt' && isset($_GET['order_item_id'])) {
    $orderItemId = intval($_GET['order_item_id']);

    $typeStmt = $conn->prepare("
        SELECT oi.item_id, oi.purchase_type, oi.rental_weeks, oi.book_id, oi.order_id, oi.seller_id, oi.unit_price,
               b.rent_price, b.price
        FROM order_items oi 
        JOIN books b ON oi.book_id = b.book_id 
        WHERE oi.item_id = ?
    ");
    $typeStmt->bind_param("i", $orderItemId);
    $typeStmt->execute();
    $typeResult = $typeStmt->get_result();
    $itemType = $typeResult->fetch_assoc();

    if (!$itemType) {
        $_SESSION['error_message'] = "Item not found.";
        header("Location: rented_books.php");
        exit();
    }

    error_log("Item #" . $orderItemId . " purchase_type: '" . $itemType['purchase_type'] . "'");
    error_log("Item #" . $orderItemId . " rental_weeks: " . $itemType['rental_weeks']);

    // Update status to 'delivered'
    $updateStmt = $conn->prepare("UPDATE order_items SET status = 'delivered' WHERE item_id = ?");
    $updateStmt->bind_param("i", $orderItemId);
    $updateStmt->execute();

    // IMPORTANT: Only create rental records for items with purchase_type='rent'
    if ($itemType['purchase_type'] == 'rent' && $itemType['rental_weeks'] > 0) {
        // Check if rental already exists
        $checkRentalStmt = $conn->prepare("SELECT rental_id FROM book_rentals WHERE order_id = ? AND book_id = ? AND user_id = ?");
        $checkRentalStmt->bind_param("iii", $itemType['order_id'], $itemType['book_id'], $userId);
        $checkRentalStmt->execute();
        $existingRental = $checkRentalStmt->get_result();

        if ($existingRental->num_rows == 0) {
            // Create rental
            $rentalDate = date('Y-m-d H:i:s');
            $dueDate = date('Y-m-d H:i:s', strtotime("+{$itemType['rental_weeks']} weeks"));
            $totalPrice = $itemType['unit_price'];

            // Find seller ID
            $bookStmt = $conn->prepare("SELECT user_id FROM books WHERE book_id = ?");
            $bookStmt->bind_param("i", $itemType['book_id']);
            $bookStmt->execute();
            $bookResult = $bookStmt->get_result()->fetch_assoc();

            $sellerIdQuery = $conn->prepare("SELECT id FROM sellers WHERE user_id = ?");
            $sellerIdQuery->bind_param("i", $bookResult['user_id']);
            $sellerIdQuery->execute();
            $sellerResult = $sellerIdQuery->get_result();

            $sellerId = ($sellerResult->num_rows > 0) ? $sellerResult->fetch_assoc()['id'] : 2;

            $rentalStmt = $conn->prepare("
                INSERT INTO book_rentals (
                    user_id, book_id, seller_id, order_id,
                    rental_date, due_date, rental_weeks, status, total_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)
            ");
            $rentalStmt->bind_param(
                "iiisssid",
                $userId,
                $itemType['book_id'],
                $sellerId,
                $itemType['order_id'],
                $rentalDate,
                $dueDate,
                $itemType['rental_weeks'],
                $totalPrice
            );

            if ($rentalStmt->execute()) {
                $rentalId = $conn->insert_id;
                $_SESSION['success_message'] = "Your rental has started! You can now track your rental duration in the Rentals tab. Due date: " . date('F j, Y', strtotime($dueDate));
            } else {
                $_SESSION['error_message'] = "Failed to create rental: " . $rentalStmt->error;
            }
        } else {
            $rentalId = $existingRental->fetch_assoc()['rental_id'];
            $_SESSION['success_message'] = "This rental is already active. You can track its duration in the Rentals tab.";
        }

        header("Location: rented_books.php?tab=rentals&highlight_rental=" . ($rentalId ?? 0));
        exit();
    } else {
        // This is a purchase, not a rental
        $logStmt = $conn->prepare("
            INSERT INTO payment_logs (order_id, user_id, action, status, amount, details)
            VALUES (?, ?, 'purchase_confirmation', 'success', ?, ?)
        ");
        $details = "Purchase confirmed for book: " . $itemType['book_id'];
        $amount = $itemType['unit_price'];
        $logStmt->bind_param("iids", $itemType['order_id'], $userId, $amount, $details);
        $logStmt->execute();

        $_SESSION['success_message'] = "Your purchase has been delivered. Thank you!";
        header("Location: rented_books.php?tab=completed");
        exit();
    }
}

// Rest of the code remains unchanged
// Fetch orders query
$ordersQuery = "
    SELECT o.order_id, o.order_date, o.payment_method, o.payment_status, o.order_status,
           oi.item_id, oi.book_id, oi.purchase_type, oi.rental_weeks, oi.status as item_status, oi.unit_price,
           b.title, b.author, b.cover_image, b.description, b.price, b.rent_price,
           s.firstname as seller_firstname, s.lastname as seller_lastname, s.username as seller_username
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN books b ON oi.book_id = b.book_id
    JOIN users s ON oi.seller_id = s.id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC
";

$ordersStmt = $conn->prepare($ordersQuery);
$ordersStmt->bind_param("i", $userId);
$ordersStmt->execute();
$ordersResult = $ordersStmt->get_result();
$orders = $ordersResult->fetch_all(MYSQLI_ASSOC);

// Organize orders by status for the tabbed interface
$toPay = [];
$toShip = [];
$toReceive = [];
$completed = [];
$cancelled = [];
$activeRentals = [];

// First, fetch user's active rentals
$rentalsQuery = "
    SELECT br.*, b.title, b.author, b.cover_image, b.description, b.ISBN, 
           u.firstname, u.lastname, u.username, o.order_id
    FROM book_rentals br
    JOIN books b ON br.book_id = b.book_id
    JOIN sellers s ON br.seller_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN orders o ON br.order_id = o.order_id
    WHERE br.user_id = ? AND br.status = 'active'
    ORDER BY br.rental_date DESC
";

$rentalsStmt = $conn->prepare($rentalsQuery);
$rentalsStmt->bind_param("i", $userId);
$rentalsStmt->execute();
$rentalsResult = $rentalsStmt->get_result();
$activeRentals = $rentalsResult->fetch_all(MYSQLI_ASSOC);

// Debug information
error_log("Active Rentals Query Result: " . count($activeRentals));
if (count($activeRentals) > 0) {
    error_log("First Rental: " . print_r($activeRentals[0], true));
}

// Categorize orders by status
foreach ($orders as $order) {
    // Initialize variables for better type detection
    $isRental = false;
    
    // Determine purchase type if empty
    $purchaseType = $order['purchase_type'];
    if (empty($purchaseType)) {
        // If rental_weeks > 0 and unit price approximately matches rent_price * rental_weeks, it's a rental
        if ($order['rental_weeks'] > 0 && 
            abs($order['unit_price'] - ($order['rent_price'] * $order['rental_weeks'])) < 5) {
            $purchaseType = 'rent';
        }
        // If unit price is significantly less than book price, it's likely a rental
        else if ($order['unit_price'] < ($order['price'] * 0.9)) {
            $purchaseType = 'rent';
        }
        // If unit price is close to book price, it's a purchase
        else if (abs($order['unit_price'] - $order['price']) < ($order['price'] * 0.1)) {
            $purchaseType = 'buy';
        }
        // Default: if has rental weeks, assume rental
        else if ($order['rental_weeks'] > 0) {
            $purchaseType = 'rent';
        }
        // Otherwise, assume it's a buy
        else {
            $purchaseType = 'buy';
        }
        
        // Update the order array with the determined purchase type
        $order['purchase_type'] = $purchaseType;
    }
    
    // Check if this is a rental based on purchase_type
    if ($order['purchase_type'] == 'rent') {
        $isRental = true;
    }
    
    // Now determine which category this order belongs to
    if ($order['payment_method'] == 'bank_transfer' && $order['payment_status'] == 'awaiting_payment') {
        $toPay[] = $order;
    } 
    elseif (($order['payment_status'] == 'paid' || $order['payment_method'] == 'cod' || $order['payment_method'] == 'pickup') 
            && ($order['item_status'] == 'pending' || $order['item_status'] == 'processing')) {
        $toShip[] = $order;
    }
    elseif ($order['item_status'] == 'shipped' || $order['item_status'] == 'shipped_pending_confirmation') {
        $toReceive[] = $order;
    }
    elseif ($order['item_status'] == 'delivered') {
        if ($isRental) {
            // This is a rental item that's been delivered
            // Check if it already has an active rental record
            $checkRentalStmt = $conn->prepare(
                "SELECT rental_id, status FROM book_rentals 
                 WHERE order_id = ? AND book_id = ? AND user_id = ?"
            );
            $checkRentalStmt->bind_param("iii", $order['order_id'], $order['book_id'], $userId);
            $checkRentalStmt->execute();
            $rentalResult = $checkRentalStmt->get_result();
            
            if ($rentalResult->num_rows > 0) {
                $rentalRecord = $rentalResult->fetch_assoc();
                // Skip if the rental has a pending return request
                if ($rentalRecord['status'] === 'return_pending' || $rentalRecord['status'] === 'returned') {
                    // Don't add to any active tab - it should only appear in history
                    continue;
                }
            }
            
            // If no active rental exists, add to To Receive for confirmation
            if ($rentalResult->num_rows == 0) {
                $toReceive[] = $order;
            }
            // If it has an active entry in book_rentals, it will be shown in the rentals tab
        } else {
            // This is a regular purchase, add to completed
            $completed[] = $order;
        }
    }
    elseif ($order['item_status'] == 'cancelled') {
        $cancelled[] = $order;
    }
}

// Rest of the file remains unchanged...

// Count items in each category
$toPayCount = count($toPay);
$toShipCount = count($toShip);
$toReceiveCount = count($toReceive);
$completedCount = count($completed);
$cancelledCount = count($cancelled);
$activeRentalsCount = count($activeRentals);

// Determine which tab to show based on URL parameter or default to 'all'
$activeTab = $_GET['tab'] ?? 'all';
$highlightRentalId = isset($_GET['highlight_rental']) ? intval($_GET['highlight_rental']) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders & Rentals - BookWagon</title>
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
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            color: var(--text-dark);
            background-color: #fff;
        }
        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .navbar-brand img {
            height: 60px;
        }

        /* Tab navigation styles */
        .order-tabs {
            display: flex;
            overflow-x: auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .order-tab {
            padding: 15px 20px;
            white-space: nowrap;
            color: var(--text-dark);
            text-decoration: none;
            position: relative;
            font-weight: 500;
            transition: all 0.2s;
            text-align: center;
            flex: 1;
        }
        
        .order-tab.active {
            color: var(--primary-color);
        }
        
        .order-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .order-tab:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .tab-count {
            display: inline-block;
            background-color: #f1f1f1;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 0.8rem;
            line-height: 24px;
            margin-left: 5px;
        }
        
        /* Order card styles */
        .order-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.2s;
        }
        
        .order-card:hover {
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .order-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-date {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .order-number {
            font-weight: 600;
        }
        
        .order-status {
            font-size: 0.85rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .status-to-pay {
            background-color: #fff8e1;
            color: #ff9800;
        }
        
        .status-to-ship {
            background-color: #e3f2fd;
            color: #2196f3;
        }
        
        .status-to-receive {
            background-color: #e8f5e9;
            color: #4caf50;
        }
        
        .status-completed {
            background-color: #e0f2f1;
            color: #009688;
        }
        
        .status-cancelled {
            background-color: #ffebee;
            color: #f44336;
        }
        
        .order-body {
            padding: 20px;
        }
        
        .order-item {
            display: flex;
            align-items: flex-start;
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px dashed var(--border-color);
        }
        
        .order-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .item-image {
            width: 80px;
            height: 110px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .item-author {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .item-price {
            color: var(--text-dark);
            font-weight: 600;
        }
        
        .item-type {
            display: inline-block;
            font-size: 0.8rem;
            padding: 3px 8px;
            background-color: #f1f1f1;
            border-radius: 4px;
            margin-right: 5px;
        }
        
        .order-footer {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-total {
            font-weight: 600;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
        }
        
        .order-action-btn {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-confirm-receipt {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-confirm-receipt:hover {
            background-color: #218838;
            color: white;
        }
        
        .btn-view-details {
            background-color: var(--secondary-color);
            color: var(--text-dark);
        }
        
        .btn-view-details:hover {
            background-color: #e2e6ea;
        }
        
        .seller-info {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .highlighted-rental {
            background-color: #fffaeb;
            border: 2px solid #f8a100;
            box-shadow: 0 0 15px rgba(248, 161, 0, 0.2);
            animation: highlight-pulse 2s ease-in-out 3;
        }
        
        @keyframes highlight-pulse {
            0% { box-shadow: 0 0 15px rgba(248, 161, 0, 0.2); }
            50% { box-shadow: 0 0 20px rgba(248, 161, 0, 0.5); }
            100% { box-shadow: 0 0 15px rgba(248, 161, 0, 0.2); }
        }
        
        /* Sidebar and rental styles from the original file */
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
        
        /* Alert styles */
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 20px;
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
                    <a href="cart.php" class="sidebar-link">
                        <i class="fa-solid fa-shopping-cart"></i> Cart
                    </a>
                    <a href="rented_books.php" class="sidebar-link active">
                        <i class="fa-solid fa-book"></i> Rented Books
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
                <h2 class="mb-4">My Orders & Rentals</h2>
                
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
                
                <!-- Order Tabs -->
                <div class="order-tabs">
                    <a href="?tab=all" class="order-tab <?php echo $activeTab == 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <a href="?tab=to_pay" class="order-tab <?php echo $activeTab == 'to_pay' ? 'active' : ''; ?>">
                        To Pay
                        <?php if ($toPayCount > 0): ?>
                        <span class="tab-count"><?php echo $toPayCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?tab=to_ship" class="order-tab <?php echo $activeTab == 'to_ship' ? 'active' : ''; ?>">
                        To Ship
                        <?php if ($toShipCount > 0): ?>
                        <span class="tab-count"><?php echo $toShipCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?tab=to_receive" class="order-tab <?php echo $activeTab == 'to_receive' ? 'active' : ''; ?>">
                        To Receive
                        <?php if ($toReceiveCount > 0): ?>
                        <span class="tab-count"><?php echo $toReceiveCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?tab=completed" class="order-tab <?php echo $activeTab == 'completed' ? 'active' : ''; ?>">
                        Completed
                    </a>
                    <a href="?tab=cancelled" class="order-tab <?php echo $activeTab == 'cancelled' ? 'active' : ''; ?>">
                        Cancelled
                    </a>
                    <a href="?tab=rentals" class="order-tab <?php echo $activeTab == 'rentals' ? 'active' : ''; ?>">
                        Rentals
                        <?php if ($activeRentalsCount > 0): ?>
                        <span class="tab-count"><?php echo $activeRentalsCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <!-- Orders Content -->
                <?php 
                // Determine which orders to display based on the active tab
                $displayOrders = [];
                
                switch ($activeTab) {
                    case 'to_pay':
                        $displayOrders = $toPay;
                        $emptyMessage = "You don't have any orders waiting for payment.";
                        break;
                    case 'to_ship':
                        $displayOrders = $toShip;
                        $emptyMessage = "You don't have any orders waiting to be shipped.";
                        break;
                    case 'to_receive':
                        $displayOrders = $toReceive;
                        $emptyMessage = "You don't have any orders waiting to be received.";
                        break;
                    case 'completed':
                        $displayOrders = $completed;
                        $emptyMessage = "You don't have any completed orders.";
                        break;
                    case 'cancelled':
                        $displayOrders = $cancelled;
                        $emptyMessage = "You don't have any cancelled orders.";
                        break;
                    case 'rentals':
                        // We'll handle rentals separately
                        $emptyMessage = "You don't have any active rentals.";
                        break;
                    default: // 'all'
                        $displayOrders = array_merge($toPay, $toShip, $toReceive, $completed, $cancelled);
                        $emptyMessage = "You don't have any orders yet.";
                        break;
                }
                
                // Display orders for the active tab
                if ($activeTab != 'rentals' && !empty($displayOrders)): 
                ?>
                    <?php foreach ($displayOrders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-date"><?php echo date('F j, Y', strtotime($order['order_date'])); ?></div>
                                <div class="order-number">Order #<?php echo $order['order_id']; ?></div>
                            </div>
                            <?php
                            // Determine the status text and class based on the category
                            $statusText = "";
                            $statusClass = "";
                            
                            if ($order['payment_method'] == 'bank_transfer' && $order['payment_status'] == 'awaiting_payment') {
                                $statusText = "To Pay";
                                $statusClass = "status-to-pay";
                            } 
                            elseif (($order['payment_status'] == 'paid' || $order['payment_method'] == 'cod' || $order['payment_method'] == 'pickup') 
                                    && ($order['item_status'] == 'pending' || $order['item_status'] == 'processing')) {
                                $statusText = "To Ship";
                                $statusClass = "status-to-ship";
                            }
                            elseif ($order['item_status'] == 'shipped') {
                                $statusText = "To Receive";
                                $statusClass = "status-to-receive";
                            }
                            elseif ($order['item_status'] == 'delivered') {
                                $statusText = "Completed";
                                $statusClass = "status-completed";
                            }
                            elseif ($order['item_status'] == 'cancelled') {
                                $statusText = "Cancelled";
                                $statusClass = "status-cancelled";
                            }
                            ?>
                            <div class="order-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></div>
                        </div>
                        
                        <div class="order-body">
                            <div class="order-item">
                                <img src="<?php echo $order['cover_image']; ?>" alt="<?php echo $order['title']; ?>" class="item-image">
                                
                                <div class="item-details">
                                    <div class="item-title"><?php echo $order['title']; ?></div>
                                    <div class="item-author">by <?php echo $order['author']; ?></div>
                                    
                                    <div class="mt-2">
                                        <span class="item-type">
                                            <?php echo ucfirst($order['purchase_type']); ?>
                                            <?php if ($order['purchase_type'] == 'rent'): ?>
                                            (<?php echo $order['rental_weeks']; ?> week<?php echo $order['rental_weeks'] > 1 ? 's' : ''; ?>)
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="item-price mt-2">
                                        ₱<?php echo number_format($order['unit_price'], 2); ?>
                                    </div>
                                    
                                    <div class="seller-info">
                                        Seller: <?php echo !empty($order['seller_username']) ? $order['seller_username'] : $order['seller_firstname'] . ' ' . $order['seller_lastname']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-footer">
                            <div class="order-total">
                                Total: ₱<?php echo number_format($order['unit_price'], 2); ?>
                            </div>
                            
                            <div class="order-actions">
                                <?php if ($order['item_status'] == 'shipped_pending_confirmation'): ?>
                                <a href="rented_books.php?action=confirm_receipt&rental_id=<?php echo $order['order_id']; ?>&order_item_id=<?php echo $order['item_id']; ?>" class="order-action-btn btn-confirm-receipt">
                                    Confirm Receipt
                                </a>
                                <?php endif; ?>
                                
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="order-action-btn btn-view-details">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                
                <?php elseif ($activeTab == 'rentals' && !empty($activeRentals)): ?>
                    <!-- Display active rentals -->
                    <?php foreach ($activeRentals as $rental): 
                        $highlightClass = ($highlightRentalId > 0 && $rental['rental_id'] == $highlightRentalId) ? 'highlighted-rental' : '';
                    ?>
                    <div class="order-card <?php echo $highlightClass; ?>">
                        <div class="order-header">
                            <div>
                            <div class="order-date">Rental started: <?php echo date('F j, Y', strtotime($rental['rental_date'])); ?></div>
                                <div class="order-number">Rental #<?php echo $rental['rental_id']; ?></div>
                            </div>
                            <div class="order-status status-to-receive">
                                <?php 
                                $statusClass = '';
                                $statusText = ucfirst($rental['status']);
                                
                                switch ($rental['status']) {
                                    case 'active':
                                        $statusClass = 'status-to-receive';
                                        break;
                                    case 'overdue':
                                        $statusClass = 'status-to-pay';
                                        break;
                                    default:
                                        $statusClass = 'status-to-ship';
                                }
                                ?>
                                <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </div>
                        </div>
                        
                        <div class="order-body">
                            <div class="order-item">
                                <img src="<?php echo $rental['cover_image']; ?>" alt="<?php echo $rental['title']; ?>" class="item-image">
                                
                                <div class="item-details">
                                    <div class="item-title"><?php echo $rental['title']; ?></div>
                                    <div class="item-author">by <?php echo $rental['author']; ?></div>
                                    
                                    <div class="mt-2">
                                        <span class="item-type">
                                            Rental: <?php echo $rental['rental_weeks']; ?> week<?php echo $rental['rental_weeks'] > 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="item-price mt-2">
                                        ₱<?php echo number_format($rental['total_price'], 2); ?>
                                    </div>
                                    
                                    <div class="seller-info">
                                        Due Date: <?php echo date('F j, Y', strtotime($rental['due_date'])); ?>
                                        <span class="ms-2 text-muted">
                                            (<?php echo $rental['rental_weeks']; ?> week<?php echo $rental['rental_weeks'] > 1 ? 's' : ''; ?> rental)
                                        </span>
                                        <?php if (strtotime($rental['due_date']) < time()): ?>
                                        <span class="text-danger fw-bold ms-2">OVERDUE</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="seller-info mt-1">
                                        Seller: <?php echo !empty($rental['username']) ? $rental['username'] : $rental['firstname'] . ' ' . $rental['lastname']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-footer">
                            <div class="order-total">
                                Rental Fee: ₱<?php echo number_format($rental['total_price'], 2); ?>
                            </div>
                            
                            <div class="order-actions">
                            <a href="#" class="order-action-btn btn-confirm-receipt btn-return-book"
                            data-bs-toggle="modal" 
                            data-bs-target="#returnBookModal"
                            data-rental-id="<?php echo $rental['rental_id']; ?>"
                            data-book-title="<?php echo htmlspecialchars($rental['title']); ?>"
                            data-book-author="<?php echo htmlspecialchars($rental['author']); ?>"
                            data-book-image="<?php echo $rental['cover_image']; ?>"
                            data-due-date="<?php echo date('F j, Y', strtotime($rental['due_date'])); ?>"
                            data-is-overdue="<?php echo (strtotime($rental['due_date']) < time()) ? 'true' : 'false'; ?>"
                            data-seller-id="<?php echo $rental['seller_id']; ?>">
                                Return Book
                            </a>
                            <a href="rented_books.php?action=extend&rental_id=<?php echo $rental['rental_id']; ?>" class="order-action-btn btn-view-details">
                                Extend Rental
                            </a>
                        </div>
                    </div>
                    </div>
                    <?php endforeach; ?>
                
                <?php else: ?>
                    <!-- Empty state when no orders in the selected category -->
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3 class="mb-3"><?php echo $emptyMessage; ?></h3>
                        <p class="text-muted mb-4">Browse books to start shopping or check other order categories.</p>
                        <a href="rentbooks.php" class="btn btn-primary">Browse Books</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
              
    
    <div class="modal fade" id="returnBookModal" tabindex="-1" aria-labelledby="returnBookModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="returnBookModalLabel">Return Book</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_return.php" method="POST">
                <input type="hidden" name="rental_id" id="return_rental_id" value="">
                <input type="hidden" name="action" value="initiate_return">
                
                <div class="modal-body">
                    <div class="return-book-info mb-4">
                        <div class="d-flex align-items-start">
                            <img src="" id="return_book_image" alt="Book Cover" class="me-3" style="width: 80px; height: 120px; object-fit: cover;">
                            <div>
                                <h4 id="return_book_title"></h4>
                                <p class="text-muted" id="return_book_author"></p>
                                <p>Rental Due Date: <span id="return_due_date" class="fw-bold"></span></p>
                                <div id="overdue_notice" class="alert alert-danger d-none">
                                    <i class="fas fa-exclamation-triangle me-2"></i> This book is overdue!
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="return-steps">
                        <h5 class="mb-3">Select Return Method</h5>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="return_method" id="return_dropoff" value="dropoff" checked>
                            <label class="form-check-label" for="return_dropoff">
                                <strong>Drop-off</strong> - Return the book at one of our locations
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="return_method" id="return_pickup" value="pickup">
                            <label class="form-check-label" for="return_pickup">
                                <strong>Pickup</strong> - We'll pick up the book from your address (₱50 fee)
                            </label>
                        </div>
                        
                        <!-- Drop-off Locations -->
                        <div id="dropoff_locations" class="mb-4">
                            <h6 class="mt-4 mb-3">Select Drop-off Location</h6>
                            <div class="row" id="dropoff_locations_container">
                                <!-- Dropoff locations will be loaded here -->
                            </div>
                        </div>
                        
                        <!-- Pickup Schedule (hidden by default) -->
                        <div id="pickup_schedule" class="mb-4 d-none">
                            <h6 class="mt-4 mb-3">Schedule Pickup</h6>
                            
                            <div class="mb-3">
                                <label for="pickup_date" class="form-label">Pickup Date</label>
                                <input type="date" class="form-control" id="pickup_date" name="pickup_date" min="" required disabled>
                                <small class="text-muted">We need at least 1 day notice for pickup</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="pickup_time" class="form-label">Preferred Time Slot</label>
                                <select class="form-select" id="pickup_time" name="pickup_time" required disabled>
                                    <option value="">Select a time slot</option>
                                    <option value="morning">Morning (9AM - 12PM)</option>
                                    <option value="afternoon">Afternoon (1PM - 5PM)</option>
                                    <option value="evening">Evening (6PM - 8PM)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="pickup_address" class="form-label">Pickup Address</label>
                                <textarea class="form-control" id="pickup_address" name="pickup_address" rows="3" placeholder="Enter your complete pickup address" required disabled></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="pickup_notes" class="form-label">Additional Instructions (Optional)</label>
                                <textarea class="form-control" id="pickup_notes" name="pickup_notes" rows="2" placeholder="Gate code, landmark, etc." disabled></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> A pickup fee of ₱50 will be added to your account.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Book Condition Declaration</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="condition_checkbox" name="condition_confirmation" required>
                                <label class="form-check-label" for="condition_checkbox">
                                    I confirm that the book is in good condition with no significant damage beyond normal wear and tear.
                                </label>
                            </div>
                            <small class="text-muted">
                                Note: The book will be inspected when received. Additional charges may apply if the book is damaged.
                                See our <a href="#" data-bs-toggle="modal" data-bs-target="#damagePolicy">damage policy</a> for details.
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Return Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Damage Policy Modal -->
<div class="modal fade" id="damagePolicy" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Book Damage Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>Normal Wear and Tear (No Charge)</h6>
                <ul>
                    <li>Minor creases on the spine</li>
                    <li>Slight page yellowing</li>
                    <li>Minor dog-eared corners</li>
                    <li>Slight fading of cover</li>
                </ul>
                
                <h6>Minor Damage (25% of Book Value)</h6>
                <ul>
                    <li>Water stains (not affecting readability)</li>
                    <li>Torn pages (that don't affect content)</li>
                    <li>Writing/highlighting on fewer than 10 pages</li>
                    <li>Cover damage that doesn't affect the book's integrity</li>
                </ul>
                
                <h6>Significant Damage (50% of Book Value)</h6>
                <ul>
                    <li>Multiple pages with writing/highlighting</li>
                    <li>Broken spine that's still intact</li>
                    <li>Moderate water damage</li>
                    <li>Torn cover</li>
                </ul>
                
                <h6>Severe Damage (Full Book Value)</h6>
                <ul>
                    <li>Missing pages</li>
                    <li>Completely detached cover or spine</li>
                    <li>Extensive water damage affecting readability</li>
                    <li>Mold or pest damage</li>
                    <li>Book not returned within 30 days of due date</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Scroll to highlighted rental if present
            const highlightedRental = document.querySelector('.highlighted-rental');
            if (highlightedRental) {
                setTimeout(() => {
                    highlightedRental.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 500);
            }
            
            // Add event handler for purchase type radio buttons to ensure they're properly saved
            const purchaseTypeRadios = document.querySelectorAll('input[name="purchase_type"]');
            if (purchaseTypeRadios.length > 0) {
                purchaseTypeRadios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        // When purchase type is changed, submit the form immediately
                        if (this.closest('form')) {
                            this.closest('form').submit();
                        }
                    });
                });
            }
            
            // Helper to render default drop-off locations
            function renderDefaultDropoffLocations(container, sellerData) {
                let html = '';
                if (sellerData && sellerData.success && sellerData.address) {
                    // Compose the full address string
                    const sellerFullAddress = `${sellerData.address.name}, ${sellerData.address.address}, ${sellerData.address.city} ${sellerData.address.postal_code}`;
                    html += `
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="dropoff_location" id="seller_location" value="${sellerFullAddress.replace(/"/g, '&quot;')}" checked>
                                        <label class="form-check-label" for="seller_location">
                                            <strong>Seller's Location</strong><br>
                                            ${sellerData.address.name}<br>
                                            ${sellerData.address.address}<br>
                                            ${sellerData.address.city} ${sellerData.address.postal_code}<br>
                                            Contact: ${sellerData.address.contact_person}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="dropoff_location" id="location1" value="Main Office, 123 Book Street, Manila" ${(!sellerData || !sellerData.success) ? 'checked' : ''}>
                                    <label class="form-check-label" for="location1">
                                        <strong>Main Office</strong><br>
                                        123 Book Street, Manila<br>
                                        Mon-Fri: 9AM-6PM, Sat: 10AM-2PM
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="dropoff_location" id="location2" value="Downtown Branch, 456 Reading Ave, Quezon City">
                                    <label class="form-check-label" for="location2">
                                        <strong>Downtown Branch</strong><br>
                                        456 Reading Ave, Quezon City<br>
                                        Mon-Sun: 10AM-7PM
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.innerHTML = html;
            }

            // Get all Return Book buttons
            const returnButtons = document.querySelectorAll('.btn-return-book');
            
            // Add event listeners to Return Book buttons
            returnButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Get data from button attributes
                    const rentalId = this.getAttribute('data-rental-id');
                    const bookTitle = this.getAttribute('data-book-title');
                    const bookAuthor = this.getAttribute('data-book-author');
                    const bookImage = this.getAttribute('data-book-image');
                    const dueDate = this.getAttribute('data-due-date');
                    const isOverdue = this.getAttribute('data-is-overdue') === 'true';
                    const sellerId = this.getAttribute('data-seller-id');
                    
                    // Set values in the modal
                    document.getElementById('return_rental_id').value = rentalId;
                    document.getElementById('return_book_title').innerText = bookTitle;
                    document.getElementById('return_book_author').innerText = bookAuthor;
                    document.getElementById('return_book_image').src = bookImage;
                    document.getElementById('return_due_date').innerText = dueDate;
                    
                    // Show overdue notice if applicable
                    const overdueNotice = document.getElementById('overdue_notice');
                    if (isOverdue) {
                        overdueNotice.classList.remove('d-none');
                    } else {
                        overdueNotice.classList.add('d-none');
                    }
                    
                    // Fetch seller's address and update dropoff locations
                    const container = document.getElementById('dropoff_locations_container');
                    container.innerHTML = '<div class="text-center text-muted">Loading drop-off locations...</div>';

                    fetch(`get_seller_address.php?seller_id=${sellerId}`)
                        .then(response => response.json())
                        .then(data => {
                            renderDefaultDropoffLocations(container, data);
                        })
                        .catch(error => {
                            console.error('Error fetching seller address:', error);
                            renderDefaultDropoffLocations(container, null);
                        });
                });
            });
            
            // Toggle between Drop-off and Pickup sections
            const returnMethodRadios = document.querySelectorAll('input[name="return_method"]');
            const dropoffLocations = document.getElementById('dropoff_locations');
            const pickupSchedule = document.getElementById('pickup_schedule');
            const pickupFields = pickupSchedule.querySelectorAll('input, select, textarea');
                
            returnMethodRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'dropoff') {
                        dropoffLocations.classList.remove('d-none');
                        pickupSchedule.classList.add('d-none');
                        
                        // Disable pickup fields to prevent form submission
                        pickupFields.forEach(field => {
                            field.disabled = true;
                            field.required = false;
                        });
                    } else {
                        dropoffLocations.classList.add('d-none');
                        pickupSchedule.classList.remove('d-none');
                        
                        // Enable pickup fields
                        pickupFields.forEach(field => {
                            field.disabled = false;
                            if (field.id !== 'pickup_notes') { // Notes are optional
                                field.required = true;
                            }
                        });
                    }
                });
            });
            
            // Set minimum date for pickup
            const pickupDateField = document.getElementById('pickup_date');
            if (pickupDateField) {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                pickupDateField.min = tomorrow.toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>