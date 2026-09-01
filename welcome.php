<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Get user info
$firstName = $_SESSION['firstname'] ?? '';
$lastName = $_SESSION['lastname'] ?? '';
$email = $_SESSION['email'] ?? '';
$userType = $_SESSION['usertype'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to BookWagon!</title>
    <?php if(isset($_SESSION['login_count'])): ?>
    <meta name="login-count" content="<?php echo $_SESSION['login_count']; ?>">
    <?php endif; ?>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #d9b99b;
            --primary-light: #ffd0b1;
            --primary-dark: #c4a689;
            --secondary-color: #f8fafc;
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
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        html, body {
            height: 100%;
            overflow-x: hidden;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-dark);
            background: linear-gradient(-135deg, #e9f0ff 0%, #f7f1e9 100%);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        /* Particles background */
        #particles-js {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
            pointer-events: none;
        }
        
        .welcome-page {
            min-height: 100vh;
            padding: 0;
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
        }
        
        .welcome-header {
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
            padding: 60px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
        }
        
        .welcome-title {
            font-size: 3.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
            opacity: 0;
            transform: translateY(-20px);
            animation: fadeInDown 1s ease forwards 0.2s;
        }
        
        .welcome-subtitle {
            font-size: 1.4rem;
            color: white;
            opacity: 0.9;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transform: translateY(-10px);
            animation: fadeInDown 1s ease forwards 0.4s;
        }
        
        .welcome-content {
            flex: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            z-index: 2;
        }
        
        .welcome-message {
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 40px;
            color: var(--text-light);
            text-align: center;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            opacity: 0;
            animation: fadeIn 1s ease forwards 0.6s;
        }
        
        /* Character showcase styles */
        .character-showcase {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin: 40px 0;
            opacity: 0;
            animation: fadeIn 1s ease forwards 0.8s;
        }
        
        .character-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 220px;
            text-align: center;
            background-color: white;
            border-radius: 20px;
            padding: 30px 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.07);
            transition: all 0.4s ease;
            transform: translateY(20px);
            opacity: 0;
            animation: fadeInUp 0.7s ease forwards;
            animation-delay: calc(0.9s + var(--delay) * 0.2s);
        }
        
        .character-card:nth-child(1) { --delay: 1; }
        .character-card:nth-child(2) { --delay: 2; }
        .character-card:nth-child(3) { --delay: 3; }
        .character-card:nth-child(4) { --delay: 4; }
        .character-card:nth-child(5) { --delay: 5; }
        
        .character-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
        }
        
        .character-img {
            width: 130px;
            height: 130px;
            object-fit: contain;
            margin-bottom: 20px;
            filter: drop-shadow(0 8px 10px rgba(0, 0, 0, 0.15));
            transition: transform 0.5s ease;
        }
        
        .character-card:hover .character-img {
            transform: scale(1.1) translateY(-5px);
        }
        
        .character-title {
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-dark);
            font-size: 1.25rem;
        }
        
        .character-desc {
            color: var(--text-light);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        /* Animation for characters */
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        .bounce-animation {
            animation: bounce 5s ease infinite;
        }
        
        /* Delay for each character */
        .bounce-delay-1 { animation-delay: 0s; }
        .bounce-delay-2 { animation-delay: 0.7s; }
        .bounce-delay-3 { animation-delay: 1.4s; }
        .bounce-delay-4 { animation-delay: 2.1s; }
        .bounce-delay-5 { animation-delay: 2.8s; }
        
        /* Book background elements */
        .floating-books {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 1;
        }
        
        .floating-book {
            position: absolute;
            color: rgba(255, 255, 255, 0.25);
            font-size: 2.5rem;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.08));
        }
        
        @keyframes floatIcon {
            0% {
                transform: translateY(0) rotate(0) scale(1);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-80vh) rotate(360deg) scale(0.5);
                opacity: 0;
            }
        }
        
        .cta-container {
            text-align: center;
            margin-top: 50px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 1s ease forwards 2s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 700;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 15px rgba(217, 185, 155, 0.25);
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-primary::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, var(--primary-color), var(--primary-light), var(--primary-color));
            z-index: -1;
            transition: all 0.4s ease;
            background-size: 200% auto;
            animation: gradientShift 3s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .btn-primary:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(217, 185, 155, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(-2px);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInDown {
            from { 
                opacity: 0;
                transform: translateY(-20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes float {
            0% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0); }
        }
        
        /* Book pattern background */
        .pattern-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d9b99b' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
            z-index: 0;
            pointer-events: none;
        }
        
        /* Media queries */
        @media (max-width: 1200px) {
            .welcome-title {
                font-size: 3rem;
            }
        }
        
        @media (max-width: 991px) {
            .welcome-title {
                font-size: 2.5rem;
            }
            
            .welcome-subtitle {
                font-size: 1.2rem;
            }
            
            .character-card {
                width: 200px;
                padding: 25px 15px;
            }
            
            .character-img {
                width: 110px;
                height: 110px;
            }
        }
        
        @media (max-width: 767px) {
            .welcome-header {
                padding: 40px 20px;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .welcome-subtitle {
                font-size: 1.1rem;
            }
            
            .character-card {
                width: 160px;
                padding: 20px 10px;
            }
            
            .character-img {
                width: 90px;
                height: 90px;
            }
            
            .character-title {
                font-size: 1.1rem;
            }
            
            .character-desc {
                font-size: 0.85rem;
            }
            
            .btn-primary {
                padding: 12px 30px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .character-showcase {
                gap: 15px;
            }
            
            .character-card {
                width: 130px;
                padding: 15px 10px;
            }
            
            .character-img {
                width: 80px;
                height: 80px;
                margin-bottom: 15px;
            }
            
            .character-title {
                font-size: 0.95rem;
                margin-bottom: 8px;
            }
            
            .character-desc {
                font-size: 0.75rem;
            }
            
            .welcome-message {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <!-- Particle animation background -->
    <div id="particles-js"></div>
    
    <div class="pattern-bg"></div>
    
    <div class="welcome-page">
        <div class="welcome-header">
            <h1 class="welcome-title">Welcome to BookWagon, <?php echo htmlspecialchars($firstName); ?>!</h1>
            <p class="welcome-subtitle">Your literary journey begins now</p>
            
            <div class="floating-books">
                <?php for($i = 0; $i < 15; $i++): ?>
                    <i class="<?php 
                        $icons = ['fas fa-book', 'fas fa-book-open', 'fas fa-bookmark', 'fas fa-glasses', 'fas fa-pen'];
                        echo $icons[array_rand($icons)]; 
                    ?> floating-book" style="
                        top: <?php echo rand(10, 90); ?>%; 
                        left: <?php echo rand(5, 95); ?>%;
                        font-size: <?php echo rand(15, 35); ?>px;
                        animation: floatIcon <?php echo rand(15, 25); ?>s linear infinite;
                        animation-delay: <?php echo $i * 0.5; ?>s;
                    "></i>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="welcome-content">
            <p class="welcome-message">
                Thank you for joining our community of book lovers! We're excited to help you discover, 
                rent, and share amazing books. Your account has been successfully created, and you're now 
                ready to explore all the features BookWagon has to offer.
            </p>
            
            <!-- Character Showcase Section -->
            <div class="character-showcase">
                <div class="character-card">
                    <img src="images/tour/home.png" alt="Discover Books" class="character-img bounce-animation bounce-delay-1" onerror="this.src='https://via.placeholder.com/100x100?text=Home'">
                    <h3 class="character-title">Home</h3>
                    <p class="character-desc">Discover trending books and new releases</p>
                </div>
                
                <div class="character-card">
                    <img src="images/tour/renting.png" alt="Rent Books" class="character-img bounce-animation bounce-delay-2" onerror="this.src='https://via.placeholder.com/100x100?text=Rent+Books'">
                    <h3 class="character-title">Rent Books</h3>
                    <p class="character-desc">Access books at affordable prices</p>
                </div>
                
                <div class="character-card">
                    <img src="images/tour/forum.png" alt="Join Forum" class="character-img bounce-animation bounce-delay-3" onerror="this.src='https://via.placeholder.com/100x100?text=Forum'">
                    <h3 class="character-title">Forum</h3>
                    <p class="character-desc">Discuss your favorite reads with others</p>
                </div>
                
                <div class="character-card">
                    <img src="images/tour/library.png" alt="Find Libraries" class="character-img bounce-animation bounce-delay-4" onerror="this.src='https://via.placeholder.com/100x100?text=Libraries'">
                    <h3 class="character-title">Libraries</h3>
                    <p class="character-desc">Discover libraries near your location</p>
                </div>
                
                <div class="character-card">
                    <img src="images/tour/bookswaps.png" alt="Book Swaps" class="character-img bounce-animation bounce-delay-5" onerror="this.src='https://via.placeholder.com/100x100?text=Book+Swaps'">
                    <h3 class="character-title">Book Swaps</h3>
                    <p class="character-desc">Trade books with other members</p>
                </div>
            </div>
            
            <div class="cta-container">
                <a href="home.php?new_user=1" class="btn btn-primary">Start Your Journey</a>
            </div>
        </div>
    </div>
    
    <!-- Particles JS -->
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        // Initialize particles
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof particlesJS !== 'undefined') {
                particlesJS("particles-js", {
                    "particles": {
                        "number": {
                            "value": 50,
                            "density": {
                                "enable": true,
                                "value_area": 800
                            }
                        },
                        "color": {
                            "value": "#d9b99b"
                        },
                        "shape": {
                            "type": ["circle", "triangle", "edge"],
                            "stroke": {
                                "width": 0,
                                "color": "#000000"
                            }
                        },
                        "opacity": {
                            "value": 0.2,
                            "random": true,
                            "anim": {
                                "enable": false,
                                "speed": 1,
                                "opacity_min": 0.1,
                                "sync": false
                            }
                        },
                        "size": {
                            "value": 5,
                            "random": true,
                            "anim": {
                                "enable": false,
                                "speed": 40,
                                "size_min": 0.1,
                                "sync": false
                            }
                        },
                        "line_linked": {
                            "enable": true,
                            "distance": 150,
                            "color": "#d9b99b",
                            "opacity": 0.2,
                            "width": 1
                        },
                        "move": {
                            "enable": true,
                            "speed": 2,
                            "direction": "none",
                            "random": true,
                            "straight": false,
                            "out_mode": "out",
                            "bounce": false,
                            "attract": {
                                "enable": false,
                                "rotateX": 600,
                                "rotateY": 1200
                            }
                        }
                    },
                    "interactivity": {
                        "detect_on": "canvas",
                        "events": {
                            "onhover": {
                                "enable": true,
                                "mode": "grab"
                            },
                            "onclick": {
                                "enable": false
                            },
                            "resize": true
                        },
                        "modes": {
                            "grab": {
                                "distance": 150,
                                "line_linked": {
                                    "opacity": 0.5
                                }
                            }
                        }
                    },
                    "retina_detect": true
                });
            }
        });
        
        // Automatically redirect to home after 25 seconds with new_user parameter
        setTimeout(function() {
            window.location.href = "home.php?new_user=1";
        }, 25000);
    </script>
</body>
</html> 