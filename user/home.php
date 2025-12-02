<?php
require_once __DIR__ . '/../includes/auth.php';
requireUser();
require_once __DIR__ . '/../includes/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'User';

// Simple stats for the user
$stmt = $conn->prepare("SELECT COUNT(*) as upcoming FROM reservations WHERE user_id = ? AND reservation_date >= CURDATE() AND status IN ('pending','confirmed')");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming = $stmt->get_result()->fetch_assoc()['upcoming'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as unpaid FROM reservations WHERE user_id = ? AND payment_status = 'unpaid'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unpaid = $stmt->get_result()->fetch_assoc()['unpaid'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Welcome — Coffee Table</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hero-bg { background-image: linear-gradient(135deg, #fff7ed 0%, #fff1e6 100%); }
    </style>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body class="bg-gradient-to-br from-amber-50 to-orange-100 min-h-screen">
    <nav class="bg-white shadow sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-coffee text-amber-600 text-2xl"></i>
                    <span class="font-bold text-gray-800 text-xl">Coffee Table</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700 hidden sm:inline"><i class="fas fa-user mr-2"></i><?php echo htmlspecialchars($username); ?></span>
                    <a href="user_dashboard.php" class="text-gray-700 hover:text-amber-600">Dashboard</a>
                    <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <header class="hero-bg py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
            <div>
                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 leading-tight">Welcome back, <span class="text-amber-600"><?php echo htmlspecialchars($username); ?></span></h1>
                <p class="mt-4 text-lg text-gray-700">Easily reserve your favourite spot, manage bookings, and view receipts in one place.</p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="user_dashboard.php" class="inline-flex items-center px-6 py-3 bg-amber-600 text-white rounded-lg shadow hover:bg-amber-700">
                        <i class="fas fa-calendar-check mr-2"></i>View Dashboard
                    </a>
                    <a href="user_dashboard.php#reserve" class="inline-flex items-center px-6 py-3 bg-white border border-amber-600 text-amber-600 rounded-lg shadow hover:bg-amber-50">
                        <i class="fas fa-chair mr-2"></i>Make Reservation
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow p-6">
                <h3 class="text-sm text-gray-500">Your quick stats</h3>
                <div class="mt-4 grid grid-cols-3 gap-4">
                    <div class="p-4 bg-amber-50 rounded-lg text-center">
                        <div class="text-sm text-gray-600">Upcoming</div>
                        <div class="text-2xl font-bold text-gray-900" id="statUpcoming"><?php echo (int)$upcoming; ?></div>
                    </div>
                    <div class="p-4 bg-white rounded-lg border text-center">
                        <div class="text-sm text-gray-600">Unpaid</div>
                        <div class="text-2xl font-bold text-gray-900" id="statUnpaid"><?php echo (int)$unpaid; ?></div>
                    </div>
                    <div class="p-4 bg-white rounded-lg border text-center">
                        <div class="text-sm text-gray-600">Account</div>
                        <div class="text-sm text-gray-700 mt-2"><?php echo htmlspecialchars($_SESSION['email'] ?? '—'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-12">
        <!-- Features / Gallery -->
        <section class="mb-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Featured Tables</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php
                // show 4 featured or recent tables
                $featured = $conn->query("SELECT * FROM tables ORDER BY id DESC LIMIT 4");
                while ($f = $featured->fetch_assoc()): ?>
                    <div class="bg-white rounded-xl shadow overflow-hidden">
                        <div class="h-40 overflow-hidden">
                            <img src="<?php echo htmlspecialchars($f['image_url'] ?: '../assets/images/tables/default.jpg'); ?>" alt="Table <?php echo htmlspecialchars($f['table_number']); ?>" class="w-full h-full object-cover">
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="font-semibold text-gray-800">Table <?php echo htmlspecialchars($f['table_number']); ?></h3>
                                    <div class="text-xs text-gray-500 mt-1"><i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($f['location']); ?></div>
                                </div>
                                <div class="text-sm text-amber-600 font-bold"><?php echo $f['capacity']; ?> seats</div>
                            </div>
                            <div class="mt-4">
                                <a href="user_dashboard.php#reserve" class="inline-flex items-center px-3 py-2 bg-amber-600 text-white rounded-lg">Reserve</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>

        <!-- Recent Reservations -->
        <section>
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Recent Reservations</h2>
            <div class="bg-white rounded-xl shadow p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-2">Table</th>
                                <th class="px-4 py-2">Date</th>
                                <th class="px-4 py-2">Time</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $conn->prepare("SELECT r.id, t.table_number, r.reservation_date, r.reservation_time, r.status FROM reservations r JOIN tables t ON r.table_id = t.id WHERE r.user_id = ? ORDER BY r.reservation_date DESC, r.reservation_time DESC LIMIT 6");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $rows = $stmt->get_result();
                            if ($rows->num_rows > 0):
                                while ($row = $rows->fetch_assoc()):
                            ?>
                                <tr class="border-t">
                                    <td class="px-4 py-3">Table <?php echo htmlspecialchars($row['table_number']); ?></td>
                                    <td class="px-4 py-3"><?php echo date('M d, Y', strtotime($row['reservation_date'])); ?></td>
                                    <td class="px-4 py-3"><?php echo date('h:i A', strtotime($row['reservation_time'])); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                                    <td class="px-4 py-3">
                                        <a href="user_dashboard.php?view=<?php echo $row['id']; ?>" class="text-amber-600">View</a>
                                    </td>
                                </tr>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No recent reservations</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-white border-t mt-12">
        <div class="max-w-7xl mx-auto px-4 py-6 text-center text-sm text-gray-600">
            Contact: <a href="mailto:Mintal@gmail.com" class="text-amber-600">Mintal@gmail.com</a>
        </div>
    </footer>

    <script>
        // simple counter animation for stats
        function animateCount(id, end) {
            const el = document.getElementById(id);
            if (!el) return;
            const start = 0;
            const duration = 700;
            let startTime = null;
            function step(timestamp) {
                if (!startTime) startTime = timestamp;
                const progress = Math.min((timestamp - startTime) / duration, 1);
                el.textContent = Math.floor(progress * (end - start) + start);
                if (progress < 1) window.requestAnimationFrame(step);
            }
            window.requestAnimationFrame(step);
        }
        animateCount('statUpcoming', <?php echo (int)$upcoming; ?>);
        animateCount('statUnpaid', <?php echo (int)$unpaid; ?>);
    </script>
</body>
</html>
