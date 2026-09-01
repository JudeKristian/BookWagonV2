<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes redirect to admin dashboard
if(isset($_SESSION["admin_loggedin"]) && $_SESSION["admin_loggedin"] === true){
    header("location: admin_dashboard.php");
    exit;
}

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Display error message if exists
if(isset($_SESSION["login_err"])) {
    $login_err = $_SESSION["login_err"];
    unset($_SESSION["login_err"]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - BookWagon</title>
    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 40px 32px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .logo-wrap {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo-wrap img {
            height: 48px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .login-title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .login-subtitle {
            font-size: 13px;
            color: #64748b;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-control {
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            color: #0f172a;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #f8a100;
            box-shadow: 0 0 0 3px rgba(248, 161, 0, 0.2);
            outline: none;
        }

        .btn-login {
            background-color: #f8a100;
            color: #ffffff;
            font-weight: 600;
            font-size: 15px;
            padding: 11px;
            border-radius: 8px;
            border: none;
            width: 100%;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-login:hover {
            background-color: #e09000;
            color: #ffffff;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: #f8a100;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <!-- Logo & Title -->
        <div class="logo-wrap">
            <a href="../home.php">
                <img src="../images/logo.png" alt="BookWagon">
            </a>
            <h1 class="login-title">Admin Login</h1>
            <p class="login-subtitle">Sign in to access the control panel</p>
        </div>

        <!-- Error Alert -->
        <?php if(!empty($login_err)): ?>
            <div class="alert alert-danger py-2 px-3 mb-3 rounded-3" style="font-size: 13px;" role="alert">
                <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo htmlspecialchars($login_err); ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="login_process.php" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" 
                       class="form-control" 
                       id="username" 
                       name="username" 
                       placeholder="Enter admin username" 
                       value="<?php echo htmlspecialchars($username); ?>" 
                       required 
                       autofocus>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" 
                       class="form-control" 
                       id="password" 
                       name="password" 
                       placeholder="Enter password" 
                       required>
            </div>

            <button type="submit" class="btn btn-login">
                Login
            </button>
        </form>

        <a href="../home.php" class="back-link">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Home
        </a>
    </div>

</body>
</html>