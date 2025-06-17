<?php
/**
 * Events Admin API Endpoint
 * Provides event data and statistics for the admin interface
 */

require_once __DIR__ . '/../../admin/auth_check.php';
require_once __DIR__ . '/../../../../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

// Handle different request types
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

try {
    if (strpos($request_uri, '/statistics') !== false) {
        // Statistics endpoint
        handleStatistics();
    } else {
        // Events list endpoint
        handleEventsList();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'data' => []
    ]);
}

function handleStatistics() {
    global $pdo;
    
    try {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'featured' => 0,
            'today' => 0
        ];
        
        $totalStmt = $pdo->query("SELECT COUNT(*) FROM events");
        $stats['total'] = $totalStmt->fetchColumn();
        
        $pendingStmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'pending'");
        $stats['pending'] = $pendingStmt->fetchColumn();
        
        $approvedStmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved'");
        $stats['approved'] = $approvedStmt->fetchColumn();
        
        $featuredStmt = $pdo->query("SELECT COUNT(*) FROM events WHERE featured = 1");
        $stats['featured'] = $featuredStmt->fetchColumn();
        
        $todayStmt = $pdo->query("SELECT COUNT(*) FROM events WHERE DATE(start_datetime) = CURDATE()");
        $stats['today'] = $todayStmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'statistics' => $stats
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading statistics: ' . $e->getMessage(),
            'data' => [
                'statistics' => [
                    'total' => 0,
                    'pending' => 0,
                    'approved' => 0,
                    'featured' => 0,
                    'today' => 0
                ]
            ]
        ]);
    }
}

function handleEventsList() {
    global $pdo;
    
    try {
        // Get filter parameters
        $status = $_GET['status'] ?? 'all';
        $search = $_GET['search'] ?? '';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where = [];
        $params = [];
        
        if ($status !== 'all') {
            $where[] = "e.status = :status";
            $params[':status'] = $status;
        }
        
        if ($search) {
            $where[] = "(e.title LIKE :search OR e.description LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        
        // Get events with source information
        $sql = "SELECT 
            e.id,
            e.title,
            e.description,
            e.start_datetime,
            e.end_datetime,
            e.location,
            e.address,
            e.status,
            e.featured,
            e.latitude,
            e.longitude,
            e.contact_info,
            e.external_url,
            e.source_id,
            e.created_at,
            cs.name as source_name
        FROM events e
        LEFT JOIN calendar_sources cs ON e.source_id = cs.id
        $whereClause
        ORDER BY e.created_at DESC
        LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM events e $whereClause";
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'events' => $events,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading events: ' . $e->getMessage(),
            'data' => [
                'events' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => 20,
                    'total' => 0,
                    'pages' => 0
                ]
            ]
        ]);
    }
}
?>