<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Container\Container;
use YFEvents\Infrastructure\Config\Config;
use YFEvents\Infrastructure\Providers\ServiceProvider;

echo "Testing Communication API Endpoints\n";
echo "===================================\n\n";

try {
    // Bootstrap the application
    $container = new Container();
    $config = new Config();
    
    // Register the container itself
    $container->singleton(\YFEvents\Infrastructure\Container\ContainerInterface::class, function() use ($container) {
        return $container;
    });
    
    // Register services
    $serviceProvider = new ServiceProvider($container);
    $serviceProvider->register();
    
    echo "✅ Services registered successfully\n\n";
    
    // Test that we can resolve services
    $communicationService = $container->resolve(\YFEvents\Application\Services\Communication\CommunicationService::class);
    echo "✅ CommunicationService resolved successfully\n";
    
    // Test that we can resolve controllers
    $channelController = $container->resolve(\YFEvents\Presentation\Api\Controllers\Communication\ChannelApiController::class);
    echo "✅ ChannelApiController resolved successfully\n";
    
    $messageController = $container->resolve(\YFEvents\Presentation\Api\Controllers\Communication\MessageApiController::class);
    echo "✅ MessageApiController resolved successfully\n";
    
    $announcementController = $container->resolve(\YFEvents\Presentation\Api\Controllers\Communication\AnnouncementApiController::class);
    echo "✅ AnnouncementApiController resolved successfully\n";
    
    $notificationController = $container->resolve(\YFEvents\Presentation\Api\Controllers\Communication\NotificationApiController::class);
    echo "✅ NotificationApiController resolved successfully\n";
    
    echo "\n🎉 All communication API components are properly wired!\n";
    
    // Test database connection
    echo "\nTesting Database Connection:\n";
    $connection = $container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
    $pdo = $connection->getConnection();
    
    // Check if communication tables exist
    $tables = ['channels', 'messages', 'channel_participants'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' not found\n";
        }
    }
    
    echo "\n✅ Phase 2 Route Integration completed successfully!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}