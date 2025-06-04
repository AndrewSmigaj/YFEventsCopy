#!/usr/bin/env php
<?php
/**
 * Web Interface Test Suite
 * Tests HTTP endpoints and admin interfaces
 */

class WebInterfaceTests
{
    private $baseUrl = 'http://137.184.245.149';
    private $passed = 0;
    private $failed = 0;

    public function __construct()
    {
        echo "🌐 Web Interface Test Suite\n";
        echo "===========================\n\n";
    }

    public function runAllTests()
    {
        echo "🏠 Public Interface Tests\n";
        echo "-------------------------\n";
        $this->testPublicPages();

        echo "\n🔧 Admin Interface Tests\n";
        echo "------------------------\n";
        $this->testAdminPages();

        echo "\n🚀 Advanced Admin Tests\n";
        echo "-----------------------\n";
        $this->testAdvancedAdmin();

        echo "\n🛒 YFClaim Admin Tests\n";
        echo "----------------------\n";
        $this->testYFClaimAdmin();

        echo "\n🔌 API Endpoint Tests\n";
        echo "---------------------\n";
        $this->testAPIEndpoints();

        $this->showSummary();
    }


    private function testPublicPages()
    {
        $this->testHTTP('/', 200, 'Main calendar page');
        $this->testHTTP('/calendar.php', 200, 'Calendar interface');
        
        // Test for PHP errors (should not contain "Fatal error" or "Parse error")
        $response = $this->testHTTP('/', 200, 'Calendar page without PHP errors');
        if ($response && (strpos($response, 'Fatal error') !== false || strpos($response, 'Parse error') !== false)) {
            echo "❌ FAIL: Calendar page contains PHP errors\n";
            $this->failed++;
        } else if ($response) {
            echo "✅ PASS: Calendar page is error-free\n";
            $this->passed++;
        }
    }

    private function testAdminPages()
    {
        // Admin pages should redirect to login (302) or show content if logged in
        $this->testHTTP('/admin/', [200, 302], 'Main admin page');
        $this->testHTTP('/admin/login.php', 200, 'Admin login page');
        $this->testHTTP('/admin/events.php', [200, 302], 'Events management');
        $this->testHTTP('/admin/shops.php', [200, 302], 'Shops management');
        $this->testHTTP('/admin/geocode-fix.php', [200, 302], 'Geocoding tool');
    }

    private function testAdvancedAdmin()
    {
        $this->testHTTP('/admin/calendar/', [200, 302], 'Advanced admin dashboard');
        $this->testHTTP('/admin/calendar/events.php', [200, 302], 'Advanced events management');
        $this->testHTTP('/admin/calendar/sources.php', [200, 302], 'Advanced sources management');
        $this->testHTTP('/admin/calendar/shops.php', [200, 302], 'Advanced shops management');
    }

    private function testYFClaimAdmin()
    {
        $this->testHTTP('/modules/yfclaim/www/admin/', [200, 302], 'YFClaim admin dashboard');
        $this->testHTTP('/modules/yfclaim/www/admin/sellers.php', [200, 302], 'YFClaim sellers management');
        $this->testHTTP('/modules/yfclaim/www/admin/sales.php', [200, 302], 'YFClaim sales management');
    }

    private function testAPIEndpoints()
    {
        // API endpoints should return JSON
        $response = $this->testHTTP('/api/events-simple.php', 200, 'Events API endpoint');
        if ($response) {
            $json = json_decode($response, true);
            if ($json === null) {
                echo "❌ FAIL: Events API does not return valid JSON\n";
                $this->failed++;
            } else {
                echo "✅ PASS: Events API returns valid JSON\n";
                $this->passed++;
                
                if (isset($json['events'])) {
                    echo "✅ PASS: Events API has events array\n";
                    $this->passed++;
                } else {
                    echo "❌ FAIL: Events API missing events array\n";
                    $this->failed++;
                }
            }
        }

        // Test calendar events AJAX
        $this->testHTTP('/ajax/calendar-events.php?action=events', 200, 'Calendar AJAX endpoint');
    }

    // Updated method to handle array of expected codes
    private function testHTTP($url, $expectedCodes = 200, $description = '')
    {
        if (!is_array($expectedCodes)) {
            $expectedCodes = [$expectedCodes];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false, // Don't follow redirects to test them
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'YFEvents Test Suite'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $testName = $description ?: "HTTP " . implode('/', $expectedCodes) . " for $url";

        if ($error) {
            echo "❌ FAIL: $testName - cURL Error: $error\n";
            $this->failed++;
            return false;
        }

        if (in_array($httpCode, $expectedCodes)) {
            echo "✅ PASS: $testName (HTTP $httpCode)\n";
            $this->passed++;
            return $response;
        } else {
            echo "❌ FAIL: $testName - Expected HTTP " . implode('/', $expectedCodes) . ", got HTTP $httpCode\n";
            $this->failed++;
            return false;
        }
    }

    private function showSummary()
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "🌐 WEB INTERFACE TEST SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📊 Total:  " . ($this->passed + $this->failed) . "\n";
        
        $percentage = round(($this->passed / ($this->passed + $this->failed)) * 100, 1);
        echo "\n🎯 Success Rate: {$percentage}%\n";
        
        if ($percentage >= 90) {
            echo "🎉 EXCELLENT! All web interfaces are functional.\n";
        } elseif ($percentage >= 75) {
            echo "✅ GOOD! Minor web interface issues.\n";
        } else {
            echo "⚠️ NEEDS ATTENTION! Web interface problems detected.\n";
        }
    }
}

// Run the tests
try {
    $webTests = new WebInterfaceTests();
    $webTests->runAllTests();
} catch (Exception $e) {
    echo "💥 CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}