<?php
// =============================================
// Aurum Bank — TOTP (RFC 6238)
// Compatible with Google Authenticator, Authy
// No external libraries required
// =============================================

class TOTP {

    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const DIGITS       = 6;
    private const PERIOD       = 30;   // seconds
    private const WINDOW       = 1;    // allow ±1 period for clock skew

    // ── Generate a new secret key ───��─────────
    public static function generateSecret(int $bytes = 20): string {
        return self::base32Encode(random_bytes($bytes));
    }

    // ── Verify a user-provided code ───────────
    public static function verify(string $secret, string $code, int $window = self::WINDOW): bool {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) return false;

        $timestamp = (int)floor(time() / self::PERIOD);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::generateCode($secret, $timestamp + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    // ── Generate the current code (for testing) ─
    public static function getCurrentCode(string $secret): string {
        return self::generateCode($secret, (int)floor(time() / self::PERIOD));
    }

    // ── Build the OTPAuth URI for QR codes ────
    public static function getOtpAuthUri(string $secret, string $email, string $issuer = 'Aurum Bank'): string {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    // ── QR Code URL (Google Charts API) ───────
    public static function getQrCodeUrl(string $otpUri, int $size = 250): string {
        return 'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size .
               '&chld=M|0&cht=qr&chl=' . rawurlencode($otpUri);
    }

    // ── HOTP core algorithm ───────────────────
    private static function generateCode(string $secret, int $counter): string {
        $key     = self::base32Decode($secret);
        $time    = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian
        $hmac    = hash_hmac('sha1', $time, $key, true);
        $offset  = ord($hmac[19]) & 0x0F;
        $code    = (
            ((ord($hmac[$offset])     & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) << 8)  |
            ( ord($hmac[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string)$code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    // ── Base32 Encode ─────────────────────────
    private static function base32Encode(string $input): string {
        $output   = '';
        $v        = 0;
        $vbits    = 0;
        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $v    = ($v << 8) | ord($input[$i]);
            $vbits += 8;
            while ($vbits >= 5) {
                $vbits  -= 5;
                $output .= self::BASE32_CHARS[($v >> $vbits) & 0x1F];
            }
        }
        if ($vbits > 0) {
            $output .= self::BASE32_CHARS[($v << (5 - $vbits)) & 0x1F];
        }
        return $output;
    }

    // ── Base32 Decode ─────────────────────────
    private static function base32Decode(string $input): string {
        $input  = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input));
        $output = '';
        $v      = 0;
        $vbits  = 0;
        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $v     = ($v << 5) | (int)strpos(self::BASE32_CHARS, $input[$i]);
            $vbits += 5;
            if ($vbits >= 8) {
                $vbits  -= 8;
                $output .= chr(($v >> $vbits) & 0xFF);
            }
        }
        return $output;
    }
}
