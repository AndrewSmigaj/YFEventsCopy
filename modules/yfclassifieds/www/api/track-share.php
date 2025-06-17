<?php
/**
 * Track social media shares for classified items
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

require_once __DIR__ . '/../../../../config/database.php';

$itemId = intval($_GET['item_id'] ?? 0);
$platform = $_GET['platform'] ?? 'other';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($itemId > 0) {
    try {
        // Insert share record
        $stmt = $pdo->prepare("INSERT INTO yfc_item_shares (item_id, platform, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([$itemId, $platform, $ipAddress, $userAgent]);
        
        // Update share count on item
        $pdo->exec("UPDATE yfc_items SET share_count = share_count + 1 WHERE id = $itemId");
        
        echo json_encode(['success' => true, 'message' => 'Share tracked']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to track share']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid item ID']);
}
?>