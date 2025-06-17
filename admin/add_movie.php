<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle file upload
        $targetDir = "../uploads/posters/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . '_' . basename($_FILES["poster"]["name"]);
        $targetFile = $targetDir . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["poster"]["tmp_name"]);
        if ($check === false) {
            throw new Exception("File is not an image.");
        }

        // Check file size (5MB max)
        if ($_FILES["poster"]["size"] > 5000000) {
            throw new Exception("File is too large.");
        }

        // Allow certain file formats
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
            throw new Exception("Only JPG, JPEG & PNG files are allowed.");
        }

        if (move_uploaded_file($_FILES["poster"]["tmp_name"], $targetFile)) {
            // Insert movie into database
            $stmt = $pdo->prepare("INSERT INTO movies (title, poster_url, synopsis, duration, start_date, end_date, price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['title'],
                'uploads/posters/' . $fileName,
                $_POST['synopsis'],
                $_POST['duration'],
                $_POST['start_date'],
                $_POST['end_date'],
                $_POST['price']
            ]);

            $movieId = $pdo->lastInsertId();

            // Insert showtimes
            if (isset($_POST['showtimes']) && is_array($_POST['showtimes'])) {
                $stmt = $pdo->prepare("INSERT INTO showtimes (movie_id, studio_number, showtime) VALUES (?, ?, ?)");
                foreach ($_POST['showtimes'] as $showtime) {
                    // Create showtimes for each studio
                    for ($studio = 1; $studio <= 3; $studio++) {
                        $stmt->execute([$movieId, $studio, $showtime]);
                        
                        // Create seats for this showtime
                        $showtimeId = $pdo->lastInsertId();
                        $seatStmt = $pdo->prepare("INSERT INTO seats (showtime_id, seat_number) VALUES (?, ?)");
                        for ($seat = 1; $seat <= 40; $seat++) {
                            $seatStmt->execute([$showtimeId, $seat]);
                        }
                    }
                }
            }

            header('Location: dashboard.php');
            exit();
        } else {
            throw new Exception("Failed to upload file.");
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: dashboard.php');
        exit();
    }
}
?> 