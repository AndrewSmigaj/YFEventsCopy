<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Events;

use YakimaFinds\Infrastructure\Database\ConnectionInterface;
use DateTimeInterface;
use DateTime;
use InvalidArgumentException;
use RuntimeException;

/**
 * Event service implementation
 */
class EventService implements EventServiceInterface
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private ConnectionInterface $connection
    ) {}

    public function createEvent(array $eventData): Event
    {
        $validationErrors = $this->validateEventData($eventData);
        if (!empty($validationErrors)) {
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $validationErrors));
        }

        // Create event entity
        $event = new Event(
            id: null,
            title: $eventData['title'],
            description: $eventData['description'] ?? null,
            startDateTime: new DateTime($eventData['start_datetime']),
            endDateTime: isset($eventData['end_datetime']) ? new DateTime($eventData['end_datetime']) : null,
            location: $eventData['location'] ?? null,
            address: $eventData['address'] ?? null,
            latitude: isset($eventData['latitude']) ? (float) $eventData['latitude'] : null,
            longitude: isset($eventData['longitude']) ? (float) $eventData['longitude'] : null,
            contactInfo: $eventData['contact_info'] ?? null,
            externalUrl: $eventData['external_url'] ?? null,
            sourceId: isset($eventData['source_id']) ? (int) $eventData['source_id'] : null,
            cmsUserId: isset($eventData['cms_user_id']) ? (int) $eventData['cms_user_id'] : null,
            status: $eventData['status'] ?? 'pending',
            featured: (bool) ($eventData['featured'] ?? false),
            externalEventId: $eventData['external_event_id'] ?? null
        );

        return $this->eventRepository->save($event);
    }

    public function updateEvent(int $eventId, array $updateData): Event
    {
        $event = $this->eventRepository->findById($eventId);
        if (!$event instanceof Event) {
            throw new RuntimeException("Event not found: {$eventId}");
        }

        $validationErrors = $this->validateEventData($updateData, false);
        if (!empty($validationErrors)) {
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $validationErrors));
        }

        // Update event
        $event->update(
            title: $updateData['title'] ?? null,
            description: $updateData['description'] ?? null,
            startDateTime: isset($updateData['start_datetime']) ? new DateTime($updateData['start_datetime']) : null,
            endDateTime: isset($updateData['end_datetime']) ? new DateTime($updateData['end_datetime']) : null,
            location: $updateData['location'] ?? null,
            address: $updateData['address'] ?? null,
            latitude: isset($updateData['latitude']) ? (float) $updateData['latitude'] : null,
            longitude: isset($updateData['longitude']) ? (float) $updateData['longitude'] : null,
            contactInfo: $updateData['contact_info'] ?? null,
            externalUrl: $updateData['external_url'] ?? null,
            status: $updateData['status'] ?? null,
            featured: isset($updateData['featured']) ? (bool) $updateData['featured'] : null
        );

        return $this->eventRepository->save($event);
    }

    public function deleteEvent(int $eventId): bool
    {
        $event = $this->eventRepository->findById($eventId);
        if (!$event instanceof Event) {
            return false;
        }

        return $this->eventRepository->delete($event);
    }

    public function getEventById(int $eventId): ?Event
    {
        $result = $this->eventRepository->findById($eventId);
        return $result instanceof Event ? $result : null;
    }

    public function getEventsForCalendar(DateTimeInterface $startDate, DateTimeInterface $endDate, array $filters = []): array
    {
        // Add status filter if not specified
        if (!isset($filters['status'])) {
            $filters['status'] = 'approved';
        }

        $events = $this->eventRepository->findByDateRange($startDate, $endDate);
        
        // Apply additional filters
        return array_filter($events, function (Event $event) use ($filters) {
            if (isset($filters['status']) && $event->getStatus() !== $filters['status']) {
                return false;
            }
            
            if (isset($filters['featured']) && $event->isFeatured() !== $filters['featured']) {
                return false;
            }
            
            if (isset($filters['source_id']) && $event->getSourceId() !== $filters['source_id']) {
                return false;
            }
            
            return true;
        });
    }

    public function searchEvents(string $query, array $filters = []): array
    {
        return $this->eventRepository->search($query, $filters);
    }

    public function approveEvent(int $eventId): Event
    {
        $event = $this->eventRepository->findById($eventId);
        if (!$event instanceof Event) {
            throw new RuntimeException("Event not found: {$eventId}");
        }

        $event->approve();
        return $this->eventRepository->save($event);
    }

    public function rejectEvent(int $eventId): Event
    {
        $event = $this->eventRepository->findById($eventId);
        if (!$event instanceof Event) {
            throw new RuntimeException("Event not found: {$eventId}");
        }

        $event->reject();
        return $this->eventRepository->save($event);
    }

    public function getFeaturedEvents(int $limit = 10): array
    {
        return $this->eventRepository->findFeatured($limit);
    }

    public function getUpcomingEvents(int $limit = 10): array
    {
        return $this->eventRepository->findUpcoming($limit);
    }

    public function getEventsNearLocation(float $latitude, float $longitude, float $radiusMiles = 10): array
    {
        return $this->eventRepository->findNearLocation($latitude, $longitude, $radiusMiles);
    }

    public function bulkApproveEvents(array $eventIds): array
    {
        $results = [];
        
        $this->connection->beginTransaction();
        try {
            foreach ($eventIds as $eventId) {
                $results[] = $this->approveEvent($eventId);
            }
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }

        return $results;
    }

    public function bulkRejectEvents(array $eventIds): array
    {
        $results = [];
        
        $this->connection->beginTransaction();
        try {
            foreach ($eventIds as $eventId) {
                $results[] = $this->rejectEvent($eventId);
            }
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }

        return $results;
    }

    public function getEventStatistics(): array
    {
        $statusCounts = $this->eventRepository->countByStatus();
        $upcomingCount = count($this->eventRepository->findUpcoming(1000)); // Rough count
        $featuredCount = count($this->eventRepository->findFeatured(1000)); // Rough count

        return [
            'total' => array_sum($statusCounts),
            'by_status' => $statusCounts,
            'upcoming' => $upcomingCount,
            'featured' => $featuredCount,
            'approved' => $statusCounts['approved'] ?? 0,
            'pending' => $statusCounts['pending'] ?? 0,
            'rejected' => $statusCounts['rejected'] ?? 0,
        ];
    }

    public function validateEventData(array $eventData, bool $requireRequired = true): array
    {
        $errors = [];

        // Required fields for creation
        if ($requireRequired) {
            if (empty($eventData['title'])) {
                $errors[] = 'Title is required';
            }
            
            if (empty($eventData['start_datetime'])) {
                $errors[] = 'Start date/time is required';
            }
        }

        // Validate title length
        if (isset($eventData['title']) && strlen($eventData['title']) > 255) {
            $errors[] = 'Title must be 255 characters or less';
        }

        // Validate start datetime
        if (isset($eventData['start_datetime'])) {
            try {
                new DateTime($eventData['start_datetime']);
            } catch (\Exception $e) {
                $errors[] = 'Invalid start date/time format';
            }
        }

        // Validate end datetime
        if (isset($eventData['end_datetime'])) {
            try {
                $endDateTime = new DateTime($eventData['end_datetime']);
                if (isset($eventData['start_datetime'])) {
                    $startDateTime = new DateTime($eventData['start_datetime']);
                    if ($endDateTime < $startDateTime) {
                        $errors[] = 'End date/time must be after start date/time';
                    }
                }
            } catch (\Exception $e) {
                $errors[] = 'Invalid end date/time format';
            }
        }

        // Validate coordinates
        if (isset($eventData['latitude'])) {
            $lat = (float) $eventData['latitude'];
            if ($lat < -90 || $lat > 90) {
                $errors[] = 'Latitude must be between -90 and 90';
            }
        }

        if (isset($eventData['longitude'])) {
            $lng = (float) $eventData['longitude'];
            if ($lng < -180 || $lng > 180) {
                $errors[] = 'Longitude must be between -180 and 180';
            }
        }

        // Validate status
        if (isset($eventData['status'])) {
            $validStatuses = ['pending', 'approved', 'rejected'];
            if (!in_array($eventData['status'], $validStatuses)) {
                $errors[] = 'Status must be one of: ' . implode(', ', $validStatuses);
            }
        }

        // Validate URL
        if (isset($eventData['external_url']) && !empty($eventData['external_url'])) {
            if (!filter_var($eventData['external_url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'External URL must be a valid URL';
            }
        }

        return $errors;
    }
}