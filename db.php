<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'coffee_reservation';

// Create persistent connection for better performance
$conn = mysqli_init();
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
$conn->real_connect($host, $username, $password, $database, null, null, MYSQLI_CLIENT_COMPRESS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8 for better performance
$conn->set_charset("utf8mb4");

// Only run table alterations once by checking a flag file
$setup_file = __DIR__ . '/.db_setup_complete';
if (!file_exists($setup_file)) {
    // Create notifications table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        reservation_id INT,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
    )";
    $conn->query($sql);

    // Update reservations table to include 'rejected' status if needed
    $conn->query("ALTER TABLE reservations MODIFY COLUMN status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'rejected') DEFAULT 'pending'");

    // Add payment columns to reservations table if they don't exist
    $result = $conn->query("SHOW COLUMNS FROM reservations LIKE 'payment_amount'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN payment_amount DECIMAL(10, 2) DEFAULT 0.00");
    }
    
    $result = $conn->query("SHOW COLUMNS FROM reservations LIKE 'payment_status'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid'");
    }
    
    $result = $conn->query("SHOW COLUMNS FROM reservations LIKE 'payment_date'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE reservations ADD COLUMN payment_date TIMESTAMP NULL");
    }
    
    // Add indexes for better performance
    $conn->query("CREATE INDEX IF NOT EXISTS idx_user_date ON reservations(user_id, reservation_date)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_table_date ON reservations(table_id, reservation_date, reservation_time)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_status ON reservations(status)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_user_read ON notifications(user_id, is_read)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_created ON notifications(created_at)");
    
    // Mark setup as complete
    file_put_contents($setup_file, date('Y-m-d H:i:s'));
}
?>
