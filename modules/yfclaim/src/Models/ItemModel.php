<?php
namespace YFEvents\Modules\YFClaim\Models;

use PDO;
use Exception;

class ItemModel extends BaseModel {
    protected $table = 'yfc_items';
    protected $fillable = [
        'sale_id', 'title', 'description', 'starting_price', 'category_id',
        'current_high_offer', 'offer_count', 'primary_image', 'images',
        'condition_notes', 'measurements', 'sort_order', 'status', 'featured', 'qr_code'
    ];
    
    /**
     * Get items for a sale
     */
    public function getBySale($saleId, $status = null) {
        $conditions = ['sale_id' => $saleId];
        if ($status) {
            $conditions['status'] = $status;
        }
        
        return $this->all($conditions, 'sort_order ASC, item_number ASC');
    }
    
    /**
     * Get item with images
     */
    public function getWithImages($itemId) {
        $item = $this->find($itemId);
        if (!$item) {
            return null;
        }
        
        // Get images
        $stmt = $this->db->prepare("
            SELECT * FROM yfc_item_images 
            WHERE item_id = ? 
            ORDER BY is_primary DESC, sort_order ASC
        ");
        $stmt->execute([$itemId]);
        $item['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $item;
    }
    
    /**
     * Get items with primary images
     */
    public function getWithPrimaryImages($saleId) {
        $sql = "
            SELECT i.*,
                   img.filename as primary_image,
                   img.original_filename as primary_image_original
            FROM yfc_items i
            LEFT JOIN yfc_item_images img ON i.id = img.item_id AND img.is_primary = 1
            WHERE i.sale_id = ?
            ORDER BY i.sort_order ASC, i.item_number ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$saleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get item offers
     */
    public function getOffers($itemId, $status = null) {
        $sql = "
            SELECT o.*, b.name as buyer_name, b.email as buyer_email
            FROM yfc_offers o
            JOIN yfc_buyers b ON o.buyer_id = b.id
            WHERE o.item_id = ?
        ";
        $params = [$itemId];
        
        if ($status) {
            $sql .= " AND o.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY o.offer_amount DESC, o.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get highest offer for an item
     */
    public function getHighestOffer($itemId) {
        $sql = "
            SELECT o.*, b.name as buyer_name
            FROM yfc_offers o
            JOIN yfc_buyers b ON o.buyer_id = b.id
            WHERE o.item_id = ? AND o.status = 'active'
            ORDER BY o.offer_amount DESC
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get offer count for item
     */
    public function getOfferCount($itemId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM yfc_offers WHERE item_id = ? AND status = 'active'");
        $stmt->execute([$itemId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get price range for display
     */
    public function getPriceRange($itemId) {
        $sql = "
            SELECT 
                MIN(offer_amount) as min_offer,
                MAX(offer_amount) as max_offer,
                COUNT(*) as offer_count
            FROM yfc_offers 
            WHERE item_id = ? AND status = 'active'
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$itemId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['offer_count'] == 0) {
            return null;
        }
        
        return $result;
    }
    
    /**
     * Mark item as claimed
     */
    public function markClaimed($itemId, $winningOfferId) {
        $this->beginTransaction();
        
        try {
            // Update item status
            $this->update($itemId, [
                'status' => 'claimed',
                'winning_offer_id' => $winningOfferId
            ]);
            
            // Update winning offer
            $stmt = $this->db->prepare("UPDATE yfc_offers SET status = 'winning' WHERE id = ?");
            $stmt->execute([$winningOfferId]);
            
            // Mark other offers as outbid
            $stmt = $this->db->prepare("
                UPDATE yfc_offers 
                SET status = 'outbid' 
                WHERE item_id = ? AND id != ? AND status = 'active'
            ");
            $stmt->execute([$itemId, $winningOfferId]);
            
            $this->commit();
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Add image to item
     */
    public function addImage($itemId, $filename, $originalFilename, $fileSize, $mimeType, $isPrimary = false) {
        // If this is primary, unset other primary images
        if ($isPrimary) {
            $stmt = $this->db->prepare("UPDATE yfc_item_images SET is_primary = 0 WHERE item_id = ?");
            $stmt->execute([$itemId]);
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO yfc_item_images 
            (item_id, filename, original_filename, file_size, mime_type, is_primary)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$itemId, $filename, $originalFilename, $fileSize, $mimeType, $isPrimary]);
    }
    
    /**
     * Get items by category
     */
    public function getByCategory($category, $saleId = null) {
        $sql = "SELECT * FROM yfc_items WHERE category = ?";
        $params = [$category];
        
        if ($saleId) {
            $sql .= " AND sale_id = ?";
            $params[] = $saleId;
        }
        
        $sql .= " ORDER BY sort_order ASC, item_number ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Search items
     */
    public function search($query, $saleId = null) {
        $sql = "
            SELECT * FROM yfc_items 
            WHERE (title LIKE ? OR description LIKE ? OR item_number LIKE ?)
        ";
        $params = ["%$query%", "%$query%", "%$query%"];
        
        if ($saleId) {
            $sql .= " AND sale_id = ?";
            $params[] = $saleId;
        }
        
        $sql .= " ORDER BY sort_order ASC, item_number ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new item with auto-generated sort order
     */
    public function createItem($data) {
        // Set default sort order if not provided
        if (!isset($data['sort_order'])) {
            $stmt = $this->db->prepare("SELECT MAX(sort_order) FROM yfc_items WHERE sale_id = ?");
            $stmt->execute([$data['sale_id']]);
            $maxOrder = $stmt->fetchColumn();
            $data['sort_order'] = ($maxOrder ?: 0) + 1;
        }
        
        // Generate QR code if not provided
        if (!isset($data['qr_code'])) {
            do {
                $qrCode = 'QRI' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
                // Check if QR code already exists
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE qr_code = ?");
                $stmt->execute([$qrCode]);
                $exists = $stmt->fetchColumn() > 0;
            } while ($exists);
            $data['qr_code'] = $qrCode;
        }
        
        return $this->create($data);
    }
    
    /**
     * Get all items with pagination
     */
    public function getAllItems($limit = 50, $offset = 0, $orderBy = 'created_at DESC') {
        $sql = "
            SELECT i.*, s.title as sale_title, sel.company_name
            FROM {$this->table} i
            LEFT JOIN yfc_sales s ON i.sale_id = s.id
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
     * Update item (wrapper for consistency)
     */
    public function updateItem($id, $data) {
        return $this->update($id, $data);
    }
    
    /**
     * Get item by ID (wrapper for consistency)
     */
    public function getItemById($id) {
        return $this->find($id);
    }
    
    /**
     * Delete item and associated data
     */
    public function deleteItem($id) {
        $this->beginTransaction();
        
        try {
            // Delete images
            $stmt = $this->db->prepare("DELETE FROM yfc_item_images WHERE item_id = ?");
            $stmt->execute([$id]);
            
            // Delete offers
            $stmt = $this->db->prepare("DELETE FROM yfc_offers WHERE item_id = ?");
            $stmt->execute([$id]);
            
            // Delete item
            $this->delete($id);
            
            $this->commit();
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get items by sale ID (wrapper for consistency)
     */
    public function getItemsBySale($saleId, $status = null) {
        return $this->getBySale($saleId, $status);
    }
}