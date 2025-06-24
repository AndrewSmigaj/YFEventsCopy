<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Claims;

use YFEvents\Domain\Claims\Seller;
use YFEvents\Domain\Claims\SellerRepositoryInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use PDO;
use PDOException;

class SellerRepository implements SellerRepositoryInterface
{
    private PDO $pdo;

    public function __construct(ConnectionInterface $connection)
    {
        $this->pdo = $connection->getConnection();
    }

    public function findById(int $id): ?Seller
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_sellers WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByUserId(int $userId): ?Seller
    {
        // Note: yfc_sellers doesn't have user_id, using username instead
        return null;
    }

    public function findByEmail(string $email): ?Seller
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_sellers WHERE email = :email
        ");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?string $status = null): array
    {
        $sql = "SELECT * FROM yfc_sellers";
        $params = [];
        
        if ($status !== null) {
            $sql .= " WHERE status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $sellers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sellers[] = $this->hydrate($row);
        }
        
        return $sellers;
    }

    public function findVerified(): array
    {
        return $this->findAll('verified');
    }

    public function save(Seller $seller): Seller
    {
        if ($seller->getId()) {
            return $this->update($seller);
        }
        
        return $this->create($seller);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM yfc_sellers WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM yfc_sellers WHERE status = :status
        ");
        $stmt->execute(['status' => $status]);
        
        return (int) $stmt->fetchColumn();
    }

    public function getStatistics(int $sellerId): array
    {
        $stats = [];
        
        // Total sales
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_sales,
                   SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_sales,
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_sales
            FROM yfc_sales 
            WHERE seller_id = :seller_id
        ");
        $stmt->execute(['seller_id' => $sellerId]);
        $salesStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Total items and offers
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT i.item_id) as total_items,
                   COUNT(DISTINCT o.offer_id) as total_offers,
                   SUM(CASE WHEN i.status = 'sold' THEN 1 ELSE 0 END) as items_sold,
                   COALESCE(SUM(CASE WHEN o.status = 'accepted' THEN o.offer_amount ELSE 0 END), 0) as total_revenue
            FROM yfc_sales s
            LEFT JOIN yfc_items i ON s.sale_id = i.sale_id
            LEFT JOIN yfc_offers o ON i.item_id = o.item_id
            WHERE s.seller_id = :seller_id
        ");
        $stmt->execute(['seller_id' => $sellerId]);
        $itemStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return array_merge(
            [
                'seller_id' => $sellerId,
                'total_sales' => (int) $salesStats['total_sales'],
                'active_sales' => (int) $salesStats['active_sales'],
                'completed_sales' => (int) $salesStats['completed_sales'],
            ],
            [
                'total_items' => (int) $itemStats['total_items'],
                'total_offers' => (int) $itemStats['total_offers'],
                'items_sold' => (int) $itemStats['items_sold'],
                'total_revenue' => (float) $itemStats['total_revenue'],
            ]
        );
    }

    private function create(Seller $seller): Seller
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO yfc_sellers (
                company_name, contact_name, email, phone,
                address, city, state, zip,
                website, username, password_hash, status
            ) VALUES (
                :company_name, :contact_name, :email, :phone,
                :address, :city, :state, :zip,
                :website, :username, :password_hash, :status
            )
        ");
        
        $stmt->execute($this->toArray($seller));
        $seller->setId((int) $this->pdo->lastInsertId());
        
        return $seller;
    }

    private function update(Seller $seller): Seller
    {
        $stmt = $this->pdo->prepare("
            UPDATE yfc_sellers SET
                company_name = :company_name,
                contact_name = :contact_name,
                email = :email,
                phone = :phone,
                address = :address,
                city = :city,
                state = :state,
                zip = :zip,
                website = :website,
                username = :username,
                status = :status
            WHERE id = :id
        ");
        
        $data = $this->toArray($seller);
        $data['id'] = $seller->getId();
        
        $stmt->execute($data);
        
        return $seller;
    }

    private function hydrate(array $row): Seller
    {
        $seller = new Seller(
            id: (int) $row['id'],
            userId: 0, // Not in database schema
            companyName: $row['company_name'],
            contactName: $row['contact_name'],
            email: $row['email'],
            phone: $row['phone'] ?? '',
            address: $row['address'],
            city: $row['city'],
            state: $row['state'] ?? 'WA',
            zipCode: $row['zip'],
            website: $row['website'],
            description: null, // Not in database schema
            logo: null, // Not in database schema
            status: $row['status'] ?? 'pending',
            settings: [], // Not in database schema
            paymentMethods: [], // Not in database schema
            verifiedAt: $row['email_verified'] ? new \DateTime() : null,
            createdAt: new \DateTime($row['created_at'] ?? 'now'),
            updatedAt: $row['updated_at'] ? new \DateTime($row['updated_at']) : null
        );
        
        return $seller;
    }

    private function toArray(Seller $seller): array
    {
        return [
            'company_name' => $seller->getCompanyName(),
            'contact_name' => $seller->getContactName(),
            'email' => $seller->getEmail(),
            'phone' => $seller->getPhone(),
            'address' => $seller->getAddress(),
            'city' => $seller->getCity(),
            'state' => $seller->getState(),
            'zip' => $seller->getZipCode(),
            'website' => $seller->getWebsite(),
            'username' => null, // Will be set during registration
            'password_hash' => '', // Will be set during registration
            'status' => $seller->getStatus(),
        ];
    }
}