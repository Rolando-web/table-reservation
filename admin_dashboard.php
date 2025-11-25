<?php
// Enable output compression for faster page loading
if (!ob_start("ob_gzhandler")) ob_start();

session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle reservation approval
if (isset($_POST['approve_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    
    // Get reservation details
    $stmt = $conn->prepare("SELECT user_id, table_id FROM reservations WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();
    
    // Update reservation status
    $stmt = $conn->prepare("UPDATE reservations SET status = 'confirmed' WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    
    if ($stmt->execute()) {
        // Create notification
        $title = "Reservation Approved";
        $message = "Your reservation has been approved and confirmed!";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, reservation_id, title, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $reservation['user_id'], $reservation_id, $title, $message);
        $stmt->execute();
        
        $_SESSION['success'] = 'Reservation approved successfully!';
        header('Location: admin_dashboard.php');
        exit();
    }
}

// Handle reservation rejection
if (isset($_POST['reject_reservation'])) {
    $reservation_id = $_POST['reservation_id'];
    
    // Get reservation details
    $stmt = $conn->prepare("SELECT user_id FROM reservations WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();
    
    // Update reservation status
    $stmt = $conn->prepare("UPDATE reservations SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    
    if ($stmt->execute()) {
        // Create notification
        $title = "Reservation Rejected";
        $message = "Unfortunately, your reservation has been rejected. Please try another time slot.";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, reservation_id, title, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $reservation['user_id'], $reservation_id, $title, $message);
        $stmt->execute();
        
        $_SESSION['success'] = 'Reservation rejected successfully!';
        header('Location: admin_dashboard.php');
        exit();
    }
}

// Handle reservation status update
if (isset($_POST['update_status'])) {
    $reservation_id = $_POST['reservation_id'];
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $reservation_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Reservation status updated successfully!';
        header('Location: admin_dashboard.php');
        exit();
    }
}

// Handle table addition
if (isset($_POST['add_table'])) {
    $table_number = $_POST['table_number'];
    $capacity = $_POST['capacity'];
    $location = $_POST['location'];
    $image_url = $_POST['image_url'];
    
    $stmt = $conn->prepare("INSERT INTO tables (table_number, capacity, location, image_url) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $table_number, $capacity, $location, $image_url);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Table added successfully!';
        header('Location: admin_dashboard.php');
        exit();
    } else {
        $_SESSION['error'] = 'Failed to add table. Table number might already exist.';
        header('Location: admin_dashboard.php');
        exit();
    }
}

// Handle table deletion
if (isset($_GET['delete_table'])) {
    $table_id = $_GET['delete_table'];
    $stmt = $conn->prepare("DELETE FROM tables WHERE id = ?");
    $stmt->bind_param("i", $table_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Table deleted successfully!';
        header('Location: admin_dashboard.php');
        exit();
    }
}

// Fetch statistics (cached for performance)
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$total_tables = $conn->query("SELECT COUNT(*) as count FROM tables")->fetch_assoc()['count'];
$total_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
$pending_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'completed'")->fetch_assoc()['total'];

// Fetch all reservations with payment info (limited to 100 latest)
$reservations = $conn->query("SELECT r.*, t.table_number, t.location, u.username, u.email, u.phone,
                              p.transaction_id, p.payment_method, p.card_last_four
                              FROM reservations r 
                              JOIN tables t ON r.table_id = t.id 
                              JOIN users u ON r.user_id = u.id 
                              LEFT JOIN payments p ON r.id = p.reservation_id
                              ORDER BY r.reservation_date DESC, r.reservation_time DESC 
                              LIMIT 100");

// Fetch all tables
$tables = $conn->query("SELECT * FROM tables ORDER BY table_number");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Coffee Table Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-amber-600 to-orange-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-coffee text-white text-2xl mr-2"></i>
                        <span class="text-xl font-bold text-white">Admin Panel</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-white">
                        <i class="fas fa-user-shield mr-2"></i>
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
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Admin Dashboard</h1>
            <p class="text-gray-600">Manage reservations and tables</p>
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

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Users</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_users; ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-4">
                        <i class="fas fa-users text-blue-500 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Tables</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_tables; ?></p>
                    </div>
                    <div class="bg-green-100 rounded-full p-4">
                        <i class="fas fa-chair text-green-500 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Reservations</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_reservations; ?></p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-4">
                        <i class="fas fa-calendar-check text-purple-500 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Pending</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $pending_reservations; ?></p>
                    </div>
                    <div class="bg-yellow-100 rounded-full p-4">
                        <i class="fas fa-clock text-yellow-500 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-amber-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Revenue</p>
                        <p class="text-3xl font-bold text-gray-800">₱<?php echo number_format($total_revenue, 2); ?></p>
                    </div>
                    <div class="bg-amber-100 rounded-full p-4">
                        <i class="fas fa-money-bill-wave text-amber-500 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button onclick="showTab('reservations')" id="reservationsTab" 
                            class="tab-button border-b-2 border-amber-500 py-4 px-1 text-center text-sm font-medium text-amber-600">
                        <i class="fas fa-list mr-2"></i>Reservations
                    </button>
                    <button onclick="showTab('tables')" id="tablesTab" 
                            class="tab-button border-b-2 border-transparent py-4 px-1 text-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-chair mr-2"></i>Manage Tables
                    </button>
                </nav>
            </div>
        </div>

        <!-- Reservations Tab -->
        <div id="reservationsContent" class="tab-content">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">All Reservations</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guests</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($reservation = $reservations->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($reservation['username']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($reservation['email']); ?></div>
                                        <?php if ($reservation['phone']): ?>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($reservation['phone']); ?></div>
                                        <?php endif; ?>
                                    </td>
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
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" 
                                                    class="text-xs font-semibold rounded-full px-3 py-1 border-0 focus:ring-2 focus:ring-amber-500
                                                    <?php 
                                                    echo $reservation['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                        ($reservation['status'] == 'confirmed' ? 'bg-green-100 text-green-800' : 
                                                        ($reservation['status'] == 'cancelled' ? 'bg-red-100 text-red-800' : 
                                                        'bg-blue-100 text-blue-800')); 
                                                    ?>">
                                                <option value="pending" <?php echo $reservation['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $reservation['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="completed" <?php echo $reservation['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $reservation['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="rejected" <?php echo $reservation['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-2">
                                            <?php if ($reservation['status'] == 'pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                    <button type="submit" name="approve_reservation" 
                                                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs font-semibold transition"
                                                            onclick="return confirm('Approve this reservation?')">
                                                        <i class="fas fa-check mr-1"></i>Approve
                                                    </button>
                                                </form>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                    <button type="submit" name="reject_reservation" 
                                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs font-semibold transition"
                                                            onclick="return confirm('Reject this reservation?')">
                                                        <i class="fas fa-times mr-1"></i>Reject
                                                    </button>
                                                </form>
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tables Tab -->
        <div id="tablesContent" class="tab-content hidden">
            <div class="mb-6">
                <button onclick="openAddTableModal()" 
                        class="bg-amber-600 hover:bg-amber-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                    <i class="fas fa-plus mr-2"></i>Add New Table
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php while ($table = $tables->fetch_assoc()): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="h-48 overflow-hidden">
                            <img src="<?php echo htmlspecialchars($table['image_url']); ?>" 
                                 alt="Table <?php echo htmlspecialchars($table['table_number']); ?>"
                                 class="w-full h-full object-cover">
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800">Table <?php echo htmlspecialchars($table['table_number']); ?></h3>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($table['location']); ?>
                                    </p>
                                </div>
                                <span class="bg-amber-100 text-amber-800 px-2 py-1 rounded-full text-xs font-semibold">
                                    <i class="fas fa-users mr-1"></i><?php echo $table['capacity']; ?> seats
                                </span>
                            </div>
                            <a href="?delete_table=<?php echo $table['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this table?')"
                               class="w-full block text-center bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg transition">
                                <i class="fas fa-trash mr-2"></i>Delete
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Add Table Modal -->
    <div id="addTableModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-gray-900">Add New Table</h3>
                            <button type="button" onclick="closeAddTableModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Table Number</label>
                                <input type="text" name="table_number" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                       placeholder="e.g., T9">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Capacity (Seats)</label>
                                <input type="number" name="capacity" min="1" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                                <input type="text" name="location" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                       placeholder="e.g., Window Side, Patio">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Image URL</label>
                                <input type="url" name="image_url" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                       placeholder="https://example.com/image.jpg">
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="add_table" 
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-amber-600 text-base font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-plus mr-2"></i>Add Table
                        </button>
                        <button type="button" onclick="closeAddTableModal()" 
                                class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(el => {
                el.classList.remove('border-amber-500', 'text-amber-600');
                el.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected tab
            document.getElementById(tab + 'Content').classList.remove('hidden');
            document.getElementById(tab + 'Tab').classList.remove('border-transparent', 'text-gray-500');
            document.getElementById(tab + 'Tab').classList.add('border-amber-500', 'text-amber-600');
        }
        
        function openAddTableModal() {
            document.getElementById('addTableModal').classList.remove('hidden');
        }
        
        function closeAddTableModal() {
            document.getElementById('addTableModal').classList.add('hidden');
        }
    </script>
</body>
</html>
