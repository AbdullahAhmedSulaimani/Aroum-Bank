<?php
// =============================================
// Aurum Bank — 2FA Setup Page
// =============================================
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/totp.php';

requireLogin();
setSecurityHeaders();

$user    = getCurrentUser($conn);
$error   = '';
$success = '';
$step    = (int)($_GET['step'] ?? 1);

// Generate secret if not in session
if (empty($_SESSION['2fa_setup_secret'])) {
    $_SESSION['2fa_setup_secret'] = TOTP::generateSecret();
}
$secret  = $_SESSION['2fa_setup_secret'];
$otpUri  = TOTP::getOtpAuthUri($secret, $user['email']);
$qrUrl   = TOTP::getQrCodeUrl($otpUri);

// Step 2: Verify code and save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    requireCsrf();
    $code = preg_replace('/\D/', '', $_POST['totp_code'] ?? '');

    if (!TOTP::verify($secret, $code)) {
        $error = 'الرمز غير صحيح. تأكد من الوقت الصحيح على جهازك وحاول مجدداً.';
    } else {
        // Save secret encrypted
        $encSecret = encryptData($secret);
        $stmt = $conn->prepare("UPDATE users SET two_fa_secret = ?, two_fa_enabled = 1, two_fa_verified_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $encSecret, $user['id']);

        if ($stmt->execute()) {
            // Generate backup codes
            $backupCodes = [];
            $conn->query("DELETE FROM backup_codes WHERE user_id = {$user['id']}");
            for ($i = 0; $i < 8; $i++) {
                $plainCode = strtoupper(bin2hex(random_bytes(4)));
                $backupCodes[] = $plainCode;
                $hashed = password_hash($plainCode, PASSWORD_BCRYPT);
                $conn->query("INSERT INTO backup_codes (user_id, code_hash) VALUES ({$user['id']}, '$hashed')");
            }
            unset($_SESSION['2fa_setup_secret']);
            $_SESSION['backup_codes'] = $backupCodes;
            logSecurityEvent($conn, $user['id'], '2fa_enabled', 'IP: ' . getClientIp());
            addNotification($conn, $user['id'], 'تم تفعيل المصادقة الثنائية', 'حسابك الآن محمي بطبقة أمان إضافية.');
            header('Location: two_factor_setup.php?step=3');
            exit();
        } else {
            $error = 'حدث خطأ. يرجى المحاولة لاحقاً.';
        }
    }
}

// Disable 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_2fa'])) {
    requireCsrf();
    $code = preg_replace('/\D/', '', $_POST['disable_code'] ?? '');
    $stmt = $conn->prepare("SELECT two_fa_secret FROM users WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $storedSecret = decryptData($row['two_fa_secret'] ?? '');

    if (TOTP::verify($storedSecret, $code)) {
        $conn->query("UPDATE users SET two_fa_enabled = 0, two_fa_secret = NULL WHERE id = {$user['id']}");
        logSecurityEvent($conn, $user['id'], '2fa_disabled', 'IP: ' . getClientIp());
        $success = 'تم إلغاء تفعيل المصادقة الثنائية.';
        $user = getCurrentUser($conn);
    } else {
        $error = 'الرمز غير صحيح.';
    }
}

$unreadNotifications = getUnreadNotifications($conn, $user['id']);
$pageTitle    = 'المصادقة الثنائية';
$pageSubtitle = 'حماية حسابك بطبقة أمان إضافية';
include 'includes/header.php';
?>

<?php if ($error): ?>
<div class="alert alert-error" data-auto-dismiss><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($step === 3 && isset($_SESSION['backup_codes'])): ?>
<!-- Step 3: Backup Codes -->
<div class="card" style="max-width:540px;margin:0 auto;">
    <div class="card-header">
        <h3 style="color:var(--green);"><i class="fas fa-check-circle" style="margin-left:8px;"></i>تم تفعيل المصادقة الثنائية!</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>مهم جداً:</strong> احفظ هذه الرموز الاحتياطية في مكان آمن. كل رمز يُستخدم مرة واحدة فقط.
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:20px 0;direction:ltr;">
            <?php foreach ($_SESSION['backup_codes'] as $code): ?>
            <div style="background:var(--gray-900);color:var(--green);padding:12px;border-radius:8px;font-family:monospace;font-size:15px;text-align:center;letter-spacing:2px;">
                <?= htmlspecialchars($code) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php unset($_SESSION['backup_codes']); ?>
        <a href="profile.php" class="btn btn-primary btn-block">
            <i class="fas fa-check"></i> حفظت الرموز — إكمال الإعداد
        </a>
    </div>
</div>

<?php elseif ($user['two_fa_enabled']): ?>
<!-- Already enabled -->
<div class="card" style="max-width:540px;margin:0 auto;">
    <div class="card-header">
        <h3><i class="fas fa-shield-halved" style="color:var(--green);margin-left:8px;"></i>المصادقة الثنائية مفعّلة</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            حسابك محمي بالمصادقة الثنائية. كل تسجيل دخول يتطلب رمزاً من تطبيق المصادقة.
        </div>
        <div style="background:var(--gray-50);border-radius:10px;padding:16px;margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:48px;height:48px;background:var(--green-light);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-mobile-screen" style="color:var(--green);font-size:20px;"></i>
                </div>
                <div>
                    <div style="font-weight:700;">Google Authenticator / Authy</div>
                    <div style="font-size:13px;color:var(--gray-500);">مفعّل منذ <?= date('d/m/Y', strtotime($user['two_fa_verified_at'] ?? 'now')) ?></div>
                </div>
            </div>
        </div>
        <hr style="margin:20px 0;border-color:var(--gray-200);">
        <p style="font-size:14px;color:var(--gray-600);margin-bottom:16px;">لإلغاء التفعيل أدخل الرمز الحالي من تطبيق المصادقة:</p>
        <form method="POST" autocomplete="off">
            <?= csrfField() ?>
            <div class="form-group">
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-key"></i></span>
                    <input type="text" name="disable_code" class="form-control" placeholder="الرمز السداسي"
                           maxlength="6" inputmode="numeric" pattern="\d{6}" style="direction:ltr;text-align:center;font-size:20px;letter-spacing:8px;" required>
                </div>
            </div>
            <button type="submit" name="disable_2fa" class="btn btn-danger btn-block"
                    data-confirm="هل أنت متأكد من إلغاء المصادقة الثنائية؟ سيُضعف هذا أمان حسابك.">
                <i class="fas fa-toggle-off"></i> إلغاء تفعيل المصادقة الثنائية
            </button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- Setup Steps -->
<div class="card" style="max-width:540px;margin:0 auto;">
    <div class="card-header">
        <h3><i class="fas fa-qrcode" style="color:var(--primary);margin-left:8px;"></i>إعداد المصادقة الثنائية</h3>
        <p>Google Authenticator / Authy / أي تطبيق TOTP</p>
    </div>
    <div class="card-body">
        <!-- Progress Steps -->
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:28px;">
            <?php foreach ([1=>'تحميل التطبيق', 2=>'مسح QR Code', 3=>'تأكيد الرمز'] as $s => $label): ?>
            <div style="flex:1;text-align:center;">
                <div style="width:32px;height:32px;border-radius:50%;background:<?= $step >= $s ? 'var(--primary)' : 'var(--gray-200)' ?>;color:<?= $step >= $s ? 'white' : 'var(--gray-400)' ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 6px;font-weight:700;font-size:14px;">
                    <?= $s ?>
                </div>
                <div style="font-size:11px;color:<?= $step >= $s ? 'var(--primary)' : 'var(--gray-400)' ?>;font-weight:<?= $step >= $s ? '600' : '400' ?>;"><?= $label ?></div>
            </div>
            <?php if ($s < 3): ?><div style="flex:0 0 20px;height:2px;background:var(--gray-200);margin-bottom:22px;"></div><?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Step 1: Install App -->
        <div style="background:var(--gray-50);border-radius:10px;padding:16px;margin-bottom:20px;">
            <div style="font-weight:700;margin-bottom:10px;"><i class="fas fa-download" style="color:var(--primary);margin-left:6px;"></i> الخطوة 1: حمّل تطبيق المصادقة</div>
            <div style="display:flex;gap:12px;">
                <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;">
                    <i class="fab fa-google-play"></i> Google Authenticator
                </a>
                <a href="https://authy.com/download/" target="_blank" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;">
                    <i class="fas fa-mobile"></i> Authy
                </a>
            </div>
        </div>

        <!-- QR Code -->
        <div style="text-align:center;margin-bottom:20px;">
            <div style="font-weight:700;margin-bottom:14px;"><i class="fas fa-qrcode" style="color:var(--primary);margin-left:6px;"></i> الخطوة 2: امسح QR Code</div>
            <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code" width="220" height="220"
                 style="border:4px solid var(--gray-200);border-radius:12px;padding:8px;">
            <p style="font-size:12px;color:var(--gray-500);margin-top:10px;">أو أدخل المفتاح يدوياً:</p>
            <div style="background:var(--gray-900);color:#10b981;padding:12px 18px;border-radius:8px;font-family:monospace;font-size:14px;letter-spacing:3px;direction:ltr;display:inline-block;margin-top:6px;word-break:break-all;">
                <?= htmlspecialchars($secret) ?>
            </div>
        </div>

        <!-- Verify Code -->
        <div style="font-weight:700;margin-bottom:14px;"><i class="fas fa-shield-check" style="color:var(--primary);margin-left:6px;"></i> الخطوة 3: أدخل الرمز للتأكيد</div>
        <form method="POST" autocomplete="off" id="totpForm">
            <?= csrfField() ?>
            <div class="form-group">
                <label>الرمز السداسي من التطبيق</label>
                <input type="text" name="totp_code" id="totpInput" class="form-control"
                       placeholder="000000" maxlength="6" inputmode="numeric" pattern="\d{6}"
                       style="direction:ltr;text-align:center;font-size:28px;letter-spacing:12px;font-weight:700;" required autofocus>
            </div>
            <button type="submit" name="verify_code" class="btn btn-primary btn-block btn-lg">
                <i class="fas fa-shield-halved"></i> تفعيل المصادقة الثنائية
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// Auto-submit when 6 digits entered
const totpInput = document.getElementById('totpInput');
if (totpInput) {
    totpInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').substring(0, 6);
        if (this.value.length === 6) {
            document.getElementById('totpForm').submit();
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
