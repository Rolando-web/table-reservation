<?php
// Enable output compression
if (!ob_start("ob_gzhandler")) ob_start();

session_start();
require_once 'includes/db.php';

$error = '';
$success = '';

$step = (int)($_POST['step'] ?? 1);
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        if (empty($email)) {
            $error = 'Please enter your email address';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 1) {
                $_SESSION['reset_email'] = $email;
                $step = 2; // proceed to new password form
            } else {
                $error = 'Email not found. Please check and try again.';
            }
        }
    } elseif ($step === 2) {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $session_email = $_SESSION['reset_email'] ?? '';

        if (empty($session_email)) {
            $error = 'Your reset session expired. Please start again.';
            $step = 1;
        } elseif (empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in the new password fields';
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match';
            $step = 2;
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long';
            $step = 2;
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed, $session_email);
            if ($stmt->execute()) {
                unset($_SESSION['reset_email']);
                $_SESSION['success'] = 'Password updated! You can now sign in.';
                header('Location: login.php', true, 302);
                exit();
            } else {
                $error = 'Failed to update password. Please try again.';
                $step = 2;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Coffee Table Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body class="bg-gradient-to-br from-amber-50 to-orange-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <div class="mx-auto h-20 w-20 bg-amber-600 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-key text-white text-3xl"></i>
                </div>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                    Reset your password
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Enter your email to continue.
                </p>
            </div>

            <div class="bg-white py-8 px-6 shadow-2xl rounded-xl sm:px-10">
                <?php if ($error): ?>
                    <div class="mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-4 bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                    <form class="space-y-6" method="POST" action="">
                        <input type="hidden" name="step" value="1">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">
                                Email address
                            </label>
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input id="email" name="email" type="email" required
                                       class="appearance-none block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                                       placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>">
                            </div>
                        </div>

                        <button type="submit"
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition duration-150 ease-in-out">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-arrow-right text-amber-500 group-hover:text-amber-400"></i>
                            </span>
                            Continue
                        </button>
                    </form>
                <?php else: ?>
                    <form class="space-y-6" method="POST" action="">
                        <input type="hidden" name="step" value="2">
                        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded">
                            <i class="fas fa-envelope mr-2"></i>
                            Resetting password for <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></span>
                        </div>
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700">
                                New password
                            </label>
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input id="new_password" name="new_password" type="password" required
                                       class="appearance-none block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                                       placeholder="••••••••">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Minimum 6 characters</p>
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                                Confirm new password
                            </label>
                            <div class="mt-1 relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input id="confirm_password" name="confirm_password" type="password" required
                                       class="appearance-none block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                                       placeholder="••••••••">
                            </div>
                        </div>

                        <button type="submit"
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition duration-150 ease-in-out">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-save text-amber-500 group-hover:text-amber-400"></i>
                            </span>
                            Save new password
                        </button>
                    </form>
                <?php endif; ?>

                <div class="mt-6 text-center">
                    <a href="login.php" class="text-sm text-amber-600 hover:text-amber-800 transition">
                        <i class="fas fa-arrow-left mr-1"></i> Back to sign in
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
