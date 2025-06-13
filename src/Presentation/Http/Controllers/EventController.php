<?php

declare(strict_types=1);

namespace YakimaFinds\Presentation\Http\Controllers;

use YakimaFinds\Domain\Events\EventServiceInterface;
use YakimaFinds\Infrastructure\Container\ContainerInterface;
use YakimaFinds\Infrastructure\Config\ConfigInterface;
use DateTime;
use Exception;

/**
 * Event controller for public event management
 */
class EventController extends BaseController
{
    private EventServiceInterface $eventService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->eventService = $container->resolve(EventServiceInterface::class);
    }

    /**
     * Get events for calendar view
     */
    public function getCalendarEvents(): void
    {
        try {
            $input = $this->getInput();
            
            // Default to current month if no dates provided
            $startDate = isset($input['start']) 
                ? new DateTime($input['start']) 
                : new DateTime('first day of this month');
            
            $endDate = isset($input['end']) 
                ? new DateTime($input['end']) 
                : new DateTime('last day of this month 23:59:59');

            // Build filters
            $filters = [];
            if (isset($input['status'])) {
                $filters['status'] = $input['status'];
            }
            if (isset($input['featured'])) {
                $filters['featured'] = filter_var($input['featured'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['source_id'])) {
                $filters['source_id'] = (int) $input['source_id'];
            }

            $events = $this->eventService->getEventsForCalendar($startDate, $endDate, $filters);

            // Format events for calendar
            $formattedEvents = array_map(function ($event) {
                return [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'description' => $event->getDescription(),
                    'start' => $event->getStartDateTime()->format('c'),
                    'end' => $event->getEndDateTime()?->format('c'),
                    'location' => $event->getLocation(),
                    'address' => $event->getAddress(),
                    'latitude' => $event->getLatitude(),
                    'longitude' => $event->getLongitude(),
                    'contact_info' => $event->getContactInfo(),
                    'external_url' => $event->getExternalUrl(),
                    'status' => $event->getStatus(),
                    'featured' => $event->isFeatured(),
                    'is_upcoming' => $event->isUpcoming(),
                    'is_happening' => $event->isHappening(),
                ];
            }, $events);

            $this->successResponse([
                'events' => $formattedEvents,
                'count' => count($formattedEvents),
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load calendar events: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single event details
     */
    public function getEvent(): void
    {
        try {
            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Event ID is required');
                return;
            }

            $event = $this->eventService->getEventById((int) $input['id']);
            
            if (!$event) {
                $this->errorResponse('Event not found', 404);
                return;
            }

            $this->successResponse([
                'event' => [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'description' => $event->getDescription(),
                    'start_datetime' => $event->getStartDateTime()->format('c'),
                    'end_datetime' => $event->getEndDateTime()?->format('c'),
                    'location' => $event->getLocation(),
                    'address' => $event->getAddress(),
                    'latitude' => $event->getLatitude(),
                    'longitude' => $event->getLongitude(),
                    'contact_info' => $event->getContactInfo(),
                    'external_url' => $event->getExternalUrl(),
                    'source_id' => $event->getSourceId(),
                    'status' => $event->getStatus(),
                    'featured' => $event->isFeatured(),
                    'created_at' => $event->getCreatedAt()?->format('c'),
                    'updated_at' => $event->getUpdatedAt()?->format('c'),
                    'is_upcoming' => $event->isUpcoming(),
                    'is_happening' => $event->isHappening(),
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search events
     */
    public function searchEvents(): void
    {
        try {
            $input = $this->getInput();
            $query = $input['q'] ?? '';
            
            // Build filters
            $filters = [];
            if (isset($input['status'])) {
                $filters['status'] = $input['status'];
            }
            if (isset($input['featured'])) {
                $filters['featured'] = filter_var($input['featured'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['start_date'])) {
                $filters['start_date'] = $input['start_date'];
            }
            if (isset($input['end_date'])) {
                $filters['end_date'] = $input['end_date'];
            }
            if (isset($input['source_id'])) {
                $filters['source_id'] = (int) $input['source_id'];
            }
            if (isset($input['limit'])) {
                $filters['limit'] = min(100, max(1, (int) $input['limit']));
            }

            $events = $this->eventService->searchEvents($query, $filters);

            // Format events
            $formattedEvents = array_map(function ($event) {
                return [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'description' => $event->getDescription(),
                    'start_datetime' => $event->getStartDateTime()->format('c'),
                    'end_datetime' => $event->getEndDateTime()?->format('c'),
                    'location' => $event->getLocation(),
                    'address' => $event->getAddress(),
                    'latitude' => $event->getLatitude(),
                    'longitude' => $event->getLongitude(),
                    'status' => $event->getStatus(),
                    'featured' => $event->isFeatured(),
                ];
            }, $events);

            $this->successResponse([
                'events' => $formattedEvents,
                'count' => count($formattedEvents),
                'query' => $query,
                'filters' => $filters
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get featured events
     */
    public function getFeaturedEvents(): void
    {
        try {
            $input = $this->getInput();
            $limit = min(50, max(1, (int) ($input['limit'] ?? 10)));

            $events = $this->eventService->getFeaturedEvents($limit);

            $formattedEvents = array_map(function ($event) {
                return [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'description' => $event->getDescription(),
                    'start_datetime' => $event->getStartDateTime()->format('c'),
                    'location' => $event->getLocation(),
                    'address' => $event->getAddress(),
                    'external_url' => $event->getExternalUrl(),
                    'featured' => $event->isFeatured(),
                ];
            }, $events);

            $this->successResponse([
                'events' => $formattedEvents,
                'count' => count($formattedEvents)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load featured events: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get upcoming events
     */
    public function getUpcomingEvents(): void
    {
        try {
            $input = $this->getInput();
            $limit = min(50, max(1, (int) ($input['limit'] ?? 10)));

            $events = $this->eventService->getUpcomingEvents($limit);

            $formattedEvents = array_map(function ($event) {
                return [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'description' => $event->getDescription(),
                    'start_datetime' => $event->getStartDateTime()->format('c'),
                    'location' => $event->getLocation(),
                    'address' => $event->getAddress(),
                    'external_url' => $event->getExternalUrl(),
                    'is_upcoming' => $event->isUpcoming(),
                ];
            }, $events);

            $this->successResponse([
                'events' => $formattedEvents,
                'count' => count($formattedEvents)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load upcoming events: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get events near a location
     */
    public function getEventsNearLocation(): void
    {
        try {
            $input = $this->getInput();
            
            $missing = $this->validateRequired($input, ['latitude', 'longitude']);
            if (!empty($missing)) {
                $this->errorResponse('Missing required fields: ' . implode(', ', $missing));
                return;
            }

            $latitude = (float) $input['latitude'];
            $longitude = (float) $input['longitude'];
            $radius = (float) ($input['radius'] ?? 10);

            $events = $this->eventService->getEventsNearLocation($latitude, $longitude, $radius);

            $formattedEvents = array_map(function ($event) {
                return [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'description' => $event->getDescription(),
                    'start_datetime' => $event->getStartDateTime()->format('c'),
                    'location' => $event->getLocation(),
                    'address' => $event->getAddress(),
                    'latitude' => $event->getLatitude(),
                    'longitude' => $event->getLongitude(),
                    'external_url' => $event->getExternalUrl(),
                ];
            }, $events);

            $this->successResponse([
                'events' => $formattedEvents,
                'count' => count($formattedEvents),
                'search_center' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'radius_miles' => $radius
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Location search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit a new event (public submission)
     */
    public function submitEvent(): void
    {
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

            // Public submissions default to pending status
            $eventData = [
                'title' => $input['title'],
                'description' => $input['description'] ?? null,
                'start_datetime' => $input['start_datetime'],
                'end_datetime' => $input['end_datetime'] ?? null,
                'location' => $input['location'] ?? null,
                'address' => $input['address'] ?? null,
                'contact_info' => $input['contact_info'] ?? null,
                'external_url' => $input['external_url'] ?? null,
                'status' => 'pending', // All public submissions need approval
                'featured' => false,
            ];

            $event = $this->eventService->createEvent($eventData);

            $this->successResponse([
                'event_id' => $event->getId(),
                'status' => 'pending_approval'
            ], 'Event submitted successfully and is pending approval');

        } catch (Exception $e) {
            $this->errorResponse('Failed to submit event: ' . $e->getMessage(), 500);
        }
    }
}