<?php
// Test seller dashboard full functionality
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Application\Bootstrap;

// Bootstrap application
$container = Bootstrap::boot();

// Set up session for seller ID 1
$_SESSION['claim_seller_id'] = 1;
$_SESSION['claim_seller_logged_in'] = true;
$_SESSION['seller'] = [
    'id' => 1,
    'email' => 'contact@estatepros.com',
    'company_name' => 'Estate Sale Professionals'
];

// Test dashboard controller
try {
    $controller = $container->resolve(\YFEvents\Presentation\Http\Controllers\ClaimsController::class);
    
    // Capture output
    ob_start();
    $controller->showSellerDashboard();
    $output = ob_get_clean();
    
    // Check for key elements
    $checks = [
        'Dashboard HTML' => strlen($output) > 1000,
        'Seller Dashboard title' => strpos($output, 'Seller Dashboard') !== false,
        'Statistics section' => strpos($output, 'Statistics') !== false || strpos($output, 'Total Sales') !== false,
        'No PHP errors' => strpos($output, 'Fatal error') === false && strpos($output, 'Warning') === false,
        'Chat iframe' => strpos($output, '<iframe') !== false,
        'Company name displayed' => strpos($output, 'Estate Sale Professionals') !== false
    ];
    
    echo "Seller Dashboard Full Test Results:\n";
    echo "==================================\n\n";
    
    $allPassed = true;
    foreach ($checks as $check => $result) {
        echo sprintf("%-25s: %s\n", $check, $result ? '✓ PASS' : '✗ FAIL');
        if (!$result) $allPassed = false;
    }
    
    if (!$allPassed) {
        echo "\nDebugging Output:\n";
        echo "Output length: " . strlen($output) . " bytes\n";
        
        // Check for errors
        if (strpos($output, 'Fatal error') !== false) {
            preg_match('/Fatal error: (.+)/', $output, $matches);
            echo "Fatal Error: " . ($matches[1] ?? 'Unknown') . "\n";
        }
        if (strpos($output, 'Warning') !== false) {
            preg_match('/Warning: (.+)/', $output, $matches);
            echo "Warning: " . ($matches[1] ?? 'Unknown') . "\n";
        }
        
        // Show snippet of output
        echo "\nFirst 500 chars of output:\n";
        echo substr($output, 0, 500) . "...\n";
    }
    
    echo "\nOverall: " . ($allPassed ? '✓ ALL TESTS PASSED' : '✗ SOME TESTS FAILED') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}