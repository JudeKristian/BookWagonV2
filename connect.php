<?php
// Database configuration
$host = 'localhost';
$dbname = 'bookwagon_db';
$username = 'root';
$password = '';

// 1. Create MySQLi connection ($conn)
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("MySQLi Connection Error: " . $conn->connect_error);
    die("Database connection failed. Please try again later.");
}
$conn->set_charset("utf8");

// 2. Create PDO instance ($pdo)
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("PDO Connection Error: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}
?>
