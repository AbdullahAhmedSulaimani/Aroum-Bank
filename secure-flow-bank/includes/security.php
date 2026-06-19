<?php
// =============================================
// Aurum Bank — Central Security Module
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure'   => $isHttps, 'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

function setSecurityHeaders(): void {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self';");
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

function validateCsrfToken(string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) {
            http_response_code(403);
            die('<div style="font-family:sans-serif;text-align:center;padding:60px;color:#ef4444;"><h2>طلب غير صالح</h2><p>تم اكتشاف طلب مشبوه. <a href="javascript:history.back()">الرجوع</a></p></div>');
        }
    }
}

function getClientIp(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var(explode(',', $ip)[0], FILTER_VALIDATE_IP) ?: '0.0.0.0';
}

function isRateLimited(mysqli $conn, string $ip, int $maxAttempts = 5, int $windowMinutes = 15): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->bind_param("si", $ip, $windowMinutes);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return ($row['cnt'] ?? 0) >= $maxAttempts;
}

function getRemainingLockTime(mysqli $conn, string $ip, int $windowMinutes = 15): int {
    $stmt = $conn->prepare("SELECT attempted_at FROM login_attempts WHERE ip_address = ? ORDER BY attempted_at DESC LIMIT 1");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return 0;
    $unlock = strtotime($row['attempted_at']) + ($windowMinutes * 60);
    return max(0, (int)(($unlock - time()) / 60));
}

function recordFailedLogin(mysqli $conn, string $ip, string $email): void {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $ip, $email);
    $stmt->execute();
}

function clearLoginAttempts(mysqli $conn, string $ip): void {
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
}

function encryptData(string $plaintext): string {
    if (!defined('ENCRYPTION_KEY') || empty($plaintext)) return $plaintext;
    $iv        = random_bytes(16);
    $encrypted = openssl_encrypt($plaintext, 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptData(string $ciphertext): string {
    if (!defined('ENCRYPTION_KEY') || empty($ciphertext)) return $ciphertext;
    $data = base64_decode($ciphertext);
    if (strlen($data) < 17) return $ciphertext;
    $iv        = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? $decrypted : $ciphertext;
}

function sanitizeInput(string $input, int $maxLen = 255): string {
    return mb_substr(htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8'), 0, $maxLen);
}

function validateAmount(string $input): float|false {
    $clean = preg_replace('/[^\d.]/', '', $input);
    if (!is_numeric($clean) || substr_count($clean, '.') > 1) return false;
    $amount = (float)$clean;
    return ($amount > 0 && $amount <= 9999999.99) ? $amount : false;
}

function hardenSession(): void {
    if (empty($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
    $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . getClientIp());
    if (isset($_SESSION['fingerprint']) && $_SESSION['fingerprint'] !== $fingerprint) {
        session_unset();
        session_destroy();
        session_start();
        header('Location: index.php');
        exit();
    }
    $_SESSION['fingerprint'] = $fingerprint;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 7200) {
        session_unset();
        session_destroy();
        session_start();
        header('Location: index.php?reason=timeout');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function logSecurityEvent(mysqli $conn, string $event, string $detail = '', ?int $userId = null): void {
    $ip    = getClientIp();
    $uid   = $userId ?? ($_SESSION['user_id'] ?? null);
    $agent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt  = $conn->prepare("INSERT INTO security_log (user_id, event_type, detail, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issss", $uid, $event, $detail, $ip, $agent);
    $stmt->execute();
}

function honeypotField(): string {
    return '<input type="text" name="website" value="" style="display:none!important;position:absolute;left:-9999px;" tabindex="-1" autocomplete="off">';
}

function checkHoneypot(): void {
    if (!empty($_POST['website'])) { http_response_code(400); exit('Bad request.'); }
}

function luhnCheck(string $number): bool {
    $number  = preg_replace('/\s+/', '', $number);
    $sum     = 0;
    $reverse = strrev($number);
    for ($i = 0, $len = strlen($reverse); $i < $len; $i++) {
        $digit = (int)$reverse[$i];
        if ($i % 2 === 1) { $digit *= 2; if ($digit > 9) $digit -= 9; }
        $sum += $digit;
    }
    return $sum % 10 === 0;
}

function globalRateLimit(int $maxPerMinute = 60): void {
    $key = 'rate_' . getClientIp();
    if (!isset($_SESSION[$key])) $_SESSION[$key] = ['count' => 0, 'window' => time()];
    if ((time() - $_SESSION[$key]['window']) > 60) $_SESSION[$key] = ['count' => 0, 'window' => time()];
    $_SESSION[$key]['count']++;
    if ($_SESSION[$key]['count'] > $maxPerMinute) {
        http_response_code(429);
        die('<div style="font-family:sans-serif;text-align:center;padding:60px;color:#ef4444;"><h2>طلبات كثيرة جداً</h2><p>الرجاء الانتظار دقيقة.</p></div>');
    }
}
