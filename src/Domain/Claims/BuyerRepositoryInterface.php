<?php

declare(strict_types=1);

namespace YFEvents\Domain\Claims;

interface BuyerRepositoryInterface
{
    /**
     * Find buyer by ID
     */
    public function findById(int $id): ?Buyer;

    /**
     * Find buyer by email
     */
    public function findByEmail(string $email): ?Buyer;

    /**
     * Find buyer by phone
     */
    public function findByPhone(string $phone): ?Buyer;

    /**
     * Find buyer by auth token
     */
    public function findByAuthToken(string $token): ?Buyer;

    /**
     * Save buyer (create or update)
     */
    public function save(Buyer $buyer): Buyer;

    /**
     * Delete buyer
     */
    public function delete(int $id): void;

    /**
     * Find buyers with expired tokens
     */
    public function findExpiredTokens(int $hoursOld = 24): array;

    /**
     * Clean up expired buyers
     */
    public function cleanupExpired(int $daysOld = 30): int;

    /**
     * Count buyers by auth method
     */
    public function countByAuthMethod(string $method): int;
}