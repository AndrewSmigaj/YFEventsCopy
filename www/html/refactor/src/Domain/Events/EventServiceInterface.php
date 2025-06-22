<?php

declare(strict_types=1);

namespace YFEvents\Domain\Events;

use YFEvents\Domain\Common\ServiceInterface;
use DateTimeInterface;

/**
 * Event service interface for business logic
 */
interface EventServiceInterface extends ServiceInterface
{
    /**
     * Create a new event
     */
    public function createEvent(array $eventData): Event;

    /**
     * Update an existing event
     */
    public function updateEvent(int $eventId, array $updateData): Event;

    /**
     * Delete an event
     */
    public function deleteEvent(int $eventId): bool;

    /**
     * Get event by ID
     */
    public function getEventById(int $eventId): ?Event;

    /**
     * Get events for calendar display
     */
    public function getEventsForCalendar(DateTimeInterface $startDate, DateTimeInterface $endDate, array $filters = []): array;

    /**
     * Search events
     */
    public function searchEvents(string $query, array $filters = []): array;

    /**
     * Approve event
     */
    public function approveEvent(int $eventId): Event;

    /**
     * Reject event
     */
    public function rejectEvent(int $eventId): Event;

    /**
     * Get featured events
     */
    public function getFeaturedEvents(int $limit = 10): array;

    /**
     * Get upcoming events
     */
    public function getUpcomingEvents(int $limit = 10): array;

    /**
     * Get events near location
     */
    public function getEventsNearLocation(float $latitude, float $longitude, float $radiusMiles = 10): array;

    /**
     * Bulk approve events
     */
    public function bulkApproveEvents(array $eventIds): array;

    /**
     * Bulk reject events
     */
    public function bulkRejectEvents(array $eventIds): array;

    /**
     * Get event statistics
     */
    public function getEventStatistics(): array;

    /**
     * Validate event data
     */
    public function validateEventData(array $eventData): array;
}