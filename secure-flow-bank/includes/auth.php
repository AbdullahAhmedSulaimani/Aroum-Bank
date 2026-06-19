<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) { header('Location: index.php'); exit(); }
    hardenSession();
}

function requireGuest(): void {
    if (isLoggedIn()) { header('Location: dashboard.php'); exit(); }
}

function getCurrentUser(mysqli $conn): ?array {
    if (!isLoggedIn()) return null;
    $id   = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, full_name, email, phone, status, created_at FROM users WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

// Returns: 'ok' | 'fail' | 'locked' | '2fa_required'
function loginUser(mysqli $conn, string $email, string $password): string {
    $ip = getClientIp();
    if (isRateLimited($conn, $ip)) return 'locked';

    $stmt = $conn->prepare("SELECT id, full_name, email, password, status, two_fa_enabled FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        recordFailedLogin($conn, $ip, $email);
        return 'fail';
    }
    if ($user['status'] !== 'active') return 'fail';

    clearLoginAttempts($conn, $ip);

    if (!empty($user['two_fa_enabled'])) {
        $_SESSION['2fa_pending_user_id'] = $user['id'];
        $_SESSION['2fa_pending_name']    = $user['full_name'];
        return '2fa_required';
    }

    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email']= $user['email'];
    return 'ok';
}

function logoutUser(): void {
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
    header('Location: index.php');
    exit();
}
