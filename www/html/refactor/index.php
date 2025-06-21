<?php

declare(strict_types=1);

use YakimaFinds\Application\Bootstrap;
use YakimaFinds\Infrastructure\Http\Router;

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

try {
    // Bootstrap the application
    $container = Bootstrap::boot();
    $config = $container->resolve(\YakimaFinds\Infrastructure\Config\ConfigInterface::class);

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
    $debug = defined('APP_ENV') && APP_ENV === 'development';
    \YakimaFinds\Infrastructure\Http\ErrorHandler::handle500($e, $debug);
}