<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookWagon - Discover Your Next Great Read</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Font Awesome (for footer icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #f59e0b;
            --btn-dark: #805b10;
            --text-main: #1e293b;
            --text-muted: #52525b;
            --bg-color: #f7f9fa; /* Very light subtle gray/blue from reference */
            --brand-color: #8c5b2a; /* Brownish logo color */
            --shape-pink: #fca5a5;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ----- Navbar ----- */
        .navbar-custom {
            padding: 20px 0;
            background: transparent;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--brand-color) !important;
        }

        .navbar-brand i {
            font-size: 1.7rem;
        }

        .nav-link {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-muted) !important;
            margin: 0 15px;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
        }

        /* Buttons */
        .btn-login {
            font-weight: 600;
            color: var(--text-main);
            text-decoration: none;
            padding: 8px 16px;
            margin-right: 15px;
            transition: opacity 0.2s;
        }
        
        .btn-login:hover {
            opacity: 0.7;
        }

        .btn-signup {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 8px 24px;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            transition: opacity 0.2s;
        }

        .btn-signup:hover {
            opacity: 0.9;
            color: white;
            text-decoration: none;
        }

        /* ----- Hero Section ----- */
        .hero-section {
            flex: 1;
            display: flex;
            align-items: center;
            padding-top: 60px;
            padding-bottom: 60px;
        }

        /* Left Side: Image with Pink Shape */
        .image-container {
            position: relative;
            z-index: 1;
            padding-left: 20px;
        }

        .pink-shape {
            position: absolute;
            top: 20px;
            left: 0;
            width: 95%;
            height: 100%;
            background-color: var(--shape-pink);
            border-radius: 40px;
            z-index: -1;
            transform: rotate(-3deg);
        }

        .main-image {
            width: 100%;
            border-radius: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            object-fit: cover;
            aspect-ratio: 4/5; /* Keep it tall */
        }

        /* Right Side: Content */
        .content-container {
            padding-left: 40px;
        }

        /* Carousel Card */
        .featured-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }

        .featured-header {
            background-color: #2c3e50;
            color: white;
            padding: 8px 16px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .carousel-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        /* Text Content */
        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.2;
            color: var(--text-main);
            margin-bottom: 20px;
        }

        .hero-subtitle {
            font-size: 1.1rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 35px;
            max-width: 90%;
        }

        .btn-start {
            background-color: var(--btn-dark);
            color: white;
            font-weight: 600;
            padding: 14px 32px;
            border-radius: 50px;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: opacity 0.2s;
        }

        .btn-start:hover {
            opacity: 0.9;
            color: white;
        }

        @media (max-width: 991px) {
            .content-container {
                padding-left: 15px;
                margin-top: 50px;
            }
            .hero-title {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo.png" alt="BookWagon Logo" style="height: 40px;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">Start Selling</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Explore</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center mt-3 mt-lg-0">
                    <a href="login.php" class="btn-login">Login</a>
                    <a href="signup.php" class="btn-signup">Sign up</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                
                <!-- Left Side: Image -->
                <div class="col-lg-5 mb-5 mb-lg-0">
                    <div class="image-container">
                        <div class="pink-shape"></div>
                        <!-- We use the existing man-reading image from the project -->
                        <img src="images/man-reading.png" alt="Man reading a book" class="main-image bg-white">
                    </div>
                </div>
                
                <!-- Right Side: Content -->
                <div class="col-lg-7">
                    <div class="content-container">
                        
                        <!-- Featured Event Carousel -->
                        <div class="featured-card">
                            <div class="featured-header">
                                <i class="bi bi-calendar-event"></i> FEATURED EVENT
                            </div>
                            <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-inner">
                                    <div class="carousel-item active">
                                        <img src="images/1.jpg" alt="Featured Book Event">
                                    </div>
                                    <div class="carousel-item">
                                        <img src="images/2.png" alt="BookWagon Feature">
                                    </div>
                                    <div class="carousel-item">
                                        <img src="images/3.png" alt="BookWagon Promotion">
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
                            </div>
                        </div>

                        <!-- Main Copy -->
                        <h1 class="hero-title">
                            Discover Your Next<br>Great Read
                        </h1>
                        <p class="hero-subtitle">
                            Join a community of readers and collectors. Buy, sell, and explore thousands of books with Book Wagon.
                        </p>
                        <a href="signup.php" class="btn-start">
                            Get Started <i class="bi bi-arrow-right"></i>
                        </a>

                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include("include/footer.php"); ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>