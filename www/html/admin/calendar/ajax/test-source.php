<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../config/database.php';

use YakimaFinds\Models\CalendarSourceModel;
use YakimaFinds\Scrapers\EventScraper;

header('Content-Type: application/json');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['source_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$sourceId = intval($_POST['source_id']);

try {
    $sourceModel = new CalendarSourceModel($db);
    $source = $sourceModel->getSourceById($sourceId);
    
    if (!$source) {
        throw new Exception('Source not found');
    }
    
    // Create scraper instance
    $scraper = new EventScraper($db);
    
    // Test the source with intelligent optimization
    $testResult = $scraper->testAndOptimizeSource($sourceId, true);
    
    if (!$testResult['success']) {
        throw new Exception($testResult['error']);
    }
    
    $eventCount = count($testResult['events']);
    $message = $testResult['message'];
    
    // Add optimization info if it was performed
    $optimizationInfo = [];
    if (isset($testResult['optimization'])) {
        $optimizationInfo = [
            'strategy' => $testResult['optimization']['strategy'] ?? 'unknown',
            'optimized' => true
        ];
        $message .= " (Automatically optimized using {$optimizationInfo['strategy']} strategy)";
    }
    
    // Return test results
    echo json_encode([
        'success' => true,
        'source' => [
            'id' => $source['id'],
            'name' => $source['name'],
            'type' => $source['scrape_type'],
            'url' => $source['url']
        ],
        'results' => [
            'event_count' => $eventCount,
            'sample_events' => array_slice($testResult['events'], 0, 3), // Show first 3 events as sample
            'optimization' => $optimizationInfo,
            'test_time' => date('Y-m-d H:i:s')
        ],
        'message' => $message
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}