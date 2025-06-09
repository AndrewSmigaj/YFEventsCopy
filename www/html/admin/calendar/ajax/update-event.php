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
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['event_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$eventId = intval($_POST['event_id']);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$startDatetime = $_POST['start_datetime'] ?? '';
$endDatetime = $_POST['end_datetime'] ?? '';
$location = trim($_POST['location'] ?? '');
$status = $_POST['status'] ?? 'pending';

// Validate required fields
if (empty($title) || empty($startDatetime)) {
    http_response_code(400);
    echo json_encode(['error' => 'Title and start date/time are required']);
    exit;
}

// Validate status
$allowedStatuses = ['pending', 'approved', 'rejected'];
if (!in_array($status, $allowedStatuses)) {
    $status = 'pending';
}

try {
    // Update the event
    $query = "UPDATE events SET 
              title = ?, 
              description = ?, 
              start_datetime = ?, 
              end_datetime = ?, 
              location = ?, 
              status = ?,
              updated_at = NOW()
              WHERE id = ?";
    
    $stmt = $db->prepare($query);
    
    // Handle empty end_datetime
    $endDatetimeValue = !empty($endDatetime) ? $endDatetime : null;
    
    $result = $stmt->execute([
        $title,
        $description,
        $startDatetime,
        $endDatetimeValue,
        $location,
        $status,
        $eventId
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
    } else {
        throw new Exception('Failed to update event');
    }
    
} catch (Exception $e) {
    error_log("Event update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
?>