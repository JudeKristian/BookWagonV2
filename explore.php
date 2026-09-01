<?php
// Set default timezone
date_default_timezone_set('Asia/Manila'); // Philippines timezone

include("session.php");
// Database configuration
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "bookwagon_db";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("System error: Unable to connect to the database.");
}

$userType = $_SESSION['usertype'] ?? ''; 
$firstName = $_SESSION['firstname'] ?? ''; 
$lastName = $_SESSION['lastname'] ?? ''; 
$email = $_SESSION['email'] ?? '';
$phone = $_SESSION['phone'] ?? '';
$photo = $_SESSION['profile_picture'] ?? '';
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check user authentication and type
$userType = isset($_SESSION['usertype']) ? $_SESSION['usertype'] : 'guest';

// Pagination settings
$resultsPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $resultsPerPage;

// Filter and search parameters
$searchQuery = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sortBy = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : 'newest';
$tag = isset($_GET['tag']) ? $conn->real_escape_string($_GET['tag']) : '';

// Fetch categories
$categoryQuery = "SELECT * FROM forum_categories ORDER BY order_index ASC";
$categoryResult = $conn->query($categoryQuery);
$categories = [];
while ($cat = $categoryResult->fetch_assoc()) {
    $categories[] = $cat;
}

// Build dynamic query for posts
$whereConditions = [];
if (!empty($searchQuery)) {
    $whereConditions[] = "(fp.title LIKE '%$searchQuery%' OR fp.content LIKE '%$searchQuery%' OR fp.tags LIKE '%$searchQuery%')";
}
if (!empty($category) && $category > 0) {
    $whereConditions[] = "fp.category_id = $category";
}
if (!empty($tag)) {
    $whereConditions[] = "fp.tags LIKE '%$tag%'";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Sorting logic
$orderByClause = match($sortBy) {
    'most_comments' => 'ORDER BY comment_count DESC, fp.created_at DESC',
    'most_views' => 'ORDER BY fp.views DESC, fp.created_at DESC',
    'oldest' => 'ORDER BY fp.created_at ASC',
    default => 'ORDER BY fp.is_pinned DESC, fp.created_at DESC'
};

// Total results query
$totalQuery = "SELECT COUNT(*) as total FROM forum_posts fp $whereClause";
$totalResult = $conn->query($totalQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $resultsPerPage);

// Main forum posts query with comment count and user info
$postsQuery = "SELECT fp.*, 
               fc.name as category_name, 
               fc.color as category_color,
               u.firstname, 
               u.lastname, 
               u.email,
               u.profile_picture,
               (SELECT COUNT(*) FROM forum_comments WHERE post_id = fp.post_id) as comment_count,
               (SELECT COUNT(*) FROM forum_user_interactions WHERE post_id = fp.post_id AND interaction_type = 'like') as likes,
               (SELECT COUNT(*) > 0 FROM forum_user_interactions WHERE post_id = fp.post_id AND user_id = $userId AND interaction_type = 'like') as user_liked
               FROM forum_posts fp
               LEFT JOIN forum_categories fc ON fp.category_id = fc.category_id
               LEFT JOIN users u ON fp.user_id = u.id
               $whereClause 
               $orderByClause 
               LIMIT $resultsPerPage OFFSET $offset";
$postsResult = $conn->query($postsQuery);

// Fetch recent active posts for sidebar
$recentQuery = "SELECT fp.post_id, fp.title, fp.created_at, u.id as user_id, u.firstname, u.lastname
                FROM forum_posts fp
                LEFT JOIN users u ON fp.user_id = u.id
                ORDER BY fp.created_at DESC LIMIT 5";
$recentResult = $conn->query($recentQuery);

// Fetch popular tags
$tagsQuery = "SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(tags, ',', numbers.n), ',', -1) tag
              FROM forum_posts
              CROSS JOIN (
                SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
              ) numbers
              WHERE CHAR_LENGTH(tags) - CHAR_LENGTH(REPLACE(tags, ',', '')) >= numbers.n - 1
              GROUP BY tag
              ORDER BY COUNT(*) DESC
              LIMIT 15";
$tagsResult = $conn->query($tagsQuery);
$popularTags = [];
if ($tagsResult) {
    while ($tag = $tagsResult->fetch_assoc()) {
        if (!empty(trim($tag['tag']))) {
            $popularTags[] = trim($tag['tag']);
        }
    }
}

// Function to get time elapsed string
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    
    // If the post time appears to be in the future (due to timezone issues)
    // just show "just now" instead of negative time
    if ($now < $ago) {
        return "just now";
    }
    
    $diff = $now->diff($ago);

    // If less than a minute, show "just now"
    if ($diff->i == 0 && $diff->s < 60 && $diff->h == 0 && $diff->d == 0 && $diff->m == 0 && $diff->y == 0) {
        return "just now";
    }

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookwagon - Community Forum</title>
    <!-- Bootstrap and font libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="css/tab.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #d9b99b;
            --primary-dark: #c4a278;
            --primary-light: #f2e6d9;
            --secondary-color: #6c757d;
            --accent-color: #ffd166;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
            --text-color: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --border-radius: 0.5rem;
            --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --forum-bg: #ffffff;
        }

        body {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
            background-color: #ffffff;
            color: var(--text-color);
            line-height: 1.6;
        }

        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            z-index: 1050; /* Higher z-index than other elements */
        }
        
        .navbar-brand img {
            height: 60px;
        }

        .forum-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            overflow: hidden;
        }
        
        .card-forum-post {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .card-forum-post:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .category-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            display: inline-block;
            color: white;
            font-weight: 500;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-sm {
            width: 30px;
            height: 30px;
            font-size: 0.8rem;
        }
        
        .avatar-lg {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }
        
        .post-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            line-height: 1.4;
            color: var(--dark-color);
        }
        
        .post-title a {
            color: inherit;
            text-decoration: none;
        }
        
        .post-title a:hover {
            color: var(--primary-color);
        }
        
        .post-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        
        .post-content {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: var(--secondary-color);
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .tag {
            font-size: 0.8rem;
            background-color: var(--light-color);
            color: var(--secondary-color);
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .tag:hover {
            background-color: var(--primary-light);
            color: var(--primary-dark);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .forum-sidebar-card {
            margin-bottom: 1.5rem;
        }
        
        .forum-sidebar-card .card-header {
            font-weight: 600;
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
        }
        
        .category-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .category-list li {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .category-list li:last-child {
            border-bottom: none;
        }
        
        .category-list li:hover {
            background-color: var(--primary-light);
        }
        
        .category-list a {
            text-decoration: none;
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .category-list a:hover {
            color: var(--primary-dark);
        }
        
        .category-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            margin-right: 0.75rem;
            color: white;
        }
        
        .category-count {
            background-color: var(--light-color);
            color: var(--secondary-color);
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
        .pagination {
            margin-top: 2rem;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-link {
            color: var(--primary-color);
        }
        
        .create-post-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .pinned-post {
            position: relative;
            border-left: 4px solid var(--info-color);
        }
        
        .pinned-post::before {
            content: "📌";
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            font-size: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-name {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--dark-color);
            transition: var(--transition);
        }
        
        .user-name:hover {
            color: var(--primary-color);
            text-decoration: underline !important;
        }
        
        .post-stats {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .post-stat {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .recent-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .recent-list li {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .recent-list li:last-child {
            border-bottom: none;
        }
        
        .recent-title {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .recent-title a {
            color: var(--text-color);
            text-decoration: none;
        }
        
        .recent-title a:hover {
            color: var(--primary-color);
        }
        
        .recent-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .post-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1rem;
            border-top: 1px solid var(--border-color);
            padding-top: 0.75rem;
        }
        
        .post-actions .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            padding: 0.375rem 0.5rem;
            background: transparent;
            border: none;
        }
        
        .post-actions .text-primary {
            color: var(--primary-color) !important;
        }
        
        .post-actions .text-primary:hover {
            color: var(--primary-dark) !important;
            text-decoration: underline;
        }
        
        .like-post.liked {
            color: #3498db !important;
        }
        
        .like-post.liked i {
            color: #3498db;
        }
        
        /* Form select styles */
        .form-select {
            border-color: var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-color);
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            background-color: white;
            transition: var(--transition);
        }
        
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(217, 185, 155, 0.25);
        }
        
        /* Filter section styling */
        .card-body form .dropdown {
            margin-right: 5px;
        }

        .card-body form .dropdown select {
            border-radius: 4px;
            border-color: #dee2e6;
        }

        .card-body form .btn {
            border-radius: 4px;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .forum-container .row {
                flex-direction: column;
            }

            .forum-container .col-lg-8,
            .forum-container .col-lg-4 {
                flex: 1 1 100%;
                max-width: 100%;
            }

            .forum-sidebar {
                order: -1;
                margin-bottom: 2rem;
            }
        }

        @media (max-width: 768px) {
            .forum-header {
                padding: 1.5rem;
            }

            .forum-header h1 {
                font-size: 1.8rem;
            }

            .forum-header p {
                font-size: 0.95rem;
            }

            .card-body form {
                flex-direction: column;
                gap: 1rem;
            }

            .card-body form .dropdown {
                margin-right: 0;
                width: 100%;
            }

            .card-body form .btn {
                width: 100%;
            }

            .post-card {
                margin-bottom: 1.5rem;
            }

            .post-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .post-stats {
                justify-content: flex-start;
                flex-wrap: wrap;
                gap: 0.75rem;
            }

            .post-actions {
                justify-content: space-between;
            }

            .post-actions .btn {
                flex: 1;
                justify-content: center;
            }

            .category-list li a {
                padding: 0.75rem;
            }

            .category-icon {
                width: 32px;
                height: 32px;
            }

            .category-icon i {
                font-size: 0.9rem;
            }

            .recent-list li {
                padding: 0.75rem;
            }

            .recent-title {
                font-size: 0.85rem;
            }

            .tag-cloud {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .tag {
                font-size: 0.8rem;
                padding: 0.25rem 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .forum-header {
                padding: 1rem;
            }

            .forum-header h1 {
                font-size: 1.6rem;
            }

            .forum-header .btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }

            .post-card {
                margin-bottom: 1rem;
            }

            .post-content {
                font-size: 0.9rem;
            }

            .user-info {
                font-size: 0.85rem;
            }

            .post-stats {
                font-size: 0.8rem;
            }

            .forum-sidebar-card {
                margin-bottom: 1.5rem;
            }

            .card-header {
                padding: 0.75rem 1rem;
                font-size: 0.95rem;
            }

            .category-list li a {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }

            .recent-list li {
                padding: 0.5rem 0.75rem;
            }

            .modal-dialog {
                margin: 0.5rem;
            }

            .modal-body {
                padding: 1rem;
            }

            .form-control, .form-select {
                font-size: 0.9rem;
            }
        }
        
        .avatar a {
            display: flex;
            width: 100%;
            height: 100%;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
        }
        
        .recent-meta a {
            color: var(--dark-color);
            transition: var(--transition);
        }
        
        .recent-meta a:hover {
            color: var(--primary-color);
            text-decoration: underline !important;
        }
        
        /* Book buddies styles */
        .book-buddy-badge {
            display: inline-flex;
            align-items: center;
            background-color: var(--primary-light);
            color: var(--primary-dark);
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            margin-left: 0.5rem;
        }
        
        .book-buddy-badge i {
            margin-right: 0.25rem;
            font-size: 0.7rem;
        }
    </style>
</head>
<body>
<?php include("include/user_header.php"); ?>

<?php include('include/tab.php'); ?>
<div class="container pt-2">

        <!-- Forum header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Book Community Forum</h1>
                <p class="text-muted">Join the conversation with fellow book lovers</p>
            </div>
            <div>
                <?php if ($userId): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPostModal">
                    <i class="fas fa-plus me-2"></i> New Discussion
                </button>
                <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i> Login to Post
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search and filter bar -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body p-3">
                <form action="" method="get">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <div class="position-relative d-flex align-items-center" style="flex: 1; min-width: 200px;">
                            <span class="position-absolute ms-3" style="z-index: 5;">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control ps-5" 
                                placeholder="Search discussions..." 
                                value="<?= htmlspecialchars($searchQuery) ?>">
                        </div>
                        
                        <div class="dropdown">
                            <select name="category" class="form-select" style="min-width: 180px;">
                                <option value="0" <?= $category == 0 ? 'selected' : '' ?>>All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" 
                                        <?= $category == $cat['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="dropdown">
                            <select name="sort" class="form-select" style="min-width: 150px;">
                                <option value="newest" <?= $sortBy == 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="oldest" <?= $sortBy == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                                <option value="most_comments" <?= $sortBy == 'most_comments' ? 'selected' : '' ?>>Most Discussed</option>
                                <option value="most_views" <?= $sortBy == 'most_views' ? 'selected' : '' ?>>Most Viewed</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn px-3 py-2" style="background-color: #f0f0f0; border-color: #dee2e6;">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                    
                    <!-- Preserve tag filter if it exists -->
                    <?php if (!empty($tag)): ?>
                        <input type="hidden" name="tag" value="<?= htmlspecialchars($tag) ?>">
                    <?php endif; ?>
                    
                    <!-- Preserve page number if set -->
                    <?php if (isset($_GET['page']) && $page > 1): ?>
                        <input type="hidden" name="page" value="<?= $page ?>">
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="row">
            <!-- Main content -->
            <div class="col-lg-8">
                <!-- Discussion list -->
                <?php if ($postsResult && $postsResult->num_rows > 0): ?>
                    <?php while ($post = $postsResult->fetch_assoc()): ?>
                        <div class="card card-forum-post <?= $post['is_pinned'] ? 'pinned-post' : '' ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="category-badge" style="background-color: <?= htmlspecialchars($post['category_color']) ?>">
                                        <?= htmlspecialchars($post['category_name']) ?>
                                    </span>
                                    <div class="post-stats">
                                        <span class="post-stat">
                                            <i class="far fa-comment"></i> <?= $post['comment_count'] ?>
                                        </span>
                                        <span class="post-stat">
                                            <i class="far fa-eye"></i> <?= $post['views'] ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <h3 class="post-title">
                                    <a href="forum_post.php?id=<?= $post['post_id'] ?>">
                                        <?= htmlspecialchars($post['title']) ?>
                                    </a>
                                </h3>
                                
                                <div class="post-meta">
                                    <div class="user-info">
                                        <div class="avatar avatar-sm">
                                            <?php if (!empty($post['profile_picture']) && file_exists($post['profile_picture'])): ?>
                                                <a href="profile.php?id=<?= $post['user_id'] ?>">
                                                    <img src="<?= htmlspecialchars($post['profile_picture']) ?>" alt="Profile">
                                                </a>
                                            <?php else: ?>
                                                <a href="profile.php?id=<?= $post['user_id'] ?>">
                                                    <?= strtoupper(substr($post['firstname'] ?? $post['email'], 0, 1)) ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <a href="profile.php?id=<?= $post['user_id'] ?>" class="user-name text-decoration-none">
                                            <?= htmlspecialchars($post['firstname'] . ' ' . $post['lastname']) ?>
                                        </a>
                                    </div>
                                    <span class="post-date">
                                        <i class="far fa-clock me-1"></i>
                                        <?= time_elapsed_string($post['created_at']) ?>
                                    </span>
                                </div>
                                
                                <div class="post-content">
                                    <?= htmlspecialchars(substr(strip_tags($post['content']), 0, 150)) ?>
                                    <?= strlen(strip_tags($post['content'])) > 150 ? '...' : '' ?>
                                </div>
                                
                                <?php if (!empty($post['tags'])): ?>
                                    <div class="tag-cloud">
                                        <?php foreach (explode(',', $post['tags']) as $postTag): ?>
                                            <a href="?tag=<?= urlencode(trim($postTag)) ?>" class="tag">
                                                <?= htmlspecialchars(trim($postTag)) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="post-actions mt-3">
                                    <a href="forum_post.php?id=<?= $post['post_id'] ?>" class="btn btn-sm text-muted">
                                        <i class="far fa-comment-alt"></i> <?= $post['comment_count'] ?>
                                    </a>
                                    <?php if ($userId): ?>
                                    <button type="button" class="btn btn-sm text-muted like-post <?= $post['user_liked'] ? 'liked' : '' ?>" data-post-id="<?= $post['post_id'] ?>">
                                        <i class="<?= $post['user_liked'] ? 'fas' : 'far' ?> fa-thumbs-up"></i> <?= $post['likes'] ?>
                                    </button>
                                    <?php else: ?>
                                    <a href="login.php" class="btn btn-sm text-muted">
                                        <i class="far fa-thumbs-up"></i> <?= $post['likes'] ?>
                                    </a>
                                    <?php endif; ?>
                                    <a href="forum_post.php?id=<?= $post['post_id'] ?>" class="btn btn-sm text-primary ms-auto">Read More</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    
                    <!-- Pagination -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 
                                        . ($searchQuery ? "&search=" . urlencode($searchQuery) : '')
                                        . ($category ? "&category=" . $category : '')
                                        . ($sortBy ? "&sort=" . urlencode($sortBy) : '')
                                        . ($tag ? "&tag=" . urlencode($tag) : '')
                                    ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i 
                                        . ($searchQuery ? "&search=" . urlencode($searchQuery) : '')
                                        . ($category ? "&category=" . $category : '')
                                        . ($sortBy ? "&sort=" . urlencode($sortBy) : '')
                                        . ($tag ? "&tag=" . urlencode($tag) : '')
                                    ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 
                                        . ($searchQuery ? "&search=" . urlencode($searchQuery) : '')
                                        . ($category ? "&category=" . $category : '')
                                        . ($sortBy ? "&sort=" . urlencode($sortBy) : '')
                                        . ($tag ? "&tag=" . urlencode($tag) : '')
                                    ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body empty-state">
                            <i class="fas fa-comments"></i>
                            <h4>No discussions found</h4>
                            <p class="text-muted">Be the first to start a discussion in our community!</p>
                            <?php if ($userId): ?>
                                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createPostModal">
                                    <i class="fas fa-plus me-2"></i> Create New Discussion
                                </button>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-primary mt-2">
                                    <i class="fas fa-sign-in-alt me-2"></i> Login to Start Discussion
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Categories card -->
                <div class="card forum-sidebar-card">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-layer-group me-2"></i> Categories
                    </div>
                    <ul class="category-list">
                        <?php foreach ($categories as $cat): 
                            // Get post count for this category
                            $countQuery = "SELECT COUNT(*) as count FROM forum_posts WHERE category_id = " . $cat['category_id'];
                            $countResult = $conn->query($countQuery);
                            $postCount = $countResult->fetch_assoc()['count'];
                        ?>
                            <li>
                                <a href="?category=<?= $cat['category_id'] ?>" class="d-flex align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="category-icon" style="background-color: <?= $cat['color'] ?>">
                                            <i class="fas <?= $cat['icon'] ?>"></i>
                                        </div>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </div>
                                    <span class="category-count"><?= $postCount ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Recent discussions -->
                <div class="card forum-sidebar-card">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-clock me-2"></i> Recent Discussions
                    </div>
                    <ul class="recent-list">
                        <?php if ($recentResult && $recentResult->num_rows > 0): ?>
                            <?php while ($recent = $recentResult->fetch_assoc()): ?>
                                <li>
                                    <div class="recent-title">
                                        <a href="forum_post.php?id=<?= $recent['post_id'] ?>">
                                            <?= htmlspecialchars($recent['title']) ?>
                                        </a>
                                    </div>
                                    <div class="recent-meta">
                                        <span>by <a href="profile.php?id=<?= $recent['user_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($recent['firstname'] . ' ' . $recent['lastname']) ?></a></span>
                                        <span class="ms-2"><?= time_elapsed_string($recent['created_at']) ?></span>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="text-center py-3">
                                <span class="text-muted">No recent discussions</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Popular tags -->
                <div class="card forum-sidebar-card">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-tags me-2"></i> Popular Tags
                    </div>
                    <div class="card-body">
                        <div class="tag-cloud">
                            <?php if (!empty($popularTags)): ?>
                                <?php foreach ($popularTags as $popularTag): ?>
                                    <a href="?tag=<?= urlencode($popularTag) ?>" class="tag">
                                        <?= htmlspecialchars($popularTag) ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">No tags found</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Community guidelines -->
                <div class="card forum-sidebar-card">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-info-circle me-2"></i> Community Guidelines
                    </div>
                    <div class="card-body">
                        <ul class="mb-0 ps-3">
                            <li class="mb-2">Be respectful and considerate to other members</li>
                            <li class="mb-2">Stay on topic and avoid off-topic discussions</li>
                            <li class="mb-2">Avoid plagiarism and respect copyright</li>
                            <li class="mb-2">Use appropriate language and avoid hate speech</li>
                            <li>Report inappropriate content to moderators</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Create Post Modal -->
    <?php if ($userId): ?>
    <div class="modal fade" id="createPostModal" tabindex="-1" aria-labelledby="createPostModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPostModalLabel">Create New Discussion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="newPostForm" action="ajax_handlers/create_forum_post.php" method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="postTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="postTitle" name="title" required 
                                   placeholder="Give your discussion a descriptive title">
                        </div>
                        <div class="mb-3">
                            <label for="postCategory" class="form-label">Category</label>
                            <select class="form-select" id="postCategory" name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="postContent" class="form-label">Content</label>
                            <textarea class="form-control" id="postContent" name="content" rows="6" required
                                      placeholder="Share your thoughts, questions or insights..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="postTags" class="form-label">Tags (comma separated)</label>
                            <input type="text" class="form-control" id="postTags" name="tags" 
                                   placeholder="fiction, mystery, romance, etc.">
                            <div class="form-text">Add relevant tags to help others find your discussion</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitPost">
                            <i class="fas fa-paper-plane me-2"></i> Create Discussion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- BookWagon Global Footer -->
    <?php include("include/footer.php"); ?>

    <!-- Sweet Alert 2 for better notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Forum interactions script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle new post form submission
            const newPostForm = document.getElementById('newPostForm');
            if (newPostForm) {
                newPostForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const submitBtn = document.getElementById('submitPost');
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating...';
                    
                    fetch('ajax_handlers/create_forum_post.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Your discussion has been created successfully!',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = `forum_post.php?id=${data.post_id}`;
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Failed to create discussion. Please try again.'
                            });
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Create Discussion';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An unexpected error occurred. Please try again later.'
                        });
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Create Discussion';
                    });
                });
            }
            
            // Handle post likes
            const likeButtons = document.querySelectorAll('.like-post');
            likeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    
                    fetch('ajax_handlers/like_forum_post.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `post_id=${postId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update like button appearance
                            const icon = this.querySelector('i');
                            
                            if (data.liked) {
                                icon.classList.remove('far');
                                icon.classList.add('fas');
                                this.classList.add('liked');
                            } else {
                                icon.classList.remove('fas');
                                icon.classList.add('far');
                                this.classList.remove('liked');
                            }
                            
                            // Update the like count
                            this.innerHTML = `<i class="${data.liked ? 'fas' : 'far'} fa-thumbs-up"></i> Like (${data.likes})`;
                        } else {
                            if (data.message === 'Please log in to like posts') {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Login Required',
                                    text: 'Please log in to like posts',
                                    showCancelButton: true,
                                    confirmButtonText: 'Login Now',
                                    cancelButtonText: 'Later'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.href = 'login.php';
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message || 'Failed to like post'
                                });
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An unexpected error occurred'
                        });
                    });
                });
            });
        });
    </script>
</body>
</html>