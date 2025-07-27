<?php
namespace YFEvents\Modules\YFAuth\Models;

use PDO;

class SessionModel extends BaseModel {
    protected $table = 'yfa_auth_sessions';
    protected $fillable = ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity', 'expires_at'];
    
    /**
     * Create session
     */
    public function createSession($userId, $ipAddress = null, $userAgent = null, $expiresIn = 7200) {
        $sessionId = bin2hex(random_bytes(64));
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        
        $data = [
            'id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'payload' => json_encode([]),
            'last_activity' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt
        ];
        
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(", ", $columns) . ") 
                VALUES (" . implode(", ", $placeholders) . ")";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        $stmt->execute();
        
        return $sessionId;
    }
    
    /**
     * Get session by ID
     */
    public function getSession($sessionId) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? AND expires_at > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update session activity
     */
    public function updateActivity($sessionId, $payload = null) {
        $data = ['last_activity' => date('Y-m-d H:i:s')];
        
        if ($payload !== null) {
            $data['payload'] = json_encode($payload);
        }
        
        $setClauses = [];
        foreach (array_keys($data) as $field) {
            $setClauses[] = "$field = :$field";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(", ", $setClauses) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(":id", $sessionId);
        
        return $stmt->execute();
    }
    
    /**
     * Delete session
     */
    public function deleteSession($sessionId) {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$sessionId]);
    }
    
    /**
     * Delete user sessions
     */
    public function deleteUserSessions($userId, $exceptSessionId = null) {
        $sql = "DELETE FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];
        
        if ($exceptSessionId) {
            $sql .= " AND id != ?";
            $params[] = $exceptSessionId;
        }
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Get user sessions
     */
    public function getUserSessions($userId) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = ? AND expires_at > NOW() 
                ORDER BY last_activity DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpired() {
        $sql = "DELETE FROM {$this->table} WHERE expires_at < NOW()";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute();
    }
    
    /**
     * Get active session count
     */
    public function getActiveCount() {
        $sql = "SELECT COUNT(DISTINCT user_id) FROM {$this->table} WHERE expires_at > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}