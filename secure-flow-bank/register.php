<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';

requireGuest();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkHoneypot(); requireCsrf();
    $name     = sanitizeInput($_POST['full_name'] ?? '');
    $email    = sanitizeInput($_POST['email'] ?? '');
    $phone    = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($name)||empty($email)||empty($password)) { $error = 'يرجى تعبئة جميع الحقول المطلوبة.'; }
    elseif (!filter_var($email,FILTER_VALIDATE_EMAIL)) { $error = 'البريد الالكتروني غير صالح.'; }
    elseif (strlen($password)<8) { $error = 'كلمة المرور يجب ان تكون 8 احرف على الاقل.'; }
    elseif ($password!==$confirm) { $error = 'كلمتا المرور غير متطابقتين.'; }
    else {
        $chk = $conn->prepare("SELECT id FROM users WHERE email=?");
        $chk->bind_param("s",$email); $chk->execute();
        if ($chk->get_result()->num_rows>0) { $error = 'البريد الالكتروني مسجل مسبقا.'; }
        else {
            $hash = password_hash($password,PASSWORD_BCRYPT,['cost'=>12]);
            $stmt = $conn->prepare("INSERT INTO users (full_name,email,phone,password,status,created_at) VALUES (?,?,?,?,'active',NOW())");
            $stmt->bind_param("ssss",$name,$email,$phone,$hash);
            if ($stmt->execute()) {
                $userId = $conn->insert_id;
                $accNum = 'SA'.rand(10,99).'80000000'.rand(100000000,999999999);
                $s2 = $conn->prepare("INSERT INTO accounts (user_id,account_number,type,balance,status) VALUES (?,?,'checking',1000.00,'active')");
                $s2->bind_param("is",$userId,$accNum); $s2->execute();
                $success = 'تم انشاء حسابك بنجاح! يمكنك تسجيل الدخول الآن.';
            } else { $error = 'حدث خطا. يرجى المحاولة لاحقا.'; }
        }
    }
}
setSecurityHeaders();
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>انشاء حساب - Aurum Bank</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:"Cairo",sans-serif;direction:rtl;min-height:100vh;background:#fff1f5;display:flex;align-items:center;justify-content:center;padding:24px}
.reg-card{background:#fff;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,0.1);width:100%;max-width:520px;padding:48px 52px}
.reg-logo{display:flex;align-items:center;gap:12px;margin-bottom:32px;justify-content:center}
.reg-logo .icon{width:46px;height:46px;background:linear-gradient(135deg,#f43f8a,#e11d6a);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff}
.reg-logo .name{font-size:20px;font-weight:800;color:#110810}
h2{font-size:22px;font-weight:800;color:#110810;text-align:center;margin-bottom:6px}
.sub{font-size:13px;color:#b04d75;text-align:center;margin-bottom:28px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:7px}
.field-wrap{position:relative}
.field-wrap i{position:absolute;right:13px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:14px}
.field-wrap input{width:100%;padding:12px 40px 12px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:"Cairo",sans-serif;outline:none;transition:.2s;background:#f9fafb}
.field-wrap input:focus{border-color:#f43f8a;background:#fff;box-shadow:0 0 0 3px rgba(26,86,219,0.08)}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.btn-submit{width:100%;padding:13px;background:linear-gradient(135deg,#f43f8a,#2563eb);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;font-family:"Cairo",sans-serif;cursor:pointer;margin-top:8px;transition:.2s}
.btn-submit:hover{background:linear-gradient(135deg,#c01558,#1d4ed8);transform:translateY(-1px)}
.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:11px 15px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:9px}
.alert-ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;border-radius:10px;padding:11px 15px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:9px}
.form-footer{text-align:center;margin-top:20px;font-size:13px;color:#6b7280}
.form-footer a{color:#f43f8a;font-weight:700}
@media(max-width:500px){.reg-card{padding:32px 20px}.grid-2{grid-template-columns:1fr}}
</style>
    <script>
        (function(){
            var t = localStorage.getItem('aub_theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <style>
        [data-theme="dark"] body { background:#0b1120; }
        [data-theme="dark"] .auth-page { background:linear-gradient(135deg,#0b1120 0%,#111827 100%); }
        [data-theme="dark"] .auth-card { background:#1a2744; border:1px solid #2d3f5e; }
        [data-theme="dark"] .auth-card h2 { color:#fde8f0; }
        [data-theme="dark"] .auth-card .auth-subtitle { color:#e0709a; }
        [data-theme="dark"] .form-group label { color:#f9a0be; }
        [data-theme="dark"] .form-control {
            background:#111827; border-color:#2d3f5e; color:#fde8f0;
        }
        [data-theme="dark"] .form-control:focus { border-color:#f43f8a; background:#0b1120; }
        [data-theme="dark"] .auth-footer { color:#e0709a; }
        [data-theme="dark"] .auth-footer a { color:#60a5fa; }
        [data-theme="dark"] .demo-box { background:#111827; border-color:#2d3f5e; color:#e0709a; }
        .auth-theme-toggle {
            position:fixed; top:20px; left:20px; z-index:999;
            width:40px; height:40px; border-radius:10px; border:none; cursor:pointer;
            background:rgba(255,255,255,0.1); color:#fff; font-size:16px;
            display:flex; align-items:center; justify-content:center; transition:.2s;
            backdrop-filter:blur(8px);
        }
        .auth-theme-toggle:hover { background:rgba(255,255,255,0.2); }
        [data-theme="dark"] .auth-theme-toggle { background:rgba(255,255,255,0.08); }
        .auth-theme-toggle .icon-sun,
        .auth-theme-toggle .icon-moon { position:absolute; transition:.3s; }
        [data-theme="dark"]  .auth-theme-toggle .icon-sun  { opacity:1; transform:scale(1); }
        [data-theme="dark"]  .auth-theme-toggle .icon-moon { opacity:0; transform:scale(0); }
        [data-theme="light"] .auth-theme-toggle .icon-sun  { opacity:0; transform:scale(0); }
        [data-theme="light"] .auth-theme-toggle .icon-moon { opacity:1; transform:scale(1); }
    </style>
</head>
<body>
<div class="reg-card">
    <div class="reg-logo"><div class="icon"><i class="fas fa-shield-halved"></i></div><div class="name">Aurum Bank</div></div>
    <h2>انشاء حساب جديد</h2>
    <p class="sub">انضم وابدأ رحلتك المصرفية الآمنة</p>
    <?php if ($error): ?><div class="alert-err"><i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-ok"><i class="fas fa-circle-check"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <?= honeypotField() ?>
        <div class="form-group">
            <label>الاسم الكامل</label>
            <div class="field-wrap"><i class="fas fa-user"></i>
                <input type="text" name="full_name" placeholder="محمد احمد" required maxlength="100">
            </div>
        </div>
        <div class="form-group">
            <label>البريد الالكتروني</label>
            <div class="field-wrap"><i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="example@email.com" required maxlength="100">
            </div>
        </div>
        <div class="form-group">
            <label>رقم الجوال</label>
            <div class="field-wrap"><i class="fas fa-phone"></i>
                <input type="tel" name="phone" placeholder="05xxxxxxxx" maxlength="15">
            </div>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label>كلمة المرور</label>
                <div class="field-wrap"><i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="8 احرف+" required minlength="8" maxlength="100">
                </div>
            </div>
            <div class="form-group">
                <label>تاكيد المرور</label>
                <div class="field-wrap"><i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" placeholder="اعد الادخال" required maxlength="100">
                </div>
            </div>
        </div>
        <button type="submit" class="btn-submit"><i class="fas fa-user-plus"></i> انشاء الحساب</button>
    </form>
    <div class="form-footer">لديك حساب؟ <a href="login.php">تسجيل الدخول</a> &nbsp;|&nbsp; <a href="home.php">الرئيسية</a></div>
</div>

<button class="auth-theme-toggle" id="authThemeToggle" title="تبديل الوضع">
    <i class="fas fa-sun icon-sun"></i>
    <i class="fas fa-moon icon-moon"></i>
</button>
<script>
    (function(){
        var t = localStorage.getItem('aub_theme') || 'light';
        document.documentElement.setAttribute('data-theme', t);
    })();
    var btn = document.getElementById('authThemeToggle');
    if(btn) btn.addEventListener('click', function(){
        var cur = document.documentElement.getAttribute('data-theme') || 'light';
        var next = cur === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('aub_theme', next);
    });
</script>
</body></html>
