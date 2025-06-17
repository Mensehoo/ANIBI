<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Fetch statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'user'");
$totalUsers = $stmt->fetch()['total_users'];

$stmt = $pdo->query("SELECT COUNT(*) as total_tickets FROM tickets WHERE payment_status = 'paid'");
$totalTickets = $stmt->fetch()['total_tickets'];

$stmt = $pdo->query("SELECT SUM(total_price) as total_revenue FROM tickets WHERE payment_status = 'paid'");
$totalRevenue = $stmt->fetch()['total_revenue'] ?? 0;

// Get top selling movies
$stmt = $pdo->query("
    SELECT m.title, COUNT(t.id) as ticket_count, SUM(t.total_price) as revenue
    FROM movies m
    LEFT JOIN tickets t ON m.id = t.movie_id AND t.payment_status = 'paid'
    GROUP BY m.id
    ORDER BY ticket_count DESC
    LIMIT 5
");
$topMovies = $stmt->fetchAll();

// Handle movie status toggle
if (isset($_POST['toggle_movie'])) {
    $movieId = $_POST['movie_id'];
    $stmt = $pdo->prepare("UPDATE movies SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$movieId]);
    header('Location: dashboard.php');
    exit();
}

// Fetch all movies
$stmt = $pdo->query("SELECT * FROM movies ORDER BY created_at DESC");
$movies = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ANIBI - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2C3E50;
            --secondary-color: #E74C3C;
            --accent-color: #3498DB;
            --background-color: #F8F9FA;
            --text-color: #2C3E50;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), #34495E);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 700;
            color: white !important;
            letter-spacing: 1px;
        }

        .navbar-brand span {
            color: var(--secondary-color);
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-card h3 {
            font-size: 1.2rem;
            color: var(--text-color);
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .stat-card h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }

        .movie-poster {
            height: 300px;
            object-fit: cover;
            border-radius: 10px;
        }

        .card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--accent-color);
            border: none;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--secondary-color);
            border: none;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--primary-color);
            position: relative;
        }

        .section-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 4px;
            background: var(--secondary-color);
            margin: 10px 0;
            border-radius: 2px;
        }

        .table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            font-weight: 500;
            border: none;
        }

        .table tbody td {
            vertical-align: middle;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #34495E);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .form-control {
            border-radius: 8px;
            padding: 0.8rem 1rem;
            border: 1px solid #dee2e6;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">ANI<span>BI</span> Admin</a>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <h2 class="text-primary"><?php echo $totalUsers; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3>Total Tickets Sold</h3>
                    <h2 class="text-success"><?php echo $totalTickets; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <h2 class="text-info">Rp <?php echo number_format($totalRevenue, 0, ',', '.'); ?></h2>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Top Selling Movies</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Movie Title</th>
                                        <th>Tickets Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topMovies as $movie): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($movie['title']); ?></td>
                                        <td><?php echo $movie['ticket_count']; ?></td>
                                        <td>Rp <?php echo number_format($movie['revenue'], 0, ',', '.'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title mb-0">Movie Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMovieModal">
                <i class="fas fa-plus"></i> Add New Movie
            </button>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($movies as $movie): ?>
            <div class="col">
                <div class="card h-100">
                    <img src="../<?php echo htmlspecialchars($movie['poster_url']); ?>" class="card-img-top movie-poster" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($movie['title']); ?></h5>
                        <p class="card-text">
                            <small class="text-muted">
                                Duration: <?php echo $movie['duration']; ?> minutes<br>
                                Price: Rp <?php echo number_format($movie['price'], 0, ',', '.'); ?><br>
                                Showing: <?php echo $movie['start_date']; ?> to <?php echo $movie['end_date']; ?>
                            </small>
                        </p>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="movie_id" value="<?php echo $movie['id']; ?>">
                            <button type="submit" name="toggle_movie" class="btn btn-sm <?php echo $movie['is_active'] ? 'btn-danger' : 'btn-success'; ?>">
                                <?php echo $movie['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                        <td>
                            <a href="edit_movie.php?id=<?php echo $movie['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            <button class="btn btn-sm btn-danger" onclick="deleteMovie(<?php echo $movie['id']; ?>)">Delete</button>
                        </td>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Movie Modal -->
    <div class="modal fade" id="addMovieModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Movie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="add_movie.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Poster</label>
                            <input type="file" class="form-control" name="poster" accept="image/*" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Synopsis</label>
                            <textarea class="form-control" name="synopsis" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Duration (minutes)</label>
                                    <input type="number" class="form-control" name="duration" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Price</label>
                                    <input type="number" class="form-control" name="price" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Showtimes</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="showtimes[]" value="10:00">
                                <label class="form-check-label">10:00</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="showtimes[]" value="13:00">
                                <label class="form-check-label">13:00</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="showtimes[]" value="16:00">
                                <label class="form-check-label">16:00</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="showtimes[]" value="19:00">
                                <label class="form-check-label">19:00</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Movie</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 