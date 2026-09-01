<?php
session_start();
require_once 'google_config.php';
require_once 'includes/audit_logger.php';

// Database configuration
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "bookwagon_db";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("System error: Unable to connect to the database.");
}

if (isset($_GET['code'])) {
    // 1. Exchange the auth code for an access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri' => $google_redirect_uri,
        'grant_type' => 'authorization_code',
        'code' => $_GET['code']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP development
    
    $response = curl_exec($ch);
    $token_data = json_decode($response, true);
    curl_close($ch);
    
    if (isset($token_data['error'])) {
        die("Google OAuth Error: " . $token_data['error_description']);
    }
    
    $access_token = $token_data['access_token'];
    
    // 2. Fetch the user's Google profile info
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $profile_response = curl_exec($ch);
    $profile_data = json_decode($profile_response, true);
    curl_close($ch);
    
    if (!isset($profile_data['email'])) {
        die("Failed to fetch Google profile. Ensure your client ID is valid.");
    }
    
    $email = $profile_data['email'];
    $google_id = $profile_data['id'];
    $firstName = $profile_data['given_name'] ?? '';
    $lastName = $profile_data['family_name'] ?? '';
    
    // 3. Check if user exists in our database
    $stmt = $conn->prepare("SELECT id, firstname, lastname, usertype, login_count, google2fa_secret FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        // User exists -> Log them in
        $stmt->bind_result($id, $db_firstName, $db_lastName, $db_userType, $db_loginCount, $google2fa_secret);
        $stmt->fetch();
        
        // Update their google_oauth_id just in case they signed up manually first
        $update_stmt = $conn->prepare("UPDATE users SET google_oauth_id = ?, auth_provider = 'google' WHERE id = ?");
        $update_stmt->bind_param("si", $google_id, $id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $_SESSION["temp_user_id"] = $id;
        $_SESSION["temp_email"] = $email;
        $_SESSION["temp_firstname"] = $db_firstName;
        $_SESSION["temp_lastname"] = $db_lastName;
        $_SESSION["temp_usertype"] = $db_userType;
        $_SESSION["temp_login_count"] = $db_loginCount ?? 0;
        
        log_activity($id, 'Google Login', "User initiated Google login");
        
    } else {
        // User does not exist -> Register them automatically
        $random_password = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
        $username = explode('@', $email)[0] . rand(100, 999);
        $usertype = 'user';
        
        $insert_stmt = $conn->prepare("INSERT INTO users (email, password, username, firstname, lastname, usertype, auth_provider, google_oauth_id, login_count) VALUES (?, ?, ?, ?, ?, ?, 'google', ?, 0)");
        $insert_stmt->bind_param("sssssss", $email, $random_password, $username, $firstName, $lastName, $usertype, $google_id);
        
        if ($insert_stmt->execute()) {
            $id = $insert_stmt->insert_id;
            $google2fa_secret = null; // New user has no 2FA yet
            
            $_SESSION["temp_user_id"] = $id;
            $_SESSION["temp_email"] = $email;
            $_SESSION["temp_firstname"] = $firstName;
            $_SESSION["temp_lastname"] = $lastName;
            $_SESSION["temp_usertype"] = $usertype;
            $_SESSION["temp_login_count"] = 0;
            
            log_activity($id, 'Google Registration', "User automatically registered via Google");
        } else {
            die("Database Error during registration: " . $conn->error);
        }
        $insert_stmt->close();
    }
    $stmt->close();
    
    // 4. Send them to the 2FA flow (we maintain 2FA even for Google users per exam rules)
    if (empty($google2fa_secret)) {
        $_SESSION["pending_2fa_setup"] = true;
        header("Location: setup_2fa.php");
    } else {
        $_SESSION["pending_2fa_verification"] = true;
        header("Location: verify_2fa.php");
    }
    exit();
    
} else {
    // If they land here without a code, redirect to login
    header("Location: login.php");
    exit();
}
?>
