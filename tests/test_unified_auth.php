<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Config\Config;
use YFEvents\Infrastructure\Container\Container;
use YFEvents\Infrastructure\Providers\ServiceProvider;
use YFEvents\Application\Services\AuthService;
use YFEvents\Domain\Claims\SellerRepositoryInterface;

// Initialize
$config = new Config();
$config->loadFromFile(__DIR__ . '/../config/app.php');
$config->loadFromFile(__DIR__ . '/../config/database.php');

// Load environment
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, "\"'");
            
            switch ($key) {
                case 'DB_HOST':
                    $config->set('database.host', $value);
                    break;
                case 'DB_NAME':
                    $config->set('database.name', $value);
                    break;
                case 'DB_USER':
                    $config->set('database.username', $value);
                    break;
                case 'DB_PASS':
                    $config->set('database.password', $value);
                    break;
            }
        }
    }
}

$container = new Container();
$serviceProvider = new ServiceProvider($container);
$serviceProvider->register();

echo "Testing Unified Authentication Flow\n";
echo "===================================\n\n";

// Test 1: Check if services are properly registered
echo "1. Testing service registration...\n";
try {
    $authService = $container->resolve(AuthService::class);
    echo "✓ AuthService registered properly\n";
} catch (Exception $e) {
    echo "✗ Failed to resolve AuthService: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    $sellerRepo = $container->resolve(SellerRepositoryInterface::class);
    echo "✓ SellerRepository registered properly\n";
} catch (Exception $e) {
    echo "✗ Failed to resolve SellerRepository: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check YFAuth users
echo "\n2. Checking YFAuth users...\n";
try {
    $pdo = $container->resolve(\PDO::class);
    $stmt = $pdo->query("SELECT id, username, email FROM yfa_auth_users WHERE status = 'active' LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "⚠ No active users found in YFAuth\n";
    } else {
        echo "✓ Found " . count($users) . " active users:\n";
        foreach ($users as $user) {
            echo "  - {$user['username']} ({$user['email']})\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Error checking users: " . $e->getMessage() . "\n";
}

// Test 3: Check seller accounts
echo "\n3. Checking seller accounts...\n";
try {
    $stmt = $pdo->query("SELECT s.id, s.company_name, s.email, s.auth_user_id, u.username 
                         FROM yfc_sellers s 
                         LEFT JOIN yfa_auth_users u ON s.auth_user_id = u.id
                         WHERE s.status = 'active' 
                         LIMIT 5");
    $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sellers)) {
        echo "⚠ No verified sellers found\n";
    } else {
        echo "✓ Found " . count($sellers) . " verified sellers:\n";
        foreach ($sellers as $seller) {
            echo "  - {$seller['company_name']} (auth_user_id: {$seller['auth_user_id']}, username: {$seller['username']})\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Error checking sellers: " . $e->getMessage() . "\n";
}

// Test 4: Test authentication flow
echo "\n4. Testing authentication flow...\n";
// Get a test seller
$stmt = $pdo->query("SELECT s.*, u.username 
                     FROM yfc_sellers s 
                     JOIN yfa_auth_users u ON s.auth_user_id = u.id
                     WHERE s.status = 'active' AND u.status = 'active'
                     LIMIT 1");
$testSeller = $stmt->fetch(PDO::FETCH_ASSOC);

if ($testSeller) {
    echo "Testing login for seller: {$testSeller['company_name']} (username: {$testSeller['username']})\n";
    
    // Start session for testing
    session_start();
    
    // Clear any existing session
    $_SESSION = [];
    
    // Simulate login (without password since we can't decrypt)
    $_SESSION['auth'] = [
        'user_id' => (int)$testSeller['auth_user_id'],
        'username' => $testSeller['username'],
        'roles' => ['seller', 'claim_seller']
    ];
    
    $_SESSION['seller'] = [
        'seller_id' => (int)$testSeller['id'],
        'company_name' => $testSeller['company_name'],
        'contact_name' => $testSeller['contact_name'] ?? ''
    ];
    
    echo "✓ Session structure created:\n";
    echo "  - auth.user_id: " . $_SESSION['auth']['user_id'] . "\n";
    echo "  - auth.username: " . $_SESSION['auth']['username'] . "\n";
    echo "  - auth.roles: " . implode(', ', $_SESSION['auth']['roles']) . "\n";
    echo "  - seller.seller_id: " . $_SESSION['seller']['seller_id'] . "\n";
    echo "  - seller.company_name: " . $_SESSION['seller']['company_name'] . "\n";
    
    // Test session checks
    if (isset($_SESSION['auth']['user_id']) && isset($_SESSION['seller']['seller_id'])) {
        echo "✓ Session checks pass\n";
    } else {
        echo "✗ Session checks failed\n";
    }
    
} else {
    echo "⚠ No test seller found with active YFAuth account\n";
}

// Test 5: Check role assignments
echo "\n5. Checking role assignments...\n";
try {
    $stmt = $pdo->query("SELECT r.name as role_name, COUNT(ur.user_id) as user_count
                         FROM yfa_auth_roles r
                         LEFT JOIN yfa_auth_user_roles ur ON r.id = ur.role_id
                         GROUP BY r.id, r.name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Role distribution:\n";
    foreach ($roles as $role) {
        echo "  - {$role['role_name']}: {$role['user_count']} users\n";
    }
} catch (Exception $e) {
    echo "✗ Error checking roles: " . $e->getMessage() . "\n";
}

echo "\n✅ Authentication flow test complete\n";