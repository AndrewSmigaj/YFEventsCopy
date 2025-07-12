<?php

declare(strict_types=1);

/**
 * Application Bootstrap
 * 
 * This file initializes the application container and registers all services
 */

// Prevent multiple bootstrapping
if (defined('YF_BOOTSTRAPPED')) {
    return $container ?? null;
}
define('YF_BOOTSTRAPPED', true);

// Load environment configuration
require_once __DIR__ . '/database.php';

use YFEvents\Infrastructure\Container\Container;
use YFEvents\Infrastructure\Database\Connection;
use YFEvents\Infrastructure\Database\ConnectionInterface;

// Create the container instance
$container = new Container();

// Register database connection
$container->singleton(ConnectionInterface::class, function() {
    $config = require __DIR__ . '/database.php';
    return new Connection(
        $config['host'],
        $config['name'],
        $config['username'],
        $config['password'],
        $config['options'] ?? []
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