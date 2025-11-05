<?php
include("../connect.php");
include("../session.php");

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to view your books'
    ]);
    exit;
}

$userId = $_SESSION['id'];

try {
    $query = "SELECT bs.*, u.firstname, u.lastname, u.profile_picture as user_avatar
              FROM book_swaps bs
              JOIN users u ON bs.user_id = u.id
              WHERE bs.user_id = ? AND bs.status != 'swapped'
              ORDER BY bs.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        if (empty($row['image_path'])) {
            $row['image_path'] = 'images/default-book.jpg';
        }
        
        if (empty($row['user_avatar'])) {
            $row['user_avatar'] = 'images/default-avatar.png';
        }
        
        $books[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'books' => $books
    ]);
    
} catch (Exception $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load your books'
    ]);
}
?>