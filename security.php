<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? '';
$userId = $_SESSION['id'] ?? 0;

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Handle 2FA Toggle
if (isset($_POST['toggle_2fa'])) {
    $current_2fa_status = $_POST['current_status']; // 1 or 0
    $new_status = $current_2fa_status == 1 ? 0 : 1;

    if ($new_status == 1) {
        // Redirect to setup 2FA if trying to enable
        // But first check if they already have a secret
        $check_stmt = $conn->prepare("SELECT google2fa_secret FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $userId);
        $check_stmt->execute();
        $check_stmt->bind_result($existing_secret);
        $check_stmt->fetch();
        $check_stmt->close();

        if (empty($existing_secret)) {
            // Need to setup
            $_SESSION["pending_2fa_setup"] = true;
            $_SESSION["temp_user_id"] = $userId;
            $_SESSION["temp_email"] = $_SESSION['email'];
            header("Location: setup_2fa.php");
            exit();
        } else {
            // Just enable it
            $update_stmt = $conn->prepare("UPDATE users SET is_2fa_enabled = 1 WHERE id = ?");
            $update_stmt->bind_param("i", $userId);
            $update_stmt->execute();
            $_SESSION['success_message'] = "Two-Factor Authentication has been enabled.";
        }
    } else {
        // Disable it
        $update_stmt = $conn->prepare("UPDATE users SET is_2fa_enabled = 0 WHERE id = ?");
        $update_stmt->bind_param("i", $userId);
        $update_stmt->execute();
        $_SESSION['success_message'] = "Two-Factor Authentication has been disabled.";
    }

    header("Location: security.php");
    exit();
}

// Fetch user security data
$stmt = $conn->prepare("SELECT is_2fa_enabled FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($is_2fa_enabled);
$stmt->fetch();
$stmt->close();

// Fetch login history
$history_query = "SELECT ip_address, device_info, login_time, status FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT 10";
$h_stmt = $conn->prepare($history_query);
$h_stmt->bind_param("i", $userId);
$h_stmt->execute();
$history_res = $h_stmt->get_result();
$login_history = $history_res->fetch_all(MYSQLI_ASSOC);
$h_stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - BookWagon</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #f8a100;
            --secondary-color: #f8f9fa;
            --text-dark: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
        }

        body {
            font-family: 'Arial', sans-serif;
            color: var(--text-dark);
            background-color: #fff;
        }

        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .navbar-brand img {
            height: 60px;
        }

        /* Sidebar Styles */
        .sidebar {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px 0;
            min-height: calc(100vh - 150px);
            position: sticky;
            top: 20px;
        }

        .sidebar-link {
            display: block;
            padding: 12px 20px;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background-color: rgba(0, 123, 255, 0.05);
            color: #4a6cf7;
            border-left: 3px solid #4a6cf7;
        }

        .sidebar-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }

        /* Security Card Styles */
        .security-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }
    </style>
</head>

<body>
    <!-- Include Header -->
    <?php include("include/user_header.php"); ?>

    <div class="container py-5">
        <div class="row">
            <!-- Sidebar Column -->
            <div class="col-md-3 mb-4">
                <div class="sidebar">
                    <h4 class="px-4 mb-4">My Profile</h4>
                    <a href="account.php" class="sidebar-link">
                        <i class="fa-solid fa-user"></i> Account
                    </a>
                    <a href="cart.php" class="sidebar-link">
                        <i class="fa-solid fa-shopping-cart"></i> Cart
                    </a>
                    <a href="rented_books.php" class="sidebar-link">
                        <i class="fa-solid fa-book"></i> Rented Books
                    </a>
                    <a href="collections.php" class="sidebar-link">
                        <i class="fa-solid fa-bookmark"></i> My Collections
                    </a>
                    <a href="history.php" class="sidebar-link">
                        <i class="fa-solid fa-clock-rotate-left"></i> Order History
                    </a>
                    <a href="security.php" class="sidebar-link active">
                        <i class="fa-solid fa-shield-halved"></i> Security Settings
                    </a>
                </div>
            </div>

            <!-- Main Content Column -->
            <div class="col-md-9">

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <h3 class="mb-4 fw-bold">Security Settings</h3>

                <!-- 2FA Section -->
                <div class="security-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="fa-solid fa-mobile-screen me-2 text-primary"></i>
                                Two-Factor Authentication (2FA)</h5>
                            <p class="text-muted mb-0">Add an extra layer of security to your account by requiring a
                                verification code when you log in.</p>
                        </div>
                        <form method="POST" action="security.php">
                            <input type="hidden" name="current_status" value="<?php echo $is_2fa_enabled; ?>">
                            <button type="submit" name="toggle_2fa"
                                class="btn <?php echo $is_2fa_enabled ? 'btn-danger' : 'btn-success'; ?> fw-bold px-4 shadow-sm"
                                style="border-radius: 8px;">
                                <?php echo $is_2fa_enabled ? 'Disable 2FA' : 'Enable 2FA'; ?>
                            </button>
                        </form>
                    </div>
                    <?php if ($is_2fa_enabled): ?>
                        <div class="mt-4 p-3 bg-light rounded text-success fw-medium border border-success-subtle">
                            <i class="fa-solid fa-circle-check me-2"></i> 2FA is currently active and protecting your
                            account.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Login History Section -->
                <div class="security-card">
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-laptop-house me-2 text-primary"></i> Recent Login
                        Activity</h5>
                    <p class="text-muted mb-3">Review your recent logins. If you see anything suspicious, change your
                        password immediately.</p>
                        
                    <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                        <i class="fa-brands fa-google fs-4 me-3"></i>
                        <div>
                            <strong>Use Google Sign-in?</strong><br>
                            If you signed up with Google, changing your password here won't work. If you see suspicious activity, please secure your Google Account immediately.
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>IP Address</th>
                                    <th>Device / Browser</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($login_history) > 0): ?>
                                    <?php foreach ($login_history as $history):
                                        // Simple parsing of User Agent
                                        $ua = $history['device_info'];
                                        $os = "Unknown OS";
                                        if (strpos($ua, 'Windows') !== false)
                                            $os = "Windows";
                                        elseif (strpos($ua, 'Mac') !== false)
                                            $os = "Mac OS";
                                        elseif (strpos($ua, 'Linux') !== false)
                                            $os = "Linux";
                                        elseif (strpos($ua, 'iPhone') !== false)
                                            $os = "iPhone";
                                        elseif (strpos($ua, 'Android') !== false)
                                            $os = "Android";

                                        $browser = "Unknown Browser";
                                        if (strpos($ua, 'Chrome') !== false)
                                            $browser = "Chrome";
                                        elseif (strpos($ua, 'Firefox') !== false)
                                            $browser = "Firefox";
                                        elseif (strpos($ua, 'Safari') !== false)
                                            $browser = "Safari";
                                        elseif (strpos($ua, 'Edge') !== false)
                                            $browser = "Edge";
                                        ?>
                                        <tr>
                                            <td><span
                                                    class="fw-medium text-dark"><?php echo date('M d, Y', strtotime($history['login_time'])); ?></span><br><small
                                                    class="text-muted"><?php echo date('h:i A', strtotime($history['login_time'])); ?></small>
                                            </td>
                                            <td><span
                                                    class="badge bg-light text-dark border"><?php echo htmlspecialchars($history['ip_address']); ?></span>
                                            </td>
                                            <td>
                                                <i
                                                    class="fa-solid <?php echo ($os == 'Windows' || $os == 'Mac OS' || $os == 'Linux') ? 'fa-desktop' : 'fa-mobile-screen'; ?> me-2 text-muted"></i>
                                                <span class="fw-medium text-dark"><?php echo $os; ?></span><br><small
                                                    class="text-muted"><?php echo $browser; ?></small>
                                            </td>
                                            <td>
                                                <?php if ($history['status'] == 'success'): ?>
                                                    <span
                                                        class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-3 py-2 rounded-pill"><i
                                                            class="fa-solid fa-check me-1"></i> Success</span>
                                                <?php else: ?>
                                                    <span
                                                        class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle px-3 py-2 rounded-pill"><i
                                                            class="fa-solid fa-xmark me-1"></i> Failed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-5">
                                            <i class="fa-solid fa-clock-rotate-left fs-2 mb-3 text-light"></i><br>
                                            No login history recorded yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>