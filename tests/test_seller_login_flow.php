<?php

declare(strict_types=1);

// Test the actual seller login flow through the web interface

// Get the first seller with YFAuth account
require_once __DIR__ . '/../vendor/autoload.php';

// Load config properly
$config = [
    'host' => '127.0.0.1',
    'name' => 'yakima_finds',
    'username' => 'yfevents',
    'password' => 'yfevents_pass'
];

// Override with env if available
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, "\"'");
            
            switch ($key) {
                case 'DB_HOST': $config['host'] = $value; break;
                case 'DB_DATABASE': $config['name'] = $value; break;
                case 'DB_USERNAME': $config['username'] = $value; break;
                case 'DB_PASSWORD': $config['password'] = $value; break;
            }
        }
    }
}

$dsn = "mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4";
$pdo = new PDO($dsn, $config['username'], $config['password']);

echo "Testing Seller Login Flow\n";
echo "========================\n\n";

// Get a test seller
$stmt = $pdo->query("SELECT s.*, u.username 
                     FROM yfc_sellers s 
                     JOIN yfa_auth_users u ON s.auth_user_id = u.id
                     WHERE s.status = 'active' AND u.status = 'active'
                     LIMIT 1");
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    echo "✗ No active seller found\n";
    exit(1);
}

echo "Test seller:\n";
echo "- Company: {$seller['company_name']}\n";
echo "- Email: {$seller['email']}\n";
echo "- Username: {$seller['username']}\n";
echo "- Auth User ID: {$seller['auth_user_id']}\n\n";

// Test 1: Access seller login page
echo "1. Testing access to seller login page...\n";
$ch = curl_init('http://localhost/seller/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✓ Login page accessible (HTTP 200)\n";
    if (strpos($response, '<form') !== false && strpos($response, 'username') !== false) {
        echo "✓ Login form present\n";
    } else {
        echo "✗ Login form not found\n";
    }
} else {
    echo "✗ Login page returned HTTP $httpCode\n";
    if ($httpCode >= 300 && $httpCode < 400) {
        echo "  (Redirect detected - may indicate auth loop)\n";
    }
}

// Test 2: Check for YFAuth integration
echo "\n2. Testing YFAuth integration...\n";
if (strpos($response, 'YFAuth') !== false || strpos($response, 'Unified Authentication') !== false) {
    echo "✓ YFAuth branding detected\n";
} else {
    echo "⚠ YFAuth branding not found (may be OK)\n";
}

// Test 3: Test seller dashboard redirect when not logged in
echo "\n3. Testing dashboard access without login...\n";
$ch = curl_init('http://localhost/seller/dashboard');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headers = curl_getinfo($ch);
curl_close($ch);

if ($httpCode === 302 || $httpCode === 303) {
    echo "✓ Dashboard redirects when not logged in (HTTP $httpCode)\n";
    if (preg_match('/Location: .*\/seller\/login/i', $response)) {
        echo "✓ Redirects to login page\n";
    } else {
        echo "⚠ Redirects to unexpected location\n";
    }
} else if ($httpCode === 200) {
    echo "✗ Dashboard accessible without login (security issue)\n";
} else {
    echo "⚠ Unexpected response code: $httpCode\n";
}

// Test 4: Check iframe endpoint
echo "\n4. Testing communication iframe endpoint...\n";
$ch = curl_init('http://localhost/communication/embedded?seller_id=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✓ Communication endpoint accessible (HTTP 200)\n";
    if (strpos($response, 'Please log in') !== false || strpos($response, 'Authentication required') !== false) {
        echo "✓ Shows authentication message when not logged in\n";
    } else if (strpos($response, 'communication-hub') !== false) {
        echo "⚠ Shows communication hub without auth (potential issue)\n";
    }
} else {
    echo "✗ Communication endpoint returned HTTP $httpCode\n";
}

echo "\n✅ Login flow test complete\n";
echo "\nNOTE: To test actual login, use username '{$seller['username']}' with the password.\n";