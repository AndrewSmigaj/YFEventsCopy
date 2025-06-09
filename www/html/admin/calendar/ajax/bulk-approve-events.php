<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../../../config/database.php';

header('Content-Type: application/json');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['event_ids']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$eventIds = $input['event_ids'];
$action = $input['action'];

// Validate event IDs
if (!is_array($eventIds) || empty($eventIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event IDs']);
    exit;
}

// Validate action
$allowedActions = ['approve', 'reject', 'delete'];
if (!in_array($action, $allowedActions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

try {
    $db->beginTransaction();
    
    $successCount = 0;
    $totalCount = count($eventIds);
    
    foreach ($eventIds as $eventId) {
        $eventId = intval($eventId);
        if ($eventId <= 0) continue;
        
        if ($action === 'delete') {
            // Delete the event
            $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
            $result = $stmt->execute([$eventId]);
        } else {
            // Update status (approve/reject)
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $db->prepare("UPDATE events SET status = ? WHERE id = ?");
            $result = $stmt->execute([$newStatus, $eventId]);
        }
        
        if ($result) {
            $successCount++;
        }
    }
    
    $db->commit();
    
    if ($successCount === $totalCount) {
        $actionText = $action === 'approve' ? 'approved' : 
                     ($action === 'reject' ? 'rejected' : 'deleted');
        echo json_encode([
            'success' => true, 
            'message' => "Successfully $actionText $successCount event(s)"
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => "Partially successful: $successCount of $totalCount events processed",
            'warning' => true
        ]);
    }
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Bulk event action error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
?>