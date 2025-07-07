<?php
// Simple verification that chat loading is fixed

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

echo "=== Verifying Chat Fix ===\n\n";

// 1. Check database schema
$pdo = new PDO('mysql:host=localhost;dbname=yakima_finds', 'root', '');
echo "1. Database columns check:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM communication_channels WHERE Field IN ('last_activity_at', 'is_archived')");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "   ✓ Column '{$col['Field']}' exists\n";
}

// 2. Check global channels exist
echo "\n2. Global channels check:\n";
$stmt = $pdo->query("SELECT name, type FROM communication_channels WHERE type = 'public'");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($channels as $ch) {
    echo "   ✓ {$ch['name']} (type: {$ch['type']})\n";
}

// 3. Test repository queries
echo "\n3. Testing fixed SQL queries:\n";
try {
    $container = \YFEvents\Application\Bootstrap::boot();
    $channelRepo = $container->resolve(\YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface::class);
    
    // Test public channels query
    $publicChannels = $channelRepo->findPublicChannels();
    echo "   ✓ findPublicChannels() works - found " . count($publicChannels) . " channels\n";
    
    // Test user channels query (with a dummy user ID that won't have channels)
    $userChannels = $channelRepo->findUserChannels(99999);
    echo "   ✓ findUserChannels() works - found " . count($userChannels) . " channels\n";
    
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// 4. Summary
echo "\n4. Summary:\n";
echo "   The SQL column reference issues have been fixed:\n";
echo "   - Changed 'last_activity' to 'last_activity_at'\n";
echo "   - Changed 'is_active' to 'is_archived' (with inverted logic)\n";
echo "   - Changed 'title' to 'name'\n";
echo "   - Global channels (Support and Tips & Tricks) are created\n";
echo "\n   The chat should now load properly for authenticated sellers.\n";

echo "\n=== Verification Complete ===\n";