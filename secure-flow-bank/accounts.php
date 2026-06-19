<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
$user  = getCurrentUser($conn);
$unreadNotifications = getUnreadNotifications($conn, $user['id']);
$error = $success = '';

// Add new account
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='add') {
    requireCsrf();
    checkHoneypot();
    $type = sanitizeInput($_POST['type'] ?? '');
    $allowed = ['checking','savings','investment'];
    if (!in_array($type, $allowed)) {
        $error = 'نوع الحساب غير صالح.';
    } else {
        $accNum = 'SA'.rand(10,99).'80000000'.rand(100000000,999999999);
        $stmt   = $conn->prepare("INSERT INTO accounts (user_id, account_number, type, balance, status) VALUES (?,?,'". $conn->real_escape_string($type) ."',0.00,'active')");
        $stmt->bind_param("is", $user['id'], $accNum);
        $stmt->execute() ? $success='تم إنشاء الحساب بنجاح.' : $error='حدث خطأ. حاول لاحقاً.';
    }
}

$accounts = getUserAccounts($conn, $user['id']);
$pageTitle = 'حساباتي';
include 'includes/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus-circle"></i> فتح حساب جديد</h3>
    </div>
    <form method="POST" style="padding:16px 0 0;">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="action" value="add">
        <?= honeypotField() ?>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <select name="type" class="form-control" style="max-width:220px;">
                <option value="checking">حساب جاري</option>
                <option value="savings">حساب توفير</option>
                <option value="investment">حساب استثمار</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> فتح الحساب</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-wallet"></i> حساباتي</h3></div>
    <?php if (empty($accounts)): ?>
    <div class="empty-state"><i class="fas fa-wallet"></i><p>لا توجد حسابات. أنشئ حسابك الأول!</p></div>
    <?php else: ?>
    <div class="accounts-list">
    <?php foreach ($accounts as $acc): ?>
    <div class="account-card-full">
        <div class="account-card-header">
            <div>
                <div class="account-type-badge"><?= getAccountTypeName($acc['type']) ?></div>
                <div class="account-number"><?= htmlspecialchars($acc['account_number']) ?></div>
            </div>
            <div class="account-status <?= $acc['status']==='active'?'active':'inactive' ?>"><?= $acc['status']==='active'?'نشط':'موقوف' ?></div>
        </div>
        <div class="account-balance-big"><?= number_format($acc['balance'], 2) ?> <span>ر.س</span></div>
        <div class="account-date">تاريخ الفتح: <?= date('d/m/Y', strtotime($acc['created_at'])) ?></div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
