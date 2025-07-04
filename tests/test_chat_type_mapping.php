#!/usr/bin/env php
<?php
/**
 * Test script to verify chat channel type mapping
 */

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Database\Connection;
use YFEvents\Infrastructure\Repositories\Communication\ChannelRepository;

try {
    echo "Testing Chat Channel Type Mapping...\n\n";
    
    // Load configuration directly from .env
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) {
        throw new Exception(".env file not found");
    }
    
    // Simple env parsing
    $envContent = file_get_contents($envFile);
    $env = [];
    foreach (explode("\n", $envContent) as $line) {
        $line = trim($line);
        if ($line && strpos($line, '=') !== false && $line[0] !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value, '"\'');
        }
    }
    
    // Connect to database
    $connection = new Connection(
        $env['DB_HOST'] ?? 'localhost',
        $env['DB_DATABASE'] ?? 'yakima_finds',
        $env['DB_USERNAME'] ?? 'root',
        $env['DB_PASSWORD'] ?? ''
    );
    $channelRepo = new ChannelRepository($connection);
    
    // Test 1: Load existing channels and verify type mapping
    echo "Test 1: Loading channels from database\n";
    echo str_repeat('-', 50) . "\n";
    
    $pdo = $connection->getConnection();
    $stmt = $pdo->query("SELECT id, type, title FROM chat_conversations WHERE type IN ('support', 'tips')");
    $dbChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($dbChannels as $dbChannel) {
        echo "DB Channel: ID={$dbChannel['id']}, Type={$dbChannel['type']}, Title={$dbChannel['title']}\n";
        
        // Load through repository
        $channel = $channelRepo->findById((int)$dbChannel['id']);
        if ($channel) {
            echo "  Domain Channel: Type={$channel->getType()->getValue()}, Name={$channel->getName()}, Slug={$channel->getSlug()}\n";
            echo "  Type is Public: " . ($channel->getType()->isPublic() ? 'YES' : 'NO') . "\n";
            
            // Verify mapping
            if ($dbChannel['type'] === 'support' || $dbChannel['type'] === 'tips') {
                if (!$channel->getType()->isPublic()) {
                    echo "  ❌ ERROR: {$dbChannel['type']} should map to public type!\n";
                } else {
                    echo "  ✅ Correct: {$dbChannel['type']} mapped to public type\n";
                }
            }
        } else {
            echo "  ❌ ERROR: Could not load channel!\n";
        }
        echo "\n";
    }
    
    // Test 2: Test findBySlug
    echo "\nTest 2: Testing findBySlug\n";
    echo str_repeat('-', 50) . "\n";
    
    $testSlugs = ['support-channel', 'selling-tips', 'support', 'tips'];
    foreach ($testSlugs as $slug) {
        echo "Finding by slug: '$slug'\n";
        $channel = $channelRepo->findBySlug($slug);
        if ($channel) {
            echo "  ✅ Found: {$channel->getName()} (Type: {$channel->getType()->getValue()})\n";
        } else {
            echo "  ❌ Not found\n";
        }
    }
    
    // Test 3: Test reverse mapping (save)
    echo "\nTest 3: Testing reverse mapping (entity to DB)\n";
    echo str_repeat('-', 50) . "\n";
    
    // Get a channel and test mapEntityToDb
    $supportChannel = $channelRepo->findBySlug('support');
    if ($supportChannel) {
        // Use reflection to test protected method
        $reflection = new ReflectionClass($channelRepo);
        $method = $reflection->getMethod('mapEntityToDb');
        $method->setAccessible(true);
        
        $dbData = $method->invoke($channelRepo, $supportChannel);
        echo "Support Channel reverse mapping:\n";
        echo "  Domain type: {$supportChannel->getType()->getValue()}\n";
        echo "  DB type: {$dbData['type']}\n";
        echo "  " . ($dbData['type'] === 'support' ? '✅ Correct' : '❌ Incorrect') . "\n";
    }
    
    // Test 4: Verify public channel access
    echo "\nTest 4: Verifying channel access types\n";
    echo str_repeat('-', 50) . "\n";
    
    $publicChannels = $channelRepo->findPublicChannels();
    echo "Found " . count($publicChannels) . " public channels:\n";
    foreach ($publicChannels as $channel) {
        echo "  - {$channel->getName()} (Slug: {$channel->getSlug()})\n";
    }
    
    echo "\n✅ All tests completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}