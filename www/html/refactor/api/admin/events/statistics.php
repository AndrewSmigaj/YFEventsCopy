<?php
/**
 * Admin Events Statistics API endpoint
 */

require_once __DIR__ . '/../../../admin/bootstrap.php';

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
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Admin authentication required']);
    exit;
}

try {
    $statistics = [];
    
    // Total events
    $stmt = $db->query("SELECT COUNT(*) FROM events");
    $statistics['total_events'] = (int)$stmt->fetchColumn();
    
    // Pending events
    $stmt = $db->query("SELECT COUNT(*) FROM events WHERE status = 'pending'");
    $statistics['pending_events'] = (int)$stmt->fetchColumn();
    
    // Approved events
    $stmt = $db->query("SELECT COUNT(*) FROM events WHERE status = 'approved'");
    $statistics['approved_events'] = (int)$stmt->fetchColumn();
    
    // Featured events
    $stmt = $db->query("SELECT COUNT(*) FROM events WHERE featured = 1");
    $statistics['featured_events'] = (int)$stmt->fetchColumn();
    
    // Today's events
    $stmt = $db->query("SELECT COUNT(*) FROM events WHERE DATE(start_datetime) = CURDATE()");
    $statistics['todays_events'] = (int)$stmt->fetchColumn();
    
    // Events by source
    $stmt = $db->query("SELECT cs.name, cs.scrape_type, COUNT(e.id) as count 
                       FROM calendar_sources cs 
                       LEFT JOIN events e ON cs.id = e.source_id 
                       GROUP BY cs.id 
                       ORDER BY count DESC");
    $statistics['by_source'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'statistics' => $statistics
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading statistics: ' . $e->getMessage()
    ]);
}