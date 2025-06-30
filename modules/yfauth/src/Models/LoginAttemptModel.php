<?php
namespace YFEvents\Modules\YFAuth\Models;

use PDO;

class LoginAttemptModel extends BaseModel {
    protected $table = 'yfa_login_attempts';
    protected $fillable = ['email', 'ip_address', 'attempted_at', 'success'];
    
    /**
     * Record login attempt
     */
    public function recordAttempt($email, $ipAddress, $success = false) {
        return $this->create([
            'email' => $email,
            'ip_address' => $ipAddress,
            'success' => $success
        ]);
    }
    
    /**
     * Get recent attempts
     */
    public function getRecentAttempts($email, $ipAddress, $timeframe = 900) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE (email = ? OR ip_address = ?) 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
                ORDER BY attempted_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email, $ipAddress, $timeframe]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get failed attempts count
     */
    public function getFailedAttemptsCount($email, $ipAddress, $timeframe = 900) {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE (email = ? OR ip_address = ?) 
                AND success = 0
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email, $ipAddress, $timeframe]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Clean old attempts
     */
    public function cleanOldAttempts($olderThan = 86400) { // 24 hours
        $sql = "DELETE FROM {$this->table} 
                WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$olderThan]);
    }
}