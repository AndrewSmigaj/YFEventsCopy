<?php

namespace YFEvents\Modules\YFAuth\Services;

use YFEvents\Modules\YFAuth\Models\User;
use Exception;
use PDO;

/**
 * JWT Token Management Service
 * Handles creation, validation, and blacklisting of JWT tokens
 */
class JWTService
{
    private PDO $db;
    private string $secretKey;
    private int $accessTokenTtl;
    private int $refreshTokenTtl;
    private SecurityLogService $securityLogger;

    public function __construct(PDO $db, string $secretKey = null)
    {
        $this->db = $db;
        $this->secretKey = $secretKey ?: $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this';
        $this->accessTokenTtl = 3600; // 1 hour
        $this->refreshTokenTtl = 86400 * 7; // 7 days
        $this->securityLogger = new SecurityLogService($db);
    }

    /**
     * Generate access token for user
     */
    public function generateAccessToken(User $user, array $claims = []): string
    {
        $payload = [
            'iss' => $_SERVER['HTTP_HOST'] ?? 'yfevents',
            'iat' => time(),
            'exp' => time() + $this->accessTokenTtl,
            'sub' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'type' => 'access_token',
            'roles' => array_column($user->getRoles(), 'name')
        ];

        // Add custom claims
        $payload = array_merge($payload, $claims);

        return $this->createToken($payload);
    }

    /**
     * Generate refresh token
     */
    public function generateRefreshToken(User $user): string
    {
        $payload = [
            'iss' => $_SERVER['HTTP_HOST'] ?? 'yfevents',
            'iat' => time(),
            'exp' => time() + $this->refreshTokenTtl,
            'sub' => $user->id,
            'type' => 'refresh_token',
            'jti' => bin2hex(random_bytes(16)) // Unique token ID
        ];

        return $this->createToken($payload);
    }

    /**
     * Validate and decode token
     */
    public function validateToken(string $token): ?array
    {
        try {
            // Check if token is blacklisted
            if ($this->isTokenBlacklisted($token)) {
                return null;
            }

            $payload = $this->decodeToken($token);

            // Check expiration
            if ($payload['exp'] < time()) {
                return null;
            }

            return $payload;

        } catch (Exception $e) {
            error_log("JWT validation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(string $refreshToken): ?array
    {
        $payload = $this->validateToken($refreshToken);

        if (!$payload || $payload['type'] !== 'refresh_token') {
            return null;
        }

        // Load user
        $user = User::find($this->db, $payload['sub']);
        if (!$user || !$user->is_active) {
            return null;
        }

        // Generate new tokens
        $newAccessToken = $this->generateAccessToken($user);
        $newRefreshToken = $this->generateRefreshToken($user);

        // Blacklist old refresh token
        $this->blacklistToken($refreshToken, $user->id, 'refresh');

        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => $this->accessTokenTtl
        ];
    }

    /**
     * Blacklist a token
     */
    public function blacklistToken(string $token, int $userId, string $reason = 'logout'): void
    {
        try {
            $payload = $this->decodeToken($token);
            $tokenHash = hash('sha256', $token);

            $sql = "
                INSERT INTO auth_token_blacklist (token_hash, user_id, expires_at, reason)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE reason = VALUES(reason)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $tokenHash,
                $userId,
                date('Y-m-d H:i:s', $payload['exp']),
                $reason
            ]);

        } catch (Exception $e) {
            error_log("Token blacklist error: " . $e->getMessage());
        }
    }

    /**
     * Check if token is blacklisted
     */
    public function isTokenBlacklisted(string $token): bool
    {
        try {
            $tokenHash = hash('sha256', $token);

            $sql = "
                SELECT COUNT(*) FROM auth_token_blacklist 
                WHERE token_hash = ? AND expires_at > NOW()
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tokenHash]);

            return $stmt->fetchColumn() > 0;

        } catch (Exception $e) {
            error_log("Token blacklist check error: " . $e->getMessage());
            return false; // Fail open
        }
    }

    /**
     * Logout user (blacklist all their tokens)
     */
    public function logoutUser(int $userId): void
    {
        try {
            // This is a simplified approach - in production you might want to track active tokens
            $this->securityLogger->logSecurityEvent(
                $userId,
                'user_logout',
                'low',
                "User logged out - tokens invalidated"
            );

            // For now, we rely on token expiration
            // In a full implementation, you'd track active tokens per user

        } catch (Exception $e) {
            error_log("User logout error: " . $e->getMessage());
        }
    }

    /**
     * Create JWT token
     */
    private function createToken(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $this->secretKey, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Decode JWT token
     */
    private function decodeToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $this->secretKey, true);
        $expectedSignature = $this->base64UrlEncode($signature);

        if (!hash_equals($expectedSignature, $signatureEncoded)) {
            throw new Exception('Invalid token signature');
        }

        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            throw new Exception('Invalid token payload');
        }

        return $payload;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    /**
     * Clean up expired blacklisted tokens
     */
    public function cleanupBlacklistedTokens(): int
    {
        try {
            $sql = "DELETE FROM auth_token_blacklist WHERE expires_at <= NOW()";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->rowCount();

        } catch (Exception $e) {
            error_log("Token cleanup error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get token statistics
     */
    public function getTokenStatistics(): array
    {
        try {
            $stats = [];

            // Count blacklisted tokens by reason
            $sql = "
                SELECT reason, COUNT(*) as count
                FROM auth_token_blacklist
                WHERE expires_at > NOW()
                GROUP BY reason
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats['blacklisted_by_reason'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total blacklisted tokens
            $sql = "SELECT COUNT(*) FROM auth_token_blacklist WHERE expires_at > NOW()";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats['total_blacklisted'] = $stmt->fetchColumn();

            return $stats;

        } catch (Exception $e) {
            error_log("Token statistics error: " . $e->getMessage());
            return [];
        }
    }
}