#!/usr/bin/env php
<?php

/**
 * Script to analyze runtime discovery logs
 */

declare(strict_types=1);

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Load autoloader
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/config/database.php';

use YakimaFinds\Utils\SystemLogger;

try {
    echo "Runtime Discovery Analysis\n";
    echo "=========================\n\n";
    
    // Connect to database
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $logger = new SystemLogger($pdo, 'runtime_analysis', false, false);
    
    // Get recent logs
    $since = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $logs = $logger->getLogs($since, null, 'runtime_discovery');
    
    if (empty($logs)) {
        echo "No runtime discovery logs found in the last hour.\n";
        echo "Make sure you've enabled runtime discovery and visited some pages.\n";
        exit(0);
    }
    
    echo "Found " . count($logs) . " log entries\n\n";
    
    // Analyze different types of logs
    $routes = [];
    $controllers = [];
    $services = [];
    $namespaces = [];
    $errors = [];
    
    foreach ($logs as $log) {
        $context = json_decode($log['context'] ?? '{}', true);
        
        switch ($log['message']) {
            case 'ROUTE_MATCHED':
                $path = $context['path'] ?? 'unknown';
                $controller = $context['controller'] ?? 'unknown';
                $action = $context['action'] ?? 'unknown';
                $routes[$path] = [
                    'controller' => $controller,
                    'action' => $action
                ];
                $controllers[$controller] = ($controllers[$controller] ?? 0) + 1;
                break;
                
            case 'SERVICE_RESOLVED':
                $abstract = $context['abstract'] ?? 'unknown';
                $concrete = $context['concrete'] ?? 'unknown';
                $namespace = $context['namespace'] ?? 'unknown';
                $services[$abstract] = $concrete;
                if ($namespace) {
                    $namespaces[$namespace] = ($namespaces[$namespace] ?? 0) + 1;
                }
                break;
                
            case 'ROUTE_NOT_FOUND':
                $path = $context['path'] ?? 'unknown';
                $errors['404'][] = $path;
                break;
        }
    }
    
    // Display results
    echo "=== ACTIVE ROUTES ===\n";
    foreach ($routes as $path => $info) {
        echo sprintf("%-30s => %s::%s\n", $path, $info['controller'], $info['action']);
    }
    
    echo "\n=== ACTIVE CONTROLLERS ===\n";
    foreach ($controllers as $controller => $count) {
        echo sprintf("%-60s (%d requests)\n", $controller, $count);
    }
    
    echo "\n=== ACTIVE SERVICES ===\n";
    foreach ($services as $abstract => $concrete) {
        if ($abstract !== $concrete) {
            echo sprintf("%-50s => %s\n", $abstract, $concrete);
        }
    }
    
    echo "\n=== ACTIVE NAMESPACES ===\n";
    arsort($namespaces);
    foreach ($namespaces as $namespace => $count) {
        echo sprintf("%-50s (%d classes)\n", $namespace, $count);
    }
    
    // Determine active modules based on namespaces
    echo "\n=== MODULE DETECTION ===\n";
    $modules = [
        'YFClaim' => false,
        'YFAuth' => false,
        'YFTheme' => false,
        'Communication' => false
    ];
    
    foreach ($namespaces as $namespace => $count) {
        if (strpos($namespace, 'Modules\\YFClaim') !== false) {
            $modules['YFClaim'] = true;
        } elseif (strpos($namespace, 'Modules\\YFAuth') !== false) {
            $modules['YFAuth'] = true;
        } elseif (strpos($namespace, 'Modules\\YFTheme') !== false) {
            $modules['YFTheme'] = true;
        } elseif (strpos($namespace, 'Communication') !== false) {
            $modules['Communication'] = true;
        }
    }
    
    foreach ($modules as $module => $active) {
        echo sprintf("%-20s: %s\n", $module, $active ? '✓ ACTIVE' : '✗ NOT DETECTED');
    }
    
    if (!empty($errors['404'])) {
        echo "\n=== 404 ERRORS ===\n";
        foreach ($errors['404'] as $path) {
            echo "- $path\n";
        }
    }
    
    // SQL query analysis
    echo "\n=== DATABASE ACTIVITY ===\n";
    $stmt = $pdo->query("
        SELECT 
            JSON_EXTRACT(context, '$.table') as table_name,
            COUNT(*) as query_count
        FROM system_logs 
        WHERE component = 'runtime_discovery' 
        AND level = 'database'
        AND created_at >= '$since'
        GROUP BY table_name
        ORDER BY query_count DESC
    ");
    
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($tables) {
        foreach ($tables as $table) {
            if ($table['table_name']) {
                echo sprintf("%-30s: %d queries\n", trim($table['table_name'], '"'), $table['query_count']);
            }
        }
    } else {
        echo "No database activity logged yet.\n";
    }
    
    echo "\n✅ Analysis complete!\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}