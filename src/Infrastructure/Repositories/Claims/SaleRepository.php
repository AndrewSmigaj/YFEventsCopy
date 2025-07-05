<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Claims;

use YFEvents\Domain\Claims\Sale;
use YFEvents\Domain\Claims\SaleRepositoryInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use PDO;

class SaleRepository implements SaleRepositoryInterface
{
    private PDO $pdo;

    public function __construct(ConnectionInterface $connection)
    {
        $this->pdo = $connection->getConnection();
    }

    /**
     * Find sale by ID
     */
    public function findById(int $id): ?Sale
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, sel.company_name,
                   COUNT(DISTINCT i.id) as item_count
            FROM yfc_sales s
            LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            WHERE s.id = :id
            GROUP BY s.id
        ");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();
        
        return $data ? Sale::fromArray($data) : null;
    }

    /**
     * Find sale by access code
     */
    public function findByAccessCode(string $accessCode): ?Sale
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, sel.company_name
            FROM yfc_sales s
            LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
            WHERE s.access_code = :code
        ");
        $stmt->execute(['code' => $accessCode]);
        $data = $stmt->fetch();
        
        return $data ? Sale::fromArray($data) : null;
    }

    /**
     * Find sales by seller ID
     */
    public function findBySellerId(int $sellerId, ?string $status = null): array
    {
        $sql = "
            SELECT s.*, 
                   COUNT(DISTINCT i.id) as item_count
            FROM yfc_sales s
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            WHERE s.seller_id = :seller_id
        ";
        
        $params = ['seller_id' => $sellerId];
        
        if ($status !== null) {
            $sql .= " AND s.status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " GROUP BY s.id ORDER BY s.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return array_map(fn($row) => Sale::fromArray($row), $stmt->fetchAll());
    }

    /**
     * Find active sales
     * Reusing query from ClaimsController::getCurrentSales()
     */
    public function findActive(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, sel.company_name, 
                   COUNT(i.id) as item_count
            FROM yfc_sales s
            LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            WHERE s.status = 'active' 
            AND s.claim_start <= NOW() 
            AND s.claim_end >= NOW()
            GROUP BY s.id
            ORDER BY s.claim_end ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return array_map(fn($row) => Sale::fromArray($row), $stmt->fetchAll());
    }

    /**
     * Find sales by phase
     */
    public function findByPhase(string $phase, int $limit = 20, int $offset = 0): array
    {
        $sql = match($phase) {
            'preview' => "
                SELECT s.*, sel.company_name, COUNT(i.id) as item_count
                FROM yfc_sales s
                LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
                LEFT JOIN yfc_items i ON s.id = i.sale_id
                WHERE s.status = 'active' 
                AND s.preview_start IS NOT NULL
                AND s.preview_start <= NOW() 
                AND s.claim_start > NOW()
                GROUP BY s.id
                ORDER BY s.claim_start ASC
            ",
            'active' => "
                SELECT s.*, sel.company_name, COUNT(i.id) as item_count
                FROM yfc_sales s
                LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
                LEFT JOIN yfc_items i ON s.id = i.sale_id
                WHERE s.status = 'active' 
                AND s.claim_start <= NOW() 
                AND s.claim_end >= NOW()
                GROUP BY s.id
                ORDER BY s.claim_end ASC
            ",
            'ended' => "
                SELECT s.*, sel.company_name, COUNT(i.id) as item_count
                FROM yfc_sales s
                LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
                LEFT JOIN yfc_items i ON s.id = i.sale_id
                WHERE s.status = 'active' 
                AND s.claim_end < NOW()
                GROUP BY s.id
                ORDER BY s.claim_end DESC
            ",
            default => throw new \InvalidArgumentException("Invalid phase: $phase")
        };
        
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return array_map(fn($row) => Sale::fromArray($row), $stmt->fetchAll());
    }

    /**
     * Find sales near location
     */
    public function findNearLocation(float $latitude, float $longitude, float $radiusMiles = 25): array
    {
        // Using Haversine formula for distance calculation
        $stmt = $this->pdo->prepare("
            SELECT s.*, sel.company_name,
                   COUNT(i.id) as item_count,
                   (3959 * acos(
                       cos(radians(:lat)) * 
                       cos(radians(s.latitude)) * 
                       cos(radians(s.longitude) - radians(:lng)) + 
                       sin(radians(:lat2)) * 
                       sin(radians(s.latitude))
                   )) AS distance
            FROM yfc_sales s
            LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            WHERE s.status = 'active'
            AND s.latitude IS NOT NULL
            AND s.longitude IS NOT NULL
            GROUP BY s.id
            HAVING distance < :radius
            ORDER BY distance ASC
        ");
        
        $stmt->execute([
            'lat' => $latitude,
            'lat2' => $latitude,
            'lng' => $longitude,
            'radius' => $radiusMiles
        ]);
        
        return array_map(fn($row) => Sale::fromArray($row), $stmt->fetchAll());
    }

    /**
     * Find upcoming sales
     * Reusing query from ClaimsController::getUpcomingSales()
     */
    public function findUpcoming(int $days = 7): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, sel.company_name, 
                   COUNT(i.id) as item_count
            FROM yfc_sales s
            LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            WHERE s.status = 'active' 
            AND s.claim_start > NOW()
            AND s.claim_start <= DATE_ADD(NOW(), INTERVAL :days DAY)
            GROUP BY s.id
            ORDER BY s.claim_start ASC
        ");
        $stmt->execute(['days' => $days]);
        
        return array_map(fn($row) => Sale::fromArray($row), $stmt->fetchAll());
    }

    /**
     * Save sale (create or update)
     */
    public function save(Sale $sale): Sale
    {
        $data = $sale->toArray();
        
        if ($sale->getId() === null) {
            // Insert new sale
            $sql = "INSERT INTO yfc_sales (seller_id, title, description, address, city, state, zip, 
                    latitude, longitude, preview_start, preview_end, claim_start, claim_end, 
                    status, access_code, qr_code, created_at, updated_at)
                    VALUES (:seller_id, :title, :description, :address, :city, :state, :zip,
                    :latitude, :longitude, :preview_start, :preview_end, :claim_start, :claim_end,
                    :status, :access_code, :qr_code, NOW(), NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            
            $data['id'] = (int)$this->pdo->lastInsertId();
            return Sale::fromArray($data);
        } else {
            // Update existing sale
            $sql = "UPDATE yfc_sales SET 
                    seller_id = :seller_id, title = :title, description = :description,
                    address = :address, city = :city, state = :state, zip = :zip,
                    latitude = :latitude, longitude = :longitude,
                    preview_start = :preview_start, preview_end = :preview_end,
                    claim_start = :claim_start, claim_end = :claim_end,
                    status = :status, access_code = :access_code, qr_code = :qr_code,
                    updated_at = NOW()
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            
            return $sale;
        }
    }

    /**
     * Delete sale
     */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM yfc_sales WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /**
     * Count sales by seller
     */
    public function countBySeller(int $sellerId, ?string $status = null): int
    {
        $sql = "SELECT COUNT(*) FROM yfc_sales WHERE seller_id = :seller_id";
        $params = ['seller_id' => $sellerId];
        
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get sale statistics
     * Reusing query from ClaimsController::getSaleStats()
     */
    public function getStatistics(int $saleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT i.id) as total_items,
                COUNT(DISTINCT CASE WHEN i.status = 'claimed' THEN i.id END) as claimed_items
            FROM yfc_items i
            WHERE i.sale_id = :sale_id
        ");
        $stmt->execute(['sale_id' => $saleId]);
        
        $stats = $stmt->fetch();
        
        // Add more stats
        $stats['total_offers'] = 0; // Offers removed
        $stats['items_with_offers'] = 0; // Offers removed
        
        return $stats;
    }

    /**
     * Update sale statistics
     */
    public function updateStatistics(int $saleId): void
    {
        // This could update cached statistics if we had a stats column
        // For now, statistics are calculated on demand
    }

    /**
     * Generate unique access code
     */
    public function generateUniqueAccessCode(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM yfc_sales WHERE access_code = :code");
            $stmt->execute(['code' => $code]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        
        return $code;
    }
}