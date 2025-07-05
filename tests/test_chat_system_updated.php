#!/usr/bin/env php
<?php
/**
 * Updated test script for communication system
 * Uses correct communication_* tables
 */

require_once __DIR__ . '/../vendor/autoload.php';

try {
    echo "Testing Communication System (Updated)\n";
    echo "=====================================\n\n";
    
    // Direct PDO connection
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=yakima_finds;charset=utf8mb4', 'yfevents', 'yfevents_pass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test 1: Verify tables exist
    echo "Test 1: Verifying communication tables...\n";
    $tables = [
        'communication_channels',
        'communication_messages',
        'communication_participants',
        'communication_notifications',
        'communication_attachments',
        'communication_email_addresses',
        'communication_reactions'
    ];
    
    $allExist = true;
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
            echo "  ✓ $table exists\n";
        } catch (PDOException $e) {
            echo "  ✗ $table missing\n";
            $allExist = false;
        }
    }
    
    if (!$allExist) {
        echo "\n❌ Some tables are missing. Run setup_communication_simple.php first.\n";
        exit(1);
    }
    
    // Test 2: Check global channels
    echo "\nTest 2: Checking global channels...\n";
    $stmt = $pdo->query("SELECT * FROM communication_channels WHERE type = 'public' ORDER BY id");
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($channels) < 2) {
        echo "  ✗ Expected at least 2 global channels, found " . count($channels) . "\n";
    } else {
        foreach ($channels as $channel) {
            echo "  ✓ {$channel['name']} (slug: {$channel['slug']})\n";
        }
    }
    
    // Test 3: Add test participants
    echo "\nTest 3: Adding test participants...\n";
    $testUsers = [
        ['id' => 1, 'type' => 'admin', 'name' => 'Admin User'],
        ['id' => 2, 'type' => 'seller', 'name' => 'Test Seller']
    ];
    
    foreach ($channels as $channel) {
        foreach ($testUsers as $user) {
            try {
                // Check if already exists
                $stmt = $pdo->prepare("SELECT id FROM communication_participants WHERE channel_id = ? AND user_id = ?");
                $stmt->execute([$channel['id'], $user['id']]);
                
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO communication_participants (channel_id, user_id, role) 
                        VALUES (?, ?, ?)
                    ");
                    $role = $user['type'] === 'admin' ? 'admin' : 'member';
                    $stmt->execute([$channel['id'], $user['id'], $role]);
                    echo "  ✓ Added {$user['name']} to {$channel['name']}\n";
                } else {
                    echo "  - {$user['name']} already in {$channel['name']}\n";
                }
            } catch (PDOException $e) {
                echo "  ✗ Error adding {$user['name']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Test 4: Send test messages
    echo "\nTest 4: Sending test messages...\n";
    $messages = [
        ['user_id' => 1, 'content' => 'Welcome sellers! This is a test message from admin.'],
        ['user_id' => 2, 'content' => 'Thanks for the welcome! Happy to be here.'],
        ['user_id' => 1, 'content' => 'Feel free to ask any questions about the platform.']
    ];
    
    $supportChannelId = $channels[0]['id'] ?? 1;
    
    foreach ($messages as $msg) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO communication_messages (channel_id, user_id, content, content_type)
                VALUES (?, ?, ?, 'text')
            ");
            $stmt->execute([$supportChannelId, $msg['user_id'], $msg['content']]);
            echo "  ✓ Message sent by User {$msg['user_id']}\n";
        } catch (PDOException $e) {
            echo "  ✗ Error sending message: " . $e->getMessage() . "\n";
        }
    }
    
    // Test 5: Test notifications
    echo "\nTest 5: Creating test notifications...\n";
    try {
        // Create mention notification
        $stmt = $pdo->prepare("
            INSERT INTO communication_notifications (user_id, channel_id, message_id, type)
            VALUES (?, ?, ?, 'mention')
        ");
        $stmt->execute([2, $supportChannelId, 1, 'mention']);
        echo "  ✓ Created mention notification\n";
    } catch (PDOException $e) {
        echo "  ✗ Error creating notification: " . $e->getMessage() . "\n";
    }
    
    // Test 6: Test unread counts
    echo "\nTest 6: Testing unread counts...\n";
    foreach ($testUsers as $user) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT m.id) as unread
            FROM communication_messages m
            JOIN communication_participants p ON m.channel_id = p.channel_id
            WHERE p.user_id = ?
            AND m.user_id != ?
            AND (p.last_read_at IS NULL OR m.created_at > p.last_read_at)
        ");
        $stmt->execute([$user['id'], $user['id']]);
        $unread = $stmt->fetchColumn();
        echo "  - {$user['name']} has $unread unread messages\n";
    }
    
    // Test 7: Show recent activity
    echo "\nTest 7: Recent channel activity...\n";
    $stmt = $pdo->query("
        SELECT 
            c.name as channel,
            COUNT(DISTINCT m.id) as message_count,
            COUNT(DISTINCT p.user_id) as participant_count,
            MAX(m.created_at) as last_activity
        FROM communication_channels c
        LEFT JOIN communication_messages m ON c.id = m.channel_id
        LEFT JOIN communication_participants p ON c.id = p.channel_id
        GROUP BY c.id, c.name
        ORDER BY c.id
    ");
    
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($activities as $activity) {
        echo "  - {$activity['channel']}:\n";
        echo "    Messages: {$activity['message_count']}\n";
        echo "    Participants: {$activity['participant_count']}\n";
        echo "    Last activity: " . ($activity['last_activity'] ?? 'Never') . "\n";
    }
    
    // Test 8: API endpoint test instructions
    echo "\nTest 8: API Endpoints (Manual Testing Required)...\n";
    echo "  To test the API endpoints, use these URLs:\n";
    echo "  - Unread count: GET /api/communication/unread-count\n";
    echo "  - Send message: POST /api/communication/messages\n";
    echo "  - Get channels: GET /api/communication/channels\n";
    echo "  - Mark as read: POST /api/communication/channels/{id}/read\n";
    
    // Summary
    echo "\n✅ Communication system tests completed!\n";
    echo "\nSummary:\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM communication_channels");
    echo "- Total channels: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM communication_messages");
    echo "- Total messages: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM communication_participants");
    echo "- Total participants: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM communication_notifications");
    echo "- Total notifications: " . $stmt->fetchColumn() . "\n";
    
    echo "\nNext steps:\n";
    echo "1. Access the seller dashboard: /modules/yfclaim/www/seller-dashboard.php\n";
    echo "2. Click on the 'Messages' tab\n";
    echo "3. Verify the chat interface loads\n";
    echo "4. Test sending messages\n";
    echo "5. Check unread counts update properly\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n";