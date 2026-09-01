<?php
/**
 * Simple helper functions for file uploads (Debug Version)
 */

/**
 * Upload a book cover image
 * 
 * @param array $file The $_FILES array element
 * @param string $upload_dir The directory to upload to (with trailing slash)
 * @return string|bool The path to the uploaded image or false on failure
 */
function upload_book_cover($file, $upload_dir = 'uploads/covers/') {
    // Debug info - write to error log
    error_log("Starting file upload process for: " . $file['name']);
    
    // Check if uploads directory exists, if not create it
    if (!file_exists($upload_dir)) {
        error_log("Creating directory: " . $upload_dir);
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create directory: " . $upload_dir);
            return false;
        }
        // Set permissions
        chmod($upload_dir, 0777);
        error_log("Directory created with 777 permissions: " . $upload_dir);
    }
    
    // Debug file upload status
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            error_log("File upload status: OK");
            break;
        case UPLOAD_ERR_INI_SIZE:
            error_log("File upload error: The uploaded file exceeds the upload_max_filesize directive in php.ini");
            return false;
        case UPLOAD_ERR_FORM_SIZE:
            error_log("File upload error: The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form");
            return false;
        case UPLOAD_ERR_PARTIAL:
            error_log("File upload error: The uploaded file was only partially uploaded");
            return false;
        case UPLOAD_ERR_NO_FILE:
            error_log("File upload error: No file was uploaded");
            return false;
        case UPLOAD_ERR_NO_TMP_DIR:
            error_log("File upload error: Missing temporary folder");
            return false;
        case UPLOAD_ERR_CANT_WRITE:
            error_log("File upload error: Failed to write file to disk");
            return false;
        case UPLOAD_ERR_EXTENSION:
            error_log("File upload error: A PHP extension stopped the file upload");
            return false;
        default:
            error_log("File upload error: Unknown error code - " . $file['error']);
            return false;
    }
    
    // Generate a unique filename (with timestamp for additional uniqueness)
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // SECURE UPLOAD VALIDATION
    // 1. Validate file extension
    $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    if (!in_array($file_extension, $allowed_types)) {
        error_log("Security Error: Invalid file type uploaded - " . $file_extension);
        return false;
    }
    
    // 2. Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        error_log("Security Error: File exceeds maximum allowed size of 5MB.");
        return false;
    }
    
    // 3. Validate MIME type to prevent fake extensions
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed_mimes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($mime_type, $allowed_mimes)) {
            error_log("Security Error: Invalid MIME type - " . $mime_type);
            return false;
        }
    }
    $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
    $destination = $upload_dir . $new_filename;
    
    error_log("Attempting to upload to: " . $destination);
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        error_log("File successfully uploaded to: " . $destination);
        // Set file permissions to ensure it's readable
        chmod($destination, 0644);
        return $destination;
    } else {
        error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $destination);
        error_log("PHP error: " . error_get_last()['message']);
        return false;
    }
}
?>