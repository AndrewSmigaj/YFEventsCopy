<?php
/**
 * Run specific scrapers
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
    $input = json_decode(file_get_contents('php://input'), true);
    $sourceIds = $input['source_ids'] ?? [];
    
    if (empty($sourceIds)) {
        throw new Exception('No source IDs provided');
    }
    
    $results = [];
    
    foreach ($sourceIds as $sourceId) {
        $sourceId = (int)$sourceId;
        
        // Get source details
        $stmt = $db->prepare("SELECT * FROM calendar_sources WHERE id = ?");
        $stmt->execute([$sourceId]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$source) {
            $results[] = [
                'source_id' => $sourceId,
                'success' => false,
                'message' => 'Source not found'
            ];
            continue;
        }
        
        // Include the scraper runner
        require_once __DIR__ . '/../../scripts/run_scraper.php';
        
        // Run the actual scraper
        $result = runScraper($sourceId, $db);
        $results[] = $result;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'results' => $results
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error running scrapers: ' . $e->getMessage()
    ]);
}