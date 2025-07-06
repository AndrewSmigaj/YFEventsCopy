#!/usr/bin/env php
<?php
require_once __DIR__ . '/generate_mock_data.php';

$config = [
    'host' => 'localhost',
    'database' => 'yakima_finds',
    'username' => 'yfevents',
    'password' => 'yfevents_pass'
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "[INFO] Starting mock data generation...\n";
    generateMockData($pdo);
    
    // Show counts
    $counts = [
        'events' => "SELECT COUNT(*) FROM events",
        'shops' => "SELECT COUNT(*) FROM local_shops",
        'sellers' => "SELECT COUNT(*) FROM yfc_sellers",
        'sales' => "SELECT COUNT(*) FROM yfc_sales",
        'items' => "SELECT COUNT(*) FROM yfc_items",
        'users' => "SELECT COUNT(*) FROM yfa_auth_users"
    ];
    
    echo "\n[SUCCESS] Mock data generated!\n\n";
    echo "Data summary:\n";
    foreach ($counts as $label => $query) {
        $count = $pdo->query($query)->fetchColumn();
        echo "  - $label: $count\n";
    }
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . basename($e->getFile()) . "\n";
    exit(1);
}