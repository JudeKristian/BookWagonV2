<?php
session_start();
include("../connect.php");

// Set response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to search users'
    ]);
    exit;
}

$userId = $_SESSION['id'];
$searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';

// Validate input
if (empty($searchTerm) || strlen($searchTerm) < 2) {
    echo json_encode([
        'success' => false,
        'message' => 'Search term must be at least 2 characters'
    ]);
    exit;
}

// Search for users
$query = "SELECT id, firstname, lastname, username, profile_picture 
          FROM users 
          WHERE id != ? 
          AND (firstname LIKE ? OR lastname LIKE ? OR username LIKE ?) 
          ORDER BY firstname 
          LIMIT 10";

$searchParam = "%{$searchTerm}%";
$stmt = $conn->prepare($query);
$stmt->bind_param("isss", $userId, $searchParam, $searchParam, $searchParam);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    // Verify profile_picture exists
    if (!empty($row['profile_picture']) && !file_exists("../{$row['profile_picture']}")) {
        $row['profile_picture'] = null;
    }
    $users[] = $row;
}

echo json_encode([
    'success' => true,
    'users' => $users,
    'count' => count($users)
]); 