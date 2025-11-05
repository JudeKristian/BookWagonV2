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

// Fetch most popular books from database
$popularBooksQuery = "SELECT * FROM books WHERE popularity = 'Most popular' ORDER BY created_at DESC LIMIT 5";
$popularBooksResult = $conn->query($popularBooksQuery);

// Fetch new releases
$newReleasesQuery = "SELECT * FROM books WHERE popularity = 'New Releases' ORDER BY created_at DESC LIMIT 4";
$newReleasesResult = $conn->query($newReleasesQuery);

// Fetch all themes for category tabs
$themesQuery = "SELECT DISTINCT theme FROM books WHERE theme IS NOT NULL AND theme != ''";
$themesResult = $conn->query($themesQuery);
$themes = [];
while ($row = $themesResult->fetch_assoc()) {
    $themes[] = $row['theme'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookWagon - Dashboard</title>
    <?php if(isset($_SESSION['login_count'])): ?>
    <meta name="login-count" content="<?php echo $_SESSION['login_count']; ?>">
    <?php endif; ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/tab.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        :root {
            --primary-color: #d9b99b; /* Modern indigo */
            --primary-light: rgb(255, 132, 56);
            --primary-dark: #4f46e5;
            --secondary-color: #f8f9fa;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', 'Arial', sans-serif;
            color: var(--text-dark);
            background-color:rgb(255, 255, 255);
            line-height: 1.6;
            position: relative;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Ensure proper z-index stacking for navbar */
        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            z-index: 1050; /* Higher z-index than other elements */
        }
        
        .navbar-brand img {
            height: 60px;
        }
        

        
        /* Navbar styles remain unchanged to preserve header */
        
        /* Enhanced Carousel styles */
        .carousel {
            margin: 25px 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        
        .carousel-item {
            position: relative;
        }
        
        .carousel-item img {
            width: 100%;
            max-height: 450px;
            object-fit: cover;
            filter: brightness(0.85);
        }
        
        .carousel-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(0deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 100%);
            padding: 30px;
            text-align: left;
        }
        
        .carousel-caption h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        
        .carousel-caption p {
            font-size: 1.1rem;
            margin-bottom: 0;
            text-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }
        
        .carousel-control-prev, .carousel-control-next {
            width: 5%;
            opacity: 0.9;
            background-color: rgba(0,0,0,0.3);
            border-radius: 50%;
            height: 50px;
            width: 50px;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.3s ease;
        }
        
        .carousel-control-prev:hover, .carousel-control-next:hover {
            background-color: var(--primary-color);
        }
        
        .carousel-indicators {
            bottom: 20px;
        }
        
        .carousel-indicators button {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.7);
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .carousel-indicators button.active {
            background-color: var(--primary-color);
            width: 12px;
            height: 12px;
        }
        
        /* Added animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .book-card {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .book-badge {
            animation: pulse 2s infinite;
        }
        
        /* Enhanced sections and containers */

        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            margin-top: 50px;
            position: relative;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }
        
        /* 3D tilt effect for book cards */
        .book-card {
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            perspective: 1000px;
        }
        
        .book-card:hover .book-img-container {
            transform: rotateY(10deg);
        }
        
        .book-img-container {
            transition: transform 0.5s ease;
            transform-style: preserve-3d;
        }
        
        /* Modern gradient backgrounds for sections */
        .libraries-section {
            background: linear-gradient(145deg, white, #f8fafc);
        }
        
        /* Glass morphism effect */
        .search-bar .form-control {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        /* Section headers */
        .section-title {
            font-weight: 700;
            margin: 0;
            font-size: 1.75rem;
            color: var(--text-dark);
            position: relative;
            padding-left: 18px;
        }
        
        .section-title:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 6px;
            background-color: var(--primary-color);
            border-radius: 3px;
        }
        
        .see-more {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            padding: 6px 14px;
            border-radius: 20px;
            background-color: rgba(99, 102, 241, 0.1);
        }
        
        .see-more:hover {
            color: white;
            background-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .see-more i {
            transition: transform 0.3s ease;
        }
        
        .see-more:hover i {
            transform: translateX(3px);
        }
        
        /* Book cards with enhanced design */
        .books-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin: 0 -10px;
        }
        
        .book-card {
            position: relative;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.07);
            height: 100%;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
        }
        
        .book-img-container {
            height: 260px;
            overflow: hidden;
            position: relative;
        }
        
        .book-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s ease;
        }
        
        .book-card:hover .book-img {
            transform: scale(1.08);
        }
        
        .book-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background-color: var(--primary-color);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 2;
        }
        
        .book-details {
            padding: 18px;
        }
        
        .book-author {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .book-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 44px;
            color: var(--text-dark);
        }
        
        .book-price {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }
        
        .price-week {
            font-size: 0.8rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .price-value {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.15rem;
        }
        
        .book-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            gap: 10px;
        }
        
        .btn-rent, .btn-buy {
            flex: 1;
            text-align: center;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-rent {
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .btn-rent:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-buy {
            background-color: var(--success-color);
            color: white;
            border: 1px solid var(--success-color);
        }
        
        .btn-buy:hover {
            background-color: #0ca678;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }
        
        /* Theme tabs with enhanced design */
        .theme-tabs {
            display: flex;
            margin-bottom: 25px;
            border-bottom: none;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .theme-tab {
            padding: 8px 18px;
            cursor: pointer;
            font-weight: 600;
            border-radius: 20px;
            transition: all 0.3s ease;
            color: var(--text-light);
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        
        .theme-tab:hover {
            color: var(--primary-color);
            border-color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.05);
        }
        
        .theme-tab.active {
            color: white;
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }
        
        /* Libraries section with enhanced design */
        .libraries-section {
            margin-top: 50px;
            background-color: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
        }
        
        .search-bar {
            margin-bottom: 25px;
            position: relative;
        }
        
        .search-bar .form-control {
            padding: 12px 45px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
            background-color: #f8fafc;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .search-bar .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
        }
        
        .search-bar i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        .library-card {
            border: none;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            transition: all 0.3s ease;
            background-color: #f8fafc;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .library-card:hover {
            box-shadow: var(--hover-shadow);
            transform: translateY(-5px);
            background-color: white;
        }
        
        .library-img {
            width: 140px;
            height: 100px;
            border-radius: 10px;
            object-fit: cover;
            margin-right: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .library-details {
            flex: 1;
        }
        
        .library-name {
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        
        .library-type {
            font-size: 0.8rem;
            color: var(--text-muted);
            background-color: #f1f5f9;
            padding: 3px 8px;
            border-radius: 4px;
            margin-left: 8px;
        }
        
        .library-rating {
            display: flex;
            align-items: center;
        }
        
        .rating-value {
            margin-right: 5px;
            font-weight: 700;
        }
        
        .stars {
            color: var(--warning-color);
            font-size: 0.9rem;
            letter-spacing: 2px;
        }
        
        .library-location {
            font-size: 0.9rem;
            color: var(--text-light);
            margin: 8px 0;
        }
        
        .library-address {
            font-size: 0.9rem;
            margin-bottom: 12px;
        }
        
        .library-status {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            margin-top: 10px;
            color: var(--text-light);
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-open {
            background-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        
        .status-closed {
            background-color: var(--danger-color);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
        }
        
        .directions-btn {
            font-size: 0.9rem;
            color: var(--primary-color);
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            background-color: rgba(99, 102, 241, 0.1);
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
        }
        
        .directions-btn:hover {
            color: white;
            background-color: var(--primary-color);
            text-decoration: none;
        }
        
        /* Footer enhanced styling */
        .footer {
            padding: 60px 0 40px;
            border-top: 1px solid var(--border-color);
            margin-top: 60px;
            background-color: #f8fafc;
        }
        
        .footer-heading {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.1rem;
        }
        
        .footer-link {
            display: block;
            color: var(--text-light);
            margin-bottom: 10px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .footer-link:hover {
            color: var(--primary-color);
            padding-left: 8px;
        }
        
        .app-download {
            display: block;
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }
        
        .app-download:hover {
            transform: translateY(-3px);
        }
        
        .app-download img {
            height: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .social-links {
            margin-top: 25px;
        }
        
        .social-icon {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #f1f5f9;
            color: var(--primary-color);
            margin-right: 12px;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .social-icon:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }
        
        .copyright {
            padding: 20px 0;
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border-color);
            background-color: white;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .books-container {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 18px;
            }
        }

        @media (max-width: 992px) {
            .container {
                max-width: 100%;
                padding-left: 20px;
                padding-right: 20px;
            }

            .carousel {
                margin: 20px 0;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 15px;
            }

            .see-more {
                align-self: flex-end;
            }
        }

        @media (max-width: 768px) {
            .book-img-container {
                height: 220px;
            }

            .library-card {
                flex-direction: column;
            }

            .library-img {
                width: 100%;
                height: 140px;
                margin-right: 0;
                margin-bottom: 15px;
            }

            .books-container {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 15px;
                margin: 0 -5px;
            }

            .book-card {
                margin: 0 auto;
            }

            .book-details {
                padding: 15px;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .theme-tabs {
                gap: 8px;
            }

            .theme-tab {
                padding: 6px 14px;
                font-size: 0.9rem;
            }

            .libraries-section {
                padding: 25px 20px;
                margin-top: 30px;
            }

            .search-bar .form-control {
                padding: 10px 40px;
                font-size: 0.9rem;
            }

            .footer {
                padding: 40px 0 20px;
                margin-top: 40px;
            }

            .footer-heading {
                font-size: 1rem;
                margin-bottom: 15px;
            }
        }

        @media (max-width: 576px) {
            .books-container {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 12px;
            }

            .book-img-container {
                height: 200px;
            }

            .book-details {
                padding: 12px;
            }

            .book-title {
                font-size: 1rem;
                margin-bottom: 8px;
            }

            .book-author {
                font-size: 0.8rem;
            }

            .book-price {
                margin-top: 12px;
            }

            .price-value {
                font-size: 1rem;
            }

            .book-actions {
                margin-top: 12px;
                gap: 8px;
            }

            .btn-rent, .btn-buy {
                padding: 6px 10px;
                font-size: 0.85rem;
            }

            .section-title {
                font-size: 1.4rem;
            }

            .theme-tabs {
                justify-content: center;
                flex-wrap: wrap;
            }

            .theme-tab {
                padding: 5px 12px;
                font-size: 0.85rem;
            }

            .carousel-caption h3 {
                font-size: 1.2rem;
            }

            .carousel-caption p {
                font-size: 0.9rem;
            }

            .library-name {
                font-size: 1rem;
            }

            .library-location, .library-address {
                font-size: 0.85rem;
            }

            .directions-btn {
                font-size: 0.8rem;
                padding: 4px 10px;
            }

            .navbar-brand img {
                height: 50px;
            }

            .footer-link {
                font-size: 0.9rem;
            }

            .social-icon {
                width: 32px;
                height: 32px;
                margin-right: 10px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }

            .books-container {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 10px;
            }

            .book-img-container {
                height: 180px;
            }

            .book-details {
                padding: 10px;
            }

            .book-title {
                font-size: 0.95rem;
            }

            .section-title {
                font-size: 1.3rem;
            }

            .libraries-section {
                padding: 20px 15px;
            }

            .search-bar .form-control {
                padding: 8px 35px;
                font-size: 0.85rem;
            }

            .tab-menu {
                padding: 8px 10px;
            }
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

    <!-- Navigation tabs -->
    <div class="container  pt-3">
    <div class="tab-menu">
      <a href="dashboard.php" class="active">
          <svg class="tab-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          Home
      </a>
      <a href="rentbooks.php">
         <svg class="tab-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
          </svg>
          Rentbooks
      </a>
      <a href="explore.php">
          <svg class="tab-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
          Forum
      </a>
      <a href="libraries.php">
          <svg class="tab-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            <line x1="12" y1="6" x2="12" y2="14"></line>
            <rect x="9" y="6" width="6" height="3"></rect>
          </svg>
          Libraries
      </a>
      <a href="bookswap.php">
          <svg class="tab-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="none">
            <path d="M21,9L17,5V8H10V10H17V13M7,11L3,15L7,19V16H14V14H7V11Z" />
          </svg>
          Bookswap
      </a>
    </div>

    <!-- Hero Carousel -->
    <div class="container">
        <div id="heroCarousel" class="carousel slide mx-auto" data-bs-ride="carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="images/1.jpg" class="d-block w-100" alt="Philippine Book Festival">
                    <div class="carousel-caption d-none d-md-block">
                        <h3>Philippine Book Festival</h3>
                        <p>Join us for the biggest literary event of the year</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="images/2.png" class="d-block w-100" alt="BookWagon Feature">
                    <div class="carousel-caption d-none d-md-block">
                        <h3>Join Our Community</h3>
                        <p>Connect with fellow book lovers</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="images/3.png" class="d-block w-100" alt="BookWagon Promotion">
                    <div class="carousel-caption d-none d-md-block">
                        <h3>Special Offers</h3>
                        <p>Get exclusive deals on your favorite titles</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="images/4.jpg" class="d-block w-100" alt="BookWagon Event">
                    <div class="carousel-caption d-none d-md-block">
                        <h3>Author Meet & Greet</h3>
                        <p>Meet your favorite authors in person</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="images/5.jpg" class="d-block w-100" alt="BookWagon Special">
                    <div class="carousel-caption d-none d-md-block">
                        <h3>New Arrivals Weekly</h3>
                        <p>Fresh titles added to our collection every week</p>
                    </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="4" aria-label="Slide 5"></button>
            </div>
        </div>
    </div>
    
    <!-- Most Popular -->
    <div class="container">
        <div class="section-header">
            <h4 class="section-title">Most Popular</h4>
            <a href="rentbooks.php?filter=Most popular" class="see-more">See More <i class="fas fa-chevron-right"></i></a>
        </div>
        
        <div class="books-container">
            <?php if ($popularBooksResult->num_rows > 0): ?>
                <?php while($book = $popularBooksResult->fetch_assoc()): ?>
                    <div class="book-card">
                        <div class="book-img-container">
                            <img src="<?php echo $book['cover_image'] ?: 'https://via.placeholder.com/200x250?text=No+Cover'; ?>" class="book-img" alt="<?php echo htmlspecialchars($book['title']); ?>">
                            <span class="book-badge">Popular</span>
                        </div>
                        <div class="book-details">
                            <div class="book-author"><?php echo htmlspecialchars($book['author']); ?></div>
                            <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                            <div class="book-price">
                                <span class="price-week">₱<?php echo number_format($book['rent_price'], 2); ?>/week</span>
                                <span class="price-value">₱<?php echo number_format($book['price'], 2); ?></span>
                            </div>
                            <div class="book-actions">
                                <a href="rentbooks.php?book_id=<?php echo $book['book_id']; ?>" class="btn-rent">Rent</a>
                                <a href="checkout.php?book_id=<?php echo $book['book_id']; ?>" class="btn-buy">Buy</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-4">
                    <p>No popular books found. Check back later!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- New Releases -->
    <div class="container">
        <div class="section-header">
            <h4 class="section-title">New Releases</h4>
            <a href="rentbooks.php?filter=new" class="see-more">See More <i class="fas fa-chevron-right"></i></a>
        </div>
        
        <div class="books-container">
            <?php if ($newReleasesResult->num_rows > 0): ?>
                <?php while($book = $newReleasesResult->fetch_assoc()): ?>
                    <div class="book-card">
                        <div class="book-img-container">
                            <img src="<?php echo $book['cover_image'] ?: 'https://via.placeholder.com/200x250?text=No+Cover'; ?>" class="book-img" alt="<?php echo htmlspecialchars($book['title']); ?>">
                            <span class="book-badge">New</span>
                        </div>
                        <div class="book-details">
                            <div class="book-author"><?php echo htmlspecialchars($book['author']); ?></div>
                            <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                            <div class="book-price">
                                <span class="price-week">₱<?php echo number_format($book['rent_price'], 2); ?>/week</span>
                                <span class="price-value">₱<?php echo number_format($book['price'], 2); ?></span>
                            </div>
                            <div class="book-actions">
                                <a href="rentbooks.php?book_id=<?php echo $book['book_id']; ?>" class="btn-rent">Rent</a>
                                <a href="checkout.php?book_id=<?php echo $book['book_id']; ?>" class="btn-buy">Buy</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-4">
                    <p>No new releases found. Check back later!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Explore Section -->
    <div class="container">
        <div class="section-header">
            <h4 class="section-title">Explore by Theme</h4>
        </div>
        
        <div class="theme-tabs">
            <div class="theme-tab active">All</div>
            <?php foreach($themes as $theme): ?>
                <div class="theme-tab"><?php echo htmlspecialchars($theme); ?></div>
            <?php endforeach; ?>
        </div>
        
        <div class="books-container">
            <?php 
            // Fetch 4 random books to display in explore section
            $exploreQuery = "SELECT * FROM books ORDER BY RAND() LIMIT 4";
            $exploreResult = $conn->query($exploreQuery);
            
            if ($exploreResult->num_rows > 0): 
                while($book = $exploreResult->fetch_assoc()): ?>
                    <div class="book-card">
                        <div class="book-img-container">
                            <img src="<?php echo $book['cover_image'] ?: 'https://via.placeholder.com/200x250?text=No+Cover'; ?>" class="book-img" alt="<?php echo htmlspecialchars($book['title']); ?>">
                            <?php if($book['theme']): ?>
                                <span class="book-badge"><?php echo htmlspecialchars($book['theme']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="book-details">
                            <div class="book-author"><?php echo htmlspecialchars($book['author']); ?></div>
                            <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                            <div class="book-price">
                                <span class="price-week">Theme Book</span>
                                <span class="price-value">₱<?php echo number_format($book['price'], 2); ?></span>
                            </div>
                            <div class="book-actions">
                                <a href="book_details.php?id=<?php echo $book['book_id']; ?>" class="btn-rent">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-4">
                    <p>No books found. Check back later!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-end mt-4 mb-5">
            <a href="rentbooks.php" class="see-more">Browse All Books <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    
    <!-- Libraries Section -->
    <div class="container libraries-section">
        <h4 class="section-title mb-4">Libraries Near You</h4>
        
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" class="form-control" placeholder="Search libraries by name or location...">
        </div>
        
        <div class="mb-3 fw-semibold text-muted">Suggested for you</div>
        
        <div class="library-card">
            <img src="Images/up.jpg" class="library-img" alt="Davao Central College Library">
            <div class="library-details">
                <div class="library-name">
                    <div>Davao Central College Library <span class="library-type">Private</span></div>
                    <div class="library-rating">
                        <span class="rating-value">5.0</span>
                        <div class="stars">★★★★★</div>
                    </div>
                </div>
                <div class="library-location">
                    <i class="fa-solid fa-location-dot"></i> 3 kilometers from your current location
                </div>
                <div class="library-address">
                    Don Juan Street, Toril<br>
                    Davao City 8000
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <a href="#" class="directions-btn">
                        <i class="fa-solid fa-directions me-1"></i> Get Directions
                    </a>
                    <div class="library-status">
                        <span class="status-indicator status-open"></span>
                        Open • Weekdays 8am to 7pm
                    </div>
                </div>
            </div>
        </div>
        
        <div class="library-card">
            <img src="images/Davao library.jpg" class="library-img" alt="Davao City Library">
            <div class="library-details">
                <div class="library-name">
                    <div>Davao City Library and Information Center <span class="library-type">Public</span></div>
                    <div class="library-rating">
                        <span class="rating-value">5.0</span>
                        <div class="stars">★★★★★</div>
                    </div>
                </div>
                <div class="library-location">
                    <i class="fa-solid fa-location-dot"></i> 6 kilometers from your current location
                </div>
                <div class="library-address">
                    152 San Pedro St, Poblacion District,<br>
                    Davao City, 8000 Davao del Sur
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <a href="#" class="directions-btn">
                        <i class="fa-solid fa-directions me-1"></i> Get Directions
                    </a>
                    <div class="library-status">
                        <span class="status-indicator status-open"></span>
                        Open • Weekdays 8am to 7pm
                    </div>
                </div>
            </div>
        </div>
        
        <div class="library-card">
            <img src="Images/usep.jpg" class="library-img" alt="University Library">
            <div class="library-details">
                <div class="library-name">
                    <div>University Library <span class="library-type">Private</span></div>
                    <div class="library-rating">
                        <span class="rating-value">5.0</span>
                        <div class="stars">★★★★★</div>
                    </div>
                </div>
                <div class="library-location">
                    <i class="fa-solid fa-location-dot"></i> 5 kilometers from your current location
                </div>
                <div class="library-address">
                    JHMP+WH, Tugbok,<br>
                    Davao City, Davao del Sur
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <a href="#" class="directions-btn">
                        <i class="fa-solid fa-directions me-1"></i> Get Directions
                    </a>
                    <div class="library-status">
                        <span class="status-indicator status-closed"></span>
                        Closed • Opens 8 AM Mon
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="libraries.php" class="see-more">
                <i class="fas fa-map-marker-alt me-1"></i> View All Libraries
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-6 mb-4">
                    <h5 class="footer-heading">Company</h5>
                    <a href="#" class="footer-link">About Us</a>
                    <a href="#" class="footer-link">Help/FAQ</a>
                    <a href="#" class="footer-link">Works</a>
                    <a href="#" class="footer-link">Careers</a>
                </div>
                
                <div class="col-md-3 col-6 mb-4">
                    <h5 class="footer-heading">Help</h5>
                    <a href="#" class="footer-link">Customer Support</a>
                    <a href="#" class="footer-link">Delivery Details</a>
                    <a href="#" class="footer-link">Terms & Conditions</a>
                    <a href="#" class="footer-link">Privacy Policy</a>
                </div>
                
                <div class="col-md-3 col-6 mb-4">
                    <h5 class="footer-heading">Resources</h5>
                    <a href="#" class="footer-link">Free eBooks</a>
                    <a href="#" class="footer-link">Development</a>
                    <a href="#" class="footer-link">Tutorials</a>
                    <a href="#" class="footer-link">How-to Videos</a>
                    <a href="#" class="footer-link">YouTube Playlist</a>
                </div>
                
                <div class="col-md-3 col-6 mb-4">
                    <div class="mb-4">
                        <h5 class="footer-heading">Get the App</h5>
                        <a href="#" class="app-download">
                            <img src="https://i.ibb.co/kSzkRgQ/app-store.png" alt="App Store">
                        </a>
                        <a href="#" class="app-download">
                            <img src="https://i.ibb.co/FqJsKND/play-store.png" alt="Play Store">
                        </a>
                    </div>
                    <div class="social-links">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    
    <div class="copyright">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>© Copyright <?php echo date('Y'); ?>, All Rights Reserved by BookWagon</div>
                <div>
                    <a href="#" class="text-muted me-3">Terms</a>
                    <a href="#" class="text-muted">Privacy</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize carousel
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Carousel(document.querySelector('#heroCarousel'), {
                interval: 5000, // Change slides every 5 seconds
                wrap: true     // Continue from last to first slide
            });
            
            // Theme tab functionality with animation
            const tabs = document.querySelectorAll('.theme-tab');
            const books = document.querySelectorAll('.books-container .book-card');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    const selectedTheme = this.innerText.trim();
                    
                    // Fade out all books first
                    books.forEach(book => {
                        book.style.opacity = '0.5';
                        book.style.transform = 'translateY(10px)';
                        book.style.transition = 'all 0.3s ease';
                    });
                    
                    // Simulate loading new books by theme
                    setTimeout(() => {
                        books.forEach(book => {
                            book.style.opacity = '1';
                            book.style.transform = 'translateY(0)';
                        });
                    }, 400);
                });
            });
            
            // Smooth scroll for "See more" links
            document.querySelectorAll('.see-more').forEach(link => {
                link.addEventListener('click', function(e) {
                    // Don't smooth scroll for links pointing to other pages
                    if (this.getAttribute('href').includes('#')) {
                        e.preventDefault();
                        const targetId = this.getAttribute('href').split('#')[1];
                        const targetElement = document.getElementById(targetId);
                        if (targetElement) {
                            window.scrollTo({
                                top: targetElement.offsetTop - 100,
                                behavior: 'smooth'
                            });
                        }
                    }
                });
            });
            
            // Add hover effects to book cards
            const bookCards = document.querySelectorAll('.book-card');
            bookCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px)';
                    this.style.boxShadow = '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.07)';
                });
            });
            
            // Enhance search input
            const searchInput = document.querySelector('.search-bar .form-control');
            if (searchInput) {
                searchInput.addEventListener('focus', function() {
                    this.parentElement.style.boxShadow = '0 0 0 3px rgba(99, 102, 241, 0.25)';
                });
                
                searchInput.addEventListener('blur', function() {
                    this.parentElement.style.boxShadow = 'none';
                });
            }
        });
        
        // Prevent back button after logout
        window.onload = function() {
            if(typeof window.history.pushState == 'function') {
                window.history.pushState({}, "Hide", location.href);
            }
            window.onpopstate = function() {
                window.history.go(1);
            };
        };
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('#userDropdown').addEventListener('click', function(e) {
                e.preventDefault();
                var dropdown = bootstrap.Dropdown.getOrCreateInstance(this);
                dropdown.toggle();
            });
        });
    </script>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- IntroJS for the interactive tour (with fallback) -->
    <script src="https://unpkg.com/intro.js/minified/intro.min.js"></script>
    <script>
        // Check if intro.js loaded correctly, if not, try another CDN
        if (typeof introJs === 'undefined') {
            console.error("IntroJS not loaded from primary CDN, trying fallback...");
            let script = document.createElement('script');
            script.src = "https://cdnjs.cloudflare.com/ajax/libs/intro.js/6.0.0/intro.min.js";
            document.head.appendChild(script);
            
            let link = document.createElement('link');
            link.rel = "stylesheet";
            link.href = "https://cdnjs.cloudflare.com/ajax/libs/intro.js/6.0.0/introjs.min.css";
            document.head.appendChild(link);
        } else {
            console.log("IntroJS loaded successfully!");
        }
    </script>
    <link rel="stylesheet" href="https://unpkg.com/intro.js/minified/introjs.min.css">
    
    <!-- Custom tour script -->
    <script src="js/book-tour.js"></script>
</body>
</html>