<?php
// =============================================
// Aurum Bank — OTP Helper
// =============================================

function generateTransferOtp(mysqli $conn, int $userId, string $reference): string {
    // Delete old unused OTPs for this user
    $conn->query("DELETE FROM otp_codes WHERE user_id = $userId AND purpose = 'transfer' AND is_used = 0");

    $code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 300); // 5 minutes

    $stmt = $conn->prepare("INSERT INTO otp_codes (user_id, purpose, code, reference, expires_at) VALUES (?, 'transfer', ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $code, $reference, $expires);
    $stmt->execute();

    return $code;
}

function verifyTransferOtp(mysqli $conn, int $userId, string $code): bool {
    $stmt = $conn->prepare("
        SELECT id, attempts FROM otp_codes
        WHERE user_id = ? AND purpose = 'transfer'
          AND is_used = 0 AND expires_at > NOW()
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $otp = $stmt->get_result()->fetch_assoc();

    if (!$otp) return false;

    // Max 5 attempts
    if ($otp['attempts'] >= 5) {
        $conn->query("UPDATE otp_codes SET is_used = 1 WHERE id = {$otp['id']}");
        return false;
    }

    // Increment attempts
    $conn->query("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = {$otp['id']}");

    // Timing-safe compare
    if (hash_equals($otp['code'] ?? '', $code)) {
        $conn->query("UPDATE otp_codes SET is_used = 1 WHERE id = {$otp['id']}");
        return true;
    }

    return false;
}
