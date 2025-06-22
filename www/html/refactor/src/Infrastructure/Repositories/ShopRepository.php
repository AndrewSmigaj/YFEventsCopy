<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories;

use YFEvents\Domain\Shops\Shop;
use YFEvents\Domain\Shops\ShopRepositoryInterface;
use YFEvents\Infrastructure\Database\AbstractRepository;

/**
 * Shop repository implementation
 */
class ShopRepository extends AbstractRepository implements ShopRepositoryInterface
{
    protected function getTableName(): string
    {
        return 'local_shops';
    }

    protected function getEntityClass(): string
    {
        return Shop::class;
    }

    public function findByCategory(int $categoryId): array
    {
        return $this->findBy(['category_id' => $categoryId], ['name' => 'ASC']);
    }

    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status], ['name' => 'ASC']);
    }

    public function findFeatured(int $limit = 10): array
    {
        return $this->findBy(
            ['featured' => 1, 'status' => 'active', 'active' => 1],
            ['name' => 'ASC'],
            $limit
        );
    }

    public function findVerified(int $limit = 100): array
    {
        return $this->findBy(
            ['verified' => 1, 'status' => 'active', 'active' => 1],
            ['name' => 'ASC'],
            $limit
        );
    }

    public function findNearLocation(float $latitude, float $longitude, float $radiusMiles = 10): array
    {
        $sql = "SELECT *, 
                (3959 * acos(cos(radians(:lat)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(:lng)) + 
                sin(radians(:lat)) * sin(radians(latitude)))) AS distance 
                FROM {$this->getTableName()} 
                WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                AND status = 'active' AND active = 1
                HAVING distance <= :radius 
                ORDER BY distance ASC, name ASC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'lat' => $latitude,
            'lng' => $longitude,
            'radius' => $radiusMiles
        ]);

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // Remove distance from data before creating entity
            unset($data['distance']);
            $results[] = Shop::fromArray($data);
        }

        return $results;
    }

    public function search(string $query, array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE 1=1";
        $params = [];

        // Text search
        if (!empty($query)) {
            $sql .= " AND (name LIKE :query1 OR description LIKE :query2 OR address LIKE :query3)";
            $params['query1'] = "%{$query}%";
            $params['query2'] = "%{$query}%";
            $params['query3'] = "%{$query}%";
        }

        // Apply filters
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }

        if (isset($filters['featured'])) {
            $sql .= " AND featured = :featured";
            $params['featured'] = $filters['featured'] ? 1 : 0;
        }

        if (isset($filters['verified'])) {
            $sql .= " AND verified = :verified";
            $params['verified'] = $filters['verified'] ? 1 : 0;
        }

        if (isset($filters['active'])) {
            $sql .= " AND active = :active";
            $params['active'] = $filters['active'] ? 1 : 0;
        }

        if (isset($filters['category_id'])) {
            $sql .= " AND category_id = :category_id";
            $params['category_id'] = $filters['category_id'];
        }

        if (isset($filters['owner_id'])) {
            $sql .= " AND owner_id = :owner_id";
            $params['owner_id'] = $filters['owner_id'];
        }

        // Payment methods filter
        if (isset($filters['payment_methods']) && is_array($filters['payment_methods'])) {
            $paymentConditions = [];
            foreach ($filters['payment_methods'] as $index => $method) {
                $paramKey = "payment_method_{$index}";
                $paymentConditions[] = "JSON_CONTAINS(payment_methods, JSON_QUOTE(:{$paramKey}))";
                $params[$paramKey] = $method;
            }
            if (!empty($paymentConditions)) {
                $sql .= " AND (" . implode(' OR ', $paymentConditions) . ")";
            }
        }

        // Amenities filter
        if (isset($filters['amenities']) && is_array($filters['amenities'])) {
            $amenityConditions = [];
            foreach ($filters['amenities'] as $index => $amenity) {
                $paramKey = "amenity_{$index}";
                $amenityConditions[] = "JSON_CONTAINS(amenities, JSON_QUOTE(:{$paramKey}))";
                $params[$paramKey] = $amenity;
            }
            if (!empty($amenityConditions)) {
                $sql .= " AND (" . implode(' AND ', $amenityConditions) . ")";
            }
        }

        $sql .= " ORDER BY featured DESC, verified DESC, name ASC";

        if (isset($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params['limit'] = (int) $filters['limit'];
        }

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === 'limit') {
                $stmt->bindValue(":$key", $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$key", $value);
            }
        }
        
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            // Debug info for parameter issues
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params));
            throw $e;
        }

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Shop::fromArray($data);
        }

        return $results;
    }

    public function findByOwner(int $ownerId): array
    {
        return $this->findBy(['owner_id' => $ownerId], ['name' => 'ASC']);
    }

    public function findByPaymentMethods(array $methods): array
    {
        if (empty($methods)) {
            return [];
        }

        $conditions = [];
        $params = [];
        
        foreach ($methods as $index => $method) {
            $paramKey = "method_{$index}";
            $conditions[] = "JSON_CONTAINS(payment_methods, JSON_QUOTE(:{$paramKey}))";
            $params[$paramKey] = $method;
        }

        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE (" . implode(' OR ', $conditions) . ")
                AND status = 'active' AND active = 1
                ORDER BY featured DESC, name ASC";

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Shop::fromArray($data);
        }

        return $results;
    }

    public function findByAmenities(array $amenities): array
    {
        if (empty($amenities)) {
            return [];
        }

        $conditions = [];
        $params = [];
        
        foreach ($amenities as $index => $amenity) {
            $paramKey = "amenity_{$index}";
            $conditions[] = "JSON_CONTAINS(amenities, JSON_QUOTE(:{$paramKey}))";
            $params[$paramKey] = $amenity;
        }

        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE (" . implode(' AND ', $conditions) . ")
                AND status = 'active' AND active = 1
                ORDER BY featured DESC, name ASC";

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Shop::fromArray($data);
        }

        return $results;
    }

    public function getShopsForMap(array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                AND status = 'active' AND active = 1";
        
        $params = [];

        if (isset($filters['category_id'])) {
            $sql .= " AND category_id = :category_id";
            $params['category_id'] = $filters['category_id'];
        }

        if (isset($filters['featured'])) {
            $sql .= " AND featured = :featured";
            $params['featured'] = $filters['featured'] ? 1 : 0;
        }

        if (isset($filters['verified'])) {
            $sql .= " AND verified = :verified";
            $params['verified'] = $filters['verified'] ? 1 : 0;
        }

        $sql .= " ORDER BY featured DESC, verified DESC, name ASC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Shop::fromArray($data);
        }

        return $results;
    }

    public function countByStatus(): array
    {
        $sql = "SELECT status, COUNT(*) as count FROM {$this->getTableName()} GROUP BY status";
        $stmt = $this->connection->execute($sql);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[$row['status']] = (int) $row['count'];
        }

        return $results;
    }

    public function getStatistics(): array
    {
        $statusCounts = $this->countByStatus();
        
        // Get additional stats
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured_count,
                    SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified_count,
                    SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 ELSE 0 END) as geocoded_count,
                    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_count
                FROM {$this->getTableName()}";
        
        $stmt = $this->connection->execute($sql);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total' => (int) $stats['total'],
            'by_status' => $statusCounts,
            'featured' => (int) $stats['featured_count'],
            'verified' => (int) $stats['verified_count'],
            'geocoded' => (int) $stats['geocoded_count'],
            'active' => (int) $stats['active_count'],
            'pending' => $statusCounts['pending'] ?? 0,
            'approved' => $statusCounts['active'] ?? 0,
            'inactive' => $statusCounts['inactive'] ?? 0,
        ];
    }
}