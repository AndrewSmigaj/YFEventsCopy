<?php
// Create test seller for YFClaim login testing
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';

use YFEvents\Modules\YFClaim\Models\SellerModel;

$sellerModel = new SellerModel($db);

// Create test seller
$testSeller = [
    'company_name' => 'Test Estate Sales Co',
    'contact_name' => 'Test User',
    'email' => 'test@estatesales.com',
    'username' => 'testuser',
    'phone' => '509-555-0100',
    'password' => 'testpass123', // Will be hashed by createSeller
    'address' => '123 Test St',
    'city' => 'Yakima',
    'state' => 'WA',
    'zip' => '98901',
    'status' => 'active'
];

try {
    // Check if seller already exists
    $existing = $sellerModel->findByEmail($testSeller['email']);
    if ($existing) {
        echo "Test seller already exists with ID: " . $existing['id'] . "\n";
        echo "Username: " . ($existing['username'] ?? 'Not set') . "\n";
        echo "Email: " . $existing['email'] . "\n";
        
        // Update password
        $sellerModel->update($existing['id'], [
            'password_hash' => password_hash('testpass123', PASSWORD_DEFAULT),
            'username' => 'testuser'
        ]);
        echo "Password reset to: testpass123\n";
        echo "Username set to: testuser\n";
    } else {
        $sellerId = $sellerModel->createSeller($testSeller);
        echo "Test seller created successfully!\n";
        echo "Seller ID: $sellerId\n";
        echo "Username: testuser\n";
        echo "Email: test@estatesales.com\n";
        echo "Password: testpass123\n";
    }
    
    echo "\nYou can now login at:\n";
    echo "http://backoffice.yakimafinds.com/modules/yfclaim/www/admin/login.php\n";
    
} catch (Exception $e) {
    echo "Error creating test seller: " . $e->getMessage() . "\n";
}
?>