<?php
/**
 * Get feedbacks for a specific table
 * Returns JSON response with feedbacks array
 */

header('Content-Type: application/json');

session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get table_id from request
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($table_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid table ID']);
    exit();
}

try {
    // Fetch feedbacks for the specific table
    $stmt = $conn->prepare("
        SELECT 
            f.id,
            f.rating,
            f.comment,
            f.created_at,
            u.username,
            r.reservation_date,
            r.reservation_time
        FROM feedbacks f
        JOIN users u ON f.user_id = u.id
        JOIN reservations r ON f.reservation_id = r.id
        WHERE r.table_id = ?
        ORDER BY f.created_at DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $table_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $feedbacks = [];
    $total_rating = 0;
    
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = [
            'id' => $row['id'],
            'rating' => (int)$row['rating'],
            'comment' => $row['comment'],
            'username' => $row['username'],
            'reservation_date' => date('M d, Y', strtotime($row['reservation_date'])),
            'reservation_time' => date('h:i A', strtotime($row['reservation_time'])),
            'created_at' => date('M d, Y h:i A', strtotime($row['created_at']))
        ];
        $total_rating += (int)$row['rating'];
    }
    
    // Calculate average rating
    $average_rating = count($feedbacks) > 0 ? $total_rating / count($feedbacks) : 0;
    
    echo json_encode([
        'success' => true,
        'feedbacks' => $feedbacks,
        'average_rating' => $average_rating,
        'total_feedbacks' => count($feedbacks)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
