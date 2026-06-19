<?php
// =============================================
// Aurum Bank — 2FA Verification (post-login)
// =============================================
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/totp.php';

setSecurityHeaders();

// Must have pending 2FA session
if (empty($_SESSION['2fa_pending_user_id'])) {
    header('Location: index.php');
    exit();
}

$userId  = (int)$_SESSION['2fa_pending_user_id'];
$error   = '';
$ip      = getClientIp();

// Check if device is trusted
$deviceToken = $_COOKIE['trusted_device'] ?? '';
if ($deviceToken) {
    $stmt = $conn->prepare("SELECT id FROM trusted_devices WHERE user_id = ? AND device_token = ? AND expires_at > NOW()");
    $stmt->bind_param("is", $userId, $deviceToken);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        // Trusted device — skip 2FA
        completeTwoFactorLogin($conn, $userId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $code      = preg_replace('/\D/', '', $_POST['totp_code'] ?? '');
    $useBackup = isset($_POST['use_backup']);

    // Rate limit 2FA attempts
    if (isRateLimited($conn, $ip, 5, 15)) {
        $error = 'تم تجاوز عدد المحاولات. حاول بعد 15 دقيقة.';
    } else {
        $stmt = $conn->prepare("SELECT two_fa_secret FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row     = $stmt->get_result()->fetch_assoc();
        $secret  = decryptData($row['two_fa_secret'] ?? '');
        $valid   = false;

        if ($useBackup) {
            // Try backup codes
            $stmtBC = $conn->prepare("SELECT id, code_hash FROM backup_codes WHERE user_id = ? AND is_used = 0");
            $stmtBC->bind_param("i", $userId);
            $stmtBC->execute();
            $bkCodes = $stmtBC->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($bkCodes as $bc) {
                if (password_verify(strtoupper($code), $bc['code_hash'])) {
                    $conn->query("UPDATE backup_codes SET is_used = 1, used_at = NOW() WHERE id = {$bc['id']}");
                    $valid = true;
                    break;
                }
            }
        } else {
            $valid = TOTP::verify($secret, $code);
        }

        if ($valid) {
            // Trust device?
            if (!empty($_POST['trust_device'])) {
                $token     = bin2hex(random_bytes(32));
                $expires   = date('Y-m-d H:i:s', strtotime('+30 days'));
                $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 150);
                $stmt = $conn->prepare("INSERT INTO trusted_devices (user_id, device_token, device_name, ip_address, expires_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $userId, $token, $userAgent, $ip, $expires);
                $stmt->execute();
                setcookie('trusted_device', $token, strtotime('+30 days'), '/', '', true, true);
            }
            completeTwoFactorLogin($conn, $userId);
        } else {
            recordFailedLogin($conn, $ip, '2fa_attempt');
            $error = 'الرمز غير صحيح أو انتهت صلاحيته.';
        }
    }
}

function completeTwoFactorLogin(mysqli $conn, int $userId): void {
    global $ip;
    $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    session_regenerate_id(true);
    $_SESSION['user_id']       = $userId;
    $_SESSION['user_name']     = $user['full_name'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['2fa_passed']    = true;
    $_SESSION['initiated']     = true;
    $_SESSION['last_activity'] = time();
    $_SESSION['fingerprint']   = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . getClientIp());
    unset($_SESSION['2fa_pending_user_id']);

    logSecurityEvent($conn, $userId, '2fa_passed', 'IP: ' . getClientIp());
    clearLoginAttempts($conn, getClientIp());

    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>التحقق الثنائي — Aurum Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-container" style="max-width:420px;">
        <div class="auth-logo">
            <div class="auth-logo-icon"><i class="fas fa-shield-halved"></i></div>
            <h1>التحقق الثنائي</h1>
            <p>أدخل الرمز من تطبيق المصادقة</p>
        </div>
        <div class="auth-card">
            <?php if ($error): ?>
            <div class="alert alert-error" data-auto-dismiss>
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <div style="text-align:center;margin-bottom:24px;">
                <div style="width:64px;height:64px;background:var(--primary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fas fa-mobile-screen" style="font-size:28px;color:var(--primary);"></i>
                </div>
                <p style="font-size:14px;color:var(--gray-600);">افتح تطبيق <strong>Google Authenticator</strong> أو <strong>Authy</strong> وأدخل الرمز السداسي.</p>
            </div>

            <form method="POST" autocomplete="off" id="totpForm">
                <?= csrfField() ?>
                <div class="form-group">
                    <input type="text" name="totp_code" id="totpInput" class="form-control"
                           placeholder="000000" maxlength="6" inputmode="numeric" pattern="\d{6}"
                           style="direction:ltr;text-align:center;font-size:32px;letter-spacing:14px;font-weight:700;padding:18px;"
                           required autofocus>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--gray-600);">
                        <input type="checkbox" name="trust_device">
                        <span>ثق بهذا الجهاز لمدة 30 يوماً</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-shield-halved"></i> تأكيد
                </button>
            </form>

            <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--gray-200);">
                <p style="font-size:13px;color:var(--gray-500);text-align:center;margin-bottom:12px;">فقدت هاتفك؟ استخدم رمز احتياطي:</p>
                <form method="POST" autocomplete="off">
                    <?= csrfField() ?>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="totp_code" class="form-control"
                               placeholder="رمز الطوارئ" maxlength="8"
                               style="direction:ltr;text-align:center;font-family:monospace;font-size:16px;letter-spacing:4px;">
                        <button type="submit" name="use_backup" value="1" class="btn btn-secondary" style="white-space:nowrap;">
                            <i class="fas fa-key"></i> استخدام
                        </button>
                    </div>
                </form>
            </div>

            <div class="auth-footer" style="margin-top:16px;">
                <a href="logout.php" style="color:var(--red);">
                    <i class="fas fa-right-from-bracket"></i> تسجيل الخروج
                </a>
            </div>
        </div>
    </div>
</div>
<script>
const input = document.getElementById('totpInput');
if (input) {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').substring(0, 6);
        if (this.value.length === 6) document.getElementById('totpForm').submit();
    });
}
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
