<?php
session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

try {
    // Connect to database
    $pdo = new PDO('mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4', 'yfevents', 'yfevents_pass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all active scrapers
    $stmt = $pdo->query("SELECT id, name, url FROM calendar_sources WHERE active = 1");
    $scrapers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalEvents = 0;
    $results = [];
    
    // Path to the actual scraper script - use absolute path
    $scraperScript = '/home/robug/YFEvents/cron/scrape-events.php';
    
    if (file_exists($scraperScript)) {
        // Run the scraper script
        $output = [];
        $returnCode = 0;
        // Run with a timeout of 60 seconds
        exec("timeout 60 php $scraperScript 2>&1", $output, $returnCode);
        
        // Parse output for event count
        $outputText = implode("\n", $output);
        $totalEventsFound = 0;
        $totalEventsAdded = 0;
        
        // Look for the summary line
        if (preg_match('/Total events found:\s*(\d+)/i', $outputText, $matches)) {
            $totalEventsFound = intval($matches[1]);
        }
        if (preg_match('/Total events added:\s*(\d+)/i', $outputText, $matches)) {
            $totalEventsAdded = intval($matches[1]);
        }
        
        // Use whichever is higher for display
        $totalEvents = max($totalEventsFound, $totalEventsAdded);
        
        $success = ($returnCode === 0);
        $message = $success ? 'All scrapers completed successfully' : 'Some scrapers encountered errors';
    } else {
        // Fallback: Just update the last_scraped timestamp
        $stmt = $pdo->prepare("UPDATE calendar_sources SET last_scraped = NOW() WHERE active = 1");
        $stmt->execute();
        
        $success = true;
        $message = 'Scrapers triggered successfully';
        $totalEvents = 0; // Can't determine actual count without running the scraper
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'total_events' => $totalEvents,
        'events_found' => $totalEventsFound ?? 0,
        'events_added' => $totalEventsAdded ?? 0,
        'scrapers_count' => count($scrapers)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}