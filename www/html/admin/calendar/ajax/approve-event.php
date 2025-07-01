<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../config/database.php';

use YFEvents\Models\EventModel;

header('Content-Type: application/json');

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['event_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$eventId = intval($_POST['event_id']);
$action = $_POST['action'] ?? 'approve';

try {
    $eventModel = new EventModel($db);
    
    if ($action === 'approve') {
        // Approve the event
        $stmt = $db->prepare("UPDATE events SET status = 'approved' WHERE id = ?");
        $result = $stmt->execute([$eventId]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Event approved successfully']);
        } else {
            throw new Exception('Failed to approve event');
        }
    } elseif ($action === 'reject') {
        // Reject the event
        $stmt = $db->prepare("UPDATE events SET status = 'rejected' WHERE id = ?");
        $result = $stmt->execute([$eventId]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Event rejected']);
        } else {
            throw new Exception('Failed to reject event');
        }
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}