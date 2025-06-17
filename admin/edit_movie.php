<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Get movie ID from URL
$movieId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($movieId === 0) {
    $_SESSION['error'] = "Invalid movie ID";
    header('Location: dashboard.php');
    exit();
}

try {
    // Fetch movie details
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([$movieId]);
    $movie = $stmt->fetch();

    if (!$movie) {
        $_SESSION['error'] = "Movie not found";
        header('Location: dashboard.php');
        exit();
    }

    // Fetch existing showtimes
    $stmt = $pdo->prepare("SELECT * FROM showtimes WHERE movie_id = ? ORDER BY showtime");
    $stmt->execute([$movieId]);
    $existingShowtimes = $stmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = $_POST['title'];
        $synopsis = $_POST['synopsis'];
        $duration = (int)$_POST['duration'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $price = (float)$_POST['price'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Start transaction
        $pdo->beginTransaction();

        // Update movie details
        $stmt = $pdo->prepare("UPDATE movies SET 
            title = ?, 
            synopsis = ?, 
            duration = ?, 
            start_date = ?, 
            end_date = ?, 
            price = ?,
            is_active = ?
            WHERE id = ?");
        
        $stmt->execute([
            $title,
            $synopsis,
            $duration,
            $startDate,
            $endDate,
            $price,
            $isActive,
            $movieId
        ]);

        // Handle poster update if new file is uploaded
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['poster'];
            $fileName = time() . '_' . basename($file['name']);
            $targetPath = '../uploads/posters/' . $fileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Delete old poster if exists
                if ($movie['poster_url'] && file_exists('../uploads/posters/' . basename($movie['poster_url']))) {
                    unlink('../uploads/posters/' . basename($movie['poster_url']));
                }

                // Update poster URL in database
                $stmt = $pdo->prepare("UPDATE movies SET poster_url = ? WHERE id = ?");
                $stmt->execute(['uploads/posters/' . $fileName, $movieId]);
            }
        }

        // Handle showtimes update
        if (isset($_POST['showtimes']) && is_array($_POST['showtimes'])) {
            // Delete existing showtimes
            $stmt = $pdo->prepare("DELETE FROM showtimes WHERE movie_id = ?");
            $stmt->execute([$movieId]);

            // Insert new showtimes
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

        $pdo->commit();
        $_SESSION['success'] = "Movie updated successfully";
        header('Location: dashboard.php');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Movie - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Cinema Ticket System - Admin</a>
            <div class="d-flex">
                <a href="dashboard.php" class="btn btn-outline-light me-2">Dashboard</a>
                <a href="../logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Edit Movie</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['error'];
                                unset($_SESSION['error']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($movie['title']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Synopsis</label>
                                <textarea class="form-control" name="synopsis" rows="3" required><?php echo htmlspecialchars($movie['synopsis']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Duration (minutes)</label>
                                <input type="number" class="form-control" name="duration" value="<?php echo $movie['duration']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $movie['start_date']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo $movie['end_date']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Price</label>
                                <input type="number" class="form-control" name="price" value="<?php echo $movie['price']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Poster</label>
                                <?php if ($movie['poster_url']): ?>
                                    <div class="mb-2">
                                        <img src="../<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="Current Poster" style="max-width: 200px;">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="poster" accept="image/*">
                                <small class="text-muted">Leave empty to keep current poster</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Showtimes</label>
                                <?php
                                $existingTimes = array_map(function($showtime) {
                                    return $showtime['showtime'];
                                }, $existingShowtimes);
                                $existingTimes = array_unique($existingTimes);
                                ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="showtimes[]" value="10:00" <?php echo in_array('10:00', $existingTimes) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">10:00</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="showtimes[]" value="13:00" <?php echo in_array('13:00', $existingTimes) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">13:00</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="showtimes[]" value="16:00" <?php echo in_array('16:00', $existingTimes) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">16:00</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="showtimes[]" value="19:00" <?php echo in_array('19:00', $existingTimes) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">19:00</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?php echo $movie['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isActive">
                                        Active
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Movie</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 