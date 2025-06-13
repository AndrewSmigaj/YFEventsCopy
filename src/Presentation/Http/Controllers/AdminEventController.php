<?php

declare(strict_types=1);

namespace YakimaFinds\Presentation\Http\Controllers;

use YakimaFinds\Domain\Events\EventServiceInterface;
use YakimaFinds\Infrastructure\Container\ContainerInterface;
use YakimaFinds\Infrastructure\Config\ConfigInterface;
use DateTime;
use Exception;

/**
 * Admin event controller for event management
 */
class AdminEventController extends BaseController
{
    private EventServiceInterface $eventService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->eventService = $container->resolve(EventServiceInterface::class);
    }

    /**
     * Get all events with admin privileges
     */
    public function getAllEvents(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $pagination = $this->getPaginationParams($input);
            
            // Build filters for admin view
            $filters = [];
            if (isset($input['status']) && $input['status'] !== 'all') {
                $filters['status'] = $input['status'];
            }
            if (isset($input['featured'])) {
                $filters['featured'] = filter_var($input['featured'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['source_id'])) {
                $filters['source_id'] = (int) $input['source_id'];
            }
            if (isset($input['start_date'])) {
                $filters['start_date'] = $input['start_date'];
            }
            if (isset($input['end_date'])) {
                $filters['end_date'] = $input['end_date'];
            }

            // For admin, we can search all events
            $filters['limit'] = $pagination['limit'];
            $query = $input['search'] ?? '';

            $events = $this->eventService->searchEvents($query, $filters);

            // Format events with admin details
            $formattedEvents = array_map(function ($event) {
                return [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'description' => $event->getDescription(),
                    'start_datetime' => $event->getStartDateTime()->format('Y-m-d H:i:s'),
                    'end_datetime' => $event->getEndDateTime()?->format('Y-m-d H:i:s'),
                    'location' => $event->getLocation(),
                    'address' => $event->getAddress(),
                    'latitude' => $event->getLatitude(),
                    'longitude' => $event->getLongitude(),
                    'contact_info' => $event->getContactInfo(),
                    'external_url' => $event->getExternalUrl(),
                    'source_id' => $event->getSourceId(),
                    'cms_user_id' => $event->getCmsUserId(),
                    'status' => $event->getStatus(),
                    'featured' => $event->isFeatured(),
                    'external_event_id' => $event->getExternalEventId(),
                    'created_at' => $event->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updated_at' => $event->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    'is_upcoming' => $event->isUpcoming(),
                    'is_happening' => $event->isHappening(),
                ];
            }, $events);

            $this->successResponse([
                'events' => $formattedEvents,
                'count' => count($formattedEvents),
                'pagination' => $pagination,
                'filters' => $filters
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load events: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new event
     */
    public function createEvent(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            $missing = $this->validateRequired($input, ['title', 'start_datetime']);
            if (!empty($missing)) {
                $this->errorResponse('Missing required fields: ' . implode(', ', $missing));
                return;
            }

            // Admin can set all fields including status
            $eventData = [
                'title' => $input['title'],
                'description' => $input['description'] ?? null,
                'start_datetime' => $input['start_datetime'],
                'end_datetime' => $input['end_datetime'] ?? null,
                'location' => $input['location'] ?? null,
                'address' => $input['address'] ?? null,
                'latitude' => isset($input['latitude']) ? (float) $input['latitude'] : null,
                'longitude' => isset($input['longitude']) ? (float) $input['longitude'] : null,
                'contact_info' => $input['contact_info'] ?? null,
                'external_url' => $input['external_url'] ?? null,
                'source_id' => isset($input['source_id']) ? (int) $input['source_id'] : null,
                'cms_user_id' => $_SESSION['user_id'] ?? null,
                'status' => $input['status'] ?? 'approved',
                'featured' => filter_var($input['featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'external_event_id' => $input['external_event_id'] ?? null,
            ];

            $event = $this->eventService->createEvent($eventData);

            $this->successResponse([
                'event_id' => $event->getId(),
                'status' => $event->getStatus()
            ], 'Event created successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to create event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing event
     */
    public function updateEvent(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Event ID is required');
                return;
            }

            $eventId = (int) $input['id'];
            unset($input['id']); // Remove ID from update data

            // Build update data from input
            $updateData = array_filter($input, function($value) {
                return $value !== null && $value !== '';
            });

            // Handle boolean fields
            if (isset($updateData['featured'])) {
                $updateData['featured'] = filter_var($updateData['featured'], FILTER_VALIDATE_BOOLEAN);
            }

            $event = $this->eventService->updateEvent($eventId, $updateData);

            $this->successResponse([
                'event_id' => $event->getId(),
                'status' => $event->getStatus()
            ], 'Event updated successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to update event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete an event
     */
    public function deleteEvent(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Event ID is required');
                return;
            }

            $eventId = (int) $input['id'];
            $success = $this->eventService->deleteEvent($eventId);

            if ($success) {
                $this->successResponse([], 'Event deleted successfully');
            } else {
                $this->errorResponse('Event not found or could not be deleted', 404);
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to delete event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve an event
     */
    public function approveEvent(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Event ID is required');
                return;
            }

            $eventId = (int) $input['id'];
            $event = $this->eventService->approveEvent($eventId);

            $this->successResponse([
                'event_id' => $event->getId(),
                'status' => $event->getStatus()
            ], 'Event approved successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to approve event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject an event
     */
    public function rejectEvent(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Event ID is required');
                return;
            }

            $eventId = (int) $input['id'];
            $event = $this->eventService->rejectEvent($eventId);

            $this->successResponse([
                'event_id' => $event->getId(),
                'status' => $event->getStatus()
            ], 'Event rejected successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to reject event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk approve events
     */
    public function bulkApproveEvents(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['event_ids']) || !is_array($input['event_ids'])) {
                $this->errorResponse('Event IDs array is required');
                return;
            }

            $eventIds = array_map('intval', $input['event_ids']);
            $events = $this->eventService->bulkApproveEvents($eventIds);

            $this->successResponse([
                'approved_count' => count($events),
                'event_ids' => array_map(fn($event) => $event->getId(), $events)
            ], 'Events approved successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to bulk approve events: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk reject events
     */
    public function bulkRejectEvents(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->errorResponse('POST method required', 405);
                return;
            }

            $input = $this->getInput();
            
            if (!isset($input['event_ids']) || !is_array($input['event_ids'])) {
                $this->errorResponse('Event IDs array is required');
                return;
            }

            $eventIds = array_map('intval', $input['event_ids']);
            $events = $this->eventService->bulkRejectEvents($eventIds);

            $this->successResponse([
                'rejected_count' => count($events),
                'event_ids' => array_map(fn($event) => $event->getId(), $events)
            ], 'Events rejected successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to bulk reject events: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get event statistics
     */
    public function getEventStatistics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $statistics = $this->eventService->getEventStatistics();

            $this->successResponse([
                'statistics' => $statistics
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load statistics: ' . $e->getMessage(), 500);
        }
    }
}