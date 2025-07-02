<?php
/**
 * Database Bridge for Browser Automation Scraper
 * ==============================================
 * 
 * Provides database operations for the Node.js browser scraper
 * using the existing YFEvents refactored configuration and architecture.
 */

declare(strict_types=1);

// Autoload the refactored system
// Bootstrap application and load all dependencies
require_once __DIR__ . '/../../config/app-root.php';

use YFEvents\Infrastructure\Config\ConfigManager;

header('Content-Type: application/json');

class DatabaseBridge
{
    private PDO $pdo;
    
    public function __construct()
    {
        $this->setupDatabase();
    }
    
    private function setupDatabase(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $dbConfig = $config['database'];
        
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    
    public function saveEvent(array $eventData): array
    {
        try {
            // Check if event already exists
            $stmt = $this->pdo->prepare("SELECT id FROM events WHERE external_event_id = ?");
            $stmt->execute([$eventData['external_event_id']]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => true,
                    'action' => 'skipped',
                    'message' => 'Event already exists',
                    'title' => $eventData['title']
                ];
            }
            
            // Insert new event
            $sql = "INSERT INTO events (
                title, start_datetime, end_datetime, location, description, 
                status, external_event_id, source_url, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $eventData['title'],
                $eventData['start_date'] ?: null,
                $eventData['end_date'] ?: null,
                $eventData['location'],
                $eventData['description'],
                $eventData['external_event_id'],
                $eventData['url']
            ]);
            
            if ($result) {
                $eventId = $this->pdo->lastInsertId();
                return [
                    'success' => true,
                    'action' => 'inserted',
                    'id' => $eventId,
                    'message' => 'Event saved successfully',
                    'title' => $eventData['title']
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to insert event',
                'title' => $eventData['title']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'title' => $eventData['title'] ?? 'Unknown'
            ];
        }
    }
    
    public function getEventStats(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_events,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_events,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_events,
                    COUNT(CASE WHEN external_event_id LIKE 'eventbrite_%' THEN 1 END) as eventbrite_events,
                    COUNT(CASE WHEN external_event_id LIKE 'meetup_%' THEN 1 END) as meetup_events,
                    COUNT(CASE WHEN external_event_id LIKE 'browser_%' THEN 1 END) as browser_scraped_events,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS) THEN 1 END) as recent_events
                FROM events
            ");
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            return [
                'error' => 'Failed to get stats: ' . $e->getMessage()
            ];
        }
    }
    
    public function testConnection(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM events");
            $result = $stmt->fetch();
            
            return [
                'success' => true,
                'message' => 'Database connection successful',
                'total_events' => $result['count']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }
}

// Handle API requests
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'test';

$bridge = new DatabaseBridge();

try {
    switch ($action) {
        case 'test':
            echo json_encode($bridge->testConnection());
            break;
            
        case 'stats':
            echo json_encode($bridge->getEventStats());
            break;
            
        case 'save':
            if ($method !== 'POST') {
                throw new Exception('Save action requires POST method');
            }
            
            $eventData = json_decode(file_get_contents('php://input'), true);
            if (!$eventData) {
                throw new Exception('Invalid JSON data');
            }
            
            echo json_encode($bridge->saveEvent($eventData));
            break;
            
        case 'batch-save':
            if ($method !== 'POST') {
                throw new Exception('Batch save action requires POST method');
            }
            
            $eventsData = json_decode(file_get_contents('php://input'), true);
            if (!is_array($eventsData)) {
                throw new Exception('Invalid JSON array');
            }
            
            $results = [
                'success' => 0,
                'skipped' => 0,
                'failed' => 0,
                'details' => []
            ];
            
            foreach ($eventsData as $eventData) {
                $result = $bridge->saveEvent($eventData);
                $results['details'][] = $result;
                
                if ($result['success']) {
                    if ($result['action'] === 'inserted') {
                        $results['success']++;
                    } else {
                        $results['skipped']++;
                    }
                } else {
                    $results['failed']++;
                }
            }
            
            echo json_encode($results);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>