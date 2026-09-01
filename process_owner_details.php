<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connect.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Normalize user ID from session
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
        
        if (!$userId) {
            throw new Exception("You must be logged in to apply as a seller. Please log in first.");
        }

        // Validate required fields
        $requiredFields = ['firstName', 'lastName', 'phoneNumber', 'email', 'primaryIdType', 'secondaryIdType'];
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(preg_replace('/(?<!\ )[A-Z]/', ' $0', $field)) . " is required.";
            }
        }
        
        if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        }
        
        if (!empty($_POST['phoneNumber']) && !preg_match("/^[0-9+\-\s()]*$/", $_POST['phoneNumber'])) {
            $errors[] = "Invalid phone number format.";
        }

        // Create user upload directory
        $baseUploadDir = "uploads/sellers/" . $userId;
        if (!file_exists($baseUploadDir)) {
            if (!mkdir($baseUploadDir, 0777, true)) {
                throw new Exception("Failed to create secure upload directory.");
            }
        }

        // Helper function for secure image upload
        function saveUploadedFile($fileInputName, $prefix, $uploadDir) {
            if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Missing or invalid upload for " . str_replace('_', ' ', $prefix) . ".");
            }

            $file = $_FILES[$fileInputName];
            
            // Check file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception(ucfirst(str_replace('_', ' ', $prefix)) . " exceeds maximum file size of 5MB.");
            }

            // Verify MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($mimeType, $allowedMimes) || !in_array($extension, $allowedExtensions)) {
                throw new Exception("Invalid file type for " . str_replace('_', ' ', $prefix) . ". Please upload JPG, PNG, or WEBP.");
            }

            $uniqueName = $prefix . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $extension;
            $destination = $uploadDir . "/" . $uniqueName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new Exception("Failed to save uploaded file for " . str_replace('_', ' ', $prefix) . ".");
            }

            return $destination;
        }

        // Process file uploads
        $primaryFront = saveUploadedFile('primaryIdFront', 'primary_front', $baseUploadDir);
        $primaryBack = saveUploadedFile('primaryIdBack', 'primary_back', $baseUploadDir);
        $secondaryFront = saveUploadedFile('secondaryIdFront', 'secondary_front', $baseUploadDir);
        $secondaryBack = saveUploadedFile('secondaryIdBack', 'secondary_back', $baseUploadDir);
        $selfieImg = saveUploadedFile('selfieImage', 'selfie', $baseUploadDir);

        // Store step 1 registration data in session
        $_SESSION['seller_registration'] = [
            'user_id' => $userId,
            'first_name' => trim($_POST['firstName']),
            'middle_name' => trim($_POST['middleInitial'] ?? ''),
            'last_name' => trim($_POST['lastName']),
            'email' => trim($_POST['email']),
            'phone' => trim($_POST['phoneNumber']),
            'social_media' => trim($_POST['socialMedia'] ?? ''),
            'primary_id_type' => ($_POST['primaryIdType'] === 'other' && !empty($_POST['otherPrimaryIdType'])) ? trim($_POST['otherPrimaryIdType']) : trim($_POST['primaryIdType']),
            'primary_id_front' => $primaryFront,
            'primary_id_back' => $primaryBack,
            'secondary_id_type' => ($_POST['secondaryIdType'] === 'other' && !empty($_POST['otherSecondaryIdType'])) ? trim($_POST['otherSecondaryIdType']) : trim($_POST['secondaryIdType']),
            'secondary_id_front' => $secondaryFront,
            'secondary_id_back' => $secondaryBack,
            'selfie_image' => $selfieImg,
            'step_1_completed' => true
        ];

        // Also set compatibility temp_seller_id
        $_SESSION['temp_seller_id'] = $userId;
        unset($_SESSION['error_message']);

        // Redirect to Step 2: Store & Address
        header("Location: seller_address.php");
        exit();

    } catch (Exception $e) {
        error_log("Error in process_owner_details.php: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
        header("Location: start_selling.php");
        exit();
    }
} else {
    header("Location: start_selling.php");
    exit();
}
?>