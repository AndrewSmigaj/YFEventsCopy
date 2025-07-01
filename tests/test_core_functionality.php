#!/usr/bin/env php
<?php
/**
 * Core Functionality Test Suite
 * Tests all major YFEvents components
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

class YFEventsTestSuite
{
    private $db;
    private $passed = 0;
    private $failed = 0;
    private $tests = [];

    public function __construct($database)
    {
        $this->db = $database;
        echo "🧪 YFEvents Core Functionality Test Suite\n";
        echo "==========================================\n\n";
    }

    public function test($name, $callback)
    {
        try {
            $result = $callback();
            if ($result === true) {
                echo "✅ PASS: $name\n";
                $this->passed++;
                $this->tests[$name] = 'PASS';
            } else {
                echo "❌ FAIL: $name - $result\n";
                $this->failed++;
                $this->tests[$name] = "FAIL: $result";
            }
        } catch (Exception $e) {
            echo "❌ ERROR: $name - " . $e->getMessage() . "\n";
            $this->failed++;
            $this->tests[$name] = "ERROR: " . $e->getMessage();
        }
    }

    public function runAllTests()
    {
        // Database Tests
        echo "📊 Database Tests\n";
        echo "-----------------\n";
        $this->testDatabaseConnection();
        $this->testCoreTableStructure();
        $this->testYFClaimTables();
        $this->testDataIntegrity();

        // Configuration Tests
        echo "\n⚙️ Configuration Tests\n";
        echo "----------------------\n";
        $this->testEnvironmentConfig();
        $this->testAutoloader();
        $this->testAPIKeys();

        // Model Tests
        echo "\n🏗️ Model Tests\n";
        echo "---------------\n";
        $this->testModelLoading();
        $this->testGeocodeService();

        // Admin Interface Tests
        echo "\n🌐 Admin Interface Tests\n";
        echo "------------------------\n";
        $this->testAdminPages();
        $this->testAdvancedAdmin();
        $this->testYFClaimAdmin();

        // API Tests
        echo "\n🔌 API Tests\n";
        echo "------------\n";
        $this->testAPIEndpoints();

        $this->showSummary();
    }

    private function testDatabaseConnection()
    {
        $this->test("Database Connection", function() {
            $result = $this->db->query("SELECT 1");
            return $result !== false;
        });
    }

    private function testCoreTableStructure()
    {
        $tables = ['events', 'local_shops', 'calendar_sources', 'event_categories'];
        
        foreach ($tables as $table) {
            $this->test("Table exists: $table", function() use ($table) {
                $stmt = $this->db->query("DESCRIBE $table");
                return $stmt !== false;
            });
        }
    }

    private function testYFClaimTables()
    {
        $tables = ['yfc_sellers', 'yfc_sales', 'yfc_items', 'yfc_offers', 'yfc_buyers', 'yfc_categories'];
        
        foreach ($tables as $table) {
            $this->test("YFClaim table exists: $table", function() use ($table) {
                $stmt = $this->db->query("DESCRIBE $table");
                return $stmt !== false;
            });
        }

        $this->test("YFClaim categories populated", function() {
            $stmt = $this->db->query("SELECT COUNT(*) FROM yfc_categories");
            $count = $stmt->fetchColumn();
            return $count >= 9;
        });
    }

    private function testDataIntegrity()
    {
        $this->test("Events table has data", function() {
            $stmt = $this->db->query("SELECT COUNT(*) FROM events");
            $count = $stmt->fetchColumn();
            return $count > 0;
        });

        $this->test("Shops table has data", function() {
            $stmt = $this->db->query("SELECT COUNT(*) FROM local_shops");
            $count = $stmt->fetchColumn();
            return $count > 0;
        });

        $this->test("Approved future events exist", function() {
            $stmt = $this->db->query("SELECT COUNT(*) FROM events WHERE status = 'approved' AND start_datetime >= NOW()");
            $count = $stmt->fetchColumn();
            return $count > 0;
        });
    }

    private function testEnvironmentConfig()
    {
        $this->test("Environment file exists", function() {
            return file_exists(__DIR__ . '/../.env');
        });

        $this->test("Environment variables loaded", function() {
            return !empty($_ENV['DB_NAME']) || !empty(getenv('DB_NAME'));
        });

        $this->test("Google Maps API key configured", function() {
            return !empty($_ENV['GOOGLE_MAPS_API_KEY']);
        });
    }

    private function testAutoloader()
    {
        $this->test("Composer autoloader working", function() {
            return class_exists('YFEvents\Utils\GeocodeService');
        });

        $this->test("YFClaim models loadable", function() {
            return class_exists('YFEvents\Modules\YFClaim\Models\BaseModel');
        });
    }

    private function testAPIKeys()
    {
        $this->test("Google Maps API key format", function() {
            $key = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
            return !empty($key) && strlen($key) > 30;
        });
    }

    private function testModelLoading()
    {
        $this->test("GeocodeService instantiation", function() {
            $service = new YakimaFinds\Utils\GeocodeService();
            return $service instanceof YakimaFinds\Utils\GeocodeService;
        });

        $this->test("YFClaim SellerModel instantiation", function() {
            $model = new YFEvents\Modules\YFClaim\Models\SellerModel($this->db);
            return $model instanceof YFEvents\Modules\YFClaim\Models\SellerModel;
        });
    }

    private function testGeocodeService()
    {
        $this->test("Geocoding service validation", function() {
            $service = new YakimaFinds\Utils\GeocodeService();
            return $service->validateCoordinates(46.6021, -120.5059);
        });

        $this->test("Distance calculation", function() {
            $service = new YakimaFinds\Utils\GeocodeService();
            $distance = $service->calculateDistance(46.6021, -120.5059, 46.6022, -120.5060);
            return $distance > 0 && $distance < 1;
        });
    }

    private function testAdminPages()
    {
        $pages = [
            '/admin/index.php' => 'Main Admin',
            '/admin/events.php' => 'Events Management',
            '/admin/shops.php' => 'Shops Management',
            '/admin/geocode-fix.php' => 'Geocoding Tool'
        ];

        foreach ($pages as $path => $name) {
            $this->test("Admin page exists: $name", function() use ($path) {
                return file_exists(__DIR__ . '/../www/html' . $path);
            });
        }
    }

    private function testAdvancedAdmin()
    {
        $pages = [
            '/admin/calendar/index.php' => 'Advanced Dashboard',
            '/admin/calendar/events.php' => 'Advanced Events',
            '/admin/calendar/sources.php' => 'Advanced Sources',
            '/admin/calendar/shops.php' => 'Advanced Shops'
        ];

        foreach ($pages as $path => $name) {
            $this->test("Advanced admin exists: $name", function() use ($path) {
                return file_exists(__DIR__ . '/../www/html' . $path);
            });
        }
    }

    private function testYFClaimAdmin()
    {
        $this->test("YFClaim admin index exists", function() {
            return file_exists(__DIR__ . '/../modules/yfclaim/www/admin/index.php');
        });

        $this->test("YFClaim database schema exists", function() {
            return file_exists(__DIR__ . '/../modules/yfclaim/database/schema.sql');
        });

        $this->test("YFClaim model structure", function() {
            return file_exists(__DIR__ . '/../modules/yfclaim/src/Models/SellerModel.php');
        });
    }

    private function testAPIEndpoints()
    {
        $this->test("Events API file exists", function() {
            return file_exists(__DIR__ . '/../www/html/api/events-simple.php');
        });

        $this->test("Calendar events AJAX exists", function() {
            return file_exists(__DIR__ . '/../www/html/ajax/calendar-events.php');
        });

        $this->test("Advanced admin AJAX exists", function() {
            return file_exists(__DIR__ . '/../www/html/admin/calendar/ajax/approve-event.php');
        });
    }

    private function showSummary()
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 TEST SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📊 Total:  " . ($this->passed + $this->failed) . "\n";
        
        if ($this->failed > 0) {
            echo "\n❌ FAILED TESTS:\n";
            foreach ($this->tests as $name => $result) {
                if (strpos($result, 'FAIL') === 0 || strpos($result, 'ERROR') === 0) {
                    echo "   • $name: $result\n";
                }
            }
        }

        $percentage = round(($this->passed / ($this->passed + $this->failed)) * 100, 1);
        echo "\n🎯 Success Rate: {$percentage}%\n";
        
        if ($percentage >= 95) {
            echo "🎉 EXCELLENT! System is fully functional.\n";
        } elseif ($percentage >= 80) {
            echo "✅ GOOD! Minor issues to address.\n";
        } else {
            echo "⚠️ NEEDS ATTENTION! Major issues found.\n";
        }
    }
}

// Run the tests
try {
    $testSuite = new YFEventsTestSuite($db);
    $testSuite->runAllTests();
} catch (Exception $e) {
    echo "💥 CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}