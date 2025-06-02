<?php
namespace YFEvents\Modules\YFClaim\Models;

use PDO;

class ItemModel extends BaseModel {
    protected $table = 'yfc_items';
    protected $fillable = [
        'sale_id', 'title', 'description', 'starting_price', 'offer_increment',
        'buy_now_price', 'category', 'condition_rating', 'dimensions', 'weight',
        'item_number', 'sort_order', 'status', 'winning_offer_id'
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
}