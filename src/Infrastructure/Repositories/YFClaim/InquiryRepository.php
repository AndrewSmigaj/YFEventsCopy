<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\YFClaim;

use YFEvents\Infrastructure\Database\ConnectionInterface;
use YFEvents\Domain\YFClaim\Entities\Inquiry;
use YFEvents\Domain\YFClaim\Repositories\InquiryRepositoryInterface;
use PDO;

/**
 * Inquiry repository implementation
 */
class InquiryRepository implements InquiryRepositoryInterface
{
    private PDO $pdo;
    
    public function __construct(ConnectionInterface $connection)
    {
        $this->pdo = $connection->getConnection();
    }
    
    private function getTableName(): string
    {
        return 'yfc_inquiries';
    }
    
    /**
     * Save an inquiry (create or update)
     */
    public function save(Inquiry $inquiry): ?Inquiry
    {
        
        $data = $inquiry->toArray();
        
        if ($inquiry->getId() === null) {
            // Insert new inquiry
            unset($data['id']);
            $sql = "INSERT INTO {$this->getTableName()} 
                    (sale_id, item_id, seller_user_id, buyer_name, buyer_email, 
                     buyer_phone, subject, message, status, ip_address, 
                     user_agent, admin_notes, created_at, updated_at, responded_at) 
                    VALUES (:sale_id, :item_id, :seller_user_id, :buyer_name, :buyer_email,
                            :buyer_phone, :subject, :message, :status, :ip_address,
                            :user_agent, :admin_notes, :created_at, :updated_at, :responded_at)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            
            $id = (int) $this->pdo->lastInsertId();
            return $this->findById($id);
        } else {
            // Update existing inquiry
            $sql = "UPDATE {$this->getTableName()} 
                    SET status = :status, admin_notes = :admin_notes, 
                        updated_at = :updated_at, responded_at = :responded_at
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'status' => $data['status'],
                'admin_notes' => $data['admin_notes'],
                'updated_at' => $data['updated_at'],
                'responded_at' => $data['responded_at'],
                'id' => $data['id']
            ]);
            
            return $inquiry;
        }
    }
    
    /**
     * Find inquiry by ID
     */
    public function findById(int $id): ?Inquiry
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ? Inquiry::fromArray($data) : null;
    }
    
    /**
     * Find all inquiries for a seller
     */
    public function findBySellerId(
        int $sellerId, 
        array $filters = [], 
        array $orderBy = ['created_at' => 'DESC'], 
        ?int $limit = null, 
        ?int $offset = null
    ): array {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE seller_user_id = :seller_id";
        $params = ['seller_id' => $sellerId];
        
        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['item_id'])) {
            $sql .= " AND item_id = :item_id";
            $params['item_id'] = $filters['item_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        // Add order by
        $orderClauses = [];
        foreach ($orderBy as $field => $direction) {
            $orderClauses[] = "$field $direction";
        }
        if (!empty($orderClauses)) {
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }
        
        // Add limit and offset
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params['limit'] = $limit;
        }
        
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
            $params['offset'] = $offset;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Inquiry::fromArray($row);
        }
        
        return $results;
    }
    
    /**
     * Count unread inquiries for a seller
     */
    public function countUnreadBySeller(int $sellerId): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->getTableName()} 
                WHERE seller_user_id = :seller_id AND status = :status";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'seller_id' => $sellerId,
            'status' => Inquiry::STATUS_NEW
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }
    
    /**
     * Update inquiry status
     */
    public function updateStatus(int $id, string $status): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET status = :status, updated_at = :updated_at
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'status' => $status,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $id
        ]);
    }
    
    /**
     * Find inquiries by item ID
     */
    public function findByItemId(int $itemId): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE item_id = :item_id 
                ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['item_id' => $itemId]);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Inquiry::fromArray($row);
        }
        
        return $results;
    }
    
    /**
     * Find inquiries by buyer email
     */
    public function findByBuyerEmail(string $email): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE buyer_email = :email 
                ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Inquiry::fromArray($row);
        }
        
        return $results;
    }
}