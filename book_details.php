<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? '';
$firstName = $_SESSION['firstname'] ?? '';
$lastName = $_SESSION['lastname'] ?? '';
$email = $_SESSION['email'] ?? '';

// Check if book_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: rentbooks.php");
    exit();
}

$book_id = intval($_GET['id']);

// Fetch book details with seller info
$query = "SELECT b.*, u.firstname, u.lastname, u.email, u.phone, u.profile_picture, u.id as seller_id
          FROM books b 
          LEFT JOIN users u ON b.user_id = u.id 
          WHERE b.book_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: rentbooks.php");
    exit();
}

$book = $result->fetch_assoc();

// Format the cover image path
if (!empty($book['cover_image'])) {
    if (strpos($book['cover_image'], 'uploads/covers/') === 0) {
        $cover_image = $book['cover_image'];
    } else {
        $cover_image = 'uploads/covers/' . $book['cover_image'];
    }
} else {
    $cover_image = 'uploads/covers/default_book.jpg';
}

// Generate random rating for demo (1-5)
$rating = rand(4, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - BookWagon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/tab.css">
    
    <style>
        :root {
            --primary-color: #f8a100; /* Changed to indigo for consistency */
            --primary-light:rgb(255, 132, 56);
            --primary-dark: #4f46e5;
            --secondary-color: #f8f9fa;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --accent-color: #6366f1;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition-speed: 0.3s;
        }
        
        body {
            font-family:'Inter', 'Segoe UI', 'Arial', sans-serif;
            color: var(--text-dark);
            background-color: #f1f5f9;
            line-height: 1.6;
            position: relative;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
            position: relative;
            z-index: 1050;
        }
        
        .navbar-brand img {
            height: 60px;
        }

        /* Ensure dropdown is visible */
        .dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            z-index: 1030;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 0;
            margin-top: 10px;
        }

        /* Make sure tab menu is consistent */
        .tab-menu {
            margin-bottom: 1rem;
        }
        
        /* Enhanced book details container */
        .book-details-container {
            background-color: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 2.5rem;
            margin-top: 2rem;
            margin-bottom: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .book-details-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary-color), var(--primary-light));
        }
        
        .book-cover-container {
            position: relative;
            margin-bottom: 1.5rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transform: perspective(1000px) rotateY(0deg);
            transition: transform 0.6s ease;
        }
        
        .book-cover-container:hover {
            transform: perspective(1000px) rotateY(5deg);
        }
        
        .book-cover {
            width: 100%;
            height: auto;
            max-height: 500px;
            object-fit: cover;
            border-radius: 12px;
            transition: all 0.5s ease;
        }
        
        .book-cover:hover {
            transform: scale(1.05);
        }
        
        .book-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
            z-index: 2;
            backdrop-filter: blur(4px);
        }
        
        .book-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--text-dark);
            line-height: 1.2;
            position: relative;
            display: inline-block;
        }
        
        .book-title::after {
            content: '';
            display: block;
            width: 40px;
            height: 4px;
            background: var(--primary-color);
            margin-top: 10px;
            border-radius: 2px;
        }
        
        .book-author {
            font-size: 1.25rem;
            color: var(--text-light);
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        /* Enhanced ratings */
        .ratings {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            background-color: rgba(99, 102, 241, 0.05);
            padding: 12px 16px;
            border-radius: 8px;
            width: fit-content;
        }
        
        .star {
            color: var(--warning-color);
            font-size: 1.2rem;
            margin-right: 2px;
            transition: transform 0.3s ease;
        }
        
        .ratings:hover .star {
            transform: scale(1.1);
        }
        
        .ratings-text {
            margin-left: 10px;
            font-size: 0.95rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(to right, var(--border-color), transparent);
            margin: 2rem 0;
        }
        
        /* Enhanced button styling */
        .cart-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 25px;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .cart-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            z-index: -1;
            transition: opacity 0.3s ease;
            opacity: 0;
        }
        
        .cart-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(99, 102, 241, 0.3);
        }
        
        .cart-btn:hover::before {
            opacity: 1;
        }
        
        .price-row {
            display: flex;
            margin-bottom: 20px;
            align-items: center;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            background-color: white;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .price-row:hover {
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
        }
        
        .price-label {
            width: 130px;
            font-size: 1.1rem;
            font-weight: 600;
            text-align: left;
            padding: 16px 20px;
            background-color: #f8fafc;
            border-right: 1px solid var(--border-color);
        }
        
        .price-label .per-week {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 400;
            margin-top: 2px;
        }
        
        .action-button {
            flex: 1;
            padding: 16px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .action-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            z-index: -1;
        }
        
        .action-button:hover::before {
            left: 0;
        }
        
        .btn-rent {
            background: linear-gradient(135deg, var(--success-color), #0d9488);
            color: white;
        }
        
        .btn-rent:hover {
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.3);
        }
        
        .btn-buy {
            background: linear-gradient(135deg, var(--danger-color), #b91c1c);
            color: white;
        }
        
        .btn-buy:hover {
            box-shadow: 0 6px 15px rgba(239, 68, 68, 0.3);
        }
        
        /* Form styling */
        .form-select {
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: white;
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-dark);
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        /* Accordion styling */
        .book-details-accordion {
            margin-top: 2rem;
        }
        
        .accordion-item {
            border: 1px solid var(--border-color);
            margin-bottom: 15px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
        }
        
        .accordion-item:hover {
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .accordion-button {
            padding: 18px 22px;
            font-weight: 600;
            background-color: #f8fafc;
            color: var(--text-dark);
            border: none;
            font-size: 1.05rem;
            transition: all 0.3s ease;
        }
        
        .accordion-button:not(.collapsed) {
            background: linear-gradient(to right, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0.05));
            color: var(--primary-color);
        }
        
        .accordion-button:focus {
            box-shadow: none;
            border-color: var(--border-color);
        }
        
        .accordion-button::after {
            background-size: 1.25rem;
            transition: all 0.3s;
        }
        
        .accordion-body {
            padding: 20px 25px;
            background-color: white;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px dashed #edf2f7;
            padding-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .detail-row:hover {
            background-color: rgba(99, 102, 241, 0.02);
            transform: translateX(5px);
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .detail-label {
            width: 130px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-light);
        }
        
        /* Tab styling */
        .book-details-tabs {
            margin-top: 2.5rem;
        }
        
        .nav-tabs {
            border-bottom: none;
            gap: 10px;
        }
        
        .nav-tabs .nav-item {
            margin-bottom: 0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            padding: 14px 22px;
            font-weight: 500;
            color: var(--text-light);
            border-radius: 12px 12px 0 0;
            transition: all 0.3s;
            background-color: rgba(99, 102, 241, 0.05);
            position: relative;
            overflow: hidden;
        }
        
        .nav-tabs .nav-link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 3px;
            background-color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover::before {
            width: 80%;
        }
        
        .nav-tabs .nav-link.active {
            background-color: white;
            color: var(--primary-color);
            font-weight: 600;
            box-shadow: 0 -4px 6px rgba(0,0,0,0.03);
        }
        
        .nav-tabs .nav-link.active::before {
            width: 80%;
        }
        
        .tab-content {
            background-color: white;
            border-radius: 0 12px 12px 12px;
            padding: 2.5rem;
            box-shadow: var(--card-shadow);
        }
        
        .tab-pane {
            min-height: 200px;
            animation: fadeIn 0.5s ease forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Quote design for description */
        .book-quote {
            font-style: italic;
            border-left: 4px solid var(--primary-color);
            padding: 15px 25px;
            margin: 20px 0;
            background-color: rgba(99, 102, 241, 0.05);
            border-radius: 0 8px 8px 0;
            position: relative;
        }
        
        .book-quote::before {
            content: '"';
            position: absolute;
            top: 10px;
            left: 15px;
            font-size: 2.5rem;
            color: var(--primary-color);
            opacity: 0.2;
            font-family: Georgia, serif;
        }
        
        /* Enhanced book details sections */
        .book-detail-section {
            padding: 5px 0;
            transition: all 0.3s ease;
            border-bottom: 1px dashed var(--border-color);
            margin-bottom: 15px;
        }
        
        .book-detail-section:hover {
            padding-left: 5px;
        }
        
        .book-detail-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .book-detail-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }
        
        .book-detail-value {
            color: var(--text-light);
        }
        
        /* Highlight text */
        .highlight-text {
            background: linear-gradient(to bottom, transparent 50%, rgba(99, 102, 241, 0.1) 50%);
            padding: 0 3px;
        }
        
        /* Edition details */
        .edition-details {
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .edition-details:hover {
            box-shadow: 0 6px 15px rgba(0,0,0,0.05);
            transform: translateY(-3px);
        }
        
        .edition-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-dark);
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .edition-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }
        
        .book-description {
            line-height: 1.9;
            color: var(--text-light);
            font-size: 1.05rem;
        }
        
        /* Review styling */
        .review-item {
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .review-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.05);
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .reviewer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }
        
        .reviewer-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.1rem;
        }
        
        .review-date {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-left: auto;
        }
        
        .review-text {
            line-height: 1.8;
            color: var(--text-light);
            margin-bottom: 0;
            font-size: 1rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .book-details-container {
                padding: 2rem;
            }

            .book-title {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 992px) {
            .book-details-container {
                padding: 1.5rem;
            }

            .book-title {
                font-size: 2rem;
            }

            .tab-content {
                padding: 2rem;
            }

            .seller-card {
                padding: 1.25rem;
            }
        }

        @media (max-width: 768px) {
            .book-details-container {
                margin-top: 1rem;
                margin-bottom: 1rem;
                padding: 1.25rem;
            }

            .row {
                --bs-gutter-x: 0;
            }

            .book-title {
                font-size: 1.8rem;
                margin-bottom: 0.5rem;
            }

            .book-author {
                font-size: 1rem;
                margin-bottom: 1rem;
            }

            .ratings {
                margin-bottom: 1rem;
            }

            .book-cover {
                max-height: 350px;
            }

            .book-cover-container {
                margin-bottom: 1rem;
            }

            .seller-card {
                margin-bottom: 1.5rem;
                padding: 1rem;
            }

            .seller-header {
                flex-direction: column;
                text-align: center;
                gap: 0.75rem;
            }

            .seller-avatar {
                margin-right: 0;
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }

            .seller-stats {
                justify-content: space-around;
                padding-top: 0.75rem;
            }

            .seller-stat-value {
                font-size: 1.1rem;
            }

            .tab-content {
                padding: 1.5rem;
                border-radius: 0 8px 8px 8px;
            }

            .nav-tabs {
                flex-direction: row;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                -ms-overflow-style: none;
            }

            .nav-tabs::-webkit-scrollbar {
                display: none;
            }

            .nav-tabs .nav-item {
                flex-shrink: 0;
            }

            .edition-details {
                padding: 20px;
                margin-bottom: 20px;
            }

            .review-item {
                padding: 20px;
                margin-bottom: 20px;
            }

            .reviewer-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .review-date {
                margin-left: 0;
                margin-top: 0.25rem;
            }
        }

        @media (max-width: 576px) {
            .book-details-container {
                padding: 1rem;
                border-radius: 12px;
            }

            .book-title {
                font-size: 1.6rem;
                line-height: 1.3;
            }

            .book-author {
                font-size: 0.95rem;
            }

            .ratings {
                font-size: 0.9rem;
            }

            .star {
                font-size: 1rem;
            }

            .book-cover {
                max-height: 300px;
            }

            .book-badge {
                top: 10px;
                right: 10px;
                padding: 6px 12px;
                font-size: 0.75rem;
            }

            .seller-card {
                padding: 0.875rem;
                margin-bottom: 1rem;
            }

            .seller-avatar {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }

            .seller-info h4 {
                font-size: 1.1rem;
            }

            .seller-title {
                font-size: 0.85rem;
            }

            .seller-stats {
                margin-top: 0.75rem;
                padding-top: 0.75rem;
            }

            .seller-stat-value {
                font-size: 1.1rem;
            }

            .seller-stat-label {
                font-size: 0.8rem;
            }

            .contact-seller-btn {
                padding: 0.625rem;
                font-size: 0.9rem;
            }

            .tab-content {
                padding: 1.25rem;
            }

            .edition-title {
                font-size: 1.1rem;
            }

            .book-description {
                font-size: 1rem;
                line-height: 1.7;
            }

            .book-quote {
                padding: 12px 20px;
                margin: 15px 0;
                font-size: 0.95rem;
            }

            .review-item {
                padding: 16px;
            }

            .reviewer-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .reviewer-name {
                font-size: 1rem;
            }

            .review-text {
                font-size: 0.95rem;
                line-height: 1.6;
            }

            .action-form {
                padding: 12px;
                gap: 8px;
            }

            .action-button {
                padding: 12px;
                font-size: 0.9rem;
            }

            .detail-label {
                width: 100px;
                font-size: 0.9rem;
            }

            .detail-value {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .book-details-container {
                padding: 0.875rem;
                margin-top: 0.5rem;
                margin-bottom: 0.5rem;
            }

            .book-title {
                font-size: 1.4rem;
            }

            .book-author {
                font-size: 0.9rem;
            }

            .book-cover {
                max-height: 250px;
            }

            .seller-card {
                padding: 0.75rem;
            }

            .tab-content {
                padding: 1rem;
            }

            .edition-details {
                padding: 16px;
            }

            .review-item {
                padding: 14px;
            }
        }
        
        /* Fix for action button form style */
        .action-form {
            flex: 1;
            margin: 0;
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .seller-card {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .seller-card:hover {
            box-shadow: var(--card-shadow);
            transform: translateY(-5px);
        }
        
        .seller-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .seller-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .seller-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .seller-info h4 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--text-dark);
        }
        
        .seller-title {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .seller-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .seller-stat {
            text-align: center;
        }
        
        .seller-stat-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .seller-stat-label {
            font-size: 0.85rem;
            color: var(--text-light);
        }
        
        .contact-seller-btn {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            margin-top: 1rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        .contact-seller-btn:hover {
            background-color: var(--primary-light);
        }
    </style>
</head>

<body>
    <?php 
    if ($userType == 'user') {
        include("include/user_header.php");
    } elseif ($userType == 'seller') {
        include("include/seller_header.php");
    }
    ?>

    <?php include('include/tab.php'); ?>

    <!-- Book Details -->
    <div class="container">
        <div class="book-details-container">
            <div class="row">
                <!-- Book Cover Column -->
                <div class="col-lg-4 col-md-5">
                    <div class="book-cover-container">
                        <img src="<?php echo $cover_image; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="book-cover" onerror="this.src='uploads/covers/default_book.jpg'">
                        <?php if ($book['condition'] === 'New' || $book['condition'] === 'Like New'): ?>
                            <div class="book-badge"><?php echo htmlspecialchars($book['condition']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Book Info Column -->
                <div class="col-lg-8 col-md-7">
                    <h1 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h1>
                    <p class="book-author">Author: <?php echo htmlspecialchars($book['author']); ?></p>
                    
                    <!-- Ratings -->
                    <div class="ratings">
                        <?php
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $rating) {
                                echo '<i class="fas fa-star star"></i>';
                            } else {
                                echo '<i class="far fa-star star"></i>';
                            }
                        }
                        ?>
                        <span class="ratings-text"><?php echo $rating; ?> out of 5</span>
                    </div>
                    
                    <div class="divider"></div>

                    <!-- Seller Info Card -->
                    <div class="seller-card mb-4">
                        <div class="seller-header">
                            <div class="seller-avatar">
                                <?php if (!empty($book['profile_picture'])): ?>
                                    <img src="<?php echo $book['profile_picture']; ?>" alt="Seller" onerror="this.parentNode.innerHTML='<?php echo substr($book['firstname'], 0, 1); ?>'">
                                <?php else: ?>
                                    <?php echo substr($book['firstname'], 0, 1); ?>
                                <?php endif; ?>
                            </div>
                            <div class="seller-info">
                                <h4><?php echo htmlspecialchars($book['firstname'] . ' ' . $book['lastname']); ?></h4>
                                <div class="seller-title">Book Seller</div>
                            </div>
                        </div>
                        
                        <div class="seller-stats">
                            <div class="seller-stat">
                                <div class="seller-stat-value">4.9</div>
                                <div class="seller-stat-label">Rating</div>
                            </div>
                            <div class="seller-stat">
                                <div class="seller-stat-value">98%</div>
                                <div class="seller-stat-label">Response</div>
                            </div>
                            <div class="seller-stat">
                                <div class="seller-stat-value">24h</div>
                                <div class="seller-stat-label">Delivery</div>
                            </div>
                        </div>
                        
                        <button class="contact-seller-btn" data-bs-toggle="modal" data-bs-target="#contactSellerModal">
                            <i class="fas fa-envelope me-2"></i> Contact Seller
                        </button>
                    </div>

                    <button class="cart-btn add-to-cart-btn" 
                    data-book-id="<?php echo $book_id; ?>" 
                    data-purchase-type="buy">
                    <i class="fas fa-shopping-cart me-2"></i>Add to cart
                </button>

<!-- Price & Action Buttons -->
                        <div class="price-row">
                            <div class="price-label">
                                ₱<?php echo number_format($book['rent_price'], 2); ?>
                                <div class="per-week">/Week</div>
                            </div>
                            <div class="action-form">
                                <label for="rental_weeks" class="mb-2 fw-semibold">Rental Duration (weeks):</label>
                                <select name="rental_weeks" id="rental_weeks" class="form-select rental-weeks-selector mb-3">
                                    <?php for ($i = 1; $i <= 16; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> week<?php echo ($i > 1) ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <button class="action-button btn-rent add-to-cart-btn" 
                                        data-book-id="<?php echo $book_id; ?>" 
                                        data-purchase-type="rent">
                                    <i class="fas fa-book-open me-2"></i>Rent
                                </button>
                            </div>
                        </div>

                        <div class="price-row">
                            <div class="price-label">₱<?php echo number_format($book['price'], 2); ?></div>
                            <button class="action-button btn-buy add-to-cart-btn" 
                                    data-book-id="<?php echo $book_id; ?>" 
                                    data-purchase-type="buy">
                                <i class="fas fa-shopping-bag me-2"></i>Buy
                            </button>
                        </div>
                
                    <!-- Accordion for Seller & Book Details -->
                    <div class="book-details-accordion mt-4">
                        <div class="accordion" id="bookAccordion">
                            <!-- Seller Details -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="sellerHeading">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#sellerCollapse" aria-expanded="true" aria-controls="sellerCollapse">
                                        <i class="fas fa-user me-2"></i>Seller Details
                                    </button>
                                </h2>
                                <div id="sellerCollapse" class="accordion-collapse collapse show" aria-labelledby="sellerHeading" data-bs-parent="#bookAccordion">
                                    <div class="accordion-body">
                                        <div class="detail-row">
                                            <div class="detail-label">Seller:</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($book['firstname'] . ' ' . $book['lastname']); ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Location:</div>
                                            <div class="detail-value">Davao City</div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Rental Limit:</div>
                                            <div class="detail-value">1 month</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Book Details -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="bookDetailsHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#bookDetailsCollapse" aria-expanded="false" aria-controls="bookDetailsCollapse">
                                        <i class="fas fa-info-circle me-2"></i>Book Details
                                    </button>
                                </h2>
                                <div id="bookDetailsCollapse" class="accordion-collapse collapse" aria-labelledby="bookDetailsHeading" data-bs-parent="#bookAccordion">
                                    <div class="accordion-body">
                                        <div class="detail-row">
                                            <div class="detail-label">ISBN:</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($book['ISBN'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Book-ID:</div>
                                            <div class="detail-value"><?php echo $book['book_id']; ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Book Type:</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($book['book_type'] ?? 'Paperback'); ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Condition:</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($book['condition'] ?? 'New'); ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Damages:</div>
                                            <div class="detail-value">
                                                <?php if (!empty($book['damages'])): ?>
                                                    <?php echo htmlspecialchars($book['damages']); ?>
                                                <?php else: ?>
                                                    None
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Genre:</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($book['genre'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="detail-row">
                                            <div class="detail-label">Theme:</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($book['theme'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Description & Reviews Tabs -->
        <div class="book-details-tabs">
            <ul class="nav nav-tabs" id="bookTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab" aria-controls="description" aria-selected="true">
                        <i class="fas fa-book me-2"></i>Description
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" aria-controls="reviews" aria-selected="false">
                        <i class="fas fa-comments me-2"></i>Reviews <span class="badge bg-secondary">3</span>
                    </button>
                </li>
            </ul>
            <div class="tab-content" id="bookTabsContent">
                <div class="tab-pane fade show active" id="description" role="tabpanel" aria-labelledby="description-tab">
                    <div class="edition-details">
                        <h4 class="edition-title">This edition</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="book-detail-section">
                                    <div class="book-detail-label">ISBN</div>
                                    <div class="book-detail-value"><?php echo htmlspecialchars($book['ISBN'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="book-detail-section">
                                    <div class="book-detail-label">Genre</div>
                                    <div class="book-detail-value"><?php echo htmlspecialchars($book['genre'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="book-detail-section">
                                    <div class="book-detail-label">Theme</div>
                                    <div class="book-detail-value"><?php echo htmlspecialchars($book['theme'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="book-detail-section">
                                    <div class="book-detail-label">Publication</div>
                                    <div class="book-detail-value">First Edition</div>
                                </div>
                                <div class="book-detail-section">
                                    <div class="book-detail-label">Language</div>
                                    <div class="book-detail-value">English</div>
                                </div>
                                <div class="book-detail-section">
                                    <div class="book-detail-label">Book Format</div>
                                    <div class="book-detail-value"><?php echo htmlspecialchars($book['book_type'] ?? 'Paperback'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="book-description">
                        <?php if (!empty($book['description'])): ?>
                            <?php 
                                $description = htmlspecialchars($book['description']); 
                                // Add a quote-style paragraph if description is long enough
                                if (strlen($description) > 200) {
                                    $parts = explode('.', $description, 3);
                                    if (count($parts) >= 2) {
                                        echo '<p>' . $parts[0] . '.</p>';
                                        echo '<div class="book-quote">' . $parts[1] . '.</div>';
                                        if (isset($parts[2])) {
                                            echo '<p>' . $parts[2] . '</p>';
                                        }
                                    } else {
                                        echo '<p>' . nl2br($description) . '</p>';
                                    }
                                } else {
                                    echo '<p>' . nl2br($description) . '</p>';
                                }
                            ?>
                            <p class="mt-4">
                                <span class="highlight-text">Written by <?php echo htmlspecialchars($book['author']); ?></span>, 
                                this <?php echo htmlspecialchars($book['book_type'] ?? 'paperback'); ?> edition is currently available for 
                                <strong>purchase</strong> at ₱<?php echo number_format($book['price'], 2); ?> or 
                                <strong>rental</strong> starting at ₱<?php echo number_format($book['rent_price'], 2); ?> per week.
                            </p>
                        <?php else: ?>
                            <p>No description available for this book.</p>
                            <p class="mt-4">
                                <span class="highlight-text">Written by <?php echo htmlspecialchars($book['author']); ?></span>, 
                                this <?php echo htmlspecialchars($book['book_type'] ?? 'paperback'); ?> edition is currently available for 
                                <strong>purchase</strong> at ₱<?php echo number_format($book['price'], 2); ?> or 
                                <strong>rental</strong> starting at ₱<?php echo number_format($book['rent_price'], 2); ?> per week.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                    <h4 class="mb-4">Customer Reviews</h4>
                    
                    <!-- Sample reviews -->
                    <div class="review-item">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar">ES</div>
                            <div class="reviewer-name">Emma S.</div>
                            <div class="review-date">April 15, 2025</div>
                        </div>
                        <div class="ratings mb-2">
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star star"></i>
                        </div>
                        <p class="review-text">Excellent book! I couldn't put it down. The characters are well developed and the story is engaging from start to finish.</p>
                    </div>
                    
                    <div class="review-item">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar">MT</div>
                            <div class="reviewer-name">Michael T.</div>
                            <div class="review-date">April 10, 2025</div>
                        </div>
                        <div class="ratings mb-2">
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star star"></i>
                            <i class="far fa-star star"></i>
                        </div>
                        <p class="review-text">I enjoyed this book overall, though some parts dragged a bit. The ending was satisfying and made up for the slower sections.</p>
                    </div>
                    
                    <div class="review-item">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar">SL</div>
                            <div class="reviewer-name">Sofia L.</div>
                            <div class="review-date">April 5,2025</div>
                        </div>
                        <div class="ratings mb-2">
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star star"></i>
                            <i class="fas fa-star-half-alt star"></i>
                        </div>
                        <p class="review-text">Great book with interesting characters and a compelling plot. The author's writing style is beautiful and descriptive.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Add success toast notification JavaScript -->
    <script>
        // Check if there was a redirect from cart.php with success parameter
        document.addEventListener('DOMContentLoaded', function() {
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const successParam = urlParams.get('cart_success');
            
            if (successParam === 'true') {
                // Create toast element
                const toastContainer = document.createElement('div');
                toastContainer.style.position = 'fixed';
                toastContainer.style.bottom = '20px';
                toastContainer.style.right = '20px';
                toastContainer.style.zIndex = '9999';
                
                const toast = document.createElement('div');
                toast.className = 'toast show';
                toast.style.backgroundColor = '#fff';
                toast.style.color = '#333';
                toast.style.padding = '15px 20px';
                toast.style.borderRadius = '12px';
                toast.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
                toast.style.display = 'flex';
                toast.style.alignItems = 'center';
                toast.style.minWidth = '300px';
                toast.style.border = '1px solid var(--border-color)';
                toast.style.transform = 'translateY(20px)';
                toast.style.opacity = '0';
                toast.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    toast.style.transform = 'translateY(0)';
                    toast.style.opacity = '1';
                }, 100);
                
                toast.innerHTML = `
                    <div class="me-3" style="color: var(--success-color);">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1rem;">Success!</div>
                        <div style="font-size: 0.9rem; color: var(--text-light);">Item added to your cart.</div>
                    </div>
                `;
                
                toastContainer.appendChild(toast);
                document.body.appendChild(toastContainer);
                
                // Remove toast after 3 seconds
                setTimeout(function() {
                    toast.style.transform = 'translateY(20px)';
                    toast.style.opacity = '0';
                    setTimeout(function() {
                        document.body.removeChild(toastContainer);
                    }, 300);
                }, 3000);
                
                // Clean URL without reloading the page
                history.replaceState({}, document.title, window.location.pathname + window.location.hash);
            }
            
            // Add hover effect to book cover
            const bookCover = document.querySelector('.book-cover');
            if (bookCover) {
                bookCover.addEventListener('mousemove', function(e) {
                    const container = this.parentElement;
                    const rect = container.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;
                    
                    const rotateY = ((x - centerX) / centerX) * 5;
                    const rotateX = ((y - centerY) / centerY) * 5;
                    
                    container.style.transform = `perspective(1000px) rotateY(${rotateY}deg) rotateX(${-rotateX}deg)`;
                });
                
                bookCover.addEventListener('mouseleave', function() {
                    const container = this.parentElement;
                    container.style.transform = 'perspective(1000px) rotateY(0) rotateX(0)';
                });
            }
            
            // Animate stars in ratings
            const stars = document.querySelectorAll('.star');
            if (stars.length) {
                stars.forEach((star, index) => {
                    setTimeout(() => {
                        star.style.transform = 'scale(1.2)';
                        setTimeout(() => {
                            star.style.transform = 'scale(1)';
                        }, 200);
                    }, index * 100);
                });
            }
        });
    </script>
    <!-- AJAX Cart Management -->
<script src="js/cart_ajax.js"></script>

<!-- Contact Seller Modal -->
<div class="modal fade" id="contactSellerModal" tabindex="-1" aria-labelledby="contactSellerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactSellerModalLabel">Contact <?php echo htmlspecialchars($book['firstname'] . ' ' . $book['lastname']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="contactSellerForm">
                    <div class="mb-3">
                        <label for="message-subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="message-subject" placeholder="Question about <?php echo htmlspecialchars($book['title']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="message-text" class="form-label">Message</label>
                        <textarea class="form-control" id="message-text" rows="5" placeholder="Type your message here..."></textarea>
                    </div>
                </form>
                <div class="seller-contact-info mt-4">
                    <h6>Seller Contact Information</h6>
                    <p><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($book['email']); ?></p>
                    <?php if (!empty($book['phone'])): ?>
                    <p><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($book['phone']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Send Message</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>