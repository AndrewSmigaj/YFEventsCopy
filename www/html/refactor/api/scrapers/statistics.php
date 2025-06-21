<?php
/**
 * Scrapers Statistics API endpoint
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

try {
    $statistics = [];
    
    // Get total sources
    $stmt = $db->query("SELECT COUNT(*) FROM calendar_sources");
    $statistics['total_sources'] = (int)$stmt->fetchColumn();
    
    // Get active sources
    $stmt = $db->query("SELECT COUNT(*) FROM calendar_sources WHERE active = 1");
    $statistics['active_sources'] = (int)$stmt->fetchColumn();
    
    // Get scraped sources (last 24 hours)
    $stmt = $db->query("SELECT COUNT(*) FROM calendar_sources WHERE last_scraped > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $statistics['scraped_sources'] = (int)$stmt->fetchColumn();
    
    // Get recent scrapes (last 7 days) - check if scraping_logs table exists
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM scraping_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $statistics['recent_scrapes'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // If table doesn't exist or column is different, use calendar_sources last_scraped
        $stmt = $db->query("SELECT COUNT(*) FROM calendar_sources WHERE last_scraped > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $statistics['recent_scrapes'] = (int)$stmt->fetchColumn();
    }
    
    // Get scraper types breakdown
    $stmt = $db->query("SELECT scrape_type, COUNT(*) as count FROM calendar_sources GROUP BY scrape_type");
    $statistics['by_type'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statistics['by_type'][$row['scrape_type']] = (int)$row['count'];
    }
    
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