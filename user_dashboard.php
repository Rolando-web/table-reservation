<?php
if (!ob_start("ob_gzhandler")) ob_start();

$start_time = microtime(true);

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'user'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header('Location: login.php?error=invalid_session');
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
        // Calculate payment amount: ₱200 per person + ₱500 base fee
        $payment_amount = ($guests * 200) + 500;
        
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
    $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Reservation cancelled successfully.';
        header('Location: user_dashboard.php');
        exit();
    }
}

// Handle payment
if (isset($_POST['process_payment'])) {
    $reservation_id = $_POST['reservation_id'];
    $payment_amount = $_POST['payment_amount'];
    $payment_method = $_POST['payment_method'] ?? 'Credit Card';
    
    // Handle different payment methods
    if ($payment_method === 'GCash') {
        $gcash_number = preg_replace('/\s+/', '', $_POST['gcash_number'] ?? '');
        $cardholder_name = $_POST['gcash_name'] ?? 'Guest';
        $card_last_four = substr($gcash_number, -4);
    } else {
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
        header('Location: user_dashboard.php');
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error'] = 'Payment processing failed. Please try again.';
        header('Location: user_dashboard.php');
        exit();
    }
}

// Fetch available tables (only 20 latest)
$tables = $conn->query("SELECT * FROM tables ORDER BY table_number LIMIT 20");

// Fetch user's reservations (only latest 50)
$stmt = $conn->prepare("SELECT r.*, t.table_number, t.location, t.capacity 
                        FROM reservations r 
                        JOIN tables t ON r.table_id = t.id 
                        WHERE r.user_id = ? 
                        ORDER BY r.reservation_date DESC, r.reservation_time DESC 
                        LIMIT 50");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_reservations = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Coffee Table Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>img{content-visibility:auto}</style>

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
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Section -->
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 rounded-2xl shadow-xl p-8 mb-8 text-white">
            <h1 class="text-3xl font-bold mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
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
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-chair mr-2 text-amber-600"></i>Available Tables
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php while ($table = $tables->fetch_assoc()): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-2xl transition transform hover:-translate-y-1">
                        <div class="h-48 overflow-hidden">
                            <img src="<?php echo htmlspecialchars($table['image_url']); ?>" 
                                 alt="Table <?php echo htmlspecialchars($table['table_number']); ?>"
                                 loading="lazy"
                                 class="w-full h-full object-cover">
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800">Table <?php echo htmlspecialchars($table['table_number']); ?></h3>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($table['location']); ?>
                                    </p>
                                    <p class="text-lg font-bold text-amber-600 mt-2">
                                        ₱<?php echo number_format(($table['capacity'] * 20) + 200, 2); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        ₱200 base + ₱20 per guest
                                    </p>
                                </div>
                                <span class="bg-amber-100 text-amber-800 px-2 py-1 rounded-full text-xs font-semibold">
                                    <i class="fas fa-users mr-1"></i><?php echo $table['capacity']; ?> seats
                                </span>
                            </div>
                            <button onclick="openBookingModal(<?php echo $table['id']; ?>, '<?php echo $table['table_number']; ?>', <?php echo $table['capacity']; ?>)" 
                                    class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                <i class="fas fa-calendar-check mr-2"></i>Book Now
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- My Reservations Section -->
        <div>
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-list mr-2 text-amber-600"></i>My Reservations
            </h2>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guests</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($user_reservations->num_rows > 0): ?>
                                <?php while ($reservation = $user_reservations->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-semibold text-gray-900">Table <?php echo htmlspecialchars($reservation['table_number']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($reservation['location']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-gray-900"><?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-900">
                                            <i class="fas fa-users mr-1"></i><?php echo $reservation['guests']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-gray-900 font-semibold">₱<?php echo number_format($reservation['payment_amount'], 2); ?></div>
                                            <?php if ($reservation['payment_status'] == 'paid'): ?>
                                                <span class="text-xs text-green-600">
                                                    <i class="fas fa-check-circle mr-1"></i>Paid
                                                </span>
                                            <?php else: ?>
                                                <span class="text-xs text-red-600">
                                                    <i class="fas fa-times-circle mr-1"></i>Unpaid
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $status_colors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'confirmed' => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                'completed' => 'bg-blue-100 text-blue-800',
                                                'rejected' => 'bg-red-100 text-red-800'
                                            ];
                                            $status = $reservation['status'];
                                            ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_colors[$status]; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="flex flex-col space-y-2">
                                                <?php if ($reservation['status'] == 'confirmed' && $reservation['payment_status'] == 'unpaid'): ?>
                                                    <button onclick="openPaymentModal(<?php echo $reservation['id']; ?>, <?php echo $reservation['payment_amount']; ?>)" 
                                                            class="text-green-600 hover:text-green-900 font-medium">
                                                        <i class="fas fa-credit-card mr-1"></i>Pay Now
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($reservation['payment_status'] == 'paid'): ?>
                                                    <a href="generate_receipt.php?reservation_id=<?php echo $reservation['id']; ?>" 
                                                       target="_blank"
                                                       class="text-amber-600 hover:text-amber-900 font-medium">
                                                        <i class="fas fa-file-pdf mr-1"></i>View Receipt
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if (($reservation['status'] == 'pending' || $reservation['status'] == 'confirmed') && $reservation['payment_status'] == 'unpaid'): ?>
                                                    <a href="?cancel=<?php echo $reservation['id']; ?>" 
                                                       onclick="return confirm('Are you sure you want to cancel this reservation?')" 
                                                       class="text-red-600 hover:text-red-900 font-medium">
                                                        <i class="fas fa-times-circle mr-1"></i>Cancel
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-2 text-gray-300"></i>
                                        <p>No reservations yet. Book your first table!</p>
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
                        <input type="hidden" name="payment_method" id="paymentMethod" value="Credit Card">
                        
                        <div class="mb-6 bg-amber-50 p-4 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="text-lg text-gray-700">Total Amount:</span>
                                <span class="text-3xl font-bold text-amber-600" id="displayAmount">₱0.00</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Pricing: ₱500 base fee + ₱200 per guest</p>
                        </div>

                        <!-- Payment Method Selection -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Select Payment Method</label>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" onclick="selectPaymentMethod('Credit Card')" 
                                        id="btnCreditCard"
                                        class="payment-method-btn active flex flex-col items-center justify-center p-4 border-2 border-amber-500 bg-amber-50 rounded-lg hover:bg-amber-100 transition">
                                    <i class="fas fa-credit-card text-3xl text-amber-600 mb-2"></i>
                                    <span class="text-sm font-semibold text-gray-800">Credit Card</span>
                                </button>
                                <button type="button" onclick="selectPaymentMethod('GCash')" 
                                        id="btnGCash"
                                        class="payment-method-btn flex flex-col items-center justify-center p-4 border-2 border-gray-300 bg-white rounded-lg hover:bg-gray-50 transition">
                                    <i class="fas fa-mobile-alt text-3xl text-blue-600 mb-2"></i>
                                    <span class="text-sm font-semibold text-gray-800">GCash</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Credit Card Fields -->
                        <div id="creditCardFields" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                                <input type="text" name="card_number" id="cardNumber" placeholder="1234 5678 9012 3456"
                                       maxlength="19"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                                    <input type="text" id="cardExpiry" placeholder="MM/YY"
                                           maxlength="5"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">CVV</label>
                                    <input type="text" id="cardCvv" placeholder="123"
                                           maxlength="3"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cardholder Name</label>
                                <input type="text" name="cardholder_name" id="cardholderName" placeholder="John Doe"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
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

    <script>
        function openBookingModal(tableId, tableNumber, capacity) {
            document.getElementById('bookingModal').classList.remove('hidden');
            document.getElementById('modalTableId').value = tableId;
            document.getElementById('modalTableName').textContent = 'Book Table ' + tableNumber;
            document.getElementById('modalGuests').max = capacity;
        }
        
        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
        }
        
        function openPaymentModal(reservationId, amount) {
            document.getElementById('paymentModal').classList.remove('hidden');
            document.getElementById('paymentReservationId').value = reservationId;
            document.getElementById('paymentAmount').value = amount;
            document.getElementById('displayAmount').textContent = '₱' + parseFloat(amount).toFixed(2);
            
            // Reset to credit card by default
            selectPaymentMethod('Credit Card');
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
        }
        
        function selectPaymentMethod(method) {
            // Update hidden field
            document.getElementById('paymentMethod').value = method;
            
            // Update submit button text
            const btnText = document.getElementById('paymentBtnText');
            btnText.textContent = method === 'GCash' ? 'Pay with GCash' : 'Pay Now';
            
            // Update button styles
            const creditBtn = document.getElementById('btnCreditCard');
            const gcashBtn = document.getElementById('btnGCash');
            
            if (method === 'Credit Card') {
                creditBtn.classList.add('active', 'border-amber-500', 'bg-amber-50');
                creditBtn.classList.remove('border-gray-300', 'bg-white');
                gcashBtn.classList.remove('active', 'border-blue-500', 'bg-blue-50');
                gcashBtn.classList.add('border-gray-300', 'bg-white');
                
                // Show/hide fields
                document.getElementById('creditCardFields').classList.remove('hidden');
                document.getElementById('gcashFields').classList.add('hidden');
                
                // Set required fields
                document.getElementById('cardNumber').required = true;
                document.getElementById('cardExpiry').required = true;
                document.getElementById('cardCvv').required = true;
                document.getElementById('cardholderName').required = true;
                document.getElementById('gcashNumber').required = false;
                document.getElementById('gcashName').required = false;
            } else if (method === 'GCash') {
                gcashBtn.classList.add('active', 'border-blue-500', 'bg-blue-50');
                gcashBtn.classList.remove('border-gray-300', 'bg-white');
                creditBtn.classList.remove('active', 'border-amber-500', 'bg-amber-50');
                creditBtn.classList.add('border-gray-300', 'bg-white');
                
                // Show/hide fields
                document.getElementById('creditCardFields').classList.add('hidden');
                document.getElementById('gcashFields').classList.remove('hidden');
                
                // Set required fields
                document.getElementById('cardNumber').required = false;
                document.getElementById('cardExpiry').required = false;
                document.getElementById('cardCvv').required = false;
                document.getElementById('cardholderName').required = false;
                document.getElementById('gcashNumber').required = true;
                document.getElementById('gcashName').required = true;
            }
        }
        
        function formatPhoneNumber(input) {
            // Remove all non-digit characters
            let value = input.value.replace(/\D/g, '');
            
            // Limit to 10 digits
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            
            // Format as XXX XXX XXXX
            if (value.length > 6) {
                value = value.substring(0, 3) + ' ' + value.substring(3, 6) + ' ' + value.substring(6);
            } else if (value.length > 3) {
                value = value.substring(0, 3) + ' ' + value.substring(3);
            }
            
            input.value = value;
        }
        
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('hidden');
        }
        
        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = event.target.closest('.fa-bell');
            
            if (!dropdown.contains(event.target) && !bell) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
