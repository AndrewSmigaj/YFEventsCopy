<?php
namespace YFEvents\Modules\YFClaim\Models;

use PDO;

class SaleModel extends BaseModel {
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_DRAFT = 'draft';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';
    
    protected $table = 'yfc_sales';
    protected $fillable = [
        'seller_id', 'title', 'description', 'address', 'city', 'state', 'zip',
        'latitude', 'longitude', 'start_date', 'end_date',
        'claim_start', 'claim_end', 'pickup_start', 'pickup_end',
        'qr_code', 'access_code', 'status', 'featured'
    ];
    
    /**
     * Get active sales
     */
    public function getActive() {
        return $this->all(['status' => self::STATUS_ACTIVE], 'claim_start DESC');
    }
    
    /**
     * Get sales by seller
     */
    public function getBySeller($sellerId) {
        return $this->all(['seller_id' => $sellerId], 'created_at DESC');
    }
    
    /**
     * Get sale with seller info
     */
    public function getWithSeller($saleId) {
        $sql = "
            SELECT s.*, 
                   sel.company_name, sel.contact_name, sel.phone as seller_phone
            FROM yfc_sales s
            JOIN yfc_sellers sel ON s.seller_id = sel.id
            WHERE s.id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$saleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get sales by access code
     */
    public function findByAccessCode($accessCode) {
        return $this->findBy('access_code', $accessCode);
    }
    
    /**
     * Get sales by QR code
     */
    public function findByQrCode($qrCode) {
        return $this->findBy('qr_code', $qrCode);
    }
    
    /**
     * Get sale items
     */
    public function getItems($saleId, $status = null) {
        $sql = "SELECT * FROM yfc_items WHERE sale_id = ?";
        $params = [$saleId];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY sort_order ASC, item_number ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get sale statistics
     */
    public function getStats($saleId) {
        $stats = [];
        
        // Total items
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM yfc_items WHERE sale_id = ?");
        $stmt->execute([$saleId]);
        $stats['total_items'] = $stmt->fetchColumn();
        
        // Items with offers
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT item_id) FROM yfc_offers o
            JOIN yfc_items i ON o.item_id = i.id
            WHERE i.sale_id = ?
        ");
        $stmt->execute([$saleId]);
        $stats['items_with_offers'] = $stmt->fetchColumn();
        
        // Total offers
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM yfc_offers o
            JOIN yfc_items i ON o.item_id = i.id
            WHERE i.sale_id = ?
        ");
        $stmt->execute([$saleId]);
        $stats['total_offers'] = $stmt->fetchColumn();
        
        // Claimed items
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM yfc_items WHERE sale_id = ? AND status = 'claimed'");
        $stmt->execute([$saleId]);
        $stats['claimed_items'] = $stmt->fetchColumn();
        
        // Unique buyers (through offers)
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT o.buyer_id) FROM yfc_offers o
            JOIN yfc_items i ON o.item_id = i.id
            WHERE i.sale_id = ?
        ");
        $stmt->execute([$saleId]);
        $stats['unique_buyers'] = $stmt->fetchColumn();
        
        return $stats;
    }
    
    /**
     * Generate unique access code
     */
    public function generateAccessCode() {
        do {
            $code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE access_code = ?");
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Generate unique QR code
     */
    public function generateQrCode() {
        do {
            $code = 'QR' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE qr_code = ?");
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Check if sale is currently active
     */
    public function isActive($saleId) {
        $sale = $this->find($saleId);
        if (!$sale || $sale['status'] !== 'active') {
            return false;
        }
        
        $now = date('Y-m-d H:i:s');
        return $now >= $sale['claim_start'] && $now <= $sale['claim_end'];
    }
    
    /**
     * Get upcoming sales (not yet started)
     */
    public function getUpcoming() {
        try {
            $now = date('Y-m-d H:i:s');
            
            $sql = "
                SELECT s.*, sel.company_name
                FROM yfc_sales s
                JOIN yfc_sellers sel ON s.seller_id = sel.id
                WHERE s.status = ? 
                AND s.claim_start > ?
                ORDER BY s.claim_start ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([self::STATUS_ACTIVE, $now]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error in getUpcoming: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get current sales (claim period active)
     */
    public function getCurrent() {
        try {
            $now = date('Y-m-d H:i:s');
            
            $sql = "
                SELECT s.*, sel.company_name
                FROM yfc_sales s
                JOIN yfc_sellers sel ON s.seller_id = sel.id
                WHERE s.status = ? 
                AND s.claim_start <= ?
                AND s.claim_end >= ?
                ORDER BY s.claim_end ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([self::STATUS_ACTIVE, $now, $now]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error in getCurrent: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create new sale with generated codes
     */
    public function createSale($data) {
        // Generate codes if not provided
        if (!isset($data['access_code'])) {
            $data['access_code'] = $this->generateAccessCode();
        }
        if (!isset($data['qr_code'])) {
            $data['qr_code'] = $this->generateQrCode();
        }
        
        return $this->create($data);
    }
    
    /**
     * Get all sales with pagination
     */
    public function getAllSales($limit = 50, $offset = 0, $orderBy = 'created_at DESC') {
        $sql = "
            SELECT s.*, sel.company_name, sel.contact_name
            FROM {$this->table} s
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
     * Update sale (wrapper for consistency)
     */
    public function updateSale($id, $data) {
        return $this->update($id, $data);
    }
    
    /**
     * Get sale by ID (wrapper for consistency)
     */
    public function getSaleById($id) {
        return $this->find($id);
    }
    
    /**
     * Delete sale (wrapper for consistency)
     */
    public function deleteSale($id) {
        return $this->delete($id);
    }
    
    /**
     * Get sales by seller ID (wrapper for consistency)
     */
    public function getSalesBySeller($sellerId) {
        return $this->getBySeller($sellerId);
    }
    
}