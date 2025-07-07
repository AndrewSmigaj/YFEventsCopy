<?php

/**
 * Mock data generator for testing the communication and inquiry systems
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "YFEvents Mock Data Generator\n";
echo "============================\n\n";

try {
    // Direct database connection for simplicity
    $pdo = new PDO(
        "mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4",
        'yfevents',
        'yfevents_pass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Note: For a full implementation, we'd use the container and services
    // but for mock data generation, direct SQL is simpler and sufficient
    
    // Check if we should clear existing data
    if (in_array('--clear', $argv)) {
        echo "Clearing existing mock data...\n";
        // Clear in correct order due to foreign keys
        $pdo->exec("DELETE FROM communication_messages WHERE content LIKE '%[MOCK]%'");
        $pdo->exec("DELETE FROM communication_participants WHERE channel_id IN (SELECT id FROM communication_channels WHERE name LIKE '%[MOCK]%')");
        $pdo->exec("DELETE FROM communication_channels WHERE name LIKE '%[MOCK]%'");
        $pdo->exec("DELETE FROM yfc_inquiries WHERE message LIKE '%[MOCK]%'");
        $pdo->exec("DELETE FROM yfc_items WHERE title LIKE '%[MOCK]%'");
        $pdo->exec("DELETE FROM yfc_sales WHERE title LIKE '%[MOCK]%'");
        echo "✓ Cleared existing mock data\n\n";
    }
    
    // 1. Ensure test users exist
    echo "Creating test users...\n";
    $testUsers = [
        ['id' => 1, 'username' => 'admin', 'email' => 'admin@yfevents.test', 'role' => 'admin'],
        ['id' => 2, 'username' => 'johnseller', 'email' => 'john@seller.test', 'role' => 'seller'],
        ['id' => 3, 'username' => 'janeseller', 'email' => 'jane@seller.test', 'role' => 'seller'],
    ];
    
    foreach ($testUsers as $user) {
        // Check if user exists in yfa_auth_users
        $stmt = $pdo->prepare("SELECT id FROM yfa_auth_users WHERE id = ?");
        $stmt->execute([$user['id']]);
        if (!$stmt->fetch()) {
            // Create user
            $stmt = $pdo->prepare("
                INSERT INTO yfa_auth_users (id, username, email, password_hash, is_active, created_at) 
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $user['id'], 
                $user['username'], 
                $user['email'], 
                password_hash('password123', PASSWORD_DEFAULT)
            ]);
            echo "✓ Created user: {$user['username']}\n";
        } else {
            echo "✓ User exists: {$user['username']}\n";
        }
    }
    
    // 2. Create seller records
    echo "\nCreating seller records...\n";
    $sellers = [
        ['id' => 2, 'company_name' => 'John\'s Estate Sales', 'contact_name' => 'John Doe', 'email' => 'john@seller.test'],
        ['id' => 3, 'company_name' => 'Jane\'s Treasures', 'contact_name' => 'Jane Smith', 'email' => 'jane@seller.test'],
    ];
    
    foreach ($sellers as $seller) {
        $stmt = $pdo->prepare("SELECT id FROM yfc_sellers WHERE id = ? OR email = ?");
        $stmt->execute([$seller['id'], $seller['email']]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO yfc_sellers (id, company_name, contact_name, email, phone, password_hash, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $seller['id'],
                $seller['company_name'],
                $seller['contact_name'],
                $seller['email'],
                '555-000' . $seller['id'],
                password_hash('password123', PASSWORD_DEFAULT)
            ]);
            echo "✓ Created seller: {$seller['company_name']}\n";
        } else {
            echo "✓ Seller exists: {$seller['company_name']}\n";
        }
    }
    
    // 3. Create test sales and items
    echo "\nCreating test sales and items...\n";
    $sales = [
        [
            'seller_id' => 2,
            'title' => 'Downtown Estate Sale [MOCK]',
            'description' => 'Beautiful collection of antiques and furniture',
            'address' => '123 Main St',
            'city' => 'Yakima',
            'state' => 'WA',
            'items' => [
                ['title' => 'Antique Dining Set', 'price' => 1200.00],
                ['title' => 'Vintage Leather Couch', 'price' => 800.00],
                ['title' => 'Crystal Chandelier', 'price' => 450.00],
            ]
        ],
        [
            'seller_id' => 3,
            'title' => 'Moving Sale - Everything Must Go [MOCK]',
            'description' => 'Quality furniture and household items',
            'address' => '456 Oak Ave',
            'city' => 'Yakima',
            'state' => 'WA',
            'items' => [
                ['title' => 'King Size Bedroom Set', 'price' => 1500.00],
                ['title' => 'Kitchen Appliance Bundle', 'price' => 600.00],
                ['title' => 'Home Office Desk', 'price' => 350.00],
            ]
        ]
    ];
    
    $createdItems = [];
    foreach ($sales as $sale) {
        // Check if seller exists
        $stmt = $pdo->prepare("SELECT id FROM yfc_sellers WHERE id = ?");
        $stmt->execute([$sale['seller_id']]);
        $sellerData = $stmt->fetch();
        
        if ($sellerData) {
            // Create sale
            $stmt = $pdo->prepare("
                INSERT INTO yfc_sales (seller_id, title, description, address, city, state, zip, 
                                     claim_start, claim_end, preview_start, preview_end, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, '98901', 
                        DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 3 DAY),
                        NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 'active', NOW())
            ");
            $stmt->execute([
                $sale['seller_id'],
                $sale['title'],
                $sale['description'],
                $sale['address'],
                $sale['city'],
                $sale['state']
            ]);
            $saleId = $pdo->lastInsertId();
            echo "✓ Created sale: {$sale['title']}\n";
            
            // Create items
            foreach ($sale['items'] as $idx => $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO yfc_items (sale_id, title, description, price, status, sort_order, created_at)
                    VALUES (?, ?, ?, ?, 'available', ?, NOW())
                ");
                $stmt->execute([
                    $saleId,
                    $item['title'] . ' [MOCK]',
                    'High quality item in excellent condition',
                    $item['price'],
                    $idx + 1
                ]);
                $itemId = $pdo->lastInsertId();
                $createdItems[] = [
                    'id' => $itemId,
                    'seller_id' => $sale['seller_id'],
                    'title' => $item['title']
                ];
            }
        }
    }
    
    // 4. Create communication channels
    echo "\nCreating communication channels...\n";
    $channels = [];
    
    // Create direct channels between admin and each seller
    foreach ([2, 3] as $sellerId) {
        $channelData = [
            'name' => "Support Channel - Seller $sellerId [MOCK]",
            'slug' => "support-seller-$sellerId-mock",
            'type' => 'private',
            'description' => 'Direct support channel between admin and seller',
            'created_by_user_id' => 1
        ];
        
        try {
            // Create channel
            $stmt = $pdo->prepare("
                INSERT INTO communication_channels (name, slug, type, description, created_by_user_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $channelData['name'],
                $channelData['slug'],
                $channelData['type'],
                $channelData['description'],
                $channelData['created_by_user_id']
            ]);
            $channelId = $pdo->lastInsertId();
            
            // Add participants
            foreach ([1, $sellerId] as $userId) {
                $stmt = $pdo->prepare("
                    INSERT INTO communication_participants (channel_id, user_id, joined_at, last_read_message_id)
                    VALUES (?, ?, NOW(), NULL)
                ");
                $stmt->execute([$channelId, $userId]);
            }
            
            $channels[] = ['id' => $channelId, 'participants' => [1, $sellerId]];
            echo "✓ Created channel: {$channelData['name']}\n";
        } catch (\Exception $e) {
            echo "✗ Failed to create channel: " . $e->getMessage() . "\n";
        }
    }
    
    // 5. Create test messages
    echo "\nCreating test messages...\n";
    $messageTemplates = [
        ['from' => 1, 'text' => 'Welcome to YFClaim! How can I help you today? [MOCK]'],
        ['from' => 'seller', 'text' => 'Hi! I need help setting up my first estate sale. [MOCK]'],
        ['from' => 1, 'text' => 'I\'d be happy to help! Have you already created your seller account? [MOCK]'],
        ['from' => 'seller', 'text' => 'Yes, I\'m logged in now. What\'s the next step? [MOCK]'],
        ['from' => 1, 'text' => 'Great! Click on "Create New Sale" to get started. You\'ll need your sale dates and address. [MOCK]'],
    ];
    
    foreach ($channels as $channelData) {
        $channelId = $channelData['id'];
        $sellerId = $channelData['participants'][1];
        
        foreach ($messageTemplates as $template) {
            $userId = $template['from'] === 'seller' ? $sellerId : $template['from'];
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO communication_messages (channel_id, user_id, content, content_type, created_at)
                    VALUES (?, ?, ?, 'text', NOW())
                ");
                $stmt->execute([
                    $channelId,
                    $userId,
                    str_replace('[MOCK]', "[MOCK-{$channelId}]", $template['text'])
                ]);
                echo "✓ Created message in channel {$channelId}\n";
                
                // Add small delay to ensure message ordering
                usleep(100000); // 0.1 second
            } catch (\Exception $e) {
                echo "✗ Failed to create message: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // 6. Create test inquiries
    echo "\nCreating test inquiries...\n";
    $inquiryTemplates = [
        ['name' => 'Tom Buyer', 'email' => 'tom@buyer.test', 'message' => 'Is this item still available? I\'m very interested. [MOCK]'],
        ['name' => 'Mary Buyer', 'email' => 'mary@buyer.test', 'message' => 'What are the dimensions? I need to make sure it fits. [MOCK]'],
        ['name' => 'Bob Buyer', 'email' => 'bob@buyer.test', 'message' => 'Can you provide more photos? I\'d like to see the condition better. [MOCK]'],
        ['name' => 'Alice Buyer', 'email' => 'alice@buyer.test', 'message' => 'Is the price negotiable? I\'m interested in buying multiple items. [MOCK]'],
    ];
    
    foreach ($createdItems as $idx => $item) {
        if ($idx >= count($inquiryTemplates)) break;
        
        $template = $inquiryTemplates[$idx];
        $inquiryData = [
            'item_id' => $item['id'],
            'seller_id' => $item['seller_id'],
            'buyer_name' => $template['name'],
            'buyer_email' => $template['email'],
            'buyer_phone' => '555-' . sprintf('%04d', rand(1000, 9999)),
            'message' => $template['message'] . "\n\nRegarding: " . $item['title'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'MockDataGenerator/1.0'
        ];
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO yfc_inquiries (item_id, seller_user_id, buyer_name, buyer_email, 
                                         buyer_phone, message, status, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'new', ?, ?, NOW())
            ");
            $stmt->execute([
                $item['id'],
                $item['seller_id'],
                $template['name'],
                $template['email'],
                $inquiryData['buyer_phone'],
                $inquiryData['message'],
                $inquiryData['ip_address'],
                $inquiryData['user_agent']
            ]);
            echo "✓ Created inquiry from {$template['name']} for {$item['title']}\n";
        } catch (\Exception $e) {
            echo "✗ Failed to create inquiry: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ Mock data generation complete!\n";
    echo "\nSummary:\n";
    echo "- Users created: " . count($testUsers) . "\n";
    echo "- Sales created: " . count($sales) . "\n";
    echo "- Items created: " . count($createdItems) . "\n";
    echo "- Channels created: " . count($channels) . "\n";
    echo "- Messages created: ~" . (count($channels) * count($messageTemplates)) . "\n";
    echo "- Inquiries created: " . min(count($createdItems), count($inquiryTemplates)) . "\n";
    
    echo "\nTo clear mock data, run: php " . $argv[0] . " --clear\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}