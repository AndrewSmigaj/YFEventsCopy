<?php

declare(strict_types=1);

namespace YFEvents\Domain\Claims;

interface SaleRepositoryInterface
{
    /**
     * Find sale by ID
     */
    public function findById(int $id): ?Sale;

    /**
     * Find sale by access code
     */
    public function findByAccessCode(string $accessCode): ?Sale;

    /**
     * Find sales by seller ID
     */
    public function findBySellerId(int $sellerId, ?string $status = null): array;

    /**
     * Find active sales
     */
    public function findActive(int $limit = 20, int $offset = 0): array;

    /**
     * Find sales by phase
     */
    public function findByPhase(string $phase, int $limit = 20, int $offset = 0): array;

    /**
     * Find sales near location
     */
    public function findNearLocation(float $latitude, float $longitude, float $radiusMiles = 25): array;

    /**
     * Find upcoming sales
     */
    public function findUpcoming(int $days = 7): array;

    /**
     * Save sale (create or update)
     */
    public function save(Sale $sale): Sale;

    /**
     * Delete sale
     */
    public function delete(int $id): void;

    /**
     * Count sales by seller
     */
    public function countBySeller(int $sellerId, ?string $status = null): int;

    /**
     * Get sale statistics
     */
    public function getStatistics(int $saleId): array;

    /**
     * Update sale statistics
     */
    public function updateStatistics(int $saleId): void;

    /**
     * Generate unique access code
     */
    public function generateUniqueAccessCode(): string;

    /**
     * Find sales by date range
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array;
}