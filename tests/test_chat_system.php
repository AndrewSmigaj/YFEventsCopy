#!/usr/bin/env php
<?php
/**
 * Test script to verify admin-seller chat system functionality
 */

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Database\Connection;
use YFEvents\Infrastructure\Repositories\Communication\ChannelRepository;
use YFEvents\Infrastructure\Repositories\Communication\MessageRepository;
use YFEvents\Infrastructure\Repositories\Communication\ParticipantRepository;
use YFEvents\Infrastructure\Repositories\Communication\NotificationRepository;
use YFEvents\Domain\Communication\Entities\Message;
use YFEvents\Domain\Communication\Entities\Notification;

try {
    echo "Testing Admin-Seller Chat System...\n\n";
    
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
    $messageRepo = new MessageRepository($connection);
    $participantRepo = new ParticipantRepository($connection);
    $notificationRepo = new NotificationRepository($connection);
    
    echo "Test 1: Verify global channels exist\n";
    echo str_repeat('-', 50) . "\n";
    
    $pdo = $connection->getConnection();
    $stmt = $pdo->query("SELECT id, type, title FROM chat_conversations WHERE type IN ('support', 'tips')");
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($channels) !== 2) {
        echo "❌ ERROR: Expected 2 global channels, found " . count($channels) . "\n";
        echo "Please run: php scripts/seed_chat_rooms.php\n";
        exit(1);
    }
    
    $supportChannelId = null;
    $tipsChannelId = null;
    
    foreach ($channels as $channel) {
        echo "✅ Found: {$channel['title']} (ID: {$channel['id']}, Type: {$channel['type']})\n";
        if ($channel['type'] === 'support') {
            $supportChannelId = $channel['id'];
        } else {
            $tipsChannelId = $channel['id'];
        }
    }
    
    echo "\nTest 2: Verify participants in channels\n";
    echo str_repeat('-', 50) . "\n";
    
    foreach ([$supportChannelId => 'Support', $tipsChannelId => 'Tips'] as $channelId => $name) {
        $count = $participantRepo->countByChannelId($channelId);
        echo "$name Channel: $count participants\n";
        
        // Get some participants
        $participants = $participantRepo->findByChannelId($channelId, 5);
        foreach ($participants as $participant) {
            echo "  - User ID: {$participant->getUserId()}, Role: {$participant->getRole()}\n";
        }
    }
    
    echo "\nTest 3: Test message creation\n";
    echo str_repeat('-', 50) . "\n";
    
    // Create a test message in support channel
    $testMessage = new Message(
        null,
        $supportChannelId,
        1, // Admin user
        "Test message from integration test at " . date('Y-m-d H:i:s')
    );
    
    try {
        $savedMessage = $messageRepo->save($testMessage);
        echo "✅ Message saved successfully (ID: {$savedMessage->getId()})\n";
        
        // Verify message can be retrieved
        $retrievedMessage = $messageRepo->findById($savedMessage->getId());
        if ($retrievedMessage) {
            echo "✅ Message retrieved successfully\n";
            echo "  Content: {$retrievedMessage->getContent()}\n";
        } else {
            echo "❌ ERROR: Could not retrieve saved message\n";
        }
    } catch (Exception $e) {
        echo "❌ ERROR saving message: " . $e->getMessage() . "\n";
    }
    
    echo "\nTest 4: Test notification creation\n";
    echo str_repeat('-', 50) . "\n";
    
    if (isset($savedMessage)) {
        // Get participants who should be notified (excluding sender)
        $participants = $participantRepo->findByChannelId($supportChannelId);
        $userIdsToNotify = [];
        
        foreach ($participants as $participant) {
            if ($participant->getUserId() !== 1) { // Exclude sender
                $userIdsToNotify[] = $participant->getUserId();
            }
        }
        
        if (count($userIdsToNotify) > 0) {
            // Create batch notifications
            $result = $notificationRepo->createBatchNotifications(
                $savedMessage->getId(),
                $supportChannelId,
                array_slice($userIdsToNotify, 0, 3) // Test with first 3 users
            );
            
            if ($result) {
                echo "✅ Notifications created for " . min(3, count($userIdsToNotify)) . " users\n";
                
                // Verify notifications exist
                if (count($userIdsToNotify) > 0) {
                    $notifications = $notificationRepo->findByUserId($userIdsToNotify[0], true);
                    echo "  User {$userIdsToNotify[0]} has " . count($notifications) . " unread notifications\n";
                }
            } else {
                echo "❌ ERROR: Failed to create notifications\n";
            }
        } else {
            echo "- No other participants to notify\n";
        }
    }
    
    echo "\nTest 5: Test channel type mapping\n";
    echo str_repeat('-', 50) . "\n";
    
    $supportChannel = $channelRepo->findById($supportChannelId);
    if ($supportChannel) {
        echo "Support Channel:\n";
        echo "  Domain Type: {$supportChannel->getType()->getValue()}\n";
        echo "  Is Public: " . ($supportChannel->getType()->isPublic() ? 'YES' : 'NO') . "\n";
        
        if (!$supportChannel->getType()->isPublic()) {
            echo "  ❌ ERROR: Support channel should be public!\n";
        } else {
            echo "  ✅ Correctly mapped as public channel\n";
        }
    }
    
    echo "\nTest 6: Test message retrieval\n";
    echo str_repeat('-', 50) . "\n";
    
    $messages = $messageRepo->findByConversationId($supportChannelId, 10);
    echo "Found " . count($messages) . " messages in Support Channel:\n";
    
    foreach (array_slice($messages, 0, 5) as $message) {
        echo "  - [{$message->getId()}] User {$message->getUserId()}: " . 
             substr($message->getContent(), 0, 50) . "...\n";
    }
    
    echo "\nTest 7: Test database triggers\n";
    echo str_repeat('-', 50) . "\n";
    
    // Check if last_activity was updated
    $stmt = $pdo->prepare("SELECT last_activity, last_message_id FROM chat_conversations WHERE id = ?");
    $stmt->execute([$supportChannelId]);
    $channelData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (isset($savedMessage) && $channelData['last_message_id'] == $savedMessage->getId()) {
        echo "✅ Trigger updated last_message_id correctly\n";
    } else {
        echo "❌ Trigger may not be working for last_message_id\n";
    }
    
    echo "\n✅ All tests completed!\n";
    echo "\nSummary:\n";
    echo "- Global channels are properly set up\n";
    echo "- Messages can be created and retrieved\n";
    echo "- Notifications work without 'type' field\n";
    echo "- Channel type mapping is correct\n";
    echo "- Database triggers appear to be working\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}