<?php
session_start();
require_once '../config/database.php';
require_once '../includes/pdf_generator.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $requiredFields = ['movie_id', 'showtime_id', 'selected_seats', 'payment_method', 'total_price'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        $pdo->beginTransaction();

        $movieId = $_POST['movie_id'];
        $showtimeId = $_POST['showtime_id'];
        $selectedSeats = json_decode($_POST['selected_seats']);
        $paymentMethod = $_POST['payment_method'];
        $totalPrice = $_POST['total_price'];

        // Validate movie exists
        $stmt = $pdo->prepare("SELECT title, price FROM movies WHERE id = ? AND is_active = 1");
        $stmt->execute([$movieId]);
        $movie = $stmt->fetch();

        if (!$movie) {
            throw new Exception("Movie not found or inactive");
        }

        // Validate showtime exists
        $stmt = $pdo->prepare("SELECT showtime, studio_number FROM showtimes WHERE id = ?");
        $stmt->execute([$showtimeId]);
        $showtime = $stmt->fetch();

        if (!$showtime) {
            throw new Exception("Showtime not found");
        }

        // Validate seats are available with row locking
        $stmt = $pdo->prepare("
            SELECT seat_number 
            FROM seats 
            WHERE showtime_id = ? 
            AND seat_number IN (" . implode(',', array_fill(0, count($selectedSeats), '?')) . ")
            AND is_available = 1
            FOR UPDATE
        ");
        $params = array_merge([$showtimeId], $selectedSeats);
        $stmt->execute($params);
        $availableSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($availableSeats) !== count($selectedSeats)) {
            throw new Exception("One or more selected seats are no longer available");
        }

        // Create ticket record
        $stmt = $pdo->prepare("INSERT INTO tickets (user_id, movie_id, showtime_id, payment_method, total_price, payment_status) VALUES (?, ?, ?, ?, ?, 'paid')");
        $stmt->execute([
            $_SESSION['user_id'],
            $movieId,
            $showtimeId,
            $paymentMethod,
            $totalPrice
        ]);

        $ticketId = $pdo->lastInsertId();

        // Update seat availability
        $stmt = $pdo->prepare("UPDATE seats SET is_available = 0 WHERE showtime_id = ? AND seat_number = ?");
        foreach ($selectedSeats as $seat) {
            $stmt->execute([$showtimeId, $seat]);
        }

        // Generate ticket PDF
        $ticketData = [
            'ticket_id' => $ticketId,
            'movie_title' => $movie['title'],
            'show_date' => date('Y-m-d'),
            'showtime' => $showtime['showtime'],
            'studio' => 'Studio ' . $showtime['studio_number'],
            'seats' => $selectedSeats,
            'total_price' => $totalPrice
        ];

        $pdfFilename = generateTicketPDF($ticketData);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'ticket_id' => $ticketId,
            'pdf_filename' => $pdfFilename
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Payment Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?> 