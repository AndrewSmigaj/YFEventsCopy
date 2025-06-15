<?php

namespace YFEvents\Modules\YFAuth\Services;

use YFEvents\Modules\YFAuth\Models\User;
use Exception;
use PDO;

/**
 * Session Management Service
 * Provides secure session handling with Redis support
 */
class SessionService
{
    private PDO $db;
    private SecurityLogService $securityLogger;
    private bool $useRedis;
    private $redis;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->securityLogger = new SecurityLogService($db);
        $this->useRedis = extension_loaded('redis') && !empty($_ENV['REDIS_HOST']);
        
        if ($this->useRedis) {
            $this->initializeRedis();
        }
        
        $this->configureSession();
    }

    /**
     * Start secure session
     */
    public function start(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        if (!session_start()) {
            error_log("Failed to start session");
            return false;
        }

        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration']) || 
            time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            $this->regenerateId();
        }

        return true;
    }

    /**
     * Login user and create session
     */
    public function loginUser(User $user, bool $rememberMe = false): bool
    {
        try {
            if (!$this->start()) {
                return false;
            }

            // Regenerate session ID on login
            $this->regenerateId(true);

            // Store user data in session
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;
            $_SESSION['email'] = $user->email;
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            // Set remember me cookie if requested
            if ($rememberMe) {
                $this->setRememberMeCookie($user);
            }

            // Log successful login
            $this->securityLogger->logSecurityEvent(
                $user->id,
                'session_created',
                'low',
                "User session created: {$user->username}",
                [
                    'session_id' => session_id(),
                    'remember_me' => $rememberMe
                ]
            );

            return true;

        } catch (Exception $e) {
            error_log("Session login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Logout user and destroy session
     */
    public function logoutUser(): bool
    {
        try {
            if (!$this->start()) {
                return false;
            }

            $userId = $_SESSION['user_id'] ?? null;

            // Clear remember me cookie
            $this->clearRememberMeCookie();

            // Log logout
            if ($userId) {
                $this->securityLogger->logSecurityEvent(
                    $userId,
                    'session_destroyed',
                    'low',
                    "User session destroyed",
                    ['session_id' => session_id()]
                );
            }

            // Clear all session data
            $_SESSION = [];

            // Delete session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }

            // Destroy session
            session_destroy();

            return true;

        } catch (Exception $e) {
            error_log("Session logout error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        if (!$this->start()) {
            return false;
        }

        if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
            return false;
        }

        // Check session timeout
        if ($this->isSessionExpired()) {
            $this->logoutUser();
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Get current user from session
     */
    public function getCurrentUser(): ?User
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            $user = User::find($this->db, $_SESSION['user_id']);
            
            // Verify user is still active
            if (!$user || !$user->is_active) {
                $this->logoutUser();
                return null;
            }

            return $user;

        } catch (Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken(): string
    {
        if (!$this->start()) {
            throw new Exception("Cannot generate CSRF token - session not started");
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();

        return $token;
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRFToken(string $token): bool
    {
        if (!$this->start()) {
            return false;
        }

        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
            return false;
        }

        // Check token age (5 minutes max)
        if (time() - $_SESSION['csrf_token_time'] > 300) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Set remember me cookie
     */
    private function setRememberMeCookie(User $user): void
    {
        try {
            $token = bin2hex(random_bytes(32));
            $hashedToken = password_hash($token, PASSWORD_ARGON2ID);
            $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

            // Store in database
            $sql = "
                INSERT INTO auth_remember_tokens (user_id, token, expires_at)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user->id, $hashedToken, $expiresAt]);

            // Set cookie
            setcookie(
                'remember_token',
                $token,
                time() + (86400 * 30), // 30 days
                '/',
                '',
                true, // Secure
                true  // HttpOnly
            );

        } catch (Exception $e) {
            error_log("Remember me cookie error: " . $e->getMessage());
        }
    }

    /**
     * Clear remember me cookie
     */
    private function clearRememberMeCookie(): void
    {
        try {
            // Clear database record
            if (isset($_COOKIE['remember_token'])) {
                $sql = "DELETE FROM auth_remember_tokens WHERE token = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([password_hash($_COOKIE['remember_token'], PASSWORD_ARGON2ID)]);
            }

            // Clear cookie
            setcookie('remember_token', '', time() - 3600, '/');

        } catch (Exception $e) {
            error_log("Clear remember cookie error: " . $e->getMessage());
        }
    }

    /**
     * Check remember me cookie and auto-login
     */
    public function checkRememberMe(): ?User
    {
        if (empty($_COOKIE['remember_token']) || $this->isLoggedIn()) {
            return null;
        }

        try {
            $token = $_COOKIE['remember_token'];

            $sql = "
                SELECT u.* FROM auth_users u
                JOIN auth_remember_tokens rt ON u.id = rt.user_id
                WHERE rt.expires_at > NOW() AND u.is_active = 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as $userData) {
                $sql = "SELECT token FROM auth_remember_tokens WHERE user_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$userData['id']]);
                $hashedToken = $stmt->fetchColumn();

                if (password_verify($token, $hashedToken)) {
                    $user = new User($this->db);
                    $user->fill($userData);
                    
                    // Auto-login user
                    $this->loginUser($user, true);
                    
                    return $user;
                }
            }

        } catch (Exception $e) {
            error_log("Remember me check error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Check if session is expired
     */
    private function isSessionExpired(): bool
    {
        $maxLifetime = ini_get('session.gc_maxlifetime') ?: 1440; // 24 minutes default
        $lastActivity = $_SESSION['last_activity'] ?? 0;

        return (time() - $lastActivity) > $maxLifetime;
    }

    /**
     * Regenerate session ID
     */
    private function regenerateId(bool $deleteOld = false): void
    {
        session_regenerate_id($deleteOld);
        $_SESSION['last_regeneration'] = time();
    }

    /**
     * Configure secure session settings
     */
    private function configureSession(): void
    {
        // Prevent JavaScript access to session cookie
        ini_set('session.cookie_httponly', 1);
        
        // Use secure cookies if HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        // Prevent session fixation
        ini_set('session.use_strict_mode', 1);
        
        // Set session name
        session_name('YFEVENTS_SESSION');
        
        // Set custom session handler if using Redis
        if ($this->useRedis) {
            session_set_save_handler(
                [$this, 'sessionOpen'],
                [$this, 'sessionClose'],
                [$this, 'sessionRead'],
                [$this, 'sessionWrite'],
                [$this, 'sessionDestroy'],
                [$this, 'sessionGc']
            );
        }
    }

    /**
     * Initialize Redis connection
     */
    private function initializeRedis(): void
    {
        try {
            $this->redis = new \Redis();
            $this->redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', $_ENV['REDIS_PORT'] ?? 6379);
            
            if (!empty($_ENV['REDIS_PASSWORD'])) {
                $this->redis->auth($_ENV['REDIS_PASSWORD']);
            }
            
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->useRedis = false;
        }
    }

    // Redis session handlers (if Redis is available)
    public function sessionOpen($savePath, $sessionName): bool
    {
        return true;
    }

    public function sessionClose(): bool
    {
        return true;
    }

    public function sessionRead($sessionId): string
    {
        if (!$this->useRedis) return '';
        
        try {
            return $this->redis->get("session:$sessionId") ?: '';
        } catch (Exception $e) {
            error_log("Redis session read error: " . $e->getMessage());
            return '';
        }
    }

    public function sessionWrite($sessionId, $sessionData): bool
    {
        if (!$this->useRedis) return true;
        
        try {
            $ttl = ini_get('session.gc_maxlifetime') ?: 1440;
            return $this->redis->setex("session:$sessionId", $ttl, $sessionData);
        } catch (Exception $e) {
            error_log("Redis session write error: " . $e->getMessage());
            return false;
        }
    }

    public function sessionDestroy($sessionId): bool
    {
        if (!$this->useRedis) return true;
        
        try {
            return $this->redis->del("session:$sessionId") > 0;
        } catch (Exception $e) {
            error_log("Redis session destroy error: " . $e->getMessage());
            return false;
        }
    }

    public function sessionGc($maxLifetime): int
    {
        // Redis handles expiration automatically
        return 0;
    }
}