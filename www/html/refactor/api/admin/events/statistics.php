<?php
/**
 * Events Statistics API Endpoint
 */

require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../../../../../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

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
?>