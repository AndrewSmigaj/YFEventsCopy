<?php
namespace YFEvents\Modules\YFClaim\Models;

use PDO;
use Exception;

class BuyerModel extends BaseModel {
    protected $table = 'yfc_buyers';
    protected $fillable = [
        'sale_id', 'name', 'email', 'phone', 'auth_method',
        'auth_code', 'auth_code_expires', 'auth_verified',
        'session_token', 'session_expires'
    ];
    
    /**
     * Create buyer with authentication
     */
    public function createWithAuth($saleId, $name, $contact, $authMethod = 'email') {
        // Generate auth code
        $authCode = sprintf('%06d', mt_rand(100000, 999999));
        $authExpires = date('Y-m-d H:i:s', time() + 900); // 15 minutes
        
        $data = [
            'sale_id' => $saleId,
            'name' => $name,
            'auth_method' => $authMethod,
            'auth_code' => $authCode,
            'auth_code_expires' => $authExpires,
            'auth_verified' => false
        ];
        
        if ($authMethod === 'email') {
            $data['email'] = $contact;
        } else {
            $data['phone'] = $contact;
        }
        
        $buyerId = $this->create($data);
        
        return [
            'buyer_id' => $buyerId,
            'auth_code' => $authCode,
            'expires_at' => $authExpires
        ];
    }
    
    /**
     * Verify authentication code
     */
    public function verifyAuthCode($buyerId, $code) {
        $buyer = $this->find($buyerId);
        
        if (!$buyer) {
            return false;
        }
        
        // Check if code matches and hasn't expired
        if ($buyer['auth_code'] === $code && 
            $buyer['auth_code_expires'] && 
            strtotime($buyer['auth_code_expires']) > time()) {
            
            // Generate session token
            $sessionToken = bin2hex(random_bytes(32));
            $sessionExpires = date('Y-m-d H:i:s', time() + 3600 * 4); // 4 hours
            
            // Update buyer record
            $this->update($buyerId, [
                'auth_verified' => true,
                'session_token' => $sessionToken,
                'session_expires' => $sessionExpires
            ]);
            
            return [
                'success' => true,
                'session_token' => $sessionToken,
                'buyer' => $this->find($buyerId)
            ];
        }
        
        return false;
    }
    
    /**
     * Validate session token
     */
    public function validateSession($sessionToken) {
        $sql = "
            SELECT * FROM yfc_buyers 
            WHERE session_token = ? 
            AND session_expires > NOW()
            AND auth_verified = 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sessionToken]);
        $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($buyer) {
            // Update last activity
            $this->update($buyer['id'], [
                'last_activity' => date('Y-m-d H:i:s')
            ]);
        }
        
        return $buyer;
    }
    
    /**
     * Find buyer by contact info for sale
     */
    public function findByContact($saleId, $contact, $authMethod = 'email') {
        $column = $authMethod === 'email' ? 'email' : 'phone';
        
        $sql = "SELECT * FROM yfc_buyers WHERE sale_id = ? AND {$column} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$saleId, $contact]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get buyer's offers
     */
    public function getOffers($buyerId, $status = null) {
        $sql = "
            SELECT o.*, 
                   i.title as item_title,
                   i.item_number,
                   i.status as item_status,
                   s.title as sale_title
            FROM yfc_offers o
            JOIN yfc_items i ON o.item_id = i.id
            JOIN yfc_sales s ON i.sale_id = s.id
            WHERE o.buyer_id = ?
        ";
        $params = [$buyerId];
        
        if ($status) {
            $sql .= " AND o.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get buyer statistics
     */
    public function getStats($buyerId) {
        $stats = [];
        
        // Total offers
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM yfc_offers WHERE buyer_id = ?");
        $stmt->execute([$buyerId]);
        $stats['total_offers'] = $stmt->fetchColumn();
        
        // Winning offers
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM yfc_offers WHERE buyer_id = ? AND status = 'winning'");
        $stmt->execute([$buyerId]);
        $stats['winning_offers'] = $stmt->fetchColumn();
        
        // Active offers
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM yfc_offers WHERE buyer_id = ? AND status = 'active'");
        $stmt->execute([$buyerId]);
        $stats['active_offers'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Resend authentication code
     */
    public function resendAuthCode($buyerId) {
        $buyer = $this->find($buyerId);
        if (!$buyer) {
            return false;
        }
        
        // Generate new code
        $authCode = sprintf('%06d', mt_rand(100000, 999999));
        $authExpires = date('Y-m-d H:i:s', time() + 900); // 15 minutes
        
        $this->update($buyerId, [
            'auth_code' => $authCode,
            'auth_code_expires' => $authExpires
        ]);
        
        return [
            'auth_code' => $authCode,
            'expires_at' => $authExpires,
            'contact' => $buyer['auth_method'] === 'email' ? $buyer['email'] : $buyer['phone']
        ];
    }
    
    /**
     * Logout buyer (invalidate session)
     */
    public function logout($buyerId) {
        return $this->update($buyerId, [
            'session_token' => null,
            'session_expires' => null
        ]);
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions() {
        $stmt = $this->db->prepare("
            UPDATE yfc_buyers 
            SET session_token = NULL, session_expires = NULL 
            WHERE session_expires < NOW()
        ");
        
        return $stmt->execute();
    }
    
    /**
     * Clean expired auth codes
     */
    public function cleanExpiredAuthCodes() {
        $stmt = $this->db->prepare("
            DELETE FROM yfc_buyers 
            WHERE auth_verified = 0 
            AND auth_code_expires < NOW()
            AND auth_code_expires < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        
        return $stmt->execute();
    }
    
    /**
     * Get buyers for a sale
     */
    public function getBySale($saleId, $verified = true) {
        $conditions = ['sale_id' => $saleId];
        if ($verified) {
            $conditions['auth_verified'] = 1;
        }
        
        return $this->all($conditions, 'created_at DESC');
    }
    
    /**
     * Update buyer activity timestamp
     */
    public function updateActivity($buyerId) {
        return $this->update($buyerId, [
            'last_activity' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Create buyer (wrapper for consistency)
     */
    public function createBuyer($data) {
        return $this->create($data);
    }
    
    /**
     * Get all buyers with pagination
     */
    public function getAllBuyers($limit = 50, $offset = 0, $orderBy = 'created_at DESC') {
        $sql = "
            SELECT b.*, s.title as sale_title, sel.company_name
            FROM {$this->table} b
            LEFT JOIN yfc_sales s ON b.sale_id = s.id
            LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update buyer (wrapper for consistency)
     */
    public function updateBuyer($id, $data) {
        return $this->update($id, $data);
    }
    
    /**
     * Get buyer by ID (wrapper for consistency)
     */
    public function getBuyerById($id) {
        return $this->find($id);
    }
    
    /**
     * Delete buyer and associated offers
     */
    public function deleteBuyer($id) {
        $this->beginTransaction();
        
        try {
            // Delete offers
            $stmt = $this->db->prepare("DELETE FROM yfc_offers WHERE buyer_id = ?");
            $stmt->execute([$id]);
            
            // Delete offer history
            $stmt = $this->db->prepare("DELETE FROM yfc_offer_history WHERE buyer_id = ?");
            $stmt->execute([$id]);
            
            // Delete buyer
            $this->delete($id);
            
            $this->commit();
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}