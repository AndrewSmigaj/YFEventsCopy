<?php
/**
 * Detailed route testing with actual page content analysis
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Test specific routes and analyze errors
$baseUrl = 'http://localhost';

function testRoute($url, $description) {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Testing: $description\n";
    echo "URL: $url\n";
    echo str_repeat('-', 60) . "\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    
    // Check for redirects
    if (preg_match('/Location: (.+)/', $header, $matches)) {
        echo "Redirect to: " . trim($matches[1]) . "\n";
    }
    
    // Extract title
    if (preg_match('/<title>([^<]+)<\/title>/i', $body, $matches)) {
        echo "Page Title: " . trim($matches[1]) . "\n";
    }
    
    // Look for error messages
    $errors = [];
    
    // PHP errors
    if (preg_match('/Fatal error: (.+?) in (.+?) on line (\d+)/', $body, $matches)) {
        $errors[] = "Fatal error: {$matches[1]} in {$matches[2]} on line {$matches[3]}";
    }
    if (preg_match('/Warning: (.+?) in (.+?) on line (\d+)/', $body, $matches)) {
        $errors[] = "Warning: {$matches[1]} in {$matches[2]} on line {$matches[3]}";
    }
    if (preg_match('/Notice: (.+?) in (.+?) on line (\d+)/', $body, $matches)) {
        $errors[] = "Notice: {$matches[1]} in {$matches[2]} on line {$matches[3]}";
    }
    
    // Custom error messages
    if (strpos($body, 'no seller id in session') !== false) {
        $errors[] = "Session error: No seller ID in session";
    }
    if (strpos($body, 'Class not found') !== false) {
        preg_match('/Class ([\'"])(.+?)\1 not found/', $body, $matches);
        $errors[] = "Class not found: " . ($matches[2] ?? 'Unknown');
    }
    if (strpos($body, 'Call to undefined method') !== false) {
        preg_match('/Call to undefined method (.+)/', $body, $matches);
        $errors[] = "Undefined method: " . ($matches[1] ?? 'Unknown');
    }
    if (strpos($body, 'Access denied') !== false || strpos($body, 'Unauthorized') !== false) {
        $errors[] = "Authentication required";
    }
    
    // Display errors
    if (!empty($errors)) {
        echo "\nErrors Found:\n";
        foreach ($errors as $error) {
            echo "  • $error\n";
        }
    }
    
    // Show excerpt of body if it's an error page
    if ($httpCode >= 400 || !empty($errors)) {
        $bodyText = strip_tags($body);
        $bodyText = preg_replace('/\s+/', ' ', $bodyText);
        $bodyText = trim($bodyText);
        
        if (strlen($bodyText) > 300) {
            $bodyText = substr($bodyText, 0, 300) . '...';
        }
        
        echo "\nBody excerpt:\n";
        echo wordwrap($bodyText, 80, "\n") . "\n";
    }
    
    return ['status' => $httpCode, 'errors' => $errors];
}

// Test problematic routes
echo "YFEvents Detailed Route Testing\n";
echo "==============================\n";

// Public routes that showed issues
testRoute($baseUrl . '/health', 'Health check (404 error)');
testRoute($baseUrl . '/claims/sale', 'Claims sale page without ID');
testRoute($baseUrl . '/api/events/nearby', 'Nearby events API');

// Seller routes that need session
testRoute($baseUrl . '/seller/dashboard', 'Seller dashboard (no auth)');
testRoute($baseUrl . '/seller/sale/1/edit', 'Edit sale (no auth)');

// Authentication pages
testRoute($baseUrl . '/seller/login', 'Seller login page');
testRoute($baseUrl . '/admin/login', 'Admin login page');

// Module routes
testRoute($baseUrl . '/theme/editor', 'Theme editor module');
testRoute($baseUrl . '/estate-sales/upcoming', 'Upcoming estate sales');

// API routes
testRoute($baseUrl . '/api/shops/nearby?lat=46.6&lng=-120.5', 'Nearby shops with coordinates');

echo "\n" . str_repeat('=', 60) . "\n";
echo "Testing Complete\n";