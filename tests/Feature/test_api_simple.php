<?php

/**
 * Simple API Testing to verify basic endpoint structure
 * This version doesn't require database connection
 */

declare(strict_types=1);

// Mock the database by setting a simple health endpoint
$baseUrl = 'http://localhost:8000';

function makeSimpleRequest($endpoint) {
    global $baseUrl;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'endpoint' => $endpoint,
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'response' => $response,
        'error' => $error
    ];
}

echo "🧪 Simple API Endpoint Test\n";
echo "=============================\n\n";

$endpoints = [
    '/api/health' => 'Health Check',
    '/api/events' => 'Events List',
    '/api/events/1' => 'Single Event',
    '/api/events/featured' => 'Featured Events',
    '/api/events/upcoming' => 'Upcoming Events',
    '/api/events/calendar' => 'Calendar Events',
    '/api/shops' => 'Shops List',
    '/api/shops/1' => 'Single Shop',
    '/api/shops/featured' => 'Featured Shops',
    '/api/shops/map' => 'Map Shops',
    '/api/admin/events' => 'Admin Events (No Auth)',
    '/api/admin/shops' => 'Admin Shops (No Auth)',
    '/api/scrapers' => 'Scrapers List (No Auth)',
    '/api/nonexistent' => 'Non-existent Endpoint'
];

$results = [];

foreach ($endpoints as $endpoint => $description) {
    echo "Testing: $description\n";
    echo "  Endpoint: $endpoint\n";
    
    $result = makeSimpleRequest($endpoint);
    
    if ($result['error']) {
        echo "  ❌ Connection Error: {$result['error']}\n";
    } else {
        echo "  ✅ HTTP {$result['http_code']}\n";
        echo "  Content-Type: {$result['content_type']}\n";
        
        // Try to parse JSON
        if (!empty($result['response'])) {
            $jsonData = json_decode($result['response'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "  📄 Valid JSON Response\n";
                
                // Show response structure
                if (isset($jsonData['error'])) {
                    echo "  🔍 Error: {$jsonData['message']}\n";
                } elseif (isset($jsonData['data'])) {
                    $count = is_array($jsonData['data']) ? count($jsonData['data']) : 1;
                    echo "  📊 Data: $count items\n";
                } elseif (isset($jsonData['status'])) {
                    echo "  🎯 Status: {$jsonData['status']}\n";
                }
            } else {
                echo "  ⚠️ Non-JSON Response\n";
                echo "  📝 Content: " . substr($result['response'], 0, 100) . "...\n";
            }
        }
    }
    
    $results[] = $result;
    echo "\n";
}

// Summary
echo "📋 SUMMARY\n";
echo "==========\n";

$total = count($results);
$successful = 0;
$errors = 0;
$httpCodes = [];

foreach ($results as $result) {
    if ($result['error']) {
        $errors++;
    } else {
        $successful++;
        $code = $result['http_code'];
        $httpCodes[$code] = ($httpCodes[$code] ?? 0) + 1;
    }
}

echo "Total Endpoints Tested: $total\n";
echo "Successful Connections: $successful\n";
echo "Connection Errors: $errors\n\n";

if (!empty($httpCodes)) {
    echo "HTTP Status Code Distribution:\n";
    foreach ($httpCodes as $code => $count) {
        echo "  HTTP $code: $count responses\n";
    }
}

echo "\n✅ API Structure Test Complete!\n";