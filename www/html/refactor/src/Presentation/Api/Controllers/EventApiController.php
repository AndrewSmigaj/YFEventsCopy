<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Api\Controllers;

use YFEvents\Presentation\Http\Controllers\BaseController;
use YFEvents\Domain\Events\EventServiceInterface;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use DateTime;
use Exception;

/**
 * RESTful API controller for events
 */
class EventApiController extends BaseController
{
    private EventServiceInterface $eventService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->eventService = $container->resolve(EventServiceInterface::class);
        
        // Set CORS headers for API
        $this->setCorsHeaders();
    }

    /**
     * Handle GET /api/events
     */
    public function index(): void
    {
        try {
            $input = $this->getInput();
            $pagination = $this->getPaginationParams($input);
            
            // Default to approved events for public API
            $filters = ['status' => 'approved'];
            
            // Optional filters
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
            
            $filters['limit'] = $pagination['limit'];
            $query = $input['search'] ?? '';

            $events = $this->eventService->searchEvents($query, $filters);

            // Format for API response
            $apiEvents = array_map([$this, 'formatEventForApi'], $events);

            $this->jsonResponse([
                'data' => $apiEvents,
                'meta' => [
                    'count' => count($apiEvents),
                    'page' => $pagination['page'],
                    'limit' => $pagination['limit'],
                    'filters' => array_diff_key($filters, ['limit' => '']),
                ],
                'links' => $this->generatePaginationLinks($pagination, count($apiEvents))
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load events: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle GET /api/events/{id}
     */
    public function show(): void
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

            // Only show approved events in public API
            if ($event->getStatus() !== 'approved') {
                $this->errorResponse('Event not found', 404);
                return;
            }

            $this->jsonResponse([
                'data' => $this->formatEventForApi($event)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle GET /api/events/calendar
     */
    public function calendar(): void
    {
        try {
            $input = $this->getInput();
            
            // Calendar date range
            $startDate = isset($input['start']) 
                ? new DateTime($input['start']) 
                : new DateTime('first day of this month');
            
            $endDate = isset($input['end']) 
                ? new DateTime($input['end']) 
                : new DateTime('last day of this month 23:59:59');

            // Build filters
            $filters = ['status' => 'approved']; // Public API only shows approved
            if (isset($input['featured'])) {
                $filters['featured'] = filter_var($input['featured'], FILTER_VALIDATE_BOOLEAN);
            }
            if (isset($input['source_id'])) {
                $filters['source_id'] = (int) $input['source_id'];
            }

            $events = $this->eventService->getEventsForCalendar($startDate, $endDate, $filters);

            // Format for calendar display
            $calendarEvents = array_map(function ($event) {
                return [
                    'id' => $event->getId(),
                    'title' => $event->getTitle(),
                    'start' => $event->getStartDateTime()->format('c'),
                    'end' => $event->getEndDateTime()?->format('c'),
                    'description' => $event->getDescription(),
                    'location' => $event->getLocation(),
                    'url' => $event->getExternalUrl(),
                    'className' => $event->isFeatured() ? 'featured-event' : 'regular-event',
                    'extendedProps' => [
                        'address' => $event->getAddress(),
                        'contact_info' => $event->getContactInfo(),
                        'featured' => $event->isFeatured(),
                        'coordinates' => [
                            'lat' => $event->getLatitude(),
                            'lng' => $event->getLongitude()
                        ]
                    ]
                ];
            }, $events);

            $this->jsonResponse([
                'data' => $calendarEvents,
                'meta' => [
                    'count' => count($calendarEvents),
                    'period' => [
                        'start' => $startDate->format('Y-m-d'),
                        'end' => $endDate->format('Y-m-d')
                    ],
                    'filters' => $filters
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load calendar events: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle GET /api/events/featured
     */
    public function featured(): void
    {
        try {
            $input = $this->getInput();
            $limit = min(50, max(1, (int) ($input['limit'] ?? 10)));

            $events = $this->eventService->getFeaturedEvents($limit);
            $apiEvents = array_map([$this, 'formatEventForApi'], $events);

            $this->jsonResponse([
                'data' => $apiEvents,
                'meta' => [
                    'count' => count($apiEvents),
                    'limit' => $limit
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load featured events: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle GET /api/events/upcoming
     */
    public function upcoming(): void
    {
        try {
            $input = $this->getInput();
            $limit = min(50, max(1, (int) ($input['limit'] ?? 10)));

            $events = $this->eventService->getUpcomingEvents($limit);
            $apiEvents = array_map([$this, 'formatEventForApi'], $events);

            $this->jsonResponse([
                'data' => $apiEvents,
                'meta' => [
                    'count' => count($apiEvents),
                    'limit' => $limit
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load upcoming events: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle GET /api/events/nearby
     */
    public function nearby(): void
    {
        try {
            $input = $this->getInput();
            
            $missing = $this->validateRequired($input, ['lat', 'lng']);
            if (!empty($missing)) {
                $this->errorResponse('Missing required fields: ' . implode(', ', $missing));
                return;
            }

            $latitude = (float) $input['lat'];
            $longitude = (float) $input['lng'];
            $radius = (float) ($input['radius'] ?? 10);

            $events = $this->eventService->getEventsNearLocation($latitude, $longitude, $radius);
            $apiEvents = array_map([$this, 'formatEventForApi'], $events);

            $this->jsonResponse([
                'data' => $apiEvents,
                'meta' => [
                    'count' => count($apiEvents),
                    'search_center' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'radius_miles' => $radius
                    ]
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Location search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle POST /api/events (public submission)
     */
    public function store(): void
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

            // API submissions default to pending
            $eventData = [
                'title' => $input['title'],
                'description' => $input['description'] ?? null,
                'start_datetime' => $input['start_datetime'],
                'end_datetime' => $input['end_datetime'] ?? null,
                'location' => $input['location'] ?? null,
                'address' => $input['address'] ?? null,
                'contact_info' => $input['contact_info'] ?? null,
                'external_url' => $input['external_url'] ?? null,
                'status' => 'pending',
                'featured' => false,
            ];

            $event = $this->eventService->createEvent($eventData);

            $this->jsonResponse([
                'data' => [
                    'id' => $event->getId(),
                    'status' => 'pending_approval',
                    'message' => 'Event submitted successfully and is pending approval'
                ]
            ], 201);

        } catch (Exception $e) {
            $this->errorResponse('Failed to submit event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Format event for API response
     */
    private function formatEventForApi($event): array
    {
        return [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'start_datetime' => $event->getStartDateTime()->format('c'),
            'end_datetime' => $event->getEndDateTime()?->format('c'),
            'location' => $event->getLocation(),
            'address' => $event->getAddress(),
            'coordinates' => [
                'latitude' => $event->getLatitude(),
                'longitude' => $event->getLongitude()
            ],
            'contact_info' => $event->getContactInfo(),
            'external_url' => $event->getExternalUrl(),
            'featured' => $event->isFeatured(),
            'status' => [
                'upcoming' => $event->isUpcoming(),
                'happening' => $event->isHappening(),
            ],
            'created_at' => $event->getCreatedAt()?->format('c'),
            'updated_at' => $event->getUpdatedAt()?->format('c'),
        ];
    }

    /**
     * Set CORS headers for API access
     */
    private function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Generate pagination links
     */
    private function generatePaginationLinks(array $pagination, int $resultCount): array
    {
        $baseUrl = $this->config->get('app.url') . '/api/events';
        
        $links = [
            'self' => $baseUrl . '?page=' . $pagination['page'] . '&limit=' . $pagination['limit']
        ];

        if ($pagination['page'] > 1) {
            $links['prev'] = $baseUrl . '?page=' . ($pagination['page'] - 1) . '&limit=' . $pagination['limit'];
            $links['first'] = $baseUrl . '?page=1&limit=' . $pagination['limit'];
        }

        if ($resultCount >= $pagination['limit']) {
            $links['next'] = $baseUrl . '?page=' . ($pagination['page'] + 1) . '&limit=' . $pagination['limit'];
        }

        return $links;
    }
}