<?php

declare(strict_types=1);

namespace YFEvents\Domain\Events;

use YFEvents\Domain\Common\RepositoryInterface;
use DateTimeInterface;

/**
 * Event repository interface with event-specific methods
 */
interface EventRepositoryInterface extends RepositoryInterface
{
    /**
     * Find events by date range
     */
    public function findByDateRange(DateTimeInterface $startDate, DateTimeInterface $endDate): array;

    /**
     * Find events by status
     */
    public function findByStatus(string $status): array;

    /**
     * Find featured events
     */
    public function findFeatured(int $limit = 10): array;

    /**
     * Find upcoming events
     */
    public function findUpcoming(int $limit = 10): array;

    /**
     * Find events near location
     */
    public function findNearLocation(float $latitude, float $longitude, float $radiusMiles = 10): array;

    /**
     * Find events by source
     */
    public function findBySource(int $sourceId): array;

    /**
     * Find events by external event ID
     */
    public function findByExternalEventId(string $externalEventId): ?Event;

    /**
     * Search events by text
     */
    public function search(string $query, array $filters = []): array;

    /**
     * Get events grouped by date
     */
    public function getEventsByDate(DateTimeInterface $date): array;

    /**
     * Count events by status
     */
    public function countByStatus(): array;
}