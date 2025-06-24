<?php

declare(strict_types=1);

use YFEvents\Application\Bootstrap;
use YFEvents\Infrastructure\Http\Router;

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Configure session to use a writable directory
$sessionDir = __DIR__ . '/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
}
if (is_writable($sessionDir)) {
    ini_set('session.save_path', $sessionDir);
}

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

try {
    // Bootstrap the application with all services
    $container = require __DIR__ . '/config/bootstrap.php';
    $config = $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class);

    // Create router
    $router = new Router($container, $config);

    // Load routes - pass router to route files via closure
    (function() use ($router) {
        require __DIR__ . '/routes/web.php';
        require __DIR__ . '/routes/api.php';
    })();

    // Dispatch the request
    $router->dispatch();

} catch (\Exception $e) {
    // Handle bootstrap errors
    $debug = false;
    \YFEvents\Infrastructure\Http\ErrorHandler::handle500($e, $debug);
}