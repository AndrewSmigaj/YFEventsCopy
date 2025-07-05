<?php
/**
 * Service container configuration
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use YFEvents\Infrastructure\Container\Container;

// Create container instance
$container = new Container();

// Load database configuration
$dbConfig = require __DIR__ . '/database.php';
$dbParams = $dbConfig['database'];

// Bind PDO with lazy loading
$container->bind(PDO::class, function() use ($dbParams) {
    $dsn = "mysql:host={$dbParams['host']};dbname={$dbParams['name']};charset={$dbParams['charset']}";
    return new PDO($dsn, $dbParams['username'], $dbParams['password'], $dbParams['options']);
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