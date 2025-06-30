<?php

declare(strict_types=1);

/**
 * Comprehensive Error Handling Test Script
 * Tests various error conditions and validates responses
 */

class ErrorHandlingTester
{
    private string $baseUrl;
    private array $testResults = [];
    private int $totalTests = 0;
    private int $passedTests = 0;

    public function __construct(string $baseUrl = 'http://localhost')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Run all error handling tests
     */
    public function runAllTests(): void
    {
        echo "Starting Comprehensive Error Handling Tests\n";
        echo "==========================================\n\n";

        $this->test404Routes();
        $this->testInvalidRouteParameters();
        $this->testMethodMismatches();
        $this->testMalformedRequests();
        $this->testAuthenticationFailures();
        $this->testApiErrorResponses();
        $this->testEdgeCases();

        $this->printSummary();
    }

    /**
     * Test 404 routes - non-existent URLs
     */
    private function test404Routes(): void
    {
        echo "Testing 404 Routes\n";
        echo "==================\n";

        $notFoundRoutes = [
            '/nonexistent',
            '/events/nonexistent',
            '/shops/nonexistent',
            '/admin/nonexistent',
            '/api/nonexistent',
            '/events/123/details',
            '/shops/456/reviews',
            '/admin/events/789/publish',
            '/api/v2/events',
            '/random/path/here',
            '/..%2F..%2Fetc%2Fpasswd',  // Path traversal attempt
            '/\\..\\..\\windows\\system32',  // Windows path traversal
        ];

        foreach ($notFoundRoutes as $route) {
            $this->testRoute($route, 'GET', [], 404, "404 for non-existent route: $route");
        }

        echo "\n";
    }

    /**
     * Test invalid route parameters
     */
    private function testInvalidRouteParameters(): void
    {
        echo "Testing Invalid Route Parameters\n";
        echo "===============================\n";

        $invalidParameterRoutes = [
            // Invalid numeric IDs
            ['/api/events/abc', 'GET', [], [400, 404, 500]], // Non-numeric ID
            ['/api/shops/xyz', 'GET', [], [400, 404, 500]],
            ['/shops/999999999', 'GET', [], [404, 500]], // Very large ID
            ['/api/events/-1', 'GET', [], [400, 404]], // Negative ID
            ['/api/shops/0', 'GET', [], [400, 404]], // Zero ID
            
            // SQL injection attempts in parameters
            ['/api/events/1\'; DROP TABLE events; --', 'GET', [], [400, 404, 500]],
            ['/api/shops/1 UNION SELECT * FROM users', 'GET', [], [400, 404, 500]],
            
            // Special characters in IDs
            ['/api/events/<script>alert(1)</script>', 'GET', [], [400, 404, 500]],
            ['/api/shops/../../admin', 'GET', [], [400, 404, 500]],
            
            // Admin routes with invalid IDs
            ['/admin/events/invalid/update', 'POST', [], [400, 401, 404, 500]],
            ['/admin/shops/invalid/delete', 'POST', [], [400, 401, 404, 500]],
        ];

        foreach ($invalidParameterRoutes as [$route, $method, $data, $expectedCodes]) {
            $this->testRoute($route, $method, $data, $expectedCodes, "Invalid parameter test: $method $route");
        }

        echo "\n";
    }

    /**
     * Test method mismatches
     */
    private function testMethodMismatches(): void
    {
        echo "Testing Method Mismatches\n";
        echo "========================\n";

        $methodMismatches = [
            // GET-only routes with POST
            ['/', 'POST', [], 405],
            ['/events', 'POST', [], 405],
            ['/shops', 'POST', [], 405],
            ['/api/events', 'DELETE', [], 405],
            ['/api/shops', 'PUT', [], 405],
            
            // POST-only routes with GET
            ['/admin/login', 'GET', [], [200, 405]], // This might be valid
            ['/admin/events/1/update', 'GET', [], 405],
            ['/admin/shops/1/delete', 'GET', [], 405],
            ['/api/events/submit', 'GET', [], 405],
            
            // Unsupported methods
            ['/api/events', 'PATCH', [], 405],
            ['/api/shops', 'OPTIONS', [], [200, 405]], // OPTIONS might be allowed
            ['/', 'HEAD', [], [200, 405]], // HEAD might be allowed
            ['/api/events/1', 'TRACE', [], 405],
        ];

        foreach ($methodMismatches as [$route, $method, $data, $expectedCode]) {
            $this->testRoute($route, $method, $data, $expectedCode, "Method mismatch: $method $route");
        }

        echo "\n";
    }

    /**
     * Test malformed requests
     */
    private function testMalformedRequests(): void
    {
        echo "Testing Malformed Requests\n";
        echo "=========================\n";

        // Test malformed JSON in POST requests
        $malformedRequests = [
            ['/api/events', 'POST', 'invalid-json', 400],
            ['/api/shops', 'POST', '{"incomplete": json', 400],
            ['/admin/events/create', 'POST', '{key: "no quotes"}', [400, 401]],
        ];

        foreach ($malformedRequests as [$route, $method, $data, $expectedCode]) {
            $this->testMalformedJsonRoute($route, $method, $data, $expectedCode, "Malformed JSON: $method $route");
        }

        // Test missing required fields
        $missingFieldRequests = [
            ['/api/events', 'POST', [], [400, 401]],
            ['/api/shops', 'POST', ['name' => ''], [400, 401]], // Empty required field
            ['/api/events/submit', 'POST', ['title' => 'Test', 'description' => ''], [400, 401]],
        ];

        foreach ($missingFieldRequests as [$route, $method, $data, $expectedCode]) {
            $this->testRoute($route, $method, $data, $expectedCode, "Missing fields: $method $route");
        }

        echo "\n";
    }

    /**
     * Test authentication failures
     */
    private function testAuthenticationFailures(): void
    {
        echo "Testing Authentication Failures\n";
        echo "===============================\n";

        $protectedRoutes = [
            // Admin routes
            ['/admin/dashboard', 'GET', [], 401],
            ['/admin/events', 'GET', [], 401],
            ['/admin/shops', 'GET', [], 401],
            ['/admin/events/create', 'POST', [], 401],
            ['/admin/shops/1/update', 'POST', [], 401],
            ['/admin/events/1/delete', 'POST', [], 401],
            ['/admin/events/statistics', 'GET', [], 401],
            ['/admin/shops/statistics', 'GET', [], 401],
            
            // Admin API routes
            ['/api/admin/events', 'GET', [], 401],
            ['/api/admin/shops', 'GET', [], 401],
            ['/api/scrapers', 'GET', [], 401],
            ['/api/scrapers/run', 'POST', [], 401],
        ];

        foreach ($protectedRoutes as [$route, $method, $data, $expectedCode]) {
            $this->testRoute($route, $method, $data, $expectedCode, "Auth required: $method $route");
        }

        echo "\n";
    }

    /**
     * Test API error responses format
     */
    private function testApiErrorResponses(): void
    {
        echo "Testing API Error Response Formats\n";
        echo "==================================\n";

        $apiRoutes = [
            '/api/events/999999',
            '/api/shops/999999',
            '/api/nonexistent',
            '/api/events/invalid-id',
        ];

        foreach ($apiRoutes as $route) {
            $response = $this->makeRequest($route, 'GET', []);
            $this->validateApiErrorFormat($response, "API error format: GET $route");
        }

        echo "\n";
    }

    /**
     * Test edge cases and security
     */
    private function testEdgeCases(): void
    {
        echo "Testing Edge Cases and Security\n";
        echo "==============================\n";

        $edgeCases = [
            // Very long URLs
            ['/' . str_repeat('a', 2000), 'GET', [], [404, 414]], // URI too long
            
            // Unicode and special characters
            ['/events/🚀', 'GET', [], [400, 404]],
            ['/shops/münchen', 'GET', [], [400, 404]],
            ['/api/events/test%00null', 'GET', [], [400, 404]],
            
            // Multiple slashes
            ['//events', 'GET', [], [200, 404]],
            ['/events///', 'GET', [], [200, 404]],
            ['/api///events', 'GET', [], [200, 404]],
            
            // Case sensitivity
            ['/API/events', 'GET', [], [200, 404]],
            ['/Events', 'GET', [], [200, 404]],
            ['/SHOPS', 'GET', [], [200, 404]],
            
            // Trailing slashes
            ['/events/', 'GET', [], [200, 404]],
            ['/api/events/', 'GET', [], [200, 404]],
            ['/admin/dashboard/', 'GET', [], [200, 401, 404]],
        ];

        foreach ($edgeCases as [$route, $method, $data, $expectedCodes]) {
            $this->testRoute($route, $method, $data, $expectedCodes, "Edge case: $method $route");
        }

        echo "\n";
    }

    /**
     * Test a route and validate response
     */
    private function testRoute(string $route, string $method, array $data, $expectedCode, string $description): void
    {
        $this->totalTests++;
        $response = $this->makeRequest($route, $method, $data);
        
        $expectedCodes = is_array($expectedCode) ? $expectedCode : [$expectedCode];
        $success = in_array($response['status_code'], $expectedCodes);
        
        if ($success) {
            $this->passedTests++;
            echo "✓ PASS: $description (Status: {$response['status_code']})\n";
        } else {
            echo "✗ FAIL: $description (Expected: " . implode('|', $expectedCodes) . ", Got: {$response['status_code']})\n";
            if (!empty($response['body'])) {
                echo "  Response: " . substr($response['body'], 0, 200) . "\n";
            }
        }

        $this->testResults[] = [
            'description' => $description,
            'route' => $route,
            'method' => $method,
            'expected' => $expectedCodes,
            'actual' => $response['status_code'],
            'success' => $success,
            'response' => $response['body']
        ];
    }

    /**
     * Test route with malformed JSON
     */
    private function testMalformedJsonRoute(string $route, string $method, string $data, $expectedCode, string $description): void
    {
        $this->totalTests++;
        $response = $this->makeRequestWithRawData($route, $method, $data);
        
        $expectedCodes = is_array($expectedCode) ? $expectedCode : [$expectedCode];
        $success = in_array($response['status_code'], $expectedCodes);
        
        if ($success) {
            $this->passedTests++;
            echo "✓ PASS: $description (Status: {$response['status_code']})\n";
        } else {
            echo "✗ FAIL: $description (Expected: " . implode('|', $expectedCodes) . ", Got: {$response['status_code']})\n";
        }
    }

    /**
     * Validate API error response format
     */
    private function validateApiErrorFormat(array $response, string $description): void
    {
        $this->totalTests++;
        $success = false;

        if ($response['status_code'] >= 400) {
            $body = json_decode($response['body'], true);
            if ($body && isset($body['error'])) {
                $success = true;
                echo "✓ PASS: $description - Proper API error format\n";
            } else {
                echo "✗ FAIL: $description - Invalid API error format\n";
                echo "  Response: " . substr($response['body'], 0, 200) . "\n";
            }
        } else {
            echo "✗ FAIL: $description - Expected error status code, got {$response['status_code']}\n";
        }

        if ($success) {
            $this->passedTests++;
        }
    }

    /**
     * Make HTTP request
     */
    private function makeRequest(string $path, string $method, array $data): array
    {
        $url = $this->baseUrl . $path;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        if ($method === 'POST' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'status_code' => 0,
                'body' => "cURL Error: $error",
                'error' => $error
            ];
        }

        return [
            'status_code' => $statusCode,
            'body' => $body ?: '',
            'error' => null
        ];
    }

    /**
     * Make HTTP request with raw data
     */
    private function makeRequestWithRawData(string $path, string $method, string $data): array
    {
        $url = $this->baseUrl . $path;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'status_code' => 0,
                'body' => "cURL Error: $error",
                'error' => $error
            ];
        }

        return [
            'status_code' => $statusCode,
            'body' => $body ?: '',
            'error' => null
        ];
    }

    /**
     * Print test summary
     */
    private function printSummary(): void
    {
        echo "\nTest Summary\n";
        echo "============\n";
        echo "Total Tests: {$this->totalTests}\n";
        echo "Passed: {$this->passedTests}\n";
        echo "Failed: " . ($this->totalTests - $this->passedTests) . "\n";
        echo "Success Rate: " . round(($this->passedTests / $this->totalTests) * 100, 2) . "%\n";

        // Save detailed results to file
        $resultsFile = 'error_handling_test_results.json';
        file_put_contents($resultsFile, json_encode([
            'summary' => [
                'total' => $this->totalTests,
                'passed' => $this->passedTests,
                'failed' => $this->totalTests - $this->passedTests,
                'success_rate' => round(($this->passedTests / $this->totalTests) * 100, 2)
            ],
            'results' => $this->testResults,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT));

        echo "\nDetailed results saved to: $resultsFile\n";
    }
}

// Run the tests
if (php_sapi_name() === 'cli') {
    $baseUrl = $argv[1] ?? 'http://localhost';
    $tester = new ErrorHandlingTester($baseUrl);
    $tester->runAllTests();
} else {
    echo "This script should be run from the command line.\n";
    echo "Usage: php test_error_handling.php [base_url]\n";
    echo "Example: php test_error_handling.php http://localhost/refactor\n";
}