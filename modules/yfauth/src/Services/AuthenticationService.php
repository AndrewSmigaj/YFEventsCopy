<?php

namespace YFEvents\Modules\YFAuth\Services;

use YFEvents\Modules\YFAuth\Models\User;
use YFEvents\Modules\YFAuth\Models\SecurityEvent;
use Exception;
use PDO;

/**
 * Comprehensive Authentication Service
 * Implements Gemini's security recommendations
 */
class AuthenticationService
{
    private PDO $db;
    private SecurityLogService $securityLogger;
    private MFAService $mfaService;
    private RateLimitService $rateLimiter;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->securityLogger = new SecurityLogService($db);
        $this->mfaService = new MFAService($db);
        $this->rateLimiter = new RateLimitService($db);
    }

    /**
     * Authenticate user with comprehensive security checks
     */
    public function authenticate(
        string $username, 
        string $password, 
        ?string $mfaCode = null,
        array $context = []
    ): AuthResult {
        $ipAddress = $context['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $context['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        try {
            // Rate limiting check
            if (!$this->rateLimiter->attempt("login:{$ipAddress}", 5, 300)) {
                $this->securityLogger->logSecurityEvent(
                    null,
                    'suspicious_login',
                    'high',
                    'Rate limit exceeded for IP: ' . $ipAddress,
                    ['ip_address' => $ipAddress, 'user_agent' => $userAgent]
                );
                return AuthResult::failed('Too many login attempts. Please try again later.');
            }

            // Find user
            $user = User::findByLogin($this->db, $username);
            if (!$user) {
                $this->securityLogger->logLoginAttempt(
                    null, 
                    $username, 
                    $ipAddress, 
                    $userAgent, 
                    'failed_password',
                    'User not found'
                );
                return AuthResult::failed('Invalid credentials');
            }

            // Check if account is locked
            if ($user->isAccountLocked()) {
                $this->securityLogger->logLoginAttempt(
                    $user->id, 
                    $username, 
                    $ipAddress, 
                    $userAgent, 
                    'account_locked'
                );
                return AuthResult::failed('Account is temporarily locked due to multiple failed attempts');
            }

            // Check if account is active
            if (!$user->is_active) {
                $this->securityLogger->logLoginAttempt(
                    $user->id, 
                    $username, 
                    $ipAddress, 
                    $userAgent, 
                    'account_disabled'
                );
                return AuthResult::failed('Account is disabled');
            }

            // Check if email is verified
            if (!$user->email_verified) {
                return AuthResult::failed('Please verify your email address before logging in');
            }

            // Verify password
            if (!$user->verifyPassword($password)) {
                $user->incrementFailedAttempts();
                
                $this->securityLogger->logLoginAttempt(
                    $user->id, 
                    $username, 
                    $ipAddress, 
                    $userAgent, 
                    'failed_password'
                );

                // Check for suspicious activity
                if ($user->failed_login_attempts >= 3) {
                    $this->securityLogger->logSecurityEvent(
                        $user->id,
                        'multiple_failures',
                        'medium',
                        "Multiple failed login attempts for user: {$username}",
                        [
                            'failed_attempts' => $user->failed_login_attempts,
                            'ip_address' => $ipAddress,
                            'user_agent' => $userAgent
                        ]
                    );
                }

                return AuthResult::failed('Invalid credentials');
            }

            // Check MFA if enabled
            if ($user->isMfaEnabled()) {
                if ($mfaCode === null) {
                    return AuthResult::requiresMfa($user->id);
                }

                if (!$this->mfaService->verifyCode($user, $mfaCode)) {
                    $this->securityLogger->logLoginAttempt(
                        $user->id, 
                        $username, 
                        $ipAddress, 
                        $userAgent, 
                        'failed_mfa'
                    );
                    return AuthResult::failed('Invalid MFA code');
                }
            }

            // Successful authentication
            $user->resetFailedAttempts();
            
            $this->securityLogger->logLoginAttempt(
                $user->id, 
                $username, 
                $ipAddress, 
                $userAgent, 
                'success'
            );

            // Check for suspicious login patterns
            $this->checkSuspiciousActivity($user, $ipAddress, $userAgent);

            return AuthResult::success($user);

        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return AuthResult::failed('Authentication failed');
        }
    }

    /**
     * Register new user with security validation
     */
    public function register(array $userData, array $context = []): AuthResult
    {
        $ipAddress = $context['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $context['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        try {
            // Rate limiting for registration
            if (!$this->rateLimiter->attempt("register:{$ipAddress}", 3, 3600)) {
                return AuthResult::failed('Too many registration attempts. Please try again later.');
            }

            // Create user
            $user = User::createUser($this->db, $userData);

            // Generate email verification token
            $verificationToken = $user->generateEmailVerificationToken();

            // Log registration
            $this->securityLogger->logSecurityEvent(
                $user->id,
                'account_created',
                'low',
                "New user registration: {$user->username}",
                [
                    'email' => $user->email,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent
                ]
            );

            return AuthResult::success($user, [
                'verification_token' => $verificationToken,
                'requires_verification' => true
            ]);

        } catch (Exception $e) {
            return AuthResult::failed($e->getMessage());
        }
    }

    /**
     * Verify email address
     */
    public function verifyEmail(string $token): AuthResult
    {
        try {
            // Find user by verification token
            $sql = "SELECT * FROM auth_users WHERE email_verification_token = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([hash('sha256', $token)]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userData) {
                return AuthResult::failed('Invalid verification token');
            }

            $user = new User($this->db);
            $user->fill($userData);

            if ($user->verifyEmail($token)) {
                $this->securityLogger->logSecurityEvent(
                    $user->id,
                    'email_verified',
                    'low',
                    "Email verified for user: {$user->username}"
                );

                return AuthResult::success($user);
            }

            return AuthResult::failed('Email verification failed');

        } catch (Exception $e) {
            return AuthResult::failed('Email verification failed');
        }
    }

    /**
     * Initiate password reset
     */
    public function initiatePasswordReset(string $email, array $context = []): AuthResult
    {
        $ipAddress = $context['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $context['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        try {
            // Rate limiting for password reset
            if (!$this->rateLimiter->attempt("password_reset:{$ipAddress}", 3, 3600)) {
                return AuthResult::failed('Too many password reset attempts. Please try again later.');
            }

            // Find user by email
            $user = User::findByLogin($this->db, $email);
            if (!$user) {
                // Don't reveal if email exists or not
                return AuthResult::success(null, [
                    'message' => 'If an account with that email exists, you will receive a password reset link.'
                ]);
            }

            // Generate reset token
            $resetToken = $user->generatePasswordResetToken($ipAddress, $userAgent);

            $this->securityLogger->logSecurityEvent(
                $user->id,
                'password_reset_requested',
                'medium',
                "Password reset requested for user: {$user->username}",
                [
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent
                ]
            );

            return AuthResult::success($user, [
                'reset_token' => $resetToken,
                'message' => 'Password reset link sent to your email.'
            ]);

        } catch (Exception $e) {
            return AuthResult::failed('Password reset failed');
        }
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $token, string $newPassword, array $context = []): AuthResult
    {
        $ipAddress = $context['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        try {
            // Find user by reset token
            $hashedToken = hash('sha256', $token);
            $sql = "
                SELECT u.* FROM auth_users u
                JOIN auth_password_resets pr ON u.id = pr.user_id
                WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$hashedToken]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userData) {
                return AuthResult::failed('Invalid or expired reset token');
            }

            $user = new User($this->db);
            $user->fill($userData);

            // Validate and set new password
            $user->setPassword($newPassword);
            $user->save();

            // Mark reset token as used
            $user->usePasswordResetToken($token);

            // Reset failed attempts
            $user->resetFailedAttempts();

            $this->securityLogger->logSecurityEvent(
                $user->id,
                'password_changed',
                'medium',
                "Password reset completed for user: {$user->username}",
                ['ip_address' => $ipAddress]
            );

            return AuthResult::success($user);

        } catch (Exception $e) {
            return AuthResult::failed($e->getMessage());
        }
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): AuthResult
    {
        try {
            // Verify current password
            if (!$user->verifyPassword($currentPassword)) {
                return AuthResult::failed('Current password is incorrect');
            }

            // Set new password
            $user->setPassword($newPassword);
            $user->save();

            $this->securityLogger->logSecurityEvent(
                $user->id,
                'password_changed',
                'low',
                "Password changed by user: {$user->username}"
            );

            return AuthResult::success($user);

        } catch (Exception $e) {
            return AuthResult::failed($e->getMessage());
        }
    }

    /**
     * Check for suspicious login activity
     */
    private function checkSuspiciousActivity(User $user, string $ipAddress, string $userAgent): void
    {
        try {
            // Check for login from new location (simplified IP-based check)
            $sql = "
                SELECT COUNT(*) FROM auth_login_logs 
                WHERE user_id = ? AND ip_address = ? AND login_result = 'success'
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user->id, $ipAddress]);
            $knownLocation = $stmt->fetchColumn() > 0;

            if (!$knownLocation) {
                $this->securityLogger->logSecurityEvent(
                    $user->id,
                    'suspicious_login',
                    'medium',
                    "Login from new location for user: {$user->username}",
                    [
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                        'new_location' => true
                    ]
                );
            }

            // Check for rapid successive logins
            $sql = "
                SELECT COUNT(*) FROM auth_login_logs 
                WHERE user_id = ? AND login_result = 'success'
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user->id]);
            $recentLogins = $stmt->fetchColumn();

            if ($recentLogins > 3) {
                $this->securityLogger->logSecurityEvent(
                    $user->id,
                    'suspicious_login',
                    'high',
                    "Multiple rapid logins for user: {$user->username}",
                    [
                        'recent_logins' => $recentLogins,
                        'ip_address' => $ipAddress
                    ]
                );
            }

        } catch (Exception $e) {
            error_log("Error checking suspicious activity: " . $e->getMessage());
        }
    }
}

/**
 * Authentication result class
 */
class AuthResult
{
    private bool $success;
    private ?User $user;
    private string $message;
    private array $data;
    private bool $requiresMfa;

    private function __construct(
        bool $success, 
        ?User $user = null, 
        string $message = '', 
        array $data = [],
        bool $requiresMfa = false
    ) {
        $this->success = $success;
        $this->user = $user;
        $this->message = $message;
        $this->data = $data;
        $this->requiresMfa = $requiresMfa;
    }

    public static function success(User $user, array $data = []): self
    {
        return new self(true, $user, 'Authentication successful', $data);
    }

    public static function failed(string $message): self
    {
        return new self(false, null, $message);
    }

    public static function requiresMfa(int $userId): self
    {
        return new self(false, null, 'MFA required', ['user_id' => $userId], true);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function requiresMfa(): bool
    {
        return $this->requiresMfa;
    }
}