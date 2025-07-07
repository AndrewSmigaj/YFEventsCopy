<?php
// Test the fixed chat channel loading

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use YFEvents\Application\Bootstrap;
use YFEvents\Application\Services\Communication\AdminSellerChatService;

// Bootstrap application
$container = Bootstrap::boot();

// Test creating channels and loading them
try {
    echo "=== Testing Chat Channel System ===\n\n";
    
    // 1. Create test user and seller
    session_start();
    $_SESSION = [
        'auth' => [
            'user_id' => 9999,
            'username' => 'testseller',
            'email' => 'testseller@example.com',
            'roles' => ['seller', 'user']
        ],
        'seller' => [
            'seller_id' => 99,
            'company_name' => 'Test Estate Sales',
            'contact_name' => 'Test Seller'
        ]
    ];
    
    echo "1. Test user session created\n";
    
    // 2. Ensure user is in global channels
    $adminSellerChat = $container->resolve(AdminSellerChatService::class);
    $adminSellerChat->ensureUserInGlobalChannels($_SESSION['auth']['user_id'], 'seller');
    echo "2. Added user to global channels\n";
    
    // 3. Test loading channels via API controller
    $config = $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class);
    $controller = new \YFEvents\Presentation\Api\Controllers\Communication\ChannelApiController($container, $config);
    
    ob_start();
    $controller->index();
    $response = ob_get_clean();
    
    $data = json_decode($response, true);
    
    echo "\n3. API Response:\n";
    if ($data['success']) {
        echo "   Success: Yes\n";
        echo "   Channels found: " . count($data['data']) . "\n";
        
        if (count($data['data']) > 0) {
            echo "\n   Channel List:\n";
            foreach ($data['data'] as $channel) {
                echo "   - " . $channel['name'] . " (Type: " . $channel['type'] . ", ID: " . $channel['id'] . ")\n";
            }
        }
    } else {
        echo "   Success: No\n";
        echo "   Error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
    
    // 4. Test embedded view for seller dashboard
    echo "\n4. Testing embedded view:\n";
    
    $_GET['seller_id'] = '99';
    $commController = new \YFEvents\Presentation\Http\Controllers\CommunicationController($container, $config);
    
    ob_start();
    $errorOccurred = false;
    try {
        $commController->embedded();
    } catch (Exception $e) {
        $errorOccurred = true;
        echo "   Error: " . $e->getMessage() . "\n";
    }
    $embeddedOutput = ob_get_clean();
    
    if (!$errorOccurred) {
        if (strpos($embeddedOutput, 'Communication Hub') !== false) {
            echo "   Embedded view rendered successfully\n";
            echo "   Contains JS initialization: " . (strpos($embeddedOutput, 'CommunicationApp.init()') !== false ? 'Yes' : 'No') . "\n";
        } else if (strpos($embeddedOutput, 'Unauthorized') !== false) {
            echo "   Embedded view: Unauthorized access\n";
        } else {
            echo "   Embedded view: Unknown response\n";
        }
    }
    
    // 5. Check database directly
    echo "\n5. Direct database check:\n";
    $pdo = $container->resolve(\PDO::class);
    
    // Check channels
    $stmt = $pdo->query("SELECT id, name, type, is_archived FROM communication_channels ORDER BY id");
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Total channels in DB: " . count($channels) . "\n";
    
    // Check participants
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM communication_participants WHERE user_id = ?");
    $stmt->execute([$_SESSION['auth']['user_id']]);
    $result = $stmt->fetch();
    echo "   User participations: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";