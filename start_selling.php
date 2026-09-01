<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
include("connect.php");

// Normalize user ID
$userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

// Check if user is logged in
if (!$userId) {
    $_SESSION['error_message'] = "Please login to continue.";
    header("Location: login.php");
    exit();
}

$_SESSION['user_id'] = $userId;
$_SESSION['id'] = $userId;
$_SESSION['loggedin'] = true;

// Check if user is already a seller
try {
    $sql = "SELECT u.usertype, s.status 
            FROM users u 
            LEFT JOIN sellers s ON u.id = s.user_id 
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // If user is already an approved seller, redirect to seller dashboard
    if ($user && ($user['usertype'] === 'seller' || ($user['status'] ?? '') === 'approved')) {
        $_SESSION['error_message'] = "You are already registered as a seller.";
        header("Location: seller_dashboard.php");
        exit();
    }
    
    // If user has a pending seller application, redirect them to the success/pending page
    if ($user && ($user['status'] ?? '') === 'pending') {
        header("Location: seller_success.php");
        exit();
    }

    // Fetch user data for the form
    $sql = "SELECT firstname, lastname, middlename, email FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    
    // Map variables for form pre-population
    $firstName = $userData['firstname'] ?? '';
    $lastName = $userData['lastname'] ?? '';
    $middleInitial = !empty($userData['middlename']) ? substr($userData['middlename'], 0, 1) : '';
    $email = $userData['email'] ?? '';
    
    if (!$userData) {
        $_SESSION['error_message'] = "User data not found. Please try logging in again.";
        header("Location: login.php");
        exit();
    }

} catch(Exception $e) {
    error_log("Error in start_selling.php: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while processing your request. Please try again later.";
    header("Location: home.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Seller - BookWagon</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/tab.css">
    <style>
        :root {
            --primary-color: #f8a100;
            --primary-hover: #e08f00;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --bg-light: #f8fafc;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            color: var(--text-dark);
            background-color: #f8fafc;
        }

        /* Stepper Progress Bar */
        .stepper-container {
            max-width: 860px;
            margin: 30px auto 40px auto;
        }

        .stepper-track {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 20px;
        }

        .stepper-track::before {
            content: '';
            position: absolute;
            top: 22px;
            left: 50px;
            right: 50px;
            height: 3px;
            background: #e2e8f0;
            z-index: 1;
        }

        .step-item {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }

        .step-circle {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            margin: 0 auto 10px auto;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        .step-item.active .step-circle {
            background: #f8a100;
            border-color: #f8a100;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(248, 161, 0, 0.4);
            transform: scale(1.08);
        }

        .step-item.completed .step-circle {
            background: #22c55e;
            border-color: #22c55e;
            color: #ffffff;
        }

        .step-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }

        .step-item.active .step-label {
            color: #1e293b;
            font-weight: 700;
        }

        /* Section Cards */
        .registration-card {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .registration-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
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
            color: #f8a100;
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

        /* Form Inputs */
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
            border-color: #f8a100;
            box-shadow: 0 0 0 3px rgba(248, 161, 0, 0.18);
        }

        .form-control[readonly] {
            background-color: #f1f5f9;
            color: #475569;
            border-color: #e2e8f0;
            cursor: not-allowed;
        }

        /* Upload Dropzones */
        .upload-dropzone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px 15px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .upload-dropzone:hover {
            border-color: #f8a100;
            background: #fffbf2;
        }

        .upload-dropzone input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon {
            font-size: 26px;
            color: #94a3b8;
            margin-bottom: 8px;
            transition: color 0.2s ease;
        }

        .upload-dropzone:hover .upload-icon {
            color: #f8a100;
        }

        .upload-text {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 2px;
        }

        .upload-hint {
            font-size: 11px;
            color: #94a3b8;
        }

        .preview-img {
            max-height: 90px;
            max-width: 100%;
            border-radius: 6px;
            object-fit: cover;
            display: none;
            margin-top: 6px;
            border: 1px solid #e2e8f0;
        }

        /* Submit Button */
        .btn-submit-action {
            background: #f8a100;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            padding: 14px 36px;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 14px rgba(248, 161, 0, 0.35);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-submit-action:hover {
            background: #e08f00;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(248, 161, 0, 0.45);
        }
    </style>
</head>
<body>
    <!-- Dedicated Distraction-Free Onboarding Header -->
    <header class="bg-white border-bottom py-3 sticky-top shadow-sm">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="home.php" class="d-inline-block">
                    <img src="images/logo.png" alt="BookWagon" style="height: 52px; object-fit: contain;">
                </a>
                <span class="d-none d-md-inline-block border-start ps-3 py-1 text-muted fw-semibold" style="font-size: 14px;">
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 me-1" style="font-size: 11px;">SELLER HUB</span>
                    Merchant Registration
                </span>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <a href="home.php" class="btn btn-outline-secondary rounded-pill px-3 py-1 fw-medium" style="font-size: 13px; transition: all 0.2s ease;">
                    <i class="fa-solid fa-arrow-left me-1"></i> Exit to Home
                </a>
                
                <div class="d-flex align-items-center gap-2 border-start ps-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning text-white fw-bold shadow-sm" style="width: 36px; height: 36px; font-size: 14px; background-color: #f8a100 !important;">
                        <?php echo strtoupper(substr($firstName ?: 'U', 0, 1)); ?>
                    </div>
                    <div class="d-none d-sm-block text-start" style="line-height: 1.2;">
                        <div class="fw-bold text-dark" style="font-size: 13px;"><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></div>
                        <small class="text-muted" style="font-size: 11px;">Applicant</small>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container py-4">
        <!-- Hero Header -->
        <div class="text-center mb-4">
            <h2 class="fw-bold text-dark mb-2">Become a BookWagon Seller</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">
                Share your personal book collection, connect with fellow readers in your area, and earn from rentals and sales.
            </p>
        </div>

        <!-- 3-Step Progress Indicator -->
        <div class="stepper-container">
            <div class="stepper-track">
                <div class="step-item active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Owner Details & IDs</div>
                </div>
                <div class="step-item">
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

            <form action="process_owner_details.php" method="POST" enctype="multipart/form-data" id="sellerForm">
                
                <!-- Card 1: Personal Information -->
                <div class="registration-card">
                    <div class="card-header-title">
                        <div class="header-icon-badge">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                        <div>
                            <h5>Personal & Account Information</h5>
                            <small class="text-muted">Pre-filled with your registered BookWagon account details</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" 
                                   value="<?php echo htmlspecialchars($firstName); ?>" readonly>
                        </div>
                        <div class="col-md-2">
                            <label for="middleInitial" class="form-label">M.I.</label>
                            <input type="text" class="form-control" id="middleInitial" name="middleInitial" 
                                   value="<?php echo htmlspecialchars($middleInitial); ?>" readonly>
                        </div>
                        <div class="col-md-5">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" 
                                   value="<?php echo htmlspecialchars($lastName); ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($email); ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label for="phoneNumber" class="form-label">Contact Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-phone"></i></span>
                                <input type="tel" class="form-control border-start-0 ps-0" id="phoneNumber" name="phoneNumber" placeholder="0912 345 6789" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="socialMedia" class="form-label">Social Media / Portfolio Link <span class="text-muted fw-normal">(Optional)</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-brands fa-facebook"></i></span>
                                <input type="url" class="form-control border-start-0 ps-0" id="socialMedia" name="socialMedia" placeholder="https://facebook.com/yourprofile">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Primary ID Verification -->
                <div class="registration-card">
                    <div class="card-header-title">
                        <div class="header-icon-badge">
                            <i class="fa-solid fa-id-card"></i>
                        </div>
                        <div>
                            <h5>Primary Government ID</h5>
                            <small class="text-muted">Submit a clear government-issued identification</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="primaryIdType" class="form-label">Primary ID Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="primaryIdType" name="primaryIdType" required>
                                <option value="">Choose your primary ID...</option>
                                <option value="national_id">Philippine National ID (PhilID / ePhilID)</option>
                                <option value="passport">Philippine Passport</option>
                                <option value="driver_license">Driver's License (LTO)</option>
                                <option value="umid">UMID / SSS ID</option>
                                <option value="prc">PRC License</option>
                                <option value="postal">Postal ID</option>
                                <option value="other">Other Valid ID</option>
                            </select>
                        </div>

                        <div class="col-12" id="otherPrimaryIdTypeContainer" style="display: none;">
                            <label for="otherPrimaryIdType" class="form-label">Specify ID Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="otherPrimaryIdType" name="otherPrimaryIdType" placeholder="e.g., Voter's ID">
                        </div>

                        <!-- Front & Back Upload -->
                        <div class="col-md-6">
                            <label class="form-label">Front of ID <span class="text-danger">*</span></label>
                            <div class="upload-dropzone" onclick="document.getElementById('primaryIdFront').click()">
                                <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                                <div class="upload-text">Upload Front Side</div>
                                <div class="upload-hint">JPG, PNG, or WEBP (Max 5MB)</div>
                                <input type="file" id="primaryIdFront" name="primaryIdFront" accept="image/*" required onchange="previewFile(this, 'previewPrimaryFront')">
                                <img id="previewPrimaryFront" class="preview-img" alt="Front Preview">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Back of ID <span class="text-danger">*</span></label>
                            <div class="upload-dropzone" onclick="document.getElementById('primaryIdBack').click()">
                                <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                                <div class="upload-text">Upload Back Side</div>
                                <div class="upload-hint">JPG, PNG, or WEBP (Max 5MB)</div>
                                <input type="file" id="primaryIdBack" name="primaryIdBack" accept="image/*" required onchange="previewFile(this, 'previewPrimaryBack')">
                                <img id="previewPrimaryBack" class="preview-img" alt="Back Preview">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Secondary ID Verification -->
                <div class="registration-card">
                    <div class="card-header-title">
                        <div class="header-icon-badge">
                            <i class="fa-solid fa-address-card"></i>
                        </div>
                        <div>
                            <h5>Secondary Identification</h5>
                            <small class="text-muted">Provides additional security and faster application approval</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="secondaryIdType" class="form-label">Secondary ID Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="secondaryIdType" name="secondaryIdType" required>
                                <option value="">Choose your secondary ID...</option>
                                <option value="school_id">Student / School ID</option>
                                <option value="tin">TIN ID</option>
                                <option value="philhealth">PhilHealth ID</option>
                                <option value="pagibig">Pag-IBIG / Loyalty Card</option>
                                <option value="barangay_id">Barangay ID / Clearance</option>
                                <option value="company_id">Company / Employee ID</option>
                                <option value="other">Other Secondary Document</option>
                            </select>
                        </div>

                        <div class="col-12" id="otherSecondaryIdTypeContainer" style="display: none;">
                            <label for="otherSecondaryIdType" class="form-label">Specify Secondary ID Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="otherSecondaryIdType" name="otherSecondaryIdType" placeholder="e.g., Company ID">
                        </div>

                        <!-- Secondary Front & Back Upload -->
                        <div class="col-md-6">
                            <label class="form-label">Front Image <span class="text-danger">*</span></label>
                            <div class="upload-dropzone" onclick="document.getElementById('secondaryIdFront').click()">
                                <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                                <div class="upload-text">Upload Front Side</div>
                                <div class="upload-hint">JPG, PNG, or WEBP (Max 5MB)</div>
                                <input type="file" id="secondaryIdFront" name="secondaryIdFront" accept="image/*" required onchange="previewFile(this, 'previewSecFront')">
                                <img id="previewSecFront" class="preview-img" alt="Secondary Front Preview">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Back Image <span class="text-danger">*</span></label>
                            <div class="upload-dropzone" onclick="document.getElementById('secondaryIdBack').click()">
                                <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                                <div class="upload-text">Upload Back Side</div>
                                <div class="upload-hint">JPG, PNG, or WEBP (Max 5MB)</div>
                                <input type="file" id="secondaryIdBack" name="secondaryIdBack" accept="image/*" required onchange="previewFile(this, 'previewSecBack')">
                                <img id="previewSecBack" class="preview-img" alt="Secondary Back Preview">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Security Verification Selfie -->
                <div class="registration-card">
                    <div class="card-header-title">
                        <div class="header-icon-badge">
                            <i class="fa-solid fa-camera-retro"></i>
                        </div>
                        <div>
                            <h5>Identity Verification Selfie</h5>
                            <small class="text-muted">Take a clear selfie while holding your Primary ID next to your face</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-3 mb-3 rounded-3" style="background-color: #fff8eb; border: 1px solid #ffe6be;">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fa-solid fa-circle-info text-warning mt-1"></i>
                                    <div style="font-size: 13px; color: #854d0e;">
                                        <strong>Photo Guidelines:</strong> Ensure good lighting. Both your face and all details on your ID must be sharp, clear, and unblurred.
                                    </div>
                                </div>
                            </div>

                            <div class="upload-dropzone" onclick="document.getElementById('selfieImage').click()" style="min-height: 160px;">
                                <i class="fa-solid fa-user-camera upload-icon"></i>
                                <div class="upload-text">Upload Selfie with Primary ID</div>
                                <div class="upload-hint">Take a clear picture holding your primary ID</div>
                                <input type="file" id="selfieImage" name="selfieImage" accept="image/*" required onchange="previewFile(this, 'previewSelfie')">
                                <img id="previewSelfie" class="preview-img" alt="Selfie Preview">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="text-center mt-4 mb-5">
                    <button type="submit" class="btn btn-submit-action">
                        Proceed to Address Details <i class="fa-solid fa-arrow-right"></i>
                    </button>
                    <div class="text-muted mt-2" style="font-size: 12px;">
                        <i class="fa-solid fa-lock me-1"></i> Your personal and identity information is securely encrypted.
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle 'Other' ID input fields
        document.getElementById('primaryIdType').addEventListener('change', function() {
            const otherContainer = document.getElementById('otherPrimaryIdTypeContainer');
            const otherInput = document.getElementById('otherPrimaryIdType');
            if (this.value === 'other') {
                otherContainer.style.display = 'block';
                otherInput.required = true;
            } else {
                otherContainer.style.display = 'none';
                otherInput.required = false;
            }
        });

        document.getElementById('secondaryIdType').addEventListener('change', function() {
            const otherContainer = document.getElementById('otherSecondaryIdTypeContainer');
            const otherInput = document.getElementById('otherSecondaryIdType');
            if (this.value === 'other') {
                otherContainer.style.display = 'block';
                otherInput.required = true;
            } else {
                otherContainer.style.display = 'none';
                otherInput.required = false;
            }
        });

        // Instant Image Preview Handler
        function previewFile(input, previewId) {
            const preview = document.getElementById(previewId);
            const parent = input.closest('.upload-dropzone');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    if (parent) {
                        const uploadText = parent.querySelector('.upload-text');
                        if (uploadText) {
                            uploadText.innerHTML = '<i class="fa-solid fa-check text-success me-1"></i> ' + input.files[0].name;
                        }
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>