<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Claims;

use YFEvents\Domain\Claims\Offer;
use YFEvents\Domain\Claims\OfferRepositoryInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use PDO;
use PDOException;

class OfferRepository implements OfferRepositoryInterface
{
    private PDO $pdo;

    public function __construct(ConnectionInterface $connection)
    {
        $this->pdo = $connection->getConnection();
    }

    public function findById(int $id): ?Offer
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_offers WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByItemId(int $itemId, ?string $status = null): array
    {
        $sql = "SELECT * FROM yfc_offers WHERE item_id = :item_id";
        $params = ['item_id' => $itemId];
        
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY offer_amount DESC, created_at ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $offers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $offers[] = $this->hydrate($row);
        }
        
        return $offers;
    }

    public function findByBuyerId(int $buyerId, ?string $status = null): array
    {
        $sql = "SELECT * FROM yfc_offers WHERE buyer_id = :buyer_id";
        $params = ['buyer_id' => $buyerId];
        
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $offers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $offers[] = $this->hydrate($row);
        }
        
        return $offers;
    }

    public function findBySaleId(int $saleId, ?string $status = null): array
    {
        $sql = "
            SELECT o.* FROM yfc_offers o
            INNER JOIN yfc_items i ON o.item_id = i.item_id
            WHERE i.sale_id = :sale_id
        ";
        
        $params = ['sale_id' => $saleId];
        
        if ($status !== null) {
            $sql .= " AND o.status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $offers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $offers[] = $this->hydrate($row);
        }
        
        return $offers;
    }

    public function findWinningOffersBySale(int $saleId): array
    {
        return $this->findBySaleId($saleId, 'accepted');
    }

    public function findTopOffersForItem(int $itemId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_offers 
            WHERE item_id = :item_id
            AND status IN ('pending', 'accepted')
            ORDER BY offer_amount DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':item_id', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $offers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $offers[] = $this->hydrate($row);
        }
        
        return $offers;
    }

    public function getHighestOfferForItem(int $itemId): ?Offer
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_offers 
            WHERE item_id = :item_id
            AND status IN ('pending', 'accepted')
            ORDER BY offer_amount DESC
            LIMIT 1
        ");
        
        $stmt->execute(['item_id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function save(Offer $offer): Offer
    {
        if ($offer->getId()) {
            return $this->update($offer);
        }
        
        return $this->create($offer);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM yfc_offers WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function deleteByItem(int $itemId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM yfc_offers WHERE item_id = :item_id");
        $stmt->execute(['item_id' => $itemId]);
    }

    public function countByItem(int $itemId, ?string $status = null): int
    {
        $sql = "SELECT COUNT(*) FROM yfc_offers WHERE item_id = :item_id";
        $params = ['item_id' => $itemId];
        
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }

    public function countByBuyer(int $buyerId, ?string $status = null): int
    {
        $sql = "SELECT COUNT(*) FROM yfc_offers WHERE buyer_id = :buyer_id";
        $params = ['buyer_id' => $buyerId];
        
        if ($status !== null) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }

    public function getSaleStatistics(int $saleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT o.offer_id) as total_offers,
                COUNT(DISTINCT o.buyer_id) as unique_buyers,
                COUNT(DISTINCT o.item_id) as items_with_offers,
                SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_offers,
                SUM(CASE WHEN o.status = 'accepted' THEN 1 ELSE 0 END) as accepted_offers,
                SUM(CASE WHEN o.status = 'rejected' THEN 1 ELSE 0 END) as rejected_offers,
                SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_offers,
                MAX(o.offer_amount) as highest_offer,
                MIN(o.offer_amount) as lowest_offer,
                AVG(o.offer_amount) as average_offer,
                SUM(CASE WHEN o.status = 'accepted' THEN o.offer_amount ELSE 0 END) as total_accepted_amount
            FROM yfc_offers o
            INNER JOIN yfc_items i ON o.item_id = i.item_id
            WHERE i.sale_id = :sale_id
        ");
        
        $stmt->execute(['sale_id' => $saleId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'sale_id' => $saleId,
            'total_offers' => (int) $stats['total_offers'],
            'unique_buyers' => (int) $stats['unique_buyers'],
            'items_with_offers' => (int) $stats['items_with_offers'],
            'pending_offers' => (int) $stats['pending_offers'],
            'accepted_offers' => (int) $stats['accepted_offers'],
            'rejected_offers' => (int) $stats['rejected_offers'],
            'cancelled_offers' => (int) $stats['cancelled_offers'],
            'highest_offer' => (float) $stats['highest_offer'],
            'lowest_offer' => (float) $stats['lowest_offer'],
            'average_offer' => (float) $stats['average_offer'],
            'total_accepted_amount' => (float) $stats['total_accepted_amount']
        ];
    }

    public function buyerHasOfferOnItem(int $buyerId, int $itemId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM yfc_offers 
            WHERE buyer_id = :buyer_id 
            AND item_id = :item_id
            AND status NOT IN ('cancelled', 'expired')
        ");
        
        $stmt->execute([
            'buyer_id' => $buyerId,
            'item_id' => $itemId
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    private function create(Offer $offer): Offer
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO yfc_offers (
                item_id, buyer_id, offer_amount, max_offer,
                message, seller_notes, status, payment_status
            ) VALUES (
                :item_id, :buyer_id, :offer_amount, :max_offer,
                :message, :seller_notes, :status, :payment_status
            )
        ");
        
        $stmt->execute($this->toArray($offer));
        $offer->setId((int) $this->pdo->lastInsertId());
        
        return $offer;
    }

    private function update(Offer $offer): Offer
    {
        $stmt = $this->pdo->prepare("
            UPDATE yfc_offers SET
                item_id = :item_id,
                buyer_id = :buyer_id,
                offer_amount = :offer_amount,
                max_offer = :max_offer,
                message = :message,
                seller_notes = :seller_notes,
                status = :status,
                payment_status = :payment_status
            WHERE id = :id
        ");
        
        $data = $this->toArray($offer);
        $data['id'] = $offer->getId();
        
        $stmt->execute($data);
        
        return $offer;
    }

    private function hydrate(array $row): Offer
    {
        $offer = new Offer(
            itemId: (int) $row['item_id'],
            buyerId: (int) $row['buyer_id'],
            offerAmount: (float) $row['offer_amount']
        );
        
        $offer->setId((int) $row['id']);
        
        if ($row['max_offer'] !== null) {
            $offer->setMaxAmount((float) $row['max_offer']);
        }
        
        if ($row['message']) {
            $offer->setMessage($row['message']);
        }
        
        $offer->setStatus($row['status'] ?? 'active');
        
        // Store additional data in metadata
        $metadata = [];
        if ($row['seller_notes']) {
            $metadata['seller_notes'] = $row['seller_notes'];
        }
        if ($row['payment_status']) {
            $metadata['payment_status'] = $row['payment_status'];
        }
        if ($row['payment_method']) {
            $metadata['payment_method'] = $row['payment_method'];
        }
        if ($row['transaction_id']) {
            $metadata['transaction_id'] = $row['transaction_id'];
        }
        if ($row['accepted_at']) {
            $metadata['accepted_at'] = $row['accepted_at'];
        }
        $offer->setMetadata($metadata);
        
        if ($row['created_at']) {
            $offer->setCreatedAt(new \DateTime($row['created_at']));
        }
        
        if ($row['updated_at']) {
            $offer->setUpdatedAt(new \DateTime($row['updated_at']));
        }
        
        return $offer;
    }

    private function toArray(Offer $offer): array
    {
        $metadata = $offer->getMetadata();
        
        return [
            'item_id' => $offer->getItemId(),
            'buyer_id' => $offer->getBuyerId(),
            'offer_amount' => $offer->getOfferAmount(),
            'max_offer' => $offer->getMaxAmount(),
            'message' => $offer->getMessage(),
            'seller_notes' => $metadata['seller_notes'] ?? null,
            'status' => $offer->getStatus(),
            'payment_status' => $metadata['payment_status'] ?? 'pending',
        ];
    }
}