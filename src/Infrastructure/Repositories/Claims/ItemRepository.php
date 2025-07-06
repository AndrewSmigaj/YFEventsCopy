<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Claims;

use YFEvents\Domain\Claims\Item;
use YFEvents\Domain\Claims\ItemRepositoryInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use PDO;

class ItemRepository implements ItemRepositoryInterface
{
    private PDO $pdo;

    public function __construct(ConnectionInterface $connection)
    {
        $this->pdo = $connection->getConnection();
    }

    /**
     * Find item by ID
     * Reusing query from ClaimsController::getItemById()
     */
    public function findById(int $id): ?Item
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, s.title as sale_title, s.id as sale_id,
                   sel.company_name, sel.phone as seller_phone
            FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            JOIN yfc_sellers sel ON s.seller_id = sel.id
            WHERE i.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();
        
        return $data ? Item::fromArray($data) : null;
    }

    /**
     * Find items by sale ID
     * Reusing query from ClaimsController::getSaleItems()
     */
    public function findBySaleId(int $saleId, ?string $status = null): array
    {
        $sql = "
            SELECT i.*,
                   (SELECT filename FROM yfc_item_images 
                    WHERE item_id = i.id 
                    ORDER BY is_primary DESC, sort_order ASC 
                    LIMIT 1) as primary_image
            FROM yfc_items i
            WHERE i.sale_id = :sale_id
        ";
        
        $params = ['sale_id' => $saleId];
        
        if ($status !== null) {
            $sql .= " AND i.status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY i.item_number ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return array_map(fn($row) => Item::fromArray($row), $stmt->fetchAll());
    }

    /**
     * Find items by category
     */
    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*,
                   s.title as sale_title,
                   (SELECT filename FROM yfc_item_images 
                    WHERE item_id = i.id 
                    ORDER BY is_primary DESC, sort_order ASC 
                    LIMIT 1) as primary_image
            FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            WHERE i.category_id = :category_id
            AND s.status = 'active'
            AND s.claim_start <= NOW()
            AND s.claim_end >= NOW()
            ORDER BY i.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return array_map(fn($row) => Item::fromArray($row), $stmt->fetchAll());
    }

    /**
     * Find items with offers
     * Since offers are removed, this returns all items with mock data
     */
    public function findWithOffers(int $saleId): array
    {
        $items = $this->findBySaleId($saleId);
        
        // Add mock offer data for compatibility
        foreach ($items as $item) {
            $item->setOfferCount(0);
            $item->setHighestOffer(null);
        }
        
        return $items;
    }

    /**
     * Find sold items
     */
    public function findSold(int $saleId): array
    {
        return $this->findBySaleId($saleId, 'claimed');
    }

    /**
     * Search items
     */
    public function search(string $query, ?int $saleId = null, int $limit = 20): array
    {
        $sql = "
            SELECT i.*,
                   s.title as sale_title,
                   (SELECT filename FROM yfc_item_images 
                    WHERE item_id = i.id 
                    ORDER BY is_primary DESC, sort_order ASC 
                    LIMIT 1) as primary_image
            FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            WHERE (i.title LIKE :query OR i.description LIKE :query)
            AND s.status = 'active'
        ";
        
        $params = ['query' => '%' . $query . '%'];
        
        if ($saleId !== null) {
            $sql .= " AND i.sale_id = :sale_id";
            $params['sale_id'] = $saleId;
        }
        
        $sql .= " ORDER BY i.created_at DESC LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return array_map(fn($row) => Item::fromArray($row), $stmt->fetchAll());
    }

    /**
     * Save item (create or update)
     */
    public function save(Item $item): Item
    {
        $data = $item->toArray();
        
        if ($item->getId() === null) {
            // Insert new item
            $sql = "INSERT INTO yfc_items (sale_id, item_number, title, description, category,
                    condition_rating, price, quantity, status, created_at, updated_at)
                    VALUES (:sale_id, :item_number, :title, :description, :category,
                    :condition_rating, :price, :quantity, :status, NOW(), NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            
            $data['id'] = (int)$this->pdo->lastInsertId();
            return Item::fromArray($data);
        } else {
            // Update existing item
            $sql = "UPDATE yfc_items SET 
                    sale_id = :sale_id, item_number = :item_number, title = :title,
                    description = :description, category = :category,
                    condition_rating = :condition_rating, price = :price,
                    quantity = :quantity, status = :status, updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            
            return $item;
        }
    }

    /**
     * Save multiple items
     */
    public function saveMultiple(array $items): array
    {
        $savedItems = [];
        
        foreach ($items as $item) {
            $savedItems[] = $this->save($item);
        }
        
        return $savedItems;
    }

    /**
     * Delete item
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM yfc_items WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /**
     * Delete items by sale
     */
    public function deleteBySale(int $saleId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM yfc_items WHERE sale_id = :sale_id");
        $stmt->execute(['sale_id' => $saleId]);
    }

    /**
     * Count items by sale
     */
    public function countBySale(int $saleId, ?string $status = null): int
    {
        $sql = "SELECT COUNT(*) FROM yfc_items WHERE sale_id = :sale_id";
        $params = ['sale_id' => $saleId];
        
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get item statistics
     */
    public function getStatistics(int $itemId): array
    {
        return [
            'view_count' => 0,
            'offer_count' => 0, // Offers removed
            'highest_offer' => null, // Offers removed
            'watchers' => 0
        ];
    }

    /**
     * Increment view count
     */
    public function incrementViewCount(int $itemId): void
    {
        // Could update a view_count column if it existed
        // For now, this is a no-op
    }

    /**
     * Get popular items
     */
    public function getPopular(int $saleId, int $limit = 10): array
    {
        // Since we don't track views/offers, return items with images first
        $stmt = $this->pdo->prepare("
            SELECT i.*,
                   (SELECT filename FROM yfc_item_images 
                    WHERE item_id = i.id 
                    ORDER BY is_primary DESC, sort_order ASC 
                    LIMIT 1) as primary_image
            FROM yfc_items i
            WHERE i.sale_id = :sale_id
            AND i.status = 'available'
            AND EXISTS (
                SELECT 1 FROM yfc_item_images 
                WHERE item_id = i.id
            )
            ORDER BY i.created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return array_map(fn($row) => Item::fromArray($row), $stmt->fetchAll());
    }

    /**
     * Find all items from active sales with filters
     * This is NEW - existing methods only search within a single sale
     */
    public function findAllWithImages(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT i.*, 
                   s.title as sale_title, s.city, s.state,
                   sel.company_name,
                   (SELECT filename FROM yfc_item_images 
                    WHERE item_id = i.id 
                    ORDER BY is_primary DESC, sort_order ASC 
                    LIMIT 1) as primary_image
            FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            JOIN yfc_sellers sel ON s.seller_id = sel.id
            WHERE s.status = 'active'
            AND s.claim_start <= NOW()
            AND s.claim_end >= NOW()
            AND i.status = 'available'
        ";
        
        $params = [];
        
        // Add filters
        if (!empty($filters['category_id'])) {
            $sql .= " AND i.category = :category_id";
            $params['category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['min_price'])) {
            $sql .= " AND i.price >= :min_price";
            $params['min_price'] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $sql .= " AND i.price <= :max_price";
            $params['max_price'] = $filters['max_price'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (i.title LIKE :search OR i.description LIKE :search2)";
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search2'] = '%' . $filters['search'] . '%';
        }
        
        // Add sorting
        $sortBy = $filters['sort'] ?? 'newest';
        switch ($sortBy) {
            case 'price_low':
                $sql .= " ORDER BY i.price ASC";
                break;
            case 'price_high':
                $sql .= " ORDER BY i.price DESC";
                break;
            case 'ending_soon':
                $sql .= " ORDER BY s.claim_end ASC";
                break;
            default:
                $sql .= " ORDER BY i.created_at DESC";
        }
        
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        // Return arrays for controller compatibility
        return $stmt->fetchAll();
    }

    /**
     * Get all categories that have items in active sales
     * This is NEW - no existing method provides this
     */
    public function getCategories(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT i.category as category_id, i.category
            FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            WHERE s.status = 'active'
            AND s.claim_start <= NOW()
            AND s.claim_end >= NOW()
            AND i.category IS NOT NULL
            ORDER BY i.category
        ");
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Count all items matching filters
     * This is NEW - needed for pagination
     */
    public function countAll(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            WHERE s.status = 'active'
            AND s.claim_start <= NOW()
            AND s.claim_end >= NOW()
            AND i.status = 'available'
        ";
        
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND i.category = :category_id";
            $params['category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['min_price'])) {
            $sql .= " AND i.price >= :min_price";
            $params['min_price'] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $sql .= " AND i.price <= :max_price";
            $params['max_price'] = $filters['max_price'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (i.title LIKE :search OR i.description LIKE :search2)";
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search2'] = '%' . $filters['search'] . '%';
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Get item previews for a sale (first N items with images)
     */
    public function getItemPreviews(int $saleId, int $limit = 4): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.id, i.title, i.price,
                   (SELECT filename FROM yfc_item_images 
                    WHERE item_id = i.id 
                    ORDER BY is_primary DESC, sort_order ASC 
                    LIMIT 1) as primary_image
            FROM yfc_items i
            WHERE i.sale_id = :sale_id
            AND i.status = 'available'
            ORDER BY i.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}