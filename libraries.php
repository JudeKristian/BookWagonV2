<?php
include("session.php");
include("connect.php");


$userType = $_SESSION['usertype'] ?? ''; // Change to lowercase 'usertype'
$firstName = $_SESSION['firstname'] ?? ''; // Change to lowercase 'firstname'
$lastName = $_SESSION['lastname'] ?? ''; // Change to lowercase 'lastname'
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
    <title>BookWagon - Discover Libraries in Davao</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/tab.css">
    
    <!-- Custom CSS -->
    <style>
<style>
    /* General Styles */
    body {
        font-family: 'Nunito', sans-serif;
        color: #333;
        background-color: #ffffff;
    }
    
    h1, h2, h3, h4, h5, h6 {
        font-weight: 700;
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
    /* Tab Menu Styles */
    
    /* Libraries Header */
    .libraries-header {
        position: relative;
        padding: 40px 0;
        margin-bottom: 40px;
        text-align: center;
    }
    
    .libraries-header h1 {
        color: #2d3748;
        margin-bottom: 15px;
        font-size: 2.5rem;
    }
    
    .libraries-header p {
        color: #718096;
        max-width: 700px;
        margin: 0 auto;
    }
    
    /* Library Map Container */
    .library-map-container {
        position: relative;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .map-wrapper {
        height: 450px;
    }
    
    .map-overlay {
        position: absolute;
        top: 20px;
        left: 20px;
        background-color: rgba(255, 255, 255, 0.9);
        padding: 20px;
        border-radius: 12px;
        max-width: 300px;
        z-index: 10;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .map-overlay h3 {
        margin-bottom: 10px;
        color: #2d3748;
        font-size: 1.25rem;
    }
    
    .map-overlay p {
        color: #718096;
        margin-bottom: 15px;
        font-size: 0.95rem;
    }
    
    /* Library Cards */
    .library-card {
        background-color: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        height: 100%;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .library-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    
    .library-image {
        position: relative;
        height: 200px;
        overflow: hidden;
    }
    
    .library-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    
    .library-card:hover .library-image img {
        transform: scale(1.1);
    }
    
    .library-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background-color: #6366f1;
        color: white;
        padding: 5px 15px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        z-index: 1;
    }
    
    .library-content {
        padding: 25px;
    }
    
    .library-content h3 {
        margin-bottom: 10px;
        color: #2d3748;
        font-size: 1.4rem;
    }
    
    .library-rating {
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }
    
    .stars {
        color: #f59e0b;
        margin-right: 10px;
    }
    
    .rating-score {
        font-weight: 600;
        color: #4b5563;
    }
    
    .library-details {
        margin-bottom: 15px;
    }
    
    .library-details p {
        margin-bottom: 8px;
        color: #4b5563;
    }
    
    .library-details i {
        width: 20px;
        color: #6366f1;
        margin-right: 8px;
    }
    
    .library-description {
        color: #6b7280;
        margin-bottom: 20px;
        line-height: 1.6;
    }
    
    .library-features {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .feature-badge {
        background-color: #f3f4f6;
        color: #4b5563;
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .feature-badge i {
        color: #6366f1;
        margin-right: 5px;
    }
    
    .library-actions {
        display: flex;
        gap: 10px;
    }
    
    /* Button Styles */
    .btn-primary {
        background-color: #6366f1;
        border-color: #6366f1;
        font-weight: 600;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        background-color: #4f46e5;
        border-color: #4f46e5;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
    }
    
    .btn-outline-primary {
        color: #6366f1;
        border-color: #6366f1;
        font-weight: 600;
        padding: 8px 20px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-outline-primary:hover {
        background-color: #6366f1;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
    }
    
    /* Events Section */
    .library-events-section {
        padding: 40px 0;
        background-color: #fcfcfc;
        border-radius: 16px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    
    .section-title {
        text-align: center;
        margin-bottom: 30px;
        color: #2d3748;
        position: relative;
        padding-bottom: 15px;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 50px;
        height: 4px;
        background-color: #6366f1;
        border-radius: 2px;
    }
    
    .event-card {
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        overflow: hidden;
        display: flex;
        height: 100%;
    }
    
    .event-date {
        background-color: #6366f1;
        color: white;
        padding: 15px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 80px;
    }
    
    .event-date .day {
        font-size: 1.8rem;
        font-weight: 700;
        line-height: 1;
    }
    
    .event-date .month {
        font-size: 0.9rem;
        text-transform: uppercase;
    }
    
    .event-content {
        padding: 15px;
        flex: 1;
    }
    
    .event-content h4 {
        margin-bottom: 8px;
        color: #2d3748;
        font-size: 1.1rem;
    }
    
    .event-location, .event-time {
        color: #6b7280;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }
    
    .event-location i, .event-time i {
        color: #6366f1;
        margin-right: 5px;
    }
    
    .event-desc {
        color: #4b5563;
        margin-bottom: 15px;
        font-size: 0.9rem;
    }
    
    /* Membership Section */
    .library-membership-section {
        background-color: white;
        border-radius: 16px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        padding: 30px;
    }
    
    .membership-content h2 {
        color: #2d3748;
        margin-bottom: 15px;
    }
    
    .benefits-list {
        list-style: none;
        padding: 0;
        margin: 20px 0;
    }
    
    .benefits-list li {
        margin-bottom: 10px;
        display: flex;
        align-items: center;
    }
    
    .benefits-list i {
        margin-right: 10px;
        font-size: 1.1rem;
    }
    
    .membership-actions {
        margin-top: 25px;
    }
    
    /* Community Reviews */
    .community-reviews-section {
        background-color: #f3f4f6;
        padding: 60px 0;
    }
    
    .review-card {
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        padding: 20px;
        height: 100%;
    }
    
    .review-header {
        display: flex;
        flex-direction: column;
        margin-bottom: 15px;
    }
    
    .reviewer-info {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .reviewer-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 15px;
        border: 3px solid #f0f2f5;
    }
    
    .review-stars {
        color: #f59e0b;
        font-size: 0.9rem;
    }
    
    .review-library {
        font-size: 0.9rem;
        color: #6366f1;
        font-weight: 600;
    }
    
    .review-content {
        color: #4b5563;
        font-style: italic;
        margin-bottom: 15px;
        line-height: 1.6;
    }
    
    .review-date {
        color: #9ca3af;
        font-size: 0.85rem;
    }
    
    /* FAQ Section */
    .accordion-item {
        border: none;
        margin-bottom: 15px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .accordion-button {
        background-color: white;
        color: #2d3748;
        font-weight: 600;
        padding: 15px 20px;
    }
    
    .accordion-button:not(.collapsed) {
        background-color: #6366f1;
        color: white;
    }
    
    .accordion-button:focus {
        box-shadow: none;
        border-color: transparent;
    }
    
    .accordion-button::after {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%236366f1'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
    }
    
    .accordion-button:not(.collapsed)::after {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
    }
    
    .accordion-body {
        padding: 20px;
        background-color: white;
        color: #4b5563;
        line-height: 1.6;
    }
    
    /* Newsletter Section */
    .newsletter-section {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    }
    
    .newsletter-form .form-control {
        border: none;
        border-radius: 50px 0 0 50px;
        padding-left: 25px;
    }
    
    .newsletter-form .btn {
        border-radius: 0 50px 50px 0;
        padding: 10px 25px;
        font-weight: 600;
        color: #6366f1;
    }
    
    /* Footer */
    .footer {
        background-color: #1f2937;
        color: #f9fafb;
    }
    
    .footer h5 {
        margin-bottom: 20px;
        font-weight: 600;
        color: white;
    }
    
    .footer-links a {
        color: #d1d5db;
        text-decoration: none;
        display: block;
        margin-bottom: 10px;
        transition: color 0.3s ease;
    }
    
    .footer-links a:hover {
        color: #ffffff;
    }
    
    .social-icons a {
        color: #d1d5db;
        font-size: 1.2rem;
        transition: color 0.3s ease;
    }
    
    .social-icons a:hover {
        color: #ffffff;
    }
    
    .list-inline-item:not(:last-child) {
        margin-right: 15px;
    }
    
    .list-inline-item a {
        color: #d1d5db;
        text-decoration: none;
        transition: color 0.3s ease;
    }
    
    .list-inline-item a:hover {
        color: #ffffff;
    }
    
    address p {
        margin-bottom: 10px;
        color: #d1d5db;
    }
    
    /* Media Queries */
    @media (max-width: 991.98px) {
        .tab-menu {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .tab-menu a {
            margin-bottom: 10px;
        }
        
        .library-card {
            margin-bottom: 30px;
        }
    }
    
    @media (max-width: 767.98px) {
        .libraries-header h1 {
            font-size: 2rem;
        }
        
        .map-overlay {
            position: relative;
            top: auto;
            left: auto;
            max-width: 100%;
            margin-bottom: 20px;
        }
        
        .library-actions {
            flex-direction: column;
        }
        
        .library-actions .btn {
            width: 100%;
            margin-bottom: 10px;
        }
    }
    
    @media (max-width: 575.98px) {
        .event-card {
            flex-direction: column;
        }
        
        .event-date {
            padding: 10px;
            flex-direction: row;
            justify-content: center;
            gap: 5px;
            min-width: auto;
        }
        
        .event-date .day {
            font-size: 1.2rem;
        }
        
        .event-date .month {
            font-size: 0.8rem;
        }
    }
</style>
    </style>
</head>
<body>

<?php include("include/user_header.php"); ?>


<?php include('include/tab.php'); ?>


    <!-- Libraries Content -->
<div class="container mt-4">
    <div class="libraries-header">
        <h1 class="text-center">Discover Libraries in Davao</h1>
        <p class="text-center lead mb-5">Find your perfect reading spot in the heart of Mindanao</p>
    </div>

    <!-- Library Map Overview -->
    <div class="library-map-container mb-5">
        <div class="map-overlay">
            <h3>Davao City Library Network</h3>
            <p>Explore the diverse range of libraries throughout Davao City</p>
            <a href="https://www.google.com/maps/search/libraries+in+davao+city" target="_blank" class="btn btn-primary">
                <i class="fas fa-map-marked-alt me-2"></i>View All on Map
            </a>
        </div>
        <div class="map-wrapper">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m16!1m12!1m3!1d126910.48111525372!2d125.528261!3d7.073971!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!2m1!1slibraries%20in%20davao%20city!5e0!3m2!1sen!2sph!4v1651004812345!5m2!1sen!2sph" 
                width="100%" 
                height="450" 
                style="border:0;" 
                allowfullscreen="" 
                loading="lazy">
            </iframe>
        </div>
    </div>

    <!-- Libraries Section -->
    <div class="row">
        <!-- Davao City Library -->
        <div class="col-lg-6 mb-4">
            <div class="library-card">
                <div class="library-image">
                    <div class="library-badge">Public</div>
                    <img src="images/Davao library.jpg" alt="Davao City Library" class="img-fluid">
                </div>
                <div class="library-content">
                    <h3>Davao City Public Library</h3>
                    <div class="library-rating">
                        <span class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </span>
                        <span class="rating-score">4.5</span>
                    </div>
                    <div class="library-details">
                        <p><i class="fas fa-map-marker-alt"></i> CM Recto St, Davao City, 8000</p>
                        <p><i class="fas fa-phone"></i> (082) 222-0123</p>
                        <p><i class="fas fa-clock"></i> Open 8:00 AM - 5:00 PM (Mon-Sat)</p>
                    </div>
                    <p class="library-description">
                        The central public library of Davao City offering a wide collection of books, periodicals, and 
                        digital resources. Free WiFi and study areas are available.
                    </p>
                    <div class="library-features">
                        <span class="feature-badge"><i class="fas fa-wifi"></i> Free WiFi</span>
                        <span class="feature-badge"><i class="fas fa-laptop"></i> Computer Access</span>
                        <span class="feature-badge"><i class="fas fa-book-reader"></i> Reading Areas</span>
                        <span class="feature-badge"><i class="fas fa-coffee"></i> Cafe Nearby</span>
                    </div>
                    <div class="library-actions">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=7.0730,125.6128" target="_blank" class="btn btn-primary">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                        <a href="#" class="btn btn-outline-primary">
                            <i class="fas fa-info-circle me-2"></i>More Info
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ateneo de Davao Library -->
        <div class="col-lg-6 mb-4">
            <div class="library-card">
                <div class="library-image">
                    <div class="library-badge">Academic</div>
                    <img src="images/ADDU.jpg" alt="Ateneo de Davao Library" class="img-fluid">
                </div>
                <div class="library-content">
                    <h3>Ateneo de Davao University Library</h3>
                    <div class="library-rating">
                        <span class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </span>
                        <span class="rating-score">4.8</span>
                    </div>
                    <div class="library-details">
                        <p><i class="fas fa-map-marker-alt"></i> E. Jacinto St, Davao City, 8000</p>
                        <p><i class="fas fa-phone"></i> (082) 221-2411</p>
                        <p><i class="fas fa-clock"></i> Open 8:00 AM - 8:00 PM (Mon-Fri), 8:00 AM - 5:00 PM (Sat)</p>
                    </div>
                    <p class="library-description">
                        A comprehensive academic library with extensive collections in various disciplines. Features modern facilities 
                        including digital archives, multimedia rooms, and collaborative study spaces.
                    </p>
                    <div class="library-features">
                        <span class="feature-badge"><i class="fas fa-wifi"></i> Free WiFi</span>
                        <span class="feature-badge"><i class="fas fa-database"></i> Digital Archives</span>
                        <span class="feature-badge"><i class="fas fa-users"></i> Study Rooms</span>
                        <span class="feature-badge"><i class="fas fa-print"></i> Printing Services</span>
                    </div>
                    <div class="library-actions">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=7.0738,125.6080" target="_blank" class="btn btn-primary">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                        <a href="#" class="btn btn-outline-primary">
                            <i class="fas fa-info-circle me-2"></i>More Info
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- UP Mindanao Library -->
        <div class="col-lg-6 mb-4">
            <div class="library-card">
                <div class="library-image">
                    <div class="library-badge">Academic</div>
                    <img src="Images/up.jpg" alt="UP Mindanao Library" class="img-fluid">
                </div>
                <div class="library-content">
                    <h3>UP Mindanao Library</h3>
                    <div class="library-rating">
                        <span class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
                        </span>
                        <span class="rating-score">4.0</span>
                    </div>
                    <div class="library-details">
                        <p><i class="fas fa-map-marker-alt"></i> Mintal, Tugbok District, Davao City, 8022</p>
                        <p><i class="fas fa-phone"></i> (082) 293-0310</p>
                        <p><i class="fas fa-clock"></i> Open 8:00 AM - 6:00 PM (Mon-Fri)</p>
                    </div>
                    <p class="library-description">
                        The University of the Philippines Mindanao's library houses specialized collections focusing on 
                        Mindanao studies, agriculture, and technology. Provides a quiet environment for serious research.
                    </p>
                    <div class="library-features">
                        <span class="feature-badge"><i class="fas fa-wifi"></i> Free WiFi</span>
                        <span class="feature-badge"><i class="fas fa-book"></i> Special Collections</span>
                        <span class="feature-badge"><i class="fas fa-desktop"></i> Computer Lab</span>
                        <span class="feature-badge"><i class="fas fa-leaf"></i> Garden View</span>
                    </div>
                    <div class="library-actions">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=7.0544,125.5075" target="_blank" class="btn btn-primary">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                        <a href="#" class="btn btn-outline-primary">
                            <i class="fas fa-info-circle me-2"></i>More Info
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- USEP Library -->
        <div class="col-lg-6 mb-4">
            <div class="library-card">
                <div class="library-image">
                    <div class="library-badge">Academic</div>
                    <img src="Images/usep.jpg" alt="USEP Library" class="img-fluid">
                </div>
                <div class="library-content">
                    <h3>University of Southeastern Philippines Library</h3>
                    <div class="library-rating">
                        <span class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
                        </span>
                        <span class="rating-score">4.2</span>
                    </div>
                    <div class="library-details">
                        <p><i class="fas fa-map-marker-alt"></i> Bo. Obrero, Davao City, 8000</p>
                        <p><i class="fas fa-phone"></i> (082) 227-8192</p>
                        <p><i class="fas fa-clock"></i> Open 8:00 AM - 7:00 PM (Mon-Fri), 8:00 AM - 5:00 PM (Sat)</p>
                    </div>
                    <p class="library-description">
                        A modern university library serving students and faculty with resources in science, technology, 
                        education, and the arts. Features air-conditioned study areas and updated digital resources.
                    </p>
                    <div class="library-features">
                        <span class="feature-badge"><i class="fas fa-wifi"></i> Free WiFi</span>
                        <span class="feature-badge"><i class="fas fa-air-conditioner"></i> Air Conditioned</span>
                        <span class="feature-badge"><i class="fas fa-graduation-cap"></i> Thesis Archive</span>
                        <span class="feature-badge"><i class="fas fa-question-circle"></i> Research Assistance</span>
                    </div>
                    <div class="library-actions">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=7.0807,125.6131" target="_blank" class="btn btn-primary">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                        <a href="#" class="btn btn-outline-primary">
                            <i class="fas fa-info-circle me-2"></i>More Info
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Brokenshire Library -->
        <div class="col-lg-6 mb-4">
            <div class="library-card">
                <div class="library-image">
                    <div class="library-badge">Academic</div>
                    <img src="Images/brokenshire.jpg" alt="Brokenshire College Library" class="img-fluid">
                </div>
                <div class="library-content">
                    <h3>Brokenshire College Library</h3>
                    <div class="library-rating">
                        <span class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <i class="far fa-star"></i>
                        </span>
                        <span class="rating-score">3.6</span>
                    </div>
                    <div class="library-details">
                        <p><i class="fas fa-map-marker-alt"></i> Madapo Hills, Davao City, 8000</p>
                        <p><i class="fas fa-phone"></i> (082) 221-6758</p>
                        <p><i class="fas fa-clock"></i> Open 8:00 AM - 5:00 PM (Mon-Fri)</p>
                    </div>
                    <p class="library-description">
                        Specializing in medical and health science literature, this library serves nursing students and 
                        medical professionals. Also houses a general collection for other academic disciplines.
                    </p>
                    <div class="library-features">
                        <span class="feature-badge"><i class="fas fa-wifi"></i> Limited WiFi</span>
                        <span class="feature-badge"><i class="fas fa-heartbeat"></i> Medical Collection</span>
                        <span class="feature-badge"><i class="fas fa-book-reader"></i> Reading Areas</span>
                        <span class="feature-badge"><i class="fas fa-cross"></i> Christian Literature</span>
                    </div>
                    <div class="library-actions">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=7.0862,125.6199" target="_blank" class="btn btn-primary">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                        <a href="#" class="btn btn-outline-primary">
                            <i class="fas fa-info-circle me-2"></i>More Info
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- NCCC Mall Library Hub -->
        <div class="col-lg-6 mb-4">
            <div class="library-card">
                <div class="library-image">
                    <div class="library-badge">Modern</div>
                    <img src="Images/NCC.jpg" alt="NCCC Mall Library Hub" class="img-fluid">
                </div>
                <div class="library-content">
                    <h3>NCCC Mall Library Hub</h3>
                    <div class="library-rating">
                        <span class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </span>
                        <span class="rating-score">4.7</span>
                    </div>
                    <div class="library-details">
                        <p><i class="fas fa-map-marker-alt"></i> NCCC Mall, Ma-a, Davao City, 8000</p>
                        <p><i class="fas fa-phone"></i> (082) 295-3339</p>
                        <p><i class="fas fa-clock"></i> Open 10:00 AM - 8:00 PM (Daily)</p>
                    </div>
                    <p class="library-description">
                        A modern library space inside a shopping mall, offering comfortable seating, 
                        contemporary books, and integrated digital resources. Popular for students and young professionals.
                    </p>
                    <div class="library-features">
                        <span class="feature-badge"><i class="fas fa-wifi"></i> High-Speed WiFi</span>
                        <span class="feature-badge"><i class="fas fa-coffee"></i> Coffee Shop</span>
                        <span class="feature-badge"><i class="fas fa-plug"></i> Power Outlets</span>
                        <span class="feature-badge"><i class="fas fa-shopping-bag"></i> Shopping Nearby</span>
                    </div>
                    <div class="library-actions">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=7.0944,125.6139" target="_blank" class="btn btn-primary">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                        <a href="#" class="btn btn-outline-primary">
                            <i class="fas fa-info-circle me-2"></i>More Info
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Library Events Section -->
    <div class="library-events-section my-5">
        <h2 class="section-title">Upcoming Library Events in Davao</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="event-card">
                    <div class="event-date">
                        <span class="day">25</span>
                        <span class="month">May</span>
                    </div>
                    <div class="event-content">
                        <h4>Book Lovers' Meet-up</h4>
                        <p class="event-location"><i class="fas fa-map-marker-alt"></i> Davao City Public Library</p>
                        <p class="event-time"><i class="fas fa-clock"></i> 2:00 PM - 4:00 PM</p>
                        <p class="event-desc">Join fellow book enthusiasts for discussions, recommendations, and light refreshments.</p>
                        <a href="#" class="btn btn-sm btn-outline-primary">Learn More</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="event-card">
                    <div class="event-date">
                        <span class="day">30</span>
                        <span class="month">May</span>
                    </div>
                    <div class="event-content">
                        <h4>Children's Story Time</h4>
                        <p class="event-location"><i class="fas fa-map-marker-alt"></i> NCCC Mall Library Hub</p>
                        <p class="event-time"><i class="fas fa-clock"></i> 10:00 AM - 11:30 AM</p>
                        <p class="event-desc">Interactive storytelling session for kids ages 3-8. Registration required.</p>
                        <a href="#" class="btn btn-sm btn-outline-primary">Sign Up</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="event-card">
                    <div class="event-date">
                        <span class="day">05</span>
                        <span class="month">Jun</span>
                    </div>
                    <div class="event-content">
                        <h4>Digital Research Workshop</h4>
                        <p class="event-location"><i class="fas fa-map-marker-alt"></i> UP Mindanao Library</p>
                        <p class="event-time"><i class="fas fa-clock"></i> 1:00 PM - 5:00 PM</p>
                        <p class="event-desc">Learn advanced digital research techniques and database navigation. Free admission.</p>
                        <a href="#" class="btn btn-sm btn-outline-primary">Register</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Library Membership Section -->
    <div class="library-membership-section py-5 mb-5">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <img src="images/card.png"alt="Library Membership" class="img-fluid rounded-3 shadow-lg">
            </div>
            <div class="col-lg-6">
                <div class="membership-content">
                    <h2>Get Your Library Card Today</h2>
                    <p class="lead">Access thousands of books, digital resources, and exclusive events with a Davao Library Network membership.</p>
                    <ul class="benefits-list">
                        <li><i class="fas fa-check-circle text-success"></i> Borrow up to 10 books at a time</li>
                        <li><i class="fas fa-check-circle text-success"></i> Access online databases and e-books</li>
                        <li><i class="fas fa-check-circle text-success"></i> Use computer facilities for free</li>
                        <li><i class="fas fa-check-circle text-success"></i> Join members-only workshops and events</li>
                        <li><i class="fas fa-check-circle text-success"></i> Reserve books and study rooms online</li>
                    </ul>
                    <div class="membership-actions">
                        <a href="#" class="btn btn-primary">Apply for Membership</a>
                        <a href="#" class="btn btn-outline-primary ms-2">Learn More</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Community Reviews Section -->
<div class="community-reviews-section py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5">What Our Community Says</h2>
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <img src="img/avatars/user1.jpg" alt="User Avatar" class="reviewer-avatar">
                            <div>
                                <h5>Maria Santiago</h5>
                                <div class="review-stars">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                        </div>
                        <div class="review-library">Davao City Public Library</div>
                    </div>
                    <div class="review-content">
                        <p>"I love spending my weekends at the Davao City Public Library. The atmosphere is peaceful, and the staff is always helpful. Their new collection of local literature is impressive!"</p>
                    </div>
                    <div class="review-date">Posted 2 weeks ago</div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <img src="img/avatars/user2.jpg" alt="User Avatar" class="reviewer-avatar">
                            <div>
                                <h5>Juan Dela Cruz</h5>
                                <div class="review-stars">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="far fa-star"></i>
                                </div>
                            </div>
                        </div>
                        <div class="review-library">NCCC Mall Library Hub</div>
                    </div>
                    <div class="review-content">
                        <p>"The NCCC Mall Library Hub is incredibly convenient. I can study for hours in their comfortable chairs, and the coffee shop next door is perfect for breaks. WiFi can be spotty during peak hours though."</p>
                    </div>
                    <div class="review-date">Posted 1 month ago</div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <img src="img/avatars/user3.jpg" alt="User Avatar" class="reviewer-avatar">
                            <div>
                                <h5>Paolo Mendoza</h5>
                                <div class="review-stars">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star-half-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="review-library">Ateneo de Davao Library</div>
                    </div>
                    <div class="review-content">
                        <p>"As a researcher, I find the Ateneo Library's collection exceptional. Their digital archives saved me countless hours. The librarians are knowledgeable and always willing to assist with complex searches."</p>
                    </div>
                    <div class="review-date">Posted 3 weeks ago</div>
                </div>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="#" class="btn btn-outline-primary">Read More Reviews</a>
        </div>
    </div>
</div>

<!-- FAQ Section -->
<div class="container py-5">
    <h2 class="text-center mb-5">Frequently Asked Questions</h2>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="accordion" id="libraryFAQ">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingOne">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                            How do I get a library card in Davao City?
                        </button>
                    </h2>
                    <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#libraryFAQ">
                        <div class="accordion-body">
                            To get a library card at the Davao City Public Library, bring a valid ID, proof of address, and a completed application form. For academic libraries, you'll need to show your student or faculty ID. Most libraries issue cards on the same day, and they're typically valid for 1-2 years before renewal.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingTwo">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                            Can non-students use university libraries in Davao?
                        </button>
                    </h2>
                    <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#libraryFAQ">
                        <div class="accordion-body">
                            Yes, most university libraries in Davao offer visitor or guest passes. At Ateneo de Davao and UP Mindanao libraries, visitors can access the facilities for reference purposes but may not be able to borrow books. Some libraries charge a small visitor fee, while others require a letter of introduction or valid ID.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingThree">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                            Are there any digital libraries accessible to Davao residents?
                        </button>
                    </h2>
                    <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#libraryFAQ">
                        <div class="accordion-body">
                            Yes, several digital library resources are available. The Davao City Public Library offers access to eLibrary Philippines. University libraries provide access to EBSCO, JSTOR, and other academic databases. Some libraries also offer temporary digital access cards for residents who cannot visit physically.
                            </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingFour">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                            What study facilities are available at Davao libraries?
                        </button>
                    </h2>
                    <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#libraryFAQ">
                        <div class="accordion-body">
                            Most Davao libraries offer dedicated study areas with tables and chairs. Academic libraries like Ateneo and UP Mindanao provide private study carrels and group study rooms that can be reserved in advance. The NCCC Mall Library Hub features modern co-working spaces with power outlets. Many libraries now offer WiFi, though connection speeds and availability may vary.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingFive">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                            How long can I borrow books from Davao libraries?
                        </button>
                    </h2>
                    <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#libraryFAQ">
                        <div class="accordion-body">
                            Loan periods vary by library. At the Davao City Public Library, regular books can be borrowed for 2 weeks with one renewal. Academic libraries typically allow students to borrow for 1-2 weeks, while faculty members may have extended borrowing privileges of up to a month. Reference materials and rare collections are usually restricted to in-library use only.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php include("include/footer.php"); ?>

<script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    
    // Auto-hide toast notifications after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var toasts = document.querySelectorAll('.toast');
            toasts.forEach(function(toast) {
                var bsToast = bootstrap.Toast.getInstance(toast);
                if (bsToast) {
                    bsToast.hide();
                }
            });
        }, 5000);
    });
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>