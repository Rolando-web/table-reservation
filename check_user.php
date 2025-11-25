<?php
require_once 'db.php';

echo "<h1>Database User Check</h1>";

// Check all users
$result = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY id");

echo "<h2>All Users:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created At</th></tr>";

while ($user = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
    echo "<td>" . $user['role'] . "</td>";
    echo "<td>" . $user['created_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Check reservations count
$result = $conn->query("SELECT COUNT(*) as count FROM reservations");
$count = $result->fetch_assoc()['count'];
echo "<h2>Total Reservations: " . $count . "</h2>";

// Check if user@gmail.com exists
$stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
$email = 'user@gmail.com';
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<h2>User 'user@gmail.com' Found!</h2>";
    echo "<p>ID: " . $user['id'] . "</p>";
    echo "<p>Username: " . htmlspecialchars($user['username']) . "</p>";
} else {
    echo "<h2 style='color: red;'>User 'user@gmail.com' NOT FOUND!</h2>";
    echo "<p>You need to register this account first.</p>";
    echo "<p><a href='register.php'>Register Here</a></p>";
}

echo "<br><br>";
echo "<a href='index.php'>Back to Home</a> | ";
echo "<a href='login.php'>Login</a> | ";
echo "<a href='register.php'>Register</a>";
?>
