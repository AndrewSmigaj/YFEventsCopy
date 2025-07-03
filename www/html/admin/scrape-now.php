<?php
// Immediate scraping endpoint - simplified version
require_once '../../../config/database.php';

// Authentication check
require_once dirname(__DIR__, 3) . '/includes/admin_auth_required.php';

header('Content-Type: application/json');

// Set longer execution time for scraping
set_time_limit(300);

try {
    $sourceId = $_GET['source_id'] ?? null;
    
    if (!$sourceId) {
        throw new Exception('No source ID provided');
    }
    
    // Get source details
    $stmt = $pdo->prepare("SELECT * FROM calendar_sources WHERE id = ? AND active = 1");
    $stmt->execute([$sourceId]);
    $source = $stmt->fetch();
    
    if (!$source) {
        throw new Exception('Source not found or inactive');
    }
    
    // For now, just test the Yakima Valley scraper
    if ($source['scrape_type'] === 'yakima_valley') {
        require_once '../../../src/Scrapers/YakimaValleyEventScraper.php';
        
        // Fetch the page content
        $content = file_get_contents($source['url']);
        if (!$content) {
            throw new Exception('Failed to fetch content from URL');
        }
        
        $config = json_decode($source['scrape_config'], true) ?: [];
        $baseUrl = $config['base_url'] ?? 'https://visityakima.com';
        $year = $config['year'] ?? date('Y');
        
        // Parse events
        $events = \YFEvents\Scrapers\YakimaValleyEventScraper::parseEvents($content, $baseUrl, $year);
        
        // Insert events into database
        $eventsAdded = 0;
        $stmt = $pdo->prepare("
            INSERT INTO events (title, description, start_datetime, end_datetime, location, address, external_url, source_id, status, created_at)
            VALUES (:title, :description, :start_datetime, :end_datetime, :location, :address, :external_url, :source_id, 'approved', NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        
        foreach ($events as $event) {
            try {
                $stmt->execute([
                    'title' => $event['title'],
                    'description' => $event['description'],
                    'start_datetime' => $event['start_datetime'],
                    'end_datetime' => $event['end_datetime'],
                    'location' => $event['full_location'] ?? $event['location'] ?? '',
                    'address' => $event['address'] ?? '',
                    'external_url' => $event['external_url'] ?? '',
                    'source_id' => $sourceId
                ]);
                $eventsAdded++;
            } catch (Exception $e) {
                // Skip duplicates
            }
        }
        
        // Update last scraped time
        $pdo->prepare("UPDATE calendar_sources SET last_scraped = NOW() WHERE id = ?")->execute([$sourceId]);
        
        echo json_encode([
            'success' => true,
            'message' => "Found " . count($events) . " events, added $eventsAdded new events",
            'events_found' => count($events),
            'events_added' => $eventsAdded
        ]);
    } else {
        throw new Exception('Scraper type not implemented in simplified version');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>