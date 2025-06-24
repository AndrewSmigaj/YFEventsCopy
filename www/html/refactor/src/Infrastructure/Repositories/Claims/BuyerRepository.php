<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Claims;

use YFEvents\Domain\Claims\Buyer;
use YFEvents\Domain\Claims\BuyerRepositoryInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use PDO;
use PDOException;

class BuyerRepository implements BuyerRepositoryInterface
{
    private PDO $pdo;

    public function __construct(ConnectionInterface $connection)
    {
        $this->pdo = $connection->getConnection();
    }

    public function findById(int $id): ?Buyer
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_buyers WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?Buyer
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_buyers WHERE email = :email
        ");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByPhone(string $phone): ?Buyer
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_buyers WHERE phone = :phone
        ");
        $stmt->execute(['phone' => $phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findByAuthToken(string $token): ?Buyer
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_buyers 
            WHERE session_token = :token
            AND session_expires > NOW()
        ");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $this->hydrate($row) : null;
    }

    public function findExpiredTokens(int $hoursOld = 24): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_buyers 
            WHERE session_token IS NOT NULL
            AND session_expires < DATE_SUB(NOW(), INTERVAL :hours HOUR)
        ");
        $stmt->bindValue(':hours', $hoursOld, PDO::PARAM_INT);
        $stmt->execute();
        
        $buyers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $buyers[] = $this->hydrate($row);
        }
        
        return $buyers;
    }

    public function save(Buyer $buyer): Buyer
    {
        if ($buyer->getId()) {
            return $this->update($buyer);
        }
        
        return $this->create($buyer);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM yfc_buyers WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function cleanupExpired(int $daysOld = 30): int
    {
        // Delete buyers who haven't been active and have no offers
        $stmt = $this->pdo->prepare("
            DELETE b FROM yfc_buyers b
            LEFT JOIN yfc_offers o ON b.buyer_id = o.buyer_id
            WHERE o.offer_id IS NULL
            AND b.last_activity < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        
        $stmt->bindValue(':days', $daysOld, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    public function countByAuthMethod(string $method): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM yfc_buyers 
            WHERE auth_method = :method
        ");
        $stmt->execute(['method' => $method]);
        
        return (int) $stmt->fetchColumn();
    }

    private function create(Buyer $buyer): Buyer
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO yfc_buyers (
                email, phone, name, auth_method, verification_code,
                verified_at, session_token, session_expires,
                sale_id, auth_verified
            ) VALUES (
                :email, :phone, :name, :auth_method, :verification_code,
                :verified_at, :session_token, :session_expires,
                :sale_id, :auth_verified
            )
        ");
        
        $stmt->execute($this->toArray($buyer));
        $buyer->setId((int) $this->pdo->lastInsertId());
        
        return $buyer;
    }

    private function update(Buyer $buyer): Buyer
    {
        $stmt = $this->pdo->prepare("
            UPDATE yfc_buyers SET
                email = :email,
                phone = :phone,
                name = :name,
                auth_method = :auth_method,
                verification_code = :verification_code,
                verified_at = :verified_at,
                session_token = :session_token,
                session_expires = :session_expires,
                sale_id = :sale_id,
                auth_verified = :auth_verified,
                last_active = NOW()
            WHERE id = :id
        ");
        
        $data = $this->toArray($buyer);
        $data['id'] = $buyer->getId();
        
        $stmt->execute($data);
        
        return $buyer;
    }

    private function hydrate(array $row): Buyer
    {
        $authMethod = $row['auth_method'] === 'email' ? 'email' : 'phone';
        $authValue = $authMethod === 'email' ? $row['email'] : $row['phone'];
        
        $buyer = new Buyer(
            authMethod: $authMethod,
            authValue: $authValue ?? ''
        );
        
        $buyer->setId((int) $row['id']);
        
        if ($row['email']) {
            $buyer->setEmail($row['email']);
        }
        
        if ($row['phone']) {
            $buyer->setPhone($row['phone']);
        }
        
        if ($row['name']) {
            $buyer->setName($row['name']);
        }
        
        if ($row['verification_code']) {
            $buyer->setAuthCode($row['verification_code']);
        }
        
        if ($row['verified_at']) {
            $buyer->setAuthCodeExpires(new \DateTime($row['verified_at']));
        }
        
        if ($row['session_token']) {
            $buyer->setAuthToken($row['session_token']);
        }
        
        if ($row['session_expires']) {
            $buyer->setAuthTokenExpires(new \DateTime($row['session_expires']));
        }
        
        // Store sale_id and auth_verified in preferences
        $preferences = [];
        if ($row['sale_id']) {
            $preferences['sale_id'] = (int) $row['sale_id'];
        }
        if ($row['auth_verified'] !== null) {
            $preferences['auth_verified'] = (bool) $row['auth_verified'];
        }
        $buyer->setPreferences($preferences);
        
        if ($row['last_active']) {
            $buyer->setLastActivity(new \DateTime($row['last_active']));
        }
        
        if ($row['created_at']) {
            $buyer->setCreatedAt(new \DateTime($row['created_at']));
        }
        
        if ($row['last_activity']) {
            $buyer->setUpdatedAt(new \DateTime($row['last_activity']));
        }
        
        return $buyer;
    }

    private function toArray(Buyer $buyer): array
    {
        $preferences = $buyer->getPreferences();
        
        return [
            'email' => $buyer->getEmail(),
            'phone' => $buyer->getPhone(),
            'name' => $buyer->getName(),
            'auth_method' => $buyer->getAuthMethod(),
            'verification_code' => $buyer->getAuthCode(),
            'verified_at' => $buyer->getAuthCodeExpires() ? $buyer->getAuthCodeExpires()->format('Y-m-d H:i:s') : null,
            'session_token' => $buyer->getAuthToken(),
            'session_expires' => $buyer->getAuthTokenExpires() ? $buyer->getAuthTokenExpires()->format('Y-m-d H:i:s') : null,
            'sale_id' => $preferences['sale_id'] ?? null,
            'auth_verified' => $preferences['auth_verified'] ?? 0,
        ];
    }
}