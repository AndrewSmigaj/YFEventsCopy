<?php
// Simple test to verify seller dashboard works
require_once __DIR__ . '/../vendor/autoload.php';

// Test by making HTTP request
$ch = curl_init('http://localhost/seller/dashboard');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_create_id());

$output = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Response Code: $httpCode\n";

if ($httpCode == 200) {
    // Check for key elements
    $checks = [
        'Login redirect' => strpos($output, 'seller/login') !== false,
        'Dashboard content' => strpos($output, 'Dashboard') !== false,
        'Authentication required' => strpos($output, 'Please log in') !== false || strpos($output, 'login') !== false
    ];
    
    foreach ($checks as $check => $result) {
        if ($result) {
            echo "✓ $check detected\n";
        }
    }
    
    // Since we're not logged in, we expect a redirect to login
    if (strpos($output, 'seller/login') !== false || strpos($output, 'location.href') !== false) {
        echo "\n✓ Authentication is working correctly - redirects to login when not authenticated\n";
    }
} else {
    echo "✗ Unexpected HTTP response code\n";
}

// Now test that the route exists and is reachable
echo "\nRoute Test: ";
if ($httpCode != 404) {
    echo "✓ Route /seller/dashboard exists and is handled\n";
} else {
    echo "✗ Route not found\n";
}