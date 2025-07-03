<?php
namespace YFEvents\Modules\YFClaim\Models;

use PDO;
use Exception;
use YFEvents\Domain\Common\BaseModel;

class OfferModel extends BaseModel {
    protected $table = 'yfc_offers';
    protected $fillable = [
        'item_id', 'buyer_id', 'offer_amount', 'max_offer', 
        'status', 'seller_notes', 'ip_address', 'user_agent'
    ];
    
    /**
     * Get offers for an item
     */
    public function getByItem($itemId, $status = null) {
        $sql = "
            SELECT o.*, 
                   b.name as buyer_name, 
                   b.email as buyer_email,
                   b.phone as buyer_phone
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
     * Get offers by buyer
     */
    public function getByBuyer($buyerId, $status = null) {
        $sql = "
            SELECT o.*, 
                   i.title as item_title,
                   i.item_number,
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
     * Create new offer with history tracking
     */
    public function createOffer($data) {
        $this->beginTransaction();
        
        try {
            // Create the offer
            $offerId = $this->create($data);
            
            // Add to history
            $this->addToHistory($offerId, $data['item_id'], $data['buyer_id'], $data['offer_amount'], 'placed');
            
            $this->commit();
            
            // Create notification for new offer (non-blocking)
            try {
                $notificationService = new \YFEvents\Modules\YFClaim\Services\NotificationService($this->db);
                $notificationService->createNewOfferNotification($offerId);
            } catch (\Exception $e) {
                // Log but don't fail the offer
                error_log('Failed to create offer notification: ' . $e->getMessage());
            }
            
            return $offerId;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Update offer amount
     */
    public function updateAmount($offerId, $newAmount) {
        $offer = $this->find($offerId);
        if (!$offer) {
            throw new Exception('Offer not found');
        }
        
        $this->beginTransaction();
        
        try {
            // Update offer
            $this->update($offerId, [
                'offer_amount' => $newAmount,
                'status' => 'active'
            ]);
            
            // Add to history
            $this->addToHistory($offerId, $offer['item_id'], $offer['buyer_id'], $newAmount, 'increased');
            
            $this->commit();
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Accept offer (mark as winning)
     */
    public function acceptOffer($offerId, $sellerNotes = null) {
        $offer = $this->find($offerId);
        if (!$offer) {
            throw new Exception('Offer not found');
        }
        
        $this->beginTransaction();
        
        try {
            // Update this offer as winning
            $updateData = ['status' => 'winning'];
            if ($sellerNotes) {
                $updateData['seller_notes'] = $sellerNotes;
            }
            $this->update($offerId, $updateData);
            
            // Mark other offers on same item as outbid
            $stmt = $this->db->prepare("
                UPDATE yfc_offers 
                SET status = 'outbid' 
                WHERE item_id = ? AND id != ? AND status = 'active'
            ");
            $stmt->execute([$offer['item_id'], $offerId]);
            
            // Update item as claimed
            $stmt = $this->db->prepare("
                UPDATE yfc_items 
                SET status = 'claimed', winning_offer_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$offerId, $offer['item_id']]);
            
            $this->commit();
            
            // Create notification for accepted offer (non-blocking)
            try {
                $notificationService = new \YFEvents\Modules\YFClaim\Services\NotificationService($this->db);
                $notificationService->createOfferAcceptedNotification($offerId);
            } catch (\Exception $e) {
                // Log but don't fail the acceptance
                error_log('Failed to create accepted offer notification: ' . $e->getMessage());
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Reject offer
     */
    public function rejectOffer($offerId, $sellerNotes = null) {
        $updateData = ['status' => 'rejected'];
        if ($sellerNotes) {
            $updateData['seller_notes'] = $sellerNotes;
        }
        
        return $this->update($offerId, $updateData);
    }
    
    /**
     * Get highest offer for item
     */
    public function getHighest($itemId, $status = 'active') {
        $sql = "
            SELECT o.*, b.name as buyer_name
            FROM yfc_offers o
            JOIN yfc_buyers b ON o.buyer_id = b.id
            WHERE o.item_id = ? AND o.status = ?
            ORDER BY o.offer_amount DESC
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$itemId, $status]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get offer statistics for item
     */
    public function getItemStats($itemId) {
        $sql = "
            SELECT 
                COUNT(*) as total_offers,
                MIN(offer_amount) as min_offer,
                MAX(offer_amount) as max_offer,
                AVG(offer_amount) as avg_offer,
                COUNT(DISTINCT buyer_id) as unique_buyers
            FROM yfc_offers
            WHERE item_id = ? AND status = 'active'
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if buyer has already made offer on item
     */
    public function hasOfferFromBuyer($itemId, $buyerId) {
        return $this->exists([
            'item_id' => $itemId,
            'buyer_id' => $buyerId,
            'status' => 'active'
        ]);
    }
    
    /**
     * Get buyer's offer on item
     */
    public function getBuyerOffer($itemId, $buyerId) {
        $sql = "
            SELECT * FROM yfc_offers 
            WHERE item_id = ? AND buyer_id = ? AND status = 'active'
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$itemId, $buyerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Add offer to history
     */
    private function addToHistory($offerId, $itemId, $buyerId, $amount, $action) {
        $stmt = $this->db->prepare("
            INSERT INTO yfc_offer_history 
            (offer_id, item_id, buyer_id, offer_amount, action)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$offerId, $itemId, $buyerId, $amount, $action]);
    }
    
    /**
     * Get offer history for item
     */
    public function getHistory($itemId) {
        $sql = "
            SELECT h.*, b.name as buyer_name
            FROM yfc_offer_history h
            JOIN yfc_buyers b ON h.buyer_id = b.id
            WHERE h.item_id = ?
            ORDER BY h.created_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Expire old offers (for scheduled cleanup)
     */
    public function expireOldOffers($hoursOld = 24) {
        $stmt = $this->db->prepare("
            UPDATE yfc_offers 
            SET status = 'expired' 
            WHERE status = 'active' 
            AND TIMESTAMPDIFF(HOUR, created_at, NOW()) > ?
        ");
        
        return $stmt->execute([$hoursOld]);
    }
    
    /**
     * Get all offers with pagination
     */
    public function getAllOffers($limit = 50, $offset = 0, $orderBy = 'created_at DESC') {
        $sql = "
            SELECT o.*, 
                   b.name as buyer_name, 
                   b.email as buyer_email,
                   i.title as item_title,
                   i.item_number,
                   s.title as sale_title
            FROM {$this->table} o
            LEFT JOIN yfc_buyers b ON o.buyer_id = b.id
            LEFT JOIN yfc_items i ON o.item_id = i.id
            LEFT JOIN yfc_sales s ON i.sale_id = s.id
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
     * Update offer (wrapper for consistency)
     */
    public function updateOffer($id, $data) {
        return $this->update($id, $data);
    }
    
    /**
     * Get offer by ID (wrapper for consistency)
     */
    public function getOfferById($id) {
        return $this->find($id);
    }
    
    /**
     * Delete offer (wrapper for consistency)
     */
    public function deleteOffer($id) {
        return $this->delete($id);
    }
    
    /**
     * Get offers by item ID (wrapper for consistency)
     */
    public function getOffersByItem($itemId, $status = null) {
        return $this->getByItem($itemId, $status);
    }
}