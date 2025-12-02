<?php
// Disable all caching for real-time data
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Date range filtering (use start/end GET parameters, default to today)
$start_date = isset($_GET['start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end_date = isset($_GET['end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end']) ? $_GET['end'] : $start_date;

// Users signups in range
$users_q = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE DATE(created_at) BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'");
$users_today = $users_q ? (int)$users_q->fetch_assoc()['cnt'] : 0;

// Reservations in range
$reservations_q = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE reservation_date BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'");
$reservations_today = $reservations_q ? (int)$reservations_q->fetch_assoc()['cnt'] : 0;

// Reservations by status
$status_counts = [];
$statuses = ['pending','confirmed','completed','cancelled','rejected'];
foreach ($statuses as $s) {
    $r = $conn->query("SELECT COUNT(*) as cnt FROM reservations WHERE reservation_date BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "' AND status = '" . $conn->real_escape_string($s) . "'");
    $status_counts[$s] = $r ? (int)$r->fetch_assoc()['cnt'] : 0;
}

// Revenue in range
$revenue_total = 0.00;
$payments_table_check = $conn->query("SHOW TABLES LIKE 'payments'");
if ($payments_table_check && $payments_table_check->num_rows > 0) {
    $rq = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM payments WHERE payment_status = 'completed' AND DATE(payment_date) BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'");
    $revenue_total = $rq ? (float)$rq->fetch_assoc()['total'] : 0.00;
} else {
    $rq = $conn->query("SELECT COALESCE(SUM(payment_amount),0) as total FROM reservations WHERE payment_status = 'paid' AND DATE(payment_date) BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'");
    $revenue_total = $rq ? (float)$rq->fetch_assoc()['total'] : 0.00;
}

// Payments breakdown by method (if payments table exists)
$payments_breakdown = [];
if ($payments_table_check && $payments_table_check->num_rows > 0) {
    $pb = $conn->query("SELECT payment_method, COALESCE(SUM(amount),0) as total, COUNT(*) as cnt FROM payments WHERE payment_status = 'completed' AND DATE(payment_date) BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "' GROUP BY payment_method");
    while ($row = $pb->fetch_assoc()) {
        $payments_breakdown[$row['payment_method']] = ['total' => (float)$row['total'], 'count' => (int)$row['cnt']];
    }
} else {
    // Fallback: group by reservations.payment_method if available
    $pb = $conn->query("SELECT payment_status, COALESCE(SUM(payment_amount),0) as total, COUNT(*) as cnt FROM reservations WHERE payment_status = 'paid' AND DATE(payment_date) BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "' GROUP BY payment_status");
    // no payment method data here, leave breakdown empty
}

// Top tables by reservation count in range
$top_tables = [];
$tt = $conn->query("SELECT t.table_number, t.id, COUNT(r.id) as cnt FROM reservations r JOIN tables t ON r.table_id = t.id WHERE r.reservation_date BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "' GROUP BY t.id ORDER BY cnt DESC LIMIT 5");
while ($row = $tt->fetch_assoc()) {
    $top_tables[] = $row;
}

// Guests statistics
$guest_stats = $conn->query("SELECT COALESCE(SUM(guests),0) as total_guests, COALESCE(AVG(guests),0) as avg_guests FROM reservations WHERE reservation_date BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'");
$guest_row = $guest_stats ? $guest_stats->fetch_assoc() : ['total_guests' => 0, 'avg_guests' => 0];

// Average table capacity (for context)
$cap_q = $conn->query("SELECT COALESCE(AVG(capacity),0) as avg_capacity FROM tables");
$avg_capacity = $cap_q ? (float)$cap_q->fetch_assoc()['avg_capacity'] : 0;

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Daily Report - <?php echo htmlspecialchars($start_date === $end_date ? $start_date : ($start_date . ' to ' . $end_date)); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{font-family:Arial,Helvetica,sans-serif}</style>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body class="bg-gray-50 p-6">
    <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-2xl font-bold">Daily Report</h1>
            <div class="space-x-2">
                <a href="admin_dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-2 rounded">Back</a>
                <button id="downloadPdf" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded inline-flex items-center"><i class="fas fa-file-pdf mr-2"></i>Download PDF</button>
                <button id="exportCsv" class="bg-gray-700 hover:bg-gray-800 text-white px-3 py-2 rounded inline-flex items-center"><i class="fas fa-file-csv mr-2"></i>Export CSV</button>
            </div>
        </div>

        <form method="GET" class="mb-4 flex items-end gap-3">
            <div>
                <label class="text-sm text-gray-600">Start Date</label>
                <input type="date" name="start" value="<?php echo htmlspecialchars($start_date); ?>" class="border p-2 rounded">
            </div>
            <div>
                <label class="text-sm text-gray-600">End Date</label>
                <input type="date" name="end" value="<?php echo htmlspecialchars($end_date); ?>" class="border p-2 rounded">
            </div>
            <div>
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded">Apply</button>
            </div>
        </form>

        <div id="reportVisible" style="background-color: white; padding: 20px;">
            <div style="text-align:center; margin-bottom:12px;">
                <h1 style="font-size:20px; font-weight:bold; margin:0;">Coffee Table — Report</h1>
                <p style="color:#666; margin-top:6px;">Date range: <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 border rounded">
                    <div class="text-sm text-gray-600">New Users</div>
                    <div class="text-2xl font-bold"><?php echo (int)$users_today; ?></div>
                </div>
                <div class="p-4 border rounded">
                    <div class="text-sm text-gray-600">Total Reservations</div>
                    <div class="text-2xl font-bold"><?php echo (int)$reservations_today; ?></div>
                </div>
                <div class="p-4 border rounded">
                    <div class="text-sm text-gray-600">Revenue</div>
                    <div class="text-2xl font-bold text-amber-600">₱<?php echo number_format($revenue_total,2); ?></div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="col-span-1 p-4 border rounded">
                    <h3 class="font-semibold mb-2">Reservation Status</h3>
                    <ul>
                        <?php foreach ($status_counts as $st => $cnt): ?>
                            <li class="flex justify-between py-1"><span class="capitalize"><?php echo htmlspecialchars($st); ?></span><span><?php echo (int)$cnt; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="col-span-1 p-4 border rounded">
                    <h3 class="font-semibold mb-2">Payments Breakdown</h3>
                    <?php if (!empty($payments_breakdown)): ?>
                        <ul>
                        <?php foreach ($payments_breakdown as $method => $data): ?>
                            <li class="flex justify-between py-1"><span><?php echo htmlspecialchars($method); ?></span><span>₱<?php echo number_format($data['total'],2); ?> (<?php echo $data['count']; ?>)</span></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">No payment breakdown available.</p>
                    <?php endif; ?>
                </div>

                <div class="col-span-1 p-4 border rounded">
                    <h3 class="font-semibold mb-2">Guests / Capacity</h3>
                    <div class="text-sm text-gray-600">Total Guests Reserved</div>
                    <div class="text-xl font-bold"><?php echo (int)$guest_row['total_guests']; ?></div>
                    <div class="text-sm text-gray-600 mt-2">Avg Guests / Reservation: <?php echo number_format((float)$guest_row['avg_guests'],2); ?></div>
                    <div class="text-sm text-gray-600">Avg Table Capacity: <?php echo number_format($avg_capacity,2); ?></div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="p-4 border rounded">
                    <h3 class="font-semibold mb-2">Top Tables (by reservations)</h3>
                    <canvas id="topTablesChart" width="400" height="200"></canvas>
                    <ul class="mt-3">
                        <?php foreach ($top_tables as $t): ?>
                            <li class="flex justify-between py-1">Table <?php echo htmlspecialchars($t['table_number']); ?><span><?php echo (int)$t['cnt']; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="p-4 border rounded">
                    <h3 class="font-semibold mb-2">Payments Chart</h3>
                    <canvas id="paymentsChart" width="400" height="200"></canvas>
                </div>
            </div>

        </div>
    </div>

    <script>
        (function(){
            var s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js';
            s.async = true; document.head.appendChild(s);

            var c = document.createElement('script');
            c.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            c.async = true; document.head.appendChild(c);
        })();

        document.getElementById('downloadPdf').addEventListener('click', function(){
            const content = document.getElementById('reportVisible');
            if (!content) return alert('Report content not found');
            const clone = content.cloneNode(true);
            clone.style.display = 'block';
            clone.style.position = 'fixed';
            clone.style.left = '0'; clone.style.top = '0';
            clone.style.width = '800px'; clone.style.zIndex = '99999';
            clone.style.background = '#ffffff'; clone.style.opacity = '0'; clone.style.pointerEvents = 'none';
            document.body.appendChild(clone);

            const opt = { margin:10, filename: 'daily-report-'+(new Date()).toISOString().slice(0,10)+'.pdf', image:{type:'jpeg',quality:0.98}, html2canvas:{scale:2, backgroundColor:'#ffffff', logging:false}, jsPDF:{unit:'mm',format:'a4',orientation:'portrait'} };

            const waitForLib = () => {
                if (window.html2pdf) {
                    html2pdf().set(opt).from(clone).save().then(()=>{
                        setTimeout(() => document.body.removeChild(clone), 100);
                    }).catch((e)=>{
                        console.error(e);
                        document.body.removeChild(clone);
                        alert('Failed to generate PDF: ' + e.message);
                    });
                } else setTimeout(waitForLib,200);
            };
            waitForLib();
        });

        // Prepare chart data from PHP
        const topTablesData = <?php echo json_encode(array_column($top_tables, 'cnt')); ?> || [];
        const topTablesLabels = <?php echo json_encode(array_map(function($r){ return 'Table ' . $r['table_number']; }, $top_tables)); ?> || [];

        const paymentsLabels = <?php echo json_encode(array_keys($payments_breakdown)); ?> || [];
        const paymentsTotals = <?php echo json_encode(array_map(function($p){ return $p['total']; }, $payments_breakdown)); ?> || [];

        // Render charts once Chart.js is available
        function renderCharts(){
            if (!window.Chart) return setTimeout(renderCharts, 200);

            const ctxTop = document.getElementById('topTablesChart').getContext('2d');
            new Chart(ctxTop, {
                type: 'bar',
                data: {
                    labels: topTablesLabels,
                    datasets: [{ label: 'Reservations', data: topTablesData, backgroundColor: '#f59e0b' }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });

            const ctxPay = document.getElementById('paymentsChart').getContext('2d');
            new Chart(ctxPay, {
                type: 'pie',
                data: {
                    labels: paymentsLabels,
                    datasets: [{ data: paymentsTotals, backgroundColor: ['#10b981','#3b82f6','#f97316','#ef4444'] }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
        renderCharts();

        // CSV export: compile summary + top tables + payments
        document.getElementById('exportCsv').addEventListener('click', function(){
            const rows = [];
            rows.push(['Report','Date Range','<?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?>']);
            rows.push([]);
            rows.push(['Metric','Value']);
            rows.push(['New Users','<?php echo (int)$users_today; ?>']);
            rows.push(['Total Reservations','<?php echo (int)$reservations_today; ?>']);
            rows.push(['Revenue','<?php echo number_format($revenue_total,2); ?>']);
            rows.push([]);
            rows.push(['Reservation Status','Count']);
            <?php foreach ($status_counts as $st => $cnt): ?>
                rows.push(['<?php echo htmlspecialchars($st); ?>','<?php echo (int)$cnt; ?>']);
            <?php endforeach; ?>
            rows.push([]);
            rows.push(['Top Tables','Reservations']);
            <?php foreach ($top_tables as $t): ?>
                rows.push(['Table <?php echo htmlspecialchars($t['table_number']); ?>','<?php echo (int)$t['cnt']; ?>']);
            <?php endforeach; ?>
            rows.push([]);
            rows.push(['Payments Breakdown','Total']);
            <?php foreach ($payments_breakdown as $method => $data): ?>
                rows.push(['<?php echo htmlspecialchars($method); ?>','<?php echo number_format($data['total'],2); ?>']);
            <?php endforeach; ?>

            // Convert to CSV
            const csvContent = rows.map(r => r.map(c=> '"'+String(c).replace(/"/g,'""')+'"').join(',')).join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'daily-report-<?php echo htmlspecialchars($start_date); ?>_to_<?php echo htmlspecialchars($end_date); ?>.csv';
            document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
        });
    </script>
</body>
</html>
