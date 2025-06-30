<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Scrapers;

interface ScrapingSourceRepositoryInterface
{
    /**
     * Find source by ID
     */
    public function findById(int $id): ?ScrapingSource;

    /**
     * Find all active sources
     */
    public function findActive(): array;

    /**
     * Find sources by type
     */
    public function findByType(string $type): array;

    /**
     * Find sources that need scraping
     */
    public function findDue(): array;

    /**
     * Find sources by priority
     */
    public function findByPriority(int $priority): array;

    /**
     * Find unhealthy sources
     */
    public function findUnhealthy(): array;

    /**
     * Save source (create or update)
     */
    public function save(ScrapingSource $source): ScrapingSource;

    /**
     * Delete source
     */
    public function delete(int $id): void;

    /**
     * Get all source types
     */
    public function getSourceTypes(): array;

    /**
     * Get source statistics
     */
    public function getStatistics(): array;

    /**
     * Update source statistics
     */
    public function updateStatistics(int $sourceId, array $stats): void;
}