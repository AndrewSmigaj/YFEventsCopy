<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Claims;

use YFEvents\Domain\Claims\Sale;
use YFEvents\Domain\Claims\SaleRepositoryInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use PDO;
use PDOException;

class SaleRepository implements SaleRepositoryInterface
{
    private PDO $pdo;

    public function __construct(ConnectionInterface $connection)
    {
        $this->pdo = $connection->getConnection();
    }

    public function findById(int $id): ?Sale
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_sales WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByAccessCode(string $accessCode): ?Sale
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_sales WHERE access_code = :access_code
        ");
        $stmt->execute(['access_code' => $accessCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findBySellerId(int $sellerId, ?string $status = null): array
    {
        $sql = "SELECT * FROM yfc_sales WHERE seller_id = :seller_id";
        $params = ['seller_id' => $sellerId];
        
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY start_date DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $sales = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sales[] = $this->hydrate($row);
        }
        
        return $sales;
    }

    public function findActive(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_sales 
            WHERE status = 'active' 
            AND start_date <= NOW() 
            AND end_date >= NOW()
            ORDER BY start_date ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $sales = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sales[] = $this->hydrate($row);
        }
        
        return $sales;
    }

    public function findByPhase(string $phase, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_sales 
            WHERE phase = :phase 
            AND status = 'active'
            ORDER BY start_date ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':phase', $phase);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $sales = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sales[] = $this->hydrate($row);
        }
        
        return $sales;
    }

    public function findNearLocation(float $latitude, float $longitude, float $radiusMiles = 25): array
    {
        // Using Haversine formula for distance calculation
        $stmt = $this->pdo->prepare("
            SELECT *,
                (3959 * acos(
                    cos(radians(:lat)) * 
                    cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(:lng)) + 
                    sin(radians(:lat)) * 
                    sin(radians(latitude))
                )) AS distance
            FROM yfc_sales
            WHERE status = 'active'
            AND latitude IS NOT NULL
            AND longitude IS NOT NULL
            HAVING distance < :radius
            ORDER BY distance ASC
        ");
        
        $stmt->execute([
            'lat' => $latitude,
            'lng' => $longitude,
            'radius' => $radiusMiles
        ]);
        
        $sales = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sales[] = $this->hydrate($row);
        }
        
        return $sales;
    }

    public function findUpcoming(int $days = 7): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_sales 
            WHERE status = 'scheduled' 
            AND start_date > NOW() 
            AND start_date <= DATE_ADD(NOW(), INTERVAL :days DAY)
            ORDER BY start_date ASC
        ");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        $sales = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sales[] = $this->hydrate($row);
        }
        
        return $sales;
    }

    public function save(Sale $sale): Sale
    {
        if ($sale->getId()) {
            return $this->update($sale);
        }
        
        return $this->create($sale);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM yfc_sales WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

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
        
        return (int) $stmt->fetchColumn();
    }

    public function getStatistics(int $saleId): array
    {
        $stats = [];
        
        // Basic sale stats
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT i.item_id) as total_items,
                SUM(CASE WHEN i.status = 'available' THEN 1 ELSE 0 END) as available_items,
                SUM(CASE WHEN i.status = 'sold' THEN 1 ELSE 0 END) as sold_items,
                COUNT(DISTINCT o.buyer_id) as unique_buyers,
                COUNT(o.offer_id) as total_offers,
                SUM(CASE WHEN o.status = 'accepted' THEN o.offer_amount ELSE 0 END) as total_sales_amount,
                AVG(CASE WHEN o.status = 'accepted' THEN o.offer_amount ELSE NULL END) as average_sale_price
            FROM yfc_sales s
            LEFT JOIN yfc_items i ON s.sale_id = i.sale_id
            LEFT JOIN yfc_offers o ON i.item_id = o.item_id
            WHERE s.sale_id = :sale_id
        ");
        $stmt->execute(['sale_id' => $saleId]);
        $basicStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // View statistics
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(i.view_count) as total_views,
                AVG(i.view_count) as avg_views_per_item
            FROM yfc_items i
            WHERE i.sale_id = :sale_id
        ");
        $stmt->execute(['sale_id' => $saleId]);
        $viewStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'sale_id' => $saleId,
            'total_items' => (int) $basicStats['total_items'],
            'available_items' => (int) $basicStats['available_items'],
            'sold_items' => (int) $basicStats['sold_items'],
            'unique_buyers' => (int) $basicStats['unique_buyers'],
            'total_offers' => (int) $basicStats['total_offers'],
            'total_sales_amount' => (float) $basicStats['total_sales_amount'],
            'average_sale_price' => (float) $basicStats['average_sale_price'],
            'total_views' => (int) $viewStats['total_views'],
            'avg_views_per_item' => (float) $viewStats['avg_views_per_item'],
            'conversion_rate' => $basicStats['total_items'] > 0 
                ? round(($basicStats['sold_items'] / $basicStats['total_items']) * 100, 2) 
                : 0
        ];
    }

    public function updateStatistics(int $saleId): void
    {
        $stats = $this->getStatistics($saleId);
        
        $stmt = $this->pdo->prepare("
            UPDATE yfc_sales SET
                stats_cache = :stats,
                stats_updated_at = NOW()
            WHERE sale_id = :sale_id
        ");
        
        $stmt->execute([
            'sale_id' => $saleId,
            'stats' => json_encode($stats)
        ]);
    }

    public function generateUniqueAccessCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM yfc_sales WHERE access_code = :code");
            $stmt->execute(['code' => $code]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        
        return $code;
    }

    private function create(Sale $sale): Sale
    {
        // Generate access code if not set
        if (!$sale->getAccessCode()) {
            $sale->setAccessCode($this->generateUniqueAccessCode());
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO yfc_sales (
                seller_id, title, description, start_date, end_date,
                address, city, state, zip, latitude, longitude,
                access_code, claim_start, claim_end, pickup_start, pickup_end,
                qr_code, qr_code_path, status
            ) VALUES (
                :seller_id, :title, :description, :start_date, :end_date,
                :address, :city, :state, :zip, :latitude, :longitude,
                :access_code, :claim_start, :claim_end, :pickup_start, :pickup_end,
                :qr_code, :qr_code_path, :status
            )
        ");
        
        $stmt->execute($this->toArray($sale));
        $sale->setId((int) $this->pdo->lastInsertId());
        
        return $sale;
    }

    private function update(Sale $sale): Sale
    {
        $stmt = $this->pdo->prepare("
            UPDATE yfc_sales SET
                seller_id = :seller_id,
                title = :title,
                description = :description,
                start_date = :start_date,
                end_date = :end_date,
                address = :address,
                city = :city,
                state = :state,
                zip = :zip,
                latitude = :latitude,
                longitude = :longitude,
                access_code = :access_code,
                claim_start = :claim_start,
                claim_end = :claim_end,
                pickup_start = :pickup_start,
                pickup_end = :pickup_end,
                qr_code = :qr_code,
                qr_code_path = :qr_code_path,
                status = :status
            WHERE id = :id
        ");
        
        $data = $this->toArray($sale);
        $data['id'] = $sale->getId();
        
        $stmt->execute($data);
        
        return $sale;
    }

    private function hydrate(array $row): Sale
    {
        $sale = new Sale(
            sellerId: (int) $row['seller_id'],
            title: $row['title'],
            description: $row['description'] ?? '',
            startDate: new \DateTime($row['start_date']),
            endDate: new \DateTime($row['end_date']),
            address: $row['address'] ?? '',
            city: $row['city'] ?? '',
            state: $row['state'] ?? 'WA',
            zip: $row['zip'] ?? ''
        );
        
        $sale->setId((int) $row['id']);
        
        if ($row['latitude'] && $row['longitude']) {
            $sale->setCoordinates((float) $row['latitude'], (float) $row['longitude']);
        }
        
        if ($row['access_code']) {
            $sale->setAccessCode($row['access_code']);
        }
        
        // Determine phase based on dates
        $now = new \DateTime();
        if ($row['claim_start'] && $row['claim_end']) {
            $claimStart = new \DateTime($row['claim_start']);
            $claimEnd = new \DateTime($row['claim_end']);
            if ($now >= $claimStart && $now <= $claimEnd) {
                $sale->setPhase('claiming');
            }
        }
        
        $sale->setStatus($row['status'] ?? 'active');
        
        if ($row['claim_start']) {
            $sale->setPreviewStart(new \DateTime($row['claim_start']));
        }
        
        if ($row['claim_end']) {
            $sale->setBiddingEnd(new \DateTime($row['claim_end']));
        }
        
        if ($row['pickup_start']) {
            $sale->setPickupDate(new \DateTime($row['pickup_start']));
        }
        
        if ($row['qr_code_path']) {
            $sale->setQrCodeUrl($row['qr_code_path']);
        }
        
        if ($row['created_at']) {
            $sale->setCreatedAt(new \DateTime($row['created_at']));
        }
        
        if ($row['updated_at']) {
            $sale->setUpdatedAt(new \DateTime($row['updated_at']));
        }
        
        return $sale;
    }

    private function toArray(Sale $sale): array
    {
        return [
            'seller_id' => $sale->getSellerId(),
            'title' => $sale->getTitle(),
            'description' => $sale->getDescription(),
            'start_date' => $sale->getStartDate()->format('Y-m-d'),
            'end_date' => $sale->getEndDate()->format('Y-m-d'),
            'address' => $sale->getAddress(),
            'city' => $sale->getCity(),
            'state' => $sale->getState(),
            'zip' => $sale->getZip(),
            'latitude' => $sale->getLatitude(),
            'longitude' => $sale->getLongitude(),
            'access_code' => $sale->getAccessCode(),
            'claim_start' => $sale->getPreviewStart() ? $sale->getPreviewStart()->format('Y-m-d H:i:s') : null,
            'claim_end' => $sale->getBiddingEnd() ? $sale->getBiddingEnd()->format('Y-m-d H:i:s') : null,
            'pickup_start' => $sale->getPickupDate() ? $sale->getPickupDate()->format('Y-m-d H:i:s') : null,
            'pickup_end' => null, // Set based on business rules
            'qr_code' => null, // Generated separately
            'qr_code_path' => $sale->getQrCodeUrl(),
            'status' => $sale->getStatus(),
        ];
    }
}