<?php
// Initialize session at the VERY top for lockout tracking
session_start();

// Database configuration
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "bookwagon_db";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("System error: Unable to connect to the database.");
}

require_once 'includes/audit_logger.php';
require_once 'google_config.php';

// --- ACCOUNT LOCKOUT LOGIC ---
$max_attempts = 3;
$lockout_time = 300; // 5 minutes in seconds
$login_err = "";

if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= $max_attempts) {
    $time_since_last_attempt = time() - $_SESSION['last_login_attempt'];
    if ($time_since_last_attempt < $lockout_time) {
        $minutes_left = ceil(($lockout_time - $time_since_last_attempt) / 60);
        $login_err = "Account locked due to too many failed attempts. Please try again in $minutes_left minutes.";
    } else {
        // Reset after timer expires
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['last_login_attempt']);
    }
}

$email = $password = "";
$email_err = $password_err = $captcha_err = "";

// Pre-fill email if remember cookie exists
if (empty($_POST) && isset($_COOKIE['remember_email'])) {
    $email = $_COOKIE['remember_email'];
}

// Processing form data when form is submitted (AND NOT LOCKED OUT)
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($login_err)) {
    
    // 1. Google reCAPTCHA Validation
    if (empty($_POST['g-recaptcha-response'])) {
        $captcha_err = "Please check the 'I'm not a robot' checkbox.";
    } else {
        $recaptcha_secret = '6LcndJktAAAAACj2R6ryalgZG2dBaKfeIfZ50Yp4';
        $recaptcha_response = $_POST['g-recaptcha-response'];
        $recaptcha_url = "https://www.google.com/recaptcha/api/siteverify";
        
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query(array(
                    'secret' => $recaptcha_secret,
                    'response' => $recaptcha_response
                ))
            )
        );
        $context  = stream_context_create($options);
        $recaptcha_verify = file_get_contents($recaptcha_url, false, $context);
        $recaptcha_data = json_decode($recaptcha_verify);
        
        if (!$recaptcha_data->success) {
            $captcha_err = "reCAPTCHA verification failed. Please try again.";
        }
    }

    if (empty($captcha_err)) {
        // Validate email
        if (empty(trim($_POST["email"]))) {
            $email_err = "Please enter your email.";
        } else {
            $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
        }
        
        // Validate password
        if (empty(trim($_POST["password"]))) {
            $password_err = "Please enter your password.";
        } else {
            $password = trim($_POST["password"]);
        }
        
        // Check input errors before checking database
        if (empty($email_err) && empty($password_err)) {
            $sql = "SELECT id, email, password, google2fa_secret, is_2fa_enabled, auth_provider FROM users WHERE email = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_email);
                $param_email = $email;
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows == 1) {                    
                        $stmt->bind_result($id, $email_db, $hashed_password, $google2fa_secret, $is_2fa_enabled, $auth_provider);
                        if ($stmt->fetch()) {
                            if (password_verify($password, $hashed_password)) {
                                // SUCCESSFUL LOGIN
                                
                                // Handle Remember Me
                                if (isset($_POST['remember_me'])) {
                                    setcookie('remember_email', $email_db, time() + (86400 * 30), "/"); // 30 days
                                } else {
                                    setcookie('remember_email', '', time() - 3600, "/"); // delete if unchecked
                                }

                                // Reset failed attempts
                                $_SESSION['login_attempts'] = 0;
                                unset($_SESSION['last_login_attempt']);
                                
                                // Get user details
                                $user_query = "SELECT firstname, lastname, usertype, login_count FROM users WHERE id = ?";
                                $stmt_user = $conn->prepare($user_query);
                                $stmt_user->bind_param("i", $id);
                                $stmt_user->execute();
                                $stmt_user->bind_result($firstName, $lastName, $userType, $loginCount);
                                $stmt_user->fetch();
                                $stmt_user->close();

                                // Set temporary session variables for 2FA phase
                                $_SESSION["temp_user_id"] = $id;
                                $_SESSION["temp_email"] = $email_db;
                                $_SESSION["temp_firstname"] = $firstName;
                                $_SESSION["temp_lastname"] = $lastName;
                                $_SESSION["temp_usertype"] = $userType;
                                $_SESSION["temp_login_count"] = $loginCount ?? 0;

                                if ($is_2fa_enabled == 1) {
                                    $_SESSION["pending_2fa_verification"] = true;
                                    header("Location: verify_2fa.php");
                                    exit();
                                } else {
                                    // Direct Login (2FA Disabled)
                                    $_SESSION['loggedin'] = true;
                                    $_SESSION['user_id'] = $id;
                                    $_SESSION['id'] = $id;
                                    $_SESSION['email'] = $email_db;
                                    $_SESSION['user'] = $email_db;
                                    $_SESSION['firstname'] = $firstName;
                                    $_SESSION['lastname'] = $lastName;
                                    $_SESSION['usertype'] = $userType ?? 'user';
                                    
                                    $prev_count = $loginCount ?? 0;
                                    $_SESSION['login_count'] = $prev_count + 1;
                                    
                                    // Increment login count
                                    $update_stmt = $conn->prepare("UPDATE users SET login_count = login_count + 1 WHERE id = ?");
                                    $update_stmt->bind_param("i", $id);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                    
                                    // Log Login History
                                    $ip = $_SERVER['REMOTE_ADDR'];
                                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
                                    $hist_sql = "INSERT INTO login_history (user_id, ip_address, device_info, status) VALUES (?, ?, ?, 'success')";
                                    if ($hstmt = $conn->prepare($hist_sql)) {
                                        $hstmt->bind_param("iss", $id, $ip, $ua);
                                        $hstmt->execute();
                                        $hstmt->close();
                                    }
                                    
                                    // Clean up temp vars just in case
                                    unset($_SESSION["temp_user_id"], $_SESSION["temp_email"], $_SESSION["temp_firstname"], $_SESSION["temp_lastname"], $_SESSION["temp_usertype"], $_SESSION["temp_login_count"]);
                                    
                                    if ($prev_count === 0) {
                                        header('Location: welcome.php');
                                    } elseif (isset($_SESSION['redirect_after_login'])) {
                                        $redirect = $_SESSION['redirect_after_login'];
                                        unset($_SESSION['redirect_after_login']);
                                        header("Location: " . $redirect);
                                    } else {
                                        header('Location: home.php');
                                    }
                                    exit();
                                }
                            } else {
                                // FAILED PASSWORD
                                handle_failed_login($email, $conn);
                                if ($auth_provider === 'google') {
                                    $login_err = "This account was created with Google! Please click the 'Sign in with Google' button below.";
                                } else {
                                    $login_err = "Invalid email or password.";
                                }
                            }
                        }
                    } else {
                        // EMAIL NOT FOUND
                        handle_failed_login($email, $conn);
                        $login_err = "Invalid email or password.";
                    }
                } else {
                    $login_err = "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
        }
    }
}

function handle_failed_login($email, $conn) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
    }
    $_SESSION['login_attempts']++;
    $_SESSION['last_login_attempt'] = time();
    
    // Log it to audit_logs (id is 0 because user failed to login)
    log_activity(0, 'Failed Login', "Failed login attempt for email: " . $email);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookWagon - Sign In</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons for Eye toggle -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Google reCAPTCHA script -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        :root {
            --primary-color: #f8a100;
            --secondary-color: #f8f9fa;
            --accent-blue: #5b6bff;
            --bg-cream: #faebc8;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .login-container {
            max-width: 800px;
            margin: 40px auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            background: white;
        }
        
        .login-row {
            display: flex;
            min-height: 500px;
        }
        
        .login-image {
            flex: 1;
            background-color: var(--bg-cream);
            position: relative;
            overflow: hidden;
        }
        
        .login-image img {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 110%;
            height: 110%;
            object-fit: cover;
        }
        
        .login-form {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo img {
            height: 100px;
        }
        
        h2 {
            font-weight: 600;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-control {
            height: 50px;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        /* Eye toggle styling */
        .input-group-text {
            background-color: white;
            border-left: none;
            cursor: pointer;
        }
        .password-field {
            border-right: none;
        }
        
        .btn-signin {
            height: 50px;
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 18px;
            margin-top: 10px;
        }
        
        .btn-signin:hover {
            background-color: #e09000;
        }
        
        .form-divider {
            text-align: center;
            position: relative;
            margin: 30px 0;
        }
        
        .form-divider::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            width: 45%;
            height: 1px;
            background-color: #ddd;
        }
        
        .form-divider::after {
            content: "";
            position: absolute;
            right: 0;
            top: 50%;
            width: 45%;
            height: 1px;
            background-color: #ddd;
        }
        
        .signup-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .blob-blue {
            position: absolute;
            background-color: var(--accent-blue);
            border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
            z-index: 0;
        }
        
        .blob-blue-1 { width: 200px; height: 200px; top: -50px; left: -50px; }
        .blob-blue-2 { width: 300px; height: 300px; bottom: -100px; left: -50px; }
        .blob-blue-3 { width: 180px; height: 180px; top: 50%; right: -50px; transform: translateY(-50%); }

        @media (max-width: 768px) {
            .login-row { flex-direction: column; }
            .login-image { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-row">
                <!-- Left side - Decorative illustration -->
                <div class="login-image">
                    <div class="blob-blue blob-blue-1"></div>
                    <div class="blob-blue blob-blue-2"></div>
                    <div class="blob-blue blob-blue-3"></div>
                </div>
                
                <!-- Right side - Login form -->
                <div class="login-form">
                    <div class="logo">
                        <img src="images/logo.png" alt="BookWagon Logo">
                    </div>
                    
                    <h2>Sign In</h2>
                    
                    <?php 
                    if(!empty($login_err)){
                        echo '<div class="alert alert-danger fw-bold text-center">' . $login_err . '</div>';
                    }        
                    ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <!-- Email -->
                        <div class="form-group">
                            <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($email); ?>" placeholder="Email Address">
                            <span class="invalid-feedback"><?php echo $email_err; ?></span>
                        </div>
                        
                        <!-- Password with Eye Toggle -->
                        <div class="form-group">
                            <div class="input-group">
                                <input type="password" id="password" name="password" class="form-control password-field <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                       placeholder="Password">
                                <span class="input-group-text" onclick="togglePassword('password', 'toggle-eye')">
                                    <i class="bi bi-eye-slash" id="toggle-eye"></i>
                                </span>
                            </div>
                            <?php if(!empty($password_err)): ?>
                                <div class="text-danger small mt-1"><?php echo $password_err; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remember_me" value="1" id="rememberPasswordCheck" <?php echo isset($_COOKIE['remember_email']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="rememberPasswordCheck">
                                Remember me
                            </label>
                            <a href="forgot-password.php" class="float-end text-primary">Forgot password?</a>
                        </div>
                        
                        <!-- Google reCAPTCHA -->
                        <div class="form-group d-flex flex-column align-items-center">
                            <div class="g-recaptcha" data-sitekey="6LcndJktAAAAAHfBOz7zcg5fxVW8dmJT9UoGO9jk"></div>
                            <?php if(!empty($captcha_err)): ?>
                                <div class="text-danger small mt-2 fw-bold text-center"><?php echo $captcha_err; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-signin w-100" <?php echo (!empty($login_err) && strpos($login_err, 'locked') !== false) ? 'disabled' : ''; ?>>Sign In</button>
                        </div>
                        
                        <div class="form-divider">Or</div>
                        
                        <div class="form-group mt-3">
                            <a href="<?php echo htmlspecialchars($google_auth_url); ?>" class="btn btn-outline-dark w-100 d-flex justify-content-center align-items-center" style="gap: 10px; border-radius: 8px; height: 50px; font-weight: 500;">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google Logo" style="height: 20px;">
                                Sign in with Google
                            </a>
                        </div>
                        
                        <div class="signup-link mt-3">
                            Don't have an account? <a href="signup.php" class="text-primary">Sign up</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS for Password Toggle -->
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            }
        }
    </script>
</body>
</html>