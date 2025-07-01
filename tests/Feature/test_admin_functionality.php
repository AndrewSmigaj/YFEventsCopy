<?php
// Comprehensive Admin Functionality Test
session_start();

// Set up test authentication
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_user'] = [
    'id' => 1,
    'username' => 'test_admin',
    'email' => 'admin@test.com'
];

// Color codes for output
$GREEN = "\033[0;32m";
$RED = "\033[0;31m";
$YELLOW = "\033[0;33m";
$BLUE = "\033[0;34m";
$NC = "\033[0m"; // No Color

echo "\n{$BLUE}=== Testing Admin API Endpoints ==={$NC}\n\n";

// Base URL for tests
$baseUrl = 'http://localhost/refactor';

// Test endpoints
$apiTests = [
    // Event endpoints
    [
        'name' => 'Event Statistics',
        'method' => 'GET',
        'url' => '/admin/events/statistics',
        'description' => 'Get event statistics'
    ],
    [
        'name' => 'Get All Events',
        'method' => 'GET',
        'url' => '/admin/events',
        'description' => 'Get paginated events list'
    ],
    [
        'name' => 'Create Event',
        'method' => 'POST',
        'url' => '/admin/events/create',
        'data' => [
            'title' => 'Test Event ' . time(),
            'description' => 'Test event description',
            'start_datetime' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'location' => 'Test Location',
            'status' => 'pending'
        ],
        'description' => 'Create a new event'
    ],
    
    // Shop endpoints
    [
        'name' => 'Shop Statistics',
        'method' => 'GET',
        'url' => '/admin/shops/statistics',
        'description' => 'Get shop statistics'
    ],
    [
        'name' => 'Get All Shops',
        'method' => 'GET',
        'url' => '/admin/shops',
        'description' => 'Get paginated shops list'
    ],
    
    // Scraper endpoints
    [
        'name' => 'Scraper Statistics',
        'method' => 'GET',
        'url' => '/api/scrapers/statistics',
        'description' => 'Get scraper statistics'
    ],
    [
        'name' => 'Get Scraper Sources',
        'method' => 'GET',
        'url' => '/api/scrapers',
        'description' => 'Get all scraper sources'
    ],
    
    // Dashboard endpoints
    [
        'name' => 'Dashboard Data',
        'method' => 'GET',
        'url' => '/admin/dashboard/data',
        'description' => 'Get dashboard statistics'
    ],
    [
        'name' => 'System Health',
        'method' => 'GET',
        'url' => '/admin/dashboard/health',
        'description' => 'Check system health'
    ],
    [
        'name' => 'Recent Activity',
        'method' => 'GET',
        'url' => '/admin/dashboard/activity',
        'description' => 'Get recent activity'
    ],
    
    // Auth check
    [
        'name' => 'Admin Status',
        'method' => 'GET',
        'url' => '/admin/status',
        'description' => 'Check admin authentication status'
    ]
];

$results = [];

foreach ($apiTests as $test) {
    echo "{$YELLOW}Testing: {$test['name']}{$NC}\n";
    echo "  URL: {$test['url']}\n";
    echo "  Method: {$test['method']}\n";
    echo "  Description: {$test['description']}\n";
    
    $result = [
        'name' => $test['name'],
        'url' => $test['url'],
        'method' => $test['method'],
        'success' => false,
        'response_code' => null,
        'response_data' => null,
        'error' => null
    ];
    
    try {
        // Initialize cURL
        $ch = curl_init();
        
        // Set common options
        curl_setopt($ch, CURLOPT_URL, $baseUrl . $test['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // Set method-specific options
        if ($test['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (isset($test['data'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($test['data']));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            }
        }
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        // Separate headers and body
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        curl_close($ch);
        
        $result['response_code'] = $httpCode;
        
        // Parse JSON response if possible
        $jsonData = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result['response_data'] = $jsonData;
        } else {
            // Check if it's HTML (redirect to login)
            if (strpos($body, '<html') !== false || strpos($body, '<!DOCTYPE') !== false) {
                $result['response_data'] = 'HTML response (possible redirect)';
            } else {
                $result['response_data'] = substr($body, 0, 200) . '...';
            }
        }
        
        // Determine success
        if ($httpCode >= 200 && $httpCode < 300) {
            if (is_array($result['response_data']) && isset($result['response_data']['success'])) {
                $result['success'] = $result['response_data']['success'];
            } else {
                $result['success'] = true;
            }
        }
        
        // Display result
        if ($result['success']) {
            echo "  {$GREEN}✓{$NC} Response: {$httpCode}\n";
            if (is_array($result['response_data'])) {
                if (isset($result['response_data']['message'])) {
                    echo "  {$BLUE}ℹ{$NC} Message: {$result['response_data']['message']}\n";
                }
                if (isset($result['response_data']['data'])) {
                    $dataKeys = array_keys($result['response_data']['data']);
                    echo "  {$BLUE}ℹ{$NC} Data keys: " . implode(', ', array_slice($dataKeys, 0, 5)) . "\n";
                }
            }
        } else {
            echo "  {$RED}✗{$NC} Response: {$httpCode}\n";
            if (is_array($result['response_data']) && isset($result['response_data']['message'])) {
                echo "  {$RED}✗{$NC} Error: {$result['response_data']['message']}\n";
            } elseif (is_string($result['response_data'])) {
                echo "  {$RED}✗{$NC} Response: {$result['response_data']}\n";
            }
        }
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        echo "  {$RED}✗{$NC} Exception: {$e->getMessage()}\n";
    }
    
    $results[] = $result;
    echo "\n";
}

// Summary
echo "{$BLUE}=== API Test Summary ==={$NC}\n\n";

$totalTests = count($results);
$passedTests = count(array_filter($results, function($r) { return $r['success']; }));

foreach ($results as $result) {
    $status = $result['success'] ? "{$GREEN}PASS{$NC}" : "{$RED}FAIL{$NC}";
    $code = $result['response_code'] ? "({$result['response_code']})" : "(No response)";
    echo sprintf("%-40s %s %s\n", $result['name'], $status, $code);
}

echo "\n";
echo "Total: {$passedTests}/{$totalTests} tests passed ";
$percentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
echo "({$percentage}%)\n";

// Test UI functionality
echo "\n{$BLUE}=== Testing UI Functionality ==={$NC}\n\n";

$uiTests = [
    'Navigation' => [
        'admin_dashboard' => 'Links to all admin sections',
        'breadcrumbs' => 'Shows current location',
        'logout' => 'Logout functionality'
    ],
    'Event Management' => [
        'event_list' => 'Display paginated events',
        'event_create' => 'Modal for creating events',
        'event_edit' => 'Modal for editing events',
        'event_delete' => 'Delete confirmation',
        'event_approve' => 'Approve pending events',
        'bulk_actions' => 'Bulk approve/reject'
    ],
    'Shop Management' => [
        'shop_list' => 'Display paginated shops',
        'shop_create' => 'Modal for creating shops',
        'shop_edit' => 'Modal for editing shops',
        'shop_verify' => 'Verify shop information',
        'shop_feature' => 'Feature/unfeature shops'
    ],
    'Scraper Management' => [
        'source_list' => 'List all scraper sources',
        'run_scraper' => 'Run individual scrapers',
        'test_scraper' => 'Test scraper functionality',
        'scraper_logs' => 'View scraper logs'
    ],
    'User Management' => [
        'user_list' => 'Display all users',
        'user_create' => 'Create new users',
        'user_edit' => 'Edit user details',
        'user_roles' => 'Manage user roles'
    ]
];

echo "UI Components to verify:\n\n";
foreach ($uiTests as $section => $features) {
    echo "{$YELLOW}{$section}:{$NC}\n";
    foreach ($features as $feature => $description) {
        echo "  • {$description}\n";
    }
    echo "\n";
}

// Save detailed results
$fullResults = [
    'test_date' => date('Y-m-d H:i:s'),
    'api_tests' => $results,
    'ui_components' => $uiTests,
    'summary' => [
        'total_api_tests' => $totalTests,
        'passed_api_tests' => $passedTests,
        'success_rate' => $percentage . '%'
    ]
];

file_put_contents('admin_functionality_test_results.json', json_encode($fullResults, JSON_PRETTY_PRINT));
echo "Detailed results saved to admin_functionality_test_results.json\n";