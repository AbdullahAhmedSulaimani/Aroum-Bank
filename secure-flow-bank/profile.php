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

if ($_SERVER['REQUEST_METHOD']==='POST') {
    requireCsrf();
    checkHoneypot();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = sanitizeInput($_POST['full_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        if (empty($name)) { $error = 'الاسم لا يمكن أن يكون فارغاً.'; }
        else {
            $s = $conn->prepare("UPDATE users SET full_name=?, phone=? WHERE id=?");
            $s->bind_param("ssi", $name, $phone, $user['id']);
            $s->execute();
            $_SESSION['user_name'] = $name;
            $success = 'تم تحديث الملف الشخصي بنجاح.';
            $user = getCurrentUser($conn);
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $s = $conn->prepare("SELECT password FROM users WHERE id=?");
        $s->bind_param("i", $user['id']);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        if (!password_verify($current, $row['password'])) { $error = 'كلمة المرور الحالية غير صحيحة.'; }
        elseif (strlen($new) < 8)  { $error = 'كلمة المرور الجديدة يجب 8 أحرف على الأقل.'; }
        elseif ($new !== $confirm) { $error = 'كلمتا المرور غير متطابقتين.'; }
        else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
            $s2   = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $s2->bind_param("si", $hash, $user['id']);
            $s2->execute();
            logSecurityEvent($conn, 'password_change', 'Password changed');
            $success = 'تم تغيير كلمة المرور بنجاح.';
        }
    }
}

$pageTitle = 'الملف الشخصي';
include 'includes/header.php';
?>

<?php if ($error): ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:900px;">

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-user-circle"></i> البيانات الشخصية</h3></div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="action" value="update_profile">
        <?= honeypotField() ?>
        <div class="form-group"><label>الاسم الكامل</label>
            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required maxlength="100">
        </div>
        <div class="form-group"><label>البريد الإلكتروني</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity:.6;">
        </div>
        <div class="form-group"><label>رقم الجوال</label>
            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" maxlength="15">
        </div>
        <div class="form-group"><label>تاريخ التسجيل</label>
            <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($user['created_at'])) ?>" disabled style="opacity:.6;">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ التغييرات</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-key"></i> تغيير كلمة المرور</h3></div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="action" value="change_password">
        <?= honeypotField() ?>
        <div class="form-group"><label>كلمة المرور الحالية</label>
            <input type="password" name="current_password" class="form-control" required maxlength="100">
        </div>
        <div class="form-group"><label>كلمة المرور الجديدة</label>
            <input type="password" name="new_password" class="form-control" required minlength="8" maxlength="100">
        </div>
        <div class="form-group"><label>تأكيد كلمة المرور الجديدة</label>
            <input type="password" name="confirm_password" class="form-control" required maxlength="100">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-lock"></i> تغيير كلمة المرور</button>
    </form>
</div>

</div>

<?php include 'includes/footer.php'; ?>
