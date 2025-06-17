<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit();
}

if (!isset($_GET['showtime_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Showtime ID is required'
    ]);
    exit();
}

try {
    $showtimeId = $_GET['showtime_id'];
    
    // Use SELECT FOR UPDATE to lock the rows
    $stmt = $pdo->prepare("
        SELECT seat_number 
        FROM seats 
        WHERE showtime_id = ? AND is_available = 0
        FOR UPDATE
    ");
    $stmt->execute([$showtimeId]);
    $occupiedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'occupied_seats' => $occupiedSeats
    ]);

} catch (Exception $e) {
    error_log("Error checking occupied seats: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error checking seat availability'
    ]);
} 