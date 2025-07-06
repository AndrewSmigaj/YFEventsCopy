<?php
/**
 * Comprehensive Route Testing Script
 * Tests all routes and documents errors
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Application\Bootstrap;

// Colors for output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[0;33m");
define('BLUE', "\033[0;34m");
define('RESET', "\033[0m");

class RouteTestRunner
{
    private string $baseUrl = 'http://localhost';
    private array $results = [];
    private array $cookies = [];
    private $ch;

    public function __construct()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    public function run(): void
    {
        echo BLUE . "\n=== YFEvents Route Testing Script ===\n" . RESET;
        echo "Testing all routes at: {$this->baseUrl}\n\n";

        // Test categories
        $this->testPublicRoutes();
        $this->testAuthenticationRoutes();
        $this->testSellerRoutes();
        $this->testAdminRoutes();
        $this->testApiRoutes();
        $this->testModuleRoutes();

        // Generate report
        $this->generateReport();
    }

    private function testRoute(string $method, string $path, array $data = [], string $category = 'General'): array
    {
        $url = $this->baseUrl . $path;
        
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($method === 'POST' && !empty($data)) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }
        
        $response = curl_exec($this->ch);
        $headerSize = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        
        // Extract error messages from body
        $errorMessages = [];
        if (preg_match('/<title>([^<]+)<\/title>/', $body, $matches)) {
            if (stripos($matches[1], 'error') !== false || stripos($matches[1], '404') !== false) {
                $errorMessages[] = $matches[1];
            }
        }
        
        // Look for common error patterns
        if (preg_match('/Fatal error: (.+?) in/', $body, $matches)) {
            $errorMessages[] = "Fatal error: " . $matches[1];
        }
        if (preg_match('/Warning: (.+?) in/', $body, $matches)) {
            $errorMessages[] = "Warning: " . $matches[1];
        }
        if (preg_match('/Notice: (.+?) in/', $body, $matches)) {
            $errorMessages[] = "Notice: " . $matches[1];
        }
        if (strpos($body, 'no seller id in session') !== false) {
            $errorMessages[] = "No seller ID in session";
        }
        if (strpos($body, 'Class not found') !== false) {
            $errorMessages[] = "Class not found error";
        }
        
        $result = [
            'method' => $method,
            'path' => $path,
            'url' => $url,
            'status' => $httpCode,
            'category' => $category,
            'errors' => $errorMessages,
            'success' => $httpCode >= 200 && $httpCode < 400,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->results[] = $result;
        
        // Display result
        $icon = $result['success'] ? GREEN . '✓' : RED . '✗';
        echo sprintf("%s %s %s %s [%d]%s", 
            $icon, 
            str_pad($method, 6), 
            str_pad($path, 50), 
            $httpCode,
            count($errorMessages),
            RESET
        );
        
        if (!empty($errorMessages)) {
            echo RED . " Errors: " . implode(', ', $errorMessages) . RESET;
        }
        echo "\n";
        
        return $result;
    }

    private function testPublicRoutes(): void
    {
        echo YELLOW . "\n--- Testing Public Routes ---\n" . RESET;
        
        // Home and general
        $this->testRoute('GET', '/', [], 'Public');
        $this->testRoute('GET', '/debug', [], 'Public');
        $this->testRoute('GET', '/map', [], 'Public');
        $this->testRoute('GET', '/health', [], 'Public');
        
        // Events
        $this->testRoute('GET', '/events', [], 'Events');
        $this->testRoute('GET', '/events/featured', [], 'Events');
        $this->testRoute('GET', '/events/upcoming', [], 'Events');
        $this->testRoute('GET', '/events/calendar', [], 'Events');
        $this->testRoute('GET', '/events/submit', [], 'Events');
        $this->testRoute('GET', '/events/1', [], 'Events'); // Test with ID
        
        // Shops
        $this->testRoute('GET', '/shops', [], 'Shops');
        $this->testRoute('GET', '/shops/featured', [], 'Shops');
        $this->testRoute('GET', '/shops/map', [], 'Shops');
        $this->testRoute('GET', '/shops/submit', [], 'Shops');
        $this->testRoute('GET', '/shops/1', [], 'Shops'); // Test with ID
        
        // Claims/Estate Sales
        $this->testRoute('GET', '/claims', [], 'Claims');
        $this->testRoute('GET', '/claims/sale', [], 'Claims');
        $this->testRoute('GET', '/claims/sale?id=1', [], 'Claims'); // With query param
        $this->testRoute('GET', '/claims/item/1', [], 'Claims');
        $this->testRoute('GET', '/claims/items', [], 'Claims');
        $this->testRoute('GET', '/estate-sales', [], 'Claims');
        $this->testRoute('GET', '/estate-sales/upcoming', [], 'Claims');
        $this->testRoute('GET', '/estate-sales/sale/1', [], 'Claims');
        
        // Classifieds
        $this->testRoute('GET', '/classifieds', [], 'Classifieds');
        $this->testRoute('GET', '/classifieds/item/1', [], 'Classifieds');
        $this->testRoute('GET', '/classifieds/category/electronics', [], 'Classifieds');
    }

    private function testAuthenticationRoutes(): void
    {
        echo YELLOW . "\n--- Testing Authentication Routes ---\n" . RESET;
        
        // Admin auth
        $this->testRoute('GET', '/admin/login', [], 'Auth');
        $this->testRoute('POST', '/admin/login', [
            'username' => 'test_admin_' . time() . '@example.com',
            'password' => 'TestPassword123!'
        ], 'Auth');
        $this->testRoute('GET', '/admin/status', [], 'Auth');
        
        // Seller auth
        $this->testRoute('GET', '/seller/login', [], 'Auth');
        $this->testRoute('GET', '/seller/register', [], 'Auth');
        $this->testRoute('POST', '/seller/register', [
            'email' => 'test_seller_' . time() . '@example.com',
            'password' => 'TestPassword123!',
            'company_name' => 'Test Estate Sales Co',
            'phone' => '555-1234'
        ], 'Auth');
        
        // Buyer auth
        $this->testRoute('GET', '/buyer/auth', [], 'Auth');
        $this->testRoute('POST', '/buyer/auth/send', [
            'email' => 'test_buyer_' . time() . '@example.com'
        ], 'Auth');
    }

    private function testSellerRoutes(): void
    {
        echo YELLOW . "\n--- Testing Seller Routes (May fail without auth) ---\n" . RESET;
        
        $this->testRoute('GET', '/seller/dashboard', [], 'Seller');
        $this->testRoute('GET', '/seller/sales', [], 'Seller');
        $this->testRoute('GET', '/seller/sale/new', [], 'Seller');
        $this->testRoute('POST', '/seller/sale/create', [
            'title' => 'Test Sale',
            'description' => 'Test description'
        ], 'Seller');
        $this->testRoute('GET', '/seller/sale/1/edit', [], 'Seller');
        $this->testRoute('POST', '/seller/sale/1/update', [], 'Seller');
        $this->testRoute('GET', '/seller/sale/1/items', [], 'Seller');
        $this->testRoute('POST', '/seller/logout', [], 'Seller');
    }

    private function testAdminRoutes(): void
    {
        echo YELLOW . "\n--- Testing Admin Routes (May fail without auth) ---\n" . RESET;
        
        // Dashboard
        $this->testRoute('GET', '/admin/dashboard', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/data', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/statistics', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/health', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/activity', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/performance', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/moderation', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/users', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/top-content', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/alerts', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/analytics', [], 'Admin');
        $this->testRoute('GET', '/admin/dashboard/export', [], 'Admin');
        
        // Events management
        $this->testRoute('GET', '/admin/events', [], 'Admin');
        $this->testRoute('GET', '/admin/events/statistics', [], 'Admin');
        
        // Shops management
        $this->testRoute('GET', '/admin/shops', [], 'Admin');
        $this->testRoute('GET', '/admin/shops/statistics', [], 'Admin');
        
        // Sellers management
        $this->testRoute('GET', '/admin/sellers', [], 'Admin');
        $this->testRoute('GET', '/admin/sellers/1', [], 'Admin');
        $this->testRoute('GET', '/admin/sellers/1/sales', [], 'Admin');
        $this->testRoute('GET', '/admin/sales', [], 'Admin');
        $this->testRoute('GET', '/admin/sales/1', [], 'Admin');
    }

    private function testApiRoutes(): void
    {
        echo YELLOW . "\n--- Testing API Routes ---\n" . RESET;
        
        // Event APIs
        $this->testRoute('GET', '/api/events', [], 'API');
        $this->testRoute('GET', '/api/events/calendar', [], 'API');
        $this->testRoute('GET', '/api/events/featured', [], 'API');
        $this->testRoute('GET', '/api/events/upcoming', [], 'API');
        $this->testRoute('GET', '/api/events/nearby', [], 'API');
        $this->testRoute('GET', '/api/events/1', [], 'API');
        
        // Shop APIs
        $this->testRoute('GET', '/api/shops', [], 'API');
        $this->testRoute('GET', '/api/shops/map', [], 'API');
        $this->testRoute('GET', '/api/shops/featured', [], 'API');
        $this->testRoute('GET', '/api/shops/nearby', [], 'API');
        $this->testRoute('GET', '/api/shops/1', [], 'API');
        
        // Claims APIs
        $this->testRoute('GET', '/api/claims/sale/1/items', [], 'API');
        $this->testRoute('GET', '/api/claims/items', [], 'API');
        
        // Health check
        $this->testRoute('GET', '/api/health', [], 'API');
    }

    private function testModuleRoutes(): void
    {
        echo YELLOW . "\n--- Testing Module Routes ---\n" . RESET;
        
        // Theme editor
        $this->testRoute('GET', '/theme/editor', [], 'Modules');
    }

    private function generateReport(): void
    {
        echo BLUE . "\n\n=== ROUTE TESTING REPORT ===\n" . RESET;
        
        $totalRoutes = count($this->results);
        $successCount = count(array_filter($this->results, fn($r) => $r['success']));
        $failureCount = $totalRoutes - $successCount;
        
        echo "\nTotal Routes Tested: $totalRoutes\n";
        echo GREEN . "Successful: $successCount\n" . RESET;
        echo RED . "Failed: $failureCount\n" . RESET;
        
        // Group errors by type
        $errorTypes = [
            '404' => [],
            'Session' => [],
            'Fatal' => [],
            'Class Not Found' => [],
            'Other' => []
        ];
        
        foreach ($this->results as $result) {
            if (!$result['success']) {
                $categorized = false;
                
                if ($result['status'] === 404) {
                    $errorTypes['404'][] = $result;
                    $categorized = true;
                }
                
                foreach ($result['errors'] as $error) {
                    if (stripos($error, 'session') !== false) {
                        $errorTypes['Session'][] = $result;
                        $categorized = true;
                        break;
                    } elseif (stripos($error, 'fatal') !== false) {
                        $errorTypes['Fatal'][] = $result;
                        $categorized = true;
                        break;
                    } elseif (stripos($error, 'class') !== false && stripos($error, 'not found') !== false) {
                        $errorTypes['Class Not Found'][] = $result;
                        $categorized = true;
                        break;
                    }
                }
                
                if (!$categorized) {
                    $errorTypes['Other'][] = $result;
                }
            }
        }
        
        // Display error summary
        echo YELLOW . "\n--- Error Summary ---\n" . RESET;
        foreach ($errorTypes as $type => $errors) {
            if (!empty($errors)) {
                echo "\n" . RED . "$type Errors (" . count($errors) . "):\n" . RESET;
                foreach ($errors as $error) {
                    echo "  - {$error['method']} {$error['path']}";
                    if (!empty($error['errors'])) {
                        echo " - " . implode(', ', $error['errors']);
                    }
                    echo "\n";
                }
            }
        }
        
        // Save detailed report
        $reportFile = __DIR__ . '/route_test_report_' . date('Y-m-d_His') . '.json';
        file_put_contents($reportFile, json_encode([
            'summary' => [
                'total' => $totalRoutes,
                'success' => $successCount,
                'failure' => $failureCount,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'results' => $this->results,
            'errorTypes' => $errorTypes
        ], JSON_PRETTY_PRINT));
        
        echo BLUE . "\nDetailed report saved to: $reportFile\n" . RESET;
        
        // Recommendations
        echo YELLOW . "\n--- Recommendations ---\n" . RESET;
        if (count($errorTypes['404']) > 0) {
            echo "• " . count($errorTypes['404']) . " routes returning 404 - Check if controllers/methods exist\n";
        }
        if (count($errorTypes['Session']) > 0) {
            echo "• " . count($errorTypes['Session']) . " session-related errors - Implement proper session handling\n";
        }
        if (count($errorTypes['Class Not Found']) > 0) {
            echo "• " . count($errorTypes['Class Not Found']) . " missing classes - Check controller namespaces and autoloading\n";
        }
        if (count($errorTypes['Fatal']) > 0) {
            echo "• " . count($errorTypes['Fatal']) . " fatal errors - Critical issues that need immediate attention\n";
        }
    }
}

// Run the tests
$tester = new RouteTestRunner();
$tester->run();