<?php
namespace YFEvents\Modules\YFClaim\Models;

use PDO;

class SellerModel extends BaseModel {
    protected $table = 'yfc_sellers';
    protected $fillable = [
        'company_name', 'contact_name', 'email', 'phone', 
        'password', 'password_hash', 'username', 'website', 'address', 'city', 'state', 'zip',
        'latitude', 'longitude', 'status', 'email_verified', 'created_at', 'updated_at', 'last_login'
    ];
    
    /**
     * Get seller by email
     */
    public function findByEmail($email) {
        return $this->findBy('email', $email);
    }
    
    /**
     * Get seller by username
     */
    public function findByUsername($username) {
        return $this->findBy('username', $username);
    }
    
    /**
     * Get all active sellers
     */
    public function getActive() {
        return $this->all(['status' => 'active'], 'company_name ASC');
    }
    
    /**
     * Get seller's sales
     */
    public function getSales($sellerId, $status = null) {
        $sql = "SELECT * FROM yfc_sales WHERE seller_id = ?";
        $params = [$sellerId];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get seller statistics
     */
    public function getStats($sellerId) {
        $stats = [];
        
        // Total sales
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM yfc_sales WHERE seller_id = ?");
        $stmt->execute([$sellerId]);
        $stats['total_sales'] = $stmt->fetchColumn();
        
        // Active sales
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM yfc_sales WHERE seller_id = ? AND status = 'active'");
        $stmt->execute([$sellerId]);
        $stats['active_sales'] = $stmt->fetchColumn();
        
        // Total items
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM yfc_items i 
            JOIN yfc_sales s ON i.sale_id = s.id 
            WHERE s.seller_id = ?
        ");
        $stmt->execute([$sellerId]);
        $stats['total_items'] = $stmt->fetchColumn();
        
        // Total offers
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM yfc_offers o
            JOIN yfc_items i ON o.item_id = i.id
            JOIN yfc_sales s ON i.sale_id = s.id
            WHERE s.seller_id = ?
        ");
        $stmt->execute([$sellerId]);
        $stats['total_offers'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Authenticate seller
     */
    public function authenticate($emailOrUsername, $password) {
        // Try to find by email first, then by username
        $seller = $this->findByEmail($emailOrUsername);
        if (!$seller) {
            $seller = $this->findByUsername($emailOrUsername);
        }
        
        if (!$seller || $seller['status'] !== 'active') {
            return false;
        }
        
        if (password_verify($password, $seller['password_hash'])) {
            // Update last login
            $this->update($seller['id'], ['last_login' => date('Y-m-d H:i:s')]);
            return $seller;
        }
        
        return false;
    }
    
    /**
     * Create new seller with hashed password
     */
    public function createSeller($data) {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        
        return $this->create($data);
    }
    
    /**
     * Get all sellers with pagination
     */
    public function getAllSellers($limit = 50, $offset = 0, $orderBy = 'company_name ASC') {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update seller (wrapper for consistency)
     */
    public function updateSeller($id, $data) {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        
        return $this->update($id, $data);
    }
    
    /**
     * Get seller by ID (wrapper for consistency)
     */
    public function getSellerById($id) {
        return $this->find($id);
    }
    
    /**
     * Delete seller (wrapper for consistency)
     */
    public function deleteSeller($id) {
        return $this->delete($id);
    }
    
}