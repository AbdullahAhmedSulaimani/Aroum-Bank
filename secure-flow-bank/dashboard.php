<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user         = getCurrentUser($conn);
$accounts     = getUserAccounts($conn, $user['id']);
$cards        = getUserCards($conn, $user['id']);
$transactions = getRecentTransactions($conn, $user['id'], 6);
$totalBalance = getTotalBalance($conn, $user['id']);
$unreadNotifications = getUnreadNotifications($conn, $user['id']);

$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN t.type='deposit' AND MONTH(t.created_at)=MONTH(NOW()) AND a.user_id=? THEN t.amount ELSE 0 END) AS total_in,
        SUM(CASE WHEN t.type IN ('withdrawal','payment') AND MONTH(t.created_at)=MONTH(NOW()) AND a.user_id=? THEN t.amount ELSE 0 END) AS total_out,
        COUNT(CASE WHEN MONTH(t.created_at)=MONTH(NOW()) THEN 1 END) AS tx_count
    FROM transactions t
    JOIN accounts a ON (t.from_account_id = a.id OR t.to_account_id = a.id)
    WHERE a.user_id = ?
");
$stmt->bind_param("iii", $user['id'], $user['id'], $user['id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$pageTitle    = 'لوحة التحكم';
$pageSubtitle = 'مرحباً، ' . $user['full_name'];
include 'includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card primary">
        <div class="stat-header">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <span class="stat-badge" style="background:rgba(255,255,255,0.2);color:white;">إجمالي</span>
        </div>
        <div class="stat-value"><?= number_format($totalBalance, 2) ?></div>
        <div class="stat-label">الرصيد الكلي (ريال سعودي)</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon green"><i class="fas fa-arrow-down"></i></div><span class="stat-badge">هذا الشهر</span></div>
        <div class="stat-value"><?= number_format($stats['total_in'] ?? 0, 2) ?></div>
        <div class="stat-label">إجمالي الوارد (ر.س)</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon red"><i class="fas fa-arrow-up"></i></div><span class="stat-badge" style="background:var(--red-light);color:var(--red);">هذا الشهر</span></div>
        <div class="stat-value"><?= number_format($stats['total_out'] ?? 0, 2) ?></div>
        <div class="stat-label">إجمالي الصادر (ر.س)</div>
    </div>
    <div class="stat-card">
        <div class="stat-header"><div class="stat-icon orange"><i class="fas fa-receipt"></i></div></div>
        <div class="stat-value"><?= (int)($stats['tx_count'] ?? 0) ?></div>
        <div class="stat-label">عمليات هذا الشهر</div>
    </div>
</div>

<div class="quick-actions">
    <a href="transfers.php" class="quick-action-btn"><i class="fas fa-arrow-right-arrow-left text-blue"></i><span>تحويل</span></a>
    <a href="accounts.php?action=add" class="quick-action-btn"><i class="fas fa-plus-circle text-green"></i><span>حساب جديد</span></a>
    <a href="cards.php?action=add" class="quick-action-btn"><i class="fas fa-credit-card text-orange"></i><span>بطاقة جديدة</span></a>
    <a href="transactions.php" class="quick-action-btn"><i class="fas fa-clock-rotate-left" style="color:var(--primary);"></i><span>السجل</span></a>
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-wallet"></i> حساباتي</h3><a href="accounts.php" class="btn btn-sm">عرض الكل</a></div>
        <?php if (empty($accounts)): ?>
        <div class="empty-state"><i class="fas fa-wallet"></i><p>لا توجد حسابات بعد</p><a href="accounts.php?action=add" class="btn btn-primary btn-sm">إضافة حساب</a></div>
        <?php else: ?>
        <?php foreach ($accounts as $acc): ?>
        <div class="account-item">
            <div class="account-info">
                <div class="account-type"><?= getAccountTypeName($acc['type']) ?></div>
                <div class="account-num"><?= maskAccountNumber($acc['account_number']) ?></div>
            </div>
            <div class="account-balance"><?= number_format($acc['balance'], 2) ?> <small>ر.س</small></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-clock-rotate-left"></i> آخر المعاملات</h3><a href="transactions.php" class="btn btn-sm">عرض الكل</a></div>
        <?php if (empty($transactions)): ?>
        <div class="empty-state"><i class="fas fa-receipt"></i><p>لا توجد معاملات بعد</p></div>
        <?php else: ?>
        <?php foreach ($transactions as $tx): ?>
        <div class="tx-item">
            <div class="tx-icon <?= getTransactionColor($tx['type']) ?>"><i class="fas <?= getTransactionIcon($tx['type']) ?>"></i></div>
            <div class="tx-info">
                <div class="tx-desc"><?= htmlspecialchars($tx['description'] ?? $tx['type']) ?></div>
                <div class="tx-date"><?= date('d/m/Y', strtotime($tx['created_at'])) ?></div>
            </div>
            <div class="tx-amount <?= in_array($tx['type'],['deposit']) ? 'text-green' : 'text-red' ?>">
                <?= in_array($tx['type'],['deposit'])?'+':'-' ?><?= number_format($tx['amount'], 2) ?> ر.س
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
