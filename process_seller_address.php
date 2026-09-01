<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
        
        if (!$userId) {
            throw new Exception("Your session has expired. Please log in again.");
        }

        if (!isset($_SESSION['seller_registration']) || empty($_SESSION['seller_registration']['step_1_completed'])) {
            throw new Exception("Step 1 details are missing. Please complete Step 1 first.");
        }

        $regData = $_SESSION['seller_registration'];

        // Validate required Step 2 fields
        $shopName = trim($_POST['shop_name'] ?? '');
        $sellerType = trim($_POST['seller_type'] ?? 'Individual Book Owner');
        $country = trim($_POST['country'] ?? 'Philippines');
        $province = trim($_POST['province'] ?? 'Davao del Sur');
        $city = trim($_POST['city'] ?? 'Davao City');
        $barangay = trim($_POST['barangay'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $detailedAddress = trim($_POST['detailed_address'] ?? '');

        if (empty($shopName) || empty($barangay) || empty($postalCode) || empty($detailedAddress)) {
            throw new Exception("Please fill in all required address and shop fields.");
        }

        $fullLocation = trim($barangay . ', ' . $city . ', ' . $province);

        // Check if an entry in sellers table already exists for this user
        $checkStmt = $pdo->prepare("SELECT id FROM sellers WHERE user_id = :user_id");
        $checkStmt->execute([':user_id' => $userId]);
        $existingSeller = $checkStmt->fetch();

        if ($existingSeller) {
            // Update existing seller record
            $sql = "UPDATE sellers SET 
                        shop_name = :shop_name,
                        seller_type = :seller_type,
                        business_name = :business_name,
                        first_name = :first_name,
                        middle_name = :middle_name,
                        last_name = :last_name,
                        location = :location,
                        address = :address,
                        zip_code = :zip_code,
                        business_email = :business_email,
                        business_phone = :business_phone,
                        social_media = :social_media,
                        primary_id_type = :primary_id_type,
                        primary_id_front = :primary_id_front,
                        primary_id_back = :primary_id_back,
                        secondary_id_type = :secondary_id_type,
                        secondary_id_front = :secondary_id_front,
                        secondary_id_back = :secondary_id_back,
                        selfie_image = :selfie_image,
                        status = 'pending',
                        updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':shop_name' => $shopName,
                ':seller_type' => $sellerType,
                ':business_name' => $shopName,
                ':first_name' => $regData['first_name'],
                ':middle_name' => $regData['middle_name'],
                ':last_name' => $regData['last_name'],
                ':location' => $fullLocation,
                ':address' => $detailedAddress,
                ':zip_code' => $postalCode,
                ':business_email' => $regData['email'],
                ':business_phone' => $regData['phone'],
                ':social_media' => $regData['social_media'],
                ':primary_id_type' => $regData['primary_id_type'],
                ':primary_id_front' => $regData['primary_id_front'],
                ':primary_id_back' => $regData['primary_id_back'],
                ':secondary_id_type' => $regData['secondary_id_type'],
                ':secondary_id_front' => $regData['secondary_id_front'],
                ':secondary_id_back' => $regData['secondary_id_back'],
                ':selfie_image' => $regData['selfie_image'],
                ':id' => $existingSeller['id']
            ]);
        } else {
            // Insert new seller record
            $sql = "INSERT INTO sellers (
                        user_id, shop_name, seller_type, business_name, first_name, middle_name, last_name,
                        location, address, zip_code, business_email, business_phone, social_media,
                        primary_id_type, primary_id_front, primary_id_back,
                        secondary_id_type, secondary_id_front, secondary_id_back,
                        selfie_image, status, created_at, updated_at
                    ) VALUES (
                        :user_id, :shop_name, :seller_type, :business_name, :first_name, :middle_name, :last_name,
                        :location, :address, :zip_code, :business_email, :business_phone, :social_media,
                        :primary_id_type, :primary_id_front, :primary_id_back,
                        :secondary_id_type, :secondary_id_front, :secondary_id_back,
                        :selfie_image, 'pending', NOW(), NOW()
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':shop_name' => $shopName,
                ':seller_type' => $sellerType,
                ':business_name' => $shopName,
                ':first_name' => $regData['first_name'],
                ':middle_name' => $regData['middle_name'],
                ':last_name' => $regData['last_name'],
                ':location' => $fullLocation,
                ':address' => $detailedAddress,
                ':zip_code' => $postalCode,
                ':business_email' => $regData['email'],
                ':business_phone' => $regData['phone'],
                ':social_media' => $regData['social_media'],
                ':primary_id_type' => $regData['primary_id_type'],
                ':primary_id_front' => $regData['primary_id_front'],
                ':primary_id_back' => $regData['primary_id_back'],
                ':secondary_id_type' => $regData['secondary_id_type'],
                ':secondary_id_front' => $regData['secondary_id_front'],
                ':secondary_id_back' => $regData['secondary_id_back'],
                ':selfie_image' => $regData['selfie_image']
            ]);
        }

        // Also update users table phone and address for user profile convenience
        $updateUser = $pdo->prepare("UPDATE users SET phone = :phone, address = :address, city = :city, postal_code = :postal_code WHERE id = :user_id");
        $updateUser->execute([
            ':phone' => $regData['phone'],
            ':address' => $detailedAddress,
            ':city' => $city,
            ':postal_code' => $postalCode,
            ':user_id' => $userId
        ]);

        // Clean up temporary session data
        unset($_SESSION['seller_registration']);
        unset($_SESSION['temp_seller_id']);
        unset($_SESSION['error_message']);

        // Mark as submitted
        $_SESSION['seller_submitted'] = true;

        // Redirect to Step 3: Success & Review Status
        header("Location: seller_success.php");
        exit();

    } catch (Exception $e) {
        error_log("Error in process_seller_address.php: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: seller_address.php");
        exit();
    }
} else {
    header("Location: start_selling.php");
    exit();
}
?>