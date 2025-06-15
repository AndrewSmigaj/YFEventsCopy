<?php

namespace YFEvents\Modules\YFAuth\Services;

use YFEvents\Modules\YFAuth\Models\User;
use Exception;
use PDO;

/**
 * Multi-Factor Authentication Service
 * Supports TOTP, SMS, and Email-based MFA
 */
class MFAService
{
    private PDO $db;
    private SecurityLogService $securityLogger;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->securityLogger = new SecurityLogService($db);
    }

    /**
     * Generate TOTP secret for user
     */
    public function generateTotpSecret(User $user): string
    {
        $secret = $this->generateRandomSecret(32);
        
        $sql = "
            INSERT INTO auth_user_mfa (user_id, mfa_type, mfa_secret, is_enabled)
            VALUES (?, 'totp', ?, FALSE)
            ON DUPLICATE KEY UPDATE mfa_secret = VALUES(mfa_secret)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user->id, $this->encrypt($secret)]);
        
        return $secret;
    }

    /**
     * Generate QR code URL for TOTP setup
     */
    public function getTotpQrCodeUrl(User $user, string $secret): string
    {
        $appName = 'YFEvents';
        $userLabel = $user->email;
        
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            urlencode($appName),
            urlencode($userLabel),
            $secret,
            urlencode($appName)
        );
    }

    /**
     * Verify TOTP code
     */
    public function verifyTotpCode(User $user, string $code): bool
    {
        $mfaConfig = $user->getMfaConfig();
        
        if (!$mfaConfig || $mfaConfig['mfa_type'] !== 'totp') {
            return false;
        }

        $secret = $this->decrypt($mfaConfig['mfa_secret']);
        $currentTime = time();
        $window = 1; // Allow 1 time window before/after current

        // Check current time window and adjacent windows
        for ($i = -$window; $i <= $window; $i++) {
            $timeSlice = intval(($currentTime + ($i * 30)) / 30);
            $expectedCode = $this->generateTotpCode($secret, $timeSlice);
            
            if (hash_equals($expectedCode, $code)) {
                // Update last used timestamp
                $this->updateMfaLastUsed($user->id);
                return true;
            }
        }

        return false;
    }

    /**
     * Generate TOTP code for given time slice
     */
    private function generateTotpCode(string $secret, int $timeSlice): string
    {
        $key = base32_decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, 6);
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Enable MFA for user after verification
     */
    public function enableMfa(User $user, string $verificationCode): bool
    {
        if ($this->verifyTotpCode($user, $verificationCode)) {
            $sql = "
                UPDATE auth_user_mfa 
                SET is_enabled = TRUE, enabled_at = NOW()
                WHERE user_id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user->id]);
            
            // Generate backup codes
            $backupCodes = $this->generateBackupCodes($user);
            
            $this->securityLogger->logSecurityEvent(
                $user->id,
                'mfa_enabled',
                'medium',
                "MFA enabled for user: {$user->username}",
                ['mfa_type' => 'totp']
            );
            
            return true;
        }
        
        return false;
    }

    /**
     * Disable MFA for user
     */
    public function disableMfa(User $user): void
    {
        $sql = "
            UPDATE auth_user_mfa 
            SET is_enabled = FALSE 
            WHERE user_id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user->id]);
        
        $this->securityLogger->logSecurityEvent(
            $user->id,
            'mfa_disabled',
            'high',
            "MFA disabled for user: {$user->username}"
        );
    }

    /**
     * Generate backup codes for user
     */
    public function generateBackupCodes(User $user): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = $this->generateRandomCode(8);
        }
        
        $hashedCodes = array_map(function($code) {
            return password_hash($code, PASSWORD_ARGON2ID);
        }, $codes);
        
        $sql = "
            UPDATE auth_user_mfa 
            SET backup_codes = ? 
            WHERE user_id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([json_encode($hashedCodes), $user->id]);
        
        return $codes; // Return unhashed codes for user to save
    }

    /**
     * Verify backup code
     */
    public function verifyBackupCode(User $user, string $code): bool
    {
        $mfaConfig = $user->getMfaConfig();
        
        if (!$mfaConfig || !$mfaConfig['backup_codes']) {
            return false;
        }
        
        $backupCodes = json_decode($mfaConfig['backup_codes'], true);
        
        foreach ($backupCodes as $index => $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                // Remove used backup code
                unset($backupCodes[$index]);
                
                $sql = "
                    UPDATE auth_user_mfa 
                    SET backup_codes = ? 
                    WHERE user_id = ?
                ";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([json_encode(array_values($backupCodes)), $user->id]);
                
                $this->securityLogger->logSecurityEvent(
                    $user->id,
                    'mfa_backup_used',
                    'medium',
                    "Backup code used for user: {$user->username}",
                    ['remaining_codes' => count($backupCodes)]
                );
                
                return true;
            }
        }
        
        return false;
    }

    /**
     * Verify any MFA code (TOTP or backup)
     */
    public function verifyCode(User $user, string $code): bool
    {
        // First try TOTP
        if ($this->verifyTotpCode($user, $code)) {
            return true;
        }
        
        // Then try backup codes
        return $this->verifyBackupCode($user, $code);
    }

    /**
     * Get MFA setup status for user
     */
    public function getMfaStatus(User $user): array
    {
        $mfaConfig = $user->getMfaConfig();
        
        if (!$mfaConfig) {
            return [
                'enabled' => false,
                'type' => null,
                'backup_codes_count' => 0
            ];
        }
        
        $backupCodes = $mfaConfig['backup_codes'] ? json_decode($mfaConfig['backup_codes'], true) : [];
        
        return [
            'enabled' => (bool)$mfaConfig['is_enabled'],
            'type' => $mfaConfig['mfa_type'],
            'backup_codes_count' => count($backupCodes),
            'last_used' => $mfaConfig['last_used_at']
        ];
    }

    /**
     * Send MFA code via SMS (placeholder - integrate with SMS provider)
     */
    public function sendSmsCode(User $user, string $phoneNumber): bool
    {
        $code = $this->generateRandomCode(6);
        
        // Store code temporarily (expires in 5 minutes)
        $expiresAt = date('Y-m-d H:i:s', time() + 300);
        
        $sql = "
            INSERT INTO auth_mfa_codes (user_id, code, phone_number, expires_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                code = VALUES(code),
                phone_number = VALUES(phone_number),
                expires_at = VALUES(expires_at)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user->id, password_hash($code, PASSWORD_ARGON2ID), $phoneNumber, $expiresAt]);
        
        // TODO: Integrate with SMS provider (Twilio, AWS SNS, etc.)
        // For now, log the code (remove in production)
        error_log("SMS MFA code for {$user->username}: {$code}");
        
        return true;
    }

    /**
     * Send MFA code via email
     */
    public function sendEmailCode(User $user): bool
    {
        $code = $this->generateRandomCode(6);
        
        // Store code temporarily (expires in 10 minutes)
        $expiresAt = date('Y-m-d H:i:s', time() + 600);
        
        $sql = "
            INSERT INTO auth_mfa_codes (user_id, code, expires_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                code = VALUES(code),
                expires_at = VALUES(expires_at)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user->id, password_hash($code, PASSWORD_ARGON2ID), $expiresAt]);
        
        // TODO: Send email with code
        // For now, log the code (remove in production)
        error_log("Email MFA code for {$user->username}: {$code}");
        
        return true;
    }

    /**
     * Verify SMS/Email code
     */
    public function verifyTemporaryCode(User $user, string $code): bool
    {
        $sql = "
            SELECT code FROM auth_mfa_codes 
            WHERE user_id = ? AND expires_at > NOW()
            ORDER BY created_at DESC LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$user->id]);
        $storedCode = $stmt->fetchColumn();
        
        if ($storedCode && password_verify($code, $storedCode)) {
            // Delete used code
            $sql = "DELETE FROM auth_mfa_codes WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user->id]);
            
            return true;
        }
        
        return false;
    }

    /**
     * Update last used timestamp
     */
    private function updateMfaLastUsed(int $userId): void
    {
        $sql = "UPDATE auth_user_mfa SET last_used_at = NOW() WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
    }

    /**
     * Generate random secret for TOTP
     */
    private function generateRandomSecret(int $length): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
        $secret = '';
        
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $secret;
    }

    /**
     * Generate random numeric code
     */
    private function generateRandomCode(int $length): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }

    /**
     * Encrypt sensitive data (placeholder - use proper encryption)
     */
    private function encrypt(string $data): string
    {
        // TODO: Implement proper encryption with a secret key
        // For now, just base64 encode (NOT SECURE - replace in production)
        return base64_encode($data);
    }

    /**
     * Decrypt sensitive data (placeholder - use proper decryption)
     */
    private function decrypt(string $encryptedData): string
    {
        // TODO: Implement proper decryption
        // For now, just base64 decode (NOT SECURE - replace in production)
        return base64_decode($encryptedData);
    }
}

/**
 * Base32 decode function for TOTP
 */
function base32_decode(string $input): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper($input);
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0; $i < strlen($input); $i++) {
        $c = $input[$i];
        if ($c == '=') break;
        
        $pos = strpos($alphabet, $c);
        if ($pos === false) continue;
        
        $v = ($v << 5) | $pos;
        $vbits += 5;
        
        if ($vbits >= 8) {
            $output .= chr(($v >> ($vbits - 8)) & 0xff);
            $vbits -= 8;
        }
    }
    
    return $output;
}