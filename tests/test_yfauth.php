<?php
/**
 * YFAuth Module Test Suite
 * Tests the authentication and authorization module functionality
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

class YFAuthTester {
    private $db;
    private $passed = 0;
    private $failed = 0;
    private $total = 0;

    public function __construct($database) {
        $this->db = $database;
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

    public function runTests() {
        echo "🔐 YFAuth Module Test Suite\n";
        echo "============================\n\n";

        // Module Configuration Tests
        echo "📋 Module Configuration Tests\n";
        echo "-----------------------------\n";
        $this->testModuleConfiguration();

        // Database Schema Tests
        echo "\n🗃️ Database Schema Tests\n";
        echo "------------------------\n";
        $this->testDatabaseSchema();

        // AuthService Tests
        echo "\n🔑 AuthService Tests\n";
        echo "-------------------\n";
        $this->testAuthService();

        // API Endpoint Tests
        echo "\n🌐 API Endpoint Tests\n";
        echo "--------------------\n";
        $this->testAPIEndpoints();

        // Admin Interface Tests
        echo "\n🛠️ Admin Interface Tests\n";
        echo "------------------------\n";
        $this->testAdminInterface();

        // File Structure Tests
        echo "\n📁 File Structure Tests\n";
        echo "-----------------------\n";
        $this->testFileStructure();

        // Summary
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "🔐 YFAUTH TEST SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📊 Total:  {$this->total}\n\n";

        $success_rate = $this->total > 0 ? round(($this->passed / $this->total) * 100, 1) : 0;
        echo "🎯 Success Rate: {$success_rate}%\n";

        if ($success_rate >= 90) {
            echo "🎉 EXCELLENT! YFAuth module is ready for production.\n";
        } elseif ($success_rate >= 75) {
            echo "👍 GOOD! YFAuth module needs minor fixes.\n";
        } elseif ($success_rate >= 50) {
            echo "⚠️ WARNING! YFAuth module has significant issues.\n";
        } else {
            echo "🚨 CRITICAL! YFAuth module requires major work.\n";
        }

        return $success_rate >= 75;
    }

    private function testModuleConfiguration() {
        // Test module.json exists
        $module_file = __DIR__ . '/../modules/yfauth/module.json';
        $this->test('Module configuration file exists', file_exists($module_file));

        if (file_exists($module_file)) {
            $config = json_decode(file_get_contents($module_file), true);
            $this->test('Module configuration is valid JSON', $config !== null);
            $this->test('Module has name', isset($config['name']) && $config['name'] === 'yfauth');
            $this->test('Module has version', isset($config['version']));
            $this->test('Module has description', isset($config['description']));
            $this->test('Module defines database tables', isset($config['database']['tables']) && count($config['database']['tables']) > 0);
            $this->test('Module defines permissions', isset($config['permissions']) && count($config['permissions']) > 0);
        }
    }

    private function testDatabaseSchema() {
        try {
            // Check if auth tables exist (they might not be installed yet)
            $tables = [
                'auth_users' => 'yfa_auth_users',
                'auth_roles' => 'yfa_auth_roles', 
                'auth_permissions' => 'yfa_auth_permissions',
                'auth_role_permissions' => 'yfa_auth_role_permissions',
                'auth_user_roles' => 'yfa_auth_user_roles',
                'auth_sessions' => 'yfa_auth_sessions',
                'auth_password_resets' => 'yfa_auth_password_resets'
            ];

            $existing_tables = [];
            foreach ($tables as $logical_name => $table_name) {
                try {
                    $stmt = $this->db->query("SHOW TABLES LIKE '$table_name'");
                    $exists = $stmt->rowCount() > 0;
                    if ($exists) {
                        $existing_tables[] = $logical_name;
                    }
                    $this->test("Table $logical_name ($table_name) exists", $exists);
                } catch (Exception $e) {
                    $this->test("Table $logical_name ($table_name) exists", false, "Error checking table: " . $e->getMessage());
                }
            }

            // Test schema file exists
            $schema_file = __DIR__ . '/../modules/yfauth/database/schema.sql';
            $this->test('Database schema file exists', file_exists($schema_file));

            if (file_exists($schema_file)) {
                $schema_content = file_get_contents($schema_file);
                $this->test('Schema contains table definitions', strpos($schema_content, 'CREATE TABLE') !== false);
                $this->test('Schema uses proper prefix', strpos($schema_content, 'yfa_') !== false);
            }

        } catch (Exception $e) {
            $this->test('Database connection works', false, $e->getMessage());
        }
    }

    private function testAuthService() {
        try {
            // Test AuthService file exists
            $service_file = __DIR__ . '/../modules/yfauth/src/Services/AuthService.php';
            $this->test('AuthService file exists', file_exists($service_file));

            if (file_exists($service_file)) {
                // Test the file contains proper PHP class
                $content = file_get_contents($service_file);
                $this->test('AuthService contains class definition', strpos($content, 'class AuthService') !== false);
                $this->test('AuthService uses proper namespace', strpos($content, 'namespace YFEvents\\Modules\\YFAuth\\Services') !== false);
                
                // Try to include and test basic functionality if autoloader works
                try {
                    if (class_exists('YFEvents\\Modules\\YFAuth\\Services\\AuthService')) {
                        $this->test('AuthService class is loadable', true);
                        
                        // Test instantiation (might fail if database tables don't exist)
                        try {
                            $auth = new \YFEvents\Modules\YFAuth\Services\AuthService($this->db);
                            $this->test('AuthService instantiation', true);
                            
                            // Test basic methods exist
                            $this->test('AuthService has authenticate method', method_exists($auth, 'authenticate'));
                            $this->test('AuthService has logout method', method_exists($auth, 'logout'));
                            $this->test('AuthService has hasPermission method', method_exists($auth, 'hasPermission'));
                            
                        } catch (Exception $e) {
                            $this->test('AuthService instantiation', false, "Tables may not be installed: " . $e->getMessage());
                        }
                    } else {
                        $this->test('AuthService class is loadable', false, 'Class not found in autoloader');
                    }
                } catch (Exception $e) {
                    $this->test('AuthService class loading', false, $e->getMessage());
                }
            }
        } catch (Exception $e) {
            $this->test('AuthService testing', false, $e->getMessage());
        }
    }

    private function testAPIEndpoints() {
        // Test API files exist
        $login_api = __DIR__ . '/../modules/yfauth/www/api/login.php';
        $this->test('Login API endpoint exists', file_exists($login_api));

        if (file_exists($login_api)) {
            $content = file_get_contents($login_api);
            $this->test('Login API contains PHP code', strpos($content, '<?php') !== false);
            $this->test('Login API handles POST requests', strpos($content, '$_POST') !== false || strpos($content, 'POST') !== false);
        }

        // Test if endpoints are accessible (basic HTTP test)
        $base_url = 'http://localhost';
        if (isset($_SERVER['HTTP_HOST'])) {
            $base_url = 'http://' . $_SERVER['HTTP_HOST'];
        }
        
        $api_url = $base_url . '/modules/yfauth/www/api/login.php';
        
        // Use curl to test if the endpoint responds
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Accept any response that's not a connection error
        $this->test('Login API endpoint is accessible', $response !== false && $http_code !== 0);
    }

    private function testAdminInterface() {
        // Test admin files exist
        $admin_login = __DIR__ . '/../modules/yfauth/www/admin/login.php';
        $this->test('Admin login page exists', file_exists($admin_login));

        if (file_exists($admin_login)) {
            $content = file_get_contents($admin_login);
            $this->test('Admin login contains PHP code', strpos($content, '<?php') !== false);
            $this->test('Admin login has form elements', strpos($content, 'form') !== false || strpos($content, 'input') !== false);
        }

        // Test admin directory structure
        $admin_dir = __DIR__ . '/../modules/yfauth/www/admin';
        $this->test('Admin directory exists', is_dir($admin_dir));
    }

    private function testFileStructure() {
        $base_path = __DIR__ . '/../modules/yfauth';
        
        // Test main module files
        $this->test('Module README exists', file_exists("$base_path/README.md"));
        $this->test('Module services directory exists', is_dir("$base_path/src/Services"));
        $this->test('Module www directory exists', is_dir("$base_path/www"));
        $this->test('Module database directory exists', is_dir("$base_path/database"));
        
        // Test www structure
        $this->test('API directory exists', is_dir("$base_path/www/api"));
        $this->test('Admin directory exists', is_dir("$base_path/www/admin"));
        
        // Test if module follows PSR-4 structure
        $this->test('Source follows PSR-4 structure', is_dir("$base_path/src"));
        
        // Test README content
        $readme_file = "$base_path/README.md";
        if (file_exists($readme_file)) {
            $readme_content = file_get_contents($readme_file);
            $this->test('README has content', strlen($readme_content) > 100);
            $this->test('README mentions YFAuth', stripos($readme_content, 'yfauth') !== false);
        }
    }
}

// Run the tests
try {
    $tester = new YFAuthTester($db);
    $success = $tester->runTests();
    
    // Return appropriate exit code
    exit($success ? 0 : 1);
    
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: Failed to run YFAuth tests\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}