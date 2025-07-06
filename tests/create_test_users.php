<?php
/**
 * Create test users for comprehensive testing
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Application\Bootstrap;
use YFEvents\Infrastructure\Database\ConnectionInterface;

// Bootstrap application
$container = Bootstrap::boot();
$connection = $container->resolve(ConnectionInterface::class);
$pdo = $connection->getConnection();

echo "Creating test users...\n\n";

try {
    // Create test admin user
    $adminEmail = 'test_admin_' . time() . '@example.com';
    $adminPassword = password_hash('TestAdmin123!', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO yfa_auth_users (username, email, password_hash, created_at, updated_at, status)
        VALUES (:username, :email, :password_hash, NOW(), NOW(), 'active')
    ");
    $stmt->execute([
        'username' => 'test_admin_' . time(),
        'email' => $adminEmail,
        'password_hash' => $adminPassword
    ]);
    $adminUserId = $pdo->lastInsertId();
    
    // Assign admin role
    $stmt = $pdo->prepare("
        INSERT INTO yfa_auth_user_roles (user_id, role_id, assigned_at)
        SELECT :user_id, id, NOW() FROM yfa_auth_roles WHERE name = 'admin'
    ");
    $stmt->execute(['user_id' => $adminUserId]);
    
    echo "✓ Created admin user: $adminEmail (password: TestAdmin123!)\n";
    
    // Create test seller user
    $sellerEmail = 'test_seller_' . time() . '@example.com';
    $sellerPassword = password_hash('TestSeller123!', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO yfa_auth_users (username, email, password_hash, created_at, updated_at, status)
        VALUES (:username, :email, :password_hash, NOW(), NOW(), 'active')
    ");
    $stmt->execute([
        'username' => 'test_seller_' . time(),
        'email' => $sellerEmail,
        'password_hash' => $sellerPassword
    ]);
    $sellerUserId = $pdo->lastInsertId();
    
    // Assign seller role
    $stmt = $pdo->prepare("
        INSERT INTO yfa_auth_user_roles (user_id, role_id, assigned_at)
        SELECT :user_id, id, NOW() FROM yfa_auth_roles WHERE name = 'seller'
    ");
    $stmt->execute(['user_id' => $sellerUserId]);
    
    // Create YFClaim seller record
    $stmt = $pdo->prepare("
        INSERT INTO yfc_sellers (company_name, contact_name, email, phone, password_hash, address, city, state, zip, status, created_at)
        VALUES (:company_name, :contact_name, :email, :phone, :password_hash, :address, :city, :state, :zip, 'active', NOW())
    ");
    $stmt->execute([
        'company_name' => 'Test Estate Sales Company',
        'contact_name' => 'Test Seller',
        'email' => $sellerEmail,
        'phone' => '555-TEST-001',
        'password_hash' => $sellerPassword,
        'address' => '123 Test Street',
        'city' => 'Yakima',
        'state' => 'WA',
        'zip' => '98901'
    ]);
    $sellerId = $pdo->lastInsertId();
    
    echo "✓ Created seller user: $sellerEmail (password: TestSeller123!)\n";
    echo "  Seller ID: $sellerId\n";
    
    // Create test buyer user (for YFClaim)
    $buyerEmail = 'test_buyer_' . time() . '@example.com';
    
    $stmt = $pdo->prepare("
        INSERT INTO yfa_auth_users (username, email, password_hash, created_at, updated_at, status)
        VALUES (:username, :email, NULL, NOW(), NOW(), 'active')
    ");
    $stmt->execute([
        'username' => 'test_buyer_' . time(),
        'email' => $buyerEmail
    ]);
    $buyerUserId = $pdo->lastInsertId();
    
    // Assign buyer role
    $stmt = $pdo->prepare("
        INSERT INTO yfa_auth_user_roles (user_id, role_id, assigned_at)
        SELECT :user_id, id, NOW() FROM yfa_auth_roles WHERE name = 'buyer'
    ");
    $stmt->execute(['user_id' => $buyerUserId]);
    
    echo "✓ Created buyer user: $buyerEmail (uses email auth)\n";
    
    // Create test sale for the seller
    $stmt = $pdo->prepare("
        INSERT INTO yfc_sales (
            seller_id, title, description, address, city, state, zip,
            preview_start, preview_end, claim_start, claim_end,
            pickup_start, pickup_end, status, created_at
        ) VALUES (
            :seller_id, :title, :description, :address, :city, :state, :zip,
            NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY),
            DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 3 DAY),
            DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 4 DAY),
            'active', NOW()
        )
    ");
    $stmt->execute([
        'seller_id' => $sellerId,
        'title' => 'Test Estate Sale',
        'description' => 'This is a test estate sale for testing purposes',
        'address' => '456 Test Avenue',
        'city' => 'Yakima',
        'state' => 'WA',
        'zip' => '98902'
    ]);
    $saleId = $pdo->lastInsertId();
    
    echo "✓ Created test sale ID: $saleId\n";
    
    // Create test items for the sale
    $items = [
        ['title' => 'Antique Desk', 'description' => 'Beautiful mahogany desk', 'price' => 250.00, 'category' => 'Furniture'],
        ['title' => 'Vintage Lamp', 'description' => 'Tiffany style lamp', 'price' => 150.00, 'category' => 'Home Decor'],
        ['title' => 'China Set', 'description' => '12 piece china set', 'price' => 300.00, 'category' => 'Kitchenware']
    ];
    
    foreach ($items as $item) {
        $stmt = $pdo->prepare("
            INSERT INTO yfc_items (sale_id, title, description, price, category, status, created_at)
            VALUES (:sale_id, :title, :description, :price, :category, 'available', NOW())
        ");
        $stmt->execute([
            'sale_id' => $saleId,
            'title' => $item['title'],
            'description' => $item['description'],
            'price' => $item['price'],
            'category' => $item['category']
        ]);
    }
    
    echo "✓ Created " . count($items) . " test items\n";
    
    // Save credentials to file
    $credentials = [
        'admin' => [
            'email' => $adminEmail,
            'password' => 'TestAdmin123!',
            'user_id' => $adminUserId
        ],
        'seller' => [
            'email' => $sellerEmail,
            'password' => 'TestSeller123!',
            'user_id' => $sellerUserId,
            'seller_id' => $sellerId,
            'sale_id' => $saleId
        ],
        'buyer' => [
            'email' => $buyerEmail,
            'password' => '(uses email auth)',
            'user_id' => $buyerUserId
        ]
    ];
    
    $credFile = __DIR__ . '/test_credentials_' . date('Y-m-d_His') . '.json';
    file_put_contents($credFile, json_encode($credentials, JSON_PRETTY_PRINT));
    
    echo "\n✓ Credentials saved to: $credFile\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTest users created successfully!\n";