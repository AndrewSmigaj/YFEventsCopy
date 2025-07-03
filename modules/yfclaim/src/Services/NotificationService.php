<?php
namespace YFEvents\Modules\YFClaim\Services;

use PDO;
use YFEvents\Modules\YFClaim\Models\NotificationModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;

class NotificationService {
    private $db;
    private $notificationModel;
    private $offerModel;
    private $itemModel;
    private $saleModel;
    
    public function __construct(PDO $db) {
        $this->db = $db;
        $this->notificationModel = new NotificationModel($db);
        $this->offerModel = new OfferModel($db);
        $this->itemModel = new ItemModel($db);
        $this->saleModel = new SaleModel($db);
    }
    
    /**
     * Create notification for new offer
     */
    public function createNewOfferNotification($offerId) {
        try {
            // Get offer details
            $offer = $this->offerModel->find($offerId);
            if (!$offer) {
                return false;
            }
            
            // Get item details
            $item = $this->itemModel->find($offer['item_id']);
            if (!$item) {
                return false;
            }
            
            // Get sale details
            $sale = $this->saleModel->find($item['sale_id']);
            if (!$sale) {
                return false;
            }
            
            // Create notification
            $data = [
                'seller_id' => $sale['seller_id'],
                'sale_id' => $sale['id'],
                'type' => 'new_offer',
                'title' => 'New offer on ' . $item['title'],
                'message' => sprintf(
                    'You received a new offer of $%.2f on "%s" (Item #%s)',
                    $offer['offer_amount'],
                    $item['title'],
                    $item['item_number'] ?? $item['id']
                ),
                'is_read' => 0
            ];
            
            return $this->notificationModel->create($data);
            
        } catch (\Exception $e) {
            // Log error but don't throw - notifications should not break offer flow
            error_log('Failed to create offer notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification for accepted offer
     */
    public function createOfferAcceptedNotification($offerId) {
        try {
            // Get offer details
            $offer = $this->offerModel->find($offerId);
            if (!$offer) {
                return false;
            }
            
            // Get item details
            $item = $this->itemModel->find($offer['item_id']);
            if (!$item) {
                return false;
            }
            
            // Get sale details
            $sale = $this->saleModel->find($item['sale_id']);
            if (!$sale) {
                return false;
            }
            
            // This would create a notification for the buyer
            // For now, we're focusing on seller notifications
            // Buyer notifications would need a different table structure
            
            return true;
            
        } catch (\Exception $e) {
            error_log('Failed to create accepted offer notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification for sale ending soon
     */
    public function createSaleEndingNotification($saleId) {
        try {
            $sale = $this->saleModel->find($saleId);
            if (!$sale) {
                return false;
            }
            
            // Get offer count for this sale
            $sql = "SELECT COUNT(DISTINCT o.id) as offer_count 
                    FROM yfc_offers o 
                    JOIN yfc_items i ON o.item_id = i.id 
                    WHERE i.sale_id = ? AND o.status = 'active'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$saleId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $offerCount = $result['offer_count'] ?? 0;
            
            $data = [
                'seller_id' => $sale['seller_id'],
                'sale_id' => $sale['id'],
                'type' => 'sale_ending',
                'title' => 'Sale ending soon',
                'message' => sprintf(
                    'Your sale "%s" ends in less than 24 hours. You have %d active offers to review.',
                    $sale['title'],
                    $offerCount
                ),
                'is_read' => 0
            ];
            
            return $this->notificationModel->create($data);
            
        } catch (\Exception $e) {
            error_log('Failed to create sale ending notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId) {
        return $this->notificationModel->markAsRead($notificationId);
    }
    
    /**
     * Get unread notifications for seller
     */
    public function getUnreadForSeller($sellerId, $limit = 10) {
        return $this->notificationModel->getUnreadBySeller($sellerId, $limit);
    }
    
    /**
     * Get unread count for seller
     */
    public function getUnreadCount($sellerId) {
        return $this->notificationModel->getUnreadCount($sellerId);
    }
}