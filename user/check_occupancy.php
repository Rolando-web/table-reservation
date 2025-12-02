<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$table_id = $_GET['table_id'] ?? null;
$reservation_date = $_GET['date'] ?? null;
$reservation_time = $_GET['time'] ?? null;

if (!$table_id || !$reservation_date || !$reservation_time) {
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

// Check if table is occupied for the selected date and time
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations 
                        WHERE table_id = ? 
                        AND reservation_date = ? 
                        AND reservation_time = ? 
                        AND status IN ('pending', 'confirmed')");
$stmt->bind_param("iss", $table_id, $reservation_date, $reservation_time);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$is_occupied = $row['count'] > 0;

echo json_encode([
    'occupied' => $is_occupied,
    'message' => $is_occupied ? 'This table is already booked for the selected time.' : 'Table is available.'
]);
