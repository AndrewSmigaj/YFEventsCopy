<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../../vendor/autoload.php';

try {
    // Get container and services
    $container = require __DIR__ . '/../../../../config/container.php';
    $communicationService = $container->resolve(YFEvents\Application\Services\Communication\CommunicationService::class);
    
    $userId = $_SESSION['user_id'];
    
    // Get all channels with unread counts
    $channelsWithUnread = $communicationService->getUserChannelsWithUnread($userId);
    
    // Calculate total unread
    $totalUnread = 0;
    foreach ($channelsWithUnread as $channelData) {
        $totalUnread += $channelData['unread_count'];
    }
    
    echo json_encode([
        'success' => true,
        'unread' => $totalUnread,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log('Failed to get unread count: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get unread count'
    ]);
}