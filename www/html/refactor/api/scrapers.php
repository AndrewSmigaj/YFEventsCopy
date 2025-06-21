<?php
/**
 * Scrapers API endpoint
 * Provides scraper management functionality
 */

require_once __DIR__ . '/../admin/bootstrap.php';

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
    // Get all scrapers
    $query = "SELECT 
        id,
        name,
        url,
        scrape_type as type,
        scrape_config as config,
        last_scraped as last_run,
        IF(active = 1, 'active', 'inactive') as status,
        created_at
    FROM calendar_sources
    ORDER BY name ASC";
    
    $stmt = $db->query($query);
    $scrapers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON config for each scraper
    foreach ($scrapers as &$scraper) {
        if (!empty($scraper['config'])) {
            $scraper['config'] = json_decode($scraper['config'], true);
        } else {
            $scraper['config'] = [];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $scrapers
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading scrapers: ' . $e->getMessage()
    ]);
}