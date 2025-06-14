<?php
namespace YFEvents\Modules\YFAuth\Services;

use YFEvents\Modules\YFAuth\Models\UserModel;
use YFEvents\Modules\YFAuth\Models\SessionModel;
use YFEvents\Modules\YFAuth\Models\LoginAttemptModel;
use PDO;
use Exception;

class AuthService {
    private $userModel;
    private $sessionModel;
    private $loginAttemptModel;
    private $config;
    
    public function __construct(PDO $db, array $config = []) {
        $this->userModel = new UserModel($db);
        $this->sessionModel = new SessionModel($db);
        $this->loginAttemptModel = new LoginAttemptModel($db);
        
        $this->config = array_merge([
            'session_lifetime' => 7200, // 2 hours
            'max_login_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
            'remember_me_duration' => 2592000, // 30 days
            'require_email_verification' => true
        ], $config);
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($credential, $password, $rememberMe = false) {
        // Check login attempts
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($this->isLockedOut($credential, $ipAddress)) {
            throw new Exception('Too many failed login attempts. Please try again later.');
        }
        
        // Find user
        $user = $this->userModel->findByCredentials($credential);
        
        if (!$user || !$this->userModel->verifyPassword($user, $password)) {
            $this->recordFailedAttempt($credential, $ipAddress);
            throw new Exception('Invalid credentials.');
        }
        
        // Check user status
        if ($user['status'] !== 'active') {
            throw new Exception('Your account is ' . $user['status'] . '.');
        }
        
        // Check email verification
        if ($this->config['require_email_verification'] && !$user['email_verified']) {
            throw new Exception('Please verify your email address before logging in.');
        }
        
        // Record successful attempt
        $this->recordSuccessfulAttempt($credential, $ipAddress);
        
        // Update last login
        $this->userModel->updateLastLogin($user['id'], $ipAddress);
        
        // Create session
        $sessionLifetime = $rememberMe ? $this->config['remember_me_duration'] : $this->config['session_lifetime'];
        $sessionId = $this->sessionModel->createSession(
            $user['id'], 
            $ipAddress, 
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $sessionLifetime
        );
        
        return [
            'user' => $user,
            'session_id' => $sessionId,
            'expires_at' => time() + $sessionLifetime
        ];
    }
    
    /**
     * Register new user
     */
    public function register($data) {
        // Validate required fields
        $required = ['username', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required.");
            }
        }
        
        // Check if email exists
        if ($this->userModel->findByEmail($data['email'])) {
            throw new Exception('Email already registered.');
        }
        
        // Check if username exists
        if ($this->userModel->findByUsername($data['username'])) {
            throw new Exception('Username already taken.');
        }
        
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address.');
        }
        
        // Validate username
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $data['username'])) {
            throw new Exception('Username must be 3-20 characters and contain only letters, numbers, and underscores.');
        }
        
        // Validate password
        if (strlen($data['password']) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }
        
        // Create user
        $userId = $this->userModel->createUser($data);
        
        // Assign default role
        $this->assignDefaultRole($userId);
        
        // Get created user
        $user = $this->userModel->find($userId);
        
        return $user;
    }
    
    /**
     * Verify session
     */
    public function verifySession($sessionId) {
        $session = $this->sessionModel->getSession($sessionId);
        
        if (!$session) {
            return null;
        }
        
        // Update activity
        $this->sessionModel->updateActivity($sessionId);
        
        // Get user with roles and permissions
        $user = $this->userModel->find($session['user_id']);
        if ($user) {
            $user['roles'] = $this->userModel->getRoles($user['id']);
            $user['permissions'] = $this->userModel->getPermissions($user['id']);
        }
        
        return $user;
    }
    
    /**
     * Logout
     */
    public function logout($sessionId) {
        return $this->sessionModel->deleteSession($sessionId);
    }
    
    /**
     * Logout all sessions
     */
    public function logoutAllSessions($userId, $exceptSessionId = null) {
        return $this->sessionModel->deleteUserSessions($userId, $exceptSessionId);
    }
    
    /**
     * Request password reset
     */
    public function requestPasswordReset($email) {
        $user = $this->userModel->findByEmail($email);
        
        if (!$user) {
            // Don't reveal if email exists
            return true;
        }
        
        $token = $this->userModel->generatePasswordResetToken($user['id']);
        
        // TODO: Send password reset email
        
        return $token;
    }
    
    /**
     * Reset password
     */
    public function resetPassword($token, $newPassword) {
        $user = $this->userModel->verifyPasswordResetToken($token);
        
        if (!$user) {
            throw new Exception('Invalid or expired reset token.');
        }
        
        // Validate password
        if (strlen($newPassword) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }
        
        // Update password
        $this->userModel->updatePassword($user['id'], $newPassword);
        
        // Logout all sessions
        $this->sessionModel->deleteUserSessions($user['id']);
        
        return true;
    }
    
    /**
     * Verify email
     */
    public function verifyEmail($token) {
        $user = $this->userModel->verifyEmail($token);
        
        if (!$user) {
            throw new Exception('Invalid verification token.');
        }
        
        return $user;
    }
    
    /**
     * Check if locked out
     */
    private function isLockedOut($credential, $ipAddress) {
        $failedCount = $this->loginAttemptModel->getFailedAttemptsCount(
            $credential, 
            $ipAddress, 
            $this->config['lockout_duration']
        );
        
        return $failedCount >= $this->config['max_login_attempts'];
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt($credential, $ipAddress) {
        $this->loginAttemptModel->recordAttempt($credential, $ipAddress, false);
    }
    
    /**
     * Record successful login attempt
     */
    private function recordSuccessfulAttempt($credential, $ipAddress) {
        $this->loginAttemptModel->recordAttempt($credential, $ipAddress, true);
    }
    
    /**
     * Assign default role to new user
     */
    private function assignDefaultRole($userId) {
        $db = $this->userModel->getDb();
        $roleModel = new \YFEvents\Modules\YFAuth\Models\RoleModel($db);
        
        $userRole = $roleModel->findByName('user');
        if ($userRole) {
            $this->userModel->assignRole($userId, $userRole['id']);
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        $user = $this->userModel->find($userId);
        
        if (!$user) {
            throw new Exception('User not found.');
        }
        
        // Verify current password
        if (!$this->userModel->verifyPassword($user, $currentPassword)) {
            throw new Exception('Current password is incorrect.');
        }
        
        // Validate new password
        if (strlen($newPassword) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }
        
        // Update password
        return $this->userModel->updatePassword($userId, $newPassword);
    }
    
    /**
     * Get active sessions for user
     */
    public function getActiveSessions($userId) {
        return $this->sessionModel->getUserSessions($userId);
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($userId, $permission) {
        return $this->userModel->hasPermission($userId, $permission);
    }
    
    /**
     * Check if user has role
     */
    public function hasRole($userId, $role) {
        return $this->userModel->hasRole($userId, $role);
    }
}