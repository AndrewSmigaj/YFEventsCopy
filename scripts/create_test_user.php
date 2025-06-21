<?php
// Create a test user for communication testing

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4",
        'yfevents',
        'yfevents_pass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Check if test user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'test@yakimafinds.com'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if (!$user) {
        // Create test user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, role, status, created_at) 
            VALUES (:username, :email, :password_hash, :first_name, :last_name, :role, :status, NOW())
        ");
        
        $stmt->execute([
            'username' => 'testuser',
            'email' => 'test@yakimafinds.com',
            'password_hash' => password_hash('test123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'admin',
            'status' => 'active'
        ]);
        
        $userId = $pdo->lastInsertId();
        echo "Test user created with ID: $userId\n";
        echo "Email: test@yakimafinds.com\n";
        echo "Password: test123\n";
        echo "Role: admin\n";
    } else {
        echo "Test user already exists with ID: " . $user['id'] . "\n";
    }
    
    // Create a test vendor user with shop
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'vendor@yakimafinds.com'");
    $stmt->execute();
    $vendor = $stmt->fetch();
    
    if (!$vendor) {
        // First check if we have a shop
        $stmt = $pdo->prepare("SELECT id FROM local_shops LIMIT 1");
        $stmt->execute();
        $shop = $stmt->fetch();
        
        // Create vendor user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, role, status, created_at) 
            VALUES (:username, :email, :password_hash, :first_name, :last_name, :role, :status, NOW())
        ");
        
        $stmt->execute([
            'username' => 'testvendor',
            'email' => 'vendor@yakimafinds.com',
            'password_hash' => password_hash('vendor123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'Vendor',
            'role' => 'user',
            'status' => 'active'
        ]);
        
        echo "\nVendor user created\n";
        echo "Email: vendor@yakimafinds.com\n";
        echo "Password: vendor123\n";
        echo "Shop ID: " . ($shop ? $shop['id'] : 'none') . "\n";
    }
    
    // Create initial channels
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM communication_channels");
    $stmt->execute();
    $channelCount = $stmt->fetchColumn();
    
    if ($channelCount == 0) {
        echo "\nCreating initial channels...\n";
        
        // Get admin user ID
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();
        
        if ($admin) {
            $channels = [
                ['name' => 'General Discussion', 'slug' => 'general', 'description' => 'Open discussion for all users', 'type' => 'public'],
                ['name' => 'Announcements', 'slug' => 'announcements', 'description' => 'Important announcements from Yakima Finds', 'type' => 'announcement'],
                ['name' => 'Event Planning', 'slug' => 'event-planning', 'description' => 'Coordinate upcoming events', 'type' => 'public']
            ];
            
            foreach ($channels as $channel) {
                $stmt = $pdo->prepare("
                    INSERT INTO communication_channels (name, slug, description, type, created_by_user_id) 
                    VALUES (:name, :slug, :description, :type, :user_id)
                ");
                $stmt->execute([
                    'name' => $channel['name'],
                    'slug' => $channel['slug'],
                    'description' => $channel['description'],
                    'type' => $channel['type'],
                    'user_id' => $admin['id']
                ]);
                echo "Created channel: " . $channel['name'] . "\n";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}