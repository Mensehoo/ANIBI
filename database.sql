

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Movies table
CREATE TABLE movies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    poster_url VARCHAR(255) NOT NULL,
    synopsis TEXT NOT NULL,
    duration INT NOT NULL, -- in minutes
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Showtimes table
CREATE TABLE showtimes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    movie_id INT,
    studio_number INT NOT NULL,
    showtime TIME NOT NULL,
    FOREIGN KEY (movie_id) REFERENCES movies(id)
);

-- Seats table
CREATE TABLE seats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    showtime_id INT,
    seat_number VARCHAR(10) NOT NULL,
    is_available BOOLEAN DEFAULT true,
    FOREIGN KEY (showtime_id) REFERENCES showtimes(id)
);

-- Tickets table
CREATE TABLE tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    movie_id INT,
    showtime_id INT,
    seat_id INT,
    payment_method ENUM('virtual_account', 'qris') NOT NULL,
    payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (movie_id) REFERENCES movies(id),
    FOREIGN KEY (showtime_id) REFERENCES showtimes(id),
    FOREIGN KEY (seat_id) REFERENCES seats(id)
); 