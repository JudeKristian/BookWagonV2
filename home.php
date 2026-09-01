<?php
include("session.php");
include("connect.php");
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookWagon - Home</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-dark);
            background-color: #fff;
        }
        
        /* Header styles */
        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .navbar-brand img {
            height: 40px;
        }
        
        /* Carousel styles */
        .carousel {
            margin: 20px 0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .carousel-item img {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
        }
        
        .carousel-control-prev, .carousel-control-next {
            width: 5%;
            opacity: 0.8;
        }
        
        .carousel-indicators {
            bottom: -30px;
        }
        
        .carousel-indicators button {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #ccc;
            margin: 0 5px;
        }
        
        .carousel-indicators .active {
            background-color: var(--primary-color);
        }
        
        /* Section headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            margin-top: 30px;
        }
        
        .section-title {
            font-weight: 600;
            margin: 0;
        }
        
        .see-more {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        /* Enhanced Book Cards */
        .book-card {
            margin-bottom: 25px;
        }
        
        .book-card-wrapper {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 14px;
            padding: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        
        .book-card-wrapper:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.08);
            border-color: #ffd992;
        }
        
        .book-img-container {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 190px;
            margin-bottom: 12px;
        }
        
        .book-img-container img {
            height: 100%;
            width: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
            border-radius: 10px;
        }
        
        .book-card-wrapper:hover .book-img-container img {
            transform: scale(1.06);
        }
        
        .book-author-tag {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .book-card-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .book-card-title a {
            color: #1e293b;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .book-card-title a:hover {
            color: #f8a100;
        }
        
        .book-pricing-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }
        
        .rent-pill {
            background-color: #fff8eb;
            color: #e68a00;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #ffe6be;
        }
        
        .buy-text {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }
        
        .btn-rent-quick {
            background: #f8a100;
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            padding: 6px 0;
            text-align: center;
            text-decoration: none;
            display: block;
            margin-top: 10px;
            transition: background-color 0.2s ease;
            border: none;
        }
        
        .btn-rent-quick:hover {
            background: #e08f00;
            color: #ffffff;
        }
        
        /* 1-by-1 Book Carousel */
        .book-carousel-container {
            position: relative;
            padding: 0 5px;
        }
        
        .book-carousel-track {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            gap: 16px;
            padding: 10px 4px 20px 4px;
            scrollbar-width: none;
            -ms-overflow-style: none;
            scroll-snap-type: x mandatory;
        }
        
        .book-carousel-track::-webkit-scrollbar {
            display: none;
        }
        
        .book-carousel-item {
            flex: 0 0 220px;
            max-width: 220px;
            scroll-snap-align: start;
        }
        
        @media (max-width: 992px) {
            .book-carousel-item {
                flex: 0 0 190px;
                max-width: 190px;
            }
        }
        
        @media (max-width: 576px) {
            .book-carousel-item {
                flex: 0 0 160px;
                max-width: 160px;
            }
        }
        
        .carousel-nav-btn {
            position: absolute;
            top: 48%;
            transform: translateY(-50%);
            width: 42px;
            height: 42px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #334155;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
            cursor: pointer;
            z-index: 10;
            transition: all 0.2s ease;
        }
        
        .carousel-nav-btn:hover {
            background: #f8a100;
            color: #ffffff;
            border-color: #f8a100;
            box-shadow: 0 6px 18px rgba(248, 161, 0, 0.35);
            transform: translateY(-50%) scale(1.08);
        }
        
        .carousel-nav-btn.prev-btn {
            left: -18px;
        }
        
        .carousel-nav-btn.next-btn {
            right: -18px;
        }
        
        /* Hero Carousel Pill Indicators */
        .hero-indicator-btn {
            width: 8px;
            height: 8px;
            border-radius: 4px;
            background-color: #cbd5e1;
            border: none;
            padding: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        
        .hero-indicator-btn.active {
            width: 28px;
            background-color: #f8a100;
            box-shadow: 0 2px 8px rgba(248, 161, 0, 0.45);
        }
        
        /* Feature Value Strip */
        .feature-strip-item {
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        
        .feature-strip-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08) !important;
            border-color: #cbd5e1 !important;
        }
        
        /* See More Orange Theme */
        .see-more {
            color: #f8a100 !important;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }
        
        .see-more:hover {
            color: #d97706 !important;
            transform: translateX(4px);
        }
        
        /* Header & Navbar Hover Effects */
        .navbar .nav-link {
            transition: color 0.2s ease, transform 0.2s ease;
        }
        
        .navbar .nav-link:hover {
            color: #f8a100 !important;
        }
        
        .navbar .nav-link:hover i {
            color: #f8a100 !important;
            transform: scale(1.12);
        }
        
        .navbar .nav-link i {
            transition: transform 0.2s ease, color 0.2s ease;
        }
        
        .dropdown-menu .dropdown-item {
            transition: all 0.2s ease;
        }
        
        .dropdown-menu .dropdown-item:hover {
            background-color: rgba(248, 161, 0, 0.08);
            color: #f8a100 !important;
        }
        
        .dropdown-menu .dropdown-item:hover i {
            color: #f8a100 !important;
        }
        
        /* Category tabs */
        .category-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .category-tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .category-tab:hover {
            color: #f8a100;
            border-bottom: 2px solid rgba(248, 161, 0, 0.5);
        }
        
        .category-tab.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }
        
        /* Libraries section */
        .libraries-section {
            margin-top: 40px;
        }
        
        .search-bar {
            margin-bottom: 20px;
        }
        
        .library-card {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
        }
        
        .library-img {
            width: 120px;
            height: 80px;
            border-radius: 5px;
            object-fit: cover;
            margin-right: 15px;
        }
        
        .library-details {
            flex: 1;
        }
        
        .library-name {
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .library-type {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .library-rating {
            display: flex;
            align-items: center;
        }
        
        .rating-value {
            margin-right: 5px;
        }
        
        .stars {
            color: var(--primary-color);
            font-size: 0.8rem;
        }
        
        .library-location {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 5px 0;
        }
        
        .library-address {
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .library-status {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            margin-top: 10px;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-open {
            background-color: #28a745;
        }
        
        .status-closed {
            background-color: #dc3545;
        }
        
        .directions-btn {
            font-size: 0.85rem;
            color: var(--primary-color);
            text-decoration: none;
            cursor: pointer;
        }
        
        /* BookWagon Footer Styles */
        .footer-link-hover {
            transition: all 0.2s ease;
            display: inline-block;
        }
        
        .footer-link-hover:hover {
            color: #f8a100 !important;
            transform: translateX(4px);
        }
        
        .social-icon-btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: #e2e8f0;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 13px;
        }
        
        .social-icon-btn:hover {
            background-color: #f8a100;
            color: #ffffff;
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(248, 161, 0, 0.35);
        }
    </style>
</head>
<body>
    <!-- User Header Navigation -->
    <?php include("include/user_header.php"); ?>

    <!-- Navigation tabs -->
    <?php include 'include/tab.php'; ?>

    <!-- Carousel Banner -->
    <div class="container my-4">
        <div class="position-relative mx-auto" style="max-width: 820px;">
            <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner shadow-sm" style="border-radius: 16px; overflow: hidden;">
                    <div class="carousel-item active">
                        <img src="images/1.jpg" class="d-block w-100" alt="Philippine Book Festival" style="height: 320px; object-fit: cover;" onerror="this.src='https://placehold.co/800x320?text=Philippine+Book+Festival'">
                    </div>
                    <div class="carousel-item">
                        <img src="images/2.png" class="d-block w-100" alt="BookWagon Feature" style="height: 320px; object-fit: cover;" onerror="this.src='https://placehold.co/800x320?text=BookWagon+Banner+2'">
                    </div>
                    <div class="carousel-item">
                        <img src="images/3.png" class="d-block w-100" alt="BookWagon Promo" style="height: 320px; object-fit: cover;" onerror="this.src='https://placehold.co/800x320?text=BookWagon+Banner+3'">
                    </div>
                    <div class="carousel-item">
                        <img src="images/4.jpg" class="d-block w-100" alt="BookWagon Event" style="height: 320px; object-fit: cover;" onerror="this.src='https://placehold.co/800x320?text=BookWagon+Banner+4'">
                    </div>
                    <div class="carousel-item">
                        <img src="images/5.jpg" class="d-block w-100" alt="BookWagon Special" style="height: 320px; object-fit: cover;" onerror="this.src='https://placehold.co/800x320?text=BookWagon+Banner+5'">
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev" style="left: -50px; width: 40px;">
                    <span class="carousel-control-prev-icon text-dark" aria-hidden="true" style="filter: invert(0.5); transform: scale(1.5);"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next" style="right: -50px; width: 40px;">
                    <span class="carousel-control-next-icon text-dark" aria-hidden="true" style="filter: invert(0.5); transform: scale(1.5);"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
            <!-- Smooth Pill Indicators Below -->
            <div class="d-flex justify-content-center align-items-center gap-2 mt-3">
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="hero-indicator-btn active" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" class="hero-indicator-btn" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" class="hero-indicator-btn" aria-label="Slide 3"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3" class="hero-indicator-btn" aria-label="Slide 4"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="4" class="hero-indicator-btn" aria-label="Slide 5"></button>
            </div>
        </div>
    </div>
    
    <!-- Feature / Value Strip -->
    <div class="container my-4">
        <div class="row g-3">
            <div class="col-md-4">
                <a href="rentbooks.php" class="text-decoration-none">
                    <div class="p-3 bg-white border rounded-3 shadow-sm d-flex align-items-center gap-3 h-100 feature-strip-item" style="transition: all 0.2s ease;">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background: rgba(248, 161, 0, 0.12); color: #f8a100; font-size: 18px;">
                            <i class="fa-solid fa-book-open-reader"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 14px;">Affordable Book Rentals</h6>
                            <small class="text-muted" style="font-size: 12px;">Rent top titles starting from ₱50/wk</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="bookswap.php" class="text-decoration-none">
                    <div class="p-3 bg-white border rounded-3 shadow-sm d-flex align-items-center gap-3 h-100 feature-strip-item" style="transition: all 0.2s ease;">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; font-size: 18px;">
                            <i class="fa-solid fa-repeat"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 14px;">Book Swap Community</h6>
                            <small class="text-muted" style="font-size: 12px;">Trade books with readers near you</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="libraries.php" class="text-decoration-none">
                    <div class="p-3 bg-white border rounded-3 shadow-sm d-flex align-items-center gap-3 h-100 feature-strip-item" style="transition: all 0.2s ease;">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; background: rgba(16, 185, 129, 0.12); color: #10b981; font-size: 18px;">
                            <i class="fa-solid fa-landmark"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 14px;">Davao Library Hubs</h6>
                            <small class="text-muted" style="font-size: 12px;">Explore public & campus study spaces</small>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Most Popular -->
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold m-0 text-dark">Most Popular</h5>
            <a href="rentbooks.php" class="see-more">
                See More <i class="fa-solid fa-chevron-right" style="font-size: 11px;"></i>
            </a>
        </div>
        
        <div class="book-carousel-container" id="popularCarousel">
            <button type="button" class="carousel-nav-btn prev-btn" onclick="scrollCarousel('popularCarousel', -1)" aria-label="Previous">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            
            <div class="book-carousel-track">
                <?php
                // Fetch random books listed in the rent books section
                $popularBooksQuery = "SELECT b.*, u.firstname, u.lastname 
                                      FROM books b
                                      LEFT JOIN users u ON b.user_id = u.id
                                      ORDER BY RAND() 
                                      LIMIT 10";
                $popularBooksResult = $conn->query($popularBooksQuery);
                
                if ($popularBooksResult && $popularBooksResult->num_rows > 0):
                    while ($book = $popularBooksResult->fetch_assoc()):
                        $authorDisplay = !empty($book['author']) ? stripslashes($book['author']) : (trim(($book['firstname'] ?? '') . ' ' . ($book['lastname'] ?? '')) ?: 'Unknown Author');
                        $bookTitle = stripslashes($book['title'] ?? '');
                        
                        $coverPath = 'images/boooks/noli_me_tangere.jpg';
                        if (!empty($book['cover_image'])) {
                            if (file_exists($book['cover_image'])) {
                                $coverPath = $book['cover_image'];
                            } elseif (file_exists('images/boooks/' . $book['cover_image'])) {
                                $coverPath = 'images/boooks/' . $book['cover_image'];
                            } elseif (file_exists('uploads/covers/' . $book['cover_image'])) {
                                $coverPath = 'uploads/covers/' . $book['cover_image'];
                            } elseif (file_exists('images/' . $book['cover_image'])) {
                                $coverPath = 'images/' . $book['cover_image'];
                            } else {
                                $coverPath = $book['cover_image'];
                            }
                        }
                        
                        $rentPrice = !empty($book['rent_price']) ? $book['rent_price'] : ($book['price'] ? round($book['price'] / 6, 2) : 60);
                        $buyPrice = !empty($book['price']) ? $book['price'] : ($rentPrice * 6);
                ?>
                    <div class="book-carousel-item book-card">
                        <div class="book-card-wrapper">
                            <div class="book-author-tag" title="<?php echo htmlspecialchars($authorDisplay); ?>">
                                <i class="fa-regular fa-user me-1 text-muted"></i><?php echo htmlspecialchars($authorDisplay); ?>
                            </div>
                            <a href="book_details.php?id=<?php echo $book['book_id']; ?>" class="book-img-container text-decoration-none">
                                <img src="<?php echo htmlspecialchars($coverPath); ?>" alt="<?php echo htmlspecialchars($bookTitle); ?>" onerror="this.src='images/boooks/noli_me_tangere.jpg'">
                            </a>
                            <div class="book-card-title" title="<?php echo htmlspecialchars($bookTitle); ?>">
                                <a href="book_details.php?id=<?php echo $book['book_id']; ?>">
                                    <?php echo htmlspecialchars($bookTitle); ?>
                                </a>
                            </div>
                            <div class="book-pricing-row">
                                <span class="rent-pill">₱<?php echo number_format($rentPrice, 0); ?>/wk</span>
                                <span class="buy-text">₱<?php echo number_format($buyPrice, 0); ?></span>
                            </div>
                            <a href="book_details.php?id=<?php echo $book['book_id']; ?>" class="btn-rent-quick">
                                Rent Now
                            </a>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else:
                ?>
                    <!-- Fallback items -->
                    <div class="book-carousel-item book-card">
                        <div class="book-card-wrapper">
                            <div class="book-author-tag">Kris Johanna Laniaza</div>
                            <a href="rentbooks.php" class="book-img-container text-decoration-none">
                                <img src="images/boooks/noli_me_tangere.jpg" alt="Noli Me Tangere">
                            </a>
                            <div class="book-card-title"><a href="rentbooks.php">NOLI ME TANGERE</a></div>
                            <div class="book-pricing-row">
                                <span class="rent-pill">₱60/wk</span>
                                <span class="buy-text">₱360</span>
                            </div>
                            <a href="rentbooks.php" class="btn-rent-quick">Rent Now</a>
                        </div>
                    </div>
                    <div class="book-carousel-item book-card">
                        <div class="book-card-wrapper">
                            <div class="book-author-tag">Kris Johanna Laniaza</div>
                            <a href="rentbooks.php" class="book-img-container text-decoration-none">
                                <img src="images/boooks/harry_potter_1.jpg" alt="Harry Potter">
                            </a>
                            <div class="book-card-title"><a href="rentbooks.php">Harry Potter</a></div>
                            <div class="book-pricing-row">
                                <span class="rent-pill">₱60/wk</span>
                                <span class="buy-text">₱360</span>
                            </div>
                            <a href="rentbooks.php" class="btn-rent-quick">Rent Now</a>
                        </div>
                    </div>
                    <div class="book-carousel-item book-card">
                        <div class="book-card-wrapper">
                            <div class="book-author-tag">Zenepachi Zenny</div>
                            <a href="rentbooks.php" class="book-img-container text-decoration-none">
                                <img src="images/boooks/divergent.jpg" alt="Divergent">
                            </a>
                            <div class="book-card-title"><a href="rentbooks.php">Divergent</a></div>
                            <div class="book-pricing-row">
                                <span class="rent-pill">₱70/wk</span>
                                <span class="buy-text">₱380</span>
                            </div>
                            <a href="rentbooks.php" class="btn-rent-quick">Rent Now</a>
                        </div>
                    </div>
                    <div class="book-carousel-item book-card">
                        <div class="book-card-wrapper">
                            <div class="book-author-tag">Jay anne Galas</div>
                            <a href="rentbooks.php" class="book-img-container text-decoration-none">
                                <img src="images/boooks/abnkkbsnplako.jpg" alt="ABNKKBSNPLAko">
                            </a>
                            <div class="book-card-title"><a href="rentbooks.php">ABNKKBSNPLAko?!</a></div>
                            <div class="book-pricing-row">
                                <span class="rent-pill">₱50/wk</span>
                                <span class="buy-text">₱350</span>
                            </div>
                            <a href="rentbooks.php" class="btn-rent-quick">Rent Now</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" class="carousel-nav-btn next-btn" onclick="scrollCarousel('popularCarousel', 1)" aria-label="Next">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
    
    <!-- Explore Section -->
    <div class="container mt-5">
        <div class="section-header d-flex justify-content-between align-items-center mb-3">
            <h4 class="section-title fw-bold m-0 text-dark">Explore</h4>
            <a href="rentbooks.php" class="see-more">
                See More <i class="fa-solid fa-chevron-right" style="font-size: 11px;"></i>
            </a>
        </div>
        
        <div class="category-tabs mb-3">
            <div class="category-tab active" data-filter="all">All</div>
            <div class="category-tab" data-filter="sci-fi">Sci-Fi</div>
            <div class="category-tab" data-filter="education">Education</div>
            <div class="category-tab" data-filter="non-fiction">Non-Fiction</div>
            <div class="category-tab" data-filter="fiction">Fiction</div>
            <div class="category-tab" data-filter="drama">Drama</div>
        </div>
        
        <div class="book-carousel-container" id="exploreCarousel">
            <button type="button" class="carousel-nav-btn prev-btn" onclick="scrollCarousel('exploreCarousel', -1)" aria-label="Previous">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            
            <div class="book-carousel-track" id="exploreBooksContainer">
                <?php
                // Fetch random books for explore section from database
                $exploreBooksQuery = "SELECT b.*, u.firstname, u.lastname 
                                      FROM books b
                                      LEFT JOIN users u ON b.user_id = u.id
                                      ORDER BY RAND() 
                                      LIMIT 15";
                $exploreBooksResult = $conn->query($exploreBooksQuery);
                
                if ($exploreBooksResult && $exploreBooksResult->num_rows > 0):
                    while ($exploreBook = $exploreBooksResult->fetch_assoc()):
                        $authorDisplay = !empty($exploreBook['author']) ? stripslashes($exploreBook['author']) : (trim(($exploreBook['firstname'] ?? '') . ' ' . ($exploreBook['lastname'] ?? '')) ?: 'Unknown Author');
                        $exploreTitle = stripslashes($exploreBook['title'] ?? '');
                        
                        $coverPath = 'images/boooks/noli_me_tangere.jpg';
                        if (!empty($exploreBook['cover_image'])) {
                            if (file_exists($exploreBook['cover_image'])) {
                                $coverPath = $exploreBook['cover_image'];
                            } elseif (file_exists('images/boooks/' . $exploreBook['cover_image'])) {
                                $coverPath = 'images/boooks/' . $exploreBook['cover_image'];
                            } elseif (file_exists('uploads/covers/' . $exploreBook['cover_image'])) {
                                $coverPath = 'uploads/covers/' . $exploreBook['cover_image'];
                            } elseif (file_exists('images/' . $exploreBook['cover_image'])) {
                                $coverPath = 'images/' . $exploreBook['cover_image'];
                            } else {
                                $coverPath = $exploreBook['cover_image'];
                            }
                        }
                        
                        $rentPrice = !empty($exploreBook['rent_price']) ? $exploreBook['rent_price'] : ($exploreBook['price'] ? round($exploreBook['price'] / 6, 2) : 60);
                        $buyPrice = !empty($exploreBook['price']) ? $exploreBook['price'] : ($rentPrice * 6);
                        $bookGenre = strtolower($exploreBook['genre'] ?? 'fiction');
                ?>
                    <div class="book-carousel-item book-card explore-book-item" data-category="<?php echo htmlspecialchars($bookGenre); ?>">
                        <div class="book-card-wrapper">
                            <div class="book-author-tag" title="<?php echo htmlspecialchars($authorDisplay); ?>">
                                <i class="fa-regular fa-user me-1 text-muted"></i><?php echo htmlspecialchars($authorDisplay); ?>
                            </div>
                            <a href="book_details.php?id=<?php echo $exploreBook['book_id']; ?>" class="book-img-container text-decoration-none">
                                <img src="<?php echo htmlspecialchars($coverPath); ?>" alt="<?php echo htmlspecialchars($exploreTitle); ?>" onerror="this.src='images/boooks/noli_me_tangere.jpg'">
                            </a>
                            <div class="book-card-title" title="<?php echo htmlspecialchars($exploreTitle); ?>">
                                <a href="book_details.php?id=<?php echo $exploreBook['book_id']; ?>">
                                    <?php echo htmlspecialchars($exploreTitle); ?>
                                </a>
                            </div>
                            <div class="book-pricing-row">
                                <span class="rent-pill">₱<?php echo number_format($rentPrice, 0); ?>/wk</span>
                                <span class="buy-text">₱<?php echo number_format($buyPrice, 0); ?></span>
                            </div>
                            <a href="book_details.php?id=<?php echo $exploreBook['book_id']; ?>" class="btn-rent-quick">
                                Rent Now
                            </a>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else:
                ?>
                    <!-- Fallback books -->
                    <div class="book-carousel-item book-card explore-book-item" data-category="drama">
                        <div class="book-card-wrapper">
                            <div class="book-author-tag">Meg Cabot</div>
                            <a href="rentbooks.php" class="book-img-container text-decoration-none">
                                <img src="images/boooks/noli_me_tangere.jpg" alt="The Fall of a Drama Queen">
                            </a>
                            <div class="book-card-title"><a href="rentbooks.php">The Fall of a Drama Queen</a></div>
                            <div class="book-pricing-row">
                                <span class="rent-pill">₱55/wk</span>
                                <span class="buy-text">₱320</span>
                            </div>
                            <a href="rentbooks.php" class="btn-rent-quick">Rent Now</a>
                        </div>
                    </div>
                    <div class="book-carousel-item book-card explore-book-item" data-category="fiction">
                        <div class="book-card-wrapper">
                            <div class="book-author-tag">Harper Lee</div>
                            <a href="rentbooks.php" class="book-img-container text-decoration-none">
                                <img src="images/boooks/harry_potter_1.jpg" alt="To Kill a Mockingbird">
                            </a>
                            <div class="book-card-title"><a href="rentbooks.php">To Kill a Mockingbird</a></div>
                            <div class="book-pricing-row">
                                <span class="rent-pill">₱60/wk</span>
                                <span class="buy-text">₱350</span>
                            </div>
                            <a href="rentbooks.php" class="btn-rent-quick">Rent Now</a>
                        </div>
                    </div>
                    <div class="book-carousel-item book-card explore-book-item" data-category="fiction">
                        <div class="book-card-wrapper">
                            <div class="book-author-tag">Jane Austen</div>
                            <a href="rentbooks.php" class="book-img-container text-decoration-none">
                                <img src="images/boooks/divergent.jpg" alt="Pride and Prejudice">
                            </a>
                            <div class="book-card-title"><a href="rentbooks.php">Pride and Prejudice</a></div>
                            <div class="book-pricing-row">
                                <span class="rent-pill">₱65/wk</span>
                                <span class="buy-text">₱360</span>
                            </div>
                            <a href="rentbooks.php" class="btn-rent-quick">Rent Now</a>
                        </div>
                    </div>
                    <div class="book-carousel-item book-card explore-book-item" data-category="sci-fi">
                        <div class="book-card-wrapper">
                            <div class="book-author-tag">Margaret Atwood</div>
                            <a href="rentbooks.php" class="book-img-container text-decoration-none">
                                <img src="images/boooks/abnkkbsnplako.jpg" alt="The Handmaid's Tale">
                            </a>
                            <div class="book-card-title"><a href="rentbooks.php">The Handmaid's Tale</a></div>
                            <div class="book-pricing-row">
                                <span class="rent-pill">₱70/wk</span>
                                <span class="buy-text">₱380</span>
                            </div>
                            <a href="rentbooks.php" class="btn-rent-quick">Rent Now</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" class="carousel-nav-btn next-btn" onclick="scrollCarousel('exploreCarousel', 1)" aria-label="Next">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        </div>
    </div>
    
    <!-- Libraries Section -->
    <div class="container libraries-section mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="section-title fw-bold m-0 text-dark">Discover Libraries in Davao</h4>
            <a href="libraries.php" class="see-more">
                Explore All Libraries <i class="fa-solid fa-chevron-right" style="font-size: 11px;"></i>
            </a>
        </div>
        
        <form action="libraries.php" method="GET" class="search-bar mb-4">
            <div class="input-group">
                <input type="text" name="search" class="form-control rounded-start-pill ps-4 py-2" placeholder="Search libraries in Davao...">
                <button class="btn btn-primary rounded-end-pill px-4" type="submit">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Search
                </button>
            </div>
        </form>
        
        <div class="mb-3 fw-semibold text-muted" style="font-size: 14px;">Suggested for you</div>
        
        <div class="row g-4">
            <!-- Davao City Public Library -->
            <div class="col-lg-4 col-md-6">
                <div class="library-card h-100 shadow-sm border rounded-3 overflow-hidden bg-white p-3 d-flex flex-column">
                    <div class="d-flex align-items-center mb-3">
                        <img src="images/Davao library.jpg" class="rounded-3 me-3" style="width: 85px; height: 85px; object-fit: cover;" alt="Davao City Public Library" onerror="this.src='https://placehold.co/85x85?text=Davao+Library'">
                        <div>
                            <h6 class="fw-bold mb-1 text-dark">
                                <a href="libraries.php" class="text-dark text-decoration-none">Davao City Public Library</a>
                            </h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2" style="font-size: 11px;">Public</span>
                            <div class="d-flex align-items-center mt-1">
                                <span class="fw-bold text-dark me-1" style="font-size: 13px;">4.5</span>
                                <span class="text-warning" style="font-size: 12px;">★★★★★</span>
                            </div>
                        </div>
                    </div>
                    <div class="library-location text-muted mb-2" style="font-size: 13px;">
                        <i class="fa-solid fa-location-dot text-danger me-1"></i> CM Recto St, Poblacion District, Davao City
                    </div>
                    <div class="library-status mb-3 mt-auto" style="font-size: 12px;">
                        <span class="status-indicator status-open me-1"></span>
                        <span class="text-success fw-medium">Open</span> • 8:00 AM - 5:00 PM (Mon-Sat)
                    </div>
                    <div class="d-flex gap-2">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=7.0730,125.6128" target="_blank" class="btn btn-sm btn-outline-primary flex-grow-1 rounded-pill" style="font-size: 12px;">
                            <i class="fa-solid fa-diamond-turn-right me-1"></i> Directions
                        </a>
                        <a href="libraries.php" class="btn btn-sm btn-primary flex-grow-1 rounded-pill" style="font-size: 12px;">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Ateneo de Davao University Library -->
            <div class="col-lg-4 col-md-6">
                <div class="library-card h-100 shadow-sm border rounded-3 overflow-hidden bg-white p-3 d-flex flex-column">
                    <div class="d-flex align-items-center mb-3">
                        <img src="images/ADDU.jpg" class="rounded-3 me-3" style="width: 85px; height: 85px; object-fit: cover;" alt="Ateneo de Davao Library" onerror="this.src='https://placehold.co/85x85?text=ADDU+Library'">
                        <div>
                            <h6 class="fw-bold mb-1 text-dark">
                                <a href="libraries.php" class="text-dark text-decoration-none">Ateneo de Davao Library</a>
                            </h6>
                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2" style="font-size: 11px;">Academic</span>
                            <div class="d-flex align-items-center mt-1">
                                <span class="fw-bold text-dark me-1" style="font-size: 13px;">4.8</span>
                                <span class="text-warning" style="font-size: 12px;">★★★★★</span>
                            </div>
                        </div>
                    </div>
                    <div class="library-location text-muted mb-2" style="font-size: 13px;">
                        <i class="fa-solid fa-location-dot text-danger me-1"></i> E. Jacinto St, Poblacion District, Davao City
                    </div>
                    <div class="library-status mb-3 mt-auto" style="font-size: 12px;">
                        <span class="status-indicator status-open me-1"></span>
                        <span class="text-success fw-medium">Open</span> • 8:00 AM - 8:00 PM (Mon-Fri)
                    </div>
                    <div class="d-flex gap-2">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=7.0738,125.6080" target="_blank" class="btn btn-sm btn-outline-primary flex-grow-1 rounded-pill" style="font-size: 12px;">
                            <i class="fa-solid fa-diamond-turn-right me-1"></i> Directions
                        </a>
                        <a href="libraries.php" class="btn btn-sm btn-primary flex-grow-1 rounded-pill" style="font-size: 12px;">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- UP Mindanao Library -->
            <div class="col-lg-4 col-md-6">
                <div class="library-card h-100 shadow-sm border rounded-3 overflow-hidden bg-white p-3 d-flex flex-column">
                    <div class="d-flex align-items-center mb-3">
                        <img src="Images/up.jpg" class="rounded-3 me-3" style="width: 85px; height: 85px; object-fit: cover;" alt="UP Mindanao Library" onerror="this.src='https://placehold.co/85x85?text=UP+Mindanao'">
                        <div>
                            <h6 class="fw-bold mb-1 text-dark">
                                <a href="libraries.php" class="text-dark text-decoration-none">UP Mindanao Library</a>
                            </h6>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2" style="font-size: 11px;">State University</span>
                            <div class="d-flex align-items-center mt-1">
                                <span class="fw-bold text-dark me-1" style="font-size: 13px;">4.0</span>
                                <span class="text-warning" style="font-size: 12px;">★★★★☆</span>
                            </div>
                        </div>
                    </div>
                    <div class="library-location text-muted mb-2" style="font-size: 13px;">
                        <i class="fa-solid fa-location-dot text-danger me-1"></i> Mintal, Tugbok District, Davao City
                    </div>
                    <div class="library-status mb-3 mt-auto" style="font-size: 12px;">
                        <span class="status-indicator status-open me-1"></span>
                        <span class="text-success fw-medium">Open</span> • 8:00 AM - 6:00 PM (Mon-Fri)
                    </div>
                    <div class="d-flex gap-2">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=7.0544,125.5075" target="_blank" class="btn btn-sm btn-outline-primary flex-grow-1 rounded-pill" style="font-size: 12px;">
                            <i class="fa-solid fa-diamond-turn-right me-1"></i> Directions
                        </a>
                        <a href="libraries.php" class="btn btn-sm btn-primary flex-grow-1 rounded-pill" style="font-size: 12px;">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- BookWagon Footer Component -->
    <?php include("include/footer.php"); ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.category-tab');
            const items = document.querySelectorAll('.explore-book-item');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = (this.getAttribute('data-filter') || 'all').toLowerCase().trim();
                    
                    items.forEach(item => {
                        const cat = (item.getAttribute('data-category') || '').toLowerCase();
                        if (filter === 'all' || cat.includes(filter) || filter.includes(cat)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    // Reset scroll to left on category change
                    const expTrack = document.querySelector('#exploreCarousel .book-carousel-track');
                    if (expTrack) expTrack.scrollTo({ left: 0, behavior: 'smooth' });
                });
            });

            // Hero Carousel Indicator Sync
            const heroCarouselEl = document.getElementById('heroCarousel');
            if (heroCarouselEl) {
                heroCarouselEl.addEventListener('slide.bs.carousel', function(e) {
                    const indicators = document.querySelectorAll('.hero-indicator-btn');
                    indicators.forEach((ind, idx) => {
                        if (idx === e.to) {
                            ind.classList.add('active');
                        } else {
                            ind.classList.remove('active');
                        }
                    });
                });
            }
        });

        // 1-by-1 Book Carousel scroll handler
        function scrollCarousel(containerId, direction) {
            const container = document.getElementById(containerId);
            if (!container) return;
            const track = container.querySelector('.book-carousel-track');
            if (!track) return;
            
            // Find the width of a single book item + gap
            const firstItem = track.querySelector('.book-carousel-item:not([style*="display: none"])');
            const itemWidth = firstItem ? firstItem.offsetWidth : 220;
            const gap = 16; // CSS gap
            const scrollDistance = (itemWidth + gap) * direction;
            
            track.scrollBy({
                left: scrollDistance,
                behavior: 'smooth'
            });
        }
    </script>
</body>
</html>