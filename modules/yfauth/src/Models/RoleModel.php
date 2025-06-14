<?php
namespace YFEvents\Modules\YFAuth\Models;

use PDO;

class RoleModel extends BaseModel {
    protected $table = 'yfa_roles';
    protected $fillable = ['name', 'display_name', 'description', 'is_system'];
    
    /**
     * Find role by name
     */
    public function findByName($name) {
        return $this->findWhere(['name' => $name]);
    }
    
    /**
     * Get role permissions
     */
    public function getPermissions($roleId) {
        $sql = "SELECT p.* FROM yfa_permissions p
                JOIN yfa_role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
                ORDER BY p.module, p.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Assign permission to role
     */
    public function assignPermission($roleId, $permissionId) {
        $sql = "INSERT IGNORE INTO yfa_role_permissions (role_id, permission_id) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$roleId, $permissionId]);
    }
    
    /**
     * Remove permission from role
     */
    public function removePermission($roleId, $permissionId) {
        $sql = "DELETE FROM yfa_role_permissions WHERE role_id = ? AND permission_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$roleId, $permissionId]);
    }
    
    /**
     * Sync permissions (replace all)
     */
    public function syncPermissions($roleId, $permissionIds) {
        $this->beginTransaction();
        
        try {
            // Remove all existing permissions
            $sql = "DELETE FROM yfa_role_permissions WHERE role_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$roleId]);
            
            // Add new permissions
            if (!empty($permissionIds)) {
                $sql = "INSERT INTO yfa_role_permissions (role_id, permission_id) VALUES ";
                $values = [];
                $params = [];
                
                foreach ($permissionIds as $permissionId) {
                    $values[] = "(?, ?)";
                    $params[] = $roleId;
                    $params[] = $permissionId;
                }
                
                $sql .= implode(", ", $values);
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }
            
            $this->commit();
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get role users
     */
    public function getUsers($roleId) {
        $sql = "SELECT u.* FROM yfa_users u
                JOIN yfa_user_roles ur ON u.id = ur.user_id
                WHERE ur.role_id = ?
                ORDER BY u.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count users in role
     */
    public function getUserCount($roleId) {
        $sql = "SELECT COUNT(*) FROM yfa_user_roles WHERE role_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roleId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Check if role can be deleted
     */
    public function canDelete($roleId) {
        $role = $this->find($roleId);
        
        // Cannot delete system roles
        if ($role && $role['is_system']) {
            return false;
        }
        
        // Can delete if no users assigned
        return $this->getUserCount($roleId) == 0;
    }
    
    /**
     * Get roles with user counts
     */
    public function getAllWithCounts() {
        $sql = "SELECT r.*, COUNT(ur.user_id) as user_count
                FROM {$this->table} r
                LEFT JOIN yfa_user_roles ur ON r.id = ur.role_id
                GROUP BY r.id
                ORDER BY r.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}