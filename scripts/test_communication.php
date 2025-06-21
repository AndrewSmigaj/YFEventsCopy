<?php
// Test communication system setup

echo "Testing YFEvents Communication Tool Setup\n";
echo "========================================\n\n";

// Test 1: Database tables
echo "1. Checking database tables...\n";
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4",
        'yfevents',
        'yfevents_pass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $tables = [
        'communication_channels',
        'communication_messages',
        'communication_participants',
        'communication_attachments',
        'communication_email_addresses',
        'communication_notifications',
        'communication_reactions'
    ];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "   ✓ $table (rows: $count)\n";
    }
    echo "   Database tables: OK\n\n";
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n\n";
}

// Test 2: Test users
echo "2. Checking test users...\n";
try {
    $stmt = $pdo->query("SELECT id, username, email, role FROM users WHERE email IN ('test@yakimafinds.com', 'vendor@yakimafinds.com')");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        echo "   ✓ User: {$user['email']} (ID: {$user['id']}, Role: {$user['role']})\n";
    }
    echo "   Test users: OK\n\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 3: Channels
echo "3. Checking channels...\n";
try {
    $stmt = $pdo->query("SELECT id, name, slug, type FROM communication_channels");
    $channels = $stmt->fetchAll();
    
    foreach ($channels as $channel) {
        echo "   ✓ Channel: {$channel['name']} (Type: {$channel['type']}, Slug: {$channel['slug']})\n";
    }
    echo "   Channels: OK\n\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 4: File paths
echo "4. Checking file paths...\n";
$paths = [
    __DIR__ . '/../www/html/communication' => 'Frontend interface',
    __DIR__ . '/../www/html/api/communication' => 'API endpoints',
    __DIR__ . '/../www/html/uploads/communication' => 'Upload directory',
    __DIR__ . '/../config/services/communication.php' => 'Service config',
    __DIR__ . '/../config/bootstrap.php' => 'Bootstrap file'
];

foreach ($paths as $path => $desc) {
    if (file_exists($path)) {
        echo "   ✓ $desc: " . (is_dir($path) ? 'directory' : 'file') . " exists\n";
    } else {
        echo "   ✗ $desc: NOT FOUND\n";
    }
}
echo "\n";

// Test 5: Class loading
echo "5. Testing class loading...\n";
require_once __DIR__ . '/../vendor/autoload.php';

$classes = [
    'YFEvents\Domain\Communication\Entities\Channel',
    'YFEvents\Domain\Communication\Entities\Message',
    'YFEvents\Domain\Communication\Services\ChannelService',
    'YakimaFinds\Infrastructure\Container\Container'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "   ✓ Class: $class\n";
    } else {
        echo "   ✗ Class not found: $class\n";
    }
}
echo "\n";

// Summary
echo "========================================\n";
echo "Setup Status: ";

// Provide URLs
echo "\n\nAccess URLs:\n";
echo "- Login: http://[your-domain]/login.php\n";
echo "- Communication Hub: http://[your-domain]/communication/\n";
echo "- API Endpoint: http://[your-domain]/api/communication/\n";

echo "\nTest Accounts:\n";
echo "- Admin: test@yakimafinds.com / test123\n";
echo "- Vendor: vendor@yakimafinds.com / vendor123\n";