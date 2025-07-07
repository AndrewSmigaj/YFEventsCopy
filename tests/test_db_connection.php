<?php
echo "Testing database connection...\n";
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4",
        'root',
        'root',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Connected successfully\n";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) FROM yfa_auth_users");
    $count = $stmt->fetchColumn();
    echo "✓ Found $count users in yfa_auth_users\n";
} catch (Exception $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
}