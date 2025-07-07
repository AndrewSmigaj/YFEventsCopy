<?php
// Create global chat channels for testing

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use YFEvents\Application\Bootstrap;

$container = Bootstrap::boot();
$pdo = $container->resolve(\PDO::class);

echo "Setting up global chat channels...\n";

try {
    // Create support and tips channels if they don't exist
    $channels = [
        [
            'name' => 'Support',
            'slug' => 'support',
            'description' => 'Get help and support from the YFEvents team',
            'type' => 'public',
            'created_by_user_id' => 1
        ],
        [
            'name' => 'Tips & Tricks',
            'slug' => 'tips',
            'description' => 'Share tips and best practices for estate sales',
            'type' => 'public',
            'created_by_user_id' => 1
        ]
    ];
    
    foreach ($channels as $channel) {
        // Check if channel already exists
        $stmt = $pdo->prepare("SELECT id FROM communication_channels WHERE slug = ? OR type = ?");
        $stmt->execute([$channel['slug'], $channel['type']]);
        
        if (!$stmt->fetch()) {
            // Create channel
            $stmt = $pdo->prepare("
                INSERT INTO communication_channels 
                (name, slug, description, type, created_by_user_id, is_archived) 
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            
            $stmt->execute([
                $channel['name'],
                $channel['slug'],
                $channel['description'],
                $channel['type'],
                $channel['created_by_user_id']
            ]);
            
            echo "Created channel: " . $channel['name'] . "\n";
        } else {
            echo "Channel already exists: " . $channel['name'] . "\n";
        }
    }
    
    echo "\nGlobal channels setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}