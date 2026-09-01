<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? '';
$firstName = $_SESSION['firstname'] ?? '';
$lastName = $_SESSION['lastname'] ?? '';
$email = $_SESSION['email'] ?? '';
$phone = $_SESSION['phone'] ?? '';
$photo = $_SESSION['profile_picture'] ?? '';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookWagon - Book Listing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/tab.css">
    
    <style>
        :root {
            --primary-color: #f8a100;
            --secondary-color: #f8f9fa;
            --text-dark: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
        }
        body {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
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
        
        .book-card {
            transition: transform 0.3s;
            height: 100%;
        }
        .book-card:hover {
            transform: translateY(-5px);
        }
        .book-img {
            width: 150px;
            height: 220px;
            object-fit: cover;
            display: block;
            margin: 0 auto; /* Center the image */
            transition: opacity 0.3s ease-in-out;
        }
        .filter-section {
            border-right: 1px solid #dee2e6;
            padding-right: 20px;
        }
        .filter-group {
            margin-bottom: 20px;
        }
        .filter-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .book-price {
            font-weight: bold;
            font-size: 1.2rem;
        }
        .price-per-week {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .book-rating i {
            color: #ffc107;
        }
        .star-gray {
            color: #e0e0e0 !important;
        }
        .card-actions {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .action-icon {
            background: rgba(255,255,255,0.8);
            border-radius: 50%;
            padding: 8px;
            display: inline-block;
            margin-bottom: 5px;
            cursor: pointer;
        }
        .search-container {
            margin-bottom: 20px;
        }
        /* Loading spinner */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255,255,255,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--primary-color);
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Image placeholder while loading */
        .image-placeholder {
            background-color: #f0f0f0;
            width: 150px;
            height: 220px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        /* Book badges */
        .book-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 10;
        }
        /* Book link styles */
        .book-link {
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }
        .book-link:hover {
            text-decoration: none;
            color: inherit;
        }
        /* Filter section scrollable on mobile */
        @media (max-width: 992px) {
            .filter-section {
                max-height: 300px;
                overflow-y: auto;
                border-right: none;
                border-bottom: 1px solid #dee2e6;
                margin-bottom: 20px;
                padding-bottom: 20px;
            }
        }
        /* Seller info styles */
        .seller-info {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            gap: 8px;
        }
        .seller-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            overflow: hidden;
            background-color: #f1f1f1;
            color: #6c757d;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .seller-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .seller-name {
            font-size: 0.85rem;
            color: #6c757d;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body>

<?php include("include/user_header.php"); ?>

<?php include('include/tab.php'); ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <!-- Left sidebar for filters -->
            <div class="col-lg-3 filter-section">
                <!-- Price Filter -->
                <div class="filter-group">
                    <h5 class="filter-title">Price</h5>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control" placeholder="Min" name="min_price" id="min_price" value="<?php echo isset($_GET['min_price']) ? $_GET['min_price'] : ''; ?>">
                        <input type="text" class="form-control" placeholder="Max" name="max_price" id="max_price" value="<?php echo isset($_GET['max_price']) ? $_GET['max_price'] : ''; ?>">
                    </div>
                </div>
                
                <!-- Book Type Filter (New) -->
                <div class="filter-group">
                    <h5 class="filter-title">Book Type</h5>
                    <div class="form-check mb-2">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="book_type" id="type_paperback" value="Paperback" <?php echo (isset($_GET['book_type']) && $_GET['book_type'] == 'Paperback') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="type_paperback">Paperback</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="book_type" id="type_hardcover" value="Hardcover" <?php echo (isset($_GET['book_type']) && $_GET['book_type'] == 'Hardcover') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="type_hardcover">Hardcover</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="book_type" id="type_ebook" value="E-book" <?php echo (isset($_GET['book_type']) && $_GET['book_type'] == 'E-book') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="type_ebook">E-book</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="book_type" id="type_audiobook" value="Audiobook" <?php echo (isset($_GET['book_type']) && $_GET['book_type'] == 'Audiobook') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="type_audiobook">Audiobook</label>
                    </div>
                </div>
                
                <!-- Condition Filter (New) -->
                <div class="filter-group">
                    <h5 class="filter-title">Condition</h5>
                    <div class="form-check mb-2">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="condition" id="condition_new" value="New" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'New') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="condition_new">New</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="condition" id="condition_like_new" value="Like New" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'Like New') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="condition_like_new">Like New</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="condition" id="condition_very_good" value="Very Good" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'Very Good') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="condition_very_good">Very Good</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="condition" id="condition_good" value="Good" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'Good') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="condition_good">Good</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="condition" id="condition_fair" value="Fair" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'Fair') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="condition_fair">Fair</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input filter-checkbox" type="checkbox" name="condition" id="condition_poor" value="Poor" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'Poor') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="condition_poor">Poor</label>
                    </div>
                </div>
                
                <!-- Popularity Filter -->
                <div class="filter-group">
                    <h5 class="filter-title">Popularity</h5>
                    <?php
                    // Fetch popularity categories from database
                    $conn = new mysqli("localhost", "root", "", "bookwagon_db"); // Replace with your actual connection details
                    
                    if ($conn->connect_error) {
                        die("Connection failed: " . $conn->connect_error);
                    }
                    
                    $popularity_query = "SELECT DISTINCT popularity FROM books WHERE popularity IS NOT NULL ORDER BY popularity";
                    $popularity_result = $conn->query($popularity_query);
                    
                    if ($popularity_result->num_rows > 0) {
                        while($pop = $popularity_result->fetch_assoc()) {
                            $checked = (isset($_GET['popularity']) && $_GET['popularity'] == $pop['popularity']) ? 'checked' : '';
                            echo '<div class="form-check mb-2">
                                    <input class="form-check-input filter-checkbox" type="checkbox" name="popularity" id="pop_'.str_replace(' ', '_', $pop['popularity']).'" value="'.$pop['popularity'].'" '.$checked.'>
                                    <label class="form-check-label" for="pop_'.str_replace(' ', '_', $pop['popularity']).'">
                                        '.$pop['popularity'].'
                                    </label>
                                </div>';
                        }
                    } else {
                        echo '<p>No popularity categories found</p>';
                    }
                    ?>
                </div>
                
                <!-- Genres Filter -->
                <div class="filter-group">
                    <h5 class="filter-title">Genres</h5>
                    <?php
                    // Fetch genres from database
                    $genre_query = "SELECT DISTINCT genre FROM books WHERE genre IS NOT NULL ORDER BY genre";
                    $genre_result = $conn->query($genre_query);
                    
                    if ($genre_result->num_rows > 0) {
                        while($genre = $genre_result->fetch_assoc()) {
                            $checked = (isset($_GET['genre']) && $_GET['genre'] == $genre['genre']) ? 'checked' : '';
                            echo '<div class="form-check mb-2">
                                    <input class="form-check-input filter-checkbox" type="checkbox" name="genre" id="genre_'.str_replace(' ', '_', $genre['genre']).'" value="'.$genre['genre'].'" '.$checked.'>
                                    <label class="form-check-label" for="genre_'.str_replace(' ', '_', $genre['genre']).'">
                                        '.$genre['genre'].'
                                    </label>
                                </div>';
                        }
                    } else {
                        echo '<p>No genres found</p>';
                    }
                    ?>
                </div>
                
                <!-- Authors Filter -->
                <div class="filter-group">
                    <h5 class="filter-title">Authors</h5>
                    <?php
                    // Fetch authors from database
                    $author_query = "SELECT DISTINCT author FROM books WHERE author IS NOT NULL ORDER BY author";
                    $author_result = $conn->query($author_query);
                    
                    if ($author_result->num_rows > 0) {
                        while($author = $author_result->fetch_assoc()) {
                            $checked = (isset($_GET['author']) && $_GET['author'] == $author['author']) ? 'checked' : '';
                            echo '<div class="form-check mb-2">
                                    <input class="form-check-input filter-checkbox" type="checkbox" name="author" id="author_'.str_replace(' ', '_', $author['author']).'" value="'.$author['author'].'" '.$checked.'>
                                    <label class="form-check-label" for="author_'.str_replace(' ', '_', $author['author']).'">
                                        '.$author['author'].'
                                    </label>
                                </div>';
                        }
                    } else {
                        echo '<p>No authors found</p>';
                    }
                    ?>
                </div>
                
                <!-- Theme Filter -->
                <div class="filter-group">
                    <h5 class="filter-title">Theme</h5>
                    <?php
                    // Fetch themes from database
                    $theme_query = "SELECT DISTINCT theme FROM books WHERE theme IS NOT NULL ORDER BY theme";
                    $theme_result = $conn->query($theme_query);
                    
                    if ($theme_result->num_rows > 0) {
                        while($theme = $theme_result->fetch_assoc()) {
                            $checked = (isset($_GET['theme']) && $_GET['theme'] == $theme['theme']) ? 'checked' : '';
                            echo '<div class="form-check mb-2">
                                    <input class="form-check-input filter-checkbox" type="checkbox" name="theme" id="theme_'.str_replace(' ', '_', $theme['theme']).'" value="'.$theme['theme'].'" '.$checked.'>
                                    <label class="form-check-label" for="theme_'.str_replace(' ', '_', $theme['theme']).'">
                                        '.$theme['theme'].'
                                    </label>
                                </div>';
                        }
                    } else {
                        echo '<p>No themes found</p>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- Right content area for book listings -->
            <div class="col-lg-9 position-relative">
                <!-- Loader (hidden by default) -->
                <div class="loading-overlay d-none" id="loading_overlay">
                    <div class="spinner"></div>
                </div>
                
                <!-- Search and sort bar -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="search-container flex-grow-1 me-3">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" placeholder="Search" id="search" value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                        </div>
                    </div>
                    <div class="sort-container d-flex align-items-center">
                        <span class="me-2">Sort by</span>
                        <select class="form-select" id="sort_by">
                            <option value="title" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'title') ? 'selected' : ''; ?>>Featured</option>
                            <option value="price_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="newest" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'newest') ? 'selected' : ''; ?>>Newest</option>
                        </select>
                        <div class="ms-3">
                            <button class="btn btn-outline-secondary btn-sm me-1 active" id="grid_view">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="list_view">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Book listings container -->
                <div class="row row-cols-1 row-cols-md-3 g-4" id="book_container">
                    <?php
                    // Initial book loading code - this will be replaced by AJAX after the first load
                    include('get_books.php'); // Create this file to handle both initial load and AJAX requests
                    ?>
                </div>
                
                <!-- Pagination container - will be updated by AJAX -->
                <div id="pagination_container" class="mt-4">
                    <?php include('get_pagination.php'); ?> <!-- Create this file for pagination -->
                </div>
            </div>
        </div>
    </div>

    <!-- BookWagon Global Footer -->
    <?php include("include/footer.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global vars for current filter state
        let currentPage = <?php echo isset($_GET['page']) ? (int)$_GET['page'] : 1; ?>;
        let currentViewMode = 'grid'; // Default view mode
        
        // Function to handle image loading
        function handleImageLoading() {
            const bookImages = document.querySelectorAll('.book-img');
            
            bookImages.forEach(img => {
                // Add load event listener to each image
                img.addEventListener('load', function() {
                    this.style.opacity = '1';
                });
                
                // Add error event listener to handle failed image loads
                img.addEventListener('error', function() {
                    this.src = 'uploads/covers/default_book.jpg';
                    this.style.opacity = '1';
                });
            });
            
            // Handle seller profile images
            const sellerImages = document.querySelectorAll('.seller-avatar img');
            sellerImages.forEach(img => {
                img.addEventListener('error', function() {
                    // Replace with the first initial of the seller's name
                    const sellerName = this.closest('.seller-info').querySelector('.seller-name').textContent.trim();
                    const initial = sellerName.charAt(0);
                    this.parentNode.innerHTML = initial;
                });
            });
        }
        
        // Load books via AJAX with filters
        function loadBooks() {
            // Show loading overlay
            document.getElementById('loading_overlay').classList.remove('d-none');
            
            // Build filter object
            const filters = {
                min_price: document.getElementById('min_price').value,
                max_price: document.getElementById('max_price').value,
                search: document.getElementById('search').value,
                sort: document.getElementById('sort_by').value,
                page: currentPage
            };
            
            // Add selected checkbox filters
            document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                // Group multiple checkboxes of the same name with commas
                if (filters[checkbox.name]) {
                    filters[checkbox.name] += ',' + checkbox.value;
                } else {
                    filters[checkbox.name] = checkbox.value;
                }
            });
            
            // Convert filters to URL params
            const params = new URLSearchParams();
            for (const key in filters) {
                if (filters[key]) {
                    params.append(key, filters[key]);
                }
            }
            
            // Update browser URL without reloading
            const newUrl = `${window.location.pathname}?${params.toString()}`;
            window.history.pushState({ path: newUrl }, '', newUrl);
            
            // Fetch books via AJAX
            fetch(`get_books.php?${params.toString()}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('book_container').innerHTML = html;
                    
                    // Handle image loading
                    handleImageLoading();
                    
                    // Reattach event listeners to new elements
                    attachEventListeners();
                    
                    // Hide loading overlay
                    document.getElementById('loading_overlay').classList.add('d-none');
                    
                    // Update pagination
                    fetch(`get_pagination.php?${params.toString()}`)
                        .then(response => response.text())
                        .then(html => {
                            document.getElementById('pagination_container').innerHTML = html;
                            attachPaginationListeners();
                        });
                })
                .catch(error => {
                    console.error('Error loading books:', error);
                    document.getElementById('loading_overlay').classList.add('d-none');
                });
        }
        
        // Attach event listeners to book action icons
        function attachEventListeners() {
            // Heart icons
            document.querySelectorAll('.fa-heart').forEach(icon => {
                icon.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent following the link
                    e.stopPropagation(); // Stop the event from bubbling up
                    
                    this.classList.toggle('far');
                    this.classList.toggle('fas');
                    
                    const bookId = this.getAttribute('data-book-id');
                    const isFavorite = this.classList.contains('fas');
                    
                    // Add AJAX request to update favorites in database
                    fetch('update_favorite.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `book_id=${bookId}&is_favorite=${isFavorite ? 1 : 0}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Favorite updated:', data);
                    })
                    .catch(error => {
                        console.error('Error updating favorite:', error);
                    });
                });
            });
            
            // Bookmark icons
            document.querySelectorAll('.fa-bookmark').forEach(icon => {
                icon.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent following the link
                    e.stopPropagation(); // Stop the event from bubbling up
                    
                    this.classList.toggle('far');
                    this.classList.toggle('fas');
                    
                    const bookId = this.getAttribute('data-book-id');
                    const isBookmarked = this.classList.contains('fas');
                    
                    // Add AJAX request to update bookmarks in database
                    fetch('update_bookmark.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `book_id=${bookId}&is_bookmarked=${isBookmarked ? 1 : 0}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Bookmark updated:', data);
                    })
                    .catch(error => {
                        console.error('Error updating bookmark:', error);
                    });
                });
            });
        }
        
        // Attach event listeners to pagination links
        function attachPaginationListeners() {
            document.querySelectorAll('.page-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Extract page number from href
                    const url = new URL(this.href);
                    currentPage = url.searchParams.get('page') || 1;
                    
                    // Load books with new page
                    loadBooks();
                    
                    // Scroll to top of book container
                    document.getElementById('book_container').scrollIntoView({ behavior: 'smooth' });
                });
            });
        }
        
        // Filter event listeners
        document.querySelectorAll('.filter-checkbox').forEach(element => {
            element.addEventListener('change', function() {
                currentPage = 1; // Reset to page 1 when filters change
                loadBooks();
            });
        });
        
        document.getElementById('sort_by').addEventListener('change', function() {
            loadBooks();
        });
        
        document.getElementById('search').addEventListener('input', function() {
            // Delay search to prevent too many requests
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                currentPage = 1; // Reset to page 1 when search changes
                loadBooks();
            }, 500);
        });
        
        document.getElementById('min_price').addEventListener('input', function() {
            clearTimeout(this.priceTimer);
            this.priceTimer = setTimeout(() => {
                currentPage = 1; // Reset to page 1 when price changes
                loadBooks();
            }, 500);
        });
        
        document.getElementById('max_price').addEventListener('input', function() {
            clearTimeout(this.priceTimer);
            this.priceTimer = setTimeout(() => {
                currentPage = 1; // Reset to page 1 when price changes
                loadBooks();
            }, 500);
        });
        
        // Grid/List view toggle
        document.getElementById('grid_view').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('list_view').classList.remove('active');
            document.getElementById('book_container').className = 'row row-cols-1 row-cols-md-3 g-4';
            currentViewMode = 'grid';
        });
        
        document.getElementById('list_view').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('grid_view').classList.remove('active');
            document.getElementById('book_container').className = 'row row-cols-1 g-4';
            currentViewMode = 'list';
        });
        
        // Initial setup
        attachEventListeners();
        attachPaginationListeners();
        handleImageLoading();
    </script>
</body>
</html>