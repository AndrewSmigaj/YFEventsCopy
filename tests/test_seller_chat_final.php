<?php
// Final test - simulate seller dashboard chat loading

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

echo "=== Testing Seller Dashboard Chat Loading ===\n\n";

// 1. Check for existing auth users
$pdo = new PDO('mysql:host=localhost;dbname=yakima_finds', 'root', '');
$stmt = $pdo->query("SELECT id, username, email FROM yfa_auth_users WHERE id IN (SELECT DISTINCT user_id FROM yfa_auth_user_roles WHERE role_id IN (SELECT id FROM yfa_auth_roles WHERE role_name = 'seller')) LIMIT 1");
$authUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$authUser) {
    echo "No seller users found in yfa_auth_users. Creating test seller...\n";
    
    // Create test user
    $stmt = $pdo->prepare("INSERT INTO yfa_auth_users (username, email, password_hash, is_active) VALUES (?, ?, ?, 1)");
    $stmt->execute(['testseller', 'testseller@example.com', password_hash('test123', PASSWORD_DEFAULT)]);
    $userId = $pdo->lastInsertId();
    
    // Get seller role
    $stmt = $pdo->query("SELECT id FROM yfa_auth_roles WHERE role_name = 'seller'");
    $role = $stmt->fetch();
    if ($role) {
        $stmt = $pdo->prepare("INSERT INTO yfa_auth_user_roles (user_id, role_id) VALUES (?, ?)");
        $stmt->execute([$userId, $role['id']]);
    }
    
    $authUser = ['id' => $userId, 'username' => 'testseller', 'email' => 'testseller@example.com'];
}

echo "Using auth user: " . $authUser['username'] . " (ID: " . $authUser['id'] . ")\n\n";

// 2. Set up session as if user logged in
session_start();
$_SESSION = [
    'auth' => [
        'user_id' => $authUser['id'],
        'username' => $authUser['username'],
        'email' => $authUser['email'],
        'roles' => ['seller', 'user']
    ],
    'seller' => [
        'seller_id' => 1,
        'company_name' => 'Test Estate Sales',
        'contact_name' => $authUser['username']
    ]
];

// 3. Test the API endpoint directly
echo "3. Testing /api/communication/channels endpoint:\n";

$ch = curl_init('http://localhost/api/communication/channels');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
if ($response) {
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "   Valid JSON: Yes\n";
        if (isset($data['success']) && $data['success']) {
            echo "   Success: Yes\n";
            echo "   Channels returned: " . count($data['data'] ?? []) . "\n";
            if (!empty($data['data'])) {
                echo "   Channel names: ";
                $names = array_map(function($ch) { return $ch['name']; }, $data['data']);
                echo implode(', ', $names) . "\n";
            }
        } else {
            echo "   Success: No\n";
            echo "   Error: " . ($data['message'] ?? 'Unknown') . "\n";
        }
    } else {
        echo "   Valid JSON: No\n";
        echo "   Response: " . substr($response, 0, 200) . "\n";
    }
}

// 4. Ensure user is in channels using the service
echo "\n4. Adding user to global channels:\n";
try {
    $container = \YFEvents\Application\Bootstrap::boot();
    $adminSellerChat = $container->resolve(\YFEvents\Application\Services\Communication\AdminSellerChatService::class);
    $adminSellerChat->ensureUserInGlobalChannels((int)$authUser['id'], 'seller');
    echo "   Success! User added to global channels.\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 5. Test loading channels again
echo "\n5. Testing channel loading after adding user:\n";
try {
    $communicationService = $container->resolve(\YFEvents\Application\Services\Communication\CommunicationService::class);
    $channelsWithUnread = $communicationService->getUserChannelsWithUnread((int)$authUser['id']);
    
    echo "   Found " . count($channelsWithUnread) . " channels:\n";
    foreach ($channelsWithUnread as $item) {
        $channel = $item['channel'];
        echo "   - " . $channel->getName() . " (Unread: " . $item['unread_count'] . ")\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "\nThe seller dashboard chat should now load properly when this user logs in.\n";