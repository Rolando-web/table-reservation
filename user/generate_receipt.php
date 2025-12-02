<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['reservation_id'])) {
    header('Location: user_dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$reservation_id = $_GET['reservation_id'];

// Fetch reservation details
$stmt = $conn->prepare("SELECT r.*, t.table_number, t.location, t.capacity, u.username, u.email, u.phone 
                        FROM reservations r 
                        JOIN tables t ON r.table_id = t.id 
                        JOIN users u ON r.user_id = u.id 
                        WHERE r.id = ? AND r.user_id = ?");
$stmt->bind_param("ii", $reservation_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: user_dashboard.php');
    exit();
}

$reservation = $result->fetch_assoc();
?>
// Fetch latest payment for this reservation (if any)
$payment = null;
$stmt = $conn->prepare("SELECT * FROM payments WHERE reservation_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$payment_result = $stmt->get_result();
if ($payment_result && $payment_result->num_rows > 0) {
    $payment = $payment_result->fetch_assoc();
}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Coffee Table Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <i class="fas fa-coffee text-amber-600"></i>
                <span class="font-semibold text-gray-800">Coffee Table</span>
            </div>
            <div class="flex items-center space-x-6">
                <a href="../index.php" class="text-gray-700 hover:text-amber-600">Home</a>
                <a href="user_dashboard.php" class="text-amber-600 hover:text-amber-800">Dashboard</a>
            </div>
        </div>
    </nav>
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <div class="mb-4 flex justify-between items-center">
                <a href="user_dashboard.php" class="text-amber-600 hover:text-amber-800">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
                <button onclick="generatePDF()" class="bg-amber-600 hover:bg-amber-700 text-white px-6 py-2 rounded-lg transition">
                    <i class="fas fa-file-pdf mr-2"></i>Download PDF
                </button>
            </div>

            <div id="receipt" class="bg-white rounded-xl shadow-2xl p-8">
                <!-- Header -->
                <div class="text-center mb-8 border-b-2 border-amber-500 pb-6">
                    <div class="flex items-center justify-center mb-4">
                        <i class="fas fa-coffee text-amber-600 text-5xl mr-3"></i>
                    </div>
                    <h1 class="text-4xl font-bold text-gray-800 mb-2">Coffee Table</h1>
                    <p class="text-gray-600">Reservation System</p>
                    <div class="mt-4 text-sm text-gray-500">
                        <p>123 Coffee Street</p>
                        <p>Phone: +1 (555) 123-4567</p>
                        <p>Email: info@coffeetable.com</p>
                    </div>
                </div>

                <!-- Receipt Title -->
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-amber-600 mb-2">PAYMENT RECEIPT</h2>
                    <p class="text-gray-600">Receipt #<?php echo str_pad($reservation['id'], 6, '0', STR_PAD_LEFT); ?></p>
                </div>

                <!-- Customer Details -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Customer Information</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Name</p>
                                <p class="font-semibold"><?php echo htmlspecialchars($reservation['username']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Email</p>
                                <p class="font-semibold"><?php echo htmlspecialchars($reservation['email']); ?></p>
                            </div>
                            <?php if ($reservation['phone']): ?>
                            <div>
                                <p class="text-sm text-gray-600">Phone</p>
                                <p class="font-semibold"><?php echo htmlspecialchars($reservation['phone']); ?></p>
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="text-sm text-gray-600">Booking Date</p>
                                <p class="font-semibold"><?php echo date('M d, Y h:i A', strtotime($reservation['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reservation Details -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Reservation Details</h3>
                    <div class="bg-amber-50 p-4 rounded-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Table Number</p>
                                <p class="font-semibold text-lg">Table <?php echo htmlspecialchars($reservation['table_number']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Location</p>
                                <p class="font-semibold"><?php echo htmlspecialchars($reservation['location']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Reservation Date</p>
                                <p class="font-semibold"><?php echo date('l, F d, Y', strtotime($reservation['reservation_date'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Reservation Time</p>
                                <p class="font-semibold"><?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Number of Guests</p>
                                <p class="font-semibold"><?php echo $reservation['guests']; ?> person(s)</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Duration</p>
                                <p class="font-semibold"><?php echo $reservation['duration']; ?> hour(s)</p>
                            </div>
                        </div>
                        <?php if ($reservation['special_requests']): ?>
                        <div class="mt-4 pt-4 border-t border-amber-200">
                            <p class="text-sm text-gray-600">Special Requests</p>
                            <p class="font-semibold"><?php echo htmlspecialchars($reservation['special_requests']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Details -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Payment Summary</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-gray-700">Reservation Fee</span>
                            <span class="font-semibold">₱<?php echo number_format($reservation['payment_amount'], 2); ?></span>
                        </div>
                        <div class="border-t-2 border-gray-300 pt-3 mt-3">
                            <div class="flex justify-between items-center">
                                <span class="text-xl font-bold text-gray-800">Total Amount</span>
                                <span class="text-2xl font-bold text-amber-600">₱<?php echo number_format($reservation['payment_amount'], 2); ?></span>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Payment Status</span>
                                <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-semibold">
                                    <?php echo ucfirst($reservation['payment_status']); ?>
                                </span>
                            </div>
                            <?php if ($reservation['payment_date']): ?>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-gray-700">Payment Date</span>
                                <span class="font-semibold"><?php echo date('M d, Y h:i A', strtotime($reservation['payment_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($payment): ?>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-gray-700">Payment Method</span>
                                <span class="font-semibold"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-gray-700">Transaction ID</span>
                                <span class="font-semibold"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                            </div>
                            <?php if ($payment['payment_method'] === 'Credit Card' && !empty($payment['card_last_four'])): ?>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-gray-700">Card</span>
                                <span class="font-semibold">Ending in <?php echo htmlspecialchars($payment['card_last_four']); ?></span>
                            </div>
                            <?php elseif ($payment['payment_method'] === 'GCash' && !empty($payment['card_last_four'])): ?>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-gray-700">GCash (last 4)</span>
                                <span class="font-semibold"><?php echo htmlspecialchars($payment['card_last_four']); ?></span>
                            </div>
                            <?php elseif ($payment['payment_method'] === 'Cash'): ?>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-gray-700">Payment Note</span>
                                <span class="font-semibold">Pay on arrival (Cash)</span>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Status Badge -->
                <div class="text-center mb-8">
                    <?php
                    $status_colors = [
                        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                        'confirmed' => 'bg-green-100 text-green-800 border-green-300',
                        'cancelled' => 'bg-red-100 text-red-800 border-red-300',
                        'completed' => 'bg-blue-100 text-blue-800 border-blue-300',
                        'rejected' => 'bg-red-100 text-red-800 border-red-300'
                    ];
                    $status = $reservation['status'];
                    ?>
                    <div class="inline-block px-6 py-3 border-2 rounded-lg <?php echo $status_colors[$status]; ?>">
                        <p class="text-sm font-semibold">Reservation Status</p>
                        <p class="text-2xl font-bold uppercase"><?php echo $status; ?></p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center pt-6 border-t-2 border-gray-200">
                    <p class="text-gray-600 mb-2">Thank you for choosing Coffee Table!</p>
                    <p class="text-sm text-gray-500">This is an official receipt for your reservation.</p>
                    <p class="text-sm text-gray-500 mt-2">For inquiries, please contact us at info@coffeetable.com</p>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/receipt.js"></script>

    <footer class="mt-12 bg-white border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-sm text-gray-600">
            Contact: <a href="mailto:Mintal@gmail.com" class="text-amber-600 hover:text-amber-800">Mintal@gmail.com</a>
        </div>
    </footer>
</body>
</html>
