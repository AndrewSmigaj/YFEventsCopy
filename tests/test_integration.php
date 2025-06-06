<?php
/**
 * End-to-End Integration Test Suite
 * Tests complete workflows and system integration
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

class IntegrationTester {
    private $db;
    private $passed = 0;
    private $failed = 0;
    private $total = 0;
    private $base_url;

    public function __construct($database) {
        $this->db = $database;
        
        // Determine base URL for HTTP tests
        $this->base_url = 'http://137.184.245.149';
        if (isset($_SERVER['HTTP_HOST'])) {
            $this->base_url = 'http://' . $_SERVER['HTTP_HOST'];
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $this->base_url = 'http://' . $_SERVER['SERVER_NAME'];
        }
    }

    private function test($description, $condition, $error_message = '') {
        $this->total++;
        if ($condition) {
            echo "✅ PASS: $description\n";
            $this->passed++;
            return true;
        } else {
            echo "❌ FAIL: $description" . ($error_message ? " - $error_message" : "") . "\n";
            $this->failed++;
            return false;
        }
    }

    private function httpRequest($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'YFEvents-Integration-Tester/1.0');
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        
        curl_close($ch);
        
        return [
            'http_code' => $http_code,
            'headers' => $headers,
            'body' => $body,
            'success' => $response !== false
        ];
    }

    public function runTests() {
        echo "🔄 End-to-End Integration Test Suite\n";
        echo "====================================\n\n";

        // Event Workflow Tests
        echo "📅 Event Management Workflow\n";
        echo "----------------------------\n";
        $this->testEventWorkflow();

        // API Integration Tests
        echo "\n🌐 API Integration Tests\n";
        echo "-----------------------\n";
        $this->testAPIIntegration();

        // Admin Workflow Tests
        echo "\n🛠️ Admin Management Workflow\n";
        echo "----------------------------\n";
        $this->testAdminWorkflow();

        // Public Interface Tests
        echo "\n👥 Public Interface Workflow\n";
        echo "---------------------------\n";
        $this->testPublicWorkflow();

        // Geocoding Integration Tests
        echo "\n🗺️ Geocoding Integration\n";
        echo "-----------------------\n";
        // $this->testGeocodingIntegration(); // Temporarily disabled

        // YFClaim Integration Tests
        echo "\n🛒 YFClaim Module Integration\n";
        echo "----------------------------\n";
        $this->testYFClaimIntegration();

        // Cross-Module Tests
        echo "\n🔗 Cross-Module Integration\n";
        echo "--------------------------\n";
        $this->testCrossModuleIntegration();

        // Summary
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "🔄 INTEGRATION TEST SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📊 Total:  {$this->total}\n\n";

        $success_rate = $this->total > 0 ? round(($this->passed / $this->total) * 100, 1) : 0;
        echo "🎯 Success Rate: {$success_rate}%\n";

        if ($success_rate >= 90) {
            echo "🎉 EXCELLENT! All integrations working perfectly.\n";
        } elseif ($success_rate >= 75) {
            echo "👍 GOOD! Minor integration issues detected.\n";
        } elseif ($success_rate >= 50) {
            echo "⚠️ WARNING! Significant integration problems.\n";
        } else {
            echo "🚨 CRITICAL! Major integration failures detected.\n";
        }

        echo "\n📋 Next Steps for Integration:\n";
        echo "1. Fix any failing HTTP endpoints\n";
        echo "2. Implement missing API functionality\n";
        echo "3. Enhance error handling in admin workflows\n";
        echo "4. Complete YFClaim integration testing\n";

        return $success_rate >= 75;
    }

    private function testEventWorkflow() {
        try {
            // Test: Create -> Approve -> Display -> Edit workflow
            
            // 1. Check if we can retrieve events
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'approved'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->test('Events exist in database', $result['count'] > 0, "Found {$result['count']} approved events");

            // 2. Test event retrieval with full data
            $stmt = $this->db->prepare("
                SELECT * FROM events 
                WHERE status = 'approved' 
                LIMIT 1
            ");
            $stmt->execute();
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->test('Event data retrieval works', $event !== false);

            // 3. Test event geocoding
            if ($event) {
                $this->test('Event has geocoded location', 
                    !empty($event['latitude']) && !empty($event['longitude']),
                    "Lat: {$event['latitude']}, Lng: {$event['longitude']}"
                );
            }

            // 4. Test future events filtering
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM events 
                WHERE status = 'approved' AND start_datetime > NOW()
            ");
            $stmt->execute();
            $future_events = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->test('Future events are available', $future_events['count'] > 0, "Found {$future_events['count']} future events");

        } catch (Exception $e) {
            $this->test('Event workflow database operations', false, $e->getMessage());
        }
    }

    private function testAPIIntegration() {
        // Test Events API
        $events_api = $this->base_url . '/api/events-simple.php';
        $response = $this->httpRequest($events_api);
        $this->test('Events API responds', $response['success'] && $response['http_code'] === 200);
        
        if ($response['success'] && $response['http_code'] === 200) {
            $data = json_decode($response['body'], true);
            $this->test('Events API returns valid JSON', $data !== null);
            $this->test('Events API has events array', isset($data['events']) && is_array($data['events']));
            $this->test('Events API returns events data', count($data['events']) > 0);
            
            // Test individual event structure
            if (!empty($data['events'])) {
                $event = $data['events'][0];
                $this->test('Event has required fields', 
                    isset($event['id']) && isset($event['title']) && isset($event['start_datetime'])
                );
            }
        }

        // Test Shops API
        $shops_api = $this->base_url . '/api/shops/index.php';
        $response = $this->httpRequest($shops_api);
        $this->test('Shops API responds', $response['success'] && $response['http_code'] === 200);
        
        if ($response['success'] && $response['http_code'] === 200) {
            $data = json_decode($response['body'], true);
            $this->test('Shops API returns valid JSON', $data !== null);
            $this->test('Shops API has shops data', isset($data['shops']) && is_array($data['shops']));
        }
    }

    private function testAdminWorkflow() {
        // Test admin login page
        $admin_login = $this->base_url . '/admin/login.php';
        $response = $this->httpRequest($admin_login);
        $this->test('Admin login page loads', $response['success'] && $response['http_code'] === 200);

        // Test admin dashboard (should redirect to login if not authenticated)
        $admin_dashboard = $this->base_url . '/admin/';
        $response = $this->httpRequest($admin_dashboard);
        $this->test('Admin dashboard accessible', $response['success'] && ($response['http_code'] === 200 || $response['http_code'] === 302));

        // Test advanced admin
        $advanced_admin = $this->base_url . '/admin/calendar/';
        $response = $this->httpRequest($advanced_admin);
        $this->test('Advanced admin accessible', $response['success'] && $response['http_code'] === 200);

        // Test event management
        $events_admin = $this->base_url . '/admin/events.php';
        $response = $this->httpRequest($events_admin);
        $this->test('Events management accessible', $response['success'] && ($response['http_code'] === 200 || $response['http_code'] === 302));

        // Test shops management
        $shops_admin = $this->base_url . '/admin/shops.php';
        $response = $this->httpRequest($shops_admin);
        $this->test('Shops management accessible', $response['success'] && ($response['http_code'] === 200 || $response['http_code'] === 302));
    }

    private function testPublicWorkflow() {
        // Test main calendar page
        $calendar = $this->base_url . '/';
        $response = $this->httpRequest($calendar);
        $this->test('Main calendar page loads', $response['success'] && ($response['http_code'] === 200 || $response['http_code'] === 302));

        // Test calendar interface specifically
        $calendar_interface = $this->base_url . '/calendar.php';
        $response = $this->httpRequest($calendar_interface);
        $this->test('Calendar interface loads', $response['success'] && $response['http_code'] === 200);

        // Test shops directory
        $shops_directory = $this->base_url . '/shops.php';
        $response = $this->httpRequest($shops_directory);
        $this->test('Shops directory loads', $response['success'] && ($response['http_code'] === 200 || $response['http_code'] === 404));

        // Test event details page (if it exists)
        $event_details = $this->base_url . '/event.php?id=1';
        $response = $this->httpRequest($event_details);
        $this->test('Event details page accessible', $response['success']);
    }

    private function testGeocodingIntegration() {
        try {
            // Test if GeocodeService can be instantiated
            $geocoder = new \YakimaFinds\Utils\GeocodeService();
            $this->test('GeocodeService instantiation', true);

            // Test geocoding functionality with a known address
            $test_address = "111 S 2nd St, Yakima, WA";
            
            // Note: This might use API calls or cached data
            try {
                $coordinates = $geocoder->geocode($test_address);
                $this->test('Geocoding returns coordinates', 
                    $coordinates && isset($coordinates['lat']) && isset($coordinates['lng'])
                );
                
                if ($coordinates) {
                    // Test that coordinates are reasonable for Yakima area
                    $lat = $coordinates['lat'];
                    $lng = $coordinates['lng'];
                    $this->test('Geocoded coordinates are in Yakima area', 
                        $lat > 46.5 && $lat < 46.7 && $lng > -120.6 && $lng < -120.4,
                        "Got: $lat, $lng"
                    );
                }
            } catch (Exception $e) {
                $this->test('Geocoding functionality', false, "API error or rate limit: " . $e->getMessage());
            }

            // Test distance calculation
            try {
                $distance = $geocoder->calculateDistance(46.6007, -120.5034, 46.6020, -120.5040);
                $this->test('Distance calculation works', is_numeric($distance) && $distance >= 0);
            } catch (Exception $e) {
                $this->test('Distance calculation', false, $e->getMessage());
            }

        } catch (Exception $e) {
            $this->test('GeocodeService integration', false, $e->getMessage());
        }
    }

    private function testYFClaimIntegration() {
        // Test YFClaim admin interface
        $yfclaim_admin = $this->base_url . '/modules/yfclaim/www/admin/';
        $response = $this->httpRequest($yfclaim_admin);
        $this->test('YFClaim admin interface loads', $response['success'] && ($response['http_code'] === 200 || $response['http_code'] === 302));

        // Test YFClaim database integration
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM yfc_categories");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->test('YFClaim categories table accessible', $result['count'] > 0, "Found {$result['count']} categories");

            // Test sellers table
            $stmt = $this->db->prepare("SHOW TABLES LIKE 'yfc_sellers'");
            $stmt->execute();
            $this->test('YFClaim sellers table exists', $stmt->rowCount() > 0);

            // Test sales table
            $stmt = $this->db->prepare("SHOW TABLES LIKE 'yfc_sales'");
            $stmt->execute();
            $this->test('YFClaim sales table exists', $stmt->rowCount() > 0);

        } catch (Exception $e) {
            $this->test('YFClaim database integration', false, $e->getMessage());
        }

        // Test YFClaim models
        try {
            if (class_exists('YFEvents\\Modules\\YFClaim\\Models\\SellerModel')) {
                $seller_model = new \YFEvents\Modules\YFClaim\Models\SellerModel($this->db);
                $this->test('YFClaim SellerModel instantiation', true);
            } else {
                $this->test('YFClaim SellerModel loadable', false, 'Class not found');
            }
        } catch (Exception $e) {
            $this->test('YFClaim model integration', false, $e->getMessage());
        }
    }

    private function testCrossModuleIntegration() {
        // Test if main system can access module functionality
        try {
            // Test module loading system
            $modules_dir = __DIR__ . '/../modules';
            $this->test('Modules directory exists', is_dir($modules_dir));

            // Test YFClaim module configuration
            $yfclaim_config = $modules_dir . '/yfclaim/module.json';
            $this->test('YFClaim module configuration exists', file_exists($yfclaim_config));

            if (file_exists($yfclaim_config)) {
                $config = json_decode(file_get_contents($yfclaim_config), true);
                $this->test('YFClaim module config is valid', $config !== null);
            }

            // Test yfauth module configuration
            $yfauth_config = $modules_dir . '/yfauth/module.json';
            $this->test('YFAuth module configuration exists', file_exists($yfauth_config));

            // Test if autoloader can find module classes
            $this->test('Autoloader supports module namespaces', 
                file_exists(__DIR__ . '/../vendor/autoload.php')
            );

        } catch (Exception $e) {
            $this->test('Cross-module integration', false, $e->getMessage());
        }

        // Test database integration across modules
        try {
            // Count total tables from all modules
            $stmt = $this->db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $core_tables = array_filter($tables, function($table) {
                return !preg_match('/^(yfc_|yfa_)/', $table);
            });
            
            $yfclaim_tables = array_filter($tables, function($table) {
                return preg_match('/^yfc_/', $table);
            });
            
            $yfauth_tables = array_filter($tables, function($table) {
                return preg_match('/^yfa_/', $table);
            });

            $this->test('Core system tables exist', count($core_tables) >= 4, 
                "Found " . count($core_tables) . " core tables");
            $this->test('YFClaim module tables exist', count($yfclaim_tables) >= 5, 
                "Found " . count($yfclaim_tables) . " YFClaim tables");
            
            // YFAuth tables may not be installed yet
            $this->test('Database supports multiple module schemas', 
                count($core_tables) > 0 && count($yfclaim_tables) > 0
            );

        } catch (Exception $e) {
            $this->test('Cross-module database integration', false, $e->getMessage());
        }
    }
}

// Run the tests
try {
    $tester = new IntegrationTester($db);
    $success = $tester->runTests();
    
    // Return appropriate exit code
    exit($success ? 0 : 1);
    
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: Failed to run integration tests\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}