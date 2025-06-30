<?php

namespace YFEvents\Modules\YFAuth\Models;

use DateTime;
use Exception;
use PDO;

/**
 * Enhanced User model with security features
 * Implements Gemini's security recommendations
 */
class User
{
    private PDO $db;
    private array $attributes = [];
    private bool $exists = false;

    // Security constants
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_DURATION = 30 * 60; // 30 minutes in seconds
    const PASSWORD_MIN_LENGTH = 8;
    const PASSWORD_RESET_EXPIRY = 3600; // 1 hour

    private array $roles = [];
    private array $permissions = [];
    private bool $rolesLoaded = false;

    // Public properties
    public $id;
    public $username;
    public $email;
    public $password;
    public $first_name;
    public $last_name;
    public $is_active;
    public $email_verified;
    public $email_verification_token;
    public $last_login_at;
    public $failed_login_attempts;
    public $locked_until;
    public $password_changed_at;
    public $must_change_password;
    public $created_at;
    public $updated_at;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Fill model with data
     */
    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
                $this->attributes[$key] = $value;
            }
        }
        
        if (isset($data['id'])) {
            $this->exists = true;
        }
    }

    /**
     * Save model to database
     */
    public function save(): bool
    {
        try {
            if ($this->exists) {
                return $this->update();
            } else {
                return $this->insert();
            }
        } catch (Exception $e) {
            error_log("User save error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert new user
     */
    private function insert(): bool
    {
        $sql = "
            INSERT INTO auth_users (
                username, email, password, first_name, last_name, 
                is_active, email_verified, email_verification_token
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute([
            $this->username,
            $this->email,
            $this->password,
            $this->first_name,
            $this->last_name,
            $this->is_active,
            $this->email_verified,
            $this->email_verification_token
        ])) {
            $this->id = $this->db->lastInsertId();
            $this->exists = true;
            return true;
        }
        
        return false;
    }

    /**
     * Update existing user
     */
    private function update(): bool
    {
        $sql = "
            UPDATE auth_users SET 
                username = ?, email = ?, password = ?, first_name = ?, last_name = ?,
                is_active = ?, email_verified = ?, email_verification_token = ?,
                failed_login_attempts = ?, locked_until = ?, last_login_at = ?,
                password_changed_at = ?, must_change_password = ?
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $this->username,
            $this->email,
            $this->password,
            $this->first_name,
            $this->last_name,
            $this->is_active,
            $this->email_verified,
            $this->email_verification_token,
            $this->failed_login_attempts,
            $this->locked_until,
            $this->last_login_at,
            $this->password_changed_at,
            $this->must_change_password,
            $this->id
        ]);
    }

    /**
     * Check if account is locked due to failed login attempts
     */
    public function isAccountLocked(): bool
    {
        if (!$this->locked_until) {
            return false;
        }

        $lockedUntil = new DateTime($this->locked_until);
        $now = new DateTime();

        return $lockedUntil > $now;
    }

    /**
     * Increment failed login attempts and lock if necessary
     */
    public function incrementFailedAttempts(): void
    {
        $this->failed_login_attempts = ($this->failed_login_attempts ?? 0) + 1;

        if ($this->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $this->locked_until = date('Y-m-d H:i:s', time() + self::LOCKOUT_DURATION);
        }

        $this->save();
    }

    /**
     * Reset failed login attempts after successful login
     */
    public function resetFailedAttempts(): void
    {
        $this->failed_login_attempts = 0;
        $this->locked_until = null;
        $this->last_login_at = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Verify password against hash
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * Set password with secure hashing
     */
    public function setPassword(string $password): void
    {
        if (!$this->isValidPassword($password)) {
            throw new Exception('Password does not meet security requirements');
        }

        $this->password = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
        $this->password_changed_at = date('Y-m-d H:i:s');
        $this->must_change_password = false;
    }

    /**
     * Validate password strength
     */
    public function isValidPassword(string $password): bool
    {
        // Minimum length
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            return false;
        }

        // Must contain at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // Must contain at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        // Must contain at least one number
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        // Must contain at least one special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * Load user roles from database
     */
    public function loadRoles(): void
    {
        if ($this->rolesLoaded) {
            return;
        }

        $sql = "
            SELECT r.name, r.display_name, r.description
            FROM auth_roles r
            JOIN auth_user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ? 
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        $this->roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->rolesLoaded = true;
    }

    /**
     * Get user roles
     */
    public function getRoles(): array
    {
        $this->loadRoles();
        return $this->roles;
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $roleName): bool
    {
        $this->loadRoles();
        
        foreach ($this->roles as $role) {
            if ($role['name'] === $roleName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load user permissions from roles
     */
    public function loadPermissions(): void
    {
        $sql = "
            SELECT DISTINCT p.name, p.category, p.display_name
            FROM auth_permissions p
            JOIN auth_role_permissions rp ON p.id = rp.permission_id
            JOIN auth_user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        $this->permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get user permissions
     */
    public function getPermissions(): array
    {
        if (empty($this->permissions)) {
            $this->loadPermissions();
        }
        return $this->permissions;
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permissionName): bool
    {
        if (empty($this->permissions)) {
            $this->loadPermissions();
        }

        foreach ($this->permissions as $permission) {
            if ($permission['name'] === $permissionName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assign role to user
     */
    public function assignRole(int $roleId, ?int $assignedBy = null, ?string $expiresAt = null): void
    {
        $sql = "
            INSERT INTO auth_user_roles (user_id, role_id, assigned_by, expires_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                assigned_by = VALUES(assigned_by),
                expires_at = VALUES(expires_at),
                assigned_at = CURRENT_TIMESTAMP
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id, $roleId, $assignedBy, $expiresAt]);
        
        // Clear cached roles and permissions
        $this->roles = [];
        $this->permissions = [];
        $this->rolesLoaded = false;
    }

    /**
     * Remove role from user
     */
    public function removeRole(int $roleId): void
    {
        $sql = "DELETE FROM auth_user_roles WHERE user_id = ? AND role_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id, $roleId]);
        
        // Clear cached roles and permissions
        $this->roles = [];
        $this->permissions = [];
        $this->rolesLoaded = false;
    }

    /**
     * Check if MFA is enabled for user
     */
    public function isMfaEnabled(): bool
    {
        $sql = "SELECT is_enabled FROM auth_user_mfa WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (bool)$result['is_enabled'] : false;
    }

    /**
     * Get MFA configuration for user
     */
    public function getMfaConfig(): ?array
    {
        $sql = "SELECT * FROM auth_user_mfa WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create email verification token
     */
    public function generateEmailVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->email_verification_token = hash('sha256', $token);
        $this->save();
        
        return $token; // Return unhashed token for email
    }

    /**
     * Verify email with token
     */
    public function verifyEmail(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        
        if ($this->email_verification_token === $hashedToken) {
            $this->email_verified = true;
            $this->email_verification_token = null;
            $this->save();
            return true;
        }

        return false;
    }

    /**
     * Generate password reset token
     */
    public function generatePasswordResetToken(string $ipAddress, string $userAgent): string
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::PASSWORD_RESET_EXPIRY);

        $sql = "
            INSERT INTO auth_password_resets (user_id, token, expires_at, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id, $hashedToken, $expiresAt, $ipAddress, $userAgent]);

        return $token; // Return unhashed token for email
    }

    /**
     * Validate password reset token
     */
    public function validatePasswordResetToken(string $token): bool
    {
        $hashedToken = hash('sha256', $token);

        $sql = "
            SELECT id FROM auth_password_resets 
            WHERE user_id = ? AND token = ? AND expires_at > NOW() AND used_at IS NULL
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id, $hashedToken]);
        
        return $stmt->fetch() !== false;
    }

    /**
     * Use password reset token (mark as used)
     */
    public function usePasswordResetToken(string $token): void
    {
        $hashedToken = hash('sha256', $token);

        $sql = "
            UPDATE auth_password_resets 
            SET used_at = NOW() 
            WHERE user_id = ? AND token = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id, $hashedToken]);
    }

    /**
     * Get user's full name
     */
    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Check if user is active and can login
     */
    public function canLogin(): bool
    {
        return $this->is_active && 
               $this->email_verified && 
               !$this->isAccountLocked();
    }

    /**
     * Get user profile data for API responses
     */
    public function getProfile(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->getFullName(),
            'is_active' => $this->is_active,
            'email_verified' => $this->email_verified,
            'last_login_at' => $this->last_login_at,
            'mfa_enabled' => $this->isMfaEnabled(),
            'roles' => $this->getRoles(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Create new user with security defaults
     */
    public static function createUser(PDO $db, array $data): self
    {
        $user = new self($db);
        
        // Validate required fields
        $required = ['username', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '{$field}' is required");
            }
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Check for existing username/email
        if (self::usernameExists($db, $data['username'])) {
            throw new Exception('Username already exists');
        }

        if (self::emailExists($db, $data['email'])) {
            throw new Exception('Email already exists');
        }

        // Set user data
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->first_name = $data['first_name'] ?? '';
        $user->last_name = $data['last_name'] ?? '';
        $user->is_active = $data['is_active'] ?? true;
        $user->email_verified = false; // Always require email verification

        // Set password with validation
        $user->setPassword($data['password']);

        // Save user
        $user->save();

        // Assign default user role
        $defaultRole = Role::findByName($db, 'user');
        if ($defaultRole) {
            $user->assignRole($defaultRole->id);
        }

        return $user;
    }

    /**
     * Check if username exists
     */
    public static function usernameExists(PDO $db, string $username): bool
    {
        $sql = "SELECT COUNT(*) FROM auth_users WHERE username = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$username]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Check if email exists
     */
    public static function emailExists(PDO $db, string $email): bool
    {
        $sql = "SELECT COUNT(*) FROM auth_users WHERE email = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Find user by username or email
     */
    public static function findByLogin(PDO $db, string $login): ?self
    {
        $sql = "SELECT * FROM auth_users WHERE username = ? OR email = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$login, $login]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $user = new self($db);
            $user->fill($data);
            return $user;
        }

        return null;
    }
}