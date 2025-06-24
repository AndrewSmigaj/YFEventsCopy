<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Claims;

use YFEvents\Domain\Claims\Item;
use YFEvents\Domain\Claims\ItemRepositoryInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use PDO;
use PDOException;

class ItemRepository implements ItemRepositoryInterface
{
    private PDO $pdo;

    public function __construct(ConnectionInterface $connection)
    {
        $this->pdo = $connection->getConnection();
    }

    public function findById(int $id): ?Item
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_items WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findBySaleId(int $saleId, ?string $status = null): array
    {
        $sql = "SELECT * FROM yfc_items WHERE sale_id = :sale_id";
        $params = ['sale_id' => $saleId];
        
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY sort_order ASC, id ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        
        return $items;
    }

    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.* FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.sale_id
            WHERE i.category_id = :category_id
            AND i.status = 'available'
            AND s.status = 'active'
            ORDER BY i.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        
        return $items;
    }

    public function findWithOffers(int $saleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT i.* FROM yfc_items i
            INNER JOIN yfc_offers o ON i.item_id = o.item_id
            WHERE i.sale_id = :sale_id
            ORDER BY i.sort_order ASC, i.id ASC
        ");
        
        $stmt->execute(['sale_id' => $saleId]);
        
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        
        return $items;
    }

    public function findSold(int $saleId): array
    {
        return $this->findBySaleId($saleId, 'sold');
    }

    public function search(string $query, ?int $saleId = null, int $limit = 20): array
    {
        $sql = "
            SELECT i.* FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.sale_id
            WHERE (i.title LIKE :query OR i.description LIKE :query)
            AND s.status = 'active'
        ";
        
        $params = ['query' => '%' . $query . '%'];
        
        if ($saleId !== null) {
            $sql .= " AND i.sale_id = :sale_id";
            $params['sale_id'] = $saleId;
        }
        
        $sql .= " ORDER BY i.view_count DESC LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        
        return $items;
    }

    public function getPopular(int $saleId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, COUNT(o.offer_id) as offer_count
            FROM yfc_items i
            LEFT JOIN yfc_offers o ON i.item_id = o.item_id
            WHERE i.sale_id = :sale_id
            AND i.status = 'available'
            GROUP BY i.item_id
            ORDER BY offer_count DESC, i.view_count DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        
        return $items;
    }

    public function save(Item $item): Item
    {
        if ($item->getId()) {
            return $this->update($item);
        }
        
        return $this->create($item);
    }

    public function saveMultiple(array $items): array
    {
        $saved = [];
        
        $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $saved[] = $this->save($item);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        
        return $saved;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM yfc_items WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function deleteBySale(int $saleId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM yfc_items WHERE sale_id = :sale_id");
        $stmt->execute(['sale_id' => $saleId]);
    }

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
        
        return (int) $stmt->fetchColumn();
    }

    public function getStatistics(int $itemId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                i.*,
                COUNT(DISTINCT o.offer_id) as total_offers,
                COUNT(DISTINCT o.buyer_id) as unique_bidders,
                MAX(o.offer_amount) as highest_offer,
                MIN(o.offer_amount) as lowest_offer,
                AVG(o.offer_amount) as average_offer,
                SUM(CASE WHEN o.status = 'accepted' THEN o.offer_amount ELSE 0 END) as accepted_amount
            FROM yfc_items i
            LEFT JOIN yfc_offers o ON i.item_id = o.item_id
            WHERE i.item_id = :item_id
            GROUP BY i.item_id
        ");
        
        $stmt->execute(['item_id' => $itemId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            return [];
        }
        
        return [
            'item_id' => $itemId,
            'view_count' => (int) $stats['view_count'],
            'total_offers' => (int) $stats['total_offers'],
            'unique_bidders' => (int) $stats['unique_bidders'],
            'highest_offer' => (float) $stats['highest_offer'],
            'lowest_offer' => (float) $stats['lowest_offer'],
            'average_offer' => (float) $stats['average_offer'],
            'accepted_amount' => (float) $stats['accepted_amount'],
            'status' => $stats['status']
        ];
    }

    public function incrementViewCount(int $itemId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE yfc_items 
            SET view_count = view_count + 1 
            WHERE item_id = :item_id
        ");
        
        $stmt->execute(['item_id' => $itemId]);
    }

    private function create(Item $item): Item
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO yfc_items (
                sale_id, item_number, title, description, category_id,
                starting_price, status, primary_image, images,
                condition_notes, measurements, sort_order, featured
            ) VALUES (
                :sale_id, :item_number, :title, :description, :category_id,
                :starting_price, :status, :primary_image, :images,
                :condition_notes, :measurements, :sort_order, :featured
            )
        ");
        
        $stmt->execute($this->toArray($item));
        $item->setId((int) $this->pdo->lastInsertId());
        
        return $item;
    }

    private function update(Item $item): Item
    {
        $stmt = $this->pdo->prepare("
            UPDATE yfc_items SET
                sale_id = :sale_id,
                item_number = :item_number,
                title = :title,
                description = :description,
                category_id = :category_id,
                starting_price = :starting_price,
                status = :status,
                primary_image = :primary_image,
                images = :images,
                condition_notes = :condition_notes,
                measurements = :measurements,
                sort_order = :sort_order,
                featured = :featured
            WHERE id = :id
        ");
        
        $data = $this->toArray($item);
        $data['id'] = $item->getId();
        
        $stmt->execute($data);
        
        return $item;
    }

    private function hydrate(array $row): Item
    {
        $item = new Item(
            saleId: (int) $row['sale_id'],
            title: $row['title'],
            description: $row['description'] ?? ''
        );
        
        $item->setId((int) $row['id']);
        
        if ($row['item_number']) {
            $item->setLotNumber($row['item_number']);
        }
        
        if ($row['category_id']) {
            $item->setCategoryId((int) $row['category_id']);
        }
        
        if ($row['condition_notes']) {
            $item->setCondition($row['condition_notes']);
        }
        
        if ($row['measurements']) {
            $item->setDimensions($row['measurements']);
        }
        
        if ($row['starting_price'] !== null) {
            $item->setStartingBid((float) $row['starting_price']);
        }
        
        $item->setStatus($row['status'] ?? 'active');
        
        $images = [];
        if ($row['primary_image']) {
            $images[] = $row['primary_image'];
        }
        if ($row['images']) {
            $additionalImages = json_decode($row['images'], true) ?: [];
            $images = array_merge($images, $additionalImages);
        }
        $item->setImages($images);
        
        $metadata = [];
        if ($row['current_high_offer'] !== null) {
            $metadata['current_high_offer'] = (float) $row['current_high_offer'];
        }
        if ($row['offer_count'] !== null) {
            $metadata['offer_count'] = (int) $row['offer_count'];
        }
        if ($row['featured']) {
            $metadata['featured'] = (bool) $row['featured'];
        }
        if ($row['sort_order'] !== null) {
            $metadata['sort_order'] = (int) $row['sort_order'];
        }
        $item->setMetadata($metadata);
        
        $item->setViewCount(0); // Not tracked in this schema
        
        if ($row['created_at']) {
            $item->setCreatedAt(new \DateTime($row['created_at']));
        }
        
        if ($row['updated_at']) {
            $item->setUpdatedAt(new \DateTime($row['updated_at']));
        }
        
        return $item;
    }

    private function toArray(Item $item): array
    {
        $images = $item->getImages();
        $primaryImage = !empty($images) ? array_shift($images) : null;
        
        $metadata = $item->getMetadata();
        
        return [
            'sale_id' => $item->getSaleId(),
            'item_number' => $item->getLotNumber(),
            'title' => $item->getTitle(),
            'description' => $item->getDescription(),
            'category_id' => $item->getCategoryId(),
            'starting_price' => $item->getStartingBid() ?? 0.00,
            'status' => $item->getStatus(),
            'primary_image' => $primaryImage,
            'images' => json_encode($images),
            'condition_notes' => $item->getCondition(),
            'measurements' => $item->getDimensions(),
            'sort_order' => $metadata['sort_order'] ?? 0,
            'featured' => $metadata['featured'] ?? 0,
        ];
    }
}