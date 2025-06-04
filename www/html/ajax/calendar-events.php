<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';

use YakimaFinds\Models\EventModel;
use YakimaFinds\Models\ShopModel;
use YakimaFinds\Utils\SystemSettings;

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Use database connection from config
    $eventModel = new EventModel($db);
    $shopModel = new ShopModel($db);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    
    // Route the request
    switch ($method) {
        case 'GET':
            handleGetRequest($eventModel, $shopModel, $pathParts);
            break;
        case 'POST':
            handlePostRequest($eventModel, $shopModel, $pathParts);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}

/**
 * Handle GET requests
 */
function handleGetRequest($eventModel, $shopModel, $pathParts) {
    $endpoint = end($pathParts);
    
    switch ($endpoint) {
        case 'events':
            getEvents($eventModel);
            break;
        case 'today':
            getTodaysEvents($eventModel);
            break;
        case 'nearby':
            getNearbyEvents($eventModel);
            break;
        case 'shops':
            getShops($shopModel);
            break;
        case 'categories':
            getCategories($shopModel);
            break;
        default:
            // Check if it's a specific event ID
            if (is_numeric($endpoint)) {
                getEventById($eventModel, $endpoint);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;
    }
}

/**
 * Handle POST requests
 */
function handlePostRequest($eventModel, $shopModel, $pathParts) {
    $endpoint = end($pathParts);
    
    switch ($endpoint) {
        case 'submit':
            submitEvent($eventModel);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
}

/**
 * Get events with filtering
 */
function getEvents($eventModel) {
    $filters = [];
    
    // Date filters
    if (isset($_GET['start_date'])) {
        $filters['start_date'] = $_GET['start_date'];
    }
    
    if (isset($_GET['end_date'])) {
        $filters['end_date'] = $_GET['end_date'];
    }
    
    // Location filters
    if (isset($_GET['latitude']) && isset($_GET['longitude'])) {
        $filters['latitude'] = (float)$_GET['latitude'];
        $filters['longitude'] = (float)$_GET['longitude'];
        $filters['radius'] = isset($_GET['radius']) ? (float)$_GET['radius'] : 10;
    }
    
    // Category filter
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $filters['category'] = $_GET['category'];
    }
    
    // Featured filter
    if (isset($_GET['featured'])) {
        $filters['featured'] = $_GET['featured'] === 'true' ? 1 : 0;
    }
    
    // Status filter - check system setting for unapproved events
    if (SystemSettings::showUnapprovedEvents($db)) {
        $filters['include_unapproved'] = true;
    } else {
        $filters['status'] = 'approved';
    }
    
    // Pagination
    if (isset($_GET['limit'])) {
        $filters['limit'] = (int)$_GET['limit'];
    }
    
    if (isset($_GET['offset'])) {
        $filters['offset'] = (int)$_GET['offset'];
    }
    
    // Search
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        // Add search functionality to the model if needed
        $filters['search'] = $_GET['search'];
    }
    
    $events = $eventModel->getEvents($filters);
    
    // Process events for API response
    $processedEvents = array_map(function($event) use ($db) {
        // Parse JSON fields
        $event['contact_info'] = $event['contact_info'] ? json_decode($event['contact_info'], true) : null;
        
        // Format dates
        $event['start_datetime_formatted'] = date('c', strtotime($event['start_datetime']));
        if ($event['end_datetime']) {
            $event['end_datetime_formatted'] = date('c', strtotime($event['end_datetime']));
        }
        
        // Add image URL if available
        if ($event['primary_image']) {
            $event['image_url'] = '/uploads/events/' . $event['primary_image'];
        }
        
        // Add unapproved event indicator and disclaimer
        if ($event['status'] === 'pending') {
            $event['is_unapproved'] = true;
            $event['disclaimer'] = SystemSettings::getUnapprovedEventsDisclaimer($db);
        } else {
            $event['is_unapproved'] = false;
        }
        
        return $event;
    }, $events);
    
    echo json_encode([
        'success' => true,
        'events' => $processedEvents,
        'total' => count($processedEvents)
    ]);
}

/**
 * Get today's events
 */
function getTodaysEvents($eventModel) {
    $events = $eventModel->getTodaysEvents();
    
    $processedEvents = array_map(function($event) {
        $event['contact_info'] = $event['contact_info'] ? json_decode($event['contact_info'], true) : null;
        $event['start_datetime_formatted'] = date('c', strtotime($event['start_datetime']));
        
        if ($event['primary_image']) {
            $event['image_url'] = '/uploads/events/' . $event['primary_image'];
        }
        
        return $event;
    }, $events);
    
    echo json_encode([
        'success' => true,
        'events' => $processedEvents
    ]);
}

/**
 * Get nearby events
 */
function getNearbyEvents($eventModel) {
    $latitude = isset($_GET['latitude']) ? (float)$_GET['latitude'] : null;
    $longitude = isset($_GET['longitude']) ? (float)$_GET['longitude'] : null;
    $radius = isset($_GET['radius']) ? (float)$_GET['radius'] : 10;
    
    if (!$latitude || !$longitude) {
        http_response_code(400);
        echo json_encode(['error' => 'Latitude and longitude are required']);
        return;
    }
    
    $events = $eventModel->getNearbyEvents($latitude, $longitude, $radius);
    
    $processedEvents = array_map(function($event) {
        $event['contact_info'] = $event['contact_info'] ? json_decode($event['contact_info'], true) : null;
        $event['start_datetime_formatted'] = date('c', strtotime($event['start_datetime']));
        
        if ($event['primary_image']) {
            $event['image_url'] = '/uploads/events/' . $event['primary_image'];
        }
        
        return $event;
    }, $events);
    
    echo json_encode([
        'success' => true,
        'events' => $processedEvents
    ]);
}

/**
 * Get event by ID
 */
function getEventById($eventModel, $id) {
    $event = $eventModel->getEventById($id);
    
    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        return;
    }
    
    // Parse JSON fields
    $event['contact_info'] = $event['contact_info'] ? json_decode($event['contact_info'], true) : null;
    
    // Format dates
    $event['start_datetime_formatted'] = date('c', strtotime($event['start_datetime']));
    if ($event['end_datetime']) {
        $event['end_datetime_formatted'] = date('c', strtotime($event['end_datetime']));
    }
    
    // Add image URLs
    if ($event['images']) {
        $images = explode(',', $event['images']);
        $event['image_urls'] = array_map(function($image) {
            return '/uploads/events/' . $image;
        }, $images);
    }
    
    echo json_encode([
        'success' => true,
        'event' => $event
    ]);
}

/**
 * Get shops
 */
function getShops($shopModel) {
    $filters = [];
    
    // Location filters
    if (isset($_GET['latitude']) && isset($_GET['longitude'])) {
        $filters['latitude'] = (float)$_GET['latitude'];
        $filters['longitude'] = (float)$_GET['longitude'];
        $filters['radius'] = isset($_GET['radius']) ? (float)$_GET['radius'] : 10;
    }
    
    // Category filter
    if (isset($_GET['category']) && !empty($_GET['category'])) {
        $filters['category'] = $_GET['category'];
    }
    
    // Featured filter
    if (isset($_GET['featured'])) {
        $filters['featured'] = $_GET['featured'] === 'true' ? 1 : 0;
    }
    
    // Status filter (default to active for public API)
    $filters['status'] = 'active';
    
    // Search
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $filters['search'] = $_GET['search'];
    }
    
    // Pagination
    if (isset($_GET['limit'])) {
        $filters['limit'] = (int)$_GET['limit'];
    }
    
    if (isset($_GET['offset'])) {
        $filters['offset'] = (int)$_GET['offset'];
    }
    
    $shops = $shopModel->getShops($filters);
    
    // Process shops for API response
    $processedShops = array_map(function($shop) {
        // Parse JSON fields
        $shop['operating_hours'] = $shop['operating_hours'] ? json_decode($shop['operating_hours'], true) : null;
        $shop['payment_methods'] = $shop['payment_methods'] ? json_decode($shop['payment_methods'], true) : null;
        $shop['amenities'] = $shop['amenities'] ? json_decode($shop['amenities'], true) : null;
        
        // Add image URL if available
        if ($shop['primary_image']) {
            $shop['image_url'] = '/uploads/shops/' . $shop['primary_image'];
        }
        
        return $shop;
    }, $shops);
    
    echo json_encode([
        'success' => true,
        'shops' => $processedShops,
        'total' => count($processedShops)
    ]);
}

/**
 * Get shop categories
 */
function getCategories($shopModel) {
    $categories = $shopModel->getCategoryTree();
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
}

/**
 * Submit new event
 */
function submitEvent($eventModel) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }
    
    // Validate required fields
    $required = ['title', 'start_datetime'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '{$field}' is required"]);
            return;
        }
    }
    
    // Sanitize data
    $eventData = [
        'title' => trim($data['title']),
        'description' => isset($data['description']) ? trim($data['description']) : null,
        'start_datetime' => $data['start_datetime'],
        'end_datetime' => isset($data['end_datetime']) ? $data['end_datetime'] : null,
        'location' => isset($data['location']) ? trim($data['location']) : null,
        'address' => isset($data['address']) ? trim($data['address']) : null,
        'external_url' => isset($data['external_url']) ? trim($data['external_url']) : null,
        'status' => 'pending' // All public submissions require approval
    ];
    
    // Handle contact info
    if (isset($data['contact_info']) && is_array($data['contact_info'])) {
        $eventData['contact_info'] = $data['contact_info'];
    }
    
    try {
        $eventId = $eventModel->createEvent($eventData);
        
        if ($eventId) {
            echo json_encode([
                'success' => true,
                'message' => 'Event submitted successfully and is pending approval',
                'event_id' => $eventId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create event']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Database connection handled by config/database.php

?>