<?php

include("connect.php");

// Initialize variables
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Check input errors before checking database
    if (empty($email_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, email, password, firstname, lastname, username, usertype FROM users WHERE email = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_email);
            
            // Set parameters
            $param_email = $email;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if email exists, if yes then verify password
                if ($stmt->num_rows == 1) {                    
                    // Bind result variables
                    $stmt->bind_result($id, $email, $hashed_password, $firstname, $lastname, $username, $usertype);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, start a new session
                            session_start();
                            
                            // Get the current login count
                            $loginCountSql = "SELECT login_count FROM users WHERE id = ?";
                            $loginCountStmt = $conn->prepare($loginCountSql);
                            $loginCountStmt->bind_param("i", $id);
                            $loginCountStmt->execute();
                            $loginCountStmt->bind_result($login_count);
                            $loginCountStmt->fetch();
                            $loginCountStmt->close();
                            
                            // Check if this is the user's first login (login_count is 0)
                            $isFirstLogin = ($login_count == 0);
                            
                            // Increment the login count
                            $updateLoginCountSql = "UPDATE users SET login_count = login_count + 1 WHERE id = ?";
                            $updateLoginCountStmt = $conn->prepare($updateLoginCountSql);
                            $updateLoginCountStmt->bind_param("i", $id);
                            $updateLoginCountStmt->execute();
                            $updateLoginCountStmt->close();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["email"] = $email;
                            $_SESSION["firstname"] = $firstname;
                            $_SESSION["lastname"] = $lastname;
                            $_SESSION["username"] = $username;
                            $_SESSION["usertype"] = $usertype;
                            $_SESSION["login_count"] = $login_count + 1;
                            
                            // Check if this is a new user or the first login
                            $isNewUser = isset($_GET['new_user']) && $_GET['new_user'] == '1';
                            
                            // Redirect based on usertype and whether this is a new user
                            if($usertype === "admin") {
                                header("location: admin/dashboard.php");
                            } else if($isNewUser || $isFirstLogin) {
                                // Redirect new users to the welcome page
                                header("location: welcome.php");
                            } else {
                                header("location: dashboard.php");
                            }
                        } else {
                            // Password is not valid
                            $login_err = "Invalid email or password.";
                        }
                    }
                } else {
                    // Email doesn't exist
                    $login_err = "Invalid email or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
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
    <title>BookWagon - Sign In</title>
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
        
        .login-container {
            max-width: 900px;
            margin: 40px auto;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            background: white;
            position: relative;
            z-index: 2;
        }
        
        .login-row {
            display: flex;
            min-height: 550px;
        }
        
        .login-image {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-image-content {
            position: relative;
            z-index: 5;
            padding: 30px;
            color: white;
            text-align: center;
            max-width: 80%;
        }
        
        .login-image-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .login-image-text {
            font-size: 1.1rem;
            margin-bottom: 20px;
            line-height: 1.6;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .floating-books {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
        }
        
        .floating-book {
            position: absolute;
            color: rgba(255, 255, 255, 0.2);
            font-size: 2rem;
            animation: float 6s ease-in-out infinite;
        }
        
        .book-1 {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .book-2 {
            top: 20%;
            right: 15%;
            animation-delay: 1s;
        }
        
        .book-3 {
            bottom: 15%;
            left: 15%;
            animation-delay: 2s;
        }
        
        .book-4 {
            bottom: 25%;
            right: 10%;
            animation-delay: 3s;
        }
        
        @keyframes float {
            0% { transform: translateY(0) rotate(0); }
            50% { transform: translateY(-15px) rotate(5deg); }
            100% { transform: translateY(0) rotate(0); }
        }
        
        .login-form {
            flex: 1;
            padding: 50px 40px;
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
        
        .btn-signin {
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
        
        .btn-signin::after {
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
        
        .btn-signin:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(248, 176, 121, 0.4);
        }
        
        .btn-signin:hover::after {
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
        
        .signup-link {
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
        
        .forgot-password {
            font-size: 0.95rem;
            color: var(--primary-color);
            transition: all 0.3s ease;
            font-weight: 500;
            text-decoration: none;
        }
        
        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .login-container {
                max-width: 95%;
                margin: 30px auto;
            }

            .login-image-content {
                padding: 25px;
                max-width: 85%;
            }

            .login-image-title {
                font-size: 1.8rem;
            }

            .login-image-text {
                font-size: 1rem;
            }
        }

        @media (max-width: 991px) {
            .login-container {
                max-width: 90%;
                margin: 25px auto;
            }

            .login-image-content {
                padding: 20px;
                max-width: 90%;
            }

            .login-image-title {
                font-size: 1.6rem;
            }

            .login-image-text {
                font-size: 0.95rem;
            }

            .login-form {
                padding: 45px 35px;
            }

            .form-control {
                height: 50px;
                padding: 10px 18px;
                font-size: 0.95rem;
            }

            .btn-signin {
                height: 50px;
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            .login-row {
                flex-direction: column;
                min-height: auto;
            }

            .login-image {
                display: none;
            }

            .login-form {
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

            .form-control {
                height: 48px;
                padding: 10px 16px;
                font-size: 0.95rem;
                margin-bottom: 18px;
            }

            .btn-signin {
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

            .forgot-password {
                font-size: 0.9rem;
            }

            .signup-link {
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
            .login-container {
                max-width: 95%;
                margin: 20px auto;
                border-radius: 16px;
            }

            .login-form {
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

            .btn-signin {
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

            .forgot-password {
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

            .signup-link {
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
            .login-container {
                max-width: 98%;
                margin: 15px auto;
                border-radius: 12px;
            }

            .login-form {
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

            .btn-signin {
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

            .forgot-password {
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

            .signup-link {
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
        <div class="login-container">
            <div class="login-row">
                <!-- Left side - Decorative illustration -->
                <div class="login-image">
                    <div class="floating-books">
                        <div class="floating-book book-1"><i class="fas fa-book"></i></div>
                        <div class="floating-book book-2"><i class="fas fa-book-open"></i></div>
                        <div class="floating-book book-3"><i class="fas fa-bookmark"></i></div>
                        <div class="floating-book book-4"><i class="fas fa-book-reader"></i></div>
                    </div>
                    
                    <div class="login-image-content">
                        <div class="login-image-title">Welcome Back!</div>
                        <div class="login-image-text">Access your BookWagon account to discover, buy, sell and rent books from a community of passionate readers across the Philippines.</div>
                    </div>
                </div>
                
                <!-- Right side - Login form -->
                <div class="login-form">
                    <div class="logo">
                        <img src="images/logo.png" alt="BookWagon Logo">

                    </div>
                    
                    <div class="text-center mb-4">
                        <h2>Sign In</h2>
                    </div>
                    
                    <?php 
                    if(!empty($login_err)){
                        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' . $login_err . '</div>';
                    }        
                    ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group">
                            <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo $email; ?>" placeholder="Email Address">
                            <span class="invalid-feedback"><?php echo $email_err; ?></span>
                        </div>    
                        <div class="form-group">
                            <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                   placeholder="Password">
                            <span class="invalid-feedback"><?php echo $password_err; ?></span>
                        </div>
                        <div class="form-check mb-3 d-flex justify-content-between align-items-center">
                            <div>
                                <input class="form-check-input" type="checkbox" value="" id="rememberPasswordCheck">
                                <label class="form-check-label" for="rememberPasswordCheck">
                                    Remember me
                                </label>
                            </div>
                            <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-signin w-100">Sign In</button>
                        </div>
                        <div class="form-divider">or sign in with</div>
                        <div class="signup-link">
                            Don't have an account? <a href="signup.php" class="text-primary">Sign up</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>