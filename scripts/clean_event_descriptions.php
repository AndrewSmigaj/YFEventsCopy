<?php

/**
 * Clean up event descriptions that have redundant source attribution
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

echo "=== Cleaning Event Descriptions ===\n\n";

// Find events with redundant source info in descriptions
$stmt = $db->prepare("
    SELECT id, title, description 
    FROM events 
    WHERE source_id = 1 
    AND description LIKE '%Event details from Visit Yakima%'
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($events) . " events with redundant source info in descriptions\n\n";

$cleanedCount = 0;

foreach ($events as $event) {
    // Extract the clean description (everything before the source attribution)
    $description = $event['description'];
    $lines = explode("\n", $description);
    $cleanLines = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        // Stop when we hit the source attribution
        if (strpos($line, '📍 Event details from') !== false || 
            strpos($line, '🔗 View full details:') !== false) {
            break;
        }
        if (!empty($line)) {
            $cleanLines[] = $line;
        }
    }
    
    $cleanDescription = implode("\n", $cleanLines);
    
    // Update the event
    if ($cleanDescription !== $description) {
        $updateStmt = $db->prepare("UPDATE events SET description = ? WHERE id = ?");
        $updateStmt->execute([$cleanDescription, $event['id']]);
        
        echo "Cleaned: {$event['title']}\n";
        echo "  Old length: " . strlen($description) . " chars\n";
        echo "  New length: " . strlen($cleanDescription) . " chars\n\n";
        
        $cleanedCount++;
    }
}

echo "Cleaned $cleanedCount event descriptions\n";
echo "Source attribution will now be handled cleanly by the calendar UI\n";