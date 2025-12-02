<?php
// Enable output compression for faster page loading
if (!ob_start("ob_gzhandler")) ob_start();

session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
// Handle table deletion
if (isset($_GET['delete_table'])) {
    $table_id = $_GET['delete_table'];
    $stmt = $conn->prepare("DELETE FROM tables WHERE id = ?");
    $stmt->bind_param("i", $table_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Table deleted successfully!';
        header('Location: admin_dashboard.php?tab=tables');
        exit();
    }
}

// Handle reservation POST actions: update status, approve, reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generic status update from select box
    if (isset($_POST['update_status']) && isset($_POST['reservation_id']) && isset($_POST['status'])) {
        $rid = (int)$_POST['reservation_id'];
        $new_status = $conn->real_escape_string($_POST['status']);
        $u = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $u->bind_param('si', $new_status, $rid);
        if ($u->execute()) {
            $_SESSION['success'] = 'Reservation status updated.';
        } else {
            $_SESSION['error'] = 'Failed to update reservation status: ' . $u->error;
        }
        header('Location: admin_dashboard.php');
        exit();
    }

    // Approve reservation (explicit approve button)
    if (isset($_POST['approve_reservation']) && isset($_POST['reservation_id'])) {
        $rid = (int)$_POST['reservation_id'];
        $u = $conn->prepare("UPDATE reservations SET status = 'confirmed' WHERE id = ?");
        $u->bind_param('i', $rid);
        if ($u->execute()) {
            $_SESSION['success'] = 'Reservation approved.';
        } else {
            $_SESSION['error'] = 'Failed to approve reservation: ' . $u->error;
        }
        header('Location: admin_dashboard.php');
        exit();
    }

    // Reject reservation (explicit reject button)
    if (isset($_POST['reject_reservation']) && isset($_POST['reservation_id'])) {
        $rid = (int)$_POST['reservation_id'];
        $u = $conn->prepare("UPDATE reservations SET status = 'rejected' WHERE id = ?");
        $u->bind_param('i', $rid);
        if ($u->execute()) {
            $_SESSION['success'] = 'Reservation rejected.';
        } else {
            $_SESSION['error'] = 'Failed to reject reservation: ' . $u->error;
        }
        header('Location: admin_dashboard.php');
        exit();
    }

    // Add new table
    if (isset($_POST['add_table'])) {
        $table_number = $conn->real_escape_string($_POST['table_number']);
        $capacity = (int)$_POST['capacity'];
        $location = $conn->real_escape_string($_POST['location']);
        $price = !empty($_POST['price']) ? (float)$_POST['price'] : 500.00;
        $image_url = '';

        // Handle file upload
        if (!empty($_FILES['image_file']['name'])) {
            $upload_dir = '../assets/images/tables/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['image_file']['name']));
            $target_path = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_path)) {
                $image_url = '../assets/images/tables/' . $filename;
            } else {
                $_SESSION['error'] = 'Failed to upload image file.';
                header('Location: admin_dashboard.php');
                exit();
            }
        } elseif (!empty($_POST['image_url'])) {
            $image_url = $conn->real_escape_string($_POST['image_url']);
        } else {
            // Default image if none provided
            $image_url = '../assets/images/tables/default-table.jpg';
        }

        $stmt = $conn->prepare("INSERT INTO tables (table_number, capacity, location, image_url, price) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sissd', $table_number, $capacity, $location, $image_url, $price);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Table added successfully!';
        } else {
            $_SESSION['error'] = 'Failed to add table: ' . $stmt->error;
        }
        header('Location: admin_dashboard.php?tab=tables');
        exit();
    }

    // Edit table
    if (isset($_POST['edit_table']) && isset($_POST['table_id'])) {
        $table_id = (int)$_POST['table_id'];
        $table_number = $conn->real_escape_string($_POST['table_number']);
        $capacity = (int)$_POST['capacity'];
        $location = $conn->real_escape_string($_POST['location']);
        $price = (float)$_POST['price'];

        // Handle optional image replacement
        if (!empty($_FILES['edit_image_file']['name'])) {
            $upload_dir = '../assets/images/tables/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['edit_image_file']['name']));
            $target_path = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['edit_image_file']['tmp_name'], $target_path)) {
                $image_url = '../assets/images/tables/' . $filename;
                $stmt = $conn->prepare("UPDATE tables SET table_number = ?, capacity = ?, location = ?, image_url = ?, price = ? WHERE id = ?");
                $stmt->bind_param('sissdi', $table_number, $capacity, $location, $image_url, $price, $table_id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE tables SET table_number = ?, capacity = ?, location = ?, price = ? WHERE id = ?");
            $stmt->bind_param('sisdi', $table_number, $capacity, $location, $price, $table_id);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = 'Table updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update table: ' . $stmt->error;
        }
        header('Location: admin_dashboard.php?tab=tables');
        exit();
    }

    // Delete user
    if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        // Prevent admin from deleting themselves
        if ($user_id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'User deleted successfully!';
            } else {
                $_SESSION['error'] = 'Failed to delete user: ' . $stmt->error;
            }
        } else {
            $_SESSION['error'] = 'You cannot delete your own account!';
        }
        header('Location: admin_dashboard.php');
        exit();
    }

    // Edit user
    if (isset($_POST['edit_user']) && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        
        // Check if password is being updated
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ?, password = ? WHERE id = ?");
            $stmt->bind_param('ssssi', $username, $email, $phone, $password, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->bind_param('sssi', $username, $email, $phone, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'User updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update user: ' . $stmt->error;
        }
        header('Location: admin_dashboard.php');
        exit();
    }
}

// Fetch statistics (cached for performance)
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$total_tables = $conn->query("SELECT COUNT(*) as count FROM tables")->fetch_assoc()['count'];
$total_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations")->fetch_assoc()['count'];
$pending_reservations = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status = 'pending'")->fetch_assoc()['count'];
// Determine total revenue robustly: prefer a `payments` table if present, otherwise use reservations.payment_amount
$total_revenue = 0.00;
$payments_table_check = $conn->query("SHOW TABLES LIKE 'payments'");
if ($payments_table_check && $payments_table_check->num_rows > 0) {
    $tr = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'completed'");
    $total_revenue = $tr ? (float)$tr->fetch_assoc()['total'] : 0.00;
} else {
    $tr = $conn->query("SELECT COALESCE(SUM(payment_amount), 0) as total FROM reservations WHERE payment_status = 'paid'");
    $total_revenue = $tr ? (float)$tr->fetch_assoc()['total'] : 0.00;
}

// Analytics queries for report
$status_counts = $conn->query("SELECT status, COUNT(*) as cnt FROM reservations GROUP BY status");
$top_tables = $conn->query("SELECT t.table_number, COUNT(*) as cnt FROM reservations r JOIN tables t ON r.table_id = t.id GROUP BY t.id ORDER BY cnt DESC LIMIT 5");

// Today metrics (current date)
$today_date = date('Y-m-d');
$users_today_q = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE DATE(created_at) = '" . $conn->real_escape_string($today_date) . "'");
$users_today = $users_today_q ? (int)$users_today_q->fetch_assoc()['cnt'] : 0;
$reservations_today_q = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE reservation_date = '" . $conn->real_escape_string($today_date) . "'");
$reservations_today = $reservations_today_q ? (int)$reservations_today_q->fetch_assoc()['cnt'] : 0;
$pending_today_q = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE reservation_date = '" . $conn->real_escape_string($today_date) . "' AND status = 'pending'");
$pending_today = $pending_today_q ? (int)$pending_today_q->fetch_assoc()['cnt'] : 0;
$revenue_today_q = $conn->query("SELECT COALESCE(SUM(payment_amount),0) as total FROM reservations WHERE payment_status = 'paid' AND DATE(payment_date) = '" . $conn->real_escape_string($today_date) . "'");
$revenue_today = $revenue_today_q ? (float)$revenue_today_q->fetch_assoc()['total'] : 0.00;

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

// Fetch all feedbacks with user and reservation info
$feedbacks = $conn->query("SELECT f.*, u.username, u.email, r.reservation_date, r.reservation_time, t.table_number 
                           FROM feedbacks f 
                           JOIN users u ON f.user_id = u.id 
                           JOIN reservations r ON f.reservation_id = r.id 
                           JOIN tables t ON r.table_id = t.id 
                           ORDER BY f.created_at DESC");

// Fetch all users
$all_users = $conn->query("SELECT id, username, email, phone, role, created_at FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Coffee Table Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
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
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
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

                <div class="flex justify-end mb-8 space-x-3">
                    <a href="daily_report.php" target="_blank" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg inline-flex items-center">
                        <i class="fas fa-eye mr-2"></i>Open Daily Report
                    </a>
                </div>

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
                    <button onclick="showTab('feedbacks')" id="feedbacksTab" 
                            class="tab-button border-b-2 border-transparent py-4 px-1 text-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-star mr-2"></i>Feedbacks
                    </button>
                    <button onclick="showTab('users')" id="usersTab" 
                            class="tab-button border-b-2 border-transparent py-4 px-1 text-center text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-users mr-2"></i>Manage Users
                    </button>
                </nav>
            </div>
        </div>

        <?php include 'components/reservation.php'; ?>

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
                            <div class="flex space-x-2">
                                <button onclick="openEditTableModal('<?php echo rawurlencode(json_encode($table)); ?>')"
                                        class="flex-1 inline-flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    <i class="fas fa-edit mr-2"></i>Edit
                                </button>
                                <a href="?delete_table=<?php echo $table['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this table?')"
                                   class="flex-1 block text-center bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    <i class="fas fa-trash mr-2"></i>Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

      
    <?php include 'components/manageuser.php'; ?>                
    <?php include 'components/feedback.php'; ?>
    <?php include 'modal/add-modal.php'; ?>
    <?php include 'modal/edit-modal.php'; ?>

    <!-- Hidden printable report content -->
    <div id="reportContent" style="display:none; max-width:800px; padding:20px; font-family: Arial, Helvetica, sans-serif;">
        <div style="text-align:center; margin-bottom:12px;">
            <h1 style="margin:0;">Coffee Table — Analytics Report</h1>
            <div style="color:#666; font-size:14px;">Generated: <?php echo date('F j, Y, g:i a'); ?></div>
        </div>

        <h2 style="font-size:18px; margin-top:18px;">Summary</h2>
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
            <tr>
                <td style="padding:8px; border:1px solid #ddd;">Total Users</td>
                <td style="padding:8px; border:1px solid #ddd; text-align:right"><?php echo (int)$total_users; ?></td>
            </tr>
            <tr>
                <td style="padding:8px; border:1px solid #ddd;">Total Tables</td>
                <td style="padding:8px; border:1px solid #ddd; text-align:right"><?php echo (int)$total_tables; ?></td>
            </tr>
            <tr>
                <td style="padding:8px; border:1px solid #ddd;">Total Reservations</td>
                <td style="padding:8px; border:1px solid #ddd; text-align:right"><?php echo (int)$total_reservations; ?></td>
            </tr>
            <tr>
                <td style="padding:8px; border:1px solid #ddd;">Pending Reservations</td>
                <td style="padding:8px; border:1px solid #ddd; text-align:right"><?php echo (int)$pending_reservations; ?></td>
            </tr>
            <tr>
                <td style="padding:8px; border:1px solid #ddd;">Total Revenue</td>
                <td style="padding:8px; border:1px solid #ddd; text-align:right">₱<?php echo number_format($total_revenue,2); ?></td>
            </tr>
        </table>

        <h2 style="font-size:18px; margin-top:18px;">Today (<?php echo htmlspecialchars($today_date); ?>)</h2>
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
            <tr>
                <td style="padding:8px; border:1px solid #ddd;">New Users Today</td>
                <td style="padding:8px; border:1px solid #ddd; text-align:right"><?php echo htmlspecialchars($users_today); ?></td>
            </tr>
            <tr>
                <td style="padding:8px; border:1px solid #ddd;">Total Reservations Today</td>
                <td style="padding:8px; border:1px solid #ddd; text-align:right"><?php echo htmlspecialchars($reservations_today); ?></td>
            </tr>
            <tr>
                <td style="padding:8px; border:1px solid #ddd;">Pending Reservations Today</td>
                <td style="padding:8px; border:1px solid #ddd; text-align:right"><?php echo htmlspecialchars($pending_today); ?></td>
            </tr>
            <tr>
                <td style="padding:8px; border:1px solid #ddd;">Revenue Today</td>
                <td style="padding:8px; border:1px solid #ddd; text-align:right">₱<?php echo number_format($revenue_today,2); ?></td>
            </tr>
        </table>

        <h2 style="font-size:18px; margin-top:18px;">Reservation Status Breakdown</h2>
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
            <thead>
                <tr>
                    <th style="text-align:left; padding:8px; border:1px solid #ddd;">Status</th>
                    <th style="text-align:right; padding:8px; border:1px solid #ddd;">Count</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($sc = $status_counts->fetch_assoc()): ?>
                <tr>
                    <td style="padding:8px; border:1px solid #ddd; text-transform:capitalize;"><?php echo htmlspecialchars($sc['status']); ?></td>
                    <td style="padding:8px; border:1px solid #ddd; text-align:right"><?php echo (int)$sc['cnt']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <h2 style="font-size:18px; margin-top:18px;">Top Reserved Tables</h2>
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
            <thead>
                <tr>
                    <th style="text-align:left; padding:8px; border:1px solid #ddd;">Table</th>
                    <th style="text-align:right; padding:8px; border:1px solid #ddd;">Reservations</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($tt = $top_tables->fetch_assoc()): ?>
                <tr>
                    <td style="padding:8px; border:1px solid #ddd;">Table <?php echo htmlspecialchars($tt['table_number']); ?></td>
                    <td style="padding:8px; border:1px solid #ddd; text-align:right"><?php echo (int)$tt['cnt']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        // load html2pdf library dynamically (CDN)
        (function(){
            var s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js';
            s.async = true;
            document.head.appendChild(s);
        })();

        function generateReportPDF() {
            const content = document.getElementById('reportContent');
            if (!content) return alert('Report content not found');

            // clone and make visible (but invisible to the user) for rendering
            const clone = content.cloneNode(true);
            clone.style.display = 'block';
            // place it on-screen with zero opacity so html2canvas/html2pdf can render it reliably
            clone.style.position = 'fixed';
            clone.style.left = '0';
            clone.style.top = '0';
            clone.style.width = '800px';
            clone.style.zIndex = '99999';
            clone.style.background = '#ffffff';
            clone.style.opacity = '0';
            clone.style.pointerEvents = 'none';
            document.body.appendChild(clone);

            const opt = {
                margin:       10,
                filename:     'analytics-report-'+(new Date()).toISOString().slice(0,10)+'.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // wait for html2pdf to be available
            const waitForLib = () => {
                if (window.html2pdf) {
                    try {
                        html2pdf().from(clone).set(opt).save().then(()=>{
                            document.body.removeChild(clone);
                        }).catch((err)=>{
                            document.body.removeChild(clone);
                            console.error(err);
                            alert('Failed to generate PDF');
                        });
                    } catch (e) {
                        document.body.removeChild(clone);
                        console.error(e);
                        alert('Failed to generate PDF');
                    }
                } else {
                    setTimeout(waitForLib, 200);
                }
            };
            waitForLib();
        }
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
            const modal = document.getElementById('addTableModal');
            if (modal) {
                modal.classList.remove('hidden');
            } else {
                alert('Error: Modal not found!');
            }
        }
        
        function closeAddTableModal() {
            document.getElementById('addTableModal').classList.add('hidden');
        }

        // Open Edit Modal and populate fields
        function openEditTableModal(tableJson) {
            try {
                // If we receive a URL-encoded JSON string, decode then parse
                let table;
                if (typeof tableJson === 'string') {
                    try {
                        const decoded = decodeURIComponent(tableJson);
                        table = JSON.parse(decoded);
                    } catch (e) {
                        // Fallback: maybe not encoded
                        table = JSON.parse(tableJson);
                    }
                } else {
                    table = tableJson;
                }
                document.getElementById('edit_table_id').value = table.id;
                document.getElementById('edit_table_number').value = table.table_number;
                document.getElementById('edit_capacity').value = table.capacity;
                document.getElementById('edit_location').value = table.location;
                document.getElementById('edit_price').value = table.price || 500.00;
                // show current image preview if available
                const preview = document.getElementById('editImagePreview');
                if (table.image_url) {
                    preview.src = table.image_url;
                    preview.classList.remove('hidden');
                } else {
                    preview.classList.add('hidden');
                }
                document.getElementById('editTableModal').classList.remove('hidden');
            } catch (e) {
                alert('Failed to open edit modal: ' + e.message);
                console.error('Edit modal error:', e);
            }
        }

        function closeEditTableModal() {
            document.getElementById('editTableModal').classList.add('hidden');
        }

        function openEditUserModal(userJson) {
            let user;
            try {
                if (typeof userJson === 'string') {
                    try {
                        // Support URL-encoded JSON
                        user = JSON.parse(decodeURIComponent(userJson));
                    } catch (e) {
                        // Fallback if not encoded
                        user = JSON.parse(userJson);
                    }
                } else {
                    user = userJson;
                }
            } catch (e) {
                alert('Failed to open edit modal: ' + e.message);
                console.error('Edit user modal error:', e, userJson);
                return;
            }
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_password').value = '';
            document.getElementById('editUserModal').classList.remove('hidden');
        }

        // Robust delegation: open Edit User modal from buttons with data-edit-user
        document.addEventListener('click', function(ev) {
            const btn = ev.target.closest('[data-edit-user]');
            if (!btn) return;
            ev.preventDefault();
            try {
                let user;
                // Prefer dataset fields to avoid JSON parsing pitfalls
                if (btn.dataset && btn.dataset.id) {
                    user = {
                        id: parseInt(btn.dataset.id, 10),
                        username: btn.dataset.username || '',
                        email: btn.dataset.email || '',
                        phone: btn.dataset.phone || '',
                        role: btn.dataset.role || 'user'
                    };
                } else if (btn.getAttribute('data-user')) {
                    // Fallback: try to parse JSON if present
                    const raw = btn.getAttribute('data-user') || '{}';
                    try { user = JSON.parse(raw); }
                    catch(e1) { try { user = JSON.parse(decodeURIComponent(raw)); } catch(e2) {
                        // Last resort: HTML decode then parse
                        const ta = document.createElement('textarea');
                        ta.innerHTML = raw;
                        user = JSON.parse(ta.value);
                    }}
                } else {
                    throw new Error('No user data on button');
                }
                openEditUserModal(user);
            } catch (e) {
                console.error('Failed to open edit dialog', e);
                alert('Unable to open edit dialog. Please refresh and try again.');
            }
        });

        function closeEditUserModal() {
            document.getElementById('editUserModal').classList.add('hidden');
        }
    </script>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" enctype="multipart/form-data">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-gray-900">Edit User</h3>
                            <button type="button" onclick="closeEditUserModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                        
                        <input type="hidden" name="user_id" id="edit_user_id">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                <input type="text" name="username" id="edit_username" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input type="email" name="email" id="edit_email" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                                <input type="text" name="phone" id="edit_phone" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password (leave blank to keep current)</label>
                                <input type="password" name="password" id="edit_password" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                       placeholder="Enter new password or leave blank">
                                <p class="text-xs text-gray-500 mt-1">Only fill this if you want to change the password</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="edit_user" value="1"
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-amber-600 text-base font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                        <button type="button" onclick="closeEditUserModal()" 
                                class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin-dashboard.js"></script>
    <script>
        // Check URL parameter and show correct tab on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                showTab(tab);
            }
        });
    </script>
</body>
</html>
