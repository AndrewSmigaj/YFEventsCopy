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
     * Show public events page
     */
    public function showEventsPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderEventsPage($basePath);
    }

    /**
     * Show featured events page
     */
    public function showFeaturedEventsPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderEventsPage($basePath, 'Featured Events', 'featured');
    }

    /**
     * Show upcoming events page
     */
    public function showUpcomingEventsPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderEventsPage($basePath, 'Upcoming Events', 'upcoming');
    }

    /**
     * Show calendar view page
     */
    public function showCalendarPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderCalendarPage($basePath);
    }

    /**
     * Show event submission page
     */
    public function showSubmitEventPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderSubmitEventPage($basePath);
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

    private function renderCalendarPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Calendar View</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .calendar-controls {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .month-nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .nav-btn {
            background: #667eea;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .nav-btn:hover {
            background: #5a6fd8;
        }
        
        .current-month {
            font-size: 1.5rem;
            font-weight: 600;
            color: #343a40;
            min-width: 200px;
            text-align: center;
        }
        
        .view-controls {
            display: flex;
            gap: 10px;
        }
        
        .view-btn {
            padding: 8px 16px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .view-btn.active {
            background: #667eea;
            color: white;
        }
        
        .view-btn:hover {
            background: #667eea;
            color: white;
        }
        
        .calendar-grid {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #667eea;
            color: white;
        }
        
        .day-header {
            padding: 15px;
            text-align: center;
            font-weight: 600;
        }
        
        .calendar-body {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e9ecef;
        }
        
        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 8px;
            position: relative;
            border-bottom: 1px solid #e9ecef;
        }
        
        .calendar-day.other-month {
            background: #f8f9fa;
            color: #6c757d;
        }
        
        .calendar-day.today {
            background: #fff3cd;
            border: 2px solid #ffc107;
        }
        
        .day-number {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .day-events {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .event-item {
            background: #667eea;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75rem;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .event-item.featured {
            background: #ffc107;
            color: #212529;
        }
        
        .event-item:hover {
            opacity: 0.8;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .event-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        #map {
            height: 500px;
            width: 100%;
        }
        
        .map-view {
            display: none;
        }
        
        .map-controls {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .map-legend {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        .events-count {
            position: absolute;
            top: 10px;
            left: 10px;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            z-index: 100;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .calendar-controls {
                flex-direction: column;
                text-align: center;
            }
            
            .calendar-day {
                min-height: 80px;
            }
            
            .day-header {
                padding: 10px 5px;
                font-size: 0.9rem;
            }
            
            #map {
                height: 400px;
            }
            
            .map-controls {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📅 Events Calendar</h1>
        <p>Interactive calendar view of approved events in the Yakima Valley</p>
    </div>
    
    <div class="container">
        <a href="{$basePath}/" class="back-link">← Back to Home</a>
        
        <div class="calendar-controls">
            <div class="month-nav">
                <button class="nav-btn" onclick="previousMonth()">‹</button>
                <div class="current-month" id="current-month">Loading...</div>
                <button class="nav-btn" onclick="nextMonth()">›</button>
            </div>
            
            <div class="view-controls">
                <button class="view-btn active" onclick="setView('month')">Month</button>
                <button class="view-btn" onclick="setView('list')">List</button>
                <button class="view-btn" onclick="setView('map')">Map</button>
            </div>
        </div>
        
        <div class="calendar-grid" id="calendar-container">
            <div class="loading">Loading calendar...</div>
        </div>
        
        <div class="map-view" id="map-view">
            <div class="map-controls">
                <div class="map-legend">
                    <div class="legend-item">
                        <div class="legend-dot" style="background-color: #667eea;"></div>
                        <span>Regular Event</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background-color: #ffc107;"></div>
                        <span>Featured Event</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background-color: #28a745;"></div>
                        <span>Today's Event</span>
                    </div>
                </div>
            </div>
            
            <div class="calendar-grid" style="position: relative;">
                <div class="events-count" id="events-count">Loading events...</div>
                <div id="map" style="border-radius: 15px;">
                    🗺️ Initializing events map...
                </div>
            </div>
        </div>
    </div>
    
    <!-- Event Modal -->
    <div class="event-modal" id="event-modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">×</button>
            <div id="modal-content">
                <!-- Event details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        let currentDate = new Date();
        let currentView = 'month';
        let eventsData = [];
        let map;
        let markers = [];
        
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        async function loadEvents() {
            try {
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();
                const startDate = new Date(year, month, 1);
                const endDate = new Date(year, month + 1, 0);
                
                const searchParams = new URLSearchParams();
                searchParams.append('status', 'approved');
                searchParams.append('start_date', startDate.toISOString().split('T')[0]);
                searchParams.append('end_date', endDate.toISOString().split('T')[0]);
                searchParams.append('limit', '100');
                
                const response = await fetch(`{$basePath}/api/events?\${searchParams}`);
                const data = await response.json();
                
                eventsData = data.data?.events || [];
                renderCalendar();
                
            } catch (error) {
                console.error('Error loading events:', error);
                document.getElementById('calendar-container').innerHTML = '<div class="loading">Error loading calendar</div>';
            }
        }
        
        function renderCalendar() {
            const container = document.getElementById('calendar-container');
            const monthYear = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
            document.getElementById('current-month').textContent = monthYear;
            
            if (currentView === 'month') {
                renderMonthView(container);
            } else {
                renderListView(container);
            }
        }
        
        function renderMonthView(container) {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startCalendar = new Date(firstDay);
            startCalendar.setDate(startCalendar.getDate() - firstDay.getDay());
            
            let html = `
                <div class="calendar-header">
                    \${dayNames.map(day => `<div class="day-header">\${day}</div>`).join('')}
                </div>
                <div class="calendar-body">
            `;
            
            const today = new Date();
            for (let i = 0; i < 42; i++) {
                const date = new Date(startCalendar);
                date.setDate(startCalendar.getDate() + i);
                
                const isCurrentMonth = date.getMonth() === month;
                const isToday = date.toDateString() === today.toDateString();
                const dayEvents = getEventsForDate(date);
                
                html += `
                    <div class="calendar-day \${!isCurrentMonth ? 'other-month' : ''} \${isToday ? 'today' : ''}">
                        <div class="day-number">\${date.getDate()}</div>
                        <div class="day-events">
                            \${dayEvents.slice(0, 3).map(event => `
                                <div class="event-item \${event.featured ? 'featured' : ''}" 
                                     onclick="showEventModal(\${event.id})" 
                                     title="\${event.title}">
                                    \${event.title.substring(0, 20)}\${event.title.length > 20 ? '...' : ''}
                                </div>
                            `).join('')}
                            \${dayEvents.length > 3 ? `<div class="event-item" style="background: #6c757d;">+\${dayEvents.length - 3} more</div>` : ''}
                        </div>
                    </div>
                `;
            }
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function renderListView(container) {
            const monthEvents = eventsData.filter(event => {
                const eventDate = new Date(event.start_datetime);
                return eventDate.getMonth() === currentDate.getMonth() && 
                       eventDate.getFullYear() === currentDate.getFullYear();
            });
            
            if (monthEvents.length === 0) {
                container.innerHTML = '<div class="loading">No events found for this month</div>';
                return;
            }
            
            const html = `
                <div style="background: white; border-radius: 15px; padding: 20px;">
                    \${monthEvents.map(event => `
                        <div style="padding: 15px; border-bottom: 1px solid #e9ecef; cursor: pointer;" onclick="showEventModal(\${event.id})">
                            <div style="font-weight: 600; color: #343a40; margin-bottom: 5px;">
                                \${event.featured ? '⭐ ' : ''}\${event.title || 'Untitled Event'}
                            </div>
                            <div style="color: #667eea; margin-bottom: 5px;">
                                📅 \${new Date(event.start_datetime).toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}
                            </div>
                            <div style="color: #6c757d;">
                                📍 \${event.location || 'Location TBA'}
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        function getEventsForDate(date) {
            return eventsData.filter(event => {
                const eventDate = new Date(event.start_datetime);
                return eventDate.toDateString() === date.toDateString();
            });
        }
        
        function previousMonth() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            loadEvents();
        }
        
        function nextMonth() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            loadEvents();
        }
        
        function setView(view) {
            currentView = view;
            document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Show/hide appropriate containers
            if (view === 'map') {
                document.getElementById('calendar-container').style.display = 'none';
                document.getElementById('map-view').style.display = 'block';
                if (!map) {
                    initMap();
                } else {
                    renderMapView();
                }
            } else {
                document.getElementById('calendar-container').style.display = 'block';
                document.getElementById('map-view').style.display = 'none';
                renderCalendar();
            }
        }
        
        function initMap() {
            // Center on Yakima Finds: 111 S. 2nd St, Yakima, WA
            const yakimaCenter = { lat: 46.600825, lng: -120.503357 };
            
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 11,
                center: yakimaCenter,
                styles: [
                    {
                        featureType: 'poi',
                        elementType: 'labels',
                        stylers: [{ visibility: 'off' }]
                    }
                ]
            });
            
            renderMapView();
        }
        
        function renderMapView() {
            // Clear existing markers
            markers.forEach(marker => marker.setMap(null));
            markers = [];
            
            // Filter events for current month with coordinates
            const eventsWithCoords = eventsData.filter(event => 
                event.latitude && event.longitude && event.latitude !== null && event.longitude !== null
            );
            
            // Add markers for each event
            eventsWithCoords.forEach(event => {
                const today = new Date().toDateString();
                const eventDate = new Date(event.start_datetime).toDateString();
                const isToday = eventDate === today;
                
                const markerColor = event.featured ? '#ffc107' : 
                                  isToday ? '#28a745' : '#667eea';
                
                const marker = new google.maps.Marker({
                    position: { lat: parseFloat(event.latitude), lng: parseFloat(event.longitude) },
                    map: map,
                    title: event.title || 'Event',
                    icon: {
                        url: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                        scaledSize: new google.maps.Size(32, 32)
                    }
                });
                
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div style="padding: 10px; max-width: 300px;">
                            <h3 style="margin: 0 0 8px 0; color: #343a40;">
                                \${event.featured ? '⭐ ' : ''}\${event.title || 'Untitled Event'}
                            </h3>
                            <p style="margin: 0 0 8px 0; color: #667eea; font-size: 0.9rem;">
                                📅 \${new Date(event.start_datetime).toLocaleDateString('en-US', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}
                            </p>
                            \${event.location ? `<p style="margin: 0 0 8px 0; color: #6c757d; font-size: 0.9rem;">📍 \${event.location}</p>` : ''}
                            \${event.description ? `<p style="margin: 0 0 10px 0; color: #495057; font-size: 0.9rem;">\${event.description.substring(0, 100)}\${event.description.length > 100 ? '...' : ''}</p>` : ''}
                            <div style="margin-top: 10px;">
                                \${event.external_url ? `<a href="\${event.external_url}" target="_blank" style="color: #007bff; text-decoration: none; margin-right: 10px;">🔗 Event Details</a>` : ''}
                                <button onclick="showEventModal(\${event.id})" style="background: #667eea; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;">👁️ View Details</button>
                            </div>
                            \${isToday ? '<div style="margin-top: 8px; color: #28a745; font-size: 0.8rem; font-weight: bold;">🔥 Today!</div>' : ''}
                        </div>
                    `
                });
                
                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });
                
                markers.push(marker);
            });
            
            // Update events count
            document.getElementById('events-count').textContent = `\${eventsWithCoords.length} events on map`;
            
            // Fit map to show all markers
            if (eventsWithCoords.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                eventsWithCoords.forEach(event => {
                    bounds.extend({ lat: parseFloat(event.latitude), lng: parseFloat(event.longitude) });
                });
                map.fitBounds(bounds);
                
                // Don't zoom too close for single markers
                if (eventsWithCoords.length === 1) {
                    map.setZoom(15);
                }
            }
        }
        
        function showEventModal(eventId) {
            const event = eventsData.find(e => e.id === eventId);
            if (!event) return;
            
            const modalContent = document.getElementById('modal-content');
            modalContent.innerHTML = `
                <h2 style="color: #343a40; margin-bottom: 15px;">
                    \${event.featured ? '⭐ ' : ''}\${event.title || 'Untitled Event'}
                </h2>
                <div style="color: #667eea; margin-bottom: 10px;">
                    📅 \${new Date(event.start_datetime).toLocaleDateString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}
                </div>
                \${event.location ? `<div style="color: #6c757d; margin-bottom: 15px;">📍 \${event.location}</div>` : ''}
                \${event.description ? `<div style="color: #495057; line-height: 1.6; margin-bottom: 15px;">\${event.description}</div>` : ''}
                \${event.external_url ? `<a href="\${event.external_url}" target="_blank" style="color: #007bff; text-decoration: none;">🔗 View Event Details</a>` : ''}
            `;
            
            document.getElementById('event-modal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('event-modal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('event-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Load calendar on page load
        document.addEventListener('DOMContentLoaded', loadEvents);
        
        // Handle map load errors
        window.gm_authFailure = function() {
            document.getElementById('map').innerHTML = '<div style="padding: 40px; text-align: center; color: #dc3545;">Google Maps failed to load. Please check the API key configuration.</div>';
        };
    </script>
    
    <!-- Load Google Maps API -->
    <script async defer 
        src="https://maps.googleapis.com/maps/api/js?key=<?= defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : 'YOUR_GOOGLE_MAPS_API_KEY' ?>&libraries=places&callback=initMapIfNeeded">
    </script>
    
    <script>
        // Only initialize map if map view is active
        function initMapIfNeeded() {
            if (currentView === 'map' && !map) {
                initMap();
            }
        }
    </script>
</body>
</html>
HTML;
    }

    private function renderEventsPage(string $basePath, string $pageTitle = 'Browse Events', string $filter = 'all'): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - {$pageTitle}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .filters {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #343a40;
            margin-bottom: 5px;
        }
        
        .filter-group input, .filter-group select {
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .search-btn {
            padding: 10px 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .event-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .event-card.featured {
            border: 2px solid #ffc107;
            background: linear-gradient(135deg, #fff9e6 0%, #ffffff 100%);
            position: relative;
        }
        
        .event-card.featured::before {
            content: "⭐ Featured Event";
            position: absolute;
            top: -12px;
            right: 15px;
            background: #ffc107;
            color: #212529;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .event-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 10px;
        }
        
        .event-date {
            color: #667eea;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .event-location {
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .event-description {
            color: #495057;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .event-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: opacity 0.2s ease;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📅 {$pageTitle}</h1>
        <p>Discover approved local events in the Yakima Valley • ⭐ Featured events are highlighted</p>
    </div>
    
    <div class="container">
        <a href="{$basePath}/" class="back-link">← Back to Home</a>
        
        <div class="filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Search Events</label>
                    <input type="text" id="search" placeholder="Search by title, location...">
                </div>
                
                <div class="filter-group">
                    <label for="date">Date Filter</label>
                    <select id="date">
                        <option value="all">All Dates</option>
                        <option value="today">Today</option>
                        <option value="tomorrow">Tomorrow</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="type">Event Type</label>
                    <select id="type">
                        <option value="all">All Events</option>
                        <option value="featured">Featured Only</option>
                        <option value="upcoming">Upcoming Only</option>
                        <option value="today">Today</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button class="search-btn" onclick="loadEvents()">🔍 Search Events</button>
                </div>
            </div>
        </div>
        
        <div id="events-container">
            <div class="loading">Loading events...</div>
        </div>
    </div>

    <script>
        async function loadEvents() {
            const container = document.getElementById('events-container');
            container.innerHTML = '<div class="loading">Loading events...</div>';
            
            try {
                const searchParams = new URLSearchParams();
                const search = document.getElementById('search').value;
                const date = document.getElementById('date').value;
                const type = document.getElementById('type').value;
                
                if (search) searchParams.append('search', search);
                if (date !== 'all') searchParams.append('date_filter', date);
                if (type === 'featured') searchParams.append('featured', 'true');
                if (type === 'today') searchParams.append('date_filter', 'today');
                
                // Always filter to approved events only (security)
                searchParams.append('status', 'approved');
                searchParams.append('limit', '20');
                
                // Apply page-specific filter
                const pageFilter = '{$filter}';
                if (pageFilter === 'featured') {
                    searchParams.append('featured', 'true');
                } else if (pageFilter === 'upcoming') {
                    searchParams.append('upcoming', 'true');
                }
                
                const response = await fetch(`{$basePath}/api/events?\${searchParams}`);
                const data = await response.json();
                
                if (data.data?.events && data.data.events.length > 0) {
                    container.innerHTML = `
                        <div class="events-grid">
                            \${data.data.events.map(event => `
                                <div class="event-card \${event.featured ? 'featured' : ''}">
                                    <div class="event-title">\${event.title || 'Untitled Event'}</div>
                                    <div class="event-date">📅 \${new Date(event.start_datetime).toLocaleDateString('en-US', {
                                        weekday: 'long',
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    })}</div>
                                    <div class="event-location">📍 \${event.location || 'Location TBA'}</div>
                                    <div class="event-description">\${(event.description || '').substring(0, 150)}\${event.description && event.description.length > 150 ? '...' : ''}</div>
                                    <div class="event-actions">
                                        \${event.external_url ? `<a href="\${event.external_url}" target="_blank" class="btn btn-primary">View Details</a>` : ''}
                                        <a href="{$basePath}/events/\${event.id}" class="btn btn-secondary">More Info</a>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                } else {
                    container.innerHTML = '<div class="loading">No events found matching your criteria.</div>';
                }
                
            } catch (error) {
                container.innerHTML = '<div class="loading">Error loading events. Please try again.</div>';
                console.error('Error loading events:', error);
            }
        }
        
        // Load events on page load
        document.addEventListener('DOMContentLoaded', loadEvents);
        
        // Add enter key support for search
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loadEvents();
            }
        });
    </script>
</body>
</html>
HTML;
    }

    private function renderSubmitEventPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Submit Event</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .form-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 700px;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h1 {
            font-size: 2rem;
            color: #343a40;
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 5px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
        }
        
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .info-note {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">
            <h1>📅 Submit Your Event</h1>
            <p>Share your event with the Yakima Valley community</p>
        </div>
        
        <div class="info-note">
            <strong>📋 Note:</strong> All submitted events will be reviewed by our team before publication. This helps ensure quality and accuracy in our event listings.
        </div>
        
        <div class="success-message" id="success-message"></div>
        <div class="error-message" id="error-message"></div>
        
        <form id="event-form">
            <div class="form-group">
                <label for="title">Event Title *</label>
                <input type="text" id="title" name="title" required placeholder="Enter your event title">
            </div>
            
            <div class="form-group">
                <label for="description">Event Description</label>
                <textarea id="description" name="description" placeholder="Describe your event - what will happen, who should attend, special features, etc."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="start_datetime">Start Date & Time *</label>
                    <input type="datetime-local" id="start_datetime" name="start_datetime" required>
                </div>
                
                <div class="form-group">
                    <label for="end_datetime">End Date & Time</label>
                    <input type="datetime-local" id="end_datetime" name="end_datetime">
                </div>
            </div>
            
            <div class="form-group">
                <label for="location">Location/Venue Name</label>
                <input type="text" id="location" name="location" placeholder="Event venue or location name">
            </div>
            
            <div class="form-group">
                <label for="address">Full Address</label>
                <input type="text" id="address" name="address" placeholder="Street address, city, state, zip code">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="contact_info">Contact Information</label>
                    <input type="text" id="contact_info" name="contact_info" placeholder="Phone, email, or contact person">
                </div>
                
                <div class="form-group">
                    <label for="external_url">Event Website/URL</label>
                    <input type="url" id="external_url" name="external_url" placeholder="https://your-event-website.com">
                </div>
            </div>
            
            <button type="submit" class="submit-btn" id="submit-btn">Submit Event for Review</button>
        </form>
        
        <a href="{$basePath}/events" class="back-link">← Back to Events</a>
    </div>

    <script>
        // Set minimum date to today
        const now = new Date();
        const todayString = now.toISOString().slice(0, 16);
        document.getElementById('start_datetime').min = todayString;
        document.getElementById('end_datetime').min = todayString;
        
        // Auto-set end time when start time changes
        document.getElementById('start_datetime').addEventListener('change', function() {
            const startTime = new Date(this.value);
            if (startTime) {
                // Default to 2 hours later
                const endTime = new Date(startTime.getTime() + (2 * 60 * 60 * 1000));
                document.getElementById('end_datetime').value = endTime.toISOString().slice(0, 16);
                document.getElementById('end_datetime').min = this.value;
            }
        });

        document.getElementById('event-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submit-btn');
            const successMessage = document.getElementById('success-message');
            const errorMessage = document.getElementById('error-message');
            
            // Hide previous messages
            successMessage.style.display = 'none';
            errorMessage.style.display = 'none';
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            try {
                const formData = new FormData(this);
                const eventData = Object.fromEntries(formData.entries());
                
                // Remove empty fields
                Object.keys(eventData).forEach(key => {
                    if (!eventData[key]) {
                        delete eventData[key];
                    }
                });
                
                const response = await fetch('{$basePath}/api/events/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(eventData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    successMessage.textContent = data.message || 'Event submitted successfully! Our team will review it and publish it to the calendar if approved.';
                    successMessage.style.display = 'block';
                    this.reset(); // Clear form
                    // Reset date minimums
                    document.getElementById('start_datetime').min = todayString;
                    document.getElementById('end_datetime').min = todayString;
                } else {
                    errorMessage.textContent = data.message || 'Failed to submit event. Please check your information and try again.';
                    errorMessage.style.display = 'block';
                }
                
            } catch (error) {
                console.error('Error submitting event:', error);
                errorMessage.textContent = 'Network error. Please check your connection and try again.';
                errorMessage.style.display = 'block';
            } finally {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Event for Review';
            }
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Show individual event detail page
     */
    public function showEventDetailPage(): void
    {
        try {
            $id = (int) ($_GET['id'] ?? 0);
            
            if (!$id) {
                header('HTTP/1.0 404 Not Found');
                echo $this->render404Page();
                return;
            }
            
            $event = $this->eventService->getEventById($id);
            
            if (!$event) {
                header('HTTP/1.0 404 Not Found');
                echo $this->render404Page();
                return;
            }

            $basePath = dirname($_SERVER['SCRIPT_NAME']);
            if ($basePath === '/') {
                $basePath = '';
            }

            // Convert Event object to array
            $eventArray = $event->toArray();

            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderEventDetailPage($eventArray, $basePath);
        } catch (Exception $e) {
            error_log("Error displaying event $id: " . $e->getMessage());
            header('HTTP/1.0 500 Internal Server Error');
            echo $this->render500Page();
        }
    }

    /**
     * Render event detail page
     */
    private function renderEventDetailPage(array $event, string $basePath): string
    {
        $googleMapsApiKey = $this->config->get('google_maps_api_key', '');
        
        // Format dates
        $startDate = new DateTime($event['start_datetime']);
        $endDate = $event['end_datetime'] ? new DateTime($event['end_datetime']) : null;
        
        $eventDate = $startDate->format('l, F j, Y');
        $eventTime = $startDate->format('g:i A');
        if ($endDate) {
            $eventTime .= ' - ' . $endDate->format('g:i A');
        }
        
        // Escape data for HTML
        $title = htmlspecialchars($event['title'] ?? 'Untitled Event', ENT_QUOTES, 'UTF-8');
        $description = nl2br(htmlspecialchars($event['description'] ?? 'No description available.', ENT_QUOTES, 'UTF-8'));
        $location = htmlspecialchars($event['location'] ?? 'Location TBA', ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars($event['address'] ?? '', ENT_QUOTES, 'UTF-8');
        
        // Build contact info
        $contactInfo = '';
        if (!empty($event['contact_info'])) {
            $contact = is_string($event['contact_info']) ? json_decode($event['contact_info'], true) : $event['contact_info'];
            if ($contact) {
                if (!empty($contact['phone'])) {
                    $contactInfo .= '<p>📞 Phone: ' . htmlspecialchars($contact['phone']) . '</p>';
                }
                if (!empty($contact['email'])) {
                    $contactInfo .= '<p>📧 Email: <a href="mailto:' . htmlspecialchars($contact['email']) . '">' . 
                                   htmlspecialchars($contact['email']) . '</a></p>';
                }
                if (!empty($contact['website'])) {
                    $contactInfo .= '<p>🌐 Website: <a href="' . htmlspecialchars($contact['website']) . '" target="_blank">' . 
                                   htmlspecialchars($contact['website']) . '</a></p>';
                }
            }
        }
        
        // Featured badge
        $featuredBadge = $event['featured'] ? '<span class="event-featured">⭐ Featured Event</span>' : '';
        
        // Address line
        $addressLine = $address ? '<br>' . $address : '';
        
        // Contact section
        $contactSection = $contactInfo ? '<div class="contact-section"><h3>Contact Information</h3>' . $contactInfo . '</div>' : '';
        
        // External URL button
        $externalUrlButton = '';
        if (!empty($event['external_url'])) {
            $externalUrl = htmlspecialchars($event['external_url']);
            $externalUrlButton = '<a href="' . $externalUrl . '" target="_blank" class="action-button">🔗 Visit Event Website</a>';
        }
        
        // Google Calendar URL
        $calendarUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text=' . urlencode($title)
            . '&dates=' . $startDate->format('Ymd\THis') . '/' . ($endDate ? $endDate->format('Ymd\THis') : $startDate->format('Ymd\THis'))
            . '&location=' . urlencode($location . ' ' . $address)
            . '&details=' . urlencode(strip_tags($event['description'] ?? ''));
        
        // Map section
        $mapSection = '';
        if ($event['latitude'] && $event['longitude']) {
            $lat = $event['latitude'];
            $lng = $event['longitude'];
            $mapSection = <<<HTML
            <div class="map-container" style="height: 400px; border-radius: 15px; overflow: hidden; margin: 20px 0;">
                <div id="event-map" style="height: 100%;"></div>
            </div>
            <script>
                function initEventMap() {
                    const eventLocation = { lat: {$lat}, lng: {$lng} };
                    const map = new google.maps.Map(document.getElementById('event-map'), {
                        zoom: 15,
                        center: eventLocation,
                        styles: [{
                            "featureType": "poi",
                            "elementType": "labels",
                            "stylers": [{ "visibility": "off" }]
                        }]
                    });
                    
                    new google.maps.Marker({
                        position: eventLocation,
                        map: map,
                        title: '{$title}'
                    });
                }
            </script>
            <script async defer src="https://maps.googleapis.com/maps/api/js?key={$googleMapsApiKey}&callback=initEventMap"></script>
HTML;
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - YakimaFinds Events</title>
    <link rel="icon" type="image/x-icon" href="{$basePath}/favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: white;
            text-decoration: none;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            transition: background 0.3s;
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .event-detail {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .event-header {
            margin-bottom: 30px;
        }
        
        .event-title {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .event-featured {
            display: inline-block;
            background: #f39c12;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .event-meta {
            display: grid;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .meta-item {
            display: flex;
            align-items: flex-start;
            color: #555;
        }
        
        .meta-icon {
            font-size: 1.2rem;
            margin-right: 10px;
            color: #667eea;
        }
        
        .event-description {
            margin-bottom: 30px;
            line-height: 1.8;
            color: #444;
        }
        
        .event-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .action-button {
            display: inline-flex;
            align-items: center;
            padding: 12px 25px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            transition: all 0.3s;
        }
        
        .action-button:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        .action-button.secondary {
            background: #6c757d;
        }
        
        .action-button.secondary:hover {
            background: #5a6268;
        }
        
        .contact-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-top: 30px;
        }
        
        .contact-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .contact-section p {
            margin-bottom: 10px;
        }
        
        .contact-section a {
            color: #667eea;
            text-decoration: none;
        }
        
        .contact-section a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .event-detail {
                padding: 25px;
            }
            
            .event-title {
                font-size: 1.8rem;
            }
            
            .event-actions {
                flex-direction: column;
            }
            
            .action-button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="{$basePath}/events" class="back-link">
            ← Back to Events
        </a>
        
        <div class="event-detail">
            <div class="event-header">
                $featuredBadge
                <h1 class="event-title">{$title}</h1>
            </div>
            
            <div class="event-meta">
                <div class="meta-item">
                    <span class="meta-icon">📅</span>
                    <div>
                        <strong>{$eventDate}</strong><br>
                        {$eventTime}
                    </div>
                </div>
                
                <div class="meta-item">
                    <span class="meta-icon">📍</span>
                    <div>
                        <strong>{$location}</strong>
                        $addressLine
                    </div>
                </div>
            </div>
            
            <div class="event-description">
                <h3 style="margin-bottom: 15px; color: #2c3e50;">About This Event</h3>
                {$description}
            </div>
            
            {$mapSection}
            
            $contactSection
            
            <div class="event-actions">
                $externalUrlButton
                <a href="{$calendarUrl}" target="_blank" class="action-button secondary">📅 Add to Calendar</a>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Render 404 error page
     */
    private function render404Page(): string
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Not Found - YakimaFinds</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            max-width: 500px;
        }
        h1 { color: #e74c3c; margin-bottom: 20px; }
        p { color: #555; margin-bottom: 30px; }
        a {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            transition: background 0.3s;
        }
        a:hover { background: #5a67d8; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>Event Not Found</h1>
        <p>Sorry, the event you're looking for doesn't exist or may have been removed.</p>
        <a href="{$basePath}/events">Back to Events</a>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Render 500 error page
     */
    private function render500Page(): string
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - YakimaFinds</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            max-width: 500px;
        }
        h1 { color: #e74c3c; margin-bottom: 20px; }
        p { color: #555; margin-bottom: 30px; }
        a {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            transition: background 0.3s;
        }
        a:hover { background: #5a67d8; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>Oops! Something went wrong</h1>
        <p>We're sorry, but an error occurred while processing your request. Please try again later.</p>
        <a href="{$basePath}/events">Back to Events</a>
    </div>
</body>
</html>
HTML;
    }
}