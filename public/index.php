<?php

declare(strict_types=1);

use YFEvents\Application\Bootstrap;
use YFEvents\Infrastructure\Http\Router;

// Define base path constant for consistent path resolution
define('BASE_PATH', dirname(__DIR__));

// Load autoloader from correct location
require_once BASE_PATH . '/vendor/autoload.php';

// Configure error reporting
// TODO: After Phase 9, use EnvLoader to get APP_DEBUG from .env
$isProduction = file_exists(BASE_PATH . '/.env') && 
                strpos(file_get_contents(BASE_PATH . '/.env'), 'APP_ENV=production') !== false;

if (!$isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

try {
    // Bootstrap the application
    $container = Bootstrap::boot();
    $config = $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class);

    // Create router
    $router = new Router($container, $config);

    // Load routes - pass router to route files via closure
    (function() use ($router) {
        require BASE_PATH . '/routes/web.php';
        require BASE_PATH . '/routes/api.php';
    })();

    // Dispatch the request
    $router->dispatch();

} catch (\Exception $e) {
    // Handle bootstrap errors
    http_response_code(500);
    header('Content-Type: application/json');
    
    $response = [
        'error' => true,
        'message' => 'Application error'
    ];

    // Add debug info in non-production environments
    if (!($isProduction ?? false)) {
        $response['debug'] = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
}