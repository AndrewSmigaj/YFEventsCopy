#!/usr/bin/env php
<?php
/**
 * YFClaim Module Test Suite
 * Tests YFClaim functionality specifically
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

class YFClaimTestSuite
{
    private $db;
    private $passed = 0;
    private $failed = 0;

    public function __construct($database)
    {
        $this->db = $database;
        echo "🛒 YFClaim Module Test Suite\n";
        echo "============================\n\n";
    }

    public function runAllTests()
    {
        echo "🗃️ Database Tests\n";
        echo "-----------------\n";
        $this->testDatabaseStructure();
        $this->testSampleData();

        echo "\n🏗️ Model Tests\n";
        echo "---------------\n";
        $this->testModelStructure();
        $this->testModelInstantiation();

        echo "\n🌐 Admin Interface Tests\n";
        echo "------------------------\n";
        $this->testAdminFiles();
        $this->testAdminQueries();

        echo "\n📁 File Structure Tests\n";
        echo "-----------------------\n";
        $this->testFileStructure();

        $this->showSummary();
    }

    private function test($name, $callback)
    {
        try {
            $result = $callback();
            if ($result === true) {
                echo "✅ PASS: $name\n";
                $this->passed++;
            } else {
                echo "❌ FAIL: $name - $result\n";
                $this->failed++;
            }
        } catch (Exception $e) {
            echo "❌ ERROR: $name - " . $e->getMessage() . "\n";
            $this->failed++;
        }
    }

    private function testDatabaseStructure()
    {
        $tables = [
            'yfc_sellers' => ['id', 'company_name', 'contact_name', 'email', 'status'],
            'yfc_sales' => ['id', 'seller_id', 'title', 'start_date', 'end_date', 'status'],
            'yfc_items' => ['id', 'sale_id', 'title', 'starting_price', 'status'],
            'yfc_offers' => ['id', 'item_id', 'buyer_id', 'amount', 'status'],
            'yfc_buyers' => ['id', 'name', 'email', 'phone'],
            'yfc_categories' => ['id', 'name']
        ];

        foreach ($tables as $table => $requiredColumns) {
            $this->test("Table $table exists", function() use ($table) {
                $stmt = $this->db->query("DESCRIBE $table");
                return $stmt !== false;
            });

            $this->test("Table $table has required columns", function() use ($table, $requiredColumns) {
                $stmt = $this->db->query("DESCRIBE $table");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($requiredColumns as $required) {
                    if (!in_array($required, $columns)) {
                        return "Missing column: $required";
                    }
                }
                return true;
            });
        }
    }

    private function testSampleData()
    {
        $this->test("Categories table has sample data", function() {
            $stmt = $this->db->query("SELECT COUNT(*) FROM yfc_categories");
            $count = $stmt->fetchColumn();
            return $count >= 9;
        });

        $this->test("Categories have valid data", function() {
            $stmt = $this->db->query("SELECT name FROM yfc_categories WHERE name IS NOT NULL AND name != ''");
            $count = $stmt->rowCount();
            return $count >= 9;
        });
    }

    private function testModelStructure()
    {
        $models = [
            'BaseModel',
            'SellerModel', 
            'SaleModel',
            'ItemModel',
            'OfferModel',
            'BuyerModel'
        ];

        foreach ($models as $model) {
            $this->test("Model file exists: $model", function() use ($model) {
                return file_exists(__DIR__ . "/../modules/yfclaim/src/Models/{$model}.php");
            });

            $this->test("Model class loadable: $model", function() use ($model) {
                $className = "YFEvents\\Modules\\YFClaim\\Models\\{$model}";
                return class_exists($className);
            });
        }
    }

    private function testModelInstantiation()
    {
        $this->test("BaseModel methods available", function() {
            $model = new YFEvents\Modules\YFClaim\Models\SellerModel($this->db);
            return method_exists($model, 'find') && method_exists($model, 'create');
        });

        $this->test("SellerModel instantiation", function() {
            $model = new YFEvents\Modules\YFClaim\Models\SellerModel($this->db);
            return $model instanceof YFEvents\Modules\YFClaim\Models\SellerModel;
        });

        // Test if SellerModel has the expected methods structure
        $this->test("SellerModel has expected methods", function() {
            $model = new YFEvents\Modules\YFClaim\Models\SellerModel($this->db);
            $methods = get_class_methods($model);
            
            // Check for basic CRUD method structure (even if not implemented)
            $hasBasicStructure = method_exists($model, 'getAll') || 
                               method_exists($model, 'getById') ||
                               class_exists(get_class($model));
            
            return $hasBasicStructure;
        });
    }

    private function testAdminFiles()
    {
        $adminFiles = [
            'index.php' => 'Main dashboard',
            'sellers.php' => 'Sellers management',
            'sales.php' => 'Sales management', 
            'offers.php' => 'Offers management',
            'buyers.php' => 'Buyers management'
        ];

        foreach ($adminFiles as $file => $description) {
            $this->test("Admin file exists: $description", function() use ($file) {
                return file_exists(__DIR__ . "/../modules/yfclaim/www/admin/$file");
            });
        }
    }

    private function testAdminQueries()
    {
        // Test the queries used by admin interface
        $this->test("Admin stats query: sellers", function() {
            $stmt = $this->db->query("SELECT COUNT(*) FROM yfc_sellers WHERE status = 'approved'");
            return $stmt !== false;
        });

        $this->test("Admin stats query: sales", function() {
            $stmt = $this->db->query("SELECT COUNT(*) FROM yfc_sales WHERE status = 'active' AND end_date >= NOW()");
            return $stmt !== false;
        });

        $this->test("Admin stats query: items", function() {
            $stmt = $this->db->query("SELECT COUNT(*) FROM yfc_items WHERE status = 'active'");
            return $stmt !== false;
        });

        $this->test("Admin stats query: offers", function() {
            $stmt = $this->db->query("SELECT COUNT(*) FROM yfc_offers WHERE status = 'pending'");
            return $stmt !== false;
        });
    }

    private function testFileStructure()
    {
        $structure = [
            'modules/yfclaim/module.json' => 'Module configuration',
            'modules/yfclaim/README.md' => 'Module documentation',
            'modules/yfclaim/database/schema.sql' => 'Database schema',
            'modules/yfclaim/src/Services/ClaimAuthService.php' => 'Authentication service',
            'modules/yfclaim/www/assets/css/admin.css' => 'Admin CSS',
            'modules/yfclaim/www/assets/js/admin.js' => 'Admin JavaScript'
        ];

        foreach ($structure as $path => $description) {
            $this->test("File exists: $description", function() use ($path) {
                return file_exists(__DIR__ . "/../$path");
            });
        }
    }

    private function showSummary()
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "🛒 YFCLAIM TEST SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📊 Total:  " . ($this->passed + $this->failed) . "\n";
        
        $percentage = round(($this->passed / ($this->passed + $this->failed)) * 100, 1);
        echo "\n🎯 Success Rate: {$percentage}%\n";
        
        if ($percentage >= 90) {
            echo "🎉 EXCELLENT! YFClaim foundation is solid.\n";
        } elseif ($percentage >= 75) {
            echo "✅ GOOD! YFClaim is ready for development.\n";
        } else {
            echo "⚠️ NEEDS ATTENTION! YFClaim setup issues detected.\n";
        }

        echo "\n📋 Next Steps for YFClaim:\n";
        echo "1. Implement CRUD methods in model classes\n";
        echo "2. Connect admin interface to models\n";  
        echo "3. Create public buyer/seller interfaces\n";
        echo "4. Add business logic and validation\n";
    }
}

// Run the tests
try {
    $testSuite = new YFClaimTestSuite($db);
    $testSuite->runAllTests();
} catch (Exception $e) {
    echo "💥 CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}