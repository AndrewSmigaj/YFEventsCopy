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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$scraperId = $input['scraper_id'] ?? 0;

if (!$scraperId) {
    echo json_encode(['success' => false, 'message' => 'Scraper ID required']);
    exit;
}

try {
    // Connect to database
    $pdo = new PDO('mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4', 'yfevents', 'yfevents_pass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get scraper details
    $stmt = $pdo->prepare("SELECT * FROM calendar_sources WHERE id = ? AND active = 1");
    $stmt->execute([$scraperId]);
    $scraper = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$scraper) {
        echo json_encode(['success' => false, 'message' => 'Scraper not found or inactive']);
        exit;
    }
    
    // Path to the actual scraper script
    $scraperScript = realpath(__DIR__ . '/../../../cron/scrape-events.php');
    
    if (!file_exists($scraperScript)) {
        $scraperScript = realpath(__DIR__ . '/../../../../cron/scrape-events.php');
    }
    
    $eventsCount = 0;
    
    if (file_exists($scraperScript)) {
        // Run the scraper for this specific source
        $output = [];
        $returnCode = 0;
        $command = "php $scraperScript --source-id=$scraperId 2>&1";
        exec($command, $output, $returnCode);
        
        // Parse output for event count
        $outputText = implode("\n", $output);
        if (preg_match('/(\d+)\s+events?\s+(?:found|added|scraped)/i', $outputText, $matches)) {
            $eventsCount = intval($matches[1]);
        }
        
        $success = ($returnCode === 0);
    } else {
        // Fallback: Just update the timestamp
        $stmt = $pdo->prepare("UPDATE calendar_sources SET last_scraped = NOW() WHERE id = ?");
        $stmt->execute([$scraperId]);
        $success = true;
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Scraper completed successfully' : 'Scraper encountered errors',
        'events_count' => $eventsCount,
        'scraper_name' => $scraper['name']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}