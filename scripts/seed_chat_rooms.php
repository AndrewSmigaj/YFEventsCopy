#!/usr/bin/env php
<?php
/**
 * Seed the two global chat rooms for admin-seller communication
 */

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Database\MySQLConnection;
use YFEvents\Application\Services\ConfigService;

try {
    echo "Seeding global chat rooms...\n";
    
    // Load configuration
    $configService = new ConfigService();
    $config = $configService->getConfig();
    
    // Connect to database
    $connection = new MySQLConnection($config['database']);
    $pdo = $connection->getConnection();
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if we have an admin user (user_id = 1)
    $stmt = $pdo->prepare("SELECT id FROM yfa_auth_users WHERE id = 1");
    $stmt->execute();
    if (!$stmt->fetch()) {
        throw new Exception("No admin user found with ID 1. Please ensure at least one admin exists.");
    }
    
    // Create Support Channel
    $stmt = $pdo->prepare("
        INSERT INTO chat_conversations (type, title, description, created_by, is_active, created_at, updated_at)
        SELECT 'support', 'Support Channel', 'Get help and support from admins and other sellers', 1, TRUE, NOW(), NOW()
        WHERE NOT EXISTS (
            SELECT 1 FROM chat_conversations WHERE type = 'support' AND title = 'Support Channel'
        )
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Created Support Channel\n";
    } else {
        echo "- Support Channel already exists\n";
    }
    
    // Create Selling Tips Channel
    $stmt = $pdo->prepare("
        INSERT INTO chat_conversations (type, title, description, created_by, is_active, created_at, updated_at)
        SELECT 'tips', 'Selling Tips', 'Share tips and best practices for successful estate sales', 1, TRUE, NOW(), NOW()
        WHERE NOT EXISTS (
            SELECT 1 FROM chat_conversations WHERE type = 'tips' AND title = 'Selling Tips'
        )
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Created Selling Tips Channel\n";
    } else {
        echo "- Selling Tips Channel already exists\n";
    }
    
    // Get channel IDs
    $stmt = $pdo->query("SELECT id, type FROM chat_conversations WHERE type IN ('support', 'tips')");
    $channels = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Add welcome messages if channels don't have any messages yet
    foreach ($channels as $channelId => $type) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE conversation_id = ?");
        $stmt->execute([$channelId]);
        $messageCount = $stmt->fetchColumn();
        
        if ($messageCount == 0) {
            $welcomeMessage = $type === 'support' 
                ? 'Welcome to the Support Channel! Feel free to ask questions and get help from the community.'
                : 'Welcome to Selling Tips! Share your experiences and learn from other sellers to make your estate sales more successful.';
            
            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (conversation_id, user_id, content, is_deleted, created_at)
                VALUES (?, 1, ?, 0, NOW())
            ");
            $stmt->execute([$channelId, $welcomeMessage]);
            
            echo "✓ Added welcome message to " . ($type === 'support' ? 'Support' : 'Selling Tips') . " Channel\n";
        }
    }
    
    // Add the admin user as participant in both channels
    foreach ($channels as $channelId => $type) {
        $stmt = $pdo->prepare("
            INSERT INTO chat_participants (conversation_id, user_id, role, joined_at, is_active)
            SELECT ?, 1, 'admin', NOW(), 1
            WHERE NOT EXISTS (
                SELECT 1 FROM chat_participants WHERE conversation_id = ? AND user_id = 1
            )
        ");
        $stmt->execute([$channelId, $channelId]);
        
        if ($stmt->rowCount() > 0) {
            echo "✓ Added admin as participant in " . ($type === 'support' ? 'Support' : 'Selling Tips') . " Channel\n";
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Global chat rooms seeded successfully!\n";
    echo "\nChannels created:\n";
    
    // Display created channels
    $stmt = $pdo->query("SELECT id, type, title, description FROM chat_conversations WHERE type IN ('support', 'tips')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("- [%d] %s: %s\n", $row['id'], $row['title'], $row['description']);
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}