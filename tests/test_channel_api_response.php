<?php
// Test script to inspect the /api/communication/channels API response

// Set up environment
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

// Initialize session to simulate authenticated user
session_start();

// Test different authentication scenarios
$testScenarios = [
    [
        'name' => 'No authentication',
        'session' => []
    ],
    [
        'name' => 'Basic user authentication',
        'session' => [
            'user_id' => 123
        ]
    ],
    [
        'name' => 'YFAuth authentication without seller role',
        'session' => [
            'auth' => [
                'user_id' => 456,
                'username' => 'testuser',
                'email' => 'test@example.com',
                'roles' => ['user']
            ]
        ]
    ],
    [
        'name' => 'YFAuth authentication with seller role',
        'session' => [
            'auth' => [
                'user_id' => 789,
                'username' => 'testseller',
                'email' => 'seller@example.com',
                'roles' => ['user', 'seller']
            ],
            'seller' => [
                'seller_id' => 10,
                'company_name' => 'Test Seller Co',
                'contact_name' => 'Test Seller'
            ]
        ]
    ]
];

echo "Testing /api/communication/channels endpoint\n";
echo "=" . str_repeat("=", 50) . "\n\n";

foreach ($testScenarios as $scenario) {
    echo "Scenario: " . $scenario['name'] . "\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    // Clear and set session
    $_SESSION = $scenario['session'];
    
    // Capture the API response
    ob_start();
    $errorOutput = '';
    
    // Set error handler to capture warnings/notices
    $oldErrorHandler = set_error_handler(function($severity, $message, $file, $line) use (&$errorOutput) {
        $errorOutput .= "PHP " . ($severity == E_WARNING ? "Warning" : "Notice") . ": $message in $file on line $line\n";
    });
    
    try {
        // Simulate the request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/communication/channels';
        
        // Bootstrap the application
        $container = \YFEvents\Application\Bootstrap::boot();
        $config = $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class);
        $router = new \YFEvents\Infrastructure\Http\Router($container, $config);
        
        // Load routes
        require BASE_PATH . '/routes/api.php';
        
        // Dispatch the request
        $router->dispatch();
        
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
        echo "Trace:\n" . $e->getTraceAsString() . "\n";
    }
    
    $response = ob_get_clean();
    
    // Restore error handler
    if ($oldErrorHandler) {
        restore_error_handler();
    }
    
    // Analyze response
    echo "Response Headers:\n";
    $headers = headers_list();
    foreach ($headers as $header) {
        echo "  $header\n";
    }
    
    echo "\nPHP Errors/Warnings:\n";
    if ($errorOutput) {
        echo $errorOutput;
    } else {
        echo "  None\n";
    }
    
    echo "\nResponse Body:\n";
    echo "  Length: " . strlen($response) . " bytes\n";
    
    // Check if it's valid JSON
    $jsonData = json_decode($response, true);
    $jsonError = json_last_error();
    
    if ($jsonError === JSON_ERROR_NONE) {
        echo "  Valid JSON: Yes\n";
        echo "  Structure:\n";
        print_r($jsonData);
    } else {
        echo "  Valid JSON: No\n";
        echo "  JSON Error: " . json_last_error_msg() . "\n";
        
        // Check for common error patterns
        if (strpos($response, '<br />') !== false || strpos($response, '<b>Warning</b>') !== false) {
            echo "  Error Page Detected: Yes (PHP error output)\n";
        } elseif (strpos($response, '<!DOCTYPE') !== false || strpos($response, '<html') !== false) {
            echo "  Error Page Detected: Yes (HTML page)\n";
        } else {
            echo "  Error Page Detected: Unknown\n";
        }
        
        // Show snippet of response
        echo "  Response Snippet (first 500 chars):\n";
        echo "    " . substr($response, 0, 500) . "\n";
    }
    
    echo "\n\n";
    
    // Clear headers for next test
    header_remove();
}

// Test direct controller method call
echo "Direct Controller Test\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // Set up a seller session
    $_SESSION = [
        'auth' => [
            'user_id' => 999,
            'username' => 'directtest',
            'email' => 'direct@test.com',
            'roles' => ['seller']
        ]
    ];
    
    $container = \YFEvents\Application\Bootstrap::boot();
    $config = $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class);
    
    $controller = new \YFEvents\Presentation\Api\Controllers\Communication\ChannelApiController($container, $config);
    
    ob_start();
    $controller->index();
    $directResponse = ob_get_clean();
    
    echo "Direct Response:\n";
    $jsonData = json_decode($directResponse, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "  Valid JSON: Yes\n";
        print_r($jsonData);
    } else {
        echo "  Valid JSON: No\n";
        echo "  Response: " . substr($directResponse, 0, 500) . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception in direct test: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}