<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Claims;

interface SellerRepositoryInterface
{
    /**
     * Find seller by ID
     */
    public function findById(int $id): ?Seller;

    /**
     * Find seller by user ID
     */
    public function findByUserId(int $userId): ?Seller;

    /**
     * Find seller by email
     */
    public function findByEmail(string $email): ?Seller;

    /**
     * Find all sellers
     */
    public function findAll(?string $status = null): array;

    /**
     * Find verified sellers
     */
    public function findVerified(): array;

    /**
     * Save seller (create or update)
     */
    public function save(Seller $seller): Seller;

    /**
     * Delete seller
     */
    public function delete(int $id): void;

    /**
     * Count sellers by status
     */
    public function countByStatus(string $status): int;

    /**
     * Get seller statistics
     */
    public function getStatistics(int $sellerId): array;
}