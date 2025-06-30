<?php

namespace YFEvents\Modules\YFAuth\Models;

use PDO;

/**
 * Role model for enhanced authentication system
 */
class Role extends BaseModel
{
    protected $table = 'auth_roles';
    protected $fillable = ['name', 'display_name', 'description', 'is_system_role'];

    /**
     * Find role by name
     */
    public static function findByName(PDO $db, string $name): ?self
    {
        $sql = "SELECT * FROM auth_roles WHERE name = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$name]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            $role = new self($db);
            $role->fill($data);
            return $role;
        }

        return null;
    }

    /**
     * Get all permissions for this role
     */
    public function getPermissions(): array
    {
        $sql = "
            SELECT p.*
            FROM auth_permissions p
            JOIN auth_role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.category, p.name
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assign permission to this role
     */
    public function assignPermission(int $permissionId, ?int $grantedBy = null): bool
    {
        $sql = "
            INSERT IGNORE INTO auth_role_permissions (role_id, permission_id, granted_by)
            VALUES (?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$this->id, $permissionId, $grantedBy]);
    }

    /**
     * Remove permission from this role
     */
    public function removePermission(int $permissionId): bool
    {
        $sql = "DELETE FROM auth_role_permissions WHERE role_id = ? AND permission_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$this->id, $permissionId]);
    }

    /**
     * Get all users with this role
     */
    public function getUsers(): array
    {
        $sql = "
            SELECT u.*
            FROM auth_users u
            JOIN auth_user_roles ur ON u.id = ur.user_id
            WHERE ur.role_id = ?
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
            ORDER BY u.created_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if role has specific permission
     */
    public function hasPermission(string $permissionName): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM auth_permissions p
            JOIN auth_role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ? AND p.name = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id, $permissionName]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get role with permission and user counts
     */
    public function getWithCounts(): array
    {
        $sql = "
            SELECT 
                r.*,
                COUNT(DISTINCT rp.permission_id) as permission_count,
                COUNT(DISTINCT ur.user_id) as user_count
            FROM auth_roles r
            LEFT JOIN auth_role_permissions rp ON r.id = rp.role_id
            LEFT JOIN auth_user_roles ur ON r.id = ur.role_id 
                AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
            WHERE r.id = ?
            GROUP BY r.id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}