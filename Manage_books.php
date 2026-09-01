<?php
include("session.php");
include("connect.php");

// Direct upload function - no helper file needed
function direct_upload_image($file, $upload_dir = 'uploads/covers/') {
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . basename($file['name']);
    $targetPath = $upload_dir . $filename;
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }
    
    return false;
}

$userType = $_SESSION['usertype'] ?? '';
$userId = $_SESSION['id'] ?? 0;

// Ensure only sellers can access this page
if ($userType !== 'seller') {
    header("Location: login.php");
    exit();
}

// Add a function to create the new database fields if they don't exist
function ensure_pricing_fields_exist($conn) {
    $fields_to_add = [
        'base_rental_fee' => 'DECIMAL(10,2) DEFAULT NULL',
        'handling_fee' => 'DECIMAL(10,2) DEFAULT NULL',
        'condition_multiplier' => 'DECIMAL(5,2) DEFAULT NULL',
        'book_value' => 'DECIMAL(10,2) DEFAULT NULL',
        'listing_fee' => 'DECIMAL(10,2) DEFAULT NULL',
        'markup_percentage' => 'INT DEFAULT NULL'
    ];
    
    // Check if fields exist
    $result = $conn->query("SHOW COLUMNS FROM books");
    $existing_fields = [];
    while($row = $result->fetch_assoc()) {
        $existing_fields[] = $row['Field'];
    }
    
    // Add missing fields
    foreach($fields_to_add as $field => $definition) {
        if (!in_array($field, $existing_fields)) {
            $conn->query("ALTER TABLE books ADD COLUMN $field $definition");
            error_log("Added field $field to books table");
        }
    }
}

// Call the function to ensure fields exist
ensure_pricing_fields_exist($conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check which action is being performed
    if (isset($_POST['action'])) {
        
        // Add new book
        if ($_POST['action'] === 'add') {
            // Prepare and sanitize the data
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $author = mysqli_real_escape_string($conn, $_POST['author']);
            $isbn = mysqli_real_escape_string($conn, $_POST['isbn']);
            $genre = mysqli_real_escape_string($conn, $_POST['genre']);
            $theme = mysqli_real_escape_string($conn, $_POST['theme']);
            // Add new fields
            $book_type = mysqli_real_escape_string($conn, $_POST['book_type']);
            $condition = mysqli_real_escape_string($conn, $_POST['condition']);
            $damages = mysqli_real_escape_string($conn, $_POST['damages']);
            $popularity = mysqli_real_escape_string($conn, $_POST['popularity']);
            $price = floatval($_POST['price']);
            $rent_price = floatval($_POST['rent_price']);
            $stock = intval($_POST['stock']);
            $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
            
            // Add debugging to check the description value
            error_log("Description before DB insert: " . $description);
            
            // Pricing strategy fields
            $base_rental_fee = floatval($_POST['base_rental_fee'] ?? 0);
            // Override with fixed values regardless of what was submitted
            $handling_fee = 10.00; // Fixed value
            $condition_multiplier = floatval($_POST['condition_multiplier'] ?? 1.0);
            $book_value = floatval($_POST['book_value'] ?? 0);
            // Override with fixed values regardless of what was submitted
            $listing_fee = 30.00; // Fixed value
            $markup_percentage = 30; // Fixed value
            
            // Handle image upload
            $cover_image = '';
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $uploaded_image = direct_upload_image($_FILES['cover_image']);
                if ($uploaded_image) {
                    $cover_image = $uploaded_image; // Assign the uploaded file path to $cover_image
                } else {
                    $error_message = "Error uploading image. Please try again.";
                }
            }
            
            // Insert book into database with new fields
            $query = "INSERT INTO books (user_id, title, author, ISBN, genre, theme, book_type, `condition`, damages, popularity, price, rent_price, stock, description, cover_image, base_rental_fee, handling_fee, condition_multiplier, book_value, listing_fee, markup_percentage) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            // Add this before your INSERT query
            error_log("Cover image path before DB insert: " . $cover_image);

            $stmt = $conn->prepare($query);
            // Ensure proper parameter binding:
            // i - integer: user_id
            // s - string: title, author, isbn, genre, theme, book_type, condition, damages, popularity
            // d - double: price, rent_price
            // d - double: base_rental_fee, handling_fee, condition_multiplier, book_value, listing_fee
            // i - integer: stock, markup_percentage
            // s - string: description, cover_image
            $stmt->bind_param("isssssssssddiisdddddi", 
                $userId, 
                $title, 
                $author, 
                $isbn, 
                $genre, 
                $theme, 
                $book_type, 
                $condition, 
                $damages, 
                $popularity, 
                $price, 
                $rent_price, 
                $stock, 
                $description, // string for description
                $cover_image, 
                $base_rental_fee, 
                $handling_fee, 
                $condition_multiplier, 
                $book_value, 
                $listing_fee, 
                $markup_percentage);
            if ($stmt->execute()) {
                $success_message = "Book added successfully!";
                // Redirect to prevent form resubmission on refresh
                header("Location: manage_books.php?success=add");
                exit();
            }

            $stmt->close();
        }
        
        // Edit existing book
        elseif ($_POST['action'] === 'edit' && isset($_POST['book_id'])) {
            $book_id = intval($_POST['book_id']);
            
            // First check if this book belongs to the current user
            $check_query = "SELECT user_id, cover_image FROM books WHERE book_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("i", $book_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 1) {
                $book_data = $check_result->fetch_assoc();
                
                // Verify ownership
                if ($book_data['user_id'] == $userId) {
                    // Prepare and sanitize the data
                    $title = mysqli_real_escape_string($conn, $_POST['title']);
                    $author = mysqli_real_escape_string($conn, $_POST['author']);
                    $isbn = mysqli_real_escape_string($conn, $_POST['isbn']);
                    $genre = mysqli_real_escape_string($conn, $_POST['genre']);
                    $theme = mysqli_real_escape_string($conn, $_POST['theme']);
                    // Add new fields
                    $book_type = mysqli_real_escape_string($conn, $_POST['book_type']);
                    $condition = mysqli_real_escape_string($conn, $_POST['condition']);
                    $damages = mysqli_real_escape_string($conn, $_POST['damages']);
                    $popularity = mysqli_real_escape_string($conn, $_POST['popularity']);
                    $price = floatval($_POST['price']);
                    $rent_price = floatval($_POST['rent_price']);
                    $stock = intval($_POST['stock']);
                    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
                    
                    // Add debugging to check the description value
                    error_log("Description before DB update: " . $description);
                    
                    // Pricing strategy fields
                    $base_rental_fee = floatval($_POST['base_rental_fee'] ?? 0);
                    // Override with fixed values regardless of what was submitted
                    $handling_fee = 10.00; // Fixed value
                    $condition_multiplier = floatval($_POST['condition_multiplier'] ?? 1.0);
                    $book_value = floatval($_POST['book_value'] ?? 0);
                    // Override with fixed values regardless of what was submitted
                    $listing_fee = 30.00; // Fixed value
                    $markup_percentage = 30; // Fixed value
                    
                    // Simplified file upload approach for editing
                    $cover_image_query = "";
                    $cover_image = $book_data['cover_image']; // Keep existing cover by default
                    
                    if (!empty($_FILES['cover_image']['name'])) {
                        $new_cover = direct_upload_image($_FILES['cover_image']);
                        if (!empty($new_cover)) {
                            $cover_image = $new_cover;
                            $cover_image_query = ", cover_image = ?";
                        }
                    }
                    
                    // Update book in database
                    if (!empty($cover_image_query)) {
                        $query = "UPDATE books SET title = ?, author = ?, ISBN = ?, genre = ?, theme = ?, 
                                  book_type = ?, `condition` = ?, damages = ?, popularity = ?, 
                                  price = ?, rent_price = ?, stock = ?, description = ?, cover_image = ?,
                                  base_rental_fee = ?, handling_fee = ?, condition_multiplier = ?,
                                  book_value = ?, listing_fee = ?, markup_percentage = ? 
                                  WHERE book_id = ? AND user_id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("sssssssssddiisdddddiii", $title, $author, $isbn, $genre, $theme, 
                                          $book_type, $condition, $damages, $popularity, 
                                          $price, $rent_price, $stock, $description, $cover_image,
                                          $base_rental_fee, $handling_fee, $condition_multiplier,
                                          $book_value, $listing_fee, $markup_percentage,
                                          $book_id, $userId);
                    } else {
                        $query = "UPDATE books SET title = ?, author = ?, ISBN = ?, genre = ?, theme = ?, 
                                  book_type = ?, `condition` = ?, damages = ?, popularity = ?, 
                                  price = ?, rent_price = ?, stock = ?, description = ?,
                                  base_rental_fee = ?, handling_fee = ?, condition_multiplier = ?,
                                  book_value = ?, listing_fee = ?, markup_percentage = ? 
                                  WHERE book_id = ? AND user_id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("sssssssssddisdddddiii", $title, $author, $isbn, $genre, $theme, 
                                         $book_type, $condition, $damages, $popularity, 
                                         $price, $rent_price, $stock, $description,
                                         $base_rental_fee, $handling_fee, $condition_multiplier,
                                         $book_value, $listing_fee, $markup_percentage,
                                         $book_id, $userId);
                    }
                    
                    if ($stmt->execute()) {
                        $success_message = "Book updated successfully!";
                    } else {
                        $error_message = "Error updating book: " . $stmt->error;
                    }
                    
                    $stmt->close();
                } else {
                    $error_message = "You don't have permission to edit this book.";
                }
            } else {
                $error_message = "Book not found.";
            }
            
            $check_stmt->close();
        }
        
        // Delete book
        elseif ($_POST['action'] === 'delete' && isset($_POST['book_id'])) {
            $book_id = intval($_POST['book_id']);
            
            // First check if this book belongs to the current user
            $check_query = "SELECT user_id FROM books WHERE book_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("i", $book_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 1) {
                $book_data = $check_result->fetch_assoc();
                
                // Verify ownership
                if ($book_data['user_id'] == $userId) {
                    // Begin transaction for data consistency
                    $conn->begin_transaction();
                    
                    try {
                        // First delete related rental records
                        $delete_rentals_query = "DELETE FROM book_rentals WHERE book_id = ?";
                        $delete_rentals_stmt = $conn->prepare($delete_rentals_query);
                        $delete_rentals_stmt->bind_param("i", $book_id);
                        $delete_rentals_stmt->execute();
                        $delete_rentals_stmt->close();
                        
                        // Also delete related cart items
                        $delete_cart_query = "DELETE FROM cart WHERE book_id = ?";
                        $delete_cart_stmt = $conn->prepare($delete_cart_query);
                        $delete_cart_stmt->bind_param("i", $book_id);
                        $delete_cart_stmt->execute();
                        $delete_cart_stmt->close();
                        
                        // Also delete related order_items
                        $delete_order_items_query = "DELETE FROM order_items WHERE book_id = ?";
                        $delete_order_items_stmt = $conn->prepare($delete_order_items_query);
                        $delete_order_items_stmt->bind_param("i", $book_id);
                        $delete_order_items_stmt->execute();
                        $delete_order_items_stmt->close();
                        
                        // Now delete the book
                        $delete_book_query = "DELETE FROM books WHERE book_id = ? AND user_id = ?";
                        $delete_book_stmt = $conn->prepare($delete_book_query);
                        $delete_book_stmt->bind_param("ii", $book_id, $userId);
                        $delete_book_stmt->execute();
                        
                        if ($delete_book_stmt->affected_rows > 0) {
                            $conn->commit();
                            $success_message = "Book deleted successfully!";
                        } else {
                            $conn->rollback();
                            $error_message = "You don't have permission to delete this book.";
                        }
                        
                        $delete_book_stmt->close();
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error_message = "Error deleting book: " . $e->getMessage();
                    }
                } else {
                    $error_message = "You don't have permission to delete this book.";
                }
            } else {
                $error_message = "Book not found.";
            }
            
            $check_stmt->close();
        }
    }
}

// Fetch books owned by this user
$query = "SELECT * FROM books WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch genre list for dropdown
$genre_query = "SELECT DISTINCT genre FROM books";
$genre_result = $conn->query($genre_query);
$genres = [];
while ($row = $genre_result->fetch_assoc()) {
    if (!empty($row['genre'])) {
        $genres[] = $row['genre'];
    }
}

// Fetch theme list for dropdown
$theme_query = "SELECT DISTINCT theme FROM books";
$theme_result = $conn->query($theme_query);
$themes = [];
while ($row = $theme_result->fetch_assoc()) {
    if (!empty($row['theme'])) {
        $themes[] = $row['theme'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - BookWagon</title>
    
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
        
        .card {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .table-responsive {
            background-color: white;
            border-radius: 0 0 12px 12px;
        }
        
        .table thead {
            background-color: #f8f9fa;
        }
        
        .action-dropdown {
            cursor: pointer;
        }
        
        .book-thumbnail {
            width: 50px;
            height: 70px;
            object-fit: cover;
            margin-right: 10px;
            border-radius: 6px;
        }
        
        /* Modal styles */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 12px 12px 0 0;
        }
        
        .btn-primary {
            background-color: #f8a100;
            border-color: #f8a100;
        }
        
        .btn-primary:hover {
            background-color: #d97706;
            border-color: #d97706;
        }
        
        /* Grid view styles */
        .book-grid {
            display: flex;
            flex-wrap: wrap;
        }
        
        .object-fit-cover {
            object-fit: cover;
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
                <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-12">
                        <h2 class="mb-4">Manage Books</h2>
                        
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Book List</h5>
                                <div class="d-flex">
                                    <div class="btn-group me-3">
                                        <button class="btn btn-outline-secondary active" id="tableViewBtn">
                                            <i class="fas fa-list"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" id="gridViewBtn">
                                            <i class="fas fa-th-large"></i>
                                        </button>
                                    </div>
                                    <div class="input-group me-3" style="width: 300px;">
                                        <input type="text" class="form-control" id="searchBooks" placeholder="Search books...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
                                        <i class="fas fa-plus me-2"></i>Add New Book
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>
                                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                                </th>
                                                <th>Book</th>
                                                <th>ISBN</th>
                                                <th>Genre</th>
                                                <th>Type</th>
                                                <th>Condition</th>
                                                <th>Price</th>
                                                <th>Rent Price</th>
                                                <th>Stock</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($books)): ?>
                                            <tr>
                                                <td colspan="11" class="text-center py-5">
                                                    <p>No books found. Add some books to get started.</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($books as $book): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="form-check-input book-select" value="<?php echo $book['book_id']; ?>">
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?php echo !empty($book['cover_image']) ? $book['cover_image'] : 'img/default-book-cover.jpg'; ?>" 
                                                                 alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                                                 class="book-thumbnail">
                                                            <div>
                                                                <div><?php echo htmlspecialchars($book['title']); ?></div>
                                                                <small class="text-muted">By <?php echo htmlspecialchars($book['author']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($book['ISBN'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($book['genre']); ?></td>
                                                    <!-- Display Book Type -->
                                                    <td><?php echo htmlspecialchars($book['book_type'] ?? 'Paperback'); ?></td>
                                                    <!-- Display Condition with tooltip for damages -->
                                                    <td>
                                                        <?php if (!empty($book['damages'])): ?>
                                                        <span data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($book['damages']); ?>">
                                                            <?php echo htmlspecialchars($book['condition'] ?? 'New'); ?> <i class="fas fa-info-circle text-warning"></i>
                                                        </span>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($book['condition'] ?? 'New'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>₱<?php echo number_format($book['price'], 2); ?></td>
                                                    <td>₱<?php echo number_format($book['rent_price'] ?? 0, 2); ?></td>
                                                    <td>
                                                        <?php if ($book['stock'] <= 5): ?>
                                                            <span class="text-danger"><?php echo $book['stock']; ?></span>
                                                        <?php else: ?>
                                                            <?php echo $book['stock']; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($book['stock'] > 0): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Out of Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <a href="#" class="action-dropdown" data-bs-toggle="dropdown">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </a>
                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <a class="dropdown-item edit-book" href="#" 
                                                                       data-bs-toggle="modal" 
                                                                       data-bs-target="#editBookModal" 
                                                                       data-book-id="<?php echo $book['book_id']; ?>">
                                                                        <i class="fas fa-edit me-2"></i>Edit
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item delete-book" href="#" 
                                                                       data-bs-toggle="modal" 
                                                                       data-bs-target="#deleteBookModal" 
                                                                       data-book-id="<?php echo $book['book_id']; ?>"
                                                                       data-book-title="<?php echo htmlspecialchars($book['title']); ?>">
                                                                        <i class="fas fa-trash me-2"></i>Delete
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Book Grid View (Alternative to Table View) -->
                                <div class="row book-grid" id="bookGridView" style="display: none;">
                                    <?php if (empty($books)): ?>
                                    <div class="col-12 text-center py-5">
                                        <p>No books found. Add some books to get started.</p>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach ($books as $book): ?>
                                        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                                            <div class="card h-100">
                                                <div class="card-img-top position-relative" style="height: 200px; overflow: hidden;">
                                                    <img src="<?php echo !empty($book['cover_image']) ? $book['cover_image'] : 'img/default-book-cover.jpg'; ?>" 
                                                         alt="<?php echo htmlspecialchars($book['title']); ?>"
                                                         class="w-100 h-100 object-fit-cover">
                                                    
                                                    <!-- Book Type Badge -->
                                                    <span class="position-absolute top-0 start-0 badge bg-info m-2">
                                                        <?php echo htmlspecialchars($book['book_type'] ?? 'Paperback'); ?>
                                                    </span>
                                                    
                                                    <!-- Condition Badge -->
                                                    <span class="position-absolute top-0 end-0 badge 
                                                        <?php 
                                                        $conditionClass = 'bg-success';
                                                        switch($book['condition'] ?? 'New') {
                                                            case 'New':
                                                                $conditionClass = 'bg-success';
                                                                break;
                                                            case 'Like New':
                                                                $conditionClass = 'bg-success';
                                                                break;
                                                            case 'Very Good':
                                                                $conditionClass = 'bg-info';
                                                                break;
                                                            case 'Good':
                                                                $conditionClass = 'bg-info';
                                                                break;
                                                            case 'Fair':
                                                                $conditionClass = 'bg-warning';
                                                                break;
                                                            case 'Poor':
                                                                $conditionClass = 'bg-danger';
                                                                break;
                                                        }
                                                        echo $conditionClass;
                                                        ?> m-2">
                                                        <?php echo htmlspecialchars($book['condition'] ?? 'New'); ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <h5 class="card-title text-truncate"><?php echo htmlspecialchars($book['title']); ?></h5>
                                                    <p class="card-text text-muted mb-1">By <?php echo htmlspecialchars($book['author']); ?></p>
                                                    <p class="card-text mb-1">
                                                        <small class="text-muted">ISBN: <?php echo htmlspecialchars($book['ISBN'] ?? 'N/A'); ?></small>
                                                    </p>
                                                    <p class="card-text mb-1">
                                                        <small class="text-muted">Genre: <?php echo htmlspecialchars($book['genre']); ?></small>
                                                    </p>
                                                    
                                                    <!-- Damages Note (if any) -->
                                                    <?php if (!empty($book['damages'])): ?>
                                                    <p class="card-text mb-1">
                                                        <small class="text-warning">
                                                            <i class="fas fa-exclamation-triangle"></i> 
                                                            <?php echo htmlspecialchars(mb_strimwidth($book['damages'], 0, 30, "...")); ?>
                                                        </small>
                                                    </p>
                                                    <?php endif; ?>
                                                    
                                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                                        <div>
                                                            <p class="mb-0 fw-bold">₱<?php echo number_format($book['price'], 2); ?></p>
                                                            <small class="text-muted">Rent: ₱<?php echo number_format($book['rent_price'] ?? 0, 2); ?>/wk</small>
                                                        </div>
                                                        <div>
                                                            <span class="badge <?php echo $book['stock'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                                <?php echo $book['stock'] > 0 ? 'In Stock: ' . $book['stock'] : 'Out of Stock'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-white border-0">
                                                    <div class="d-flex justify-content-end">
                                                        <button class="btn btn-sm btn-outline-primary me-2 edit-book" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editBookModal" 
                                                                data-book-id="<?php echo $book['book_id']; ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-book" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteBookModal" 
                                                                data-book-id="<?php echo $book['book_id']; ?>"
                                                                data-book-title="<?php echo htmlspecialchars($book['title']); ?>">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Book Modal -->
    <div class="modal fade" id="addBookModal" tabindex="-1" aria-labelledby="addBookModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBookModalLabel">Add New Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="manage_books.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="author" class="form-label">Author</label>
                                    <input type="text" class="form-control" id="author" name="author" required>
                                </div>
                                <div class="mb-3">
                                    <label for="isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn" required>
                                </div>
                                <div class="mb-3">
                                    <label for="genre" class="form-label">Genre</label>
                                    <select class="form-select" id="genre" name="genre" required onchange="updateThemeOptions('genre', 'theme')">
                                        <option value="">Select Genre</option>
                                        <option value="Fiction">Fiction</option>
                                        <option value="Non-Fiction">Non-Fiction</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="theme" class="form-label">Theme</label>
                                    <select class="form-select" id="theme" name="theme" required disabled>
                                        <option value="">Select Genre First</option>
                                    </select>
                                </div>
                                <!-- New Field: Book Type -->
                                <div class="mb-3">
                                    <label for="book_type" class="form-label">Book Type</label>
                                    <select class="form-select" id="book_type" name="book_type" required>
                                        <option value="Paperback">Paperback</option>
                                        <option value="Hardcover">Hardcover</option>
                                        <option value="E-book">E-book</option>
                                        <option value="Audiobook">Audiobook</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- New Field: Condition -->
                                <div class="mb-3">
                                    <label for="condition" class="form-label">Condition</label>
                                    <select class="form-select" id="condition" name="condition" required onchange="updateConditionMultiplier(); calculatePrices();">
                                        <option value="New">New</option>
                                        <option value="Like New">Like New</option>
                                        <option value="Very Good">Very Good</option>
                                        <option value="Good">Good</option>
                                        <option value="Fair">Fair</option>
                                        <option value="Poor">Poor</option>
                                    </select>
                                </div>
                                <!-- New Field: Damages -->
                                <div class="mb-3">
                                    <label for="damages" class="form-label">Damages (if any)</label>
                                    <textarea class="form-control" id="damages" name="damages" rows="2" placeholder="Describe any damages or defects..."></textarea>
                                </div>
                                
                                <!-- Pricing Strategy Fields -->
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Pricing Strategy</h6>
                                    </div>
                                    <div class="card-body">
                                        <!-- Rental Pricing Fields -->
                                        <h6>Rental Pricing</h6>
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <label for="base_rental_fee" class="form-label">Base Rental Fee (₱)</label>
                                                <input type="number" class="form-control" id="base_rental_fee" name="base_rental_fee" step="0.01" min="0" value="50" onchange="calculatePrices()">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="handling_fee" class="form-label">Handling Fee (₱)</label>
                                                <input type="number" class="form-control" id="handling_fee" name="handling_fee" step="0.01" min="0" value="10" onchange="calculatePrices()" readonly>
                                                <div class="form-text">Fixed handling fee</div>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="condition_multiplier" class="form-label">Condition Multiplier</label>
                                                <input type="number" class="form-control" id="condition_multiplier" name="condition_multiplier" step="0.01" min="0.5" value="1.2" readonly>
                                            </div>
                                        </div>
                                        
                                        <!-- Sales Pricing Fields -->
                                        <h6>Sales Pricing</h6>
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <label for="book_value" class="form-label">Book Value (₱)</label>
                                                <input type="number" class="form-control" id="book_value" name="book_value" step="0.01" min="0" value="200" onchange="calculatePrices()">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="listing_fee" class="form-label">Listing Fee (₱)</label>
                                                <input type="number" class="form-control" id="listing_fee" name="listing_fee" step="0.01" min="0" value="30" onchange="calculatePrices()" readonly>
                                                <div class="form-text">Fixed listing fee</div>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="markup_percentage" class="form-label">Markup (%)</label>
                                                <input type="number" class="form-control" id="markup_percentage" name="markup_percentage" step="1" min="0" max="100" value="30" onchange="calculatePrices()" readonly>
                                                <div class="form-text">Fixed markup percentage</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price (Whole book) (₱)</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="calculatePrices()">Recalculate</button>
                                    </div>
                                    <div class="form-text">Suggested: ₱<span id="suggested_price">0.00</span></div>
                                </div>
                                <div class="mb-3">
                                    <label for="rent_price" class="form-label">Rent Price (Per week) (₱)</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="rent_price" name="rent_price" step="0.01" min="0" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="calculatePrices()">Recalculate</button>
                                    </div>
                                    <div class="form-text">Suggested: ₱<span id="suggested_rent_price">0.00</span></div>
                                </div>
                                <div class="mb-3">
                                    <label for="stock" class="form-label">Stock</label>
                                    <input type="number" class="form-control" id="stock" name="stock" min="0" required>
                                </div>
                                <div class="mb-3">
                                    <label for="cover_image" class="form-label">Cover Image</label>
                                    <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/*">
                                    <div class="form-text">Recommended size: 400x600 pixels</div>
                                </div>
                                <!-- Popularity is hidden and will be set by the system -->
                                <input type="hidden" name="popularity" value="New Releases">
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Book Modal -->
    <div class="modal fade" id="editBookModal" tabindex="-1" aria-labelledby="editBookModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBookModalLabel">Edit Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editBookForm" action="manage_books.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="book_id" id="edit_book_id">
                    <div class="modal-body">
                        <!-- Form fields will be populated via JavaScript -->
                        <div class="spinner-border text-primary" role="status" id="editBookLoader">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div id="editBookContent" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_title" class="form-label">Title</label>
                                        <input type="text" class="form-control" id="edit_title" name="title" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_author" class="form-label">Author</label>
                                        <input type="text" class="form-control" id="edit_author" name="author" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_isbn" class="form-label">ISBN</label>
                                        <input type="text" class="form-control" id="edit_isbn" name="isbn" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_genre" class="form-label">Genre</label>
                                        <input type="text" class="form-control" id="edit_genre" name="genre" list="genreList" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_theme" class="form-label">Theme</label>
                                        <input type="text" class="form-control" id="edit_theme" name="theme" list="themeList" required>
                                    </div>
                                    <!-- New Field: Book Type -->
                                    <div class="mb-3">
                                        <label for="edit_book_type" class="form-label">Book Type</label>
                                        <select class="form-select" id="edit_book_type" name="book_type" required>
                                            <option value="Paperback">Paperback</option>
                                            <option value="Hardcover">Hardcover</option>
                                            <option value="E-book">E-book</option>
                                            <option value="Audiobook">Audiobook</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <!-- New Field: Condition -->
                                    <div class="mb-3">
                                        <label for="edit_condition" class="form-label">Condition</label>
                                        <select class="form-select" id="edit_condition" name="condition" required onchange="updateEditConditionMultiplier(); calculateEditPrices();">
                                            <option value="New">New</option>
                                            <option value="Like New">Like New</option>
                                            <option value="Very Good">Very Good</option>
                                            <option value="Good">Good</option>
                                            <option value="Fair">Fair</option>
                                            <option value="Poor">Poor</option>
                                        </select>
                                    </div>
                                    <!-- New Field: Damages -->
                                    <div class="mb-3">
                                        <label for="edit_damages" class="form-label">Damages (if any)</label>
                                        <textarea class="form-control" id="edit_damages" name="damages" rows="2" placeholder="Describe any damages or defects..."></textarea>
                                    </div>
                                    
                                    <!-- Pricing Strategy Fields -->
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Pricing Strategy</h6>
                                        </div>
                                        <div class="card-body">
                                            <!-- Rental Pricing Fields -->
                                            <h6>Rental Pricing</h6>
                                            <div class="row mb-2">
                                                <div class="col-md-6">
                                                    <label for="edit_base_rental_fee" class="form-label">Base Rental Fee (₱)</label>
                                                    <input type="number" class="form-control" id="edit_base_rental_fee" name="base_rental_fee" step="0.01" min="0" value="50" onchange="calculateEditPrices()">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="edit_handling_fee" class="form-label">Handling Fee (₱)</label>
                                                    <input type="number" class="form-control" id="edit_handling_fee" name="handling_fee" step="0.01" min="0" value="10" onchange="calculateEditPrices()" readonly>
                                                    <div class="form-text">Fixed handling fee</div>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="edit_condition_multiplier" class="form-label">Condition Multiplier</label>
                                                    <input type="number" class="form-control" id="edit_condition_multiplier" name="condition_multiplier" step="0.01" min="0.5" value="1.2" readonly>
                                                </div>
                                            </div>
                                            
                                            <!-- Sales Pricing Fields -->
                                            <h6>Sales Pricing</h6>
                                            <div class="row mb-2">
                                                <div class="col-md-6">
                                                    <label for="edit_book_value" class="form-label">Book Value (₱)</label>
                                                    <input type="number" class="form-control" id="edit_book_value" name="book_value" step="0.01" min="0" value="200" onchange="calculateEditPrices()">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="edit_listing_fee" class="form-label">Listing Fee (₱)</label>
                                                    <input type="number" class="form-control" id="edit_listing_fee" name="listing_fee" step="0.01" min="0" value="30" onchange="calculateEditPrices()" readonly>
                                                    <div class="form-text">Fixed listing fee</div>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="edit_markup_percentage" class="form-label">Markup (%)</label>
                                                    <input type="number" class="form-control" id="edit_markup_percentage" name="markup_percentage" step="1" min="0" max="100" value="30" onchange="calculateEditPrices()" readonly>
                                                    <div class="form-text">Fixed markup percentage</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_price" class="form-label">Price (₱)</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="calculateEditPrices()">Recalculate</button>
                                        </div>
                                        <div class="form-text">Suggested: ₱<span id="edit_suggested_price">0.00</span></div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_rent_price" class="form-label">Rent Price (₱)</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="edit_rent_price" name="rent_price" step="0.01" min="0" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="calculateEditPrices()">Recalculate</button>
                                        </div>
                                        <div class="form-text">Suggested: ₱<span id="edit_suggested_rent_price">0.00</span></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_stock" class="form-label">Stock</label>
                                        <input type="number" class="form-control" id="edit_stock" name="stock" min="0" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_cover_image" class="form-label">Cover Image</label>
                                        <input type="file" class="form-control" id="edit_cover_image" name="cover_image" accept="image/*">
                                        <div class="form-text">Leave empty to keep current image</div>
                                        <div id="current_cover_preview" class="mt-2"></div>
                                    </div>
                                    <!-- Popularity is hidden and will maintain its current value -->
                                    <input type="hidden" name="popularity" id="edit_popularity">
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="edit_description" class="form-label">Description</label>
                                        <textarea class="form-control" id="edit_description" name="description" rows="3" required></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Book Modal -->
    <div class="modal fade" id="deleteBookModal" tabindex="-1" aria-labelledby="deleteBookModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteBookModalLabel">Delete Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<span id="delete-book-title"></span>"? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form action="manage_books.php" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="book_id" id="delete_book_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Datalists for edit form -->
    <datalist id="genreList">
        <?php foreach ($genres as $genre): ?>
            <option value="<?php echo htmlspecialchars($genre); ?>">
        <?php endforeach; ?>
    </datalist>
    
    <datalist id="themeList">
        <?php foreach ($themes as $theme): ?>
            <option value="<?php echo htmlspecialchars($theme); ?>">
        <?php endforeach; ?>
    </datalist>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Fiction and Non-Fiction theme options
        const themeOptions = {
            "Fiction": [
                "Fantasy", 
                "Science Fiction", 
                "Mystery", 
                "Thriller", 
                "Suspense", 
                "Romance", 
                "Historical Fiction", 
                "Horror", 
                "Literary Fiction", 
                "Adventure", 
                "Dystopian", 
                "Paranormal", 
                "Magical Realism", 
                "Urban Fantasy", 
                "Contemporary Fiction", 
                "Crime Fiction", 
                "Drama", 
                "Western", 
                "Satire", 
                "Gothic Fiction"
            ],
            "Non-Fiction": [
                "Biography",
                "Autobiography",
                "Memoir",
                "Self-Help",
                "True Crime",
                "History",
                "Science",
                "Nature",
                "Travel",
                "Philosophy",
                "Psychology",
                "Religion/Spirituality",
                "Politics",
                "Essays",
                "Journalism",
                "Art",
                "Music",
                "Business",
                "Health & Wellness",
                "Cookbooks",
                "Education",
                "Parenting",
                "Sports",
                "Technology",
                "Finance"
            ]
        };
        
        // Function to update theme dropdown based on genre selection
        function updateThemeOptions(genreId, themeId) {
            const genreSelect = document.getElementById(genreId);
            const themeSelect = document.getElementById(themeId);
            
            // Clear current options
            themeSelect.innerHTML = '';
            
            const selectedGenre = genreSelect.value;
            
            if (selectedGenre === '') {
                // If no genre selected, disable theme dropdown
                themeSelect.disabled = true;
                themeSelect.innerHTML = '<option value="">Select Genre First</option>';
                return;
            }
            
            // Enable theme dropdown
            themeSelect.disabled = false;
            
            // Add default option
            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select Theme';
            themeSelect.appendChild(defaultOption);
            
            // Add options based on selected genre
            const options = themeOptions[selectedGenre] || [];
            options.forEach(theme => {
                const option = document.createElement('option');
                option.value = theme;
                option.textContent = theme;
                themeSelect.appendChild(option);
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips for damage info icons
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Show/hide damages field based on condition selection
            const conditionSelect = document.getElementById('condition');
            const damagesField = document.getElementById('damages').closest('.mb-3');
            
            if (conditionSelect && damagesField) {
                // Initial state check
                updateDamagesVisibility(conditionSelect.value);
                
                // Listen for changes
                conditionSelect.addEventListener('change', function() {
                    updateDamagesVisibility(this.value);
                });
            }
            
            // Same for edit modal
            const editConditionSelect = document.getElementById('edit_condition');
            const editDamagesField = document.getElementById('edit_damages').closest('.mb-3');
            
            if (editConditionSelect && editDamagesField) {
                editConditionSelect.addEventListener('change', function() {
                    updateDamagesVisibility(this.value, true);
                });
            }
            
            // Function to show/hide damages field based on condition
            function updateDamagesVisibility(condition, isEdit = false) {
                const damagesField = isEdit 
                    ? document.getElementById('edit_damages').closest('.mb-3')
                    : document.getElementById('damages').closest('.mb-3');
                
                // Show damages field only for non-new conditions
                if (condition === 'New') {
                    damagesField.style.display = 'none';
                    if (isEdit) {
                        document.getElementById('edit_damages').value = '';
                    } else {
                        document.getElementById('damages').value = '';
                    }
                } else {
                    damagesField.style.display = 'block';
                }
            }
            
            // Edit Book Modal
            const editBookModal = document.getElementById('editBookModal');
            const editBookLoader = document.getElementById('editBookLoader');
            const editBookContent = document.getElementById('editBookContent');
            
            // Handle Edit Book button clicks
            editBookModal.addEventListener('show.bs.modal', function(event) {
                // Button that triggered the modal
                const button = event.relatedTarget;
                // Extract book ID
                const bookId = button.getAttribute('data-book-id');
                
                // Set the book ID in the form
                document.getElementById('edit_book_id').value = bookId;
                
                // Show loader
                editBookLoader.style.display = 'block';
                editBookContent.style.display = 'none';
                
                // Fetch book details via AJAX
                fetch('get_book.php?id=' + bookId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const book = data.book;
                            
                            // Populate form fields
                            document.getElementById('edit_title').value = book.title;
                            document.getElementById('edit_author').value = book.author;
                            document.getElementById('edit_isbn').value = book.ISBN || '';
                            
                            // Set genre and update theme options
                            const genreSelect = document.getElementById('edit_genre');
                            genreSelect.value = book.genre;
                            
                            // Set theme
                            const themeSelect = document.getElementById('edit_theme');
                            themeSelect.value = book.theme;
                            
                            // Set new fields
                            document.getElementById('edit_book_type').value = book.book_type || 'Paperback';
                            document.getElementById('edit_condition').value = book.condition || 'New';
                            document.getElementById('edit_damages').value = book.damages || '';
                            
                            // Update damages visibility based on condition
                            updateDamagesVisibility(book.condition || 'New', true);
                            
                            document.getElementById('edit_popularity').value = book.popularity || 'New Releases';
                            document.getElementById('edit_price').value = book.price;
                            document.getElementById('edit_rent_price').value = book.rent_price || 0;
                            document.getElementById('edit_stock').value = book.stock;
                            document.getElementById('edit_description').value = book.description;
                            
                            // Calculate pricing strategy fields based on existing prices
                            const price = parseFloat(book.price) || 0;
                            const rentPrice = parseFloat(book.rent_price) || 0;
                            const condition = book.condition || 'New';
                            
                            // Get condition multiplier from database or calculate based on condition
                            let conditionMultiplier = parseFloat(book.condition_multiplier) || 1.0;
                            if (!book.condition_multiplier) {
                                switch(condition) {
                                    case 'New': conditionMultiplier = 1.2; break;
                                    case 'Like New': conditionMultiplier = 1.1; break;
                                    case 'Very Good': conditionMultiplier = 1.0; break;
                                    case 'Good': conditionMultiplier = 0.9; break;
                                    case 'Fair': conditionMultiplier = 0.8; break;
                                    case 'Poor': conditionMultiplier = 0.7; break;
                                }
                            }
                            
                            // Set fixed values for handling fee, listing fee, and markup percentage
                            let handlingFee = 10; // Fixed value
                            let listingFee = 30;  // Fixed value
                            let markupPercentage = 30; // Fixed value
                            
                            // Get base rental fee from database or calculate
                            let baseRentalFee = parseFloat(book.base_rental_fee) || 0;
                            
                            // If the value doesn't exist in the database, calculate it
                            if (!book.base_rental_fee) {
                                // Calculate base rental fee from rent price
                                if (rentPrice > 0) {
                                    baseRentalFee = Math.max(5, (rentPrice / conditionMultiplier) - handlingFee);
                                } else {
                                    baseRentalFee = 50; // Default value
                                }
                            }
                            
                            // Get book value from database or calculate
                            let bookValue = parseFloat(book.book_value) || 0;
                            
                            // If the value doesn't exist in the database, calculate it
                            if (!book.book_value) {
                                // Calculate book value from price
                                if (price > 0) {
                                    const estimatedBaseValue = price / 1.3;
                                    bookValue = Math.max(10, estimatedBaseValue - listingFee);
                                } else {
                                    bookValue = 200; // Default value
                                }
                            }
                            
                            // Set the calculated values
                            document.getElementById('edit_base_rental_fee').value = baseRentalFee.toFixed(2);
                            document.getElementById('edit_handling_fee').value = handlingFee.toFixed(2);
                            document.getElementById('edit_condition_multiplier').value = conditionMultiplier.toFixed(2);
                            document.getElementById('edit_book_value').value = bookValue.toFixed(2);
                            document.getElementById('edit_listing_fee').value = listingFee.toFixed(2);
                            document.getElementById('edit_markup_percentage').value = markupPercentage;
                            
                            // Update the suggested prices
                            document.getElementById('edit_suggested_rent_price').textContent = rentPrice.toFixed(2);
                            document.getElementById('edit_suggested_price').textContent = price.toFixed(2);
                            
                            // Call updateEditConditionMultiplier to ensure multiplier is correctly set
                            updateEditConditionMultiplier();
                            
                            // Show current cover image preview if available
                            const coverPreview = document.getElementById('current_cover_preview');
                            if (book.cover_image) {
                                coverPreview.innerHTML = `
                                    <img src="${book.cover_image}" alt="Current cover" style="max-height: 100px; max-width: 100%;" class="img-thumbnail">
                                    <p class="mt-1 mb-0 small">Current cover image</p>
                                `;
                            } else {
                                coverPreview.innerHTML = '<p class="text-muted">No cover image</p>';
                            }
                            
                            // Hide loader and show content
                            editBookLoader.style.display = 'none';
                            editBookContent.style.display = 'block';
                        } else {
                            // Handle error
                            alert('Error loading book details: ' + data.message);
                            const modal = bootstrap.Modal.getInstance(editBookModal);
                            modal.hide();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while loading book details.');
                        const modal = bootstrap.Modal.getInstance(editBookModal);
                        modal.hide();
                    });
            });
            
            // Delete Book Modal
            const deleteBookModal = document.getElementById('deleteBookModal');
            deleteBookModal.addEventListener('show.bs.modal', function(event) {
                // Button that triggered the modal
                const button = event.relatedTarget;
                
                // Extract book info
                const bookId = button.getAttribute('data-book-id');
                const bookTitle = button.getAttribute('data-book-title');
                
                // Update the modal content
                document.getElementById('delete-book-title').textContent = bookTitle;
                document.getElementById('delete_book_id').value = bookId;
            });
            
            // Select All Checkbox
            const selectAllCheckbox = document.getElementById('selectAll');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const isChecked = this.checked;
                    document.querySelectorAll('.book-select').forEach(checkbox => {
                        checkbox.checked = isChecked;
                    });
                });
            }
            
            // Search functionality
            const searchBooks = document.getElementById('searchBooks');
            if (searchBooks) {
                searchBooks.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    const bookRows = document.querySelectorAll('tbody tr');
                    const bookCards = document.querySelectorAll('.book-grid .col-md-6');
                    
                    // Search in table view
                    bookRows.forEach(row => {
                        // Skip empty state row
                        if (row.querySelector('td[colspan]')) return;
                        
                        const title = row.querySelector('.d-flex .text-muted')?.parentElement?.firstElementChild?.textContent.toLowerCase() || '';
                        const author = row.querySelector('.d-flex .text-muted')?.textContent.toLowerCase() || '';
                        const isbn = row.cells[2]?.textContent.toLowerCase() || '';
                        const genre = row.cells[3]?.textContent.toLowerCase() || '';
                        const type = row.cells[4]?.textContent.toLowerCase() || '';
                        const condition = row.cells[5]?.textContent.toLowerCase() || '';
                        
                        if (title.includes(searchTerm) || 
                            author.includes(searchTerm) || 
                            isbn.includes(searchTerm) || 
                            genre.includes(searchTerm) ||
                            type.includes(searchTerm) ||
                            condition.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Search in grid view
                    bookCards.forEach(card => {
                        const title = card.querySelector('.card-title')?.textContent.toLowerCase() || '';
                        const author = card.querySelector('.card-text.text-muted')?.textContent.toLowerCase() || '';
                        const isbn = card.querySelector('small:nth-of-type(1)')?.textContent.toLowerCase() || '';
                        const genre = card.querySelector('small:nth-of-type(2)')?.textContent.toLowerCase() || '';
                        const type = card.querySelector('.badge.bg-info')?.textContent.toLowerCase() || '';
                        const condition = card.querySelector('.badge:not(.bg-info):not(.bg-success):not(.bg-danger)')?.textContent.toLowerCase() || '';
                        
                        if (title.includes(searchTerm) || 
                            author.includes(searchTerm) || 
                            isbn.includes(searchTerm) || 
                            genre.includes(searchTerm) ||
                            type.includes(searchTerm) ||
                            condition.includes(searchTerm)) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                    
                    // Check if there are any visible rows
                    const visibleRows = Array.from(bookRows).filter(row => row.style.display !== 'none');
                    const tbody = document.querySelector('tbody');
                    
                    // Show "No results found" if all rows are hidden
                    if (visibleRows.length === 0 && searchTerm !== '' && !document.getElementById('no-results-row')) {
                        const noResultsRow = document.createElement('tr');
                        noResultsRow.id = 'no-results-row';
                        noResultsRow.innerHTML = `
                            <td colspan="11" class="text-center py-3">
                                <p>No books found matching "${searchTerm}"</p>
                            </td>
                        `;
                        tbody.appendChild(noResultsRow);
                    } else if ((visibleRows.length > 0 || searchTerm === '') && document.getElementById('no-results-row')) {
                        document.getElementById('no-results-row').remove();
                    }
                });
            }
            
            // View toggling (Table/Grid)
            const tableViewBtn = document.getElementById('tableViewBtn');
            const gridViewBtn = document.getElementById('gridViewBtn');
            const tableView = document.querySelector('.table-responsive');
            const gridView = document.getElementById('bookGridView');

            if (tableViewBtn && gridViewBtn && tableView && gridView) {
                // Table view (default)
                tableViewBtn.addEventListener('click', function() {
                    tableView.style.display = 'block';
                    gridView.style.display = 'none';
                    tableViewBtn.classList.add('active');
                    gridViewBtn.classList.remove('active');
                    
                    // Save preference to localStorage
                    localStorage.setItem('bookwagon_view_preference', 'table');
                });
                
                // Grid view
                gridViewBtn.addEventListener('click', function() {
                    tableView.style.display = 'none';
                    gridView.style.display = 'flex';
                    gridViewBtn.classList.add('active');
                    tableViewBtn.classList.remove('active');
                    
                    // Save preference to localStorage
                    localStorage.setItem('bookwagon_view_preference', 'grid');
                });
                
                // Load saved preference (if any)
                const savedViewPreference = localStorage.getItem('bookwagon_view_preference');
                if (savedViewPreference === 'grid') {
                    gridViewBtn.click();
                }
            }
        });

        // Function to set condition multiplier based on selected condition
        function updateConditionMultiplier() {
            const conditionSelect = document.getElementById('condition');
            const multiplierInput = document.getElementById('condition_multiplier');
            
            // Set multiplier based on condition
            switch(conditionSelect.value) {
                case 'New':
                    multiplierInput.value = 1.2;
                    break;
                case 'Like New':
                    multiplierInput.value = 1.1;
                    break;
                case 'Very Good':
                    multiplierInput.value = 1.0;
                    break;
                case 'Good':
                    multiplierInput.value = 0.9;
                    break;
                case 'Fair':
                    multiplierInput.value = 0.8;
                    break;
                case 'Poor':
                    multiplierInput.value = 0.7;
                    break;
                default:
                    multiplierInput.value = 1.0;
            }
        }
        
        // Function to calculate prices based on the formulas
        function calculatePrices() {
            // Get rental pricing inputs
            const baseRentalFee = parseFloat(document.getElementById('base_rental_fee').value) || 0;
            const handlingFee = parseFloat(document.getElementById('handling_fee').value) || 0;
            const conditionMultiplier = parseFloat(document.getElementById('condition_multiplier').value) || 1;
            
            // Get sales pricing inputs
            const bookValue = parseFloat(document.getElementById('book_value').value) || 0;
            const listingFee = parseFloat(document.getElementById('listing_fee').value) || 0;
            const markupPercentage = parseFloat(document.getElementById('markup_percentage').value) || 0;
            const markup = markupPercentage / 100;
            
            // Calculate rental price: SP = (Base Rental Fee + Handling) × Condition Multiplier
            const suggestedRentPrice = (baseRentalFee + handlingFee) * conditionMultiplier;
            
            // Calculate sales price: SP = (Book Value + Listing Fee) × (1 + Markup)
            const suggestedPrice = (bookValue + listingFee) * (1 + markup);
            
            // Update the displayed suggested prices
            document.getElementById('suggested_rent_price').textContent = suggestedRentPrice.toFixed(2);
            document.getElementById('suggested_price').textContent = suggestedPrice.toFixed(2);
            
            // Update the actual input fields with the calculated values
            document.getElementById('rent_price').value = suggestedRentPrice.toFixed(2);
            document.getElementById('price').value = suggestedPrice.toFixed(2);
        }
        
        // Call the function when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateConditionMultiplier();
            calculatePrices();
            
            // Also set up the edit form
            const editConditionSelect = document.getElementById('edit_condition');
            if (editConditionSelect) {
                editConditionSelect.addEventListener('change', function() {
                    updateEditConditionMultiplier();
                    calculateEditPrices();
                });
            }
        });

        // Function to set condition multiplier for edit form
        function updateEditConditionMultiplier() {
            const conditionSelect = document.getElementById('edit_condition');
            const multiplierInput = document.getElementById('edit_condition_multiplier');
            
            // Set multiplier based on condition
            switch(conditionSelect.value) {
                case 'New':
                    multiplierInput.value = 1.2;
                    break;
                case 'Like New':
                    multiplierInput.value = 1.1;
                    break;
                case 'Very Good':
                    multiplierInput.value = 1.0;
                    break;
                case 'Good':
                    multiplierInput.value = 0.9;
                    break;
                case 'Fair':
                    multiplierInput.value = 0.8;
                    break;
                case 'Poor':
                    multiplierInput.value = 0.7;
                    break;
                default:
                    multiplierInput.value = 1.0;
            }
        }
        
        // Function to calculate prices for edit form
        function calculateEditPrices() {
            // Get rental pricing inputs
            const baseRentalFee = parseFloat(document.getElementById('edit_base_rental_fee').value) || 0;
            const handlingFee = parseFloat(document.getElementById('edit_handling_fee').value) || 0;
            const conditionMultiplier = parseFloat(document.getElementById('edit_condition_multiplier').value) || 1;
            
            // Get sales pricing inputs
            const bookValue = parseFloat(document.getElementById('edit_book_value').value) || 0;
            const listingFee = parseFloat(document.getElementById('edit_listing_fee').value) || 0;
            const markupPercentage = parseFloat(document.getElementById('edit_markup_percentage').value) || 0;
            const markup = markupPercentage / 100;
            
            // Calculate rental price: SP = (Base Rental Fee + Handling) × Condition Multiplier
            const suggestedRentPrice = (baseRentalFee + handlingFee) * conditionMultiplier;
            
            // Calculate sales price: SP = (Book Value + Listing Fee) × (1 + Markup)
            const suggestedPrice = (bookValue + listingFee) * (1 + markup);
            
            // Update the displayed suggested prices
            document.getElementById('edit_suggested_rent_price').textContent = suggestedRentPrice.toFixed(2);
            document.getElementById('edit_suggested_price').textContent = suggestedPrice.toFixed(2);
            
            // Update the actual input fields with the calculated values
            document.getElementById('edit_rent_price').value = suggestedRentPrice.toFixed(2);
            document.getElementById('edit_price').value = suggestedPrice.toFixed(2);
        }
    </script>
    
</body>
</html>