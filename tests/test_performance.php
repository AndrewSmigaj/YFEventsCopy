<?php
/**
 * Performance Test Suite
 * Tests system performance, load handling, and response times
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

class PerformanceTester {
    private $db;
    private $passed = 0;
    private $failed = 0;
    private $total = 0;
    private $base_url;
    private $results = [];

    public function __construct($database) {
        $this->db = $database;
        $this->base_url = 'http://137.184.245.149';
    }

    private function test($description, $condition, $error_message = '', $benchmark_data = null) {
        $this->total++;
        if ($condition) {
            echo "✅ PASS: $description\n";
            $this->passed++;
            if ($benchmark_data) {
                echo "    📊 Performance: $benchmark_data\n";
            }
            return true;
        } else {
            echo "❌ FAIL: $description" . ($error_message ? " - $error_message" : "") . "\n";
            $this->failed++;
            return false;
        }
    }

    private function benchmark($callable, $iterations = 10, $name = 'Operation') {
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $result = $callable();
            $end = microtime(true);
            $times[] = ($end - $start) * 1000; // Convert to milliseconds
        }
        
        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);
        $median = $times[floor(count($times) / 2)];
        
        $this->results[$name] = [
            'avg' => $avg,
            'min' => $min,
            'max' => $max,
            'median' => $median,
            'iterations' => $iterations
        ];
        
        return [
            'avg' => $avg,
            'min' => $min,
            'max' => $max,
            'median' => $median
        ];
    }

    private function httpRequest($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'YFEvents-Performance-Tester/1.0');
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $start = microtime(true);
        $response = curl_exec($ch);
        $end = microtime(true);
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        
        curl_close($ch);
        
        return [
            'http_code' => $http_code,
            'headers' => $headers,
            'body' => $body,
            'success' => $response !== false,
            'response_time' => ($end - $start) * 1000, // milliseconds
            'curl_time' => $total_time * 1000
        ];
    }

    public function runTests() {
        echo "⚡ Performance Test Suite\n";
        echo "========================\n\n";

        // Database Performance Tests
        echo "🗃️ Database Performance\n";
        echo "----------------------\n";
        $this->testDatabasePerformance();

        // API Response Time Tests
        echo "\n🌐 API Performance\n";
        echo "-----------------\n";
        $this->testAPIPerformance();

        // Web Interface Performance
        echo "\n🖥️ Web Interface Performance\n";
        echo "----------------------------\n";
        $this->testWebPerformance();

        // Load Testing
        echo "\n📈 Load Testing\n";
        echo "---------------\n";
        $this->testLoadPerformance();

        // Memory Usage Tests
        echo "\n🧠 Memory Usage\n";
        echo "---------------\n";
        $this->testMemoryUsage();

        // Summary
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "⚡ PERFORMANCE TEST SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📊 Total:  {$this->total}\n\n";

        $success_rate = $this->total > 0 ? round(($this->passed / $this->total) * 100, 1) : 0;
        echo "🎯 Success Rate: {$success_rate}%\n";

        if ($success_rate >= 90) {
            echo "🎉 EXCELLENT! System performs very well under load.\n";
        } elseif ($success_rate >= 75) {
            echo "👍 GOOD! System performance is acceptable.\n";
        } elseif ($success_rate >= 50) {
            echo "⚠️ WARNING! Performance issues detected.\n";
        } else {
            echo "🚨 CRITICAL! Severe performance problems.\n";
        }

        // Performance Benchmarks Summary
        echo "\n📋 Performance Benchmarks:\n";
        foreach ($this->results as $name => $stats) {
            echo "  $name: " . round($stats['avg'], 2) . "ms avg (min: " . round($stats['min'], 2) . "ms, max: " . round($stats['max'], 2) . "ms)\n";
        }

        echo "\n💡 Performance Recommendations:\n";
        echo "1. Database queries should complete under 100ms\n";
        echo "2. API responses should be under 200ms\n";
        echo "3. Web pages should load under 500ms\n";
        echo "4. Memory usage should stay under 128MB per request\n";

        return $success_rate >= 75;
    }

    private function testDatabasePerformance() {
        try {
            // Test simple query performance
            $simple_benchmark = $this->benchmark(function() {
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM events");
                $stmt->execute();
                return $stmt->fetch();
            }, 20, 'Simple COUNT query');
            
            $this->test('Simple queries perform well', 
                $simple_benchmark['avg'] < 50, 
                "Average: " . round($simple_benchmark['avg'], 2) . "ms",
                "Avg: " . round($simple_benchmark['avg'], 2) . "ms, Max: " . round($simple_benchmark['max'], 2) . "ms"
            );

            // Test complex query performance
            $complex_benchmark = $this->benchmark(function() {
                $stmt = $this->db->prepare("
                    SELECT e.* 
                    FROM events e 
                    WHERE e.status = 'approved' 
                    AND e.start_datetime > NOW() 
                    ORDER BY e.start_datetime 
                    LIMIT 50
                ");
                $stmt->execute();
                return $stmt->fetchAll();
            }, 10, 'Complex query with filtering');

            $this->test('Complex queries perform adequately', 
                $complex_benchmark['avg'] < 100, 
                "Average: " . round($complex_benchmark['avg'], 2) . "ms",
                "Avg: " . round($complex_benchmark['avg'], 2) . "ms, Max: " . round($complex_benchmark['max'], 2) . "ms"
            );

            // Test transaction performance
            $transaction_benchmark = $this->benchmark(function() {
                $this->db->beginTransaction();
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM events WHERE status = ?");
                $stmt->execute(['approved']);
                $stmt->execute(['pending']);
                $stmt->execute(['rejected']);
                $this->db->commit();
                return true;
            }, 10, 'Transaction processing');

            $this->test('Transaction processing is efficient', 
                $transaction_benchmark['avg'] < 150,
                "Average: " . round($transaction_benchmark['avg'], 2) . "ms",
                "Avg: " . round($transaction_benchmark['avg'], 2) . "ms"
            );

        } catch (Exception $e) {
            $this->test('Database performance testing', false, $e->getMessage());
        }
    }

    private function testAPIPerformance() {
        // Test Events API performance
        $events_benchmark = $this->benchmark(function() {
            return $this->httpRequest($this->base_url . '/api/events-simple.php');
        }, 10, 'Events API');

        $this->test('Events API responds quickly', 
            $events_benchmark['avg'] < 200, 
            "Average: " . round($events_benchmark['avg'], 2) . "ms",
            "Avg: " . round($events_benchmark['avg'], 2) . "ms, Max: " . round($events_benchmark['max'], 2) . "ms"
        );

        // Test Shops API performance
        $shops_benchmark = $this->benchmark(function() {
            return $this->httpRequest($this->base_url . '/api/shops/index.php');
        }, 10, 'Shops API');

        $this->test('Shops API responds quickly', 
            $shops_benchmark['avg'] < 200, 
            "Average: " . round($shops_benchmark['avg'], 2) . "ms",
            "Avg: " . round($shops_benchmark['avg'], 2) . "ms, Max: " . round($shops_benchmark['max'], 2) . "ms"
        );

        // Test API with parameters
        $params_benchmark = $this->benchmark(function() {
            return $this->httpRequest($this->base_url . '/api/events-simple.php?start=' . date('Y-m-01') . '&end=' . date('Y-m-t'));
        }, 5, 'Events API with params');

        $this->test('API with parameters performs well', 
            $params_benchmark['avg'] < 300,
            "Average: " . round($params_benchmark['avg'], 2) . "ms",
            "Avg: " . round($params_benchmark['avg'], 2) . "ms"
        );
    }

    private function testWebPerformance() {
        // Test main calendar page
        $calendar_benchmark = $this->benchmark(function() {
            return $this->httpRequest($this->base_url . '/calendar.php');
        }, 5, 'Main calendar page');

        $this->test('Main calendar loads quickly', 
            $calendar_benchmark['avg'] < 500,
            "Average: " . round($calendar_benchmark['avg'], 2) . "ms",
            "Avg: " . round($calendar_benchmark['avg'], 2) . "ms"
        );

        // Test admin interface
        $admin_benchmark = $this->benchmark(function() {
            return $this->httpRequest($this->base_url . '/admin/calendar/');
        }, 5, 'Admin interface');

        $this->test('Admin interface loads reasonably', 
            $admin_benchmark['avg'] < 800,
            "Average: " . round($admin_benchmark['avg'], 2) . "ms",
            "Avg: " . round($admin_benchmark['avg'], 2) . "ms"
        );
    }

    private function testLoadPerformance() {
        // Simulate concurrent requests
        $concurrent_results = [];
        $urls = [
            '/api/events-simple.php',
            '/api/shops/index.php',
            '/calendar.php',
            '/admin/calendar/'
        ];

        foreach ($urls as $url) {
            $start = microtime(true);
            $responses = [];
            
            // Simulate 5 concurrent requests (simplified)
            for ($i = 0; $i < 5; $i++) {
                $response = $this->httpRequest($this->base_url . $url);
                $responses[] = $response;
            }
            
            $end = microtime(true);
            $total_time = ($end - $start) * 1000;
            
            $success_count = array_reduce($responses, function($carry, $response) {
                return $carry + ($response['success'] && $response['http_code'] === 200 ? 1 : 0);
            }, 0);
            
            $this->test("Load test for $url", 
                $success_count >= 4 && $total_time < 3000,
                "Success: $success_count/5, Time: " . round($total_time, 2) . "ms",
                "Time: " . round($total_time, 2) . "ms, Success: $success_count/5"
            );
        }
    }

    private function testMemoryUsage() {
        $initial_memory = memory_get_usage(true);
        
        // Test memory usage during database operations
        $start_memory = memory_get_usage(true);
        
        try {
            // Perform memory-intensive operations
            $stmt = $this->db->prepare("SELECT * FROM events LIMIT 1000");
            $stmt->execute();
            $events = $stmt->fetchAll();
            
            $stmt = $this->db->prepare("SELECT * FROM local_shops LIMIT 500");
            $stmt->execute();
            $shops = $stmt->fetchAll();
            
            $peak_memory = memory_get_peak_usage(true);
            $current_memory = memory_get_usage(true);
            $memory_used = $current_memory - $start_memory;
            
            $this->test('Memory usage is reasonable', 
                $memory_used < 64 * 1024 * 1024, // 64MB
                "Used: " . round($memory_used / 1024 / 1024, 2) . "MB",
                "Used: " . round($memory_used / 1024 / 1024, 2) . "MB, Peak: " . round($peak_memory / 1024 / 1024, 2) . "MB"
            );
            
            // Clean up
            unset($events, $shops);
            
        } catch (Exception $e) {
            $this->test('Memory usage testing', false, $e->getMessage());
        }

        // Test for memory leaks
        $final_memory = memory_get_usage(true);
        $leaked_memory = $final_memory - $initial_memory;
        
        $this->test('No significant memory leaks', 
            $leaked_memory < 16 * 1024 * 1024, // 16MB
            "Leaked: " . round($leaked_memory / 1024 / 1024, 2) . "MB",
            "Leaked: " . round($leaked_memory / 1024 / 1024, 2) . "MB"
        );
    }
}

// Run the tests
try {
    $tester = new PerformanceTester($db);
    $success = $tester->runTests();
    
    // Return appropriate exit code
    exit($success ? 0 : 1);
    
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: Failed to run performance tests\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}