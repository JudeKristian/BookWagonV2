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

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $orderId = $_POST['order_id'] ?? 0;
    $orderItemId = $_POST['order_item_id'] ?? 0;
    
    if ($action === 'update_status' && $orderId && isset($_POST['new_status'])) {
        $newStatus = $_POST['new_status'];
        
        // First check if this seller has items in this order
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) as item_count 
            FROM order_items 
            WHERE order_id = ? AND seller_id = ?
        ");
        $checkStmt->bind_param("ii", $orderId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $itemCount = $checkResult->fetch_assoc()['item_count'];
        
        if ($itemCount > 0) {
            // Update the status for all items by this seller in this order
            $updateStmt = $conn->prepare("
                UPDATE order_items 
                SET status = ? 
                WHERE order_id = ? AND seller_id = ?
            ");
            $updateStmt->bind_param("sii", $newStatus, $orderId, $userId);
            
            if ($updateStmt->execute()) {
                // Log the status change
                $logStmt = $conn->prepare("
                    INSERT INTO payment_logs (order_id, user_id, action, status, details) 
                    VALUES (?, ?, 'status_update', 'success', ?)
                ");
                $details = "Order status updated to: " . $newStatus;
                $logStmt->bind_param("iis", $orderId, $userId, $details);
                $logStmt->execute();
                
                // Check if all items in the order are in the same status
                $allItemsStmt = $conn->prepare("
                    SELECT COUNT(DISTINCT status) as status_count 
                    FROM order_items 
                    WHERE order_id = ?
                ");
                $allItemsStmt->bind_param("i", $orderId);
                $allItemsStmt->execute();
                $statusCount = $allItemsStmt->get_result()->fetch_assoc()['status_count'];
                
                // If all items have the same status, update the main order status
                if ($statusCount == 1) {
                    $orderUpdateStmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                    $orderUpdateStmt->bind_param("si", $newStatus, $orderId);
                    $orderUpdateStmt->execute();
                }
                
                $_SESSION['success_message'] = "Order status updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update order status.";
            }
        } else {
            $_SESSION['error_message'] = "You don't have permission to update this order.";
        }
    } elseif ($action === 'update_item_status' && $orderItemId && isset($_POST['new_status'])) {
        $newStatus = $_POST['new_status'];
        
        // First check if this item belongs to this seller
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) as item_count 
            FROM order_items 
            WHERE item_id = ? AND seller_id = ?
        ");
        $checkStmt->bind_param("ii", $orderItemId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $itemCount = $checkResult->fetch_assoc()['item_count'];
        
        if ($itemCount > 0) {
            // Update the status for this specific item
            $updateStmt = $conn->prepare("UPDATE order_items SET status = ? WHERE item_id = ?");
            $updateStmt->bind_param("si", $newStatus, $orderItemId);
            
            if ($updateStmt->execute()) {
                // Get the order_id for this item
                $orderIdStmt = $conn->prepare("SELECT order_id FROM order_items WHERE item_id = ?");
                $orderIdStmt->bind_param("i", $orderItemId);
                $orderIdStmt->execute();
                $orderId = $orderIdStmt->get_result()->fetch_assoc()['order_id'];
                
                // Log the status change
                $logStmt = $conn->prepare("
                    INSERT INTO payment_logs (order_id, user_id, action, status, details) 
                    VALUES (?, ?, 'item_status_update', 'success', ?)
                ");
                $details = "Order item #$orderItemId status updated to: " . $newStatus;
                $logStmt->bind_param("iis", $orderId, $userId, $details);
                $logStmt->execute();
                
                $_SESSION['success_message'] = "Item status updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update item status.";
            }
        } else {
            $_SESSION['error_message'] = "You don't have permission to update this item.";
        }
    } elseif ($action === 'confirm_payment' && isset($_POST['order_id'])) {
        $orderId = $_POST['order_id'];
        
        // First check if this seller has items in this order
        $checkStmt = $conn->prepare("
            SELECT o.payment_method, o.payment_status, COUNT(oi.item_id) as item_count 
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.order_id = ? AND oi.seller_id = ?
        ");
        $checkStmt->bind_param("ii", $orderId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $orderData = $checkResult->fetch_assoc();
        
        if ($orderData && $orderData['item_count'] > 0) {
            // Check if this is a COD or pickup order
            $paymentMethod = $orderData['payment_method'];
            $currentPaymentStatus = $orderData['payment_status'];
            
            if (($paymentMethod == 'cod' || $paymentMethod == 'pickup') && $currentPaymentStatus != 'paid') {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Update payment status to paid
                    $updateStmt = $conn->prepare("UPDATE orders SET payment_status = 'paid', payment_date = NOW() WHERE order_id = ?");
                    $updateStmt->bind_param("i", $orderId);
                    $updateStmt->execute();
                    
                    // Update all items for this seller to shipped_pending_confirmation
                    $updateItemsStmt = $conn->prepare("
                        UPDATE order_items 
                        SET status = 'shipped_pending_confirmation' 
                        WHERE order_id = ? AND seller_id = ?
                    ");
                    $updateItemsStmt->bind_param("ii", $orderId, $userId);
                    $updateItemsStmt->execute();
                    
                    // Log the payment confirmation
                    $logStmt = $conn->prepare("
                        INSERT INTO payment_logs (order_id, user_id, action, status, details) 
                        VALUES (?, ?, 'payment_confirmation', 'success', ?)
                    ");
                    $details = "Cash payment confirmed for " . ($paymentMethod == 'cod' ? 'Cash on Delivery' : 'Pickup/Meetup') . " order";
                    $logStmt->bind_param("iis", $orderId, $userId, $details);
                    $logStmt->execute();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $_SESSION['success_message'] = "Payment confirmed successfully. Items are now pending customer confirmation.";
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $_SESSION['error_message'] = "Failed to confirm payment: " . $e->getMessage();
                }
            } else {
                if ($currentPaymentStatus == 'paid') {
                    $_SESSION['error_message'] = "Payment has already been confirmed.";
                } else {
                    $_SESSION['error_message'] = "Payment confirmation is only available for Cash on Delivery or Pickup/Meetup orders.";
                }
            }
        } else {
            $_SESSION['error_message'] = "You don't have permission to update this order.";
        }
    }
    // NEW CODE: Add mark_as_shipped action
    elseif ($action === 'mark_as_shipped' && isset($_POST['item_id'])) {
        $itemId = $_POST['item_id'];
        
        // First check if this item belongs to this seller
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) as item_count 
            FROM order_items 
            WHERE item_id = ? AND seller_id = ?
        ");
        $checkStmt->bind_param("ii", $itemId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $itemCount = $checkResult->fetch_assoc()['item_count'];
        
        if ($itemCount > 0) {
            // Update the status for this specific item to shipped
            $updateStmt = $conn->prepare("UPDATE order_items SET status = 'shipped' WHERE item_id = ?");
            $updateStmt->bind_param("i", $itemId);
            
            if ($updateStmt->execute()) {
                // Get the order_id for this item
                $orderIdStmt = $conn->prepare("SELECT order_id FROM order_items WHERE item_id = ?");
                $orderIdStmt->bind_param("i", $itemId);
                $orderIdStmt->execute();
                $orderId = $orderIdStmt->get_result()->fetch_assoc()['order_id'];
                
                // Log the status change
                $logStmt = $conn->prepare("
                    INSERT INTO payment_logs (order_id, user_id, action, status, details) 
                    VALUES (?, ?, 'item_shipped', 'success', ?)
                ");
                $details = "Item #$itemId marked as shipped";
                $logStmt->bind_param("iis", $orderId, $userId, $details);
                $logStmt->execute();
                
                $_SESSION['success_message'] = "Item has been marked as shipped. It will now appear in customer's 'To Receive' section.";
            } else {
                $_SESSION['error_message'] = "Failed to update item status.";
            }
        } else {
            $_SESSION['error_message'] = "You don't have permission to update this item.";
        }
    }
    // NEW CODE: Add mark_as_delivered action

    elseif ($action === 'mark_as_delivered' && isset($_POST['item_id'])) {
        $itemId = $_POST['item_id'];
        
        // First check if this item belongs to this seller
        $checkStmt = $conn->prepare("
            SELECT 
                COUNT(*) as item_count,
                oi.purchase_type,
                oi.book_id,
                oi.rental_weeks,
                oi.unit_price,
                o.order_id,
                b.title as book_title,
                b.author as book_author,
                o.payment_method,
                o.payment_status
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN books b ON oi.book_id = b.book_id
            WHERE oi.item_id = ? AND oi.seller_id = ?
        ");
        $checkStmt->bind_param("ii", $itemId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $itemData = $checkResult->fetch_assoc();
        
        // Add a safety check in case no data is found
        if (!$itemData || $itemData['item_count'] == 0) {
            $_SESSION['error_message'] = "Invalid item or permission denied.";
            header("Location: order.php");
            exit();
        }
        
        // For COD orders, also mark payment as paid when delivered
        if ($itemData['payment_method'] == 'cod' && $itemData['payment_status'] != 'paid') {
            $updatePaymentStmt = $conn->prepare("
                UPDATE orders SET payment_status = 'paid', payment_date = NOW() 
                WHERE order_id = ?
            ");
            $updatePaymentStmt->bind_param("i", $itemData['order_id']);
            $updatePaymentStmt->execute();
            
            // Log the payment update
            $logPaymentStmt = $conn->prepare("
                INSERT INTO payment_logs (order_id, user_id, action, status, details) 
                VALUES (?, ?, 'payment_received', 'success', ?)
            ");
            $paymentDetails = "Payment received for COD order upon delivery";
            $logPaymentStmt->bind_param("iis", $itemData['order_id'], $userId, $paymentDetails);
            $logPaymentStmt->execute();
        }
        
        // Update the status for this specific item to shipped_pending_confirmation
        // Customer needs to confirm receipt before it's marked as delivered
        $updateStmt = $conn->prepare("UPDATE order_items SET status = 'shipped_pending_confirmation' WHERE item_id = ?");
        $updateStmt->bind_param("i", $itemId);
        
        if (!$updateStmt->execute()) {
            $_SESSION['error_message'] = "Failed to update item status.";
            header("Location: order.php");
            exit();
        }
        
        // Log the status change
        $logStmt = $conn->prepare("
            INSERT INTO payment_logs (order_id, user_id, action, status, details) 
            VALUES (?, ?, 'pending_confirmation', 'success', ?)
        ");
        $details = "Item #$itemId marked for delivery, waiting for customer confirmation";
        $logStmt->bind_param("iis", $itemData['order_id'], $userId, $details);
        $logStmt->execute();
        
        // If it's a rental item, prepare for rental process
        if ($itemData['purchase_type'] == 'rent') {
            // Create a rental record
            $createRentalStmt = $conn->prepare("
            INSERT INTO book_rentals (
                user_id, 
                book_id, 
                seller_id, 
                rental_date, 
                due_date, 
                rental_weeks, 
                status, 
                total_price,
                order_id
            ) VALUES (
                (SELECT user_id FROM orders WHERE order_id = ?), 
                ?, 
                ?, 
                NOW(), 
                DATE_ADD(NOW(), INTERVAL ? WEEK), 
                ?, 
                'active', 
                ?, 
                ?
            )
        ");
            $createRentalStmt->bind_param(
                "iiiiidi", 
                $itemData['order_id'], 
                $itemData['book_id'], 
                $userId, 
                $itemData['rental_weeks'], 
                $itemData['rental_weeks'], 
                $itemData['unit_price'], 
                $itemData['order_id']
            );
            
            if ($createRentalStmt->execute()) {
                // Redirect to renter.php with rental details
                $_SESSION['rental_item_id'] = $itemId;
                $_SESSION['rental_book_title'] = $itemData['book_title'];
                $_SESSION['rental_book_author'] = $itemData['book_author'];
                $_SESSION['rental_weeks'] = $itemData['rental_weeks'];
                
                $_SESSION['success_message'] = "Item has been marked as delivered. Redirecting to rental details.";
                header("Location: renter.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Failed to create rental record.";
                header("Location: order.php");
                exit();
            }
        } else {
            $_SESSION['success_message'] = "Item has been marked as delivered. Waiting for customer to confirm receipt.";
            header("Location: order.php");
            exit();
        }
    }
}


// Handle filtering
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Build the SQL query with filters
$whereClause = "oi.seller_id = ?";

if ($status_filter != 'all') {
    $whereClause .= " AND oi.status = ?";
}

if ($date_filter != 'all') {
    switch ($date_filter) {
        case 'today':
            $whereClause .= " AND DATE(o.order_date) = CURDATE()";
            break;
        case 'yesterday':
            $whereClause .= " AND DATE(o.order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'last7days':
            $whereClause .= " AND o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'last30days':
            $whereClause .= " AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

if (!empty($search_query)) {
    $whereClause .= " AND (o.order_id LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ? OR b.title LIKE ?)";
}

// Fetch orders for this seller
$query = "
    SELECT 
        o.order_id, 
        o.order_date, 
        o.payment_method, 
        o.payment_status,
        o.payment_date,
        o.order_status as main_order_status,
        u.firstname, 
        u.lastname, 
        u.email,
        u.phone,
        oi.item_id,
        oi.book_id,
        oi.quantity,
        oi.purchase_type,
        oi.rental_weeks,
        oi.unit_price,
        oi.status as item_status,
        b.title as book_title,
        b.author as book_author,
        b.ISBN,
        b.cover_image,
        b.price as book_price,
        b.rent_price as book_rent_price
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN users u ON o.user_id = u.id
    JOIN books b ON oi.book_id = b.book_id
    WHERE $whereClause
    ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($query);

// Bind parameters based on filters
if ($status_filter != 'all' && !empty($search_query)) {
    $searchParam = "%$search_query%";
    $stmt->bind_param("issssss", $userId, $status_filter, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
} elseif ($status_filter != 'all') {
    $stmt->bind_param("is", $userId, $status_filter);
} elseif (!empty($search_query)) {
    $searchParam = "%$search_query%";
    $stmt->bind_param("isssss", $userId, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
} else {
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();
$orderItems = $result->fetch_all(MYSQLI_ASSOC);

// Group order items by order_id
$orders = [];
foreach ($orderItems as $item) {
    $orderId = $item['order_id'];
    if (!isset($orders[$orderId])) {
        $orders[$orderId] = [
            'order_id' => $orderId,
            'order_date' => $item['order_date'],
            'payment_method' => $item['payment_method'],
            'payment_status' => $item['payment_status'],
            'payment_date' => $item['payment_date'],
            'main_order_status' => $item['main_order_status'],
            'customer' => [
                'name' => $item['firstname'] . ' ' . $item['lastname'],
                'email' => $item['email'],
                'phone' => $item['phone']
            ],
            'items' => [],
            'total' => 0
        ];
    }
    
    // Ensure item quantity is valid (at least 1)
    $itemQuantity = (isset($item['quantity']) && $item['quantity'] > 0) ? $item['quantity'] : 1;
    
    // Calculate item total
    $itemTotal = $item['unit_price'] * $itemQuantity;

    // Determine purchase type if empty
    $purchaseType = $item['purchase_type'];
    if (empty($purchaseType)) {
        // If rental_weeks > 0 and unit price approximately matches rent_price * rental_weeks, it's a rental
        if ($item['rental_weeks'] > 0 && 
            abs($item['unit_price'] - ($item['book_rent_price'] * $item['rental_weeks'])) < 5) {
            $purchaseType = 'rent';
        }
        // If unit price is significantly less than book price, it's likely a rental
        else if ($item['unit_price'] < ($item['book_price'] * 0.9)) {
            $purchaseType = 'rent';
        }
        // If unit price is close to book price, it's a purchase
        else if (abs($item['unit_price'] - $item['book_price']) < ($item['book_price'] * 0.1)) {
            $purchaseType = 'buy';
        }
        // Default: if has rental weeks, assume rental
        else if ($item['rental_weeks'] > 0) {
            $purchaseType = 'rent';
        }
        // Otherwise, assume it's a buy
        else {
            $purchaseType = 'buy';
        }
    }
    
    // Add item details
    $orders[$orderId]['items'][] = [
        'item_id' => $item['item_id'],
        'book_id' => $item['book_id'],
        'book_title' => $item['book_title'],
        'book_author' => $item['book_author'],
        'isbn' => $item['ISBN'],
        'cover_image' => $item['cover_image'],
        'quantity' => $itemQuantity,
        'purchase_type' => $purchaseType,
        'rental_weeks' => $item['rental_weeks'],
        'unit_price' => $item['unit_price'],
        'book_price' => $item['book_price'],
        'book_rent_price' => $item['book_rent_price'],
        'item_total' => $itemTotal,
        'status' => $item['item_status']
    ];
    
    // Add to order total
    $orders[$orderId]['total'] += $itemTotal;
}

// Determine summary statistics
$totalOrders = count($orders);
$totalItems = count($orderItems);
$totalRevenue = 0;
$pendingOrders = 0;
$shippedOrders = 0;
$completedOrders = 0;

foreach ($orders as $order) {
    $totalRevenue += $order['total'];
    
    // Count order statuses
    $allItemsStatus = array_column($order['items'], 'status');
    if (in_array('pending', $allItemsStatus)) {
        $pendingOrders++;
    } elseif (in_array('shipped', $allItemsStatus)) {
        $shippedOrders++;
    } elseif (count(array_unique($allItemsStatus)) === 1 && $allItemsStatus[0] === 'delivered') {
        $completedOrders++;
    }
}

foreach ($orders as $orderId => &$orderData) {
    if ($orderData['total'] <= 0) {
        // Try to get total from orders table
        $totalQuery = "SELECT total_amount FROM orders WHERE order_id = ?";
        $totalStmt = $conn->prepare($totalQuery);
        $totalStmt->bind_param("i", $orderId);
        $totalStmt->execute();
        $totalResult = $totalStmt->get_result();
        
        if ($totalResult && $totalResult->num_rows > 0) {
            $dbTotal = $totalResult->fetch_assoc()['total_amount'];
            if ($dbTotal > 0) {
                $orderData['total'] = $dbTotal;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - BookWagon</title>
    
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
        
        /* Stats Cards Enhancements */
        .stats-card {
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
            margin-bottom: 24px;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }
        
        .stats-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
            z-index: 2;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .stats-details {
            display: flex;
            flex-direction: column;
            z-index: 2;
        }
        
        .stats-title {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 4px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stats-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 0;
            color: #1e293b;
            line-height: 1.2;
        }
        
        /* Card Styling */
        .card {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            border-radius: 12px;
            margin-bottom: 24px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 18px 24px;
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
            font-size: 16px;
            color: #1e293b;
        }
        
        .card-body {
            padding: 24px;
        }
        
        /* Filter Container Styling */
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }
        
        .filter-item {
            flex: 1;
            min-width: 200px;
        }
        
        .form-label {
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
            font-size: 14px;
            background-color: #f8fafc;
            color: #334155;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(248, 161, 0, 0.15);
            border-color: #f8a100;
            background-color: #ffffff;
        }
        
        .input-group .btn {
            border-radius: 0 8px 8px 0;
            padding: 10px 20px;
            background-color: #f1f5f9;
            border-color: #e2e8f0;
            color: #64748b;
        }
        
        .input-group .btn:hover {
            background-color: #e2e8f0;
            color: #0f172a;
        }
        
        .input-group .form-control {
            border-radius: 8px 0 0 8px;
        }
        
        /* Table Styles */
        .table-responsive {
            background-color: white;
            border-radius: 0 0 12px 12px;
        }
        
        .table thead th {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            padding: 16px 24px;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 12px;
            letter-spacing: 0.3px;
        }
        
        .badge-pending { background-color: #fef3c7; color: #b45309; }
        .badge-shipped { background-color: #e0e7ff; color: #4338ca; }
        .badge-delivered { background-color: #d1fae5; color: #047857; }
        .badge-processing { background-color: #e0f2fe; color: #0369a1; }
        .badge-cancelled { background-color: #fee2e2; color: #b91c1c; }
        
        .book-thumbnail {
            width: 45px;
            height: 65px;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* Empty State */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }
        
        .empty-state-icon {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 24px;
        }
        
        .empty-state h4 {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #64748b;
            margin-bottom: 24px;
        }
        
        .btn-primary {
            background-color: #f8a100;
            border-color: #f8a100;
            font-weight: 500;
            padding: 10px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(248, 161, 0, 0.2);
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: #d97706;
            border-color: #d97706;
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(248, 161, 0, 0.25);
        }
        
        .btn-confirm-payment {
            background-color: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-confirm-payment:hover {
            background-color: #059669;
            color: white;
            transform: translateY(-1px);
        }
        
        .payment-info {
            display: flex;
            flex-direction: column;
        }
        
        .payment-date {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 4px;
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
                
                <h2 class="mb-4">Manage Orders</h2>
                
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon" style="background-color: #4f46e5;">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div class="stats-details">
                                <div class="stats-title">Total Orders</div>
                                <div class="stats-value"><?php echo $totalOrders; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon" style="background-color: #f59e0b;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stats-details">
                                <div class="stats-title">Pending Orders</div>
                                <div class="stats-value"><?php echo $pendingOrders; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon" style="background-color: #10b981;">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="stats-details">
                                <div class="stats-title">Shipped Orders</div>
                                <div class="stats-value"><?php echo $shippedOrders; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-icon" style="background-color: #9333ea;">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="stats-details">
                                <div class="stats-title">Total Revenue</div>
                                <div class="stats-value">₱<?php echo number_format($totalRevenue, 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card">
                    <div class="card-body">
                        <form action="order.php" method="GET" class="mb-0">
                            <div class="filter-container">
                                <div class="filter-item">
                                    <label for="status" class="form-label">Status</label>
                                    <select name="status" id="status" class="form-select custom-select">
                                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="shipped_pending_confirmation" <?php echo $status_filter == 'shipped_pending_confirmation' ? 'selected' : ''; ?>>Awaiting Confirmation</option>
                                        <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="filter-item">
                                    <label for="date" class="form-label">Date Range</label>
                                    <select name="date" id="date" class="form-select custom-select">
                                        <option value="all" <?php echo $date_filter == 'all' ? 'selected' : ''; ?>>All Time</option>
                                        <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                                        <option value="yesterday" <?php echo $date_filter == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                        <option value="last7days" <?php echo $date_filter == 'last7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                                        <option value="last30days" <?php echo $date_filter == 'last30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                    </select>
                                </div>
                                <div class="filter-item">
                                    <label for="search" class="form-label">Search</label>
                                    <div class="input-group">
                                        <input type="text" name="search" id="search" class="form-control" placeholder="Order ID, Customer, Book..." value="<?php echo htmlspecialchars($search_query); ?>">
                                        <button type="submit" class="btn btn-outline-secondary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Orders List -->
                <?php if (empty($orders)): ?>
                <div class="card">
                    <div class="card-body empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h4>No Orders Found</h4>
                        <p class="text-muted">No orders match your current filter settings.</p>
                        <a href="order.php" class="btn btn-primary">Clear Filters</a>
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">Order #<?php echo $order['order_id']; ?></h5>
                                <small class="text-muted">
                                    Placed on <?php echo date('M j, Y, g:i a', strtotime($order['order_date'])); ?>
                                </small>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="me-3 payment-info">
                                    <span class="fw-bold">Payment:</span>
                                    <span class="status-badge badge-<?php echo $order['payment_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['payment_status'])); ?>
                                    </span>
                                    <?php if ($order['payment_status'] == 'paid' && $order['payment_date']): ?>
                                    <span class="payment-date">
                                        Paid on <?php echo date('M j, Y', strtotime($order['payment_date'])); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex align-items-center">
                                    <?php 
                                    // Show confirm payment button for COD or Pickup orders that aren't paid yet
                                    if (($order['payment_method'] == 'cod' || $order['payment_method'] == 'pickup') && $order['payment_status'] != 'paid'): 
                                    ?>
                                    <form action="order.php" method="POST" class="me-2">
                                        <input type="hidden" name="action" value="confirm_payment">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-confirm-payment" onclick="return confirm('Confirm that the customer has paid in cash?')">
                                            <i class="fas fa-money-bill-wave me-1"></i> Confirm Payment
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <form action="order.php" method="POST" class="d-flex align-items-center">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <select name="new_status" class="form-select form-select-sm me-2" style="width: 150px;">
                                            <option value="">Update Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="processing">Processing</option>
                                            <option value="shipped">Shipped</option>
                                            <option value="delivered">Delivered</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-body">
                            <!-- Customer Info -->
                            <div class="order-item">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="mb-2">Customer Information</h6>
                                        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($order['customer']['name']); ?></p>
                                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer']['email']); ?></p>
                                        <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer']['phone']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-2">Payment Information</h6>
                                        <p class="mb-1"><strong>Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></p>
                                        <p class="mb-1"><strong>Status:</strong> 
                                            <span class="status-badge badge-<?php echo $order['payment_status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['payment_status'])); ?>
                                            </span>
                                        </p>
                                        <p class="mb-0"><strong>Total:</strong> ₱<?php echo number_format($order['total'], 2); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Order Items -->
                            <?php foreach ($order['items'] as $item): ?>
                            <div class="order-item">
                                <div class="row align-items-center">
                                    <div class="col-md-7">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo !empty($item['cover_image']) ? $item['cover_image'] : 'img/default-book-cover.jpg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['book_title']); ?>" 
                                                 class="item-image">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['book_title']); ?></h6>
                                                <p class="mb-1 text-muted">By <?php echo htmlspecialchars($item['book_author']); ?></p>
                                                <p class="mb-0 small text-muted">
                                                    <?php if ($item['purchase_type'] == 'rent'): ?>
                                                        Rental: <?php echo $item['rental_weeks']; ?> week<?php echo $item['rental_weeks'] > 1 ? 's' : ''; ?>
                                                    <?php else: ?>
                                                        Purchase
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <p class="mb-1">₱<?php echo number_format($item['unit_price'], 2); ?></p>
                                            <p class="mb-0 text-muted">Qty: <?php echo $item['quantity']; ?></p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex justify-content-end align-items-center">
                                            <span class="status-badge badge-<?php echo $item['status']; ?> me-2">
                                                <?php 
                                                if ($item['status'] == 'shipped_pending_confirmation') {
                                                    echo "Awaiting Confirmation";
                                                } else {
                                                    echo ucfirst($item['status']); 
                                                }
                                                ?>
                                            </span>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <?php if ($item['status'] == 'pending' || $item['status'] == 'processing'): ?>
                                                    <li>
                                                        <form action="order.php" method="POST" class="dropdown-item">
                                                            <input type="hidden" name="action" value="mark_as_shipped">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                            <button type="submit" class="btn btn-link p-0">Mark as Shipped</button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($item['status'] == 'shipped'): ?>
                                                    <li>
                                                        <form action="order.php" method="POST" class="dropdown-item">
                                                            <input type="hidden" name="action" value="mark_as_delivered">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                            <button type="submit" class="btn btn-link p-0">Mark as Delivered</button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($item['status'] != 'delivered' && $item['status'] != 'cancelled' && $item['status'] != 'shipped_pending_confirmation'): ?>
                                                    <li>
                                                        <form action="order.php" method="POST" class="dropdown-item">
                                                            <input type="hidden" name="action" value="update_item_status">
                                                            <input type="hidden" name="order_item_id" value="<?php echo $item['item_id']; ?>">
                                                            <input type="hidden" name="new_status" value="processing">
                                                            <button type="submit" class="btn btn-link p-0">Mark as Processing</button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                    
                                                    <li><hr class="dropdown-divider"></li>
                                                    
                                                    <?php if ($item['status'] != 'delivered' && $item['status'] != 'cancelled'): ?>
                                                    <li>
                                                        <form action="order.php" method="POST" class="dropdown-item">
                                                            <input type="hidden" name="action" value="update_item_status">
                                                            <input type="hidden" name="order_item_id" value="<?php echo $item['item_id']; ?>">
                                                            <input type="hidden" name="new_status" value="cancelled">
                                                            <button type="submit" class="btn btn-link p-0 text-danger">Cancel Item</button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-footer d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold">Payment Method:</span> 
                                <?php 
                                $paymentMethodDisplay = "";
                                switch($order['payment_method']) {
                                    case 'cod':
                                        $paymentMethodDisplay = "Cash on Delivery";
                                        break;
                                    case 'pickup':
                                        $paymentMethodDisplay = "Pickup/Meetup";
                                        break;
                                    case 'bank_transfer':
                                        $paymentMethodDisplay = "Bank Transfer";
                                        break;
                                    default:
                                        $paymentMethodDisplay = ucfirst($order['payment_method']);
                                }
                                echo $paymentMethodDisplay;
                                ?>
                            </div>
                            <div>
                                <span class="fw-bold">Total:</span> ₱<?php echo number_format($order['total'], 2); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Print Button at the bottom of the page -->
                <div class="text-end my-4">
                    <button onclick="window.print();" class="btn btn-outline-secondary">
                        <i class="fas fa-print me-2"></i>Print Orders
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form when filters change
            const filterSelects = document.querySelectorAll('#status, #date');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });
            
            // Print specific order
            window.printOrder = function(orderId) {
                const orderCard = document.querySelector(`.order-card[data-order-id="${orderId}"]`);
                if (orderCard) {
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write(`
                        <html>
                            <head>
                                <title>Order #${orderId} - BookWagon</title>
                                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                                <style>
                                    @media print {
                                        body {
                                            font-size: 14px;
                                        }
                                        .order-card {
                                            border: 1px solid #dee2e6;
                                            margin-bottom: 20px;
                                        }
                                        .order-header {
                                            background-color: #f8f9fa;
                                            padding: 15px;
                                            border-bottom: 1px solid #dee2e6;
                                        }
                                        .order-item {
                                            padding: 15px;
                                            border-bottom: 1px solid #dee2e6;
                                        }
                                        .order-footer {
                                            padding: 15px;
                                            border-top: 1px solid #dee2e6;
                                            background-color: #f8f9fa;
                                            font-weight: bold;
                                        }
                                        .status-badge {
                                            padding: 3px 8px;
                                            border-radius: 3px;
                                            font-size: 12px;
                                            text-transform: uppercase;
                                        }
                                    }
                                </style>
                            </head>
                            <body>
                                <div class="container my-4">
                                    <h1 class="text-center mb-4">BookWagon Order Receipt</h1>
                                    ${orderCard.outerHTML}
                                </div>
                            </body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.focus();
                    setTimeout(() => {
                        printWindow.print();
                        printWindow.close();
                    }, 500);
                }
            };
        });
    </script>
</body>
</html>