<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo "Testing Unread Count Endpoint\n";
echo "=============================\n\n";

// Test the endpoint exists
$ch = curl_init('http://localhost/api/communication/unread-count');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Response Code: $httpCode\n";

if ($httpCode === 401) {
    echo "✓ Endpoint exists and requires authentication (as expected)\n";
} else if ($httpCode === 200) {
    echo "✓ Endpoint exists and returned success\n";
    $data = json_decode($response, true);
    if (isset($data['unread'])) {
        echo "✓ Response has expected 'unread' field\n";
    }
} else if ($httpCode === 404) {
    echo "✗ Endpoint not found - route may not be registered\n";
} else {
    echo "? Unexpected response code: $httpCode\n";
}

echo "\nResponse: " . substr($response, 0, 200) . "\n";