<?php
/**
 * Admin Events API endpoint
 * Provides event management functionality for admin
 */

require_once __DIR__ . '/../../admin/bootstrap.php';

// Get database connection
$db = $GLOBALS['db'] ?? null;
if (!$db) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Set JSON response header
header('Content-Type: application/json');

// Check admin authentication
require_once __DIR__ . '/../auth_check.php';
requireAdminApi();

try {
    // Get pagination and filter parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $status = $_GET['status'] ?? 'all';
    $featured = $_GET['featured'] ?? null;
    $search = $_GET['search'] ?? '';
    $source_id = $_GET['source_id'] ?? null;
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if ($status !== 'all') {
        $whereConditions[] = "status = ?";
        $params[] = $status;
    }
    
    if ($featured !== null) {
        $whereConditions[] = "featured = ?";
        $params[] = filter_var($featured, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($source_id !== null) {
        $whereConditions[] = "source_id = ?";
        $params[] = (int)$source_id;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM events $whereClause";
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalEvents = (int)$stmt->fetchColumn();
    
    // Get events
    $query = "SELECT 
        e.id,
        e.title,
        e.description,
        e.start_datetime,
        e.end_datetime,
        e.location,
        e.address,
        e.latitude,
        e.longitude,
        e.external_url,
        e.source_id,
        e.status,
        e.featured,
        e.external_event_id,
        e.created_at,
        cs.name as source_name,
        cs.scrape_type as source_type
    FROM events e
    LEFT JOIN calendar_sources cs ON e.source_id = cs.id
    $whereClause
    ORDER BY e.created_at DESC
    LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([...$params, $limit, $offset]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format events for display
    foreach ($events as &$event) {
        $event['id'] = (int)$event['id'];
        $event['source_id'] = (int)$event['source_id'];
        $event['featured'] = (bool)$event['featured'];
        $event['latitude'] = $event['latitude'] ? (float)$event['latitude'] : null;
        $event['longitude'] = $event['longitude'] ? (float)$event['longitude'] : null;
        
        // Format dates for display
        if ($event['start_datetime']) {
            $event['start_datetime_formatted'] = date('M j, Y g:i A', strtotime($event['start_datetime']));
        }
        if ($event['end_datetime']) {
            $event['end_datetime_formatted'] = date('M j, Y g:i A', strtotime($event['end_datetime']));
        }
    }
    
    $totalPages = ceil($totalEvents / $limit);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'events' => $events,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_events' => $totalEvents,
                'per_page' => $limit,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading events: ' . $e->getMessage()
    ]);
}