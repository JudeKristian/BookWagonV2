<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? '';
$firstName = $_SESSION['firstname'] ?? '';
$lastName = $_SESSION['lastname'] ?? '';
$email = $_SESSION['email'] ?? '';
$userId = $_SESSION['id'] ?? 0;

// Redirect if not logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Check if the collections table exists, if not create it
$check_table = "SHOW TABLES LIKE 'book_collections'";
$table_exists = $conn->query($check_table);

if ($table_exists->num_rows == 0) {
    // Table doesn't exist, create it
    $create_table = "CREATE TABLE book_collections (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) NOT NULL,
        collection_type ENUM('done_reading','wishlist','looking_for','book_hunt','need_to_read') NOT NULL,
        notes TEXT DEFAULT NULL,
        book_image VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($create_table)) {
        $_SESSION['error_message'] = "Error creating collections table: " . $conn->error;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new book to collection
    if (isset($_POST['add_book'])) {
        $bookTitle = trim($_POST['book_title']);
        $author = trim($_POST['author']);
        $collectionType = $_POST['collection_type'];
        $notes = trim($_POST['notes']);
        $bookImage = '';
        
        // Handle image upload if provided
        if(isset($_FILES['book_image']) && $_FILES['book_image']['error'] == 0) {
            $upload_dir = 'uploads/book_images/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $temp_name = $_FILES['book_image']['tmp_name'];
            $name = basename($_FILES['book_image']['name']);
            $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            // Check if the file is an image
            $allowed_exts = array('jpg', 'jpeg', 'png', 'gif');
            
            if(in_array($file_ext, $allowed_exts)) {
                // Generate unique filename
                $new_filename = uniqid('book_') . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;
                
                if(move_uploaded_file($temp_name, $destination)) {
                    $bookImage = $destination;
                } else {
                    $_SESSION['error_message'] = "Failed to upload image";
                }
            } else {
                $_SESSION['error_message'] = "Only JPG, JPEG, PNG & GIF files are allowed";
            }
        }
        
        // Basic validation
        if (empty($bookTitle)) {
            $_SESSION['error_message'] = "Book title is required";
        } else {
            $insertQuery = "INSERT INTO book_collections (user_id, title, author, collection_type, notes, book_image) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("isssss", $userId, $bookTitle, $author, $collectionType, $notes, $bookImage);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Book added to your collection!";
                header("Location: collections.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error adding book: " . $conn->error;
            }
        }
    }
    
    // Remove book from collection
    if (isset($_POST['remove_book'])) {
        $bookId = $_POST['book_id'];
        
        $deleteQuery = "DELETE FROM book_collections WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("ii", $bookId, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Book removed from your collection";
            header("Location: collections.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error removing book: " . $conn->error;
        }
    }
    
    // Update book collection
    if (isset($_POST['update_book'])) {
        $bookId = $_POST['book_id'];
        $bookTitle = trim($_POST['book_title']);
        $author = trim($_POST['author']);
        $collectionType = $_POST['collection_type'];
        $notes = trim($_POST['notes']);
        $currentImage = $_POST['current_image'];
        
        // Handle image upload if provided
        if(isset($_FILES['book_image']) && $_FILES['book_image']['error'] == 0) {
            $upload_dir = 'uploads/book_images/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $temp_name = $_FILES['book_image']['tmp_name'];
            $name = basename($_FILES['book_image']['name']);
            $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            // Check if the file is an image
            $allowed_exts = array('jpg', 'jpeg', 'png', 'gif');
            
            if(in_array($file_ext, $allowed_exts)) {
                // Generate unique filename
                $new_filename = uniqid('book_') . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;
                
                if(move_uploaded_file($temp_name, $destination)) {
                    // Delete old image if exists and not default
                    if(!empty($currentImage) && file_exists($currentImage) && $currentImage != 'images/default-book.jpg') {
                        unlink($currentImage);
                    }
                    $currentImage = $destination;
                } else {
                    $_SESSION['error_message'] = "Failed to upload image";
                }
            } else {
                $_SESSION['error_message'] = "Only JPG, JPEG, PNG & GIF files are allowed";
            }
        }
        
        // Basic validation
        if (empty($bookTitle)) {
            $_SESSION['error_message'] = "Book title is required";
        } else {
            $updateQuery = "UPDATE book_collections 
                           SET title = ?, author = ?, collection_type = ?, notes = ?, book_image = ? 
                           WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("sssssii", $bookTitle, $author, $collectionType, $notes, $currentImage, $bookId, $userId);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Book updated successfully!";
                header("Location: collections.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Error updating book: " . $conn->error;
            }
        }
    }
}

// Get the active tab from URL parameter
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'done_reading';

// Get user's book collections grouped by type
$collectionsQuery = "SELECT * FROM book_collections WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($collectionsQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

// Initialize collections array
$collections = [
    'done_reading' => [],
    'wishlist' => [],
    'looking_for' => [],
    'book_hunt' => [],
    'need_to_read' => []
];

// Organize books by collection type
while ($book = $result->fetch_assoc()) {
    $collections[$book['collection_type']][] = $book;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Book Collections - BookWagon</title>
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
        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .navbar-brand img {
            height: 60px;
        }
        .collection-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 30px;
            height: 100%;
        }
        .book-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .book-item {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            position: relative;
        }
        .book-item:hover {
            transform: translateY(-5px);
        }
        .book-image {
            width: 100%;
            height: 280px;
            object-fit: cover;
            display: block;
            background-color: #f8f8f8;
        }
        .book-info {
            padding: 15px;
        }
        .book-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1rem;
            line-height: 1.3;
        }
        .book-author {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .book-notes {
            font-size: 0.85rem;
            margin-top: 5px;
            color: var(--text-dark);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .action-buttons {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
        }
        .action-button {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            color: var(--text-dark);
            transition: all 0.2s;
        }
        .action-button:hover {
            background-color: #fff;
            transform: scale(1.1);
        }
        .nav-tabs .nav-link {
            color: var(--text-muted);
            border: none;
            padding: 10px 15px;
            border-radius: 0;
            border-bottom: 3px solid transparent;
        }
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: transparent;
            border-bottom: 3px solid var(--primary-color);
        }
        .nav-tabs .nav-link:hover {
            border-color: transparent;
            border-bottom: 3px solid var(--border-color);
        }
        .empty-collection {
            padding: 30px;
            text-align: center;
            color: var(--text-muted);
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
        .price-tag {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
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
                    <a href="rented_books.php" class="sidebar-link">
                        <i class="fa-solid fa-book"></i> Rented Books
                    </a>
                    <a href="collections.php" class="sidebar-link active">
                        <i class="fa-solid fa-bookmark"></i> My Collections
                    </a>
                    <a href="history.php" class="sidebar-link">
                        <i class="fa-solid fa-clock-rotate-left"></i> History
                    </a>
                </div>
            </div>
            
            <!-- Main Content Column -->
            <div class="col-md-9">
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['success_message']; 
                            unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['error_message']; 
                            unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>My Book Collections</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
                        <i class="fas fa-plus"></i> Add Book
                    </button>
                </div>
                
                <!-- Collection Tabs -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab == 'done_reading' ? 'active' : ''; ?>" href="collections.php?tab=done_reading">
                            <i class="fas fa-check-circle me-2"></i>Done Reading
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab == 'wishlist' ? 'active' : ''; ?>" href="collections.php?tab=wishlist">
                            <i class="fas fa-heart me-2"></i>Wishlist
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab == 'looking_for' ? 'active' : ''; ?>" href="collections.php?tab=looking_for">
                            <i class="fas fa-search me-2"></i>Looking For
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab == 'book_hunt' ? 'active' : ''; ?>" href="collections.php?tab=book_hunt">
                            <i class="fas fa-binoculars me-2"></i>Book Hunt
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab == 'need_to_read' ? 'active' : ''; ?>" href="collections.php?tab=need_to_read">
                            <i class="fas fa-list-check me-2"></i>Need to Read
                        </a>
                    </li>
                </ul>
                
                <!-- Collection Content -->
                <div class="tab-content">
                    <div class="tab-pane fade show active">
                        <div class="collection-card">
                            <?php if (empty($collections[$activeTab])): ?>
                                <div class="empty-collection">
                                    <i class="fas fa-book-open mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                                    <h5>No books in this collection yet</h5>
                                    <p>Add books to your <?php echo str_replace('_', ' ', $activeTab); ?> collection by clicking the "Add Book" button.</p>
                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
                                        <i class="fas fa-plus"></i> Add Book
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="book-grid">
                                    <?php foreach ($collections[$activeTab] as $book): ?>
                                        <div class="book-item">
                                            <?php if (!empty($book['book_image']) && file_exists($book['book_image'])): ?>
                                                <img src="<?php echo $book['book_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="book-image">
                                            <?php else: ?>
                                                <div class="book-image d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-book" style="font-size: 3rem; opacity: 0.2;"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="action-buttons">
                                                <button class="action-button edit-book-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editBookModal"
                                                        data-id="<?php echo $book['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($book['title']); ?>"
                                                        data-author="<?php echo htmlspecialchars($book['author']); ?>"
                                                        data-type="<?php echo $book['collection_type']; ?>"
                                                        data-notes="<?php echo htmlspecialchars($book['notes']); ?>"
                                                        data-image="<?php echo $book['book_image']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form action="collections.php" method="post" class="d-inline">
                                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                                    <button type="submit" name="remove_book" class="action-button" 
                                                            onclick="return confirm('Are you sure you want to remove this book from your collection?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            
                                            <div class="book-info">
                                                <h5 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                                                <p class="book-author">by <?php echo htmlspecialchars($book['author'] ?: 'Unknown Author'); ?></p>
                                                <?php if (!empty($book['notes'])): ?>
                                                    <p class="book-notes"><?php echo htmlspecialchars($book['notes']); ?></p>
                                                <?php endif; ?>
                                                <small class="text-muted">Added on <?php echo date('M d, Y', strtotime($book['created_at'])); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Book Modal -->
    <div class="modal fade" id="addBookModal" tabindex="-1" aria-labelledby="addBookModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBookModalLabel">Add Book to Collection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="collections.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="book_title" class="form-label">Book Title*</label>
                            <input type="text" class="form-control" id="book_title" name="book_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="author" class="form-label">Author</label>
                            <input type="text" class="form-control" id="author" name="author">
                        </div>
                        <div class="mb-3">
                            <label for="collection_type" class="form-label">Collection</label>
                            <select class="form-select" id="collection_type" name="collection_type">
                                <option value="done_reading" <?php echo $activeTab == 'done_reading' ? 'selected' : ''; ?>>Done Reading</option>
                                <option value="wishlist" <?php echo $activeTab == 'wishlist' ? 'selected' : ''; ?>>Wishlist</option>
                                <option value="looking_for" <?php echo $activeTab == 'looking_for' ? 'selected' : ''; ?>>Looking For</option>
                                <option value="book_hunt" <?php echo $activeTab == 'book_hunt' ? 'selected' : ''; ?>>Book Hunt</option>
                                <option value="need_to_read" <?php echo $activeTab == 'need_to_read' ? 'selected' : ''; ?>>Need to Read</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="book_image" class="form-label">Book Cover Image</label>
                            <input type="file" class="form-control" id="book_image" name="book_image" accept="image/*">
                            <div class="form-text">Upload a cover image for the book (optional).</div>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Your thoughts, progress, or reminders about this book..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_book" class="btn btn-primary">Add to Collection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Book Modal -->
    <div class="modal fade" id="editBookModal" tabindex="-1" aria-labelledby="editBookModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBookModalLabel">Edit Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="collections.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" id="edit_book_id" name="book_id">
                    <input type="hidden" id="edit_current_image" name="current_image">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_book_title" class="form-label">Book Title*</label>
                            <input type="text" class="form-control" id="edit_book_title" name="book_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_author" class="form-label">Author</label>
                            <input type="text" class="form-control" id="edit_author" name="author">
                        </div>
                        <div class="mb-3">
                            <label for="edit_collection_type" class="form-label">Collection</label>
                            <select class="form-select" id="edit_collection_type" name="collection_type">
                                <option value="done_reading">Done Reading</option>
                                <option value="wishlist">Wishlist</option>
                                <option value="looking_for">Looking For</option>
                                <option value="book_hunt">Book Hunt</option>
                                <option value="need_to_read">Need to Read</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_book_image" class="form-label">Book Cover Image</label>
                            <input type="file" class="form-control" id="edit_book_image" name="book_image" accept="image/*">
                            <div class="form-text">Upload a new cover image (leave empty to keep current image).</div>
                            <div id="current_image_preview" class="mt-2 d-none">
                                <p>Current image:</p>
                                <img src="" alt="Current book cover" style="max-width: 100px; max-height: 150px;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_book" class="btn btn-primary">Update Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script>
        // Handle edit book modal data
        document.addEventListener('DOMContentLoaded', function() {
            const editButtons = document.querySelectorAll('.edit-book-btn');
            
            editButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const title = this.getAttribute('data-title');
                    const author = this.getAttribute('data-author');
                    const type = this.getAttribute('data-type');
                    const notes = this.getAttribute('data-notes');
                    const image = this.getAttribute('data-image');
                    
                    document.getElementById('edit_book_id').value = id;
                    document.getElementById('edit_book_title').value = title;
                    document.getElementById('edit_author').value = author;
                    document.getElementById('edit_collection_type').value = type;
                    document.getElementById('edit_notes').value = notes;
                    document.getElementById('edit_current_image').value = image;
                    
                    // Show current image preview if exists
                    const imagePreview = document.getElementById('current_image_preview');
                    if (image && image !== '') {
                        imagePreview.classList.remove('d-none');
                        imagePreview.querySelector('img').src = image;
                    } else {
                        imagePreview.classList.add('d-none');
                    }
                });
            });
        });
    </script>
</body>
</html> 