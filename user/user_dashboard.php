<?php
if (!ob_start("ob_gzhandler")) ob_start();

$start_time = microtime(true);

session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'user'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header('Location: ../login.php?error=invalid_session');
    exit();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    header('Location: user_dashboard.php');
    exit();
}

if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header('Location: user_dashboard.php');
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT n.*, r.reservation_date, r.reservation_time 
                        FROM notifications n 
                        LEFT JOIN reservations r ON n.reservation_id = r.id 
                        WHERE n.user_id = ? 
                        ORDER BY n.created_at DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_table'])) {
    $table_id = $_POST['table_id'];
    $reservation_date = $_POST['reservation_date'];
    $reservation_time = $_POST['reservation_time'];
    $guests = $_POST['guests'];
    $special_requests = $_POST['special_requests'] ?? '';
    
    $stmt = $conn->prepare("SELECT id FROM reservations WHERE table_id = ? AND reservation_date = ? AND reservation_time = ? AND status != 'cancelled'");
    $stmt->bind_param("iss", $table_id, $reservation_date, $reservation_time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = 'This table is already booked for the selected time. Please choose another time or table.';
        header('Location: user_dashboard.php');
        exit();
    } else {
        // Get table price
        $stmt = $conn->prepare("SELECT price FROM tables WHERE id = ?");
        $stmt->bind_param("i", $table_id);
        $stmt->execute();
        $table_result = $stmt->get_result();
        $table_price = $table_result->num_rows > 0 ? $table_result->fetch_assoc()['price'] : 500;
        
        // Calculate payment amount: use table base price only (no per-guest surcharge)
        $payment_amount = $table_price;
        
        $stmt = $conn->prepare("INSERT INTO reservations (user_id, table_id, reservation_date, reservation_time, guests, special_requests, payment_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iississ", $user_id, $table_id, $reservation_date, $reservation_time, $guests, $special_requests, $payment_amount);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Reservation successful! Your table has been booked. Total amount: ₱' . number_format($payment_amount, 2);
            header('Location: user_dashboard.php');
            exit();
        } else {
            $_SESSION['error'] = 'Reservation failed. Please contact support.';
            header('Location: user_dashboard.php');
            exit();
        }
    }
}

if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $reservation_id = $_GET['cancel'];
    // Check current reservation status to prevent cancelling confirmed reservations
    $stmt = $conn->prepare("SELECT status FROM reservations WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $_SESSION['error'] = 'Reservation not found.';
        header('Location: user_dashboard.php');
        exit();
    }

    $row = $res->fetch_assoc();
    if ($row['status'] === 'confirmed') {
        $_SESSION['error'] = 'Cannot cancel a reservation that has been approved by the admin.';
        header('Location: user_dashboard.php');
        exit();
    }

    // Proceed to cancel if not confirmed
    $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Reservation cancelled successfully.';
        header('Location: user_dashboard.php');
        exit();
    } else {
        $_SESSION['error'] = 'Failed to cancel reservation. Please try again.';
        header('Location: user_dashboard.php');
        exit();
    }
}

// Handle payment
if (isset($_POST['process_payment'])) {
    $reservation_id = $_POST['reservation_id'];
    $payment_amount = $_POST['payment_amount'];
    $payment_method = $_POST['payment_method'] ?? 'Credit Card';
    
    // Prepare default values
    $card_last_four = '';
    $cardholder_name = 'Guest';

    // Handle different payment methods
    if ($payment_method === 'GCash') {
        $gcash_number = preg_replace('/\s+/', '', $_POST['gcash_number'] ?? '');
        $cardholder_name = $_POST['gcash_name'] ?? 'Guest';
        $card_last_four = substr($gcash_number, -4);
    } elseif ($payment_method === 'Credit Card') {
        $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $cardholder_name = $_POST['cardholder_name'] ?? 'Guest';
        $card_last_four = substr($card_number, -4);
    } elseif ($payment_method === 'Cash') {
        // Cash payment: no card details
        $card_last_four = '';
        $cardholder_name = 'Cash';
    } else {
        // Fallback to credit card behavior
        $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $cardholder_name = $_POST['cardholder_name'] ?? 'Guest';
        $card_last_four = substr($card_number, -4);
    }
    
    // Generate transaction ID
    $transaction_id = 'TXN' . time() . rand(1000, 9999);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update reservation payment status
        $stmt = $conn->prepare("UPDATE reservations SET payment_status = 'paid', payment_date = NOW() WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $reservation_id, $user_id);
        $stmt->execute();
        
        // Insert payment record
        $payment_status = 'completed';
        $stmt = $conn->prepare("INSERT INTO payments (reservation_id, user_id, amount, payment_method, transaction_id, payment_status, card_last_four, cardholder_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidsssss", $reservation_id, $user_id, $payment_amount, $payment_method, $transaction_id, $payment_status, $card_last_four, $cardholder_name);
        $stmt->execute();
        
        // Create notification
        $title = "Payment Successful";
        $message = "Your payment of ₱" . number_format($payment_amount, 2) . " via " . $payment_method . " has been processed successfully. Transaction ID: " . $transaction_id;
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, reservation_id, title, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $user_id, $reservation_id, $title, $message);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = 'Payment successful via ' . $payment_method . '! Transaction ID: ' . $transaction_id;
        // Trigger feedback modal
        $_SESSION['show_feedback_modal'] = true;
        $_SESSION['feedback_reservation_id'] = $reservation_id;
        header('Location: my_reservations.php');
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error'] = 'Payment processing failed. Please try again.';
        header('Location: user_dashboard.php');
        exit();
    }
}

// Fetch tables with price
$tables = $conn->query("SELECT id, table_number, capacity, location, image_url, price FROM tables ORDER BY table_number LIMIT 20");

// Fetch ONLY current user's reservations
$stmt = $conn->prepare("SELECT r.*, t.table_number, t.location, t.capacity 
                        FROM reservations r 
                        JOIN tables t ON r.table_id = t.id 
                        WHERE r.user_id = ? 
                        ORDER BY r.reservation_date DESC, r.reservation_time DESC 
                        LIMIT 50");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_reservations = $stmt->get_result();

// Debug: Log current user ID (remove after testing)
// error_log("User Dashboard - User ID: " . $user_id . " | Reservations: " . $user_reservations->num_rows);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Coffee Table Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/user.css">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-coffee text-amber-600 text-2xl mr-2"></i>
                        <span class="text-xl font-bold text-gray-800">Coffee Table</span>
                    </div>
                    <div class="hidden md:flex items-center ml-8 space-x-6">
                        <a href="home.php" class="text-gray-700 hover:text-amber-600 transition">Home</a>
                        <a href="user_dashboard.php" class="text-amber-600 font-semibold">Book Table</a>
                        <a href="my_reservations.php" class="text-gray-700 hover:text-amber-600 transition">My Reservations</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button onclick="toggleNotifications()" class="relative text-gray-700 hover:text-amber-600 transition">
                            <i class="fas fa-bell text-2xl"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">
                                    <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Notification Dropdown -->
                        <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-2xl z-50 max-h-96 overflow-y-auto">
                            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                                <h3 class="font-bold text-gray-800">Notifications</h3>
                                <?php if ($unread_count > 0): ?>
                                    <a href="?mark_all_read=1" class="text-xs text-amber-600 hover:text-amber-800">Mark all read</a>
                                <?php endif; ?>
                            </div>
                            <?php if ($notifications->num_rows > 0): ?>
                                <?php while ($notif = $notifications->fetch_assoc()): ?>
                                    <div class="p-4 border-b border-gray-100 hover:bg-gray-50 <?php echo $notif['is_read'] ? 'bg-white' : 'bg-amber-50'; ?>">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                                <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                                <?php if ($notif['reservation_date']): ?>
                                                    <p class="text-xs text-gray-500 mt-1">
                                                        <i class="fas fa-calendar mr-1"></i><?php echo date('M d, Y', strtotime($notif['reservation_date'])); ?>
                                                        <i class="fas fa-clock ml-2 mr-1"></i><?php echo date('h:i A', strtotime($notif['reservation_time'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="text-xs text-gray-400 mt-1"><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></p>
                                            </div>
                                            <?php if (!$notif['is_read']): ?>
                                                <a href="?mark_read=<?php echo $notif['id']; ?>" class="ml-2 text-amber-600 hover:text-amber-800">
                                                    <i class="fas fa-check-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="p-4 text-center text-gray-500">
                                    <i class="fas fa-bell-slash text-3xl mb-2"></i>
                                    <p>No notifications yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <span class="text-gray-700">
                        <i class="fas fa-user-circle mr-2"></i>
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </span>
                    <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Section -->
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 rounded-2xl shadow-xl p-8 mb-8 text-white">
            <h1 class="text-3xl font-bold mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h1>
            <p class="text-amber-100">Reserve your perfect coffee table today</p>
        </div>

        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                <div class="flex">
                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                    <p class="ml-3 text-green-700"><?php echo htmlspecialchars($success); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                <div class="flex">
                    <i class="fas fa-exclamation-circle text-red-500 mt-1"></i>
                    <p class="ml-3 text-red-700"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Available Tables Section -->
        <div class="mb-12">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-chair mr-2 text-amber-600"></i>Available Tables
                </h2>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600" id="tableCount">Showing all tables</span>
                </div>
            </div>
            
            <!-- Search and Filter Section -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6 fade-in">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search Box -->
                    <div class="md:col-span-2">
                        <div class="search-box relative">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="searchTable" placeholder="Search by table number or location..."
                                   oninput="filterTables()"
                                   class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none transition">
                        </div>
                    </div>
                    
                    <!-- Capacity Filter -->
                    <div>
                        <select id="capacityFilter" onchange="filterTables()"
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none transition">
                            <option value="">All Capacities</option>
                            <option value="2">2 Seats</option>
                            <option value="4">4 Seats</option>
                            <option value="6">6 Seats</option>
                            <option value="8">8+ Seats</option>
                        </select>
                    </div>
                </div>
                
                <!-- Location Filter Buttons -->
                <div class="mt-4 flex flex-wrap gap-2">
                    <button onclick="filterByLocation('')" 
                            class="location-filter px-4 py-2 rounded-full text-sm font-semibold border-2 transition filter-active"
                            data-location="">
                        <i class="fas fa-globe mr-1"></i>All Locations
                    </button>
                    <button onclick="filterByLocation('Window Side')" 
                            class="location-filter px-4 py-2 rounded-full text-sm font-semibold border-2 border-gray-300 hover:border-amber-500 transition"
                            data-location="Window Side">
                        <i class="fas fa-window-maximize mr-1"></i>Window Side
                    </button>
                    <button onclick="filterByLocation('Corner')" 
                            class="location-filter px-4 py-2 rounded-full text-sm font-semibold border-2 border-gray-300 hover:border-amber-500 transition"
                            data-location="Corner">
                        <i class="fas fa-border-style mr-1"></i>Corner
                    </button>
                    <button onclick="filterByLocation('Center')" 
                            class="location-filter px-4 py-2 rounded-full text-sm font-semibold border-2 border-gray-300 hover:border-amber-500 transition"
                            data-location="Center">
                        <i class="fas fa-bullseye mr-1"></i>Center
                    </button>
                    <button onclick="filterByLocation('Outdoor')" 
                            class="location-filter px-4 py-2 rounded-full text-sm font-semibold border-2 border-gray-300 hover:border-amber-500 transition"
                            data-location="Outdoor">
                        <i class="fas fa-tree mr-1"></i>Outdoor
                    </button>
                    <button onclick="resetFilters()" 
                            class="px-4 py-2 rounded-full text-sm font-semibold bg-gray-100 hover:bg-gray-200 transition">
                        <i class="fas fa-redo mr-1"></i>Reset
                    </button>
                </div>
            </div>
            
            <!-- No Results Message -->
            <div id="noResults" class="hidden bg-white rounded-xl shadow-lg p-8 text-center">
                <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-700 mb-2">No tables found</h3>
                <p class="text-gray-500">Try adjusting your search or filter criteria</p>
            </div>
            
            <div id="tablesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php while ($table = $tables->fetch_assoc()): ?>
                    <div class="table-card card-hover bg-white rounded-xl shadow-lg overflow-hidden" 
                         data-table-number="<?php echo htmlspecialchars($table['table_number']); ?>"
                         data-location="<?php echo htmlspecialchars($table['location']); ?>"
                         data-capacity="<?php echo $table['capacity']; ?>">
                        <div class="h-48 overflow-hidden relative">
                            <img src="<?php echo htmlspecialchars($table['image_url']); ?>" 
                                 alt="Table <?php echo htmlspecialchars($table['table_number']); ?>"
                                 loading="lazy"
                                 class="w-full h-full object-cover transition-transform duration-300 hover:scale-110">
                            <div class="absolute top-3 right-3">
                                <span class="bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full text-sm font-bold text-amber-600 shadow-lg">
                                    <i class="fas fa-users mr-1"></i><?php echo $table['capacity']; ?> seats
                                </span>
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="mb-3">
                                <h3 class="text-xl font-bold text-gray-800 mb-1">Table <?php echo htmlspecialchars($table['table_number']); ?></h3>
                                <p class="text-sm text-gray-600 flex items-center">
                                    <i class="fas fa-map-marker-alt mr-2 text-amber-500"></i>
                                    <?php echo htmlspecialchars($table['location']); ?>
                                </p>
                            </div>
                            <div class="mb-4 p-3 bg-gradient-to-r from-amber-50 to-orange-50 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-600">Starting from</span>
                                    <span class="text-2xl font-bold text-amber-600">
                                        ₱<?php echo number_format($table['price'] ?? 500, 2); ?>
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1 text-right">
                                    ₱<?php echo number_format($table['price'] ?? 500, 0); ?> base
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="openBookingModal(<?php echo $table['id']; ?>, '<?php echo $table['table_number']; ?>', <?php echo $table['capacity']; ?>, <?php echo $table['price'] ?? 500; ?>)" 
                                        class="flex-1 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-semibold py-3 px-4 rounded-lg transition transform hover:scale-105 shadow-md">
                                    <i class="fas fa-calendar-check mr-2"></i>Book Now
                                </button>
                                <button onclick="openFeedbackModal(<?php echo $table['id']; ?>, '<?php echo $table['table_number']; ?>')" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 px-3 rounded-lg transition transform hover:scale-105 shadow-md" title="View Feedbacks">
                                    <i class="fas fa-star"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- My Reservations Section -->
        <div>
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-list mr-2 text-amber-600"></i>My Reservations
                </h2>
                <!-- Reservation Status Filter -->
                <div class="flex space-x-2">
                    <button onclick="filterReservations('')" 
                            class="reservation-filter px-3 py-1 rounded-full text-xs font-semibold border-2 filter-active"
                            data-status="">
                        All
                    </button>
                    <button onclick="filterReservations('pending')" 
                            class="reservation-filter px-3 py-1 rounded-full text-xs font-semibold border-2 border-yellow-300 text-yellow-700 hover:bg-yellow-50"
                            data-status="pending">
                        Pending
                    </button>
                    <button onclick="filterReservations('confirmed')" 
                            class="reservation-filter px-3 py-1 rounded-full text-xs font-semibold border-2 border-green-300 text-green-700 hover:bg-green-50"
                            data-status="confirmed">
                        Confirmed
                    </button>
                    <button onclick="filterReservations('completed')" 
                            class="reservation-filter px-3 py-1 rounded-full text-xs font-semibold border-2 border-blue-300 text-blue-700 hover:bg-blue-50"
                            data-status="completed">
                        Completed
                    </button>
                </div>
            </div>
            
            <!-- Search Reservations -->
            <div class="bg-white rounded-xl shadow-lg p-4 mb-4">
                <div class="search-box relative">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchReservation" placeholder="Search by table number, date, or location..."
                           oninput="searchReservations()"
                           class="w-full pl-12 pr-4 py-2 border-2 border-gray-200 rounded-lg focus:border-amber-500 focus:outline-none transition">
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-amber-50 to-orange-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-hashtag mr-1"></i>Table
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-calendar mr-1"></i>Date & Time
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-users mr-1"></i>Guests
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-money-bill mr-1"></i>Payment
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-info-circle mr-1"></i>Status
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    <i class="fas fa-cog mr-1"></i>Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="reservationsTableBody">
                            <?php if ($user_reservations->num_rows > 0): ?>
                                <?php while ($reservation = $user_reservations->fetch_assoc()): ?>
                                    <tr class="reservation-row hover:bg-amber-50 transition" 
                                        data-status="<?php echo $reservation['status']; ?>"
                                        data-table="<?php echo htmlspecialchars($reservation['table_number']); ?>"
                                        data-location="<?php echo htmlspecialchars($reservation['location']); ?>"
                                        data-date="<?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?>">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-amber-400 to-orange-500 rounded-lg flex items-center justify-center text-white font-bold">
                                                    <?php echo htmlspecialchars($reservation['table_number']); ?>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="font-semibold text-gray-900">Table <?php echo htmlspecialchars($reservation['table_number']); ?></div>
                                                    <div class="text-xs text-gray-500">
                                                        <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($reservation['location']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-gray-900 font-medium">
                                                <i class="fas fa-calendar-alt text-amber-500 mr-1"></i>
                                                <?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <i class="fas fa-clock text-amber-500 mr-1"></i>
                                                <?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <span class="bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-sm font-semibold">
                                                    <i class="fas fa-users mr-1"></i><?php echo $reservation['guests']; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-gray-900 font-bold text-lg">₱<?php echo number_format($reservation['payment_amount'], 2); ?></div>
                                            <?php if ($reservation['payment_status'] == 'paid'): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                    <i class="fas fa-check-circle mr-1"></i>Paid
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 badge-animate">
                                                    <i class="fas fa-exclamation-circle mr-1"></i>Unpaid
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                                'confirmed' => 'bg-green-100 text-green-800 border-green-300',
                                                'cancelled' => 'bg-red-100 text-red-800 border-red-300',
                                                'completed' => 'bg-blue-100 text-blue-800 border-blue-300',
                                                'rejected' => 'bg-red-100 text-red-800 border-red-300'
                                            ];
                                            $status_icons = [
                                                'pending' => 'fa-hourglass-half',
                                                'confirmed' => 'fa-check-circle',
                                                'cancelled' => 'fa-times-circle',
                                                'completed' => 'fa-flag-checkered',
                                                'rejected' => 'fa-ban'
                                            ];
                                            $status = $reservation['status'];
                                            ?>
                                            <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-bold rounded-full border-2 <?php echo $status_colors[$status]; ?>">
                                                <i class="fas <?php echo $status_icons[$status]; ?> mr-1"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="flex flex-col space-y-2">
                                                <?php if ($reservation['status'] == 'confirmed' && $reservation['payment_status'] == 'unpaid'): ?>
                                                    <button onclick="openPaymentModal(<?php echo $reservation['id']; ?>, <?php echo $reservation['payment_amount']; ?>)" 
                                                            class="inline-flex items-center px-3 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold transition shadow-md">
                                                        <i class="fas fa-credit-card mr-2"></i>Pay Now
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($reservation['payment_status'] == 'paid'): ?>
                                                    <a href="generate_receipt.php?reservation_id=<?php echo $reservation['id']; ?>" 
                                                       target="_blank"
                                                       class="inline-flex items-center px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-semibold transition shadow-md">
                                                        <i class="fas fa-file-pdf mr-2"></i>Receipt
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($reservation['status'] == 'pending' && $reservation['payment_status'] == 'unpaid'): ?>
                                                    <a href="?cancel=<?php echo $reservation['id']; ?>" 
                                                       onclick="return confirm('Are you sure you want to cancel this reservation?')" 
                                                       class="inline-flex items-center px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold transition shadow-md">
                                                        <i class="fas fa-times-circle mr-2"></i>Cancel
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                                            <p class="text-xl font-semibold text-gray-500 mb-2">No reservations yet</p>
                                            <p class="text-gray-400">Book your first table above!</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-gray-900" id="modalTableName">Book Table</h3>
                            <button type="button" onclick="closeBookingModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                        
                        <input type="hidden" name="table_id" id="modalTableId">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Reservation Date</label>
                                <input type="date" name="reservation_date" required 
                                       min="<?php echo date('Y-m-d'); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Reservation Time</label>
                                <input type="time" name="reservation_time" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Number of Guests</label>
                                <input type="number" name="guests" min="1" id="modalGuests" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                <p class="text-xs text-gray-500 mt-1"><i class="fas fa-info-circle mr-1"></i>Maximum capacity: <span id="maxCapacity" class="font-semibold text-amber-600"></span> guests</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Special Requests (Optional)</label>
                                <textarea name="special_requests" rows="3" 
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                          placeholder="Any special requirements..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="book_table" 
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-amber-600 text-base font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-check mr-2"></i>Confirm Booking
                        </button>
                        <button type="button" onclick="closeBookingModal()" 
                                class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-gray-900">Payment</h3>
                            <button type="button" onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                        
                        <input type="hidden" name="reservation_id" id="paymentReservationId">
                        <input type="hidden" name="payment_amount" id="paymentAmount">
                        <input type="hidden" name="payment_method" id="paymentMethod" value="GCash">
                        
                        <div class="mb-6 bg-amber-50 p-4 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="text-lg text-gray-700">Total Amount:</span>
                                <span class="text-3xl font-bold text-amber-600" id="displayAmount">₱0.00</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-2" id="pricingNote">Pricing: Table base price</p>
                        </div>

                        <!-- Payment Method Selection -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Select Payment Method</label>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" data-method="GCash" onclick="selectPaymentMethod('GCash')" 
                                    id="btnGCash"
                                        class="payment-method-btn active flex flex-col items-center justify-center p-4 border-2 border-blue-500 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                                    <i class="fas fa-mobile-alt text-3xl text-blue-600 mb-2"></i>
                                    <span class="text-sm font-semibold text-gray-800">GCash</span>
                                </button>
                                <button type="button" data-method="Cash" onclick="selectPaymentMethod('Cash')" 
                                    id="btnCash"
                                        class="payment-method-btn flex flex-col items-center justify-center p-4 border-2 border-gray-300 bg-white rounded-lg hover:bg-gray-50 transition">
                                    <i class="fas fa-money-bill-wave text-3xl text-green-600 mb-2"></i>
                                    <span class="text-sm font-semibold text-gray-800">Cash</span>
                                </button>
                            </div>
                        </div>
                        

                        <!-- GCash Fields -->
                        <div id="gcashFields" class="space-y-4 hidden">
                            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 mb-1">Pay via GCash</p>
                                        <p class="text-xs text-gray-600">Enter your GCash mobile number to proceed with payment</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-mobile-alt mr-1"></i>GCash Mobile Number
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-500 font-semibold">+63</span>
                                    <input type="tel" name="gcash_number" id="gcashNumber" placeholder="912 345 6789"
                                           maxlength="12"
                                           pattern="[0-9\s]{10,12}"
                                           oninput="formatPhoneNumber(this)"
                                           class="w-full pl-14 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>Enter 10-digit mobile number (e.g., 912 345 6789)
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-user mr-1"></i>Account Name
                                </label>
                                <input type="text" name="gcash_name" id="gcashName" placeholder="Juan Dela Cruz"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>

                        <!-- Cash Fields / Instructions -->
                        <div id="cashFields" class="space-y-4 hidden">
                            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-green-500 mt-1 mr-3"></i>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800 mb-1">Pay with Cash</p>
                                        <p class="text-xs text-gray-600">Select this to pay in cash at the venue. A staff member will collect payment upon arrival.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="process_payment" id="paymentSubmitBtn"
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-lock mr-2"></i><span id="paymentBtnText">Pay Now</span>
                        </button>
                        <button type="button" onclick="closePaymentModal()" 
                                class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Table Feedbacks Modal -->
    <div id="feedbackModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeFeedbackModal()"></div>
            
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-900">
                            <i class="fas fa-star text-yellow-400 mr-2"></i>
                            <span id="feedbackModalTitle">Table Feedbacks</span>
                        </h3>
                        <button type="button" onclick="closeFeedbackModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                    
                    <!-- Loading State -->
                    <div id="feedbackLoading" class="flex flex-col items-center justify-center py-8">
                        <i class="fas fa-spinner fa-spin text-4xl text-amber-500 mb-4"></i>
                        <p class="text-gray-600">Loading feedbacks...</p>
                    </div>
                    
                    <!-- Feedbacks Container -->
                    <div id="feedbacksContainer" class="hidden max-h-96 overflow-y-auto">
                        <!-- Feedbacks will be loaded here via AJAX -->
                    </div>
                    
                    <!-- No Feedbacks Message -->
                    <div id="noFeedbacksMessage" class="hidden flex flex-col items-center justify-center py-8">
                        <i class="fas fa-comment-slash text-6xl text-gray-300 mb-4"></i>
                        <p class="text-xl font-semibold text-gray-500 mb-2">No feedbacks yet</p>
                        <p class="text-gray-400">Be the first to leave a review for this table!</p>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex justify-end">
                    <button type="button" onclick="closeFeedbackModal()" 
                            class="inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/user.js"></script>
    
    <script>
    // Feedback Modal Functions
    function openFeedbackModal(tableId, tableNumber) {
        document.getElementById('feedbackModal').classList.remove('hidden');
        document.getElementById('feedbackModalTitle').textContent = 'Feedbacks for Table ' + tableNumber;
        document.getElementById('feedbackLoading').classList.remove('hidden');
        document.getElementById('feedbacksContainer').classList.add('hidden');
        document.getElementById('noFeedbacksMessage').classList.add('hidden');
        
        // Fetch feedbacks via AJAX
        fetch('get_table_feedbacks.php?table_id=' + tableId)
            .then(response => response.json())
            .then(data => {
                document.getElementById('feedbackLoading').classList.add('hidden');
                
                if (data.success && data.feedbacks.length > 0) {
                    let html = '<div class="space-y-4">';
                    data.feedbacks.forEach(feedback => {
                        let stars = '';
                        for (let i = 1; i <= 5; i++) {
                            stars += '<i class="fas fa-star ' + (i <= feedback.rating ? 'text-yellow-400' : 'text-gray-300') + '"></i>';
                        }
                        
                        html += `
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="font-semibold text-gray-900">${escapeHtml(feedback.username)}</h4>
                                        <p class="text-sm text-gray-500">${feedback.reservation_date}</p>
                                    </div>
                                    <div class="flex items-center">
                                        ${stars}
                                        <span class="ml-2 text-sm text-gray-600">(${feedback.rating}/5)</span>
                                    </div>
                                </div>
                                <p class="text-gray-700">${feedback.comment ? escapeHtml(feedback.comment) : '<em class="text-gray-400">No comment provided</em>'}</p>
                                <p class="text-xs text-gray-400 mt-2">Submitted: ${feedback.created_at}</p>
                            </div>
                        `;
                    });
                    html += '</div>';
                    
                    // Add average rating summary
                    if (data.average_rating) {
                        let avgStars = '';
                        for (let i = 1; i <= 5; i++) {
                            avgStars += '<i class="fas fa-star ' + (i <= Math.round(data.average_rating) ? 'text-yellow-400' : 'text-gray-300') + '"></i>';
                        }
                        html = `
                            <div class="bg-amber-50 rounded-lg p-4 mb-4 border border-amber-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-amber-800">Average Rating</p>
                                        <div class="flex items-center mt-1">
                                            ${avgStars}
                                            <span class="ml-2 text-lg font-bold text-amber-600">${parseFloat(data.average_rating).toFixed(1)}/5</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-amber-600">${data.feedbacks.length}</p>
                                        <p class="text-sm text-amber-800">Reviews</p>
                                    </div>
                                </div>
                            </div>
                        ` + html;
                    }
                    
                    document.getElementById('feedbacksContainer').innerHTML = html;
                    document.getElementById('feedbacksContainer').classList.remove('hidden');
                } else {
                    document.getElementById('noFeedbacksMessage').classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error fetching feedbacks:', error);
                document.getElementById('feedbackLoading').classList.add('hidden');
                document.getElementById('noFeedbacksMessage').classList.remove('hidden');
            });
    }
    
    function closeFeedbackModal() {
        document.getElementById('feedbackModal').classList.add('hidden');
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>

    <footer class="mt-12 bg-white border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-sm text-gray-600">
            Contact: <a href="mailto:Rocky@gmail.com" class="text-amber-600 hover:text-amber-800">Rocky@gmail.com</a>
        </div>
    </footer>
</body>
</html>
