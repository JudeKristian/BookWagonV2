<?php
/**
 * Audit Logger Utility
 * Records important user activities to the audit_logs table.
 */

function log_activity($user_id, $activity, $details = "") {
    // Determine which type of connection to use based on what's available
    global $conn, $pdo;
    
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            // Using PDO (e.g., from connect.php)
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, activity, details) VALUES (:user_id, :activity, :details)");
            $stmt->execute([
                ':user_id' => $user_id,
                ':activity' => $activity,
                ':details' => $details
            ]);
        } elseif (isset($conn) && $conn instanceof mysqli) {
            // Using mysqli (e.g., from login.php/signup.php)
            $sql = "INSERT INTO audit_logs (user_id, activity, details) VALUES (?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("iss", $user_id, $activity, $details);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            // No DB connection found, log to file as fallback
            error_log("Audit Log [Fallback]: User $user_id - $activity - $details");
        }
    } catch (Exception $e) {
        // Silently fail but log to PHP error log to prevent breaking user flow
        error_log("Failed to insert audit log: " . $e->getMessage());
    }
}
?>
