<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Claims;

use YFEvents\Domain\Claims\Seller;
use YFEvents\Domain\Claims\SellerRepositoryInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use PDO;

class SellerRepository implements SellerRepositoryInterface
{
    private PDO $pdo;

    public function __construct(ConnectionInterface $connection)
    {
        $this->pdo = $connection->getConnection();
    }

    public function findById(int $id): ?Seller
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                id, company_name, contact_name, email, phone, 
                address, city, state, zip, status, 
                created_at, updated_at
            FROM yfc_sellers 
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $data ? Seller::fromArray($data) : null;
    }

    public function findByUserId(int $userId): ?Seller
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM yfc_sellers WHERE auth_user_id = :user_id
        ');
        $stmt->execute(['user_id' => $userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $data ? Seller::fromArray($data) : null;
    }

    public function findByEmail(string $email): ?Seller
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                id, company_name, contact_name, email, phone, 
                address, city, state, zip, status, 
                created_at, updated_at
            FROM yfc_sellers 
            WHERE email = :email
        ');
        $stmt->execute(['email' => $email]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $data ? Seller::fromArray($data) : null;
    }

    public function findAll(?string $status = null): array
    {
        $sql = 'SELECT * FROM yfc_sellers';
        $params = [];
        
        if ($status !== null) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }
        
        $sql .= ' ORDER BY company_name ASC';
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $sellers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sellers[] = Seller::fromArray($row);
        }
        
        return $sellers;
    }

    public function findVerified(): array
    {
        return $this->findAll('verified');
    }

    public function save(Seller $seller): Seller
    {
        $data = $seller->toArray();
        
        // Map entity fields to database columns
        $dbData = [
            'company_name' => $data['company_name'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? 'WA',
            'zip' => $data['zip_code'] ?? null, // Map zip_code to zip
            'website' => $data['website'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'auth_user_id' => $data['user_id'] ?? null // Map user_id to auth_user_id
        ];
        
        if ($seller->getId()) {
            // Update existing
            $dbData['id'] = $seller->getId();
            $sql = 'UPDATE yfc_sellers SET 
                company_name = :company_name,
                contact_name = :contact_name,
                email = :email,
                phone = :phone,
                address = :address,
                city = :city,
                state = :state,
                zip = :zip,
                website = :website,
                status = :status,
                auth_user_id = :auth_user_id,
                updated_at = NOW()
                WHERE id = :id';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($dbData);
        } else {
            // Insert new
            $sql = 'INSERT INTO yfc_sellers (
                company_name, contact_name, email, phone, 
                address, city, state, zip, website, 
                status, auth_user_id, created_at
            ) VALUES (
                :company_name, :contact_name, :email, :phone,
                :address, :city, :state, :zip, :website,
                :status, :auth_user_id, NOW()
            )';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($dbData);
            
            $data['id'] = (int)$this->pdo->lastInsertId();
            return Seller::fromArray($data);
        }
        
        return $seller;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM yfc_sellers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM yfc_sellers WHERE status = :status
        ');
        $stmt->execute(['status' => $status]);
        
        return (int)$stmt->fetchColumn();
    }

    public function getStatistics(int $sellerId): array
    {
        // Get seller stats
        $stats = [];
        
        // Total sales
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM yfc_sales WHERE seller_id = :seller_id
        ');
        $stmt->execute(['seller_id' => $sellerId]);
        $stats['total_sales'] = (int)$stmt->fetchColumn();
        
        // Active sales
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM yfc_sales 
            WHERE seller_id = :seller_id 
            AND status = "active"
            AND claim_start <= NOW() 
            AND claim_end >= NOW()
        ');
        $stmt->execute(['seller_id' => $sellerId]);
        $stats['active_sales'] = (int)$stmt->fetchColumn();
        
        // Total items
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            WHERE s.seller_id = :seller_id
        ');
        $stmt->execute(['seller_id' => $sellerId]);
        $stats['total_items'] = (int)$stmt->fetchColumn();
        
        // Sold items (claimed)
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            WHERE s.seller_id = :seller_id
            AND i.status = "claimed"
        ');
        $stmt->execute(['seller_id' => $sellerId]);
        $stats['sold_items'] = (int)$stmt->fetchColumn();
        
        // Total offers - set to 0 since offers system was removed
        // TODO: Replace with inquiries count when contact system is implemented
        $stats['total_offers'] = 0;
        
        return $stats;
    }
}