<?php
session_start();
require_once 'includes/audit_logger.php'; // ensure this is here just in case

// Database configuration
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "bookwagon_db";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("System error: Unable to connect to the database.");
}
require_once 'includes/GoogleAuthenticator.php';
require_once 'includes/audit_logger.php';

// Check if user is in the middle of 2FA verification
if (!isset($_SESSION['pending_2fa_verification']) || !isset($_SESSION['temp_user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_code'])) {
    $code = trim($_POST['code']);
    
    // Get the user's secret and login_count from the database
    $stmt = $conn->prepare("SELECT google2fa_secret, login_count, usertype, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['temp_user_id']);
    $stmt->execute();
    $stmt->bind_result($secret, $login_count_db, $usertype_db, $status_db);
    $user_found = $stmt->fetch();
    $stmt->close();
    
    // Block suspended users
    if ($user_found && $status_db === 'suspended') {
        $error = 'Your account has been suspended. Please contact support.';
        $user_found = false;
    }
    
    if ($user_found && !empty($secret)) {
        $ga = new GoogleAuthenticator();
        $checkResult = $ga->verifyCode($secret, $code, 2); // 2 = 2*30sec clock tolerance
        
        if ($checkResult) {
            $prev_count = (int)$login_count_db;
            $new_count = $prev_count + 1;
            
            // Increment login count in database
            $update_stmt = $conn->prepare("UPDATE users SET login_count = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_count, $_SESSION['temp_user_id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Success! Log them in fully.
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $_SESSION['temp_user_id'];
            $_SESSION['email'] = $_SESSION['temp_email'];
            $_SESSION['user'] = $_SESSION['temp_email'];
            $_SESSION['firstname'] = $_SESSION['temp_firstname'];
            $_SESSION['lastname'] = $_SESSION['temp_lastname'];
            $_SESSION['usertype'] = $usertype_db ?? 'user';
            $_SESSION['login_count'] = $new_count;
            
            // Clean up temp vars
            unset($_SESSION['pending_2fa_verification']);
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_email']);
            unset($_SESSION['temp_firstname']);
            unset($_SESSION['temp_lastname']);
            unset($_SESSION['temp_usertype']);
            unset($_SESSION['temp_login_count']);
            
            log_activity($_SESSION['user_id'], '2FA Login', 'User successfully passed 2FA.');
            
            // Log Login History
            $ip = $_SERVER['REMOTE_ADDR'];
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
            $hist_sql = "INSERT INTO login_history (user_id, ip_address, device_info, status) VALUES (?, ?, ?, 'success')";
            if ($hstmt = $conn->prepare($hist_sql)) {
                $hstmt->bind_param("iss", $_SESSION['user_id'], $ip, $ua);
                $hstmt->execute();
                $hstmt->close();
            }
            
            // First time login (prev_count == 0) -> welcome.php, otherwise -> dashboard.php
            if ($prev_count === 0) {
                header('Location: welcome.php');
            } elseif (isset($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                header("Location: " . $redirect);
            } else {
                header('Location: home.php');
            }
            exit;
        } else {
            $error = "Invalid verification code. Please try again.";
        }
    } else {
        $error = "2FA is not configured for this account.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Two-Factor Authentication</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .verify-container { max-width: 400px; margin: 100px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
        .icon { font-size: 48px; color: #0d6efd; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="icon">🔒</div>
        <h2>Two-Factor Authentication</h2>
        <p class="text-muted">Enter the 6-digit code from your authenticator app to continue.</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form action="verify_2fa.php" method="POST" class="mt-4">
            <div class="mb-3">
                <input type="text" name="code" class="form-control form-control-lg text-center" placeholder="123456" maxlength="6" required autocomplete="off" autofocus>
            </div>
            <button type="submit" name="verify_code" class="btn btn-primary btn-lg w-100">Verify Code</button>
        </form>
        <div class="mt-3">
            <a href="login.php" class="text-muted text-decoration-none">Back to Login</a>
        </div>
    </div>
</body>
</html>
