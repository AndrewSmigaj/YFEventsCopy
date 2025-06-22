<?php

declare(strict_types=1);

/**
 * Application Bootstrap
 * 
 * This file initializes the application container and registers all services
 */

// Load environment configuration
require_once __DIR__ . '/database.php';

use YFEvents\Infrastructure\Container\Container;
use YFEvents\Infrastructure\Database\Connection;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use YFEvents\Infrastructure\Config\Config;
use YFEvents\Infrastructure\Config\ConfigInterface;

// Create the container instance
$container = Container::getInstance();

// Register config interface
$container->singleton(ConfigInterface::class, function() {
    $config = new Config();
    
    // Load database config
    if (file_exists(__DIR__ . '/database.php')) {
        $dbConfig = require __DIR__ . '/database.php';
        $config->set('database', $dbConfig);
    }
    
    return $config;
});

// Register database connection
$container->singleton(ConnectionInterface::class, function($container) {
    $config = $container->resolve(ConfigInterface::class);
    $dbConfig = $config->get('database', []);
    
    return new Connection(
        $dbConfig['host'] ?? 'localhost',
        $dbConfig['name'] ?? 'yakima_finds',
        $dbConfig['username'] ?? 'yfevents',
        $dbConfig['password'] ?? 'yfevents_pass',
        $dbConfig['options'] ?? []
    );
});

// Register core services
require_once __DIR__ . '/services/core.php';

// Register communication services
if (file_exists(__DIR__ . '/services/communication.php')) {
    $registerCommunication = require __DIR__ . '/services/communication.php';
    $registerCommunication($container);
}

// Register other module services here...

return $container;