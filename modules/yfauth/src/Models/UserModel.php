<?php
namespace YFEvents\Modules\YFAuth\Models;

use PDO;
use Exception;

class UserModel extends BaseModel {
    protected $table = 'yfa_auth_users';
    protected $fillable = [
        'username', 'email', 'password_hash', 'first_name', 'last_name', 
        'phone', 'status', 'email_verified', 'email_verification_token',
        'email_verified_at', 'password_reset_token', 'password_reset_expires',
        'two_factor_enabled', 'two_factor_secret', 'last_login_at', 'last_ip'
    ];
    
    /**
     * Find user by email
     */
    public function findByEmail($email) {
        return $this->findWhere(['email' => $email]);
    }
    
    /**
     * Find user by username
     */
    public function findByUsername($username) {
        return $this->findWhere(['username' => $username]);
    }
    
    /**
     * Find user by email or username
     */
    public function findByCredentials($credential) {
        $sql = "SELECT * FROM {$this->table} WHERE email = ? OR username = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$credential, $credential]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create user with hashed password
     */
    public function createUser($data) {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        
        // Generate email verification token
        if (!isset($data['email_verification_token'])) {
            $data['email_verification_token'] = bin2hex(random_bytes(32));
        }
        
        return $this->create($data);
    }
    
    /**
     * Verify user password
     */
    public function verifyPassword($user, $password) {
        return password_verify($password, $user['password_hash']);
    }
    
    /**
     * Update password
     */
    public function updatePassword($userId, $newPassword) {
        return $this->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'password_reset_token' => null,
            'password_reset_expires' => null
        ]);
    }
    
    /**
     * Generate password reset token
     */
    public function generatePasswordResetToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        $this->update($userId, [
            'password_reset_token' => $token,
            'password_reset_expires' => $expires
        ]);
        
        return $token;
    }
    
    /**
     * Verify password reset token
     */
    public function verifyPasswordResetToken($token) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE password_reset_token = ? 
                AND password_reset_expires > NOW() 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Verify email
     */
    public function verifyEmail($token) {
        $sql = "SELECT * FROM {$this->table} WHERE email_verification_token = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $this->update($user['id'], [
                'email_verified' => true,
                'email_verified_at' => date('Y-m-d H:i:s'),
                'email_verification_token' => null,
                'status' => 'active'
            ]);
            return $user;
        }
        
        return false;
    }
    
    /**
     * Update last login
     */
    public function updateLastLogin($userId, $ipAddress = null) {
        return $this->update($userId, [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_ip' => $ipAddress
        ]);
    }
    
    /**
     * Get user roles
     */
    public function getRoles($userId) {
        $sql = "SELECT r.* FROM yfa_auth_roles r
                JOIN yfa_auth_user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
                ORDER BY r.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Has role check
     */
    public function hasRole($userId, $roleName) {
        $sql = "SELECT COUNT(*) FROM yfa_auth_user_roles ur
                JOIN yfa_auth_roles r ON ur.role_id = r.id
                WHERE ur.user_id = ? AND r.name = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $roleName]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get user permissions
     */
    public function getPermissions($userId) {
        $sql = "SELECT DISTINCT p.* FROM yfa_auth_permissions p
                JOIN yfa_auth_role_permissions rp ON p.id = rp.permission_id
                JOIN yfa_auth_user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = ?
                ORDER BY p.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Has permission check
     */
    public function hasPermission($userId, $permissionName) {
        $sql = "SELECT COUNT(*) FROM yfa_auth_permissions p
                JOIN yfa_auth_role_permissions rp ON p.id = rp.permission_id
                JOIN yfa_auth_user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = ? AND p.name = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $permissionName]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Assign role to user
     */
    public function assignRole($userId, $roleId, $assignedBy = null) {
        $sql = "INSERT INTO yfa_auth_user_roles (user_id, role_id, assigned_by) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE assigned_at = NOW(), assigned_by = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $roleId, $assignedBy, $assignedBy]);
    }
    
    /**
     * Remove role from user
     */
    public function removeRole($userId, $roleId) {
        $sql = "DELETE FROM yfa_auth_user_roles WHERE user_id = ? AND role_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $roleId]);
    }
    
    /**
     * Get users with pagination
     */
    public function paginate($page = 1, $perPage = 20, $conditions = [], $orderBy = 'created_at DESC') {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table}";
        $countSql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "$field = ?";
                $params[] = $value;
            }
            $whereClause = " WHERE " . implode(" AND ", $whereClauses);
            $sql .= $whereClause;
            $countSql .= $whereClause;
        }
        
        // Get total count
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get paginated results
        $sql .= " ORDER BY $orderBy LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param);
        }
        $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $users,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Search users
     */
    public function search($query, $limit = 10) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE (username LIKE ? OR email LIKE ? OR 
                       first_name LIKE ? OR last_name LIKE ?)
                ORDER BY created_at DESC
                LIMIT ?";
        
        $searchTerm = "%$query%";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $searchTerm);
        $stmt->bindValue(2, $searchTerm);
        $stmt->bindValue(3, $searchTerm);
        $stmt->bindValue(4, $searchTerm);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Enable two-factor authentication
     */
    public function enableTwoFactor($userId, $secret) {
        return $this->update($userId, [
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret
        ]);
    }
    
    /**
     * Disable two-factor authentication
     */
    public function disableTwoFactor($userId) {
        return $this->update($userId, [
            'two_factor_enabled' => false,
            'two_factor_secret' => null
        ]);
    }
}