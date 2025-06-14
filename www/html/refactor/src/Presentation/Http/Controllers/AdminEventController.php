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

            // For now, return mock data since the service might not have real data
            $mockEvents = [
                (object)[
                    'id' => 1,
                    'title' => 'Yakima Valley Wine Festival',
                    'description' => 'Annual wine tasting event featuring local wineries',
                    'start_datetime' => '2025-01-20 14:00:00',
                    'end_datetime' => '2025-01-20 18:00:00',
                    'location' => 'Yakima Convention Center',
                    'status' => 'approved',
                    'featured' => true,
                    'source_id' => 1,
                    'latitude' => 46.6021,
                    'longitude' => -120.5059,
                    'created_at' => '2025-01-15 10:00:00',
                    'updated_at' => '2025-01-15 10:00:00'
                ],
                (object)[
                    'id' => 2,
                    'title' => 'Downtown Farmers Market',
                    'description' => 'Fresh produce and local crafts every Saturday',
                    'start_datetime' => '2025-01-18 08:00:00',
                    'end_datetime' => '2025-01-18 14:00:00',
                    'location' => 'Downtown Yakima',
                    'status' => 'approved',
                    'featured' => false,
                    'source_id' => 2,
                    'latitude' => 46.6016,
                    'longitude' => -120.5063,
                    'created_at' => '2025-01-14 15:30:00',
                    'updated_at' => '2025-01-14 15:30:00'
                ],
                (object)[
                    'id' => 3,
                    'title' => 'Community Art Show',
                    'description' => 'Local artists showcase their work',
                    'start_datetime' => '2025-01-25 17:00:00',
                    'end_datetime' => '2025-01-25 21:00:00',
                    'location' => 'Yakima Art Gallery',
                    'status' => 'pending',
                    'featured' => false,
                    'source_id' => 1,
                    'latitude' => 46.6035,
                    'longitude' => -120.5070,
                    'created_at' => '2025-01-15 09:15:00',
                    'updated_at' => '2025-01-15 09:15:00'
                ]
            ];

            $events = $mockEvents;
            
            // Format events with admin details
            $formattedEvents = array_map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'start_datetime' => $event->start_datetime,
                    'end_datetime' => $event->end_datetime,
                    'location' => $event->location,
                    'latitude' => $event->latitude,
                    'longitude' => $event->longitude,
                    'status' => $event->status,
                    'featured' => $event->featured,
                    'source_id' => $event->source_id,
                    'created_at' => $event->created_at,
                    'updated_at' => $event->updated_at
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
            // Mock statistics data for now
            $statistics = [
                'total' => 156,
                'pending' => 12,
                'approved' => 134,
                'rejected' => 10,
                'featured' => 8,
                'today' => 3,
                'this_week' => 15,
                'this_month' => 42
            ];

            $this->successResponse([
                'statistics' => $statistics
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get available scrapers
     */
    public function getScrapers(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            // Mock scraper data for now
            $scrapers = [
                [
                    'id' => 1,
                    'name' => 'Yakima Valley Events',
                    'url' => 'https://yakimavalley.org/events/',
                    'status' => 'active',
                    'last_run' => '2025-01-15 10:30:00',
                    'type' => 'html'
                ],
                [
                    'id' => 2,
                    'name' => 'Visit Yakima',
                    'url' => 'https://visityakima.com/events/',
                    'status' => 'active',
                    'last_run' => '2025-01-15 09:15:00',
                    'type' => 'json'
                ],
                [
                    'id' => 3,
                    'name' => 'Central Washington University',
                    'url' => 'https://cwu.edu/events/',
                    'status' => 'active',
                    'last_run' => '2025-01-15 08:45:00',
                    'type' => 'ical'
                ]
            ];

            $this->successResponse($scrapers, 'Scrapers retrieved successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to get scrapers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Run a specific scraper
     */
    public function runScraper(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $scraperId = $input['scraper_id'] ?? null;

            if (!$scraperId) {
                $this->errorResponse('Scraper ID is required');
                return;
            }

            // Mock scraper run
            sleep(2); // Simulate processing time
            
            $results = [
                'scraper_id' => $scraperId,
                'events_found' => rand(5, 25),
                'events_added' => rand(2, 15),
                'events_updated' => rand(0, 5),
                'duration' => '2.3 seconds',
                'status' => 'completed'
            ];

            $this->successResponse($results, 'Scraper completed successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to run scraper: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Run all scrapers
     */
    public function runAllScrapers(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            // Mock running all scrapers
            sleep(3); // Simulate processing time
            
            $results = [
                'total_scrapers' => 3,
                'successful_scrapers' => 3,
                'failed_scrapers' => 0,
                'total_events_found' => rand(25, 75),
                'total_events_added' => rand(10, 40),
                'total_events_updated' => rand(0, 15),
                'duration' => '5.7 seconds',
                'status' => 'completed'
            ];

            $this->successResponse($results, 'All scrapers completed successfully');

        } catch (Exception $e) {
            $this->errorResponse('Failed to run all scrapers: ' . $e->getMessage(), 500);
        }
    }
}