<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("connect.php");

// Verify Step 1 was completed
if (!isset($_SESSION['seller_registration']) || empty($_SESSION['seller_registration']['step_1_completed'])) {
    header("Location: start_selling.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
$regData = $_SESSION['seller_registration'];
$firstName = $regData['first_name'] ?? $_SESSION['firstname'] ?? 'Applicant';
$lastName = $regData['last_name'] ?? $_SESSION['lastname'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookWagon - Store & Address Details</title>
    <!-- Google Font: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-orange: #f8a100;
            --primary-dark: #d97706;
            --bg-neutral: #f8fafc;
            --card-border: #e2e8f0;
            --text-dark: #0f172a;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-neutral);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* Dedicated Header */
        .seller-onboarding-header {
            background: #ffffff;
            border-bottom: 1px solid var(--card-border);
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 1020;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        /* 3-Step Visual Progress Stepper */
        .stepper-container {
            max-width: 700px;
            margin: 25px auto 35px;
            padding: 0 15px;
        }

        .stepper-track {
            display: flex;
            justify-content: space-between;
            position: relative;
        }

        .stepper-track::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50px;
            right: 50px;
            height: 3px;
            background: #e2e8f0;
            z-index: 1;
        }

        .stepper-progress-fill {
            content: '';
            position: absolute;
            top: 20px;
            left: 50px;
            width: 50%;
            height: 3px;
            background: var(--primary-orange);
            z-index: 2;
        }

        .step-item {
            position: relative;
            z-index: 3;
            text-align: center;
            flex: 1;
        }

        .step-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            color: #64748b;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            transition: all 0.3s ease;
        }

        .step-item.completed .step-circle {
            background: #10b981;
            border-color: #10b981;
            color: #ffffff;
        }

        .step-item.active .step-circle {
            background: var(--primary-orange);
            border-color: var(--primary-orange);
            color: #ffffff;
            box-shadow: 0 0 0 5px rgba(248, 161, 0, 0.2);
        }

        .step-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }

        .step-item.active .step-label {
            color: var(--primary-orange);
            font-weight: 700;
        }

        /* Form Card */
        .registration-card {
            background: #ffffff;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .card-header-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .header-icon-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(248, 161, 0, 0.12);
            color: var(--primary-orange);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .card-header-title h5 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
        }

        .form-label {
            font-weight: 600;
            font-size: 13px;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            border: 1px solid #cbd5e1;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 14px;
            color: #1e293b;
            background-color: #ffffff;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(248, 161, 0, 0.18);
        }

        .btn-submit-action {
            background: linear-gradient(135deg, #f8a100 0%, #ea580c 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 16px;
            padding: 14px 36px;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 14px rgba(248, 161, 0, 0.4);
            transition: all 0.2s ease;
        }

        .btn-submit-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(248, 161, 0, 0.5);
            color: #ffffff;
        }
    </style>
</head>
<body>

    <!-- Onboarding Header -->
    <header class="seller-onboarding-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="home.php" class="text-decoration-none">
                    <img src="images/logo.png" alt="BookWagon" style="height: 42px; object-fit: contain;">
                </a>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-1 fw-bold" style="font-size: 12px;">
                    <i class="fa-solid fa-store me-1"></i> SELLER HUB
                </span>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <a href="start_selling.php" class="btn btn-outline-secondary rounded-pill px-3 py-1 fw-medium" style="font-size: 13px;">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Step 1
                </a>
                
                <div class="d-flex align-items-center gap-2 border-start ps-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 36px; height: 36px; font-size: 14px; background-color: #f8a100;">
                        <?php echo strtoupper(substr($firstName ?: 'U', 0, 1)); ?>
                    </div>
                    <div class="d-none d-sm-block text-start" style="line-height: 1.2;">
                        <div class="fw-bold text-dark" style="font-size: 13px;"><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></div>
                        <small class="text-muted" style="font-size: 11px;">Step 2 of 3</small>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container py-4">
        <!-- Hero Header -->
        <div class="text-center mb-4">
            <h2 class="fw-bold text-dark mb-2">Store & Pickup Location</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">
                Specify your book shop display name and the physical address where borrowers can pick up or receive rented books.
            </p>
        </div>

        <!-- 3-Step Progress Indicator -->
        <div class="stepper-container">
            <div class="stepper-track">
                <div class="stepper-progress-fill"></div>
                <div class="step-item completed">
                    <div class="step-circle"><i class="fa-solid fa-check"></i></div>
                    <div class="step-label">Owner Details & IDs</div>
                </div>
                <div class="step-item active">
                    <div class="step-circle">2</div>
                    <div class="step-label">Store & Address</div>
                </div>
                <div class="step-item">
                    <div class="step-circle">3</div>
                    <div class="step-label">Verification & Launch</div>
                </div>
            </div>
        </div>

        <!-- Form Container -->
        <div class="mx-auto" style="max-width: 860px;">
            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="process_seller_address.php" method="POST" id="sellerAddressForm">
                
                <!-- Card 1: Shop Information -->
                <div class="registration-card">
                    <div class="card-header-title">
                        <div class="header-icon-badge">
                            <i class="fa-solid fa-shop"></i>
                        </div>
                        <div>
                            <h5>Store Information</h5>
                            <small class="text-muted">How your book collection will appear to readers</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="shop_name" class="form-label">Shop / Display Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="shop_name" name="shop_name" 
                                   placeholder="e.g., <?php echo htmlspecialchars($firstName); ?>'s Book Haven" required>
                        </div>
                        <div class="col-md-5">
                            <label for="seller_type" class="form-label">Seller Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="seller_type" name="seller_type" required>
                                <option value="Individual Book Owner">Individual Book Owner</option>
                                <option value="Bookstore / Library Hub">Bookstore / Library Hub</option>
                                <option value="Student Renter">Student Renter</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Pickup Address Information -->
                <div class="registration-card">
                    <div class="card-header-title">
                        <div class="header-icon-badge">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div>
                            <h5>Physical Pickup Address</h5>
                            <small class="text-muted">Used for book handovers, swap meetups, and local delivery</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="country" class="form-label">Country <span class="text-danger">*</span></label>
                            <select class="form-select" id="country" name="country" required>
                                <option value="Philippines" selected>Philippines</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="province" class="form-label">Province <span class="text-danger">*</span></label>
                            <select class="form-select" id="province" name="province" required>
                                <option value="Davao del Sur" selected>Davao del Sur</option>
                                <option value="Davao del Norte">Davao del Norte</option>
                                <option value="Davao Oriental">Davao Oriental</option>
                                <option value="Metro Manila">Metro Manila</option>
                                <option value="Cebu">Cebu</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="city" class="form-label">City / Municipality <span class="text-danger">*</span></label>
                            <select class="form-select" id="city" name="city" required>
                                <option value="Davao City" selected>Davao City</option>
                                <option value="Digos City">Digos City</option>
                                <option value="Tagum City">Tagum City</option>
                                <option value="Panabo City">Panabo City</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="barangay" class="form-label">Barangay / District <span class="text-danger">*</span></label>
                            <select class="form-select" id="barangay" name="barangay" required>
                                <option value="">Select Barangay...</option>
                                <option value="Poblacion">Poblacion</option>
                                <option value="Talomo">Talomo</option>
                                <option value="Buhangin">Buhangin</option>
                                <option value="Agdao">Agdao</option>
                                <option value="Bangkal">Bangkal</option>
                                <option value="Matina">Matina</option>
                                <option value="Bucana">Bucana</option>
                                <option value="Toril">Toril</option>
                                <option value="Mintal">Mintal</option>
                                <option value="Catalunan Grande">Catalunan Grande</option>
                                <option value="Bajada">Bajada</option>
                                <option value="Lanang">Lanang</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="postal_code" class="form-label">Postal / ZIP Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" placeholder="8000" required>
                        </div>

                        <div class="col-md-8">
                            <label for="detailed_address" class="form-label">Street / House / Unit / Building Details <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="detailed_address" name="detailed_address" 
                                   placeholder="House/Unit No., Street Name, Landmark" required>
                        </div>
                    </div>
                </div>

                <!-- Submit Action -->
                <div class="text-center mt-4 mb-5">
                    <button type="submit" class="btn btn-submit-action">
                        Submit Application for Verification <i class="fa-solid fa-paper-plane ms-2"></i>
                    </button>
                    <div class="text-muted mt-2" style="font-size: 12px;">
                        <i class="fa-solid fa-shield-check me-1 text-success"></i> Your application will be sent to the BookWagon administration team for review.
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>