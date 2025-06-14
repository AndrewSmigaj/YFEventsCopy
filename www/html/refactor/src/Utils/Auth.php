<?php

namespace YFEvents\Utils;

use PDO;
use Exception;

class Auth {
    private $db;
    private $sessionLifetime;
    private $maxLoginAttempts;
    
    public function __construct(PDO $db) {
        $this->db = $db;
        $this->sessionLifetime = $_ENV['SESSION_LIFETIME'] ?? 86400; // 24 hours
        $this->maxLoginAttempts = 5;
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            session_start();
        }
    }
    
    /**
     * Login user with username/email and password
     */
    public function login($login, $password, $rememberMe = false) {
        try {
            // Find user by email or username
            $stmt = $this->db->prepare("
                SELECT * FROM users 
                WHERE (email = ? OR username = ?) 
                AND status = 'active'
            ");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
                return ['success' => false, 'message' => "Account locked for {$remaining} more minutes"];
            }
            
            // Check failed login attempts
            if ($user['login_attempts'] >= $this->maxLoginAttempts) {
                // Lock account for 30 minutes
                $lockUntil = date('Y-m-d H:i:s', time() + 1800);
                $stmt = $this->db->prepare("UPDATE users SET locked_until = ? WHERE id = ?");
                $stmt->execute([$lockUntil, $user['id']]);
                
                return ['success' => false, 'message' => 'Too many failed attempts. Account locked for 30 minutes.'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                // Increment failed attempts
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET login_attempts = login_attempts + 1 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);
                
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Success - reset failed attempts and update last login
            $stmt = $this->db->prepare("
                UPDATE users 
                SET login_attempts = 0, 
                    locked_until = NULL,
                    last_login = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);
            
            // Create session
            $sessionId = $this->createSession($user['id'], $rememberMe);
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['session_id'] = $sessionId;
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed due to system error'];
        }
    }
    
    /**
     * Register new user
     */
    public function register($data) {
        try {
            // Validate required fields
            $required = ['username', 'email', 'password'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => ucfirst($field) . ' is required'];
                }
            }
            
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }
            
            // Validate password strength
            if (strlen($data['password']) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters'];
            }
            
            // Check if username or email already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$data['username'], $data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, email_verified)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['username'],
                $data['email'],
                $passwordHash,
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                true // Auto-verify for now
            ]);
            
            $userId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Registration successful',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed due to system error'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        try {
            if (isset($_SESSION['session_id'])) {
                // Deactivate session in database
                $stmt = $this->db->prepare("UPDATE user_sessions SET is_active = FALSE WHERE id = ?");
                $stmt->execute([$_SESSION['session_id']]);
            }
            
            // Clear session
            session_unset();
            session_destroy();
            
            return ['success' => true, 'message' => 'Logged out successfully'];
            
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Logout failed'];
        }
    }
    
    /**
     * Get current authenticated user
     */
    public function getCurrentUser() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return null;
        }
        
        try {
            // Verify session is still valid
            $stmt = $this->db->prepare("
                SELECT s.*, u.*
                FROM user_sessions s
                JOIN users u ON s.user_id = u.id
                WHERE s.id = ? AND s.is_active = TRUE AND s.expires_at > NOW()
            ");
            $stmt->execute([$_SESSION['session_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                $this->logout();
                return null;
            }
            
            return [
                'id' => $result['user_id'],
                'username' => $result['username'],
                'email' => $result['email'],
                'first_name' => $result['first_name'],
                'last_name' => $result['last_name'],
                'role' => $result['role']
            ];
            
        } catch (Exception $e) {
            error_log("getCurrentUser error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        $roles = ['user', 'moderator', 'admin'];
        $userRoleIndex = array_search($user['role'], $roles);
        $requiredRoleIndex = array_search($role, $roles);
        
        return $userRoleIndex !== false && $userRoleIndex >= $requiredRoleIndex;
    }
    
    /**
     * Require authentication - redirect or return error if not authenticated
     */
    public function requireAuth() {
        if (!$this->getCurrentUser()) {
            if ($this->isAjaxRequest()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Authentication required']);
                exit;
            } else {
                header('Location: /auth/login');
                exit;
            }
        }
    }
    
    /**
     * Require specific role
     */
    public function requireRole($role) {
        $this->requireAuth();
        
        if (!$this->hasRole($role)) {
            if ($this->isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
                exit;
            } else {
                http_response_code(403);
                echo "Access denied";
                exit;
            }
        }
    }
    
    /**
     * Create session record in database
     */
    private function createSession($userId, $rememberMe = false) {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($rememberMe ? 2592000 : $this->sessionLifetime)); // 30 days if remember me
        
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $sessionId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiresAt
        ]);
        
        return $sessionId;
    }
    
    /**
     * Check if request is AJAX
     */
    private function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanupSessions() {
        try {
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Session cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}