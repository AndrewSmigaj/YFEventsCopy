<?php
// Test seller dashboard implementation
require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Application\Bootstrap;

// Bootstrap application
$container = Bootstrap::boot();

// Start session
session_start();

// Simulate logged-in seller
$_SESSION['auth'] = [
    'user_id' => 1,
    'email' => 'test@example.com',
    'role' => 'seller',
    'authenticated' => true
];

// Also support old format for compatibility
$_SESSION['seller'] = [
    'id' => 1,
    'email' => 'test@example.com',
    'store_name' => 'Test Store'
];

// Test dashboard controller
try {
    $controller = $container->resolve(\YFEvents\Presentation\Http\Controllers\ClaimsController::class);
    
    // Capture output
    ob_start();
    $controller->showSellerDashboard();
    $output = ob_get_clean();
    
    // Check for key dashboard elements
    $checks = [
        'Seller Dashboard' => strpos($output, 'Seller Dashboard') !== false,
        'Statistics' => strpos($output, 'Statistics') !== false,
        'Total Sales' => strpos($output, 'Total Sales') !== false,
        'Active Sales' => strpos($output, 'Active Sales') !== false,
        'Chat iframe' => strpos($output, '<iframe') !== false && strpos($output, '/modules/chat/') !== false,
        'Recent Sales' => strpos($output, 'Recent Sales') !== false,
        'Upcoming Sales' => strpos($output, 'Upcoming Sales') !== false
    ];
    
    echo "Seller Dashboard Test Results:\n";
    echo "============================\n\n";
    
    $allPassed = true;
    foreach ($checks as $check => $result) {
        echo sprintf("%-20s: %s\n", $check, $result ? '✓ PASS' : '✗ FAIL');
        if (!$result) $allPassed = false;
    }
    
    if (!$allPassed) {
        echo "\nDebugging Output:\n";
        echo "Output length: " . strlen($output) . " bytes\n";
        
        // Show first 500 chars if there's an error
        if (strlen($output) < 1000) {
            echo "Full output:\n" . $output . "\n";
        } else {
            echo "First 500 chars:\n" . substr($output, 0, 500) . "...\n";
        }
    }
    
    echo "\nOverall: " . ($allPassed ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}