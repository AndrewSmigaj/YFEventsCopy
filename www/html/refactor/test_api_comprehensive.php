<?php

/**
 * Comprehensive API Testing Framework
 * Tests all API endpoints with various parameters and scenarios
 */

declare(strict_types=1);

// Configuration
$baseUrl = 'http://localhost:8000';
$timeout = 30;
$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Colors for output
$colors = [
    'green' => "\033[32m",
    'red' => "\033[31m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'magenta' => "\033[35m",
    'cyan' => "\033[36m",
    'reset' => "\033[0m"
];

function colorOutput($text, $color) {
    global $colors;
    return $colors[$color] . $text . $colors['reset'];
}

function makeRequest($endpoint, $method = 'GET', $data = null, $headers = []) {
    global $baseUrl, $timeout;
    
    $url = $baseUrl . $endpoint;
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array_merge([
            'Accept: application/json',
            'Content-Type: application/json'
        ], $headers)
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
    }
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'endpoint' => $endpoint,
        'method' => $method,
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'response' => $response,
        'response_time' => $responseTime,
        'error' => $error,
        'data' => $data
    ];
}

function testEndpoint($name, $endpoint, $method = 'GET', $data = null, $expectedCode = 200, $description = '') {
    global $testResults, $totalTests, $passedTests, $failedTests;
    
    $totalTests++;
    
    echo colorOutput("Testing: $name", 'blue') . " - $description\n";
    echo "  Endpoint: $method $endpoint\n";
    
    $result = makeRequest($endpoint, $method, $data);
    
    $passed = ($result['http_code'] == $expectedCode && !$result['error']);
    
    if ($passed) {
        $passedTests++;
        echo colorOutput("  ✓ PASS", 'green') . " - HTTP {$result['http_code']} ({$result['response_time']}ms)\n";
    } else {
        $failedTests++;
        echo colorOutput("  ✗ FAIL", 'red') . " - HTTP {$result['http_code']} (Expected: $expectedCode)\n";
        if ($result['error']) {
            echo colorOutput("  Error: {$result['error']}", 'red') . "\n";
        }
    }
    
    // Parse and validate JSON response
    $jsonData = null;
    if (!empty($result['response'])) {
        $jsonData = json_decode($result['response'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "  Response: Valid JSON (" . strlen($result['response']) . " bytes)\n";
            
            // Show structure for successful responses
            if ($passed && isset($jsonData['data'])) {
                if (is_array($jsonData['data']) && !empty($jsonData['data'])) {
                    $count = count($jsonData['data']);
                    echo "  Data: $count items returned\n";
                    
                    // Show sample of first item structure
                    $firstItem = is_array($jsonData['data']) ? reset($jsonData['data']) : $jsonData['data'];
                    if (is_array($firstItem)) {
                        $fields = array_keys($firstItem);
                        echo "  Fields: " . implode(', ', array_slice($fields, 0, 8)) . 
                             (count($fields) > 8 ? '...' : '') . "\n";
                    }
                }
            }
        } else {
            echo colorOutput("  Invalid JSON response", 'yellow') . "\n";
            echo "  Raw response: " . substr($result['response'], 0, 200) . "...\n";
        }
    }
    
    $testResults[] = [
        'name' => $name,
        'endpoint' => $endpoint,
        'method' => $method,
        'expected_code' => $expectedCode,
        'actual_code' => $result['http_code'],
        'passed' => $passed,
        'response_time' => $result['response_time'],
        'json_data' => $jsonData,
        'error' => $result['error'],
        'description' => $description
    ];
    
    echo "\n";
    return $result;
}

function testAuthentication() {
    echo colorOutput("=== AUTHENTICATION TESTS ===", 'magenta') . "\n\n";
    
    // Test admin endpoints without authentication (should fail)
    testEndpoint(
        'Admin Events - No Auth',
        '/api/admin/events',
        'GET',
        null,
        401,
        'Should require admin authentication'
    );
    
    testEndpoint(
        'Admin Shops - No Auth',
        '/api/admin/shops',
        'GET',
        null,
        401,
        'Should require admin authentication'
    );
    
    testEndpoint(
        'Scrapers - No Auth',
        '/api/scrapers',
        'GET',
        null,
        401,
        'Should require admin authentication'
    );
}

function testHealthEndpoint() {
    echo colorOutput("=== HEALTH CHECK TESTS ===", 'magenta') . "\n\n";
    
    testEndpoint(
        'Health Check',
        '/api/health',
        'GET',
        null,
        200,
        'System health status'
    );
}

function testEventsAPI() {
    echo colorOutput("=== EVENTS API TESTS ===", 'magenta') . "\n\n";
    
    // Basic endpoints
    testEndpoint(
        'Events Index',
        '/api/events',
        'GET',
        null,
        200,
        'Get all approved events'
    );
    
    testEndpoint(
        'Events with Pagination',
        '/api/events?page=1&limit=5',
        'GET',
        null,
        200,
        'Test pagination parameters'
    );
    
    testEndpoint(
        'Events with Search',
        '/api/events?search=wine',
        'GET',
        null,
        200,
        'Search events by keyword'
    );
    
    testEndpoint(
        'Events with Filters',
        '/api/events?featured=true&source_id=1',
        'GET',
        null,
        200,
        'Filter by featured and source'
    );
    
    testEndpoint(
        'Events with Date Range',
        '/api/events?start_date=2025-01-01&end_date=2025-12-31',
        'GET',
        null,
        200,
        'Filter by date range'
    );
    
    // Specific event
    testEndpoint(
        'Single Event - Valid ID',
        '/api/events/1',
        'GET',
        null,
        200,
        'Get specific event by ID'
    );
    
    testEndpoint(
        'Single Event - Invalid ID',
        '/api/events/999999',
        'GET',
        null,
        404,
        'Non-existent event should return 404'
    );
    
    testEndpoint(
        'Single Event - Non-numeric ID',
        '/api/events/abc',
        'GET',
        null,
        400,
        'Invalid ID format should return 400'
    );
    
    // Featured events
    testEndpoint(
        'Featured Events',
        '/api/events/featured',
        'GET',
        null,
        200,
        'Get featured events'
    );
    
    testEndpoint(
        'Featured Events with Limit',
        '/api/events/featured?limit=3',
        'GET',
        null,
        200,
        'Featured events with custom limit'
    );
    
    // Upcoming events
    testEndpoint(
        'Upcoming Events',
        '/api/events/upcoming',
        'GET',
        null,
        200,
        'Get upcoming events'
    );
    
    testEndpoint(
        'Upcoming Events with Limit',
        '/api/events/upcoming?limit=10',
        'GET',
        null,
        200,
        'Upcoming events with custom limit'
    );
    
    // Calendar events
    testEndpoint(
        'Calendar Events',
        '/api/events/calendar',
        'GET',
        null,
        200,
        'Events formatted for calendar'
    );
    
    testEndpoint(
        'Calendar Events with Range',
        '/api/events/calendar?start=2025-01-01&end=2025-01-31',
        'GET',
        null,
        200,
        'Calendar events for specific month'
    );
    
    // Nearby events
    testEndpoint(
        'Nearby Events - Valid Coordinates',
        '/api/events/nearby?lat=46.6021&lng=-120.5059&radius=10',
        'GET',
        null,
        200,
        'Events near Yakima downtown'
    );
    
    testEndpoint(
        'Nearby Events - Missing Coordinates',
        '/api/events/nearby',
        'GET',
        null,
        400,
        'Should require lat/lng parameters'
    );
    
    testEndpoint(
        'Nearby Events - Invalid Coordinates',
        '/api/events/nearby?lat=abc&lng=def',
        'GET',
        null,
        400,
        'Invalid coordinate format'
    );
    
    // Event submission
    testEndpoint(
        'Submit Event - Valid Data',
        '/api/events',
        'POST',
        [
            'title' => 'Test Event via API',
            'description' => 'This is a test event submitted via API',
            'start_datetime' => '2025-02-15 19:00:00',
            'end_datetime' => '2025-02-15 22:00:00',
            'location' => 'Test Venue',
            'address' => '123 Test St, Yakima, WA',
            'contact_info' => 'test@example.com'
        ],
        201,
        'Submit new event with valid data'
    );
    
    testEndpoint(
        'Submit Event - Missing Required Fields',
        '/api/events',
        'POST',
        [
            'description' => 'Event without title or date'
        ],
        400,
        'Should require title and start_datetime'
    );
    
    testEndpoint(
        'Submit Event - Invalid Date Format',
        '/api/events',
        'POST',
        [
            'title' => 'Test Event',
            'start_datetime' => 'invalid-date'
        ],
        400,
        'Should validate date format'
    );
}

function testShopsAPI() {
    echo colorOutput("=== SHOPS API TESTS ===", 'magenta') . "\n\n";
    
    // Basic endpoints
    testEndpoint(
        'Shops Index',
        '/api/shops',
        'GET',
        null,
        200,
        'Get all active shops'
    );
    
    testEndpoint(
        'Shops with Pagination',
        '/api/shops?page=1&limit=5',
        'GET',
        null,
        200,
        'Test pagination parameters'
    );
    
    testEndpoint(
        'Shops with Search',
        '/api/shops?search=restaurant',
        'GET',
        null,
        200,
        'Search shops by keyword'
    );
    
    testEndpoint(
        'Shops with Filters',
        '/api/shops?featured=true&verified=true&category_id=1',
        'GET',
        null,
        200,
        'Filter by featured, verified, and category'
    );
    
    testEndpoint(
        'Shops with Payment Methods',
        '/api/shops?payment_methods=credit_card,cash',
        'GET',
        null,
        200,
        'Filter by payment methods'
    );
    
    testEndpoint(
        'Shops with Amenities',
        '/api/shops?amenities=parking,wifi',
        'GET',
        null,
        200,
        'Filter by amenities'
    );
    
    // Specific shop
    testEndpoint(
        'Single Shop - Valid ID',
        '/api/shops/1',
        'GET',
        null,
        200,
        'Get specific shop by ID'
    );
    
    testEndpoint(
        'Single Shop - Invalid ID',
        '/api/shops/999999',
        'GET',
        null,
        404,
        'Non-existent shop should return 404'
    );
    
    testEndpoint(
        'Single Shop - Non-numeric ID',
        '/api/shops/abc',
        'GET',
        null,
        400,
        'Invalid ID format should return 400'
    );
    
    // Featured shops
    testEndpoint(
        'Featured Shops',
        '/api/shops/featured',
        'GET',
        null,
        200,
        'Get featured shops'
    );
    
    testEndpoint(
        'Featured Shops with Limit',
        '/api/shops/featured?limit=3',
        'GET',
        null,
        200,
        'Featured shops with custom limit'
    );
    
    // Map shops
    testEndpoint(
        'Map Shops',
        '/api/shops/map',
        'GET',
        null,
        200,
        'Shops formatted for map display'
    );
    
    testEndpoint(
        'Map Shops with Filters',
        '/api/shops/map?category_id=2&featured=true',
        'GET',
        null,
        200,
        'Map shops with category and featured filters'
    );
    
    // Nearby shops
    testEndpoint(
        'Nearby Shops - Valid Coordinates',
        '/api/shops/nearby?lat=46.6021&lng=-120.5059&radius=5',
        'GET',
        null,
        200,
        'Shops near Yakima downtown'
    );
    
    testEndpoint(
        'Nearby Shops - Missing Coordinates',
        '/api/shops/nearby',
        'GET',
        null,
        400,
        'Should require lat/lng parameters'
    );
    
    testEndpoint(
        'Nearby Shops - Large Radius',
        '/api/shops/nearby?lat=46.6021&lng=-120.5059&radius=50',
        'GET',
        null,
        200,
        'Test with large search radius'
    );
    
    // Category shops
    testEndpoint(
        'Shops by Category',
        '/api/shops/categories/1',
        'GET',
        null,
        200,
        'Get shops in specific category'
    );
    
    testEndpoint(
        'Shops by Invalid Category',
        '/api/shops/categories/999',
        'GET',
        null,
        200,
        'Should return empty result for invalid category'
    );
    
    // Shop submission
    testEndpoint(
        'Submit Shop - Valid Data',
        '/api/shops',
        'POST',
        [
            'name' => 'Test Shop via API',
            'description' => 'This is a test shop submitted via API',
            'address' => '456 Test Ave, Yakima, WA',
            'phone' => '(509) 555-0123',
            'email' => 'test.shop@example.com',
            'website' => 'https://testshop.example.com',
            'category_id' => 1,
            'payment_methods' => ['credit_card', 'cash'],
            'amenities' => ['parking', 'wifi']
        ],
        201,
        'Submit new shop with valid data'
    );
    
    testEndpoint(
        'Submit Shop - Missing Required Fields',
        '/api/shops',
        'POST',
        [
            'description' => 'Shop without name or address'
        ],
        400,
        'Should require name and address'
    );
    
    testEndpoint(
        'Submit Shop - Invalid Email',
        '/api/shops',
        'POST',
        [
            'name' => 'Test Shop',
            'address' => '123 Test St',
            'email' => 'invalid-email'
        ],
        400,
        'Should validate email format'
    );
}

function testAdminAPI() {
    echo colorOutput("=== ADMIN API TESTS (Without Auth) ===", 'magenta') . "\n\n";
    
    // Note: These should all fail with 401 since we don't have admin auth
    testEndpoint(
        'Admin Events List',
        '/api/admin/events',
        'GET',
        null,
        401,
        'Admin events list (no auth)'
    );
    
    testEndpoint(
        'Admin Events with Filters',
        '/api/admin/events?status=pending&featured=true',
        'GET',
        null,
        401,
        'Admin events with filters (no auth)'
    );
    
    testEndpoint(
        'Admin Shops List',
        '/api/admin/shops',
        'GET',
        null,
        401,
        'Admin shops list (no auth)'
    );
    
    testEndpoint(
        'Admin Shops with Filters',
        '/api/admin/shops?status=pending&verified=false',
        'GET',
        null,
        401,
        'Admin shops with filters (no auth)'
    );
    
    testEndpoint(
        'Get Scrapers List',
        '/api/scrapers',
        'GET',
        null,
        401,
        'Get available scrapers (no auth)'
    );
    
    testEndpoint(
        'Run Single Scraper',
        '/api/scrapers/run',
        'POST',
        ['scraper_id' => 1],
        401,
        'Run specific scraper (no auth)'
    );
    
    testEndpoint(
        'Run All Scrapers',
        '/api/scrapers/run-all',
        'POST',
        null,
        401,
        'Run all scrapers (no auth)'
    );
}

function testErrorHandling() {
    echo colorOutput("=== ERROR HANDLING TESTS ===", 'magenta') . "\n\n";
    
    // Test non-existent endpoints
    testEndpoint(
        'Non-existent Endpoint',
        '/api/nonexistent',
        'GET',
        null,
        404,
        'Should return 404 for invalid endpoints'
    );
    
    testEndpoint(
        'Invalid Method',
        '/api/events',
        'DELETE',
        null,
        405,
        'Should return 405 for unsupported methods'
    );
    
    // Test malformed requests
    testEndpoint(
        'Invalid JSON in POST',
        '/api/events',
        'POST',
        'invalid-json-data',
        400,
        'Should handle malformed JSON gracefully'
    );
    
    // Test parameter validation
    testEndpoint(
        'Invalid Pagination Parameters',
        '/api/events?page=-1&limit=abc',
        'GET',
        null,
        200,
        'Should handle invalid pagination gracefully'
    );
    
    testEndpoint(
        'Extremely Large Limit',
        '/api/events?limit=99999',
        'GET',
        null,
        200,
        'Should cap limit to reasonable maximum'
    );
}

function testPerformance() {
    echo colorOutput("=== PERFORMANCE TESTS ===", 'magenta') . "\n\n";
    
    $performanceTests = [
        ['/api/health', 'Health check'],
        ['/api/events', 'Events list'],
        ['/api/events/featured', 'Featured events'],
        ['/api/events/upcoming', 'Upcoming events'],
        ['/api/events/calendar', 'Calendar events'],
        ['/api/shops', 'Shops list'],
        ['/api/shops/featured', 'Featured shops'],
        ['/api/shops/map', 'Map shops']
    ];
    
    foreach ($performanceTests as [$endpoint, $description]) {
        $result = testEndpoint(
            "Performance - $description",
            $endpoint,
            'GET',
            null,
            200,
            "Response time test for $description"
        );
        
        // Flag slow responses
        if ($result['response_time'] > 2000) {
            echo colorOutput("  ⚠ SLOW RESPONSE", 'yellow') . " - {$result['response_time']}ms\n";
        } elseif ($result['response_time'] > 1000) {
            echo colorOutput("  ⚠ MODERATE RESPONSE", 'yellow') . " - {$result['response_time']}ms\n";
        }
    }
}

function testConcurrency() {
    echo colorOutput("=== CONCURRENCY TESTS ===", 'magenta') . "\n\n";
    
    // Test multiple simultaneous requests
    $endpoints = [
        '/api/events',
        '/api/events/featured',
        '/api/shops',
        '/api/shops/featured',
        '/api/health'
    ];
    
    echo "Testing concurrent requests to multiple endpoints...\n";
    
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    
    foreach ($endpoints as $endpoint) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "http://localhost:8000$endpoint",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[] = $ch;
    }
    
    $startTime = microtime(true);
    
    // Execute all requests
    do {
        $status = curl_multi_exec($multiHandle, $active);
        if ($active) {
            curl_multi_select($multiHandle);
        }
    } while ($active && $status == CURLM_OK);
    
    $totalTime = round((microtime(true) - $startTime) * 1000, 2);
    
    // Check results
    $successCount = 0;
    foreach ($curlHandles as $i => $ch) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode == 200) {
            $successCount++;
        }
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($multiHandle);
    
    echo "Concurrent requests completed in {$totalTime}ms\n";
    echo "Success rate: $successCount/" . count($endpoints) . " requests\n\n";
}

function generateReport() {
    global $testResults, $totalTests, $passedTests, $failedTests;
    
    echo colorOutput("=== COMPREHENSIVE API TEST REPORT ===", 'cyan') . "\n\n";
    
    // Summary
    echo colorOutput("SUMMARY:", 'yellow') . "\n";
    echo "Total Tests: $totalTests\n";
    echo colorOutput("Passed: $passedTests", 'green') . "\n";
    echo colorOutput("Failed: $failedTests", 'red') . "\n";
    echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";
    
    // Performance summary
    $responseTimes = array_column($testResults, 'response_time');
    $avgResponseTime = round(array_sum($responseTimes) / count($responseTimes), 2);
    $maxResponseTime = max($responseTimes);
    $minResponseTime = min($responseTimes);
    
    echo colorOutput("PERFORMANCE:", 'yellow') . "\n";
    echo "Average Response Time: {$avgResponseTime}ms\n";
    echo "Fastest Response: {$minResponseTime}ms\n";
    echo "Slowest Response: {$maxResponseTime}ms\n\n";
    
    // Failed tests details
    if ($failedTests > 0) {
        echo colorOutput("FAILED TESTS:", 'red') . "\n";
        foreach ($testResults as $test) {
            if (!$test['passed']) {
                echo "❌ {$test['name']}\n";
                echo "   Endpoint: {$test['method']} {$test['endpoint']}\n";
                echo "   Expected: HTTP {$test['expected_code']}, Got: HTTP {$test['actual_code']}\n";
                if ($test['error']) {
                    echo "   Error: {$test['error']}\n";
                }
                echo "\n";
            }
        }
    }
    
    // Slow tests
    $slowTests = array_filter($testResults, fn($test) => $test['response_time'] > 1000);
    if (!empty($slowTests)) {
        echo colorOutput("SLOW RESPONSES (>1000ms):", 'yellow') . "\n";
        foreach ($slowTests as $test) {
            echo "⚠️ {$test['name']} - {$test['response_time']}ms\n";
        }
        echo "\n";
    }
    
    // API Coverage
    echo colorOutput("API COVERAGE:", 'yellow') . "\n";
    $endpoints = array_unique(array_column($testResults, 'endpoint'));
    $testedEndpoints = count($endpoints);
    echo "Tested Endpoints: $testedEndpoints\n";
    echo "Unique Endpoints:\n";
    foreach ($endpoints as $endpoint) {
        echo "  - $endpoint\n";
    }
    echo "\n";
    
    // Save detailed report
    $reportData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'summary' => [
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'failed_tests' => $failedTests,
            'success_rate' => round(($passedTests / $totalTests) * 100, 1)
        ],
        'performance' => [
            'average_response_time' => $avgResponseTime,
            'min_response_time' => $minResponseTime,
            'max_response_time' => $maxResponseTime
        ],
        'tested_endpoints' => $testedEndpoints,
        'detailed_results' => $testResults
    ];
    
    file_put_contents('api_test_results.json', json_encode($reportData, JSON_PRETTY_PRINT));
    
    echo colorOutput("DETAILED RESULTS:", 'blue') . "\n";
    echo "Full test results saved to: api_test_results.json\n";
    echo "Test completed at: " . date('Y-m-d H:i:s') . "\n\n";
}

// Main execution
echo colorOutput("🚀 COMPREHENSIVE API TESTING FRAMEWORK", 'cyan') . "\n";
echo colorOutput("====================================", 'cyan') . "\n\n";

echo "Starting comprehensive API tests...\n";
echo "Base URL: $baseUrl\n";
echo "Timeout: {$timeout}s\n\n";

// Run all test suites
testHealthEndpoint();
testEventsAPI();
testShopsAPI();
testAuthentication();
testAdminAPI();
testErrorHandling();
testPerformance();
testConcurrency();

// Generate final report
generateReport();

echo colorOutput("✅ API Testing Complete!", 'green') . "\n";