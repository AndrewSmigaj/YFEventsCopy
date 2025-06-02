<?php
namespace YFEvents\Modules\YFAuth\Services;

use PDO;
use Exception;

class AuthService {
    private $db;
    private $sessionLifetime;
    private $maxLoginAttempts;
    
    public function __construct(PDO $db) {
        $this->db = $db;
        $this->sessionLifetime = 7200; // 2 hours default
        $this->maxLoginAttempts = 5;
    }
    
    /**
     * Authenticate user with email/username and password
     */
    public function authenticate($username, $password) {
        // Clean any expired sessions first
        $this->cleanExpiredSessions();
        
        // Find user by email or username
        $stmt = $this->db->prepare("
            SELECT * FROM yfa_auth_users 
            WHERE (email = ? OR username = ?) 
            AND status != 'inactive'
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $this->logActivity(null, 'login_failed', "Invalid username: $username");
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Check if account is locked
        if ($user['status'] === 'locked') {
            return ['success' => false, 'error' => 'Account is locked. Please contact support.'];
        }
        
        // Check failed login attempts
        if ($user['failed_login_attempts'] >= $this->maxLoginAttempts) {
            $lastFailed = strtotime($user['last_failed_login']);
            $lockoutTime = 30 * 60; // 30 minutes
            
            if (time() - $lastFailed < $lockoutTime) {
                return ['success' => false, 'error' => 'Too many failed attempts. Try again later.'];
            }
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            // Increment failed attempts
            $stmt = $this->db->prepare("
                UPDATE yfa_auth_users 
                SET failed_login_attempts = failed_login_attempts + 1,
                    last_failed_login = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);
            
            $this->logActivity($user['id'], 'login_failed', 'Invalid password');
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Check if password needs rehashing
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE yfa_auth_users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $user['id']]);
        }
        
        // Success - reset failed attempts and update last login
        $stmt = $this->db->prepare("
            UPDATE yfa_auth_users 
            SET failed_login_attempts = 0,
                last_login = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        // Create session
        $sessionId = $this->createSession($user['id']);
        
        // Get user roles and permissions
        $roles = $this->getUserRoles($user['id']);
        $permissions = $this->getUserPermissions($user['id']);
        
        $this->logActivity($user['id'], 'login_success', 'Successful login');
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'roles' => $roles,
                'permissions' => $permissions
            ],
            'session_id' => $sessionId
        ];
    }
    
    /**
     * Create a new session
     */
    private function createSession($userId) {
        $sessionId = bin2hex(random_bytes(32));
        
        $stmt = $this->db->prepare("
            INSERT INTO yfa_auth_sessions (id, user_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $sessionId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return $sessionId;
    }
    
    /**
     * Validate session and get user
     */
    public function validateSession($sessionId) {
        $stmt = $this->db->prepare("
            SELECT s.*, u.* 
            FROM yfa_auth_sessions s
            JOIN yfa_auth_users u ON s.user_id = u.id
            WHERE s.id = ? 
            AND u.status = 'active'
            AND TIMESTAMPDIFF(SECOND, s.last_activity, NOW()) < ?
        ");
        
        $stmt->execute([$sessionId, $this->sessionLifetime]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return null;
        }
        
        // Update last activity
        $stmt = $this->db->prepare("
            UPDATE yfa_auth_sessions 
            SET last_activity = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$sessionId]);
        
        // Get roles and permissions
        $roles = $this->getUserRoles($session['user_id']);
        $permissions = $this->getUserPermissions($session['user_id']);
        
        return [
            'id' => $session['user_id'],
            'email' => $session['email'],
            'username' => $session['username'],
            'first_name' => $session['first_name'],
            'last_name' => $session['last_name'],
            'roles' => $roles,
            'permissions' => $permissions
        ];
    }
    
    /**
     * Logout user
     */
    public function logout($sessionId) {
        $stmt = $this->db->prepare("DELETE FROM yfa_auth_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        
        return true;
    }
    
    /**
     * Get user roles
     */
    public function getUserRoles($userId) {
        $stmt = $this->db->prepare("
            SELECT r.name, r.display_name
            FROM yfa_auth_roles r
            JOIN yfa_auth_user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user permissions
     */
    public function getUserPermissions($userId) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.name, p.module
            FROM yfa_auth_permissions p
            JOIN yfa_auth_role_permissions rp ON p.id = rp.permission_id
            JOIN yfa_auth_user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
        ");
        
        $stmt->execute([$userId]);
        $permissions = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[] = $row['name'];
        }
        
        return $permissions;
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($userId, $permission) {
        $permissions = $this->getUserPermissions($userId);
        return in_array($permission, $permissions);
    }
    
    /**
     * Check if user has role
     */
    public function hasRole($userId, $roleName) {
        $roles = $this->getUserRoles($userId);
        
        foreach ($roles as $role) {
            if ($role['name'] === $roleName) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Create new user
     */
    public function createUser($data) {
        // Validate required fields
        if (empty($data['email']) || empty($data['username']) || empty($data['password'])) {
            throw new Exception('Email, username, and password are required');
        }
        
        // Check if email or username already exists
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM yfa_auth_users 
            WHERE email = ? OR username = ?
        ");
        $stmt->execute([$data['email'], $data['username']]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Email or username already exists');
        }
        
        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Generate email verification token
        $verificationToken = bin2hex(random_bytes(32));
        
        // Insert user
        $stmt = $this->db->prepare("
            INSERT INTO yfa_auth_users 
            (email, username, password_hash, first_name, last_name, phone, 
             email_verification_token, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['email'],
            $data['username'],
            $passwordHash,
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['phone'] ?? null,
            $verificationToken,
            $data['auto_activate'] ? 'active' : 'pending'
        ]);
        
        $userId = $this->db->lastInsertId();
        
        // Assign default role
        $defaultRole = $data['default_role'] ?? 'registered_user';
        $this->assignRole($userId, $defaultRole);
        
        $this->logActivity($userId, 'user_created', 'New user account created');
        
        return [
            'id' => $userId,
            'email' => $data['email'],
            'username' => $data['username'],
            'verification_token' => $verificationToken
        ];
    }
    
    /**
     * Assign role to user
     */
    public function assignRole($userId, $roleName, $assignedBy = null) {
        // Get role ID
        $stmt = $this->db->prepare("SELECT id FROM yfa_auth_roles WHERE name = ?");
        $stmt->execute([$roleName]);
        $roleId = $stmt->fetchColumn();
        
        if (!$roleId) {
            throw new Exception("Role '$roleName' not found");
        }
        
        // Check if already assigned
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM yfa_auth_user_roles 
            WHERE user_id = ? AND role_id = ?
        ");
        $stmt->execute([$userId, $roleId]);
        
        if ($stmt->fetchColumn() > 0) {
            return true; // Already assigned
        }
        
        // Assign role
        $stmt = $this->db->prepare("
            INSERT INTO yfa_auth_user_roles (user_id, role_id, assigned_by)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $roleId, $assignedBy]);
        
        $this->logActivity($userId, 'role_assigned', "Role '$roleName' assigned");
        
        return true;
    }
    
    /**
     * Log activity
     */
    private function logActivity($userId, $action, $details = null) {
        $stmt = $this->db->prepare("
            INSERT INTO yfa_auth_activity_log 
            (user_id, action, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    /**
     * Clean expired sessions
     */
    private function cleanExpiredSessions() {
        $stmt = $this->db->prepare("
            DELETE FROM yfa_auth_sessions 
            WHERE TIMESTAMPDIFF(SECOND, last_activity, NOW()) > ?
        ");
        $stmt->execute([$this->sessionLifetime]);
    }
}