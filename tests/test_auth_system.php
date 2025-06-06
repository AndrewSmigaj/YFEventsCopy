<?php
/**
 * Test the new authentication system
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Utils\Auth;

class AuthSystemTester {
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
        echo "🔐 Authentication System Test Suite\n";
        echo "===================================\n\n";

        // Test Auth class instantiation
        try {
            $auth = new Auth($this->db);
            $this->test('Auth class instantiation', true);
        } catch (Exception $e) {
            $this->test('Auth class instantiation', false, $e->getMessage());
            return false;
        }

        // Test user registration
        echo "\n📝 User Registration Tests\n";
        echo "-------------------------\n";
        $this->testUserRegistration($auth);

        // Test user login
        echo "\n🔑 User Login Tests\n";
        echo "------------------\n";
        $this->testUserLogin($auth);

        // Test session management
        echo "\n🎫 Session Management Tests\n";
        echo "---------------------------\n";
        $this->testSessionManagement($auth);

        // Summary
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "🔐 AUTHENTICATION TEST SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📊 Total:  {$this->total}\n\n";

        $success_rate = $this->total > 0 ? round(($this->passed / $this->total) * 100, 1) : 0;
        echo "🎯 Success Rate: {$success_rate}%\n";

        if ($success_rate >= 90) {
            echo "🎉 EXCELLENT! Authentication system is ready.\n";
        } elseif ($success_rate >= 75) {
            echo "👍 GOOD! Minor issues need fixing.\n";
        } else {
            echo "⚠️ WARNING! Authentication system has issues.\n";
        }

        return $success_rate >= 75;
    }

    private function testUserRegistration($auth) {
        // Test invalid registration data
        $result = $auth->register([]);
        $this->test('Registration validation - empty data', !$result['success']);

        $result = $auth->register(['username' => 'test']);
        $this->test('Registration validation - missing fields', !$result['success']);

        $result = $auth->register([
            'username' => 'test',
            'email' => 'invalid-email',
            'password' => 'test'
        ]);
        $this->test('Registration validation - invalid email', !$result['success']);

        $result = $auth->register([
            'username' => 'test',
            'email' => 'test@example.com',
            'password' => '123' // too short
        ]);
        $this->test('Registration validation - weak password', !$result['success']);

        // Test valid registration
        $testUser = [
            'username' => 'testuser_' . time(),
            'email' => 'test_' . time() . '@example.com',
            'password' => 'testpassword123',
            'first_name' => 'Test',
            'last_name' => 'User'
        ];

        $result = $auth->register($testUser);
        $this->test('Valid user registration', $result['success'], 
            $result['message'] ?? 'Unknown error');

        if ($result['success']) {
            // Store for login test
            $this->testUsername = $testUser['username'];
            $this->testPassword = $testUser['password'];
        }

        // Test duplicate registration
        $result = $auth->register($testUser);
        $this->test('Duplicate registration prevention', !$result['success']);
    }

    private function testUserLogin($auth) {
        if (!isset($this->testUsername)) {
            echo "⚠️ Skipping login tests - no test user created\n";
            return;
        }

        // Test invalid login
        $result = $auth->login('nonexistent', 'wrongpassword');
        $this->test('Invalid login rejection', !$result['success']);

        // Test valid login
        $result = $auth->login($this->testUsername, $this->testPassword);
        $this->test('Valid user login', $result['success'], 
            $result['message'] ?? 'Unknown error');

        if ($result['success']) {
            $this->test('Login returns user data', isset($result['user']));
            $this->test('User data has required fields', 
                isset($result['user']['id']) && 
                isset($result['user']['username']) && 
                isset($result['user']['email'])
            );
        }
    }

    private function testSessionManagement($auth) {
        // Test getCurrentUser
        $user = $auth->getCurrentUser();
        $this->test('getCurrentUser after login', $user !== null);

        if ($user) {
            $this->test('Current user has valid data', 
                isset($user['id']) && isset($user['username'])
            );
        }

        // Test role checking
        $this->test('hasRole method works', method_exists($auth, 'hasRole'));
        
        if (method_exists($auth, 'hasRole')) {
            $this->test('Default user role check', $auth->hasRole('user'));
            $this->test('Admin role check (should fail)', !$auth->hasRole('admin'));
        }

        // Test logout
        $result = $auth->logout();
        $this->test('User logout', $result['success']);

        // Test session cleared after logout
        $user = $auth->getCurrentUser();
        $this->test('Session cleared after logout', $user === null);
    }
}

// Run the tests
try {
    $tester = new AuthSystemTester($db);
    $success = $tester->runTests();
    
    // Return appropriate exit code
    exit($success ? 0 : 1);
    
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: Failed to run authentication tests\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}