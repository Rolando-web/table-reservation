<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isAuthenticated(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function currentUserRole(): ?string {
    return $_SESSION['role'] ?? null;
}

function requireUser() {
    if (!isAuthenticated() || $_SESSION['role'] !== 'user') {
        header('Location: ../login.php');
        exit();
    }
}

function requireAdmin() {
    if (!isAuthenticated() || $_SESSION['role'] !== 'admin') {
        header('Location: ../login.php');
        exit();
    }
}

function redirectIfLoggedIn() {
    if (isAuthenticated()) {
        $redirect = ($_SESSION['role'] === 'admin') ? 'admin/admin_dashboard.php' : 'user/home.php';
        header('Location: ' . $redirect);
        exit();
    }
}
