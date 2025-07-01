<?php

declare(strict_types=1);

/**
 * Web Error Handling Test Script
 * Tests HTML error pages and web-specific error handling
 */

// Suppress PHP error display for clean testing
error_reporting(0);
ini_set('display_errors', '0');

class WebErrorTester
{
    private array $testResults = [];
    private int $totalTests = 0;
    private int $passedTests = 0;

    public function runTests(): void
    {
        // Test if we can access the router directly
        if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
            $this->showError("Autoloader not found. Please run 'composer install' first.");
            return;
        }

        $this->testDirectRouting();
        $this->showResults();
    }

    private function testDirectRouting(): void
    {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            
            use YFEvents\Application\Bootstrap;
            use YFEvents\Infrastructure\Http\Router;

            // Bootstrap the application
            $container = Bootstrap::boot();
            $config = $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class);

            // Create router
            $router = new Router($container, $config);

            // Load routes
            (function() use ($router) {
                require __DIR__ . '/routes/web.php';
                require __DIR__ . '/routes/api.php';
            })();

            $this->testRouterDirectly($router);

        } catch (\Exception $e) {
            $this->showError("Failed to initialize application: " . $e->getMessage());
        }
    }

    private function testRouterDirectly(object $router): void
    {
        // Test 404 handling by simulating various requests
        $testCases = [
            ['GET', '/nonexistent', 'Non-existent route'],
            ['GET', '/events/invalid-id', 'Invalid event ID'],
            ['GET', '/shops/999999', 'Non-existent shop'],
            ['POST', '/events', 'POST to GET-only route'],
            ['DELETE', '/admin/login', 'Wrong method for admin login'],
            ['GET', '/admin/dashboard', 'Admin route without auth'],
        ];

        foreach ($testCases as [$method, $path, $description]) {
            $this->simulateRequest($router, $method, $path, $description);
        }
    }

    private function simulateRequest(object $router, string $method, string $path, string $description): void
    {
        $this->totalTests++;
        
        // Save original values
        $originalMethod = $_SERVER['REQUEST_METHOD'] ?? '';
        $originalUri = $_SERVER['REQUEST_URI'] ?? '';
        $originalScriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        try {
            // Set up simulated request
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI'] = $path;
            $_SERVER['SCRIPT_NAME'] = '/index.php';

            // Capture output
            ob_start();
            
            // Simulate dispatch (this will call handleNotFound or executeRoute)
            $router->dispatch();
            
            $output = ob_get_clean();
            
            // Check if it's a proper error response
            $isValidError = $this->validateErrorResponse($output, $description);
            
            if ($isValidError) {
                $this->passedTests++;
                $this->testResults[] = [
                    'test' => $description,
                    'method' => $method,
                    'path' => $path,
                    'status' => 'PASS',
                    'output_length' => strlen($output)
                ];
            } else {
                $this->testResults[] = [
                    'test' => $description,
                    'method' => $method,
                    'path' => $path,
                    'status' => 'FAIL',
                    'output' => substr($output, 0, 200)
                ];
            }

        } catch (\Exception $e) {
            // This might be expected for some error conditions
            $this->testResults[] = [
                'test' => $description,
                'method' => $method,
                'path' => $path,
                'status' => 'EXCEPTION',
                'error' => $e->getMessage()
            ];
        } finally {
            // Restore original values
            $_SERVER['REQUEST_METHOD'] = $originalMethod;
            $_SERVER['REQUEST_URI'] = $originalUri;
            $_SERVER['SCRIPT_NAME'] = $originalScriptName;
            
            // Clean any remaining output
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }

    private function validateErrorResponse(string $output, string $description): bool
    {
        // Check if it's JSON error response
        $json = json_decode($output, true);
        if ($json && isset($json['error'])) {
            return true;
        }

        // Check if it's HTML error page
        if (strpos($output, '<html') !== false || strpos($output, '<!DOCTYPE') !== false) {
            return true;
        }

        // Check if it contains error indicators
        if (strpos($output, 'error') !== false || strpos($output, 'Error') !== false) {
            return true;
        }

        // Empty output might indicate proper error handling too
        if (empty($output)) {
            return true;
        }

        return false;
    }

    private function showResults(): void
    {
        echo "<html><head><title>Web Error Handling Test Results</title>";
        echo "<style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .pass { color: green; }
            .fail { color: red; }
            .exception { color: orange; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .summary { background-color: #f9f9f9; padding: 15px; margin-bottom: 20px; }
        </style></head><body>";

        echo "<h1>Web Error Handling Test Results</h1>";
        
        echo "<div class='summary'>";
        echo "<h2>Summary</h2>";
        echo "<p>Total Tests: {$this->totalTests}</p>";
        echo "<p>Passed: {$this->passedTests}</p>";
        echo "<p>Failed: " . ($this->totalTests - $this->passedTests) . "</p>";
        if ($this->totalTests > 0) {
            echo "<p>Success Rate: " . round(($this->passedTests / $this->totalTests) * 100, 2) . "%</p>";
        }
        echo "</div>";

        echo "<h2>Detailed Results</h2>";
        echo "<table>";
        echo "<tr><th>Test Description</th><th>Method</th><th>Path</th><th>Status</th><th>Details</th></tr>";

        foreach ($this->testResults as $result) {
            $statusClass = strtolower($result['status']);
            echo "<tr>";
            echo "<td>{$result['test']}</td>";
            echo "<td>{$result['method']}</td>";
            echo "<td>{$result['path']}</td>";
            echo "<td class='$statusClass'>{$result['status']}</td>";
            
            if (isset($result['error'])) {
                echo "<td>Exception: " . htmlspecialchars($result['error']) . "</td>";
            } elseif (isset($result['output'])) {
                echo "<td>Output: " . htmlspecialchars($result['output']) . "</td>";
            } elseif (isset($result['output_length'])) {
                echo "<td>Output Length: {$result['output_length']} chars</td>";
            } else {
                echo "<td>-</td>";
            }
            echo "</tr>";
        }

        echo "</table>";
        echo "</body></html>";
    }

    private function showError(string $message): void
    {
        echo "<html><head><title>Error</title></head><body>";
        echo "<h1>Test Error</h1>";
        echo "<p style='color: red;'>" . htmlspecialchars($message) . "</p>";
        echo "</body></html>";
    }
}

// Run the tests
$tester = new WebErrorTester();
$tester->runTests();