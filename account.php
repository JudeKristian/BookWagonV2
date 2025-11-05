<?php
include("session.php");
include("connect.php");

$userType = $_SESSION['usertype'] ?? ''; // Change to lowercase 'usertype'
$firstName = $_SESSION['firstname'] ?? ''; // Change to lowercase 'firstname'
$lastName = $_SESSION['lastname'] ?? ''; // Change to lowercase 'lastname'
$email = $_SESSION['email'] ?? '';


if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['id'];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found in database
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();

// Handle profile picture upload
if (isset($_POST['upload_picture'])) {
    $targetDir = "uploads/profile_pictures/";
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = basename($_FILES["profile_picture"]["name"]);
    $targetFilePath = $targetDir . $userId . "_" . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    
    // Allow certain file formats
    $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
    if (in_array($fileType, $allowTypes)) {
        // Upload file to server
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFilePath)) {
            // Update profile picture path in database
            $updateStmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $updateStmt->bind_param("si", $targetFilePath, $userId);
            $updateStmt->execute();
            
            // Refresh page to show updated picture
            header("Location: account.php");
            exit();
        } else {
            $uploadError = "Sorry, there was an error uploading your file.";
        }
    } else {
        $uploadError = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
    }
}

// Handle personal information update
if (isset($_POST['update_personal_info'])) {
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $lastname = $_POST['lastname'];
    $username = $_POST['username'];
    $phone = $_POST['phone'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    $updateStmt = $conn->prepare("UPDATE users SET firstname = ?, middlename = ?, lastname = ?, username = ?, phone = ?, bio = ? WHERE id = ?");
    $updateStmt->bind_param("ssssssi", $firstname, $middlename, $lastname, $username, $phone, $bio, $userId);
    
    if ($updateStmt->execute()) {
        $_SESSION['firstname'] = $firstname;
        $_SESSION['lastname'] = $lastname;
        $_SESSION['username'] = $username;
        $_SESSION['success_message'] = "Personal information updated successfully!";
        header("Location: account.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating information: " . $conn->error;
    }
}

// Handle address update
if (isset($_POST['update_address'])) {
    $country = $_POST['country'] ?? '';
    $city_state = $_POST['city_state'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $tax_id = $_POST['tax_id'] ?? '';
    
    $updateStmt = $conn->prepare("UPDATE users SET country = ?, city_state = ?, postal_code = ?, tax_id = ? WHERE id = ?");
    $updateStmt->bind_param("ssssi", $country, $city_state, $postal_code, $tax_id, $userId);
    
    if ($updateStmt->execute()) {
        $_SESSION['success_message'] = "Address information updated successfully!";
        header("Location: account.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating address: " . $conn->error;
    }
}

// Default values if fields don't exist in database yet
$profilePicture = $user['profile_picture'] ?? 'images/default-profile.png';
$phone = $user['phone'] ?? '';
$bio = $user['bio'] ?? '';
$country = $user['country'] ?? '';
$city_state = $user['city_state'] ?? '';
$postal_code = $user['postal_code'] ?? '';
$tax_id = $user['tax_id'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - BookWagon</title>
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

        .dropdown-item {
            padding: 0.75rem 1.5rem;
            transition: background-color 0.2s;
        }

        .dropdown-item:hover {
            background-color: rgba(0,0,0,0.05);
        }

        .dropdown-item:active {
            background-color: rgba(0,0,0,0.1);
        }

        /* Fix dropdown toggle arrow */
        .dropdown-toggle::after {
            margin-left: 0.5em;
        }
        /* Header styles */
        .navbar {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .navbar-brand img {
            height: 60px;
        }
        .profile-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        .profile-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .profile-picture {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
        }
        .profile-picture-container {
            position: relative;
        }
        .edit-picture {
            position: absolute;
            bottom: 0;
            right: 15px;
            background: #fff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 5px rgba(0,0,0,0.2);
            cursor: pointer;
        }
        .user-info {
            flex-grow: 1;
        }
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .edit-button {
            color: #6c757d;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
        .info-row {
            margin-bottom: 20px;
        }
        .info-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .info-value {
            color: #343a40;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px 0;
            height: 100%;
        }
        
        .sidebar-link {
            display: block;
            padding: 12px 20px;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-link:hover, .sidebar-link.active {
            background-color: rgba(0, 123, 255, 0.05);
            color: #4a6cf7;
            border-left: 3px solid #4a6cf7;
        }
        
        .sidebar-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- Include Header -->
    <?php 
    if ($userType == 'user') {
        include("include/user_header.php");
    } elseif ($userType == 'seller') {
        include("include/seller_header.php");
    }
    ?>

    <div class="container py-5">
        <div class="row">
            <!-- Sidebar Column -->
            <div class="col-md-3 mb-4">
                <div class="sidebar">
                    <h4 class="px-4 mb-4">My Profile</h4>
                    <a href="account.php" class="sidebar-link active">
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
                        <i class="fa-solid fa-clock-rotate-left"></i> History
                    </a>
                </div>
            </div>
            
            <!-- Main Content Column -->
            <div class="col-md-9">
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['success_message']; 
                            unset($_SESSION['success_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                            echo $_SESSION['error_message']; 
                            unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Profile Header Card -->
                <div class="profile-card">
                    <div class="d-flex">
                        <div class="profile-picture-container">
                            <img src="<?php echo $profilePicture; ?>" alt="Profile Picture" class="profile-picture">
                            <div class="edit-picture" data-bs-toggle="modal" data-bs-target="#uploadPictureModal">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <div class="user-info ms-3">
                            <h2 class="mb-1"><?php echo $user['firstname'] . ' ' . $user['lastname']; ?></h2>
                            <p class="text-muted mb-1"><?php echo $bio ? $bio : 'BookWagon User'; ?></p>
                            <p class="text-muted mb-0"><?php echo $city_state ? $city_state . ', ' . $country : ''; ?></p>
                        </div>
                        <div>
                            <button class="edit-button" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="fas fa-pencil-alt"></i> Edit
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Personal Information Card -->
                <div class="profile-card">
                    <div class="section-title">
                        <h3>Personal Information</h3>
                        <button class="edit-button" data-bs-toggle="modal" data-bs-target="#editPersonalInfoModal">
                            <i class="fas fa-pencil-alt"></i> Edit
                        </button>
                    </div>
                    
                    <div class="row info-row">
                        <div class="col-md-6">
                            <div class="info-label">First Name</div>
                            <div class="info-value"><?php echo $user['firstname']; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Last Name</div>
                            <div class="info-value"><?php echo $user['lastname']; ?></div>
                        </div>
                    </div>
                    
                    <div class="row info-row">
                        <div class="col-md-6">
                            <div class="info-label">Email address</div>
                            <div class="info-value"><?php echo $user['email']; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo $phone ?: 'Not provided'; ?></div>
                        </div>
                    </div>
                    
                    <div class="row info-row">
                        <div class="col-md-6">
                            <div class="info-label">Username</div>
                            <div class="info-value"><?php echo $user['username'] ?: 'Not set'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Bio</div>
                            <div class="info-value"><?php echo $bio ?: 'Not provided'; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Address Card -->
                <div class="profile-card">
                    <div class="section-title">
                        <h3>Address</h3>
                        <button class="edit-button" data-bs-toggle="modal" data-bs-target="#editAddressModal">
                            <i class="fas fa-pencil-alt"></i> Edit
                        </button>
                    </div>
                    
                    <div class="row info-row">
                        <div class="col-md-6">
                            <div class="info-label">Country</div>
                            <div class="info-value"><?php echo $country ?: 'Not provided'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">City/State</div>
                            <div class="info-value"><?php echo $city_state ?: 'Not provided'; ?></div>
                        </div>
                    </div>
                    
                    <div class="row info-row">
                        <div class="col-md-6">
                            <div class="info-label">Postal Code</div>
                            <div class="info-value"><?php echo $postal_code ?: 'Not provided'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">TAX ID</div>
                            <div class="info-value"><?php echo $tax_id ?: 'Not provided'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Picture Modal -->
    <div class="modal fade" id="uploadPictureModal" tabindex="-1" aria-labelledby="uploadPictureModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadPictureModalLabel">Upload Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="account.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <?php if (isset($uploadError)): ?>
                            <div class="alert alert-danger"><?php echo $uploadError; ?></div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label">Select Image</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" required>
                            <div class="form-text">Supported formats: JPG, JPEG, PNG, GIF</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="upload_picture" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Personal Information Modal -->
    <div class="modal fade" id="editPersonalInfoModal" tabindex="-1" aria-labelledby="editPersonalInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPersonalInfoModalLabel">Edit Personal Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="account.php" method="post">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="firstname" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo $user['firstname']; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="middlename" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middlename" name="middlename" value="<?php echo $user['middlename']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="lastname" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo $user['lastname']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo $user['username']; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $phone; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo $bio; ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_personal_info" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Address Modal -->
    <div class="modal fade" id="editAddressModal" tabindex="-1" aria-labelledby="editAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAddressModalLabel">Edit Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="account.php" method="post">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country" value="<?php echo $country; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="city_state" class="form-label">City/State</label>
                                <input type="text" class="form-control" id="city_state" name="city_state" value="<?php echo $city_state; ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo $postal_code; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="tax_id" class="form-label">TAX ID</label>
                                <input type="text" class="form-control" id="tax_id" name="tax_id" value="<?php echo $tax_id; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_address" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script>
        // Do not initialize modals programmatically - use data attributes instead
        // Add event listener for edit picture button to avoid the backdrop error
        document.addEventListener('DOMContentLoaded', function() {
            var editPictureBtn = document.querySelector('.edit-picture');
            if (editPictureBtn) {
                editPictureBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var modalId = this.getAttribute('data-bs-target');
                    var myModal = document.querySelector(modalId);
                    if (myModal) {
                        myModal.classList.add('show');
                        myModal.style.display = 'block';
                        document.body.classList.add('modal-open');
                        
                        // Create backdrop manually
                        var backdrop = document.createElement('div');
                        backdrop.className = 'modal-backdrop fade show';
                        document.body.appendChild(backdrop);
                        
                        // Add close functionality to close buttons
                        var closeButtons = myModal.querySelectorAll('[data-bs-dismiss="modal"]');
                        closeButtons.forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                myModal.classList.remove('show');
                                myModal.style.display = 'none';
                                document.body.classList.remove('modal-open');
                                backdrop.remove();
                            });
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>