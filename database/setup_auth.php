<?php
/**
 * Setup YFAuth tables and test data
 */

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Utils\EnvLoader;

// Load .env file
EnvLoader::load(__DIR__ . '/../');

try {
    // Load database config
    $configData = require __DIR__ . '/../config/database.php';
    $dbConfig = $configData['database'];
    
    // Create PDO connection
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    echo "Setting up YFAuth tables...\n";
    
    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/../modules/yfauth/database/schema.sql');
    
    // Split by semicolon but not within quotes
    $statements = array_filter(array_map('trim', preg_split('/;(?=([^"]*"[^"]*")*[^"]*$)/', $schema)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && stripos($statement, 'DELIMITER') === false) {
            try {
                $pdo->exec($statement);
                echo ".";
            } catch (PDOException $e) {
                echo "\nWarning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n\nCreating roles...\n";
    
    // Create roles
    $roles = [
        ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Full system access', 'is_system' => 1],
        ['name' => 'claim_seller', 'display_name' => 'Estate Sale Seller', 'description' => 'Can manage estate sales', 'is_system' => 1],
        ['name' => 'user', 'display_name' => 'Regular User', 'description' => 'Basic user access', 'is_system' => 0]
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO yfa_auth_roles (name, display_name, description, is_system) VALUES (?, ?, ?, ?)");
    foreach ($roles as $role) {
        $stmt->execute([$role['name'], $role['display_name'], $role['description'], $role['is_system']]);
    }
    
    echo "Creating permissions...\n";
    
    // Create permissions
    $permissions = [
        ['name' => 'admin.access', 'display_name' => 'Access Admin Panel', 'description' => 'Can access admin dashboard', 'module' => 'core'],
        ['name' => 'admin.manage_all', 'display_name' => 'Manage Everything', 'description' => 'Full administrative access', 'module' => 'core'],
        ['name' => 'claims.view', 'display_name' => 'View Estate Sales', 'description' => 'Can view estate sale listings', 'module' => 'yfclaim'],
        ['name' => 'claims.manage', 'display_name' => 'Manage Estate Sales', 'description' => 'Can create and edit estate sales', 'module' => 'yfclaim'],
        ['name' => 'claims.edit_all', 'display_name' => 'Edit All Sales', 'description' => 'Can edit any estate sale', 'module' => 'yfclaim']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO yfa_auth_permissions (name, display_name, description, module) VALUES (?, ?, ?, ?)");
    foreach ($permissions as $perm) {
        $stmt->execute([$perm['name'], $perm['display_name'], $perm['description'], $perm['module']]);
    }
    
    echo "Linking roles to permissions...\n";
    
    // Get role IDs
    $roleIds = [];
    $stmt = $pdo->query("SELECT id, name FROM yfa_auth_roles");
    while ($row = $stmt->fetch()) {
        $roleIds[$row['name']] = $row['id'];
    }
    
    // Get permission IDs
    $permIds = [];
    $stmt = $pdo->query("SELECT id, name FROM yfa_auth_permissions");
    while ($row = $stmt->fetch()) {
        $permIds[$row['name']] = $row['id'];
    }
    
    // Assign permissions to roles
    $rolePermissions = [
        'admin' => ['admin.access', 'admin.manage_all', 'claims.view', 'claims.manage', 'claims.edit_all'],
        'claim_seller' => ['claims.view', 'claims.manage'],
        'user' => ['claims.view']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO yfa_auth_role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($rolePermissions as $roleName => $permissions) {
        if (isset($roleIds[$roleName])) {
            foreach ($permissions as $permName) {
                if (isset($permIds[$permName])) {
                    $stmt->execute([$roleIds[$roleName], $permIds[$permName]]);
                }
            }
        }
    }
    
    echo "Creating test users...\n";
    
    // Create test users
    $users = [
        [
            'email' => 'admin@yakimafinds.com',
            'username' => 'admin',
            'password' => 'admin123',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => 'active',
            'email_verified' => 1,
            'role' => 'admin'
        ],
        [
            'email' => 'seller@example.com',
            'username' => 'testseller',
            'password' => 'seller123',
            'first_name' => 'Test',
            'last_name' => 'Seller',
            'status' => 'active',
            'email_verified' => 1,
            'role' => 'claim_seller'
        ]
    ];
    
    $userStmt = $pdo->prepare("
        INSERT INTO yfa_auth_users 
        (email, username, password_hash, first_name, last_name, status, email_verified) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $roleStmt = $pdo->prepare("INSERT INTO yfa_auth_user_roles (user_id, role_id) VALUES (?, ?)");
    
    foreach ($users as $user) {
        try {
            // Check if user exists
            $checkStmt = $pdo->prepare("SELECT id FROM yfa_auth_users WHERE username = ?");
            $checkStmt->execute([$user['username']]);
            $existingUser = $checkStmt->fetch();
            
            if (!$existingUser) {
                // Create user
                $userStmt->execute([
                    $user['email'],
                    $user['username'],
                    password_hash($user['password'], PASSWORD_DEFAULT),
                    $user['first_name'],
                    $user['last_name'],
                    $user['status'],
                    $user['email_verified']
                ]);
                
                $userId = $pdo->lastInsertId();
                
                // Assign role
                if (isset($roleIds[$user['role']])) {
                    $roleStmt->execute([$userId, $roleIds[$user['role']]]);
                }
                
                echo "Created user: {$user['username']} (password: {$user['password']})\n";
            } else {
                echo "User {$user['username']} already exists\n";
            }
        } catch (PDOException $e) {
            echo "Error creating user {$user['username']}: " . $e->getMessage() . "\n";
        }
    }
    
    // Also ensure seller profile exists for the test seller
    echo "\nCreating seller profile for test seller...\n";
    $stmt = $pdo->prepare("SELECT id FROM yfa_auth_users WHERE username = 'testseller'");
    $stmt->execute();
    $sellerUser = $stmt->fetch();
    
    if ($sellerUser) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO yfc_sellers 
            (email, company_name, contact_name, phone, status, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            'seller@example.com',
            'Test Estate Sales Co',
            'Test Seller',
            '555-0123',
            'active'
        ]);
    }
    
    echo "\nSetup complete!\n";
    echo "\nTest users:\n";
    echo "Admin: username=admin, password=admin123\n";
    echo "Seller: username=testseller, password=seller123\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}