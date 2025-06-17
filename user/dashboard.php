<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../index.php');
    exit();
}

// Fetch active movies
$stmt = $pdo->query("SELECT * FROM movies WHERE is_active = 1 ORDER BY start_date DESC");
$movies = $stmt->fetchAll();

// Fetch showtimes for the first movie (temporary solution)
$stmt = $pdo->prepare("SELECT * FROM showtimes WHERE movie_id = ? ORDER BY showtime");
$stmt->execute([$movies[0]['id']]);
$showtimes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ANIBI - Cinema Experience</title>
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

        .movie-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .movie-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .movie-poster {
            height: 400px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .movie-card:hover .movie-poster {
            transform: scale(1.05);
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
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

        .btn-primary {
            background: var(--accent-color);
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .seats-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .seats {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .screen {
            background: #fff;
            color: #333;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .seat-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .seat-row-label {
            width: 30px;
            text-align: center;
            font-weight: bold;
            color: #666;
        }

        .seat {
            width: 35px;
            height: 35px;
            background: #444;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .seat:hover {
            background: #666;
        }

        .seat.selected {
            background: #4CAF50;
        }

        .seat.occupied {
            background: #f44336;
            cursor: not-allowed;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--primary-color);
            text-align: center;
            position: relative;
        }

        .section-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 4px;
            background: var(--secondary-color);
            margin: 10px auto;
            border-radius: 2px;
        }

        .payment-details {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .form-check-input:checked {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">ANI<span>BI</span></a>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h2 class="section-title">Now Showing</h2>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
            <?php foreach ($movies as $movie): ?>
            <div class="col">
                <div class="card movie-card h-100" data-bs-toggle="modal" data-bs-target="#movieModal" 
                     data-movie-id="<?php echo $movie['id']; ?>"
                     data-movie-title="<?php echo htmlspecialchars($movie['title']); ?>"
                     data-movie-poster="../<?php echo htmlspecialchars($movie['poster_url']); ?>"
                     data-movie-synopsis="<?php echo htmlspecialchars($movie['synopsis']); ?>"
                     data-movie-duration="<?php echo $movie['duration']; ?>"
                     data-movie-start="<?php echo $movie['start_date']; ?>"
                     data-movie-end="<?php echo $movie['end_date']; ?>"
                     data-movie-price="<?php echo $movie['price']; ?>">
                    <img src="../<?php echo htmlspecialchars($movie['poster_url']); ?>" class="card-img-top movie-poster" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($movie['title']); ?></h5>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Movie Modal -->
    <div class="modal fade" id="movieModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <img src="" class="img-fluid movie-poster" alt="">
                        </div>
                        <div class="col-md-8">
                            <p class="synopsis"></p>
                            <p><strong>Duration:</strong> <span class="duration"></span> minutes</p>
                            <p><strong>Showing:</strong> <span class="showing-dates"></span></p>
                            <p><strong>Price:</strong> Rp <span class="price"></span></p>
                            <button class="btn btn-primary book-ticket" data-movie-id="">Book Ticket</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Seat Selection Modal -->
    <div class="modal fade" id="seatModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Seats</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Studio</label>
                        <select class="form-select" id="studioSelect">
                            <option value="1">Studio 1</option>
                            <option value="2">Studio 2</option>
                            <option value="3">Studio 3</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Showtime</label>
                        <select class="form-select" id="showtimeSelect">
                            <?php if (empty($showtimes)): ?>
                            <option value="">No showtimes available</option>
                            <?php else: ?>
                                <?php foreach ($showtimes as $showtime): ?>
                                <option value="<?php echo $showtime['id']; ?>" data-studio="<?php echo $showtime['studio_number']; ?>"><?php echo $showtime['showtime']; ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="seat-layout text-center">
                        <div class="screen mb-4">SCREEN</div>
                        <div class="seats"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="proceedToPayment">Proceed to Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6>Selected Seats: <span id="selectedSeatsList"></span></h6>
                        <h6>Total Price: Rp <span id="totalPrice"></span></h6>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Payment Method</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="paymentMethod" id="virtualAccount" value="virtual_account" checked>
                            <label class="form-check-label" for="virtualAccount">
                                Virtual Account
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="paymentMethod" id="qris" value="qris">
                            <label class="form-check-label" for="qris">
                                QRIS
                            </label>
                        </div>
                    </div>
                    <div id="virtualAccountDetails" class="payment-details">
                        <p>Virtual Account Number: <strong>1234567890</strong></p>
                    </div>
                    <div id="qrisDetails" class="payment-details d-none">
                        <div class="text-center">
                            <img src="../assets/images/QRian.jpg" alt="QRIS Code" class="img-fluid mb-3" style="max-width: 300px;">
                            <p class="mb-0">Scan QR code above to pay</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="confirmPayment">Confirm Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Successful!</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Your ticket has been generated successfully.</p>
                    <p>Click the button below to download your ticket.</p>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-primary" id="downloadTicket">Download Ticket</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let selectedSeats = new Set();

        // Movie Modal
        const movieModal = document.getElementById('movieModal');
        movieModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const modal = this;
            
            modal.querySelector('.modal-title').textContent = button.dataset.movieTitle;
            modal.querySelector('.movie-poster').src = button.dataset.moviePoster;
            modal.querySelector('.synopsis').textContent = button.dataset.movieSynopsis;
            modal.querySelector('.duration').textContent = button.dataset.movieDuration;
            modal.querySelector('.showing-dates').textContent = `${button.dataset.movieStart} to ${button.dataset.movieEnd}`;
            modal.querySelector('.price').textContent = button.dataset.moviePrice;
            modal.querySelector('.book-ticket').dataset.movieId = button.dataset.movieId;
        });

        // Seat Selection
        const seatModal = document.getElementById('seatModal');
        const seatsContainer = document.querySelector('.seats');

        function generateSeats() {
            const seatsContainer = document.querySelector('.seats');
            seatsContainer.innerHTML = ''; // Clear existing seats

            // Add screen
            const screen = document.createElement('div');
            screen.className = 'screen';
            screen.textContent = 'SCREEN';
            seatsContainer.appendChild(screen);

            // Generate seats in rows (A-J)
            const rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
            const seatsPerRow = 8;

            rows.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'seat-row';
                
                // Add row label
                const rowLabel = document.createElement('div');
                rowLabel.className = 'seat-row-label';
                rowLabel.textContent = row;
                rowDiv.appendChild(rowLabel);

                // Generate seats for this row
                for (let i = 1; i <= seatsPerRow; i++) {
                    const seat = document.createElement('div');
                    seat.className = 'seat';
                    seat.textContent = i;
                    seat.dataset.seat = `${row}${i}`;
                    
                    seat.addEventListener('click', function() {
                        if (!this.classList.contains('occupied')) {
                            this.classList.toggle('selected');
                            if (this.classList.contains('selected')) {
                                selectedSeats.add(this.dataset.seat);
                            } else {
                                selectedSeats.delete(this.dataset.seat);
                            }
                        }
                    });
                    
                    rowDiv.appendChild(seat);
                }
                
                seatsContainer.appendChild(rowDiv);
            });

            // Fetch and mark occupied seats
            const showtimeId = document.getElementById('showtimeSelect').value;
            fetch(`check_occupied_seats.php?showtime_id=${showtimeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.occupied_seats.forEach(seatNumber => {
                            const seat = document.querySelector(`.seat[data-seat="${seatNumber}"]`);
                            if (seat) {
                                seat.classList.add('occupied');
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching occupied seats:', error);
                    alert('Error loading seat information. Please try again.');
                });
        }

        document.querySelector('.book-ticket').addEventListener('click', () => {
            const movieModal = bootstrap.Modal.getInstance(document.getElementById('movieModal'));
            movieModal.hide();
            generateSeats();
            const seatModal = new bootstrap.Modal(document.getElementById('seatModal'));
            seatModal.show();
        });

        // Update payment modal with total price
        document.getElementById('proceedToPayment').addEventListener('click', () => {
            if (selectedSeats.size === 0) {
                alert('Please select at least one seat');
                return;
            }
            
            const moviePrice = parseFloat(document.querySelector('.price').textContent);
            const totalPrice = moviePrice * selectedSeats.size;
            
            document.getElementById('selectedSeatsList').textContent = Array.from(selectedSeats).join(', ');
            document.getElementById('totalPrice').textContent = totalPrice.toLocaleString('id-ID');
            
            const seatModal = bootstrap.Modal.getInstance(document.getElementById('seatModal'));
            seatModal.hide();
            const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            paymentModal.show();
        });

        // Handle payment confirmation
        document.getElementById('confirmPayment').addEventListener('click', () => {
            // Get current movie data from the modal
            const movieModal = document.getElementById('movieModal');
            const movieId = movieModal.querySelector('.book-ticket').dataset.movieId;
            const showtimeId = document.getElementById('showtimeSelect').value;
            const selectedSeatsArray = Array.from(selectedSeats);
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
            const totalPrice = parseFloat(document.getElementById('totalPrice').textContent.replace(/[^\d]/g, ''));

            // Debug information
            console.log('Payment Data:', {
                movieId,
                showtimeId,
                selectedSeats: selectedSeatsArray,
                paymentMethod,
                totalPrice
            });

            const formData = new FormData();
            formData.append('movie_id', movieId);
            formData.append('showtime_id', showtimeId);
            formData.append('selected_seats', JSON.stringify(selectedSeatsArray));
            formData.append('payment_method', paymentMethod);
            formData.append('total_price', totalPrice);

            // Show loading state
            const confirmButton = document.getElementById('confirmPayment');
            const originalText = confirmButton.innerHTML;
            confirmButton.innerHTML = 'Processing...';
            confirmButton.disabled = true;

            fetch('process_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Server Response:', data);
                if (data.success) {
                    const paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                    paymentModal.hide();
                    
                    // Show success modal with download link
                    const downloadLink = document.getElementById('downloadTicket');
                    downloadLink.href = '../downloads/' + data.pdf_filename;
                    
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                    
                    // Automatically trigger download
                    const downloadFrame = document.createElement('iframe');
                    downloadFrame.style.display = 'none';
                    downloadFrame.src = downloadLink.href;
                    document.body.appendChild(downloadFrame);
                    
                    // Remove the iframe after download starts
                    setTimeout(() => {
                        document.body.removeChild(downloadFrame);
                    }, 1000);
                } else {
                    alert('Payment failed: ' + (data.error || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred: ' + error.message);
            })
            .finally(() => {
                // Reset button state
                confirmButton.innerHTML = originalText;
                confirmButton.disabled = false;
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const studioSelect = document.getElementById('studioSelect');
            const showtimeSelect = document.getElementById('showtimeSelect');
            
            function filterShowtimes() {
                const selectedStudio = studioSelect.value;
                const options = showtimeSelect.getElementsByTagName('option');
                
                for (let option of options) {
                    if (option.value === '') continue; // Skip the "No showtimes available" option
                    const studioNumber = option.getAttribute('data-studio');
                    option.style.display = studioNumber === selectedStudio ? '' : 'none';
                }
                
                // Reset showtime selection
                showtimeSelect.value = '';
            }
            
            studioSelect.addEventListener('change', filterShowtimes);
            // Initial filter
            filterShowtimes();
        });

        // Add event listener for showtime selection
        document.getElementById('showtimeSelect').addEventListener('change', generateSeats);

        // Handle payment method switching
        document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Hide all payment details
                document.querySelectorAll('.payment-details').forEach(detail => {
                    detail.classList.add('d-none');
                });
                
                // Show selected payment details
                if (this.value === 'virtual_account') {
                    document.getElementById('virtualAccountDetails').classList.remove('d-none');
                } else if (this.value === 'qris') {
                    document.getElementById('qrisDetails').classList.remove('d-none');
                }
            });
        });
    </script>
</body>
</html> 