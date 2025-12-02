<?php
if (!ob_start("ob_gzhandler")) ob_start();

session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    // Verify this is user's reservation and hasn't been submitted yet
    $stmt = $conn->prepare("SELECT id, feedback_submitted FROM reservations WHERE id = ? AND user_id = ? AND payment_status = 'paid' LIMIT 1");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $reservation = $result->fetch_assoc();
        if ($reservation['feedback_submitted'] == 0) {
            // Insert feedback
            $stmt = $conn->prepare("INSERT INTO feedbacks (user_id, reservation_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $user_id, $reservation_id, $rating, $comment);
            
            if ($stmt->execute()) {
                // Mark feedback as submitted
                $stmt = $conn->prepare("UPDATE reservations SET feedback_submitted = 1 WHERE id = ?");
                $stmt->bind_param("i", $reservation_id);
                $stmt->execute();
                
                $_SESSION['success'] = 'Thank you for your feedback!';
            } else {
                $_SESSION['error'] = 'Failed to submit feedback. Please try again.';
            }
        } else {
            $_SESSION['error'] = 'You have already submitted feedback for this reservation.';
        }
    }
    header('Location: my_reservations.php');
    exit();
}

// Handle cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $reservation_id = $_GET['cancel'];
    $stmt = $conn->prepare("SELECT status FROM reservations WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $reservation = $res->fetch_assoc();
        if ($reservation['status'] === 'confirmed') {
            $_SESSION['error'] = 'Cannot cancel a confirmed reservation. Please contact admin.';
        } else {
            $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $reservation_id, $user_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Reservation cancelled successfully!';
            } else {
                $_SESSION['error'] = 'Failed to cancel reservation.';
            }
        }
    } else {
        $_SESSION['error'] = 'Reservation not found.';
    }
    header('Location: my_reservations.php');
    exit();
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    $payment_method = $_POST['payment_method'];
    $card_number = $_POST['card_number'] ?? '';
    $card_name = $_POST['card_name'] ?? '';
    $card_expiry = $_POST['card_expiry'] ?? '';
    $card_cvv = $_POST['card_cvv'] ?? '';
    
    $stmt = $conn->prepare("UPDATE reservations SET payment_status = 'paid', payment_date = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Payment processed successfully! Reservation confirmed.';
        // Trigger feedback modal
        $_SESSION['show_feedback_modal'] = true;
        $_SESSION['feedback_reservation_id'] = $reservation_id;
    } else {
        $_SESSION['error'] = 'Payment failed. Please try again.';
    }
    header('Location: my_reservations.php');
    exit();
}

// Fetch user's reservations with table info
$stmt = $conn->prepare("SELECT r.*, t.table_number, t.location, t.capacity 
                        FROM reservations r 
                        JOIN tables t ON r.table_id = t.id 
                        WHERE r.user_id = ? 
                        ORDER BY r.reservation_date DESC, r.reservation_time DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reservations = $stmt->get_result();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - Coffee Table Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/user.css">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-amber-600 to-orange-600 shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-coffee text-white text-2xl mr-2"></i>
                        <span class="text-xl font-bold text-white">Coffee Table Reservation</span>
                    </div>
                    <div class="hidden md:flex space-x-4">
                        <a href="home.php" class="text-white hover:bg-amber-700 px-3 py-2 rounded-md text-sm font-medium transition">
                            <i class="fas fa-home mr-1"></i>Home
                        </a>
                        <a href="user_dashboard.php" class="text-white hover:bg-amber-700 px-3 py-2 rounded-md text-sm font-medium transition">
                            <i class="fas fa-calendar-alt mr-1"></i>Book Table
                        </a>
                        <a href="my_reservations.php" class="bg-amber-700 text-white px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-list mr-1"></i>My Reservations
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button onclick="toggleNotifications()" class="relative text-white hover:bg-amber-700 p-2 rounded-full transition">
                            <i class="fas fa-bell text-xl"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                    </div>
                    <span class="text-white hidden md:inline">
                        <i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                    </span>
                    <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">My Reservations</h1>
            <p class="text-gray-600">View and manage your table reservations</p>
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

        <!-- Reservations List -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guests</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($reservations->num_rows > 0): ?>
                            <?php while ($reservation = $reservations->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900">Table <?php echo htmlspecialchars($reservation['table_number']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($reservation['location']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo $reservation['capacity']; ?> seats</div>
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
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full
                                            <?php 
                                            echo $reservation['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                ($reservation['status'] == 'confirmed' ? 'bg-green-100 text-green-800' : 
                                                ($reservation['status'] == 'cancelled' ? 'bg-red-100 text-red-800' : 
                                                ($reservation['status'] == 'rejected' ? 'bg-gray-100 text-gray-800' : 
                                                'bg-blue-100 text-blue-800'))); 
                                            ?>">
                                            <?php echo ucfirst($reservation['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($reservation['payment_status'] == 'paid'): ?>
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>Paid
                                            </span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                                <i class="fas fa-clock mr-1"></i>Unpaid
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-2">
                                            <?php if ($reservation['status'] == 'pending' && $reservation['payment_status'] != 'paid'): ?>
                                                <button onclick="openPaymentModal(<?php echo $reservation['id']; ?>, <?php echo $reservation['payment_amount']; ?>)" 
                                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs font-semibold transition">
                                                    <i class="fas fa-credit-card mr-1"></i>Pay Now
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($reservation['status'] == 'pending' && $reservation['payment_status'] == 'unpaid'): ?>
                                                <a href="?cancel=<?php echo $reservation['id']; ?>" 
                                                   onclick="return confirm('Are you sure you want to cancel this reservation?')"
                                                   class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs font-semibold transition">
                                                    <i class="fas fa-times mr-1"></i>Cancel
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($reservation['special_requests']): ?>
                                                <button onclick="alert('Special Requests: <?php echo addslashes($reservation['special_requests']); ?>')" 
                                                        class="text-amber-600 hover:text-amber-900">
                                                    <i class="fas fa-comment-dots"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-calendar-times text-4xl mb-4"></i>
                                    <p class="text-lg">You don't have any reservations yet.</p>
                                    <a href="user_dashboard.php" class="mt-4 inline-block bg-amber-600 hover:bg-amber-700 text-white px-6 py-2 rounded-lg transition">
                                        Book a Table Now
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" id="paymentForm">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-gray-900">Complete Payment</h3>
                            <button type="button" onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>

                        <input type="hidden" name="reservation_id" id="payment_reservation_id">

                        <div class="mb-4 bg-amber-50 p-4 rounded-lg">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700 font-medium">Total Amount:</span>
                                <span class="text-2xl font-bold text-amber-600" id="payment_amount_display">₱0.00</span>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                                <select name="payment_method" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="gcash">GCash</option>
                                    <option value="paymaya">PayMaya</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                                <input type="text" name="card_number" required maxlength="16" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                       placeholder="1234 5678 9012 3456">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cardholder Name</label>
                                <input type="text" name="card_name" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                       placeholder="John Doe">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                                    <input type="text" name="card_expiry" required placeholder="MM/YY" maxlength="5"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">CVV</label>
                                    <input type="text" name="card_cvv" required maxlength="4"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                           placeholder="123">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="submit_payment" 
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-check mr-2"></i>Complete Payment
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
        function openPaymentModal(reservationId, amount) {
            document.getElementById('payment_reservation_id').value = reservationId;
            document.getElementById('payment_amount_display').textContent = '₱' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            document.getElementById('paymentModal').classList.remove('hidden');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
        }

        function toggleNotifications() {
            // Placeholder for notifications dropdown
            alert('Notifications feature - check user_dashboard.php for full implementation');
        }

        function openFeedbackModal(reservationId) {
            document.getElementById('feedback_reservation_id').value = reservationId;
            document.getElementById('feedbackModal').classList.remove('hidden');
        }

        function closeFeedbackModal() {
            document.getElementById('feedbackModal').classList.add('hidden');
        }

        function setRating(rating) {
            document.getElementById('feedback_rating').value = rating;
            // Update star display
            for (let i = 1; i <= 5; i++) {
                const star = document.getElementById('star-' + i);
                if (i <= rating) {
                    star.classList.remove('text-gray-300');
                    star.classList.add('text-yellow-400');
                } else {
                    star.classList.remove('text-yellow-400');
                    star.classList.add('text-gray-300');
                }
            }
        }

        // Auto-show feedback modal if payment just completed
        <?php if (isset($_SESSION['show_feedback_modal']) && $_SESSION['show_feedback_modal']): ?>
            // Delay to ensure page is fully loaded
            setTimeout(function() {
                openFeedbackModal(<?php echo $_SESSION['feedback_reservation_id']; ?>);
            }, 500);
            <?php 
            unset($_SESSION['show_feedback_modal']);
            unset($_SESSION['feedback_reservation_id']);
            ?>
        <?php endif; ?>
    </script>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="hidden fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeFeedbackModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-star text-amber-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    How was your experience?
                                </h3>
                                <div class="mt-4">
                                    <input type="hidden" name="reservation_id" id="feedback_reservation_id">
                                    <input type="hidden" name="rating" id="feedback_rating" value="5">
                                    
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Rating</label>
                                        <div class="flex justify-center space-x-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i id="star-<?php echo $i; ?>" 
                                                   class="fas fa-star text-3xl cursor-pointer <?php echo $i <= 5 ? 'text-yellow-400' : 'text-gray-300'; ?>" 
                                                   onclick="setRating(<?php echo $i; ?>)"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Comments (Optional)</label>
                                        <textarea name="comment" rows="4" 
                                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                                  placeholder="Tell us about your experience..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="submit_feedback" 
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-amber-600 text-base font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Feedback
                        </button>
                        <button type="button" onclick="closeFeedbackModal()" 
                                class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Skip
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
