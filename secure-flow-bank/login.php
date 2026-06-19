<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';

requireGuest();
$error = '';
$locked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkHoneypot();
    requireCsrf();
    $email    = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'يرجى ادخال البريد الالكتروني وكلمة المرور.';
    } else {
        $result = loginUser($conn, $email, $password);
        if ($result === 'ok') { header('Location: dashboard.php'); exit(); }
        elseif ($result === '2fa_required') { header('Location: two_factor_verify.php'); exit(); }
        elseif ($result === 'locked') {
            $locked = true;
            $wait   = getRemainingLockTime($conn, getClientIp());
            $error  = "تجاوزت عدد المحاولات. انتظر {$wait} دقيقة.";
        } else {
            $error = 'البريد الالكتروني او كلمة المرور غير صحيحة.';
        }
    }
}
setSecurityHeaders();
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل الدخول - Aurum Bank</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:"Cairo",sans-serif;direction:rtl;min-height:100vh;display:flex;background:#110810}
.auth-left{flex:1;background:linear-gradient(135deg,#110810 0%,#2a1020 50%,#110810 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px;position:relative;overflow:hidden}
.auth-left::before{content:"";position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(ellipse at 60% 40%,rgba(244,63,138,0.15),transparent 60%)}
.brand{display:flex;align-items:center;gap:14px;margin-bottom:48px}
.brand-icon{width:54px;height:54px;background:linear-gradient(135deg,#f43f8a,#e11d6a);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff}
.brand-name{font-size:24px;font-weight:800;color:#fff}
.brand-name span{display:block;font-size:12px;font-weight:400;color:#b04d75;margin-top:2px}
.features-list{list-style:none;width:100%;max-width:360px}
.features-list li{display:flex;align-items:center;gap:14px;padding:16px;border-radius:12px;color:#e0709a;font-size:14px;margin-bottom:8px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05)}
.features-list li i{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.auth-right{width:480px;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 52px}
.form-head{text-align:center;margin-bottom:36px;width:100%}
.form-head h2{font-size:26px;font-weight:800;color:#110810;margin-bottom:8px}
.form-head p{font-size:14px;color:#b04d75}
.form-group{width:100%;margin-bottom:20px}
.form-group label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:8px}
.field-wrap{position:relative}
.field-wrap i.icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:15px}
.field-wrap input{width:100%;padding:13px 42px 13px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;font-family:"Cairo",sans-serif;color:#111827;background:#f9fafb;transition:.2s;outline:none}
.field-wrap input:focus{border-color:#f43f8a;background:#fff;box-shadow:0 0 0 3px rgba(26,86,219,0.08)}
.btn-submit{width:100%;padding:14px;background:linear-gradient(135deg,#f43f8a,#2563eb);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;font-family:"Cairo",sans-serif;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:.2s;margin-top:8px}
.btn-submit:hover{background:linear-gradient(135deg,#c01558,#1d4ed8);transform:translateY(-1px);box-shadow:0 6px 20px rgba(244,63,138,0.35)}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}
.alert-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:12px 16px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px;width:100%}
.form-footer{text-align:center;margin-top:24px;font-size:13px;color:#6b7280;width:100%}
.form-footer a{color:#f43f8a;font-weight:700}
.demo-hint{background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:12px 16px;font-size:12px;color:#0369a1;margin-top:16px;width:100%;text-align:center;line-height:1.8}
.divider{width:100%;height:1px;background:#fde8f0;margin:24px 0}
.back-link{display:flex;align-items:center;gap:8px;color:#b04d75;font-size:13px;margin-bottom:32px;align-self:flex-start;transition:.2s}
.back-link:hover{color:#f43f8a}
@media(max-width:768px){body{flex-direction:column}.auth-left{display:none}.auth-right{width:100%;padding:40px 24px}}
</style>
    <script>
        (function(){
            var t = localStorage.getItem('aub_theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <style>
        /* login.php dark mode */
        [data-theme="dark"] .login-left { background:linear-gradient(135deg,#0b1120 0%,#1a2744 100%); }
        [data-theme="dark"] .login-right { background:#111827; }
        [data-theme="dark"] .login-card { background:#1a2744; border:1px solid #2d3f5e; box-shadow:0 10px 40px rgba(0,0,0,0.6); }
        [data-theme="dark"] .login-card h2 { color:#fde8f0; }
        [data-theme="dark"] .login-card .subtitle { color:#e0709a; }
        [data-theme="dark"] .form-label { color:#f9a0be; }
        [data-theme="dark"] .form-inp {
            background:#111827; border-color:#2d3f5e; color:#fde8f0;
        }
        [data-theme="dark"] .form-inp:focus { border-color:#f43f8a; background:#0b1120; }
        [data-theme="dark"] .login-footer { color:#e0709a; }
        [data-theme="dark"] .login-footer a { color:#60a5fa; }
        [data-theme="dark"] .demo-hint { background:#111827; border-color:#2d3f5e; color:#e0709a; }
        /* toggle btn */
        .auth-theme-toggle {
            position:fixed; top:20px; left:20px; z-index:999;
            width:40px; height:40px; border-radius:10px; border:none; cursor:pointer;
            background:rgba(255,255,255,0.1); color:#fff; font-size:16px;
            display:flex; align-items:center; justify-content:center; transition:.2s;
            backdrop-filter:blur(8px); position:relative;
        }
        .auth-theme-toggle:hover { background:rgba(255,255,255,0.2); }
        [data-theme="dark"] .auth-theme-toggle { background:rgba(255,255,255,0.08); }
        .auth-theme-toggle .icon-sun, .auth-theme-toggle .icon-moon { position:absolute; transition:.3s; }
        [data-theme="dark"]  .auth-theme-toggle .icon-sun  { opacity:1;  transform:scale(1); }
        [data-theme="dark"]  .auth-theme-toggle .icon-moon { opacity:0;  transform:scale(0); }
        [data-theme="light"] .auth-theme-toggle .icon-sun  { opacity:0;  transform:scale(0); }
        [data-theme="light"] .auth-theme-toggle .icon-moon { opacity:1;  transform:scale(1); }
    </style>
</head>
<body>
<div class="auth-left">
    <div style="position:relative;z-index:1;width:100%;max-width:400px">
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="brand-name">Aurum Bank<span>منصة مصرفية آمنة</span></div>
        </div>
        <ul class="features-list">
            <li><i class="fas fa-lock" style="background:rgba(26,86,219,0.2);color:#f43f8a"></i> تشفير AES-256 لجميع بياناتك</li>
            <li><i class="fas fa-shield-halved" style="background:rgba(16,185,129,0.2);color:#10b981"></i> مصادقة ثنائية Google Authenticator</li>
            <li><i class="fas fa-bolt" style="background:rgba(245,158,11,0.2);color:#f59e0b"></i> تحويلات فورية مع OTP</li>
            <li><i class="fas fa-eye-slash" style="background:rgba(139,92,246,0.2);color:#8b5cf6"></i> خصوصية تامة وسجل امني</li>
        </ul>
    </div>
</div>
<div class="auth-right">
    <a href="home.php" class="back-link"><i class="fas fa-arrow-right"></i> الرئيسية</a>
    <div class="form-head">
        <h2>مرحباً بعودتك</h2>
        <p>سجّل دخولك للوصول إلى حسابك</p>
    </div>
    <?php if ($error): ?>
    <div class="alert-err"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" autocomplete="off" style="width:100%">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <?= honeypotField() ?>
        <div class="form-group">
            <label>البريد الالكتروني</label>
            <div class="field-wrap">
                <i class="fas fa-envelope icon"></i>
                <input type="email" name="email" placeholder="example@email.com" required maxlength="100" autofocus>
            </div>
        </div>
        <div class="form-group">
            <label>كلمة المرور</label>
            <div class="field-wrap">
                <i class="fas fa-lock icon"></i>
                <input type="password" name="password" placeholder="••••••••" required maxlength="100">
            </div>
        </div>
        <button type="submit" class="btn-submit" <?= $locked ? 'disabled' : '' ?>>
            <i class="fas fa-arrow-right-to-bracket"></i> تسجيل الدخول
        </button>
    </form>
    <div class="divider"></div>
    <div class="form-footer">ليس لديك حساب؟ <a href="register.php">انشاء حساب مجاني</a></div>
    <div class="demo-hint"><strong>حساب تجريبي:</strong> demo@secureflow.sa | كلمة المرور: password</div>
</div>

<button class="auth-theme-toggle" id="authThemeToggle" title="تبديل الوضع" style="position:fixed;top:20px;left:20px;z-index:999;">
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
</body>
</html>
