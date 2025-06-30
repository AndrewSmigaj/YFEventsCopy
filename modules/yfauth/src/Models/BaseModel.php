<?php
namespace YFEvents\Modules\YFAuth\Models;

use PDO;
use Exception;

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $fillable = [];
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Find record by ID
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find records by conditions
     */
    public function where($conditions = [], $orderBy = null, $limit = null) {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                if (is_null($value)) {
                    $whereClauses[] = "$field IS NULL";
                } else {
                    $whereClauses[] = "$field = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find single record by conditions
     */
    public function findWhere($conditions = []) {
        $results = $this->where($conditions, null, 1);
        return $results ? $results[0] : null;
    }
    
    /**
     * Get all records
     */
    public function all($orderBy = 'id DESC') {
        return $this->where([], $orderBy);
    }
    
    /**
     * Create new record
     */
    public function create($data) {
        $fields = array_intersect_key($data, array_flip($this->fillable));
        
        $columns = array_keys($fields);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(", ", $columns) . ") 
                VALUES (" . implode(", ", $placeholders) . ")";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($fields as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        $stmt->execute();
        return $this->db->lastInsertId();
    }
    
    /**
     * Update record
     */
    public function update($id, $data) {
        $fields = array_intersect_key($data, array_flip($this->fillable));
        
        $setClauses = array_map(function($col) { return "$col = :$col"; }, array_keys($fields));
        
        $sql = "UPDATE {$this->table} SET " . implode(", ", $setClauses) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($fields as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(":id", $id);
        
        return $stmt->execute();
    }
    
    /**
     * Delete record
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Check if record exists
     */
    public function exists($conditions) {
        $result = $this->findWhere($conditions);
        return !empty($result);
    }
    
    /**
     * Count records
     */
    public function count($conditions = []) {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                if (is_null($value)) {
                    $whereClauses[] = "$field IS NULL";
                } else {
                    $whereClauses[] = "$field = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->db->rollBack();
    }
    
    /**
     * Get PDO instance
     */
    public function getDb() {
        return $this->db;
    }
}