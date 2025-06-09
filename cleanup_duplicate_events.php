<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';

use YakimaFinds\Models\EventModel;

echo "Event Duplicate Cleanup Tool\n";
echo "============================\n\n";

$eventModel = new EventModel($db);

// First, run a dry run to see what would be cleaned up
echo "Analyzing duplicate events (dry run)...\n";
$dryRunResults = $eventModel->cleanupDuplicates(true);

echo "Found {$dryRunResults['groups_found']} groups of duplicate events\n";
echo "Total duplicate events to remove: {$dryRunResults['events_to_remove']}\n\n";

if ($dryRunResults['events_to_remove'] > 0) {
    echo "Examples of duplicates found:\n";
    
    // Show some examples
    $stmt = $db->query("
        SELECT title, start_datetime, COUNT(*) as count, GROUP_CONCAT(id) as ids
        FROM events 
        GROUP BY title, start_datetime 
        HAVING count > 1
        ORDER BY count DESC
        LIMIT 5
    ");
    
    while ($row = $stmt->fetch()) {
        echo "- '{$row['title']}' at {$row['start_datetime']} ({$row['count']} copies)\n";
    }
    
    echo "\nDo you want to proceed with cleanup? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($response) === 'y' || strtolower($response) === 'yes') {
        echo "\nCleaning up duplicates...\n";
        $cleanupResults = $eventModel->cleanupDuplicates(false);
        
        echo "Cleanup completed!\n";
        echo "- Removed {$cleanupResults['events_to_remove']} duplicate events\n";
        echo "- Kept the earliest copy of each event\n\n";
        
        // Verify cleanup
        $verifyResults = $eventModel->cleanupDuplicates(true);
        echo "Verification: {$verifyResults['events_to_remove']} duplicates remaining\n";
        
    } else {
        echo "Cleanup cancelled.\n";
    }
} else {
    echo "No exact duplicates found! The duplicate detection system is working well.\n";
}

echo "\nDone.\n";
?>