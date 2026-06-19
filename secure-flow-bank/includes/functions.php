<?php
// =============================================
// Aurum Bank - Helper Functions
// =============================================

function formatCurrency($amount, $currency = 'SAR') {
    return number_format($amount, 2) . ' ' . $currency;
}

function generateAccountNumber() {
    return 'SA' . rand(10, 99) . '80000000' . rand(100000000, 999999999);
}

function generateCardNumber($type = 'visa') {
    $prefix = $type === 'visa' ? '4' : '5';
    $number = $prefix;
    for ($i = 0; $i < 15; $i++) {
        $number .= rand(0, 9);
    }
    return chunk_split($number, 4, ' ');
}

function generateReference() {
    return 'REF-' . date('Y') . '-' . strtoupper(substr(uniqid(), -8));
}

function maskCardNumber($number) {
    $clean = str_replace(' ', '', $number);
    return '**** **** **** ' . substr($clean, -4);
}

function maskAccountNumber($number) {
    return substr($number, 0, 4) . '****' . substr($number, -4);
}

function getUserAccounts($conn, $userId) {
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE user_id = ? AND status = 'active' ORDER BY created_at ASC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getUserCards($conn, $userId) {
    $stmt = $conn->prepare("SELECT c.*, a.account_number FROM cards c JOIN accounts a ON c.account_id = a.id WHERE c.user_id = ? ORDER BY c.created_at ASC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getRecentTransactions($conn, $userId, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT t.*,
               fa.account_number AS from_account_num,
               ta.account_number AS to_account_num
        FROM transactions t
        LEFT JOIN accounts fa ON t.from_account_id = fa.id
        LEFT JOIN accounts ta ON t.to_account_id = ta.id
        WHERE fa.user_id = ? OR ta.user_id = ?
        ORDER BY t.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("iii", $userId, $userId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getTotalBalance($conn, $userId) {
    $stmt = $conn->prepare("SELECT SUM(balance) AS total FROM accounts WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['total'] ?? 0;
}

function getTransactionIcon($type) {
    $icons = [
        'transfer'   => 'fa-exchange-alt',
        'deposit'    => 'fa-arrow-down',
        'withdrawal' => 'fa-arrow-up',
        'payment'    => 'fa-credit-card',
    ];
    return $icons[$type] ?? 'fa-circle';
}

function getTransactionColor($type) {
    $colors = [
        'transfer'   => 'text-blue',
        'deposit'    => 'text-green',
        'withdrawal' => 'text-red',
        'payment'    => 'text-orange',
    ];
    return $colors[$type] ?? 'text-gray';
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function getUnreadNotifications($conn, $userId) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['cnt'] ?? 0;
}

function addNotification($conn, $userId, $title, $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $title, $message);
    $stmt->execute();
}

function getAccountTypeName($type) {
    $types = [
        'checking'   => 'حساب جاري',
        'savings'    => 'حساب توفير',
        'investment' => 'حساب استثمار',
    ];
    return $types[$type] ?? $type;
}
