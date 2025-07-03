<?php

namespace YFEvents\Domain\Common;

use PDO;

abstract class BaseModel
{
    protected $db;
    protected $table;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Get database connection
     */
    public function getDb()
    {
        return $this->db;
    }
    
    /**
     * Get table name
     */
    public function getTable()
    {
        return $this->table;
    }
    
    /**
     * Execute a query and return results
     */
    protected function query($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Get single record by ID
     */
    public function find($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->query($sql, ['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all records
     */
    public function findAll($orderBy = 'id', $order = 'ASC')
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$order}";
        $stmt = $this->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Count records
     */
    public function count($conditions = [])
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }
    
    /**
     * Delete record by ID
     */
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->query($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Check if record exists
     */
    public function exists($id)
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE id = :id";
        $stmt = $this->query($sql, ['id' => $id]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired($data, $required)
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            throw new \InvalidArgumentException('Missing required fields: ' . implode(', ', $missing));
        }
    }
    
    /**
     * Sanitize input data
     */
    protected function sanitize($data)
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    
    /**
     * Generate slug from string
     */
    protected function generateSlug($string)
    {
        $slug = strtolower($string);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
    
    /**
     * Format date for database
     */
    protected function formatDate($date)
    {
        if (empty($date)) {
            return null;
        }
        
        if ($date instanceof \DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
        
        return date('Y-m-d H:i:s', strtotime($date));
    }
    
    /**
     * Begin database transaction
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit database transaction
     */
    public function commit()
    {
        return $this->db->commit();
    }
    
    /**
     * Rollback database transaction
     */
    public function rollback()
    {
        return $this->db->rollback();
    }
    
    /**
     * Find record by specific column
     */
    public function findBy($column, $value)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = :value";
        $stmt = $this->query($sql, ['value' => $value]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all records with conditions
     */
    public function all($conditions = [], $orderBy = 'id ASC')
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $sql .= " ORDER BY {$orderBy}";
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new record
     */
    public function create($data)
    {
        if (!isset($this->fillable)) {
            throw new \Exception('Fillable array not defined in model');
        }
        
        // Filter data to only fillable fields
        $filteredData = array_intersect_key($data, array_flip($this->fillable));
        
        $columns = array_keys($filteredData);
        $placeholders = array_map(function($col) { return ":{$col}"; }, $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(", ", $columns) . ") 
                VALUES (" . implode(", ", $placeholders) . ")";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($filteredData as $column => $value) {
            $stmt->bindValue(":{$column}", $value);
        }
        
        $stmt->execute();
        return $this->db->lastInsertId();
    }
    
    /**
     * Update record
     */
    public function update($id, $data)
    {
        if (!isset($this->fillable)) {
            throw new \Exception('Fillable array not defined in model');
        }
        
        // Filter data to only fillable fields
        $filteredData = array_intersect_key($data, array_flip($this->fillable));
        
        $sets = [];
        foreach ($filteredData as $column => $value) {
            $sets[] = "{$column} = :{$column}";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(", ", $sets) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        
        foreach ($filteredData as $column => $value) {
            $stmt->bindValue(":{$column}", $value);
        }
        
        return $stmt->execute();
    }
}