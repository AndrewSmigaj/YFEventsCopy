<?php

declare(strict_types=1);

use YFEvents\Application\Bootstrap;
use YFEvents\Infrastructure\Http\Router;
use YFEvents\Infrastructure\Discovery\RequestTracker;
use YakimaFinds\Utils\SystemLogger;

// Define base path constant for consistent path resolution
define('BASE_PATH', dirname(__DIR__));

// Load autoloader from correct location
require_once BASE_PATH . '/vendor/autoload.php';

// Initialize runtime discovery logging if enabled
$enableRuntimeDiscovery = getenv('ENABLE_RUNTIME_DISCOVERY') === 'true' || 
                         (file_exists(BASE_PATH . '/.env') && 
                          strpos(file_get_contents(BASE_PATH . '/.env'), 'ENABLE_RUNTIME_DISCOVERY=true') !== false);

if ($enableRuntimeDiscovery) {
    
    // Initialize request tracking
    $requestId = RequestTracker::getRequestId();
    
    // Log request start (we'll get DB connection after bootstrap)
    error_log(sprintf(
        "[runtime_discovery] REQUEST_START: %s %s [request_id: %s]",
        $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        $_SERVER['REQUEST_URI'] ?? '/',
        $requestId
    ));
}

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

    // Log bootstrap completion with SystemLogger if runtime discovery enabled
    if ($enableRuntimeDiscovery) {
        try {
            $db = $container->resolve(\PDO::class);
            $logger = \YakimaFinds\Utils\SystemLogger::create($db, 'runtime_discovery');
            $logger->info('REQUEST_START', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'url' => $_SERVER['REQUEST_URI'] ?? '/',
                'request_id' => $requestId,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
            ]);
            $logger->info('BOOTSTRAP_COMPLETE', [
                'request_id' => $requestId
            ]);
        } catch (\Exception $e) {
            error_log("[runtime_discovery] Failed to initialize SystemLogger: " . $e->getMessage());
        }
    }

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