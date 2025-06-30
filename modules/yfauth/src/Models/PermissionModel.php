<?php
namespace YFEvents\Modules\YFAuth\Models;

use PDO;

class PermissionModel extends BaseModel {
    protected $table = 'yfa_permissions';
    protected $fillable = ['name', 'display_name', 'description', 'module'];
    
    /**
     * Find permission by name
     */
    public function findByName($name) {
        return $this->findWhere(['name' => $name]);
    }
    
    /**
     * Get permissions by module
     */
    public function getByModule($module) {
        return $this->where(['module' => $module], 'name ASC');
    }
    
    /**
     * Get permissions grouped by module
     */
    public function getAllGroupedByModule() {
        $sql = "SELECT * FROM {$this->table} ORDER BY module, name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $grouped = [];
        foreach ($permissions as $permission) {
            $module = $permission['module'] ?: 'general';
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $permission;
        }
        
        return $grouped;
    }
    
    /**
     * Get roles that have this permission
     */
    public function getRoles($permissionId) {
        $sql = "SELECT r.* FROM yfa_roles r
                JOIN yfa_role_permissions rp ON r.id = rp.role_id
                WHERE rp.permission_id = ?
                ORDER BY r.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$permissionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if permission is assigned to any role
     */
    public function isAssigned($permissionId) {
        $sql = "SELECT COUNT(*) FROM yfa_role_permissions WHERE permission_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$permissionId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Create multiple permissions at once
     */
    public function createBatch($permissions) {
        $this->beginTransaction();
        
        try {
            foreach ($permissions as $permission) {
                $this->create($permission);
            }
            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}