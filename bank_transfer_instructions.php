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
               (SELECT SUM(oi.quantity * oi.unit_price)) AS total_amount
               FROM orders o
               WHERE o.order_id = ? AND o.user_id = ?";
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

// Get bank account details
$bankQuery = "SELECT * FROM bank_accounts WHERE is_active = 1 LIMIT 1";
$bankResult = $conn->query($bankQuery);
$bankAccount = $bankResult->fetch_assoc();

// Handle receipt upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_receipt'])) {
    $uploadDir = 'uploads/receipts/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileExtension = pathinfo($_FILES['payment_receipt']['name'], PATHINFO_EXTENSION);
    $newFilename = 'receipt_' . $orderId . '_' . time() . '.' . $fileExtension;
    $targetFile = $uploadDir . $newFilename;
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($_FILES['payment_receipt']['type'], $allowedTypes)) {
        $_SESSION['upload_error'] = "Invalid file type. Please upload an image or PDF file.";
        header("Location: bank_transfer_instructions.php?order_id=" . $orderId);
        exit();
    }
    
    // Upload file
    if (move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $targetFile)) {
        // Update order with receipt information
        $updateStmt = $conn->prepare("UPDATE orders SET payment_receipt = ?, payment_status = 'verification_pending', payment_date = NOW() WHERE order_id = ? AND user_id = ?");
        $updateStmt->bind_param("sii", $targetFile, $orderId, $userId);
        $updateStmt->execute();
        
        // Add entry to payment logs
        $logStmt = $conn->prepare("INSERT INTO payment_logs (order_id, user_id, action, status, amount, details) VALUES (?, ?, 'receipt_uploaded', 'pending', ?, 'Payment receipt uploaded, awaiting verification')");
        $logStmt->bind_param("iid", $orderId, $userId, $order['total_amount']);
        $logStmt->execute();
        
        // Set success message
        $_SESSION['upload_success'] = "Payment receipt uploaded successfully. Your payment is being verified.";
        
        // Redirect to order confirmation
        header("Location: order_confirmation.php?order_id=" . $orderId);
        exit();
    } else {
        $_SESSION['upload_error'] = "Error uploading file. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Transfer Instructions - BookWagon</title>
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
        .transfer-container {
            max-width: 800px;
            margin: 40px auto;
        }
        
        .transfer-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .transfer-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            overflow: hidden;
            padding: 30px;
            margin-bottom: 30px;
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
        
        .steps-container {
            margin-top: 20px;
        }
        
        .step {
            display: flex;
            margin-bottom: 30px;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .step-content {
            flex-grow: 1;
        }
        
        .bank-details {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
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
        
        .receipt-preview {
            width: 100%;
            max-height: 300px;
            border-radius: 5px;
            border: 2px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .receipt-preview img {
            max-width: 100%;
            max-height: 100%;
            display: none;
        }
        
        .receipt-upload-btn {
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
        
        .receipt-upload-btn:hover {
            background-color: #e69400;
            transform: translateY(-2px);
        }
        
        .copy-btn {
            background-color: #e9ecef;
            border: none;
            padding: 2px 10px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .copy-btn:hover {
            background-color: #ced4da;
        }
    </style>
</head>
<body>
    <!-- Include Header -->
    <?php include("include/user_header.php"); ?>

    <div class="container transfer-container">
        <div class="transfer-header">
            <h2>Bank Transfer Instructions</h2>
            <p>Order #<?php echo $orderId; ?></p>
        </div>
        
        <?php if (isset($_SESSION['upload_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['upload_error']; unset($_SESSION['upload_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="order-summary">
            <h4 class="mb-3">Order Summary</h4>
            <div class="order-summary-row">
                <span>Subtotal</span>
                <span>₱<?php echo number_format($order['total_amount'] * 0.9, 2); ?></span>
            </div>
            <div class="order-summary-row">
                <span>Tax (10%)</span>
                <span>₱<?php echo number_format($order['total_amount'] * 0.1, 2); ?></span>
            </div>
            <div class="order-summary-row">
                <span>Total</span>
                <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <div class="transfer-card">
            <h3 class="mb-4">Complete Your Payment</h3>
            
            <div class="steps-container">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h5>Transfer the exact amount to our bank account</h5>
                        <p>Please send exactly ₱<?php echo number_format($order['total_amount'], 2); ?> to the following account:</p>
                        
                        <div class="bank-details">
                            <div class="bank-account-row">
                                <div class="bank-account-label">Bank Name:</div>
                                <div class="bank-account-value">
                                    <?php echo htmlspecialchars($bankAccount['bank_name'] ?? 'BookWagon Bank'); ?>
                                    <button class="copy-btn" data-copy="<?php echo htmlspecialchars($bankAccount['bank_name'] ?? 'BookWagon Bank'); ?>">Copy</button>
                                </div>
                            </div>
                            <div class="bank-account-row">
                                <div class="bank-account-label">Account Name:</div>
                                <div class="bank-account-value">
                                    <?php echo htmlspecialchars($bankAccount['account_name'] ?? 'BookWagon, Inc.'); ?>
                                    <button class="copy-btn" data-copy="<?php echo htmlspecialchars($bankAccount['account_name'] ?? 'BookWagon, Inc.'); ?>">Copy</button>
                                </div>
                            </div>
                            <div class="bank-account-row">
                                <div class="bank-account-label">Account Number:</div>
                                <div class="bank-account-value">
                                    <?php echo htmlspecialchars($bankAccount['account_number'] ?? '1234-5678-9012-3456'); ?>
                                    <button class="copy-btn" data-copy="<?php echo htmlspecialchars($bankAccount['account_number'] ?? '1234-5678-9012-3456'); ?>">Copy</button>
                                </div>
                            </div>
                            <?php if (!empty($bankAccount['branch'])): ?>
                            <div class="bank-account-row">
                                <div class="bank-account-label">Branch:</div>
                                <div class="bank-account-value">
                                    <?php echo htmlspecialchars($bankAccount['branch']); ?>
                                    <button class="copy-btn" data-copy="<?php echo htmlspecialchars($bankAccount['branch']); ?>">Copy</button>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="bank-account-row">
                                <div class="bank-account-label">Reference:</div>
                                <div class="bank-account-value">
                                    Order #<?php echo $orderId; ?>
                                    <button class="copy-btn" data-copy="Order #<?php echo $orderId; ?>">Copy</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Make sure to include the Order # as your reference. This helps us identify your payment.
                        </div>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h5>Upload the payment receipt</h5>
                        <p>After completing your bank transfer, please upload a screenshot or photo of your payment receipt.</p>
                        
                        <form action="bank_transfer_instructions.php?order_id=<?php echo $orderId; ?>" method="post" enctype="multipart/form-data">
                            <div class="receipt-preview" id="receipt-preview">
                                <img id="preview-image" src="#" alt="Receipt preview">
                                <div id="preview-placeholder">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                    <p class="mt-2 text-muted">Receipt preview will appear here</p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="payment_receipt" class="form-label">Upload receipt (JPG, PNG, GIF, or PDF)</label>
                                <input class="form-control" type="file" id="payment_receipt" name="payment_receipt" accept="image/jpeg,image/png,image/gif,application/pdf" required>
                            </div>
                            
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Your order will be processed after we verify your payment, which typically takes 1-2 business days.
                            </div>
                            
                            <button type="submit" class="receipt-upload-btn">
                                <i class="fas fa-upload me-2"></i>Upload Receipt & Complete Order
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <a href="order_history.php" class="text-decoration-none">
                <i class="fas fa-arrow-left me-2"></i>Back to My Orders
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Preview uploaded image
            const receiptInput = document.getElementById('payment_receipt');
            const previewImage = document.getElementById('preview-image');
            const previewPlaceholder = document.getElementById('preview-placeholder');
            
            receiptInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        // Only show image preview for image files
                        if (file.type.startsWith('image/')) {
                            previewImage.src = e.target.result;
                            previewImage.style.display = 'block';
                            previewPlaceholder.style.display = 'none';
                        } else {
                            // For PDFs or other files, show icon
                            previewImage.style.display = 'none';
                            previewPlaceholder.innerHTML = `
                                <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                <p class="mt-2 text-muted">${file.name}</p>
                            `;
                        }
                    }
                    
                    reader.readAsDataURL(file);
                }
            });
            
            // Copy to clipboard functionality
            const copyButtons = document.querySelectorAll('.copy-btn');
            
            copyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const textToCopy = this.getAttribute('data-copy');
                    navigator.clipboard.writeText(textToCopy).then(() => {
                        // Change button text temporarily
                        const originalText = this.textContent;
                        this.textContent = 'Copied!';
                        
                        setTimeout(() => {
                            this.textContent = originalText;
                        }, 2000);
                    });
                });
            });
        });
    </script>
</body>
</html>