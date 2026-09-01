<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes redirect to admin dashboard
if(isset($_SESSION["admin_loggedin"]) && $_SESSION["admin_loggedin"] === true){
    header("location: admin_dashboard.php");
    exit;
}

// Include database connection
require_once "db_connect.php";
require_once "../includes/audit_logger.php";

// Define variables and set to empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
 
    // Check if username is empty
    if(empty(trim($_POST["username"]))){
        $_SESSION["login_err"] = "Please enter username.";
        header("location: index.php");
        exit;
    } else{
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))){
        $_SESSION["login_err"] = "Please enter your password.";
        header("location: index.php");
        exit;
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err)){
        // Prepare a select statement
        $sql = "SELECT id, username, password FROM admin WHERE username = ?";
        
        if($stmt = $conn->prepare($sql)){
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if($stmt->execute()){
                // Store result
                $stmt->store_result();
                
                // Check if username exists, if yes then verify password
                if($stmt->num_rows == 1){                    
                    // Bind result variables
                    $stmt->bind_result($id, $username, $db_password);
                    if($stmt->fetch()){
                        if(password_verify($password, $db_password) || $password === $db_password){
                            // If plaintext, upgrade hash in background
                            if($password === $db_password) {
                                $newHash = password_hash($password, PASSWORD_DEFAULT);
                                $upStmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
                                $upStmt->bind_param("si", $newHash, $id);
                                $upStmt->execute();
                                $upStmt->close();
                            }
                            
                            // Password is correct, so start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["admin_loggedin"] = true;
                            $_SESSION["admin_id"] = $id;
                            $_SESSION["admin_username"] = $username;                            
                            
                            // Log admin login
                            log_activity($id, 'Admin Login', 'Admin successfully logged in.');
                            
                            // Redirect user to admin dashboard
                            header("location: admin_dashboard.php");
                        } else{
                            // Password is not valid, display a generic error message
                            $_SESSION["login_err"] = "Invalid username or password.";
                            header("location: index.php");
                            exit;
                        }
                    }
                } else{
                    // Username doesn't exist, display a generic error message
                    $_SESSION["login_err"] = "Invalid username or password.";
                    header("location: index.php");
                    exit;
                }
            } else{
                $_SESSION["login_err"] = "Oops! Something went wrong. Please try again later.";
                header("location: index.php");
                exit;
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}
?>