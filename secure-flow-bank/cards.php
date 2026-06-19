<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();
$user  = getCurrentUser($conn);
$unreadNotifications = getUnreadNotifications($conn, $user['id']);
$accounts = getUserAccounts($conn, $user['id']);
$error = $success = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='add') {
    requireCsrf();
    checkHoneypot();
    $accId    = (int)($_POST['account_id'] ?? 0);
    $cardType = sanitizeInput($_POST['card_type'] ?? 'visa');

    // Verify account belongs to user
    $chk = $conn->prepare("SELECT id FROM accounts WHERE id=? AND user_id=? AND status='active'");
    $chk->bind_param("ii", $accId, $user['id']);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $error = 'الحساب غير صالح.';
    } elseif (!in_array($cardType,['visa','mastercard'])) {
        $error = 'نوع البطاقة غير صالح.';
    } else {
        $rawNum  = ($cardType==='visa'?'4':'5') . implode('',array_map(fn($i)=>rand(0,9),range(1,15)));
        $expiry  = date('m/y', strtotime('+3 years'));
        $cvv     = rand(100,999);
        $encNum  = encryptData($rawNum);
        $encCvv  = encryptData((string)$cvv);
        $masked  = '**** **** **** ' . substr($rawNum,-4);
        $stmt    = $conn->prepare("INSERT INTO cards (user_id, account_id, card_number, card_number_masked, card_type, expiry_date, cvv, status) VALUES (?,?,?,?,?,?,?,'active')");
        $stmt->bind_param("iisssss", $user['id'], $accId, $encNum, $masked, $cardType, $expiry, $encCvv);
        $stmt->execute() ? $success='تم إصدار البطاقة بنجاح.' : $error='حدث خطأ. حاول لاحقاً.';
    }
}

$cards    = getUserCards($conn, $user['id']);
$pageTitle = 'البطاقات';
include 'includes/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<?php if (!empty($accounts)): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-plus-circle"></i> إصدار بطاقة جديدة</h3></div>
    <form method="POST" style="padding:16px 0 0;">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="action" value="add">
        <?= honeypotField() ?>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <select name="account_id" class="form-control" style="max-width:260px;">
                <?php foreach ($accounts as $acc): ?>
                <option value="<?= $acc['id'] ?>"><?= getAccountTypeName($acc['type']) ?> — <?= maskAccountNumber($acc['account_number']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="card_type" class="form-control" style="max-width:160px;">
                <option value="visa">Visa</option>
                <option value="mastercard">Mastercard</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> إصدار البطاقة</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="cards-grid">
<?php if (empty($cards)): ?>
<div class="empty-state"><i class="fas fa-credit-card"></i><p>لا توجد بطاقات. أصدر بطاقتك الأولى!</p></div>
<?php else: ?>
<?php foreach ($cards as $card): ?>
<div class="bank-card <?= $card['card_type'] ?>">
    <div class="bank-card-header">
        <div class="bank-card-chip"><i class="fas fa-microchip"></i></div>
        <div class="bank-card-type"><?= strtoupper($card['card_type']) ?></div>
    </div>
    <div class="bank-card-number"><?= htmlspecialchars($card['card_number_masked']) ?></div>
    <div class="bank-card-footer">
        <div><small>صلاحية</small><br><?= htmlspecialchars($card['expiry_date']) ?></div>
        <div><small>الحساب</small><br><?= maskAccountNumber($card['account_number']) ?></div>
        <div class="card-status-badge <?= $card['status']==='active'?'active':'inactive' ?>"><?= $card['status']==='active'?'نشطة':'موقوفة' ?></div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
