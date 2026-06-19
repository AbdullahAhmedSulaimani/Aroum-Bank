-- =============================================
-- SecureFlow Bank — 2FA & OTP Tables
-- Run after database.sql and security_tables.sql
-- =============================================

USE secureflow_bank;

-- Add 2FA columns to users table
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS two_fa_secret  VARCHAR(64)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS two_fa_enabled TINYINT(1)   DEFAULT 0,
    ADD COLUMN IF NOT EXISTS two_fa_verified_at TIMESTAMP DEFAULT NULL;

-- OTP Codes (for transfer confirmation & email verification)
CREATE TABLE IF NOT EXISTS otp_codes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    purpose     ENUM('transfer','email_verify','password_reset','login') NOT NULL,
    code        VARCHAR(8) NOT NULL,
    reference   VARCHAR(100) DEFAULT NULL,  -- e.g. transfer amount/details
    is_used     TINYINT(1) DEFAULT 0,
    attempts    INT DEFAULT 0,
    expires_at  TIMESTAMP NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_purpose (user_id, purpose),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Trusted devices (remember this device after 2FA)
CREATE TABLE IF NOT EXISTS trusted_devices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    device_token VARCHAR(64) NOT NULL UNIQUE,
    device_name  VARCHAR(150),
    ip_address   VARCHAR(45),
    last_used    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at   TIMESTAMP NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 2FA backup codes (one-time use emergency codes)
CREATE TABLE IF NOT EXISTS backup_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    code_hash  VARCHAR(255) NOT NULL,
    is_used    TINYINT(1) DEFAULT 0,
    used_at    TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
