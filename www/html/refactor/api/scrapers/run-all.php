<?php
/**
 * Run all active scrapers
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
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Admin authentication required']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get all active scrapers
    $stmt = $db->query("SELECT id, name FROM calendar_sources WHERE active = 1");
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalEventsFound = 0;
    $totalEventsAdded = 0;
    $results = [];
    
    // Include the scraper runner
    require_once __DIR__ . '/../../scripts/run_scraper.php';
    
    foreach ($sources as $source) {
        // Run the actual scraper
        $result = runScraper($source['id'], $db);
        
        if ($result['success']) {
            $totalEventsFound += $result['events_found'];
            $totalEventsAdded += $result['events_added'];
        }
        
        $results[] = $result;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_sources' => count($sources),
            'total_events_found' => $totalEventsFound,
            'total_events_added' => $totalEventsAdded,
            'results' => $results
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error running all scrapers: ' . $e->getMessage()
    ]);
}