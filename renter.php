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

// If no seller found, exit
if (!$sellerData) {
    $_SESSION['error_message'] = "Seller profile not found.";
    header("Location: dashboard.php");
    exit();
}

$sellerId = $sellerData['id'];

// Fetch rental records with additional details
$rentalQuery = "
    SELECT 
        br.rental_id,
        br.rental_date,
        br.due_date,
        br.return_date,
        br.rental_weeks,
        br.status,
        br.total_price,
        br.order_id,
        b.title as book_title,
        b.author as book_author,
        b.cover_image,
        b.ISBN,
        u.firstname as renter_firstname,
        u.lastname as renter_lastname,
        u.email as renter_email
    FROM book_rentals br
    JOIN books b ON br.book_id = b.book_id
    JOIN users u ON br.user_id = u.id
    WHERE br.seller_id = ?
    ORDER BY br.rental_date DESC
";

$stmt = $conn->prepare($rentalQuery);
$stmt->bind_param("i", $sellerId);
$stmt->execute();
$result = $stmt->get_result();
$rentals = $result->fetch_all(MYSQLI_ASSOC);

// Calculate rental statistics
$totalRentals = count($rentals);
$activeRentals = 0;
$overdueRentals = 0;
$returnedRentals = 0;
$totalRentalRevenue = 0;

foreach ($rentals as $rental) {
    $totalRentalRevenue += $rental['total_price'];
    
    switch ($rental['status']) {
        case 'active':
            $activeRentals++;
            if (strtotime($rental['due_date']) < time()) {
                $overdueRentals++;
            }
            break;
        case 'returned':
            $returnedRentals++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rentals - BookWagon</title>
    
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
        
        .rental-stats {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .rental-stats:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.05);
        }
        
        .stats-title {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            color: #64748b !important;
            margin-bottom: 5px;
        }
        
        .stats-value {
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0;
            line-height: 1.2;
        }
        
        .currency-value {
            color: #10b981;
        }
        
        .stats-icon {
            font-size: 28px;
            opacity: 0.2;
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            border-radius: 12px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
            font-size: 14px;
        }
        
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 3px rgba(248, 161, 0, 0.2);
            border-color: #f8a100;
        }
        
        /* Table Styles */
        .table-responsive {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            border: 1px solid #e2e8f0;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px 20px;
        }
        
        .table tbody td {
            padding: 15px 20px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 14px;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .badge {
            padding: 6px 10px;
            border-radius: 30px;
            font-weight: 500;
            font-size: 12px;
        }
        
        .badge-active { background-color: #e0e7ff; color: #4338ca; }
        .badge-returned { background-color: #d1fae5; color: #047857; }
        .badge-cancelled { background-color: #fee2e2; color: #b91c1c; }
        
        .book-thumbnail {
            width: 45px;
            height: 65px;
            object-fit: cover;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .renter-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #64748b;
            font-size: 14px;
        }
        
        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: all 0.2s ease;
        }
        
        .btn-view {
            background-color: #f1f5f9;
            color: #475569;
        }
        
        .btn-view:hover {
            background-color: #e2e8f0;
            color: #0f172a;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state-icon {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Include the fixed sidebar -->
    <?php include("include/seller_sidebar.php"); ?>

    <!-- Main Content wrapper matching the sidebar layout -->
    <div class="main-content">
        <div class="page-content">
                <h2 class="mb-3">Rental Management</h2>
                
                <!-- Stats cards - IMPROVED LAYOUT -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="rental-stats">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="stats-title text-muted">Total Rentals</h5>
                                    <p class="stats-value"><?php echo $totalRentals; ?></p>
                                </div>
                                <i class="fas fa-book-reader text-primary stats-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="rental-stats">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="stats-title text-muted">Active Rentals</h5>
                                    <p class="stats-value"><?php echo $activeRentals; ?></p>
                                </div>
                                <i class="fas fa-hourglass-half text-warning stats-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="rental-stats">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="stats-title text-muted">Overdue Rentals</h5>
                                    <p class="stats-value"><?php echo $overdueRentals; ?></p>
                                </div>
                                <i class="fas fa-exclamation-triangle text-danger stats-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="rental-stats">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="stats-title text-muted">Revenue</h5>
                                    <p class="stats-value currency-value">₱<?php echo number_format($totalRentalRevenue, 2); ?></p>
                                </div>
                                <i class="fas fa-wallet text-success stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Rental Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-4 col-md-12 mb-2">
                                <input type="text" id="searchRentals" class="form-control" placeholder="Search rentals...">
                            </div>
                            <div class="col-lg-4 col-md-6 mb-2">
                                <select id="statusFilter" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="overdue">Overdue</option>
                                    <option value="returned">Returned</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-2">
                                <select id="weeksFilter" class="form-select">
                                    <option value="">All Rental Periods</option>
                                    <option value="1">1 Week</option>
                                    <option value="2-4">2-4 Weeks</option>
                                    <option value="5+">5+ Weeks</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rental List -->
<!-- Rental List -->
<?php if (empty($rentals)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h4>No Rental Records</h4>
                        <p class="text-muted">You haven't started any book rentals yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($rentals as $rental): ?>
                        <div class="rental-card" 
                             data-status="<?php echo $rental['status']; ?>" 
                             data-weeks="<?php echo $rental['rental_weeks']; ?>"
                             data-title="<?php echo htmlspecialchars(strtolower($rental['book_title'])); ?>"
                             data-author="<?php echo htmlspecialchars(strtolower($rental['book_author'])); ?>">
                            <div class="rental-header">
                                <div>
                                    <h5 class="mb-0">Rental #<?php echo $rental['rental_id']; ?></h5>
                                    <small class="text-muted">
                                        Rented on <?php echo date('M j, Y, g:i a', strtotime($rental['rental_date'])); ?>
                                    </small>
                                </div>
                                <span class="status-badge 
                                    <?php 
                                    if ($rental['status'] == 'active') {
                                        echo strtotime($rental['due_date']) < time() ? 'badge-overdue' : 'badge-active';
                                    } elseif ($rental['status'] == 'returned') {
                                        echo 'badge-returned';
                                    } else {
                                        echo 'badge-cancelled';
                                    }
                                    ?>">
                                    <?php if ($rental['status'] == 'active') {
                                        echo strtotime($rental['due_date']) < time() ? 'Overdue' : 'Active';
                                    } elseif ($rental['status'] == 'returned') {
                                        echo 'Returned';
                                    } else {
                                        echo ucfirst($rental['status']);
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="rental-body">
                                <img 
                                    src="<?php echo !empty($rental['cover_image']) ? $rental['cover_image'] : 'img/default-book-cover.jpg'; ?>" 
                                    alt="<?php echo htmlspecialchars($rental['book_title']); ?>" 
                                    class="book-thumbnail"
                                >
                                <div class="rental-details">
                                    <h5><?php echo htmlspecialchars($rental['book_title']); ?></h5>
                                    <p class="text-muted mb-2">by <?php echo htmlspecialchars($rental['book_author']); ?></p>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Renter:</strong> 
                                                <?php echo htmlspecialchars($rental['renter_firstname'] . ' ' . $rental['renter_lastname']); ?>
                                            </p>
                                            <p class="mb-1"><strong>Renter Email:</strong> 
                                                <?php echo htmlspecialchars($rental['renter_email']); ?>
                                            </p>
                                            <p class="mb-1"><strong>ISBN:</strong> 
                                                <?php echo htmlspecialchars($rental['ISBN']); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Rental Period:</strong> 
                                                <?php echo $rental['rental_weeks']; ?> week<?php echo $rental['rental_weeks'] > 1 ? 's' : ''; ?>
                                            </p>
                                            <p class="mb-1"><strong>Rental Start:</strong> 
                                                <?php echo date('M j, Y, g:i a', strtotime($rental['rental_date'])); ?>
                                            </p>
                                            <p class="mb-1"><strong>Due Date:</strong> 
                                                <?php echo date('M j, Y, g:i a', strtotime($rental['due_date'])); ?>
                                            </p>
                                            <?php if ($rental['return_date']): ?>
                                            <p class="mb-1"><strong>Return Date:</strong> 
                                                <?php echo date('M j, Y, g:i a', strtotime($rental['return_date'])); ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Total Rental Cost:</strong> 
                                            ₱<?php echo number_format($rental['total_price'], 2); ?>
                                        </div>
                                        <div class="rental-actions">
                                            <?php if ($rental['status'] == 'active' && strtotime($rental['due_date']) < time()): ?>
                                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#overdueModal<?php echo $rental['rental_id']; ?>">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>Overdue Action
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($rental['status'] == 'active'): ?>
                                                <button class="btn btn-success btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#returnBookModal<?php echo $rental['rental_id']; ?>">
                                                    <i class="fas fa-book-reader me-1"></i>Mark as Returned
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Overdue Modal -->
                        <div class="modal fade" id="overdueModal<?php echo $rental['rental_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Overdue Rental Notice</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-warning">
                                            <strong>This rental is overdue!</strong>
                                        </div>
                                        <p>Book: <?php echo htmlspecialchars($rental['book_title']); ?></p>
                                        <p>Renter: <?php echo htmlspecialchars($rental['renter_firstname'] . ' ' . $rental['renter_lastname']); ?></p>
                                        <p>Due Date: <?php echo date('M j, Y', strtotime($rental['due_date'])); ?></p>
                                        <p>Overdue By: <?php 
                                            $overdueTime = time() - strtotime($rental['due_date']);
                                            $overdueDays = floor($overdueTime / (60 * 60 * 24));
                                            echo $overdueDays . ' day' . ($overdueDays != 1 ? 's' : '');
                                        ?></p>
                                    </div>
                                    <div class="modal-footer">
                                        <form action="process_rental.php" method="POST">
                                            <input type="hidden" name="action" value="contact_renter">
                                            <input type="hidden" name="rental_id" value="<?php echo $rental['rental_id']; ?>">
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fas fa-envelope me-1"></i>Contact Renter
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Return Book Modal -->
                        <div class="modal fade" id="returnBookModal<?php echo $rental['rental_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Mark Book as Returned</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-info">
                                            <strong>Book Return Confirmation</strong>
                                        </div>
                                        <p>Book: <?php echo htmlspecialchars($rental['book_title']); ?></p>
                                        <p>Renter: <?php echo htmlspecialchars($rental['renter_firstname'] . ' ' . $rental['renter_lastname']); ?></p>
                                        <p>Rental Period: <?php echo $rental['rental_weeks']; ?> week<?php echo $rental['rental_weeks'] > 1 ? 's' : ''; ?></p>
                                    </div>
                                    <div class="modal-footer">
                                        <form action="process_rental.php" method="POST">
                                            <input type="hidden" name="action" value="mark_returned">
                                            <input type="hidden" name="rental_id" value="<?php echo $rental['rental_id']; ?>">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check me-1"></i>Confirm Return
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchRentals');
            const statusFilter = document.getElementById('statusFilter');
            const weeksFilter = document.getElementById('weeksFilter');
            const rentalCards = document.querySelectorAll('.rental-card');

            function filterRentals() {
                rentalCards.forEach(card => {
                    const searchTerm = searchInput.value.toLowerCase();
                    const statusValue = statusFilter.value;
                    const weeksValue = weeksFilter.value;

                    const titleMatch = card.dataset.title.includes(searchTerm);
                    const authorMatch = card.dataset.author.includes(searchTerm);
                    
                    let statusMatch = true;
                    if (statusValue) {
                        if (statusValue === 'overdue') {
                            statusMatch = card.querySelector('.status-badge').textContent.toLowerCase() === 'overdue';
                        } else {
                            statusMatch = card.dataset.status === statusValue;
                        }
                    }

                    let weeksMatch = true;
                    if (weeksValue) {
                        const weeks = parseInt(card.dataset.weeks);
                        switch(weeksValue) {
                            case '1':
                                weeksMatch = weeks === 1;
                                break;
                            case '2-4':
                                weeksMatch = weeks >= 2 && weeks <= 4;
                                break;
                            case '5+':
                                weeksMatch = weeks >= 5;
                                break;
                        }
                    }

                    card.style.display = (titleMatch || authorMatch) && statusMatch && weeksMatch 
                        ? '' 
                        : 'none';
                });
            }

            searchInput.addEventListener('input', filterRentals);
            statusFilter.addEventListener('change', filterRentals);
            weeksFilter.addEventListener('change', filterRentals);
        });
    </script>
</body>
</html>