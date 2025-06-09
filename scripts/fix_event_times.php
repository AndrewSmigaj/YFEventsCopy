<?php

/**
 * Script to fix event times for existing events that have default midnight times
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

echo "=== Fixing Event Times for Existing Events ===\n\n";

// Get events with default times (00:00:00 start time)
$stmt = $db->prepare("
    SELECT id, title, start_datetime, end_datetime 
    FROM events 
    WHERE source_id = 1 
    AND TIME(start_datetime) = '00:00:00'
    AND status = 'approved'
    ORDER BY start_datetime DESC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($events) . " events with default midnight times\n\n";

$updateCount = 0;

foreach ($events as $event) {
    $title = strtolower($event['title']);
    $startDate = date('Y-m-d', strtotime($event['start_datetime']));
    $endDate = date('Y-m-d', strtotime($event['end_datetime']));
    
    // Determine appropriate time based on event type
    $timeInfo = getTimeForEventType($title);
    
    // Update times
    $newStartDateTime = $startDate . ' ' . $timeInfo['start'];
    $newEndDateTime = $endDate . ' ' . $timeInfo['end'];
    
    // For multi-day events, keep the original end date but update the time
    if ($startDate !== $endDate) {
        $newEndDateTime = $endDate . ' ' . $timeInfo['end'];
    }
    
    echo "Updating: {$event['title']}\n";
    echo "  Old: {$event['start_datetime']} - {$event['end_datetime']}\n";
    echo "  New: $newStartDateTime - $newEndDateTime\n\n";
    
    $updateStmt = $db->prepare("
        UPDATE events 
        SET start_datetime = ?, end_datetime = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$newStartDateTime, $newEndDateTime, $event['id']]);
    $updateCount++;
}

echo "\nUpdated $updateCount events with appropriate times\n";

function getTimeForEventType($title) {
    // Event type patterns and their typical times
    $patterns = [
        // Morning events
        'breakfast' => ['start' => '08:00:00', 'end' => '10:00:00'],
        'brunch' => ['start' => '10:00:00', 'end' => '13:00:00'],
        'morning' => ['start' => '09:00:00', 'end' => '12:00:00'],
        'sunrise' => ['start' => '06:00:00', 'end' => '08:00:00'],
        
        // Market events (usually morning)
        'farmers market' => ['start' => '08:00:00', 'end' => '13:00:00'],
        'market' => ['start' => '09:00:00', 'end' => '14:00:00'],
        
        // Afternoon events
        'lunch' => ['start' => '12:00:00', 'end' => '14:00:00'],
        'afternoon' => ['start' => '14:00:00', 'end' => '17:00:00'],
        'matinee' => ['start' => '14:00:00', 'end' => '16:00:00'],
        
        // Wine/brewery events
        'wine' => ['start' => '12:00:00', 'end' => '17:00:00'],
        'winery' => ['start' => '12:00:00', 'end' => '17:00:00'],
        'tasting' => ['start' => '12:00:00', 'end' => '17:00:00'],
        'brewery' => ['start' => '14:00:00', 'end' => '20:00:00'],
        'brewing' => ['start' => '14:00:00', 'end' => '20:00:00'],
        
        // Evening events
        'dinner' => ['start' => '18:00:00', 'end' => '21:00:00'],
        'happy hour' => ['start' => '17:00:00', 'end' => '19:00:00'],
        'trivia' => ['start' => '19:00:00', 'end' => '22:00:00'],
        'bingo' => ['start' => '19:00:00', 'end' => '22:00:00'],
        'comedy' => ['start' => '20:00:00', 'end' => '22:00:00'],
        'concert' => ['start' => '19:00:00', 'end' => '22:00:00'],
        'live music' => ['start' => '19:00:00', 'end' => '22:00:00'],
        'music' => ['start' => '19:00:00', 'end' => '22:00:00'],
        'show' => ['start' => '19:00:00', 'end' => '22:00:00'],
        'night' => ['start' => '20:00:00', 'end' => '23:00:00'],
        'party' => ['start' => '20:00:00', 'end' => '23:00:00'],
        
        // Tour events
        'tour' => ['start' => '10:00:00', 'end' => '12:00:00'],
        
        // Fair/Festival events (all day)
        'fair' => ['start' => '10:00:00', 'end' => '20:00:00'],
        'festival' => ['start' => '10:00:00', 'end' => '20:00:00'],
        
        // Art/Gallery events
        'gallery' => ['start' => '10:00:00', 'end' => '17:00:00'],
        'exhibit' => ['start' => '10:00:00', 'end' => '17:00:00'],
        'art' => ['start' => '10:00:00', 'end' => '17:00:00'],
    ];
    
    // Check each pattern
    foreach ($patterns as $pattern => $times) {
        if (strpos($title, $pattern) !== false) {
            return $times;
        }
    }
    
    // Default times for unknown event types
    return ['start' => '10:00:00', 'end' => '17:00:00'];
}