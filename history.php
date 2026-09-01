<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? '';
$userId = $_SESSION['id'] ?? 0;

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Fetch return requests and rental history
$returnsQuery = "
    SELECT 
        br.return_id,
        br.rental_id,
        br.return_method,
        br.return_details,
        br.status,
        br.request_date,
        br.received_date,
        br.completed_date,
        br.is_overdue,
        br.days_overdue,
        br.late_fee,
        br.additional_fee,
        br.damage_fee,
        br.damage_description,
        br.book_condition,
        br.notes,
        b.title as book_title,
        b.author as book_author,
        b.cover_image,
        r.rental_weeks,
        r.total_price
    FROM book_returns br
    JOIN book_rentals r ON br.rental_id = r.rental_id
    JOIN books b ON br.book_id = b.book_id
    WHERE br.user_id = ?
    ORDER BY br.request_date DESC
";

$returnsStmt = $conn->prepare($returnsQuery);
$returnsStmt->bind_param("i", $userId);
$returnsStmt->execute();
$returnsResult = $returnsStmt->get_result();
$returns = $returnsResult->fetch_all(MYSQLI_ASSOC);

// Fetch completed orders and rentals
$historyQuery = "
    SELECT 
        CASE 
            WHEN oi.purchase_type = 'rent' THEN 'rental'
            ELSE 'purchase'
        END as type,
        o.order_id as id,
        o.order_date as date,
        NULL as return_date,
        b.title,
        b.author,
        b.cover_image,
        oi.unit_price as total_price,
        oi.rental_weeks,
        o.order_status as status
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN books b ON oi.book_id = b.book_id
    WHERE o.user_id = ? AND o.order_status IN ('completed', 'cancelled', 'delivered')
    ORDER BY date DESC
";

$historyStmt = $conn->prepare($historyQuery);
$historyStmt->bind_param("i", $userId);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
$history = $historyResult->fetch_all(MYSQLI_ASSOC);

// Determine active tab
$activeTab = $_GET['tab'] ?? 'all';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order & Rental History - BookWagon</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Similar styles to rented_books.php */
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

        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .navbar-brand img {
            height: 60px;
        }

        .sidebar {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px 0;
            min-height: calc(100vh - 150px);
            position: sticky;
            top: 20px;
        }

        .sidebar-link {
            display: block;
            padding: 12px 20px;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background-color: rgba(0, 123, 255, 0.05);
            color: #4a6cf7;
            border-left: 3px solid #4a6cf7;
        }

        .sidebar-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }

        .history-card {
            background-color: white;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }

        .history-body {
            display: flex;
            padding: 15px;
        }

        .history-image {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-returned {
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

        .status-pending {
            background-color: #fff3e0;
            color: #ff9800;
        }

        .status-in-transit {
            background-color: #e3f2fd;
            color: #2196f3;
        }

        .empty-state {
            text-align: center;
            padding: 60px 0;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .history-tabs {
            display: flex;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .history-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            color: #6c757d;
            text-decoration: none;
            position: relative;
        }

        .history-tab.active {
            color: #f8a100;
        }

        .history-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #f8a100;
        }
    </style>
</head>

<body>
    <?php include("include/user_header.php"); ?>

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
                    <a href="rented_books.php" class="sidebar-link">
                        <i class="fa-solid fa-book"></i> Rented Books
                    </a>

                    <a href="collections.php" class="sidebar-link">
                        <i class="fa-solid fa-bookmark"></i> My Collections
                    </a>

                    <a href="history.php" class="sidebar-link active">
                        <i class="fa-solid fa-clock-rotate-left"></i> Order History
                    </a>
                    <a href="security.php" class="sidebar-link">
                        <i class="fa-solid fa-shield-halved"></i> Security Settings
                    </a>
                </div>
            </div>

            <!-- Main Content Column -->
            <div class="col-md-9">
                <h2 class="mb-4">Order & Rental History</h2>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- History Tabs -->
                <div class="history-tabs">
                    <a href="?tab=all" class="history-tab <?php echo $activeTab == 'all' ? 'active' : ''; ?>">
                        All History
                    </a>
                    <a href="?tab=purchases"
                        class="history-tab <?php echo $activeTab == 'purchases' ? 'active' : ''; ?>">
                        Purchases
                    </a>
                    <a href="?tab=rentals" class="history-tab <?php echo $activeTab == 'rentals' ? 'active' : ''; ?>">
                        Rentals
                    </a>
                    <a href="?tab=returns" class="history-tab <?php echo $activeTab == 'returns' ? 'active' : ''; ?>">
                        Returns
                    </a>
                </div>

                <!-- History Content -->
                <?php
                // Determine which items to display based on active tab
                $displayItems = [];
                $emptyMessage = "No history items found.";

                switch ($activeTab) {
                    case 'purchases':
                        $displayItems = array_filter($history, function ($item) {
                            return $item['type'] == 'purchase';
                        });
                        $emptyMessage = "You haven't completed any purchases yet.";
                        break;
                    case 'rentals':
                        $displayItems = array_filter($history, function ($item) {
                            return $item['type'] == 'rental';
                        });
                        $emptyMessage = "You have no completed or cancelled rentals.";
                        break;
                    case 'returns':
                        $displayItems = $returns;
                        $emptyMessage = "You have no return requests.";
                        break;
                    default: // 'all'
                        $displayItems = array_merge($history, $returns);
                        $emptyMessage = "You have no history items.";
                        break;
                }
                ?>

                <?php if (empty($displayItems)): ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-clock-rotate-left"></i>
                        </div>
                        <h4><?php echo $emptyMessage; ?></h4>
                        <p class="text-muted">Your past orders, rentals, and returns will appear here.</p>
                        <a href="rentbooks.php" class="btn btn-primary mt-3">Start Browsing</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($displayItems as $item): ?>
                        <div class="history-card">
                            <div class="history-header">
                                <div>
                                    <?php if (isset($item['type']) && $item['type'] == 'rental'): ?>
                                        <span class="text-muted">Rental</span>
                                    <?php elseif (isset($item['type']) && $item['type'] == 'purchase'): ?>
                                        <span class="text-muted">Purchase</span>
                                    <?php else: ?>
                                        <span class="text-muted">Return Request</span>
                                    <?php endif; ?>
                                    <span class="ms-2 order-number">
                                        #<?php echo $item['id'] ?? $item['return_id']; ?>
                                    </span>
                                </div>

                                <?php
                                // Determine status badge
                                $statusBadgeClass = '';
                                $statusText = '';

                                if (isset($item['status'])) {
                                    switch (strtolower($item['status'])) {
                                        case 'returned':
                                        case 'completed':
                                            $statusBadgeClass = 'status-completed';
                                            $statusText = 'Completed';
                                            break;
                                        case 'cancelled':
                                            $statusBadgeClass = 'status-cancelled';
                                            $statusText = 'Cancelled';
                                            break;
                                        case 'pending':
                                            $statusBadgeClass = 'status-pending';
                                            $statusText = 'Pending';
                                            break;
                                        case 'in_transit':
                                            $statusBadgeClass = 'status-in-transit';
                                            $statusText = 'In Transit';
                                            break;
                                        default:
                                            $statusBadgeClass = 'status-pending';
                                            $statusText = ucfirst($item['status']);
                                    }
                                } elseif (isset($item['return_id'])) {
                                    // For return requests
                                    switch (strtolower($item['status'])) {
                                        case 'pending':
                                            $statusBadgeClass = 'status-pending';
                                            $statusText = 'Pending';
                                            break;
                                        case 'in_transit':
                                            $statusBadgeClass = 'status-in-transit';
                                            $statusText = 'In Transit';
                                            break;
                                        case 'completed':
                                            $statusBadgeClass = 'status-completed';
                                            $statusText = 'Completed';
                                            break;
                                        default:
                                            $statusBadgeClass = 'status-pending';
                                            $statusText = ucfirst($item['status']);
                                    }
                                }
                                ?>

                                <div class="status-badge <?php echo $statusBadgeClass; ?>">
                                    <?php echo $statusText; ?>
                                </div>
                            </div>

                            <div class="history-body">
                                <img src="<?php echo $item['cover_image'] ?? ''; ?>"
                                    alt="<?php echo $item['title'] ?? $item['book_title']; ?>" class="history-image">

                                <div class="flex-grow-1">
                                    <h5 class="mb-2">
                                        <?php echo $item['title'] ?? $item['book_title']; ?>
                                    </h5>
                                    <p class="text-muted mb-2">
                                        by <?php echo $item['author'] ?? $item['book_author']; ?>
                                    </p>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <?php if (isset($item['type']) && $item['type'] == 'rental'): ?>
                                                <p class="mb-1">
                                                    <strong>Rental Period:</strong>
                                                    <?php echo $item['rental_weeks']; ?>
                                                    week<?php echo $item['rental_weeks'] > 1 ? 's' : ''; ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if (isset($item['return_id'])): ?>
                                                <p class="mb-1">
                                                    <strong>Return Method:</strong>
                                                    <?php echo ucfirst($item['return_method']); ?>
                                                </p>

                                                <?php if ($item['status'] == 'completed' && $item['book_condition']): ?>
                                                    <p class="mb-1">
                                                        <strong>Book Condition:</strong>
                                                        <span class="
                                                        <?php
                                                        switch ($item['book_condition']) {
                                                            case 'excellent':
                                                            case 'good':
                                                                echo 'text-success';
                                                                break;
                                                            case 'fair':
                                                                echo 'text-warning';
                                                                break;
                                                            case 'damaged':
                                                                echo 'text-danger';
                                                                break;
                                                            default:
                                                                echo '';
                                                        }
                                                        ?>">
                                                            <?php echo ucfirst($item['book_condition']); ?>
                                                        </span>
                                                    </p>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <p class="mb-1">
                                                <strong>Date:</strong>
                                                <?php
                                                echo isset($item['date'])
                                                    ? date('F j, Y', strtotime($item['date']))
                                                    : date('F j, Y', strtotime($item['request_date']));
                                                ?>
                                            </p>
                                        </div>

                                        <div class="col-md-6">
                                            <p class="mb-1">
                                                <strong>Total:</strong>
                                                ₱<?php echo number_format($item['total_price'], 2); ?>
                                            </p>

                                            <?php if (isset($item['return_id'])): ?>
                                                <?php
                                                $totalFees = 0;
                                                if (isset($item['late_fee']))
                                                    $totalFees += $item['late_fee'];
                                                if (isset($item['damage_fee']))
                                                    $totalFees += $item['damage_fee'];
                                                if (isset($item['additional_fee']))
                                                    $totalFees += $item['additional_fee'];

                                                if ($totalFees > 0):
                                                    ?>
                                                    <p class="mb-1">
                                                        <strong>Additional Fees:</strong>
                                                        <span class="text-danger">₱<?php echo number_format($totalFees, 2); ?></span>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if ($item['received_date']): ?>
                                                    <p class="mb-1">
                                                        <strong>Received:</strong>
                                                        <?php echo date('F j, Y', strtotime($item['received_date'])); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if ($item['completed_date']): ?>
                                                    <p class="mb-1">
                                                        <strong>Completed:</strong>
                                                        <?php echo date('F j, Y', strtotime($item['completed_date'])); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <!-- View Details Button for Returns -->
                                                <button type="button" class="btn btn-sm btn-outline-primary mt-2 return-details-btn"
                                                    data-bs-toggle="modal" data-bs-target="#returnDetailsModal"
                                                    data-return-id="<?php echo $item['return_id']; ?>"
                                                    data-book-title="<?php echo htmlspecialchars($item['book_title']); ?>"
                                                    data-book-author="<?php echo htmlspecialchars($item['book_author']); ?>"
                                                    data-cover-image="<?php echo $item['cover_image']; ?>"
                                                    data-status="<?php echo $item['status']; ?>"
                                                    data-method="<?php echo $item['return_method']; ?>"
                                                    data-details="<?php echo htmlspecialchars($item['return_details']); ?>"
                                                    data-request-date="<?php echo $item['request_date']; ?>"
                                                    data-received-date="<?php echo $item['received_date']; ?>"
                                                    data-completed-date="<?php echo $item['completed_date']; ?>"
                                                    data-condition="<?php echo $item['book_condition']; ?>"
                                                    data-damage="<?php echo htmlspecialchars($item['damage_description'] ?? ''); ?>"
                                                    data-late-fee="<?php echo $item['late_fee']; ?>"
                                                    data-damage-fee="<?php echo $item['damage_fee']; ?>"
                                                    data-additional-fee="<?php echo $item['additional_fee']; ?>"
                                                    data-is-overdue="<?php echo $item['is_overdue']; ?>"
                                                    data-days-overdue="<?php echo $item['days_overdue']; ?>"
                                                    data-notes="<?php echo htmlspecialchars($item['notes'] ?? ''); ?>">
                                                    <i class="fas fa-info-circle"></i> View Details
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <div class="modal fade" id="returnDetailsModal" tabindex="-1" aria-labelledby="returnDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="returnDetailsModalLabel">Return Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <img id="return_book_image" src="" alt="Book Cover" class="img-fluid mb-3"
                                style="max-height: 200px;">
                            <div id="return_status_badge" class="status-badge d-inline-block"></div>
                        </div>
                        <div class="col-md-8">
                            <h4 id="return_book_title" class="mb-1"></h4>
                            <p class="text-muted mb-3" id="return_book_author"></p>

                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Return Request #:</strong>
                                        <span id="return_request_id"></span>
                                    </p>

                                    <p class="mb-2">
                                        <strong>Return Method:</strong>
                                        <span id="return_method"></span>
                                    </p>

                                    <p class="mb-2">
                                        <strong>Request Date:</strong>
                                        <span id="return_request_date"></span>
                                    </p>

                                    <p class="mb-2" id="return_received_date_container" style="display: none;">
                                        <strong>Received Date:</strong>
                                        <span id="return_received_date"></span>
                                    </p>

                                    <p class="mb-2" id="return_completed_date_container" style="display: none;">
                                        <strong>Completed Date:</strong>
                                        <span id="return_completed_date"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Return Details Section -->
                    <div class="card mb-3" id="return_method_details_card">
                        <div class="card-header">
                            <h5 class="mb-0">Return Method Details</h5>
                        </div>
                        <div class="card-body">
                            <div id="return_method_details">
                                <!-- Return method details will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Condition Assessment Section (shown only when available) -->
                    <div class="card mb-3" id="condition_assessment_card" style="display: none;">
                        <div class="card-header">
                            <h5 class="mb-0">Condition Assessment</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Book Condition:</strong>
                                        <span id="book_condition_badge" class="badge rounded-pill"></span>
                                    </p>

                                    <p class="mb-2" id="damage_description_container" style="display: none;">
                                        <strong>Damage Description:</strong>
                                        <span id="damage_description"></span>
                                    </p>

                                    <p class="mb-2" id="notes_container" style="display: none;">
                                        <strong>Notes:</strong>
                                        <span id="assessment_notes"></span>
                                    </p>
                                </div>

                                <div class="col-md-6">
                                    <div id="is_overdue_container" style="display: none;">
                                        <p class="mb-2">
                                            <strong>Overdue Status:</strong>
                                            <span id="is_overdue_badge" class="badge rounded-pill"></span>
                                        </p>

                                        <p class="mb-2">
                                            <strong>Days Overdue:</strong>
                                            <span id="days_overdue"></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fees Section (shown only when there are fees) -->
                    <div class="card" id="fees_card" style="display: none;">
                        <div class="card-header">
                            <h5 class="mb-0">Additional Fees</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr id="late_fee_row" style="display: none;">
                                            <td><strong>Late Fee:</strong></td>
                                            <td class="text-end" id="late_fee"></td>
                                        </tr>
                                        <tr id="damage_fee_row" style="display: none;">
                                            <td><strong>Damage Fee:</strong></td>
                                            <td class="text-end" id="damage_fee"></td>
                                        </tr>
                                        <tr id="additional_fee_row" style="display: none;">
                                            <td><strong>Additional Fee:</strong></td>
                                            <td class="text-end" id="additional_fee"></td>
                                        </tr>
                                        <tr id="total_fee_row" style="display: none;">
                                            <td><strong>Total Additional Fees:</strong></td>
                                            <td class="text-end fw-bold" id="total_fees"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
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
        document.addEventListener('DOMContentLoaded', function () {
            // Function to parse return details
            function parseReturnDetails(detailsJson) {
                try {
                    return JSON.parse(detailsJson);
                } catch (e) {
                    // If not valid JSON, try to parse as a string
                    try {
                        const detailsStr = detailsJson.replace(/\\"/g, '"');
                        return JSON.parse(detailsStr);
                    } catch (e2) {
                        // If all else fails, return as is
                        return detailsJson;
                    }
                }
            }

            // Add click event to return details buttons
            document.querySelectorAll('.return-details-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    // Get data from button attributes
                    const returnId = this.getAttribute('data-return-id');
                    const bookTitle = this.getAttribute('data-book-title');
                    const bookAuthor = this.getAttribute('data-book-author');
                    const coverImage = this.getAttribute('data-cover-image');
                    const status = this.getAttribute('data-status');
                    const method = this.getAttribute('data-method');
                    const details = this.getAttribute('data-details');
                    const requestDate = this.getAttribute('data-request-date');
                    const receivedDate = this.getAttribute('data-received-date');
                    const completedDate = this.getAttribute('data-completed-date');
                    const condition = this.getAttribute('data-condition');
                    const damage = this.getAttribute('data-damage');
                    const lateFee = parseFloat(this.getAttribute('data-late-fee') || 0);
                    const damageFee = parseFloat(this.getAttribute('data-damage-fee') || 0);
                    const additionalFee = parseFloat(this.getAttribute('data-additional-fee') || 0);
                    const isOverdue = this.getAttribute('data-is-overdue') === '1';
                    const daysOverdue = parseInt(this.getAttribute('data-days-overdue') || 0);
                    const notes = this.getAttribute('data-notes');

                    // Parse return details
                    const parsedDetails = parseReturnDetails(details);

                    // Populate basic info
                    document.getElementById('return_book_title').textContent = bookTitle;
                    document.getElementById('return_book_author').textContent = 'by ' + bookAuthor;
                    document.getElementById('return_book_image').src = coverImage;
                    document.getElementById('return_request_id').textContent = returnId;
                    document.getElementById('return_method').textContent = method.charAt(0).toUpperCase() + method.slice(1);
                    document.getElementById('return_request_date').textContent = new Date(requestDate).toLocaleDateString('en-US', {
                        year: 'numeric', month: 'long', day: 'numeric'
                    });

                    // Set status badge
                    const statusBadge = document.getElementById('return_status_badge');
                    statusBadge.textContent = status.charAt(0).toUpperCase() + status.replace('_', ' ').slice(1);
                    statusBadge.className = 'status-badge';

                    switch (status) {
                        case 'pending':
                            statusBadge.classList.add('status-pending');
                            break;
                        case 'in_transit':
                        case 'received':
                        case 'inspected':
                            statusBadge.classList.add('status-in-transit');
                            break;
                        case 'completed':
                            statusBadge.classList.add('status-completed');
                            break;
                        case 'cancelled':
                            statusBadge.classList.add('status-cancelled');
                            break;
                        default:
                            statusBadge.classList.add('status-pending');
                    }

                    // Show/hide received date
                    const receivedDateContainer = document.getElementById('return_received_date_container');
                    if (receivedDate) {
                        receivedDateContainer.style.display = 'block';
                        document.getElementById('return_received_date').textContent = new Date(receivedDate).toLocaleDateString('en-US', {
                            year: 'numeric', month: 'long', day: 'numeric'
                        });
                    } else {
                        receivedDateContainer.style.display = 'none';
                    }

                    // Show/hide completed date
                    const completedDateContainer = document.getElementById('return_completed_date_container');
                    if (completedDate) {
                        completedDateContainer.style.display = 'block';
                        document.getElementById('return_completed_date').textContent = new Date(completedDate).toLocaleDateString('en-US', {
                            year: 'numeric', month: 'long', day: 'numeric'
                        });
                    } else {
                        completedDateContainer.style.display = 'none';
                    }

                    // Handle return method details
                    const methodDetails = document.getElementById('return_method_details');
                    if (method === 'dropoff') {
                        let dropoffLocation = '';

                        if (typeof parsedDetails === 'object' && parsedDetails.dropoff_location) {
                            dropoffLocation = parsedDetails.dropoff_location;
                        } else if (typeof parsedDetails === 'string' && parsedDetails.includes('dropoff_location')) {
                            // Try to extract location from string
                            dropoffLocation = parsedDetails;
                        } else {
                            dropoffLocation = parsedDetails;
                        }

                        methodDetails.innerHTML = `
                        <p><strong>Drop-off Location:</strong></p>
                        <p>${dropoffLocation}</p>
                    `;
                    } else if (method === 'pickup') {
                        let pickupDetails = '';

                        if (typeof parsedDetails === 'object') {
                            // Format structured data
                            pickupDetails = `
                            <p><strong>Address:</strong> ${parsedDetails.pickup_address || 'N/A'}</p>
                            <p><strong>Date:</strong> ${parsedDetails.pickup_date || 'N/A'}</p>
                            <p><strong>Time Slot:</strong> ${parsedDetails.pickup_time || 'N/A'}</p>
                        `;

                            if (parsedDetails.pickup_notes) {
                                pickupDetails += `<p><strong>Notes:</strong> ${parsedDetails.pickup_notes}</p>`;
                            }
                        } else {
                            // Show raw data if parsing failed
                            pickupDetails = `<p>${parsedDetails}</p>`;
                        }

                        methodDetails.innerHTML = pickupDetails;
                    } else {
                        methodDetails.innerHTML = '<p>No details available</p>';
                    }

                    // Handle condition assessment
                    const conditionCard = document.getElementById('condition_assessment_card');

                    if (condition) {
                        conditionCard.style.display = 'block';

                        // Set condition badge
                        const conditionBadge = document.getElementById('book_condition_badge');
                        conditionBadge.textContent = condition.charAt(0).toUpperCase() + condition.slice(1);
                        conditionBadge.className = 'badge rounded-pill';

                        switch (condition) {
                            case 'excellent':
                            case 'good':
                                conditionBadge.classList.add('bg-success');
                                break;
                            case 'fair':
                                conditionBadge.classList.add('bg-warning');
                                break;
                            case 'damaged':
                                conditionBadge.classList.add('bg-danger');
                                break;
                            default:
                                conditionBadge.classList.add('bg-secondary');
                        }

                        // Show/hide damage description
                        const damageContainer = document.getElementById('damage_description_container');
                        if (damage) {
                            damageContainer.style.display = 'block';
                            document.getElementById('damage_description').textContent = damage;
                        } else {
                            damageContainer.style.display = 'none';
                        }

                        // Show/hide assessment notes
                        const notesContainer = document.getElementById('notes_container');
                        if (notes) {
                            notesContainer.style.display = 'block';
                            document.getElementById('assessment_notes').textContent = notes;
                        } else {
                            notesContainer.style.display = 'none';
                        }

                        // Show/hide overdue information
                        const overdueContainer = document.getElementById('is_overdue_container');
                        if (isOverdue) {
                            overdueContainer.style.display = 'block';

                            const overdueBadge = document.getElementById('is_overdue_badge');
                            overdueBadge.textContent = 'Overdue';
                            overdueBadge.className = 'badge rounded-pill bg-danger';

                            document.getElementById('days_overdue').textContent = daysOverdue + ' day' + (daysOverdue !== 1 ? 's' : '');
                        } else {
                            overdueContainer.style.display = 'none';
                        }
                    } else {
                        conditionCard.style.display = 'none';
                    }

                    // Handle fees
                    const feesCard = document.getElementById('fees_card');
                    const totalFees = lateFee + damageFee + additionalFee;

                    if (totalFees > 0) {
                        feesCard.style.display = 'block';

                        // Late fee
                        const lateFeeRow = document.getElementById('late_fee_row');
                        if (lateFee > 0) {
                            lateFeeRow.style.display = 'table-row';
                            document.getElementById('late_fee').textContent = '₱' + lateFee.toFixed(2);
                        } else {
                            lateFeeRow.style.display = 'none';
                        }

                        // Damage fee
                        const damageFeeRow = document.getElementById('damage_fee_row');
                        if (damageFee > 0) {
                            damageFeeRow.style.display = 'table-row';
                            document.getElementById('damage_fee').textContent = '₱' + damageFee.toFixed(2);
                        } else {
                            damageFeeRow.style.display = 'none';
                        }

                        // Additional fee
                        const additionalFeeRow = document.getElementById('additional_fee_row');
                        if (additionalFee > 0) {
                            additionalFeeRow.style.display = 'table-row';
                            document.getElementById('additional_fee').textContent = '₱' + additionalFee.toFixed(2);
                        } else {
                            additionalFeeRow.style.display = 'none';
                        }

                        // Total fees
                        const totalFeeRow = document.getElementById('total_fee_row');
                        totalFeeRow.style.display = 'table-row';
                        document.getElementById('total_fees').textContent = '₱' + totalFees.toFixed(2);
                    } else {
                        feesCard.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>

</html>