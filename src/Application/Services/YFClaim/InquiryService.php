<?php

declare(strict_types=1);

namespace YFEvents\Application\Services\YFClaim;

use YFEvents\Domain\YFClaim\Entities\Inquiry;
use YFEvents\Domain\YFClaim\Repositories\InquiryRepositoryInterface;

/**
 * Service for managing buyer inquiries
 */
class InquiryService
{
    private InquiryRepositoryInterface $inquiryRepository;
    private \PDO $pdo;
    
    public function __construct(
        InquiryRepositoryInterface $inquiryRepository,
        \PDO $pdo
    ) {
        $this->inquiryRepository = $inquiryRepository;
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new inquiry from contact form submission
     */
    public function createInquiry(array $data): Inquiry
    {
        // Validate required fields
        $required = ['item_id', 'buyer_name', 'buyer_email', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field '$field' is required");
            }
        }
        
        // Get item and seller information
        $itemInfo = $this->getItemInfo((int)$data['item_id']);
        if (!$itemInfo) {
            throw new \InvalidArgumentException('Item not found');
        }
        
        // Build inquiry data
        $inquiryData = [
            'sale_id' => $itemInfo['sale_id'],
            'item_id' => (int)$data['item_id'],
            'seller_user_id' => $itemInfo['seller_user_id'],
            'buyer_name' => trim($data['buyer_name']),
            'buyer_email' => trim($data['buyer_email']),
            'buyer_phone' => !empty($data['buyer_phone']) ? trim($data['buyer_phone']) : null,
            'subject' => !empty($data['subject']) ? trim($data['subject']) : "Inquiry about {$itemInfo['title']}",
            'message' => trim($data['message']),
            'status' => Inquiry::STATUS_NEW,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ];
        
        // Create and save inquiry
        $inquiry = Inquiry::fromArray($inquiryData);
        return $this->inquiryRepository->save($inquiry);
    }
    
    /**
     * Get inquiries for a seller with optional filters
     */
    public function getSellerInquiries(int $sellerId, array $filters = []): array
    {
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
        
        // Remove pagination from filters before passing to repository
        unset($filters['limit'], $filters['offset']);
        
        $inquiries = $this->inquiryRepository->findBySellerId(
            $sellerId,
            $filters,
            ['created_at' => 'DESC'],
            $limit,
            $offset
        );
        
        // Enrich with item details
        foreach ($inquiries as $inquiry) {
            if ($inquiry->getItemId()) {
                $itemInfo = $this->getItemInfo($inquiry->getItemId());
                if ($itemInfo) {
                    // Add item title to inquiry array representation
                    $inquiryArray = $inquiry->toArray();
                    $inquiryArray['item_title'] = $itemInfo['title'];
                    $inquiryArray['item_price'] = $itemInfo['price'];
                }
            }
        }
        
        return $inquiries;
    }
    
    /**
     * Mark an inquiry as read
     */
    public function markAsRead(int $inquiryId, int $sellerId): bool
    {
        $inquiry = $this->inquiryRepository->findById($inquiryId);
        
        if (!$inquiry) {
            throw new \InvalidArgumentException('Inquiry not found');
        }
        
        if ($inquiry->getSellerUserId() !== $sellerId) {
            throw new \UnauthorizedAccessException('Not authorized to access this inquiry');
        }
        
        if ($inquiry->isNew()) {
            $inquiry->markAsRead();
            $this->inquiryRepository->save($inquiry);
        }
        
        return true;
    }
    
    /**
     * Get unread inquiry count for seller
     */
    public function getUnreadCount(int $sellerId): int
    {
        return $this->inquiryRepository->countUnreadBySeller($sellerId);
    }
    
    /**
     * Add admin notes to an inquiry
     */
    public function addAdminNotes(int $inquiryId, int $sellerId, string $notes): bool
    {
        $inquiry = $this->inquiryRepository->findById($inquiryId);
        
        if (!$inquiry) {
            throw new \InvalidArgumentException('Inquiry not found');
        }
        
        if ($inquiry->getSellerUserId() !== $sellerId) {
            throw new \UnauthorizedAccessException('Not authorized to access this inquiry');
        }
        
        $inquiry->setAdminNotes($notes);
        $this->inquiryRepository->save($inquiry);
        
        return true;
    }
    
    /**
     * Get item information including seller user ID
     */
    private function getItemInfo(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, s.seller_id, sel.seller_user_id
            FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            JOIN yfc_sellers sel ON s.seller_id = sel.id
            WHERE i.id = :item_id
        ");
        
        $stmt->execute(['item_id' => $itemId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
}

// Define custom exception if it doesn't exist
if (!class_exists('\UnauthorizedAccessException')) {
    class UnauthorizedAccessException extends \Exception {}
}