<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Services;

use PDO;
use Exception;

/**
 * Permission service for role-based access control
 */
class PermissionService
{
    private PDO $pdo;
    private array $userPermissionsCache = [];
    private array $rolePermissionsCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check if user has specific permission
     */
    public function userCan(int $userId, string $permission): bool
    {
        try {
            $permissions = $this->getUserPermissions($userId);
            return in_array($permission, $permissions);
        } catch (Exception $e) {
            error_log("Error checking user permission: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has any of the specified permissions
     */
    public function userCanAny(int $userId, array $permissions): bool
    {
        try {
            $userPermissions = $this->getUserPermissions($userId);
            return !empty(array_intersect($permissions, $userPermissions));
        } catch (Exception $e) {
            error_log("Error checking user permissions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has all of the specified permissions
     */
    public function userCanAll(int $userId, array $permissions): bool
    {
        try {
            $userPermissions = $this->getUserPermissions($userId);
            return empty(array_diff($permissions, $userPermissions));
        } catch (Exception $e) {
            error_log("Error checking user permissions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all permissions for a user (via roles)
     */
    public function getUserPermissions(int $userId): array
    {
        if (isset($this->userPermissionsCache[$userId])) {
            return $this->userPermissionsCache[$userId];
        }

        try {
            // Get user's roles
            $rolesSql = "
                SELECT r.name, r.id
                FROM yfa_roles r
                INNER JOIN yfa_user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
            ";
            $rolesStmt = $this->pdo->prepare($rolesSql);
            $rolesStmt->execute([$userId]);
            $roles = $rolesStmt->fetchAll();

            $permissions = [];
            
            // Get permissions for each role
            foreach ($roles as $role) {
                $rolePermissions = $this->getRolePermissions($role['id']);
                $permissions = array_merge($permissions, $rolePermissions);
            }

            // Remove duplicates
            $permissions = array_unique($permissions);
            
            // Cache result
            $this->userPermissionsCache[$userId] = $permissions;
            
            return $permissions;
            
        } catch (Exception $e) {
            error_log("Error getting user permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all permissions for a role
     */
    public function getRolePermissions(int $roleId): array
    {
        if (isset($this->rolePermissionsCache[$roleId])) {
            return $this->rolePermissionsCache[$roleId];
        }

        try {
            $sql = "
                SELECT p.name
                FROM yfa_permissions p
                INNER JOIN yfa_role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$roleId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Cache result
            $this->rolePermissionsCache[$roleId] = $permissions;
            
            return $permissions;
            
        } catch (Exception $e) {
            error_log("Error getting role permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's roles
     */
    public function getUserRoles(int $userId): array
    {
        try {
            $sql = "
                SELECT r.id, r.name, r.display_name, r.description
                FROM yfa_roles r
                INNER JOIN yfa_user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
                ORDER BY r.name
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error getting user roles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUser(int $userId, int $roleId): bool
    {
        try {
            // Check if assignment already exists
            $checkSql = "SELECT id FROM yfa_user_roles WHERE user_id = ? AND role_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$userId, $roleId]);
            
            if ($checkStmt->fetch()) {
                return true; // Already assigned
            }
            
            // Insert new assignment
            $sql = "INSERT INTO yfa_user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$userId, $roleId]);
            
            // Clear cache
            unset($this->userPermissionsCache[$userId]);
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Error assigning role to user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove role from user
     */
    public function removeRoleFromUser(int $userId, int $roleId): bool
    {
        try {
            $sql = "DELETE FROM yfa_user_roles WHERE user_id = ? AND role_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$userId, $roleId]);
            
            // Clear cache
            unset($this->userPermissionsCache[$userId]);
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Error removing role from user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync user roles (replace all current roles with new ones)
     */
    public function syncUserRoles(int $userId, array $roleIds): bool
    {
        try {
            $this->pdo->beginTransaction();
            
            // Remove all current roles
            $deleteSql = "DELETE FROM yfa_user_roles WHERE user_id = ?";
            $deleteStmt = $this->pdo->prepare($deleteSql);
            $deleteStmt->execute([$userId]);
            
            // Add new roles
            if (!empty($roleIds)) {
                $insertSql = "INSERT INTO yfa_user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())";
                $insertStmt = $this->pdo->prepare($insertSql);
                
                foreach ($roleIds as $roleId) {
                    $insertStmt->execute([$userId, (int)$roleId]);
                }
            }
            
            $this->pdo->commit();
            
            // Clear cache
            unset($this->userPermissionsCache[$userId]);
            
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Error syncing user roles: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all available roles
     */
    public function getAllRoles(): array
    {
        try {
            $sql = "SELECT id, name, display_name, description, is_system FROM yfa_roles ORDER BY name";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error getting all roles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all available permissions
     */
    public function getAllPermissions(): array
    {
        try {
            $sql = "SELECT id, name, display_name, description, module FROM yfa_permissions ORDER BY module, name";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error getting all permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get permissions grouped by module
     */
    public function getPermissionsByModule(): array
    {
        try {
            $permissions = $this->getAllPermissions();
            $grouped = [];
            
            foreach ($permissions as $permission) {
                $module = $permission['module'] ?? 'other';
                $grouped[$module][] = $permission;
            }
            
            return $grouped;
            
        } catch (Exception $e) {
            error_log("Error grouping permissions by module: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Assign permission to role
     */
    public function assignPermissionToRole(int $roleId, int $permissionId): bool
    {
        try {
            // Check if assignment already exists
            $checkSql = "SELECT id FROM yfa_role_permissions WHERE role_id = ? AND permission_id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$roleId, $permissionId]);
            
            if ($checkStmt->fetch()) {
                return true; // Already assigned
            }
            
            // Insert new assignment
            $sql = "INSERT INTO yfa_role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$roleId, $permissionId]);
            
            // Clear role cache
            unset($this->rolePermissionsCache[$roleId]);
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Error assigning permission to role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove permission from role
     */
    public function removePermissionFromRole(int $roleId, int $permissionId): bool
    {
        try {
            $sql = "DELETE FROM yfa_role_permissions WHERE role_id = ? AND permission_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$roleId, $permissionId]);
            
            // Clear role cache
            unset($this->rolePermissionsCache[$roleId]);
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Error removing permission from role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync role permissions (replace all current permissions with new ones)
     */
    public function syncRolePermissions(int $roleId, array $permissionIds): bool
    {
        try {
            $this->pdo->beginTransaction();
            
            // Remove all current permissions
            $deleteSql = "DELETE FROM yfa_role_permissions WHERE role_id = ?";
            $deleteStmt = $this->pdo->prepare($deleteSql);
            $deleteStmt->execute([$roleId]);
            
            // Add new permissions
            if (!empty($permissionIds)) {
                $insertSql = "INSERT INTO yfa_role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())";
                $insertStmt = $this->pdo->prepare($insertSql);
                
                foreach ($permissionIds as $permissionId) {
                    $insertStmt->execute([$roleId, (int)$permissionId]);
                }
            }
            
            $this->pdo->commit();
            
            // Clear cache
            unset($this->rolePermissionsCache[$roleId]);
            
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Error syncing role permissions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user can manage a specific resource (for "own" permissions)
     */
    public function userCanManageResource(int $userId, string $basePermission, int $resourceOwnerId = null): bool
    {
        // Check if user has full permission
        if ($this->userCan($userId, $basePermission)) {
            return true;
        }
        
        // Check if user has "manage_own" permission and owns the resource
        $ownPermission = str_replace('.', '.manage_own.', $basePermission);
        if ($resourceOwnerId && $this->userCan($userId, $ownPermission) && $userId === $resourceOwnerId) {
            return true;
        }
        
        return false;
    }

    /**
     * Clear all permission caches
     */
    public function clearCache(): void
    {
        $this->userPermissionsCache = [];
        $this->rolePermissionsCache = [];
    }

    /**
     * Get current user ID from session
     */
    public function getCurrentUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Check if current session user has permission
     */
    public function currentUserCan(string $permission): bool
    {
        $userId = $this->getCurrentUserId();
        return $userId ? $this->userCan($userId, $permission) : false;
    }
}