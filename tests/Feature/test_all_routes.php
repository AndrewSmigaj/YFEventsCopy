<?php

declare(strict_types=1);

/**
 * Comprehensive route testing script for YFEvents refactor
 * Tests all routes and identifies broken pages
 */

$baseUrl = 'https://backoffice.yakimafinds.com/refactor';

// Define all routes to test
$routes = [
    // Public pages
    'Home' => '/',
    'Events List' => '/events',
    'Events Featured' => '/events/featured',
    'Events Upcoming' => '/events/upcoming', 
    'Events Calendar' => '/events/calendar',
    'Event Detail (ID 7)' => '/events/7',
    'Event Detail (Invalid)' => '/events/99999',
    'Events Submit' => '/events/submit',
    
    // Shop pages
    'Shops List' => '/shops',
    'Shops Featured' => '/shops/featured',
    'Shops Map' => '/shops/map',
    'Shop Detail (ID 1)' => '/shops/1',
    'Shop Submit' => '/shops/submit',
    
    // Map
    'Combined Map' => '/map',
    
    // Claims pages
    'Claims Home' => '/claims',
    'Claims Upcoming' => '/claims/upcoming',
    'Claims Sale' => '/claims/sale',
    'Claims Item (ID 1)' => '/claims/item/1',
    
    // Seller pages
    'Seller Register' => '/seller/register',
    'Seller Login' => '/seller/login',
    'Seller Dashboard' => '/seller/dashboard',
    'Seller New Sale' => '/seller/sale/new',
    
    // Buyer pages
    'Buyer Auth' => '/buyer/auth',
    'Buyer Offers' => '/buyer/offers',
    
    // Admin pages (will redirect to login)
    'Admin Dashboard' => '/admin/',
    'Admin Index' => '/admin/index.php',
    'Admin Events' => '/admin/events.php',
    'Admin Shops' => '/admin/shops.php',
    'Admin Claims' => '/admin/claims.php',
    'Admin Scrapers' => '/admin/scrapers.php',
    'Admin Email Events' => '/admin/email-events.php',
    'Admin Email Config' => '/admin/email-config.php',
    'Admin Users' => '/admin/users.php',
    'Admin Settings' => '/admin/settings.php',
    'Admin Theme' => '/admin/theme.php',
    'Admin Login' => '/admin/login.php',
    
    // API endpoints
    'API Events' => '/api/events',
    'API Event Detail' => '/api/events/7',
    'API Shops' => '/api/shops',
    'API Shop Detail' => '/api/shops/1',
];

// ANSI color codes for output
$colors = [
    'reset' => "\033[0m",
    'green' => "\033[32m",
    'red' => "\033[31m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'gray' => "\033[90m"
];

// Function to test a single URL
function testUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Extract any error messages from the response
    $errorMessage = '';
    if ($response && strpos($response, 'Fatal error') !== false) {
        preg_match('/Fatal error.*?in.*?on line \d+/s', $response, $matches);
        if (!empty($matches[0])) {
            $errorMessage = strip_tags($matches[0]);
        }
    }
    
    return [
        'code' => $httpCode,
        'error' => $error,
        'message' => $errorMessage
    ];
}

// Print header
echo "\n";
echo $colors['blue'] . "=== YFEvents Refactor Route Testing ===" . $colors['reset'] . "\n";
echo $colors['gray'] . "Base URL: $baseUrl" . $colors['reset'] . "\n";
echo $colors['gray'] . "Testing " . count($routes) . " routes..." . $colors['reset'] . "\n";
echo str_repeat("=", 80) . "\n\n";

// Track statistics
$stats = [
    'success' => 0,
    'redirect' => 0,
    'error' => 0,
    'not_found' => 0
];

// Test each route
foreach ($routes as $name => $path) {
    $url = $baseUrl . $path;
    echo sprintf("%-30s %s ... ", $name, $colors['gray'] . $path . $colors['reset']);
    
    $result = testUrl($url);
    
    switch ($result['code']) {
        case 200:
            echo $colors['green'] . "✓ OK (200)" . $colors['reset'];
            $stats['success']++;
            break;
            
        case 301:
        case 302:
        case 303:
        case 307:
        case 308:
            echo $colors['yellow'] . "→ Redirect ({$result['code']})" . $colors['reset'];
            $stats['redirect']++;
            break;
            
        case 404:
            echo $colors['red'] . "✗ Not Found (404)" . $colors['reset'];
            $stats['not_found']++;
            break;
            
        case 500:
        case 503:
            echo $colors['red'] . "✗ Server Error ({$result['code']})" . $colors['reset'];
            $stats['error']++;
            if ($result['message']) {
                echo "\n   " . $colors['red'] . "Error: " . $result['message'] . $colors['reset'];
            }
            break;
            
        case 0:
            echo $colors['red'] . "✗ Failed to connect" . $colors['reset'];
            if ($result['error']) {
                echo "\n   " . $colors['red'] . "Error: " . $result['error'] . $colors['reset'];
            }
            $stats['error']++;
            break;
            
        default:
            echo $colors['yellow'] . "? Unknown ({$result['code']})" . $colors['reset'];
            $stats['error']++;
    }
    
    echo "\n";
}

// Print summary
echo "\n" . str_repeat("=", 80) . "\n";
echo $colors['blue'] . "Summary:" . $colors['reset'] . "\n";
echo $colors['green'] . "  ✓ Success: " . $stats['success'] . $colors['reset'] . "\n";
echo $colors['yellow'] . "  → Redirects: " . $stats['redirect'] . $colors['reset'] . "\n";
echo $colors['red'] . "  ✗ Not Found: " . $stats['not_found'] . $colors['reset'] . "\n";
echo $colors['red'] . "  ✗ Errors: " . $stats['error'] . $colors['reset'] . "\n";

$total = array_sum($stats);
$successRate = $total > 0 ? round(($stats['success'] / $total) * 100, 1) : 0;
echo "\n" . $colors['blue'] . "Success Rate: " . $successRate . "%" . $colors['reset'] . "\n";

// Provide recommendations
if ($stats['not_found'] > 0 || $stats['error'] > 0) {
    echo "\n" . $colors['yellow'] . "Recommendations:" . $colors['reset'] . "\n";
    
    if ($stats['not_found'] > 0) {
        echo "  • Some routes are missing. Check routes/web.php for missing route definitions.\n";
    }
    
    if ($stats['error'] > 0) {
        echo "  • Server errors detected. Check PHP error logs and fix any syntax/runtime errors.\n";
        echo "  • Run: tail -f /home/robug/YFEvents/logs/error.log\n";
    }
}

echo "\n";