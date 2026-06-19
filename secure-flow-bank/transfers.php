<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/otp_helper.php';

requireLogin();
$user     = getCurrentUser($conn);
$unreadNotifications = getUnreadNotifications($conn, $user['id']);
$accounts = getUserAccounts($conn, $user['id']);
$error = $success = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    checkHoneypot();
    $action = $_POST['action'] ?? '';

    if ($action === 'initiate') {
        $fromId = (int)($_POST['from_account_id'] ?? 0);
        $toNum  = sanitizeInput($_POST['to_account_number'] ?? '');
        $amount = validateAmount($_POST['amount'] ?? '');
        $desc   = sanitizeInput($_POST['description'] ?? '', 200);

        if (!$amount) { $error = 'المبلغ غير صالح.'; }
        elseif (empty($toNum)) { $error = 'يرجى ادخال رقم حساب المستفيد.'; }
        else {
            $s = $conn->prepare("SELECT id,balance,account_number FROM accounts WHERE id=? AND user_id=? AND status='active'");
            $s->bind_param("ii", $fromId, $user['id']); $s->execute();
            $fromAcc = $s->get_result()->fetch_assoc();
            if (!$fromAcc) { $error = 'الحساب المصدر غير صالح.'; }
            elseif ($fromAcc['balance'] < $amount) { $error = 'الرصيد غير كافٍ.'; }
            elseif ($fromAcc['account_number'] === $toNum) { $error = 'لا يمكن التحويل لنفس الحساب.'; }
            else {
                $s2 = $conn->prepare("SELECT id FROM accounts WHERE account_number=? AND status='active'");
                $s2->bind_param("s", $toNum); $s2->execute();
                $toAcc = $s2->get_result()->fetch_assoc();
                if (!$toAcc) { $error = 'حساب المستفيد غير موجود.'; }
                else {
                    $ref = generateReference();
                    $otp = generateTransferOtp($conn, $user['id'], $ref);
                    $_SESSION['transfer_pending'] = ['from_id'=>$fromId,'to_id'=>$toAcc['id'],'amount'=>$amount,'desc'=>$desc,'ref'=>$ref];
                    $step = 2;
                    $success = 'رمز التحقق للتجربة: <strong>' . $otp . '</strong>';
                }
            }
        }
        if ($error) $step = 1;
    }

    if ($action === 'confirm') {
        $otp     = sanitizeInput($_POST['otp'] ?? '');
        $pending = $_SESSION['transfer_pending'] ?? null;
        if (!$pending) { $error = 'انتهت جلسة التحويل. اعد العملية.'; }
        elseif (!verifyTransferOtp($conn, $user['id'], $otp)) { $error = 'رمز التحقق غير صحيح او منتهي.'; $step = 2; }
        else {
            $conn->begin_transaction();
            try {
                $s1 = $conn->prepare("UPDATE accounts SET balance=balance-? WHERE id=? AND balance>=?");
                $s1->bind_param("did", $pending['amount'], $pending['from_id'], $pending['amount']); $s1->execute();
                if ($s1->affected_rows !== 1) throw new Exception('الرصيد غير كافٍ.');
                $s2 = $conn->prepare("UPDATE accounts SET balance=balance+? WHERE id=?");
                $s2->bind_param("di", $pending['amount'], $pending['to_id']); $s2->execute();
                $s3 = $conn->prepare("INSERT INTO transactions (from_account_id,to_account_id,type,amount,description,reference,status,created_at) VALUES (?,?,'transfer',?,?,?,'completed',NOW())");
                $s3->bind_param("iidss", $pending['from_id'], $pending['to_id'], $pending['amount'], $pending['desc'], $pending['ref']); $s3->execute();
                $conn->commit();
                unset($_SESSION['transfer_pending']);
                logSecurityEvent($conn, 'transfer', 'Amount:'.$pending['amount'].' Ref:'.$pending['ref']);
                $success = 'تم التحويل بنجاح! المرجع: <strong>'.$pending['ref'].'</strong>';
            } catch (Exception $e) { $conn->rollback(); $error = $e->getMessage(); $step = 2; }
        }
    }
}

$pageTitle = 'التحويلات';
include 'includes/header.php';
?>
<style>
.transfer-wrap{max-width:560px;margin:0 auto}
.transfer-steps{display:flex;align-items:center;gap:0;margin-bottom:32px;background:#fff;border-radius:14px;padding:20px 28px;box-shadow:0 2px 12px rgba(0,0,0,0.06)}
.step{display:flex;align-items:center;gap:10px;flex:1}
.step-num{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0}
.step.active .step-num{background:var(--primary);color:#fff}
.step.done .step-num{background:var(--green);color:#fff}
.step.inactive .step-num{background:var(--gray-100);color:var(--gray-400)}
.step-label{font-size:13px;font-weight:600}
.step.active .step-label{color:var(--primary)}
.step.done .step-label{color:var(--green)}
.step.inactive .step-label{color:var(--gray-400)}
.step-line{flex:1;height:2px;background:var(--gray-200);margin:0 8px}
.step-line.done{background:var(--green)}
.form-label{font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;display:block}
.form-input{width:100%;padding:12px 16px;border:1.5px solid var(--gray-200);border-radius:10px;font-size:14px;font-family:"Cairo",sans-serif;color:var(--gray-800);background:var(--gray-50);outline:none;transition:.2s}
.form-input:focus{border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(26,86,219,0.08)}
.otp-input{text-align:center;font-size:28px;font-weight:800;letter-spacing:8px;font-family:monospace}
.summary-box{background:var(--gray-50);border:1px solid var(--gray-200);border-radius:12px;padding:20px;margin-bottom:20px}
.summary-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--gray-100);font-size:14px}
.summary-row:last-child{border:none;font-weight:700;color:var(--primary);font-size:16px}
</style>

<div class="transfer-wrap">

<!-- Steps indicator -->
<div class="transfer-steps">
    <div class="step <?= $step>=1?'active':'inactive' ?>">
        <div class="step-num"><?= $step>1?'<i class="fas fa-check"></i>':'1' ?></div>
        <span class="step-label">بيانات التحويل</span>
    </div>
    <div class="step-line <?= $step>1?'done':'' ?>"></div>
    <div class="step <?= $step===2?'active':'inactive' ?>">
        <div class="step-num">2</div>
        <span class="step-label">تأكيد OTP</span>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($step===1): ?>
<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-arrow-right-arrow-left"></i> تحويل جديد</h3></div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="action" value="initiate">
        <?= honeypotField() ?>
        <div class="form-group" style="margin-bottom:20px">
            <label class="form-label">من الحساب</label>
            <select name="from_account_id" class="form-input" required>
                <option value="">-- اختر الحساب --</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= $a['id'] ?>"><?= getAccountTypeName($a['type']) ?> | <?= maskAccountNumber($a['account_number']) ?> | <?= number_format($a['balance'],2) ?> ر.س</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:20px">
            <label class="form-label">رقم حساب المستفيد</label>
            <input type="text" name="to_account_number" class="form-input" placeholder="SAxx80000xxxxxxxxxx" required maxlength="30">
        </div>
        <div class="form-group" style="margin-bottom:20px">
            <label class="form-label">المبلغ (ر.س)</label>
            <input type="number" name="amount" class="form-input" placeholder="0.00" min="1" max="9999999" step="0.01" required>
        </div>
        <div class="form-group" style="margin-bottom:28px">
            <label class="form-label">البيان (اختياري)</label>
            <input type="text" name="description" class="form-input" placeholder="سبب التحويل" maxlength="200">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;font-size:15px;justify-content:center">
            <i class="fas fa-arrow-left"></i> متابعة
        </button>
    </form>
</div>

<?php elseif ($step===2):
    $pending = $_SESSION['transfer_pending'] ?? [];
?>
<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-shield-halved"></i> تأكيد التحويل</h3></div>
    <?php if ($success): ?>
    <div class="alert" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px">
        <i class="fas fa-circle-info"></i> <?= $success ?>
    </div>
    <?php endif; ?>
    <div class="summary-box">
        <div class="summary-row"><span>المبلغ</span><span><?= number_format($pending['amount']??0,2) ?> ر.س</span></div>
        <div class="summary-row"><span>البيان</span><span><?= htmlspecialchars($pending['desc']??'—') ?></span></div>
        <div class="summary-row"><span>المرجع</span><span style="font-family:monospace;font-size:12px"><?= htmlspecialchars($pending['ref']??'') ?></span></div>
        <div class="summary-row"><span>الاجمالي</span><span><?= number_format($pending['amount']??0,2) ?> ر.س</span></div>
    </div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="action" value="confirm">
        <?= honeypotField() ?>
        <div class="form-group" style="margin-bottom:28px;text-align:center">
            <label class="form-label" style="text-align:center;display:block;margin-bottom:12px">ادخل رمز التحقق (OTP)</label>
            <input type="text" name="otp" class="form-input otp-input" placeholder="- - - - - -" required maxlength="6" autocomplete="one-time-code" autofocus>
        </div>
        <div style="display:flex;gap:12px">
            <button type="submit" class="btn btn-primary" style="flex:1;padding:13px;font-size:15px;justify-content:center">
                <i class="fas fa-check"></i> تأكيد التحويل
            </button>
            <a href="transfers.php" class="btn" style="padding:13px 20px">الغاء</a>
        </div>
    </form>
</div>
<?php endif; ?>

</div>
<?php include 'includes/footer.php'; ?>
