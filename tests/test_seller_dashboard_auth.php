<?php
// Test seller dashboard with authentication
require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Application\Bootstrap;

// Bootstrap application
$container = Bootstrap::boot();

// Simulate a database query to get a seller
$config = $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class);
$dbConfig = $config->get('database');
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Get first seller from database
$stmt = $pdo->query("SELECT id, email, company_name FROM yfc_sellers LIMIT 1");
$seller = $stmt->fetch();

if ($seller) {
    echo "Testing with seller: {$seller['company_name']} (ID: {$seller['id']})\n\n";
    
    // Test SellerRepository
    try {
        $sellerRepo = $container->resolve(\YFEvents\Domain\Claims\SellerRepositoryInterface::class);
        $sellerEntity = $sellerRepo->findById($seller['id']);
        
        if ($sellerEntity) {
            echo "✓ SellerRepository::findById works\n";
            echo "  - Company: " . $sellerEntity->getCompanyName() . "\n";
            echo "  - Email: " . $sellerEntity->getEmail() . "\n";
            echo "  - User ID: " . ($sellerEntity->getUserId() ?? 'null') . "\n";
        } else {
            echo "✗ Failed to load seller entity\n";
        }
        
        // Test findByEmail
        $sellerByEmail = $sellerRepo->findByEmail($seller['email']);
        if ($sellerByEmail) {
            echo "✓ SellerRepository::findByEmail works\n";
        }
        
    } catch (Exception $e) {
        echo "✗ ERROR: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . "\n";
        echo "  Line: " . $e->getLine() . "\n";
    }
} else {
    echo "No sellers found in database. Please create a seller first.\n";
}