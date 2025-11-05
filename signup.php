<?php
// Database configuration
$db_host = "localhost";      // Usually "localhost" for local development
$db_user = "root";           // Database username (default is "root" for XAMPP/MAMP)
$db_pass = "";               // Database password (often empty for local development)
$db_name = "bookwagon_db";   // Your database name

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$firstname = $middlename = $lastname = $email = $password = $confirm_password = "";
$firstname_err = $lastname_err = $email_err = $password_err = $confirm_password_err = $signup_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate firstname
    if (empty(trim($_POST["firstname"]))) {
        $firstname_err = "Please enter your first name.";
    } else {
        $firstname = trim($_POST["firstname"]);
    }
    
    // Middle name is optional, just get it if provided
    $middlename = trim($_POST["middlename"]);
    
    // Validate lastname
    if (empty(trim($_POST["lastname"]))) {
        $lastname_err = "Please enter your last name.";
    } else {
        $lastname = trim($_POST["lastname"]);
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        // Prepare a select statement to check if email already exists
        $sql = "SELECT id FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $email_err = "This email is already taken.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }
    
    // Check input errors before inserting into database
    if (empty($firstname_err) && empty($lastname_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (firstname, middlename, lastname, email, password, usertype) VALUES (?, ?, ?, ?, ?, ?)";
         
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssssss", $param_firstname, $param_middlename, $param_lastname, $param_email, $param_password, $param_usertype);
            
            // Set parameters
            $param_firstname = $firstname;
            $param_middlename = $middlename;
            $param_lastname = $lastname;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_usertype = "user"; // Set default user type
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Set a flag for the login page to know this is a new user
                echo "<script>
                alert('Account created successfully!');
                window.location.href = 'login.php?new_user=1';
            </script>";
            } else {
                $signup_err = "Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookWagon - Sign Up</title>
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
            --primary-color: #f8b079;
            --primary-light: #ffd0b1;
            --primary-dark: #e69c68;
            --secondary-color: #f8fafc;
            --accent-color: #6366f1; /* Indigo */
            --text-dark: #1e293b;
            --text-light: #64748b;
            --text-muted: #94a3b8;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #eef1ff 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            color: var(--text-dark);
            position: relative;
            overflow-x: hidden;
            padding: 40px 0;
        }
        
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%236366f1' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
            z-index: -1;
        }
        
        .signup-container {
            max-width: 1000px;
            margin: 0 auto;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            background: white;
            position: relative;
            z-index: 2;
        }
        
        .signup-row {
            display: flex;
            min-height: 700px;
        }
        
        .signup-image {
            flex: 0.8;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .signup-image-content {
            position: relative;
            z-index: 5;
            padding: 30px;
            color: white;
            text-align: center;
            max-width: 80%;
        }
        
        .signup-image-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .signup-image-text {
            font-size: 1.1rem;
            margin-bottom: 20px;
            line-height: 1.6;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .signup-benefits {
            margin-top: 30px;
            text-align: left;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .benefit-icon {
            margin-right: 15px;
            background-color: rgba(255, 255, 255, 0.2);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .benefit-text {
            font-size: 0.95rem;
        }
        
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
        }
        
        .floating-element {
            position: absolute;
            color: rgba(255, 255, 255, 0.15);
            font-size: 2rem;
            animation: float 6s ease-in-out infinite;
        }
        
        .element-1 {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .element-2 {
            top: 20%;
            right: 15%;
            animation-delay: 1s;
        }
        
        .element-3 {
            bottom: 15%;
            left: 15%;
            animation-delay: 2s;
        }
        
        .element-4 {
            bottom: 25%;
            right: 10%;
            animation-delay: 3s;
        }
        
        .element-5 {
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: 4s;
        }
        
        @keyframes float {
            0% { transform: translateY(0) rotate(0); }
            50% { transform: translateY(-15px) rotate(5deg); }
            100% { transform: translateY(0) rotate(0); }
        }
        
        .signup-form {
            flex: 1.2;
            padding: 40px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo img {
            height: 70px;
            transition: transform 0.3s ease;
        }
        
        .logo-text {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-left: 10px;
        }
        
        .logo:hover img {
            transform: scale(1.05);
        }
        
        h2 {
            font-weight: 700;
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 25px;
            color: var(--text-dark);
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background-color: var(--primary-color);
            border-radius: 2px;
        }
        
        .form-control {
            height: 55px;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            font-size: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(248, 176, 121, 0.2);
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
            background-image: none;
        }
        
        .invalid-feedback {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: -15px;
            margin-bottom: 15px;
        }
        
        .name-row {
            display: flex;
            gap: 15px;
            margin-bottom: 0;
        }
        
        .name-row .form-group {
            flex: 1;
        }
        
        .optional-field {
            font-size: 0.75rem;
            color: var(--text-muted);
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background-color: white;
            padding: 0 5px;
            border-radius: 10px;
            pointer-events: none;
            z-index: 1;
        }
        
        /* Add a specific style for middle name field to prevent overlap */
        .middle-name-container {
            position: relative;
        }
        
        .middle-name-container .optional-field {
            right: 15px;
            top: 15px;
            background-color: #fff;
            padding: 0 5px;
            z-index: 5;
        }
        
        .btn-signup {
            height: 55px;
            background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 10px;
            color: white;
            position: relative;
            overflow: hidden;
            z-index: 1;
            transition: all 0.3s ease;
        }
        
        .btn-signup::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, var(--primary-dark), var(--primary-color));
            z-index: -1;
            transition: opacity 0.3s ease;
            opacity: 0;
        }
        
        .btn-signup:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(248, 176, 121, 0.4);
        }
        
        .btn-signup:hover::after {
            opacity: 1;
        }
        
        .form-divider {
            text-align: center;
            position: relative;
            margin: 30px 0;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .form-divider::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            width: 42%;
            height: 1px;
            background-color: #e2e8f0;
        }
        
        .form-divider::after {
            content: "";
            position: absolute;
            right: 0;
            top: 50%;
            width: 42%;
            height: 1px;
            background-color: #e2e8f0;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.95rem;
            color: var(--text-light);
        }
        
        .text-primary {
            color: var(--primary-color) !important;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
        }
        
        .text-primary:hover {
            color: var(--primary-dark) !important;
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 12px;
            border: none;
            font-size: 0.95rem;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            margin-top: 0.25em;
            vertical-align: top;
            background-color: #fff;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            border: 1px solid #d1d5db;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            transition: all 0.3s ease;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            margin-left: 5px;
            font-size: 0.95rem;
            color: var(--text-light);
        }
        
        .form-check-label a {
            transition: all 0.3s ease;
        }
        
        .form-group {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .signup-container {
                max-width: 95%;
                margin: 0 auto;
            }

            .signup-image-content {
                padding: 25px;
                max-width: 85%;
            }

            .signup-image-title {
                font-size: 1.8rem;
            }

            .signup-image-text {
                font-size: 1rem;
            }
        }

        @media (max-width: 992px) {
            .signup-container {
                max-width: 90%;
                margin: 0 auto;
            }

            .signup-image-content {
                padding: 20px;
                max-width: 90%;
            }

            .signup-image-title {
                font-size: 1.6rem;
            }

            .signup-image-text {
                font-size: 0.95rem;
            }

            .signup-form {
                padding: 45px 35px;
            }

            .form-control {
                height: 50px;
                padding: 10px 18px;
                font-size: 0.95rem;
            }

            .btn-signup {
                height: 50px;
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            .signup-row {
                flex-direction: column;
                min-height: auto;
            }

            .signup-image {
                display: none;
            }

            .signup-form {
                padding: 40px 30px;
                flex: none;
            }

            .logo img {
                height: 60px;
            }

            .logo-text {
                font-size: 1.4rem;
            }

            h2 {
                font-size: 1.7rem;
            }

            .name-row {
                flex-direction: column;
                gap: 0;
            }

            .name-row .form-group {
                width: 100%;
            }

            .form-control {
                height: 48px;
                padding: 10px 16px;
                font-size: 0.95rem;
                margin-bottom: 18px;
            }

            .btn-signup {
                height: 48px;
                font-size: 1rem;
                margin-top: 8px;
            }

            .form-check {
                margin-bottom: 20px;
            }

            .form-check-label {
                font-size: 0.9rem;
            }

            .terms-link {
                font-size: 0.9rem;
            }

            .signin-link {
                font-size: 0.9rem;
                margin-top: 18px;
            }

            .alert {
                padding: 12px;
                font-size: 0.9rem;
                margin-bottom: 20px;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 20px 0;
            }

            .signup-container {
                max-width: 95%;
                margin: 0 auto;
                border-radius: 16px;
            }

            .signup-form {
                padding: 30px 20px;
            }

            .logo {
                margin-bottom: 20px;
            }

            .logo img {
                height: 50px;
            }

            .logo-text {
                font-size: 1.2rem;
                margin-left: 8px;
            }

            h2 {
                font-size: 1.5rem;
                margin-bottom: 20px;
            }

            .form-control {
                height: 45px;
                padding: 8px 14px;
                font-size: 0.9rem;
                margin-bottom: 16px;
                border-radius: 10px;
            }

            .btn-signup {
                height: 45px;
                font-size: 0.95rem;
                margin-top: 6px;
                border-radius: 10px;
            }

            .form-check {
                margin-bottom: 18px;
            }

            .form-check-input {
                width: 16px;
                height: 16px;
            }

            .form-check-label {
                font-size: 0.85rem;
                margin-left: 6px;
            }

            .terms-link {
                font-size: 0.85rem;
            }

            .form-divider {
                margin: 25px 0;
                font-size: 0.85rem;
            }

            .form-divider::before,
            .form-divider::after {
                width: 38%;
            }

            .signin-link {
                font-size: 0.85rem;
                margin-top: 16px;
            }

            .alert {
                padding: 10px;
                font-size: 0.85rem;
                margin-bottom: 18px;
                border-radius: 8px;
            }

            .invalid-feedback {
                font-size: 0.8rem;
                margin-top: -12px;
                margin-bottom: 12px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 15px 0;
            }

            .signup-container {
                max-width: 98%;
                margin: 0 auto;
                border-radius: 12px;
            }

            .signup-form {
                padding: 25px 16px;
            }

            .logo {
                margin-bottom: 18px;
            }

            .logo img {
                height: 45px;
            }

            .logo-text {
                font-size: 1.1rem;
                margin-left: 6px;
            }

            h2 {
                font-size: 1.4rem;
                margin-bottom: 18px;
            }

            .form-control {
                height: 42px;
                padding: 8px 12px;
                font-size: 0.85rem;
                margin-bottom: 14px;
                border-radius: 8px;
            }

            .btn-signup {
                height: 42px;
                font-size: 0.9rem;
                margin-top: 4px;
                border-radius: 8px;
            }

            .form-check {
                margin-bottom: 16px;
            }

            .form-check-input {
                width: 14px;
                height: 14px;
            }

            .form-check-label {
                font-size: 0.8rem;
                margin-left: 5px;
            }

            .terms-link {
                font-size: 0.8rem;
            }

            .form-divider {
                margin: 20px 0;
                font-size: 0.8rem;
            }

            .form-divider::before,
            .form-divider::after {
                width: 35%;
            }

            .signin-link {
                font-size: 0.8rem;
                margin-top: 14px;
            }

            .alert {
                padding: 8px;
                font-size: 0.8rem;
                margin-bottom: 16px;
                border-radius: 6px;
            }

            .invalid-feedback {
                font-size: 0.75rem;
                margin-top: -10px;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="signup-container">
            <div class="signup-row">
                <!-- Left side - Decorative illustration -->
                <div class="signup-image">
                    <div class="floating-elements">
                        <div class="floating-element element-1"><i class="fas fa-book"></i></div>
                        <div class="floating-element element-2"><i class="fas fa-book-open"></i></div>
                        <div class="floating-element element-3"><i class="fas fa-bookmark"></i></div>
                        <div class="floating-element element-4"><i class="fas fa-book-reader"></i></div>
                        <div class="floating-element element-5"><i class="fas fa-graduation-cap"></i></div>
                    </div>
                    
                    <div class="signup-image-content">
                        <div class="signup-image-title">Join BookWagon Today</div>
                        <div class="signup-image-text">Create your account and become part of the Philippines' growing community of book lovers.</div>
                        
                        <div class="signup-benefits">
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="benefit-text">Buy and sell books at great prices</div>
                            </div>
                            
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-sync-alt"></i>
                                </div>
                                <div class="benefit-text">Rent books and save money</div>
                            </div>
                            
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="benefit-text">Connect with fellow book enthusiasts</div>
                            </div>
                            
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="benefit-text">Find libraries and bookshops near you</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right side - Signup form -->
                <div class="signup-form">
                    <div class="logo">
                        <img src="images/logo.png" alt="BookWagon Logo">

                    </div>
                    
                    <div class="text-center mb-4">
                        <h2>Create an Account</h2>
                    </div>
                    
                    <?php 
                    if(!empty($signup_err)){
                        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' . $signup_err . '</div>';
                    }        
                    ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="name-row">
                            <div class="form-group">
                                <input type="text" name="firstname" class="form-control <?php echo (!empty($firstname_err)) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo $firstname; ?>" placeholder="First Name">
                                <span class="invalid-feedback"><?php echo $firstname_err; ?></span>
                            </div>
                            
                            <div class="form-group middle-name-container">
                                <input type="text" name="middlename" class="form-control" 
                                       value="<?php echo $middlename; ?>" placeholder="Middle Name">
                                <span class="optional-field">Optional</span>
                            </div>
                            
                            <div class="form-group">
                                <input type="text" name="lastname" class="form-control <?php echo (!empty($lastname_err)) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo $lastname; ?>" placeholder="Last Name">
                                <span class="invalid-feedback"><?php echo $lastname_err; ?></span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $email; ?>" placeholder="Email Address">
                            <span class="invalid-feedback"><?php echo $email_err; ?></span>
                        </div>
                        
                        <div class="form-group">
                            <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                   placeholder="Password">
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </span>
                            <span class="invalid-feedback"><?php echo $password_err; ?></span>
                        </div>
                        
                        <div class="form-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" 
                                   placeholder="Confirm Password">
                            <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </span>
                            <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="" id="termsCheck" required>
                            <label class="form-check-label" for="termsCheck">
                                I agree to the <a href="terms.php" class="text-primary">Terms of Service</a> and <a href="privacy.php" class="text-primary">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-signup w-100">Create Account</button>
                        </div>
                        
                        <div class="form-divider">or sign up with</div>
                        
                        <div class="login-link">
                            Already have an account? <a href="login.php" class="text-primary">Sign in</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Script -->
    <script>
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const icon = event.currentTarget.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>