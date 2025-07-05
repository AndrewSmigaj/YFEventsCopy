<?php
/**
 * Service container configuration
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/database.php';

use YFEvents\Infrastructure\Container\Container;

// Create container instance
$container = new Container();

// Bind PDO
$container->bind(PDO::class, function() use ($pdo) {
    return $pdo;
});

// Load service configurations
$serviceConfigs = [
    __DIR__ . '/services/core.php',
    __DIR__ . '/services/communication.php',
];

foreach ($serviceConfigs as $config) {
    if (file_exists($config)) {
        $services = require $config;
        if (is_callable($services)) {
            $services($container);
        }
    }
}

return $container;