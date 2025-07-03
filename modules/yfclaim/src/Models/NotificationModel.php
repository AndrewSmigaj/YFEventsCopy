<?php
namespace YFEvents\Modules\YFClaim\Models;

use PDO;
use YFEvents\Domain\Common\BaseModel;

class NotificationModel extends BaseModel {
    protected $table = 'yfc_notifications';
    protected $fillable = [
        'seller_id', 'sale_id', 'type', 'title', 'message', 'is_read'
    ];
    
    /**
     * Get unread notifications for a seller
     */
    public function getUnreadBySeller($sellerId, $limit = 10) {
        $sql = "SELECT n.*, s.title as sale_title 
                FROM {$this->table} n
                LEFT JOIN yfc_sales s ON n.sale_id = s.id
                WHERE n.seller_id = ? AND n.is_read = 0
                ORDER BY n.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $sellerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId) {
        return $this->update($notificationId, ['is_read' => 1]);
    }
    
    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead($notificationIds) {
        if (empty($notificationIds)) {
            return 0;
        }
        
        $placeholders = array_fill(0, count($notificationIds), '?');
        $sql = "UPDATE {$this->table} SET is_read = 1 WHERE id IN (" . implode(',', $placeholders) . ")";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($notificationIds);
        
        return $stmt->rowCount();
    }
    
    /**
     * Get unread notification count for seller
     */
    public function getUnreadCount($sellerId) {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE seller_id = ? AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$sellerId]);
        
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get all notifications for seller with pagination
     */
    public function getBySellerWithPagination($sellerId, $limit = 20, $offset = 0) {
        $sql = "SELECT n.*, s.title as sale_title 
                FROM {$this->table} n
                LEFT JOIN yfc_sales s ON n.sale_id = s.id
                WHERE n.seller_id = ?
                ORDER BY n.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(1, $sellerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Delete old notifications
     */
    public function deleteOldNotifications($days = 30) {
        $sql = "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Create notification for new offer
     */
    public function createNewOfferNotification($sellerId, $saleId, $itemTitle, $offerAmount) {
        $data = [
            'seller_id' => $sellerId,
            'sale_id' => $saleId,
            'type' => 'new_offer',
            'title' => 'New offer received',
            'message' => sprintf('You received a new offer of $%.2f on "%s"', $offerAmount, $itemTitle),
            'is_read' => 0
        ];
        
        return $this->create($data);
    }
}