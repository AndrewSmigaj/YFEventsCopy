<?php
/**
 * Test complete user flows with authentication
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

class UserFlowTester
{
    private string $baseUrl = 'http://localhost';
    private string $cookieFile;
    private $ch;
    
    public function __construct()
    {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'cookies');
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookieFile);
    }
    
    public function __destruct()
    {
        curl_close($this->ch);
        unlink($this->cookieFile);
    }
    
    private function request(string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl . $path;
        
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($method === 'POST' && !empty($data)) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }
        
        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        return [
            'code' => $httpCode,
            'header' => $header,
            'body' => $body,
            'url' => $url
        ];
    }
    
    private function extractValue(string $html, string $pattern): ?string
    {
        if (preg_match($pattern, $html, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    public function testSellerFlow(): void
    {
        echo "\n=== Testing Seller Flow ===\n\n";
        
        // 1. Visit seller login page
        echo "1. Visiting seller login page...\n";
        $response = $this->request('GET', '/seller/login');
        echo "   Status: {$response['code']}\n";
        
        // Extract CSRF token if present
        $csrfToken = $this->extractValue($response['body'], '/<input[^>]*name="csrf_token"[^>]*value="([^"]+)"/');
        
        // 2. Try to register a new seller
        echo "\n2. Registering new seller...\n";
        $sellerData = [
            'email' => 'test_seller_flow_' . time() . '@example.com',
            'password' => 'TestSeller123!',
            'company_name' => 'Flow Test Estate Sales',
            'contact_name' => 'Flow Tester',
            'phone' => '555-FLOW-001'
        ];
        
        if ($csrfToken) {
            $sellerData['csrf_token'] = $csrfToken;
        }
        
        $response = $this->request('POST', '/seller/register', $sellerData);
        echo "   Status: {$response['code']}\n";
        
        // Check for redirect or success message
        if (preg_match('/Location: (.+)/', $response['header'], $matches)) {
            echo "   Redirected to: " . trim($matches[1]) . "\n";
        }
        
        // 3. Try to access seller dashboard
        echo "\n3. Accessing seller dashboard...\n";
        $response = $this->request('GET', '/seller/dashboard');
        echo "   Status: {$response['code']}\n";
        
        // Check if we're logged in
        if (strpos($response['body'], 'Seller Dashboard') !== false) {
            echo "   ✓ Successfully accessed dashboard\n";
        } else if (strpos($response['body'], 'login') !== false) {
            echo "   ✗ Redirected to login\n";
        }
        
        // 4. Try to create a sale
        echo "\n4. Creating a new sale...\n";
        $response = $this->request('GET', '/seller/sale/new');
        echo "   Status: {$response['code']}\n";
        
        // 5. Check session info
        echo "\n5. Checking session state...\n";
        if (strpos($response['body'], 'no seller id in session') !== false) {
            echo "   ✗ Error: No seller ID in session\n";
        }
    }
    
    public function testAdminFlow(): void
    {
        echo "\n\n=== Testing Admin Flow ===\n\n";
        
        // 1. Visit admin login
        echo "1. Visiting admin login page...\n";
        $response = $this->request('GET', '/admin/login');
        echo "   Status: {$response['code']}\n";
        
        // 2. Try to login (will fail with fake credentials)
        echo "\n2. Attempting admin login...\n";
        $response = $this->request('POST', '/admin/login', [
            'username' => 'admin_test_' . time() . '@example.com',
            'password' => 'TestAdmin123!'
        ]);
        echo "   Status: {$response['code']}\n";
        
        // 3. Check admin status
        echo "\n3. Checking admin status...\n";
        $response = $this->request('GET', '/admin/status');
        echo "   Status: {$response['code']}\n";
        
        // Parse JSON response
        $json = json_decode($response['body'], true);
        if ($json) {
            echo "   Authenticated: " . ($json['authenticated'] ?? 'false') . "\n";
        }
    }
    
    public function testPublicAPIs(): void
    {
        echo "\n\n=== Testing Public APIs ===\n\n";
        
        // Test various API endpoints
        $apis = [
            '/api/health' => 'Health check',
            '/api/events' => 'Events list',
            '/api/shops' => 'Shops list',
            '/api/claims/items' => 'Claims items',
            '/api/events/calendar' => 'Events calendar',
            '/api/shops/map' => 'Shops map data'
        ];
        
        foreach ($apis as $path => $description) {
            echo "$description ($path):\n";
            $response = $this->request('GET', $path);
            echo "   Status: {$response['code']}\n";
            
            // Try to parse JSON
            $json = json_decode($response['body'], true);
            if ($json !== null) {
                if (isset($json['error'])) {
                    echo "   Error: " . $json['message'] . "\n";
                } else if (isset($json['data']) && is_array($json['data'])) {
                    echo "   Success: " . count($json['data']) . " items\n";
                } else {
                    echo "   Success\n";
                }
            }
        }
    }
    
    public function run(): void
    {
        echo "YFEvents User Flow Testing\n";
        echo "==========================\n";
        
        $this->testSellerFlow();
        $this->testAdminFlow();
        $this->testPublicAPIs();
        
        echo "\n\nTesting complete!\n";
    }
}

// Run the tests
$tester = new UserFlowTester();
$tester->run();