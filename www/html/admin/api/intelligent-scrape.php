<?php
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../config/database.php';

use YakimaFinds\Scrapers\Intelligent\LLMScraper;

header('Content-Type: application/json');

try {
    $db = $pdo; // Use the PDO instance from database.php
    $scraper = new LLMScraper($db);
    
    // Handle GET requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'stats') {
            // Get statistics
            $stats = [
                'total_methods' => 0,
                'total_sessions' => 0,
                'success_rate' => 0,
                'total_events' => 0
            ];
            
            // Total methods
            $stmt = $db->query("SELECT COUNT(*) FROM intelligent_scraper_methods WHERE active = 1");
            $stats['total_methods'] = $stmt->fetchColumn();
            
            // Total sessions
            $stmt = $db->query("SELECT COUNT(*) FROM intelligent_scraper_sessions");
            $stats['total_sessions'] = $stmt->fetchColumn();
            
            // Success rate
            $stmt = $db->query("
                SELECT 
                    COUNT(CASE WHEN status IN ('events_found', 'approved') THEN 1 END) as success,
                    COUNT(*) as total
                FROM intelligent_scraper_sessions
                WHERE status != 'analyzing'
            ");
            $result = $stmt->fetch();
            if ($result['total'] > 0) {
                $stats['success_rate'] = round(($result['success'] / $result['total']) * 100);
            }
            
            // Total events found
            $stmt = $db->query("
                SELECT SUM(JSON_LENGTH(found_events)) 
                FROM intelligent_scraper_sessions 
                WHERE found_events IS NOT NULL
            ");
            $stats['total_events'] = $stmt->fetchColumn() ?: 0;
            
            // Recent sessions
            $stmt = $db->query("
                SELECT id, url, status, created_at
                FROM intelligent_scraper_sessions
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $recent_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'recent_sessions' => $recent_sessions
            ]);
            exit;
        }
    }
    
    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if it's a file upload or JSON request
        $isFileUpload = isset($_POST['action']) && $_POST['action'] === 'batch_upload';
        
        if ($isFileUpload) {
            $action = $_POST['action'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
        }
        
        if ($action === 'analyze') {
            $url = $input['url'] ?? '';
            
            if (empty($url)) {
                throw new Exception('URL is required');
            }
            
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid URL format');
            }
            
            // Start analysis session
            $userId = $_SESSION['admin_user_id'] ?? null;
            $sessionId = $scraper->startSession($url, $userId);
            
            // Analyze URL
            $result = $scraper->analyzeUrl($url);
            
            // Ensure result is properly formatted
            if (!isset($result['success'])) {
                $result = ['success' => false, 'error' => 'Unknown error occurred'];
            }
            
            echo json_encode($result);
            exit;
            
        } elseif ($action === 'batch_upload') {
            // Handle CSV upload
            if (!isset($_FILES['csv_file'])) {
                throw new Exception('No file uploaded');
            }
            
            $file = $_FILES['csv_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = 'Upload error: ';
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        $errorMsg .= 'File too large (exceeds upload_max_filesize)';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $errorMsg .= 'File too large (exceeds MAX_FILE_SIZE)';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errorMsg .= 'File upload incomplete';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $errorMsg .= 'No file uploaded';
                        break;
                    default:
                        $errorMsg .= 'Unknown upload error (' . $file['error'] . ')';
                }
                throw new Exception($errorMsg);
            }
            
            $result = handleCSVUpload($file, $db);
            echo json_encode($result);
            exit;
            
        } elseif ($action === 'approve') {
            $sessionId = $input['session_id'] ?? '';
            
            if (empty($sessionId)) {
                throw new Exception('Session ID is required');
            }
            
            $userId = $_SESSION['admin_user_id'] ?? null;
            $result = $scraper->approveMethod($sessionId, $userId);
            
            echo json_encode([
                'success' => true,
                'method_id' => $result['method_id'],
                'source_id' => $result['source_id']
            ]);
            exit;
            
        } elseif ($action === 'test_method') {
            $methodId = $input['method_id'] ?? '';
            $url = $input['url'] ?? '';
            
            if (empty($methodId) || empty($url)) {
                throw new Exception('Method ID and URL are required');
            }
            
            // Test existing method on new URL
            $stmt = $db->prepare("SELECT * FROM intelligent_scraper_methods WHERE id = ?");
            $stmt->execute([$methodId]);
            $method = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$method) {
                throw new Exception('Method not found');
            }
            
            $result = $scraper->applyExistingMethod($url, $method);
            
            echo json_encode($result);
            exit;
        }
    }
    
    // Handle GET requests for batch status and logs
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'batch_status') {
            $batchId = $_GET['batch_id'] ?? '';
            if (!$batchId) {
                throw new Exception('Batch ID required');
            }
            
            $result = getBatchStatus($batchId, $db);
            echo json_encode($result);
            exit;
            
        } elseif ($action === 'view_logs') {
            viewBatchLogs($db);
            exit;
            
        } elseif ($action === 'download_results') {
            $batchId = $_GET['batch_id'] ?? '';
            downloadBatchResults($batchId, $db);
            exit;
        }
    }
    
    throw new Exception('Invalid request');
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Intelligent Scraper API Error: ' . $e->getMessage());
    error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
    error_log('POST data: ' . print_r($_POST, true));
    error_log('FILES data: ' . print_r($_FILES, true));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'post_data' => $_POST,
            'files_data' => array_keys($_FILES),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
        ]
    ]);
}

// Batch processing functions
function handleCSVUpload($file, $db) {
    $filename = $file['name'];
    $tmpPath = $file['tmp_name'];
    
    // Validate CSV
    $handle = fopen($tmpPath, 'r');
    if (!$handle) {
        throw new Exception('Could not read uploaded file');
    }
    
    $urls = [];
    $lineNumber = 0;
    $isFirstLine = true;
    
    while (($data = fgetcsv($handle)) !== FALSE) {
        $lineNumber++;
        
        // Skip header row if it looks like headers
        if ($isFirstLine) {
            $isFirstLine = false;
            if (strtolower($data[0]) === 'title' && strtolower($data[1]) === 'url') {
                continue; // Skip header row
            }
        }
        
        if (count($data) < 2) {
            fclose($handle);
            throw new Exception("Invalid CSV format at line {$lineNumber}. Expected: Title,URL");
        }
        
        $title = trim($data[0]);
        $url = trim($data[1]);
        
        if (empty($title) || empty($url)) {
            continue; // Skip empty lines
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            fclose($handle);
            throw new Exception("Invalid URL at line {$lineNumber}: {$url}");
        }
        
        $urls[] = ['title' => $title, 'url' => $url];
    }
    
    fclose($handle);
    
    if (empty($urls)) {
        throw new Exception('No valid URLs found in CSV file');
    }
    
    // Create batch record
    $stmt = $db->prepare("
        INSERT INTO intelligent_scraper_batches (filename, total_urls, created_by) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$filename, count($urls), $_SESSION['admin_user_id'] ?? null]);
    $batchId = $db->lastInsertId();
    
    // Insert batch items
    $stmt = $db->prepare("
        INSERT INTO intelligent_scraper_batch_items (batch_id, title, url) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($urls as $item) {
        $stmt->execute([$batchId, $item['title'], $item['url']]);
    }
    
    // Log batch creation
    logBatchActivity($db, $batchId, null, 'info', "Batch created with {count($urls)} URLs", json_encode(['filename' => $filename]));
    
    // Start background processing
    startBatchProcessing($batchId, $db);
    
    return [
        'success' => true,
        'batch_id' => $batchId,
        'total_urls' => count($urls),
        'message' => "Uploaded {count($urls)} URLs for processing"
    ];
}

function startBatchProcessing($batchId, $db) {
    // Update batch status
    $stmt = $db->prepare("UPDATE intelligent_scraper_batches SET status = 'processing', started_at = NOW() WHERE id = ?");
    $stmt->execute([$batchId]);
    
    // Start background processing (simulate async)
    ignore_user_abort(true);
    set_time_limit(0);
    
    // Process items one by one
    $stmt = $db->prepare("SELECT id, title, url FROM intelligent_scraper_batch_items WHERE batch_id = ? AND status = 'pending' ORDER BY id");
    $stmt->execute([$batchId]);
    $items = $stmt->fetchAll();
    
    $scraper = new LLMScraper($db);
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($items as $item) {
        try {
            $result = processBatchItem($db, $scraper, $batchId, $item);
            if ($result) {
                $successCount++;
            } else {
                $errorCount++;
            }
        } catch (Exception $e) {
            $errorCount++;
            logBatchActivity($db, $batchId, null, 'error', 
                "Exception processing item {$item['id']}: {$e->getMessage()}", 
                json_encode(['exception' => $e->getTraceAsString()]), $item['url']);
        }
        
        // Update progress
        $stmt = $db->prepare("UPDATE intelligent_scraper_batches SET processed_urls = processed_urls + 1 WHERE id = ?");
        $stmt->execute([$batchId]);
        
        // Adaptive delay based on success rate
        $totalProcessed = $successCount + $errorCount;
        if ($totalProcessed > 3) {
            $successRate = $successCount / $totalProcessed;
            if ($successRate < 0.3) {
                usleep(1000000); // 1 second delay if many failures
            } else {
                usleep(300000); // 0.3 second for good success rate
            }
        } else {
            usleep(500000); // Default 0.5 second
        }
    }
    
    // Mark batch as completed
    $stmt = $db->prepare("UPDATE intelligent_scraper_batches SET status = 'completed', completed_at = NOW() WHERE id = ?");
    $stmt->execute([$batchId]);
    
    logBatchActivity($db, $batchId, null, 'info', 'Batch processing completed');
}

function processBatchItem($db, $scraper, $batchId, $item) {
    $itemId = $item['id'];
    $url = $item['url'];
    $title = $item['title'];
    
    try {
        // Update item status
        $stmt = $db->prepare("UPDATE intelligent_scraper_batch_items SET status = 'processing' WHERE id = ?");
        $stmt->execute([$itemId]);
        
        logBatchActivity($db, $batchId, null, 'info', "Processing: {$title}", json_encode(['url' => $url]), $url);
        
        // Start scraper session
        $sessionId = $scraper->startSession($url, null);
        
        // Update item with session ID
        $stmt = $db->prepare("UPDATE intelligent_scraper_batch_items SET session_id = ? WHERE id = ?");
        $stmt->execute([$sessionId, $itemId]);
        
        // Analyze URL
        $result = $scraper->analyzeUrl($url);
        
        if ($result['success']) {
            $eventsCount = count($result['events'] ?? []);
            
            // Update item as completed
            $stmt = $db->prepare("
                UPDATE intelligent_scraper_batch_items 
                SET status = 'completed', events_found = ?, processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$eventsCount, $itemId]);
            
            // Update batch counters
            $stmt = $db->prepare("
                UPDATE intelligent_scraper_batches 
                SET processed_urls = processed_urls + 1, 
                    success_count = success_count + 1,
                    total_events = total_events + ?
                WHERE id = ?
            ");
            $stmt->execute([$eventsCount, $batchId]);
            
            logBatchActivity($db, $batchId, $sessionId, 'info', 
                "Success: Found {$eventsCount} events for {$title}", 
                json_encode($result), $url);
                
        } else {
            $errorMsg = $result['error'] ?? 'Unknown error';
            
            // Update item as failed
            $stmt = $db->prepare("
                UPDATE intelligent_scraper_batch_items 
                SET status = 'failed', error_message = ?, processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$errorMsg, $itemId]);
            
            // Update batch counters
            $stmt = $db->prepare("
                UPDATE intelligent_scraper_batches 
                SET processed_urls = processed_urls + 1, error_count = error_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$batchId]);
            
            logBatchActivity($db, $batchId, $sessionId, 'error', 
                "Failed: {$title} - {$errorMsg}", 
                json_encode($result), $url);
        }
        
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        
        // Update item as failed
        $stmt = $db->prepare("
            UPDATE intelligent_scraper_batch_items 
            SET status = 'failed', error_message = ?, processed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$errorMsg, $itemId]);
        
        // Update batch counters
        $stmt = $db->prepare("
            UPDATE intelligent_scraper_batches 
            SET processed_urls = processed_urls + 1, error_count = error_count + 1 
            WHERE id = ?
        ");
        $stmt->execute([$batchId]);
        
        logBatchActivity($db, $batchId, null, 'error', 
            "Exception: {$title} - {$errorMsg}", 
            json_encode(['exception' => $e->getTraceAsString()]), $url);
    }
}

function getBatchStatus($batchId, $db) {
    $stmt = $db->prepare("
        SELECT * FROM intelligent_scraper_batches WHERE id = ?
    ");
    $stmt->execute([$batchId]);
    $batch = $stmt->fetch();
    
    if (!$batch) {
        throw new Exception('Batch not found');
    }
    
    return [
        'success' => true,
        'status' => $batch['status'],
        'total' => $batch['total_urls'],
        'processed' => $batch['processed_urls'],
        'success_count' => $batch['success_count'],
        'error_count' => $batch['error_count'],
        'total_events' => $batch['total_events']
    ];
}

function viewBatchLogs($db) {
    header('Content-Type: text/html');
    
    $stmt = $db->query("
        SELECT l.*, b.filename 
        FROM intelligent_scraper_logs l
        LEFT JOIN intelligent_scraper_batches b ON l.batch_id = b.id
        ORDER BY l.created_at DESC 
        LIMIT 1000
    ");
    $logs = $stmt->fetchAll();
    
    echo "<!DOCTYPE html><html><head><title>Batch Processing Logs</title>";
    echo "<style>body{font-family:monospace;} .error{color:red;} .warning{color:orange;} .info{color:blue;} .debug{color:gray;}</style>";
    echo "</head><body><h2>Intelligent Scraper Logs</h2>";
    
    foreach ($logs as $log) {
        $time = date('Y-m-d H:i:s', strtotime($log['created_at']));
        $class = $log['level'];
        echo "<div class='{$class}'>[{$time}] [{$log['level']}] {$log['message']}";
        if ($log['url']) echo " <small>({$log['url']})</small>";
        if ($log['details']) {
            echo "<br><small>" . htmlspecialchars($log['details']) . "</small>";
        }
        echo "</div><br>";
    }
    
    echo "</body></html>";
}

function downloadBatchResults($batchId, $db) {
    $stmt = $db->prepare("
        SELECT bi.title, bi.url, bi.status, bi.events_found, bi.error_message,
               s.found_events
        FROM intelligent_scraper_batch_items bi
        LEFT JOIN intelligent_scraper_sessions s ON bi.session_id = s.id
        WHERE bi.batch_id = ?
        ORDER BY bi.id
    ");
    $stmt->execute([$batchId]);
    $items = $stmt->fetchAll();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="batch_results_' . $batchId . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Title', 'URL', 'Status', 'Events Found', 'Error Message', 'Events JSON']);
    
    foreach ($items as $item) {
        fputcsv($output, [
            $item['title'],
            $item['url'],
            $item['status'],
            $item['events_found'],
            $item['error_message'],
            $item['found_events']
        ]);
    }
    
    fclose($output);
}

function logBatchActivity($db, $batchId, $sessionId, $level, $message, $details = null, $url = null) {
    $stmt = $db->prepare("
        INSERT INTO intelligent_scraper_logs (batch_id, session_id, level, message, details, url) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$batchId, $sessionId, $level, $message, $details, $url]);
}