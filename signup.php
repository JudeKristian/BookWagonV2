<?php
// Initialize session
session_start();

// Database configuration
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "bookwagon_db";

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("System error: Unable to connect to the database. Please try again later.");
}

// Include audit logger
require_once 'includes/audit_logger.php';

// Initialize variables
$firstName = $lastName = $email = $confirm_email = $password = $confirm_password = "";
$firstName_err = $lastName_err = $email_err = $confirm_email_err = $password_err = $confirm_password_err = $captcha_err = $signup_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Google reCAPTCHA Validation
    if (empty($_POST['g-recaptcha-response'])) {
        $captcha_err = "Please check the 'I'm not a robot' checkbox.";
    } else {
        $recaptcha_secret = '6LcndJktAAAAACj2R6ryalgZG2dBaKfeIfZ50Yp4';
        $recaptcha_response = $_POST['g-recaptcha-response'];
        
        // Make the API call to Google
        $recaptcha_url = "https://www.google.com/recaptcha/api/siteverify";
        
        // Use stream_context to make POST request securely
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
    
    // 2. Validate firstName
    if (empty(trim($_POST["firstname"]))) {
        $firstName_err = "Please enter your first name.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", trim($_POST["firstname"]))) {
        $firstName_err = "Name can only contain letters and spaces.";
    } else {
        $firstName = htmlspecialchars(trim($_POST["firstname"]), ENT_QUOTES, 'UTF-8');
    }
    
    // 3. Validate lastName
    if (empty(trim($_POST["lastname"]))) {
        $lastName_err = "Please enter your last name.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", trim($_POST["lastname"]))) {
        $lastName_err = "Name can only contain letters and spaces.";
    } else {
        $lastName = htmlspecialchars(trim($_POST["lastname"]), ENT_QUOTES, 'UTF-8');
    }
    
    // 4. Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email format.";
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
                $signup_err = "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
    
    // 5. Validate confirm email
    if (empty(trim($_POST["confirm_email"]))) {
        $confirm_email_err = "Please confirm your email.";
    } else {
        $confirm_email = trim($_POST["confirm_email"]);
        if (empty($email_err) && ($email != $confirm_email)) {
            $confirm_email_err = "Email addresses do not match.";
        }
    }
    
    // 6. Validate password (Complexity)
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 8) {
        $password_err = "Password must have at least 8 characters.";
    } elseif (!preg_match("/[A-Z]/", trim($_POST["password"]))) {
        $password_err = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match("/[0-9]/", trim($_POST["password"]))) {
        $password_err = "Password must contain at least one number.";
    } elseif (!preg_match("/[\W]/", trim($_POST["password"]))) {
        $password_err = "Password must contain at least one special character.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // 7. Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }
    
    // Check input errors before inserting into database
    if (empty($firstName_err) && empty($lastName_err) && empty($email_err) && empty($confirm_email_err) && empty($password_err) && empty($confirm_password_err) && empty($captcha_err)) {
        
        // Prepare an insert statement (Removed middle name)
        $sql = "INSERT INTO users (firstName, lastName, email, password) VALUES (?, ?, ?, ?)";
         
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssss", $param_firstname, $param_lastname, $param_email, $param_password);
            
            // Set parameters
            $param_firstname = $firstName;
            $param_lastname = $lastName;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                // Log successful registration
                log_activity($new_user_id, 'Registration', 'New user registered.');
                
                // Redirect to login page
                header("location: login.php");
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
    <title>BookWagon - Create Account</title>
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
        
        body { background-color: #f8f9fa; font-family: 'Arial', sans-serif; }
        .signup-container { max-width: 1000px; margin: 40px auto; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1); background: white; }
        .signup-row { display: flex; min-height: 650px; }
        .signup-image { flex: 0.8; background-color: var(--bg-cream); position: relative; overflow: hidden; }
        .signup-form { flex: 1.2; padding: 40px 50px; display: flex; flex-direction: column; justify-content: center; }
        .logo { text-align: center; margin-bottom: 20px; }
        .logo img { height: 100px; }
        h2 { font-weight: 600; text-align: center; margin-bottom: 30px; }
        .form-control { height: 50px; padding: 10px 15px; border-radius: 8px; border: 1px solid #ddd; }
        .form-group { margin-bottom: 20px; }
        .btn-signup { height: 50px; background-color: var(--primary-color); border: none; border-radius: 8px; font-weight: 600; font-size: 18px; margin-top: 10px; }
        .btn-signup:hover { background-color: #e09000; }
        .form-divider { text-align: center; position: relative; margin: 30px 0; }
        .form-divider::before, .form-divider::after { content: ""; position: absolute; top: 50%; width: 45%; height: 1px; background-color: #ddd; }
        .form-divider::before { left: 0; }
        .form-divider::after { right: 0; }
        .login-link { text-align: center; margin-top: 20px; }
        .text-primary { color: var(--primary-color) !important; }
        .alert { padding: 10px 15px; margin-bottom: 20px; border-radius: 8px; }
        .blob-blue { position: absolute; background-color: var(--accent-blue); border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%; z-index: 0; }
        .blob-blue-1 { width: 200px; height: 200px; top: -50px; left: -50px; }
        .blob-blue-2 { width: 300px; height: 300px; bottom: -100px; left: -50px; }
        .blob-blue-3 { width: 180px; height: 180px; top: 50%; right: -50px; transform: translateY(-50%); }
        .name-row { display: flex; gap: 15px; margin-bottom: 0; }
        .name-row .form-group { flex: 1; }
        
        /* Eye toggle styling */
        .input-group-text { background-color: white; border-left: none; cursor: pointer; }
        .password-field { border-right: none; }
        
        @media (max-width: 992px) { .signup-container { max-width: 90%; } }
        @media (max-width: 768px) {
            .signup-row { flex-direction: column; }
            .signup-image { display: none; }
            .signup-form { padding: 30px; }
            .name-row { flex-direction: column; gap: 0; }
            .name-row .form-group { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="signup-container">
            <div class="signup-row">
                <!-- Left side - Decorative illustration -->
                <div class="signup-image">
                    <div class="blob-blue blob-blue-1"></div>
                    <div class="blob-blue blob-blue-2"></div>
                    <div class="blob-blue blob-blue-3"></div>
                </div>
                
                <!-- Right side - Signup form -->
                <div class="signup-form">
                    <div class="logo">
                        <img src="images/logo.png" alt="BookWagon Logo">
                    </div>
                    
                    <h2>Create Account</h2>
                    
                    <?php 
                    if(!empty($signup_err)){
                        echo '<div class="alert alert-danger">' . $signup_err . '</div>';
                    }        
                    ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        
                        <!-- Name Row -->
                        <div class="name-row">
                            <div class="form-group">
                                <input type="text" name="firstname" class="form-control <?php echo (!empty($firstName_err)) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($firstName); ?>" placeholder="First Name">
                                <span class="invalid-feedback"><?php echo $firstName_err; ?></span>
                            </div>
                            
                            <div class="form-group">
                                <input type="text" name="lastname" class="form-control <?php echo (!empty($lastName_err)) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($lastName); ?>" placeholder="Last Name">
                                <span class="invalid-feedback"><?php echo $lastName_err; ?></span>
                            </div>
                        </div>
                        
                        <!-- Email -->
                        <div class="form-group">
                            <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($email); ?>" placeholder="Email Address">
                            <span class="invalid-feedback"><?php echo $email_err; ?></span>
                        </div>
                        
                        <!-- Confirm Email -->
                        <div class="form-group">
                            <input type="email" name="confirm_email" class="form-control <?php echo (!empty($confirm_email_err)) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($confirm_email); ?>" placeholder="Confirm Email Address">
                            <span class="invalid-feedback"><?php echo $confirm_email_err; ?></span>
                        </div>
                        
                        <!-- Password with Eye Toggle -->
                        <div class="form-group">
                            <div class="input-group">
                                <input type="password" id="password" name="password" class="form-control password-field <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                       placeholder="Password">
                                <span class="input-group-text" onclick="togglePassword('password', 'toggle-eye-1')">
                                    <i class="bi bi-eye-slash" id="toggle-eye-1"></i>
                                </span>
                            </div>
                            
                            <!-- Password Strength Meter -->
                            <div class="password-strength-container mt-2 d-none" id="password-strength-container" style="background: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #e9ecef;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="fw-bold text-muted">Password Strength: <span id="strength-text">Weak</span></small>
                                </div>
                                <div class="progress" style="height: 6px; margin-bottom: 10px;">
                                    <div class="progress-bar bg-danger transition-all" id="strength-bar" role="progressbar" style="width: 0%; transition: width 0.3s ease, background-color 0.3s ease;"></div>
                                </div>
                                <ul class="list-unstyled mb-0 small" style="columns: 2;">
                                    <li id="req-length" class="text-muted"><i class="bi bi-x-circle text-danger me-1"></i> 8+ characters</li>
                                    <li id="req-upper" class="text-muted"><i class="bi bi-x-circle text-danger me-1"></i> Uppercase</li>
                                    <li id="req-number" class="text-muted"><i class="bi bi-x-circle text-danger me-1"></i> Number</li>
                                    <li id="req-special" class="text-muted"><i class="bi bi-x-circle text-danger me-1"></i> Special char</li>
                                </ul>
                            </div>

                            <?php if(!empty($password_err)): ?>
                                <div class="text-danger small mt-1"><?php echo $password_err; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Confirm Password with Eye Toggle -->
                        <div class="form-group">
                            <div class="input-group">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control password-field <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" 
                                       placeholder="Confirm Password">
                                <span class="input-group-text" onclick="togglePassword('confirm_password', 'toggle-eye-2')">
                                    <i class="bi bi-eye-slash" id="toggle-eye-2"></i>
                                </span>
                            </div>
                            <?php if(!empty($confirm_password_err)): ?>
                                <div class="text-danger small mt-1"><?php echo $confirm_password_err; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="" id="termsCheck" required>
                            <label class="form-check-label" for="termsCheck" style="cursor: pointer;">
                                I agree to the <span class="text-primary text-decoration-underline" id="termsLink">Terms of Service and Privacy Policy</span>
                            </label>
                        </div>

                        <!-- Google reCAPTCHA -->
                        <div class="form-group d-flex flex-column align-items-center">
                            <div class="g-recaptcha" data-sitekey="6LcndJktAAAAAHfBOz7zcg5fxVW8dmJT9UoGO9jk"></div>
                            <?php if(!empty($captcha_err)): ?>
                                <div class="text-danger small mt-2 fw-bold text-center"><?php echo $captcha_err; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-signup w-100">Create Account</button>
                        </div>
                        
                        <div class="form-divider">Or</div>
                        
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

        // Password Strength Meter Logic
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const strengthContainer = document.getElementById('password-strength-container');
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            
            // Requirements elements
            const reqLength = document.getElementById('req-length');
            const reqUpper = document.getElementById('req-upper');
            const reqNumber = document.getElementById('req-number');
            const reqSpecial = document.getElementById('req-special');

            function updateIcon(element, isValid) {
                const icon = element.querySelector('i');
                if (isValid) {
                    icon.classList.remove('bi-x-circle', 'text-danger');
                    icon.classList.add('bi-check-circle-fill', 'text-success');
                    element.classList.remove('text-muted');
                    element.classList.add('text-success');
                } else {
                    icon.classList.remove('bi-check-circle-fill', 'text-success');
                    icon.classList.add('bi-x-circle', 'text-danger');
                    element.classList.remove('text-success');
                    element.classList.add('text-muted');
                }
            }

            passwordInput.addEventListener('input', function() {
                const val = passwordInput.value;
                
                // Show container if there is text, hide if empty
                if (val.length > 0) {
                    strengthContainer.classList.remove('d-none');
                } else {
                    strengthContainer.classList.add('d-none');
                }

                // Check rules
                const hasLength = val.length >= 8;
                const hasUpper = /[A-Z]/.test(val);
                const hasNumber = /[0-9]/.test(val);
                const hasSpecial = /[\W_]/.test(val);

                // Update UI icons
                updateIcon(reqLength, hasLength);
                updateIcon(reqUpper, hasUpper);
                updateIcon(reqNumber, hasNumber);
                updateIcon(reqSpecial, hasSpecial);

                // Calculate score
                let score = 0;
                if (hasLength) score++;
                if (hasUpper) score++;
                if (hasNumber) score++;
                if (hasSpecial) score++;

                // Update Progress Bar
                strengthBar.classList.remove('bg-danger', 'bg-warning', 'bg-info', 'bg-success');
                
                switch(score) {
                    case 0:
                    case 1:
                        strengthBar.style.width = '25%';
                        strengthBar.classList.add('bg-danger');
                        strengthText.textContent = 'Weak';
                        strengthText.className = 'text-danger';
                        break;
                    case 2:
                        strengthBar.style.width = '50%';
                        strengthBar.classList.add('bg-warning');
                        strengthText.textContent = 'Fair';
                        strengthText.className = 'text-warning';
                        break;
                    case 3:
                        strengthBar.style.width = '75%';
                        strengthBar.classList.add('bg-info');
                        strengthText.textContent = 'Good';
                        strengthText.className = 'text-info';
                        break;
                    case 4:
                        strengthBar.style.width = '100%';
                        strengthBar.classList.add('bg-success');
                        strengthText.textContent = 'Strong';
                        strengthText.className = 'text-success';
                        break;
                }
            });
        });
        // Terms Modal Logic
        document.addEventListener('DOMContentLoaded', function() {
            const termsCheck = document.getElementById('termsCheck');
            const termsLink = document.getElementById('termsLink');
            const termsBody = document.getElementById('termsBody');
            const btnAcceptTerms = document.getElementById('btnAcceptTerms');
            let termsModal;

            // Intercept checkbox click
            termsCheck.addEventListener('click', function(e) {
                if (!this.checked) {
                    // Allow unchecking without modal
                    return;
                }
                // Prevent checking and show modal instead
                e.preventDefault();
                if (!termsModal) termsModal = new bootstrap.Modal(document.getElementById('termsModal'));
                termsModal.show();
            });

            // Intercept link click
            termsLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!termsModal) termsModal = new bootstrap.Modal(document.getElementById('termsModal'));
                termsModal.show();
            });

            // Handle scroll
            if (termsBody) {
                termsBody.addEventListener('scroll', function() {
                    // Check if scrolled to bottom
                    if (termsBody.scrollTop + termsBody.clientHeight >= termsBody.scrollHeight - 15) {
                        btnAcceptTerms.disabled = false;
                        btnAcceptTerms.textContent = 'I Accept';
                    }
                });
            }

            // Handle accept
            if (btnAcceptTerms) {
                btnAcceptTerms.addEventListener('click', function() {
                    termsCheck.checked = true;
                    if (termsModal) termsModal.hide();
                });
            }
        });
    </script>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms of Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="termsBody" style="max-height: 60vh; overflow-y: auto;">
                    <p class="text-muted small mb-4">Last Updated: <?php echo date("F j, Y"); ?></p>
                    
                    <p class="small text-muted mb-3">Welcome to BookWagon! These Terms of Service ("Terms") govern your use of the BookWagon website, services, and applications (collectively, the "Service"). By accessing or using our Service, you agree to be bound by these Terms. If you disagree with any part of the Terms, you may not access the Service.</p>
                    
                    <h6 class="fw-bold mt-4">1. Accounts</h6>
                    <p class="small text-muted mb-3">When you create an account with us, you must provide accurate, complete, and up-to-date information. You are responsible for safeguarding the password you use to access the Service and for any activities or actions under your password. You agree not to disclose your password to any third party.</p>
                    
                    <h6 class="fw-bold mt-4">2. Book Listings and Transactions</h6>
                    <p class="small text-muted mb-2">BookWagon facilitates the buying, selling, and renting of books between users. When listing a book, you agree to:</p>
                    <ul class="small text-muted mb-3 ps-3">
                        <li>Provide accurate descriptions of the book's condition</li>
                        <li>Set fair prices that reflect the market value</li>
                        <li>Honor your commitments to sell or rent</li>
                    </ul>
                    <p class="small text-muted mb-2">As a buyer or renter, you agree to:</p>
                    <ul class="small text-muted mb-3 ps-3">
                        <li>Make payments promptly</li>
                        <li>Treat borrowed books with care and return them in the same condition</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-4">3. Service Fees</h6>
                    <p class="small text-muted mb-3">BookWagon may charge fees for certain aspects of the Service. You agree to pay all fees and charges associated with your account on a timely basis. All fees are non-refundable unless otherwise stated.</p>

                    <h6 class="fw-bold mt-4">4. Intellectual Property</h6>
                    <p class="small text-muted mb-3">The Service and its original content, features, and functionality are and will remain the exclusive property of BookWagon and its licensors. The Service is protected by copyright and trademark laws of the Philippines.</p>

                    <h6 class="fw-bold mt-4">5. User-Generated Content</h6>
                    <p class="small text-muted mb-2">You agree not to post content that:</p>
                    <ul class="small text-muted mb-3 ps-3">
                        <li>Is illegal, harmful, threatening, or defamatory</li>
                        <li>Infringes on intellectual property rights of others</li>
                        <li>Contains malware, viruses, or other malicious code</li>
                    </ul>

                    <h6 class="fw-bold mt-4">6. Limitation of Liability</h6>
                    <p class="small text-muted mb-3">In no event shall BookWagon or its affiliates be liable for any indirect, incidental, special, consequential or punitive damages resulting from your access to or use of the Service.</p>
                    
                    <h6 class="fw-bold mt-4">7. Termination</h6>
                    <p class="small text-muted mb-3">We may terminate or suspend your account immediately, without prior notice, for any reason whatsoever, including without limitation if you breach the Terms.</p>

                    <h6 class="fw-bold mt-4">8. Governing Law</h6>
                    <p class="small text-muted mb-3">These Terms shall be governed and construed in accordance with the laws of the Philippines.</p>
                    
                    <p class="mt-4 pt-3 border-top text-danger fw-bold text-center">By clicking Accept below, you agree to all these terms.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnAcceptTerms" disabled>Scroll to Accept</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>