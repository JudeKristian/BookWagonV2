<?php
// Set default timezone
date_default_timezone_set('Asia/Manila'); // Philippines timezone

include("session.php");
include("connect.php");

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

// Get post ID from URL
$postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Redirect if post ID is invalid
if ($postId <= 0) {
    header('Location: explore.php');
    exit;
}

// Increment view count
$updateViewsQuery = "UPDATE forum_posts SET views = views + 1 WHERE post_id = $postId";
$conn->query($updateViewsQuery);

// Get post details
$postQuery = "SELECT fp.*, 
              fc.name as category_name, 
              fc.color as category_color,
              u.firstname, 
              u.lastname, 
              u.email,
              u.profile_picture,
              COUNT(DISTINCT fc2.comment_id) as comment_count
              FROM forum_posts fp
              LEFT JOIN forum_categories fc ON fp.category_id = fc.category_id
              LEFT JOIN users u ON fp.user_id = u.id
              LEFT JOIN forum_comments fc2 ON fp.post_id = fc2.post_id
              WHERE fp.post_id = $postId
              GROUP BY fp.post_id";

$postResult = $conn->query($postQuery);

// If post not found, redirect
if (!$postResult || $postResult->num_rows === 0) {
    header('Location: explore.php');
    exit;
}

$post = $postResult->fetch_assoc();

// Get comments
$commentsQuery = "SELECT fc.*, 
                 u.firstname, 
                 u.lastname, 
                 u.email,
                 u.profile_picture,
                 (SELECT COUNT(*) FROM forum_user_interactions 
                  WHERE comment_id = fc.comment_id AND interaction_type = 'like') as likes
                 FROM forum_comments fc
                 LEFT JOIN users u ON fc.user_id = u.id
                 WHERE fc.post_id = $postId AND fc.parent_id IS NULL
                 ORDER BY fc.created_at ASC";

$commentsResult = $conn->query($commentsQuery);

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

// Get related posts
$relatedPostsQuery = "SELECT fp.post_id, fp.title, fp.created_at, u.firstname, u.lastname
                     FROM forum_posts fp
                     JOIN users u ON fp.user_id = u.id
                     WHERE fp.category_id = {$post['category_id']} AND fp.post_id != $postId
                     ORDER BY fp.created_at DESC
                     LIMIT 5";
$relatedPostsResult = $conn->query($relatedPostsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - Bookwagon Forum</title>
    <!-- Bootstrap and font libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="css/tab.css">
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
            font-family: 'Inter', sans-serif;
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
        
        .post-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        
        .post-content {
            font-size: 1rem;
            line-height: 1.7;
            margin: 1.5rem 0;
        }
        
        .post-content p {
            margin-bottom: 1rem;
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
        
        .comment-card {
            margin-bottom: 1rem;
            padding: 1.25rem;
            border-radius: var(--border-radius);
            background-color: white;
            box-shadow: var(--box-shadow);
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .comment-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .comment-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.85rem;
        }
        
        .comment-actions button {
            background: none;
            border: none;
            color: var(--secondary-color);
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: var(--transition);
        }
        
        .comment-actions button:hover {
            color: var(--primary-color);
        }
        
        .reply-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
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
        }
        
        .post-stats {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
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
        
        .breadcrumb {
            background-color: transparent;
            padding: 8px 0;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            font-size: 0.95rem;
        }
        
        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .breadcrumb-item a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .breadcrumb-item.active {
            color: var(--secondary-color);
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 300px;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: var(--secondary-color);
            font-weight: 500;
            padding: 0 0.5rem;
            font-size: 1.1rem;
            line-height: 1;
        }
    </style>
</head>
<body>
    <?php include("include/user_header.php"); ?>

    <div class="container-fluid py-4">
        <div class="forum-container">
            <!-- Navigation tabs -->
            <?php include('include/tab.php'); ?>

            <!-- Breadcrumb -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-2">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 py-1">
                            <li class="breadcrumb-item"><a href="home.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="explore.php">Forum</a></li>
                            <li class="breadcrumb-item"><a href="explore.php?category=<?= $post['category_id'] ?>"><?= htmlspecialchars($post['category_name']) ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars(substr($post['title'], 0, 60)) ?><?= strlen($post['title']) > 60 ? '...' : '' ?></li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="row">
                <!-- Main content -->
                <div class="col-lg-8">
                    <!-- Post card -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-3">
                                <span class="category-badge" style="background-color: <?= htmlspecialchars($post['category_color']) ?>">
                                    <?= htmlspecialchars($post['category_name']) ?>
                                </span>
                                <div class="post-date">
                                    <i class="far fa-calendar-alt me-1"></i>
                                    <?= date('F j, Y', strtotime($post['created_at'])) ?>
                                </div>
                            </div>
                            
                            <h1 class="h3 mb-3"><?= htmlspecialchars($post['title']) ?></h1>
                            
                            <div class="post-meta">
                                <div class="user-info">
                                    <div class="avatar">
                                        <?php if (!empty($post['profile_picture']) && file_exists($post['profile_picture'])): ?>
                                            <img src="<?= htmlspecialchars($post['profile_picture']) ?>" alt="Profile">
                                        <?php else: ?>
                                            <?= strtoupper(substr($post['firstname'] ?? $post['email'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <span class="user-name">
                                        <?= htmlspecialchars($post['firstname'] . ' ' . $post['lastname']) ?>
                                    </span>
                                </div>
                                <span class="post-views">
                                    <i class="far fa-eye me-1"></i> <?= $post['views'] ?> views
                                </span>
                            </div>
                            
                            <div class="post-content">
                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                            </div>
                            
                            <?php if (!empty($post['tags'])): ?>
                                <div class="tag-cloud">
                                    <?php foreach (explode(',', $post['tags']) as $postTag): ?>
                                        <a href="explore.php?tag=<?= urlencode(trim($postTag)) ?>" class="tag">
                                            <?= htmlspecialchars(trim($postTag)) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="post-stats">
                                <div class="post-stat">
                                    <i class="far fa-comment"></i> <?= $post['comment_count'] ?> comments
                                </div>
                                <div class="post-stat">
                                    <i class="far fa-calendar-alt"></i> Created <?= time_elapsed_string($post['created_at']) ?>
                                </div>
                                <?php if ($post['updated_at'] != $post['created_at']): ?>
                                    <div class="post-stat">
                                        <i class="far fa-edit"></i> Updated <?= time_elapsed_string($post['updated_at']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Comments section -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Comments (<?= $post['comment_count'] ?>)</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($userId): ?>
                                <form id="commentForm" class="mb-4">
                                    <input type="hidden" name="post_id" value="<?= $postId ?>">
                                    <div class="mb-3">
                                        <textarea class="form-control" name="content" rows="3" placeholder="Add your comment..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="far fa-paper-plane me-1"></i> Post Comment
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-info-circle me-2"></i> 
                                    <a href="login.php" class="alert-link">Login</a> to join the discussion.
                                </div>
                            <?php endif; ?>
                            
                            <div class="comments-container">
                                <?php if ($commentsResult && $commentsResult->num_rows > 0): ?>
                                    <?php while ($comment = $commentsResult->fetch_assoc()): ?>
                                        <div class="comment-card" id="comment-<?= $comment['comment_id'] ?>">
                                            <div class="comment-header">
                                                <div class="comment-meta">
                                                    <div class="avatar avatar-sm">
                                                        <?php if (!empty($comment['profile_picture']) && file_exists($comment['profile_picture'])): ?>
                                                            <img src="<?= htmlspecialchars($comment['profile_picture']) ?>" alt="Profile">
                                                        <?php else: ?>
                                                            <?= strtoupper(substr($comment['firstname'] ?? $comment['email'], 0, 1)) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <span class="user-name">
                                                            <?= htmlspecialchars($comment['firstname'] . ' ' . $comment['lastname']) ?>
                                                        </span>
                                                        <small class="text-muted d-block">
                                                            <?= time_elapsed_string($comment['created_at']) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="comment-actions">
                                                    <button type="button" class="like-comment" data-comment-id="<?= $comment['comment_id'] ?>">
                                                        <i class="far fa-thumbs-up"></i> <?= $comment['likes'] ?>
                                                    </button>
                                                    <?php if ($userId): ?>
                                                        <button type="button" class="reply-button" data-comment-id="<?= $comment['comment_id'] ?>">
                                                            <i class="far fa-reply"></i> Reply
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="comment-content">
                                                <?= nl2br(htmlspecialchars($comment['content'])) ?>
                                            </div>
                                            
                                            <!-- Reply form (hidden by default) -->
                                            <?php if ($userId): ?>
                                                <div class="reply-form d-none" id="reply-form-<?= $comment['comment_id'] ?>">
                                                    <form class="reply-comment-form">
                                                        <input type="hidden" name="post_id" value="<?= $postId ?>">
                                                        <input type="hidden" name="parent_id" value="<?= $comment['comment_id'] ?>">
                                                        <div class="mb-3">
                                                            <textarea class="form-control" name="content" rows="2" placeholder="Write your reply..." required></textarea>
                                                        </div>
                                                        <div class="d-flex justify-content-end">
                                                            <button type="button" class="btn btn-sm btn-secondary me-2 cancel-reply">Cancel</button>
                                                            <button type="submit" class="btn btn-sm btn-primary">Reply</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Replies will be loaded here via AJAX -->
                                            <div class="replies-container ms-4 mt-3" id="replies-<?= $comment['comment_id'] ?>"></div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="far fa-comment-dots fs-3 text-muted mb-2"></i>
                                        <p class="mb-0">No comments yet. Be the first to comment!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <?php if ($userId && $userId == $post['user_id']): ?>
                        <!-- Post management actions -->
                        <div class="card forum-sidebar-card">
                            <div class="card-header d-flex align-items-center">
                                <i class="fas fa-cog me-2"></i> Manage Post
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPostModal">
                                        <i class="fas fa-edit me-2"></i> Edit Post
                                    </button>
                                    <?php if ($post['status'] === 'active'): ?>
                                        <button type="button" class="btn btn-outline-secondary" id="closePostBtn">
                                            <i class="fas fa-lock me-2"></i> Close Discussion
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-success" id="reopenPostBtn">
                                            <i class="fas fa-lock-open me-2"></i> Reopen Discussion
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Author info -->
                    <div class="card forum-sidebar-card">
                        <div class="card-header d-flex align-items-center">
                            <i class="fas fa-user me-2"></i> Posted by
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-lg me-3">
                                    <?php if (!empty($post['profile_picture']) && file_exists($post['profile_picture'])): ?>
                                        <img src="<?= htmlspecialchars($post['profile_picture']) ?>" alt="Profile">
                                    <?php else: ?>
                                        <?= strtoupper(substr($post['firstname'] ?? $post['email'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?= htmlspecialchars($post['firstname'] . ' ' . $post['lastname']) ?></h6>
                                    <p class="text-muted mb-0">Member since <?= date('F Y', strtotime($post['created_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Related discussions -->
                    <div class="card forum-sidebar-card">
                        <div class="card-header d-flex align-items-center">
                            <i class="fas fa-link me-2"></i> Related Discussions
                        </div>
                        <ul class="recent-list">
                            <?php if ($relatedPostsResult && $relatedPostsResult->num_rows > 0): ?>
                                <?php while ($related = $relatedPostsResult->fetch_assoc()): ?>
                                    <li>
                                        <div class="recent-title">
                                            <a href="forum_post.php?id=<?= $related['post_id'] ?>">
                                                <?= htmlspecialchars($related['title']) ?>
                                            </a>
                                        </div>
                                        <div class="recent-meta">
                                            <span>by <?= htmlspecialchars($related['firstname'] . ' ' . $related['lastname']) ?></span>
                                            <span class="ms-2"><?= time_elapsed_string($related['created_at']) ?></span>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <li class="text-center py-3">
                                    <span class="text-muted">No related discussions found</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <!-- Back to forum -->
                    <div class="d-grid">
                        <a href="explore.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Forum
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Post Modal -->
    <?php if ($userId && $userId == $post['user_id']): ?>
    <div class="modal fade" id="editPostModal" tabindex="-1" aria-labelledby="editPostModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPostModalLabel">Edit Discussion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editPostForm" action="ajax_handlers/update_forum_post.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="post_id" value="<?= $postId ?>">
                        <div class="mb-3">
                            <label for="editTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="editTitle" name="title" required 
                                   value="<?= htmlspecialchars($post['title']) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="editCategory" class="form-label">Category</label>
                            <select class="form-select" id="editCategory" name="category_id" required>
                                <?php
                                // Reset category result pointer
                                $categoryQuery = "SELECT * FROM forum_categories ORDER BY order_index ASC";
                                $categoryResult = $conn->query($categoryQuery);
                                while ($cat = $categoryResult->fetch_assoc()): ?>
                                    <option value="<?= $cat['category_id'] ?>" 
                                            <?= $post['category_id'] == $cat['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editContent" class="form-label">Content</label>
                            <textarea class="form-control" id="editContent" name="content" rows="6" required><?= htmlspecialchars($post['content']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editTags" class="form-label">Tags (comma separated)</label>
                            <input type="text" class="form-control" id="editTags" name="tags" 
                                   value="<?= htmlspecialchars($post['tags']) ?>"
                                   placeholder="fiction, mystery, romance, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="updatePost">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sweet Alert 2 for notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Comment form submission
        const commentForm = document.getElementById('commentForm');
        if (commentForm) {
            commentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Posting...';
                
                fetch('ajax_handlers/create_forum_comment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh page to show new comment
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to post comment. Please try again.'
                        });
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="far fa-paper-plane me-1"></i> Post Comment';
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
                    submitBtn.innerHTML = '<i class="far fa-paper-plane me-1"></i> Post Comment';
                });
            });
        }
        
        // Edit post form submission
        const editPostForm = document.getElementById('editPostForm');
        if (editPostForm) {
            editPostForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = document.getElementById('updatePost');
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                
                fetch('ajax_handlers/update_forum_post.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Your post has been updated.',
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to update post. Please try again.'
                        });
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i> Save Changes';
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
                    submitBtn.innerHTML = '<i class="fas fa-save me-2"></i> Save Changes';
                });
            });
        }
        
        // Toggle reply form
        const replyButtons = document.querySelectorAll('.reply-button');
        replyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const commentId = this.getAttribute('data-comment-id');
                const replyForm = document.getElementById(`reply-form-${commentId}`);
                replyForm.classList.toggle('d-none');
            });
        });
        
        // Cancel reply
        const cancelButtons = document.querySelectorAll('.cancel-reply');
        cancelButtons.forEach(button => {
            button.addEventListener('click', function() {
                const replyForm = this.closest('.reply-form');
                replyForm.classList.add('d-none');
            });
        });
        
        // Submit reply
        const replyForms = document.querySelectorAll('.reply-comment-form');
        replyForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                fetch('ajax_handlers/create_forum_comment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh page to show new reply
                        location.reload();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to post reply. Please try again.'
                        });
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Reply';
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
                    submitBtn.innerHTML = 'Reply';
                });
            });
        });
        
        // Like comment
        const likeButtons = document.querySelectorAll('.like-comment');
        likeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const commentId = this.getAttribute('data-comment-id');
                
                fetch('ajax_handlers/like_forum_comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `comment_id=${commentId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update like count
                        this.querySelector('i').classList.remove('far');
                        this.querySelector('i').classList.add('fas');
                        this.innerHTML = `<i class="fas fa-thumbs-up"></i> ${data.likes}`;
                    } else {
                        if (data.message === 'Please log in to like comments') {
                            Swal.fire({
                                icon: 'info',
                                title: 'Login Required',
                                text: 'Please log in to like comments',
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
                                text: data.message || 'Failed to like comment'
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
        
        // Close/reopen post
        const closePostBtn = document.getElementById('closePostBtn');
        if (closePostBtn) {
            closePostBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Close this discussion?',
                    text: 'This will prevent new comments from being added.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, close it',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        updatePostStatus('closed');
                    }
                });
            });
        }
        
        const reopenPostBtn = document.getElementById('reopenPostBtn');
        if (reopenPostBtn) {
            reopenPostBtn.addEventListener('click', function() {
                updatePostStatus('active');
            });
        }
        
        function updatePostStatus(status) {
            fetch('ajax_handlers/update_forum_post_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=<?= $postId ?>&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: status === 'closed' ? 'Discussion has been closed.' : 'Discussion has been reopened.',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to update discussion status'
                    });
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
        }
        
        // Load replies for each comment
        function loadReplies() {
            const commentCards = document.querySelectorAll('.comment-card');
            commentCards.forEach(card => {
                const commentId = card.id.replace('comment-', '');
                const repliesContainer = document.getElementById(`replies-${commentId}`);
                
                fetch(`ajax_handlers/get_forum_replies.php?comment_id=${commentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.replies.length > 0) {
                        let repliesHTML = '';
                        data.replies.forEach(reply => {
                            repliesHTML += `
                                <div class="comment-card" id="comment-${reply.comment_id}">
                                    <div class="comment-header">
                                        <div class="comment-meta">
                                            <div class="avatar avatar-sm">
                                                ${reply.profile_picture ? 
                                                  `<img src="${reply.profile_picture}" alt="Profile">` : 
                                                  reply.firstname.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <span class="user-name">
                                                    ${reply.firstname} ${reply.lastname}
                                                </span>
                                                <small class="text-muted d-block">
                                                    ${reply.created_at}
                                                </small>
                                            </div>
                                        </div>
                                        <div class="comment-actions">
                                            <button type="button" class="like-comment" data-comment-id="${reply.comment_id}">
                                                <i class="far fa-thumbs-up"></i> ${reply.likes}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="comment-content">
                                        ${reply.content}
                                    </div>
                                </div>
                            `;
                        });
                        
                        repliesContainer.innerHTML = repliesHTML;
                        
                        // Attach like events to newly loaded replies
                        const newLikeButtons = repliesContainer.querySelectorAll('.like-comment');
                        newLikeButtons.forEach(button => {
                            button.addEventListener('click', function() {
                                // ... like button functionality (same as above)
                            });
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading replies:', error);
                });
            });
        }
        
        // Load replies on page load
        loadReplies();
    });
    </script>
</body>
</html> 