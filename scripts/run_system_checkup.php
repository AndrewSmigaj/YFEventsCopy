<?php

/**
 * System Checkup Command Line Tool
 * Run via: php scripts/run_system_checkup.php [--no-llm] [--verbose]
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use YFEvents\Utils\SystemCheckup;
use YFEvents\Utils\SystemLogger;

// Parse command line arguments
$options = getopt('', ['no-llm', 'verbose', 'help']);

if (isset($options['help'])) {
    echo "System Checkup Tool\n";
    echo "Usage: php scripts/run_system_checkup.php [options]\n\n";
    echo "Options:\n";
    echo "  --no-llm     Skip LLM-powered recommendations\n";
    echo "  --verbose    Show detailed output\n";
    echo "  --help       Show this help message\n\n";
    exit(0);
}

$verbose = isset($options['verbose']);
$generateRecommendations = !isset($options['no-llm']);

$logger = new SystemLogger($db, 'checkup_cli');

try {
    echo "Starting system checkup...\n";
    
    if ($verbose) {
        echo "Configuration:\n";
        echo "- LLM Recommendations: " . ($generateRecommendations ? 'enabled' : 'disabled') . "\n";
        echo "- Verbose Output: enabled\n\n";
    }
    
    $checkup = new SystemCheckup($db);
    $results = $checkup->runCheckup($generateRecommendations);
    
    echo "✅ System checkup completed in {$results['duration_ms']}ms\n\n";
    
    // Summary
    echo "=== SUMMARY ===\n";
    echo "Components Checked: " . count($results['components_checked']) . "\n";
    echo "Errors Found: " . count($results['errors_found']) . "\n";
    echo "Warnings Found: " . count($results['warnings_found']) . "\n";
    echo "Recommendations Generated: " . count($results['recommendations']) . "\n\n";
    
    // Component Health
    echo "=== COMPONENT HEALTH ===\n";
    foreach ($results['components_checked'] as $component => $health) {
        $status = $health['status'];
        $icon = $status === 'healthy' ? '✅' : ($status === 'warning' ? '⚠️' : '❌');
        echo "{$icon} " . ucfirst($component) . ": " . ucfirst($status) . "\n";
        
        if ($verbose && !empty($health['issues'])) {
            foreach ($health['issues'] as $issue) {
                echo "   - {$issue}\n";
            }
        }
    }
    echo "\n";
    
    // Errors
    if (!empty($results['errors_found'])) {
        echo "=== ERRORS FOUND ===\n";
        foreach ($results['errors_found'] as $error) {
            echo "❌ {$error['component']} ({$error['level']}): {$error['count']} occurrences\n";
            if ($verbose) {
                echo "   Last: {$error['last_occurrence']}\n";
                echo "   Sample: " . substr($error['sample_messages'], 0, 100) . "...\n";
            }
        }
        echo "\n";
    }
    
    // Warnings
    if (!empty($results['warnings_found'])) {
        echo "=== WARNINGS FOUND ===\n";
        foreach ($results['warnings_found'] as $warning) {
            echo "⚠️ {$warning['component']}: {$warning['count']} occurrences\n";
            if ($verbose) {
                echo "   Last: {$warning['last_occurrence']}\n";
                echo "   Sample: " . substr($warning['sample_messages'], 0, 100) . "...\n";
            }
        }
        echo "\n";
    }
    
    // Recommendations
    if (!empty($results['recommendations'])) {
        echo "=== AI RECOMMENDATIONS ===\n";
        foreach ($results['recommendations'] as $i => $rec) {
            $priority = $rec['priority'] ?? 'medium';
            $priorityIcon = $priority === 'high' ? '🔴' : ($priority === 'medium' ? '🟡' : '🟢');
            
            echo "{$priorityIcon} " . ($i + 1) . ". {$rec['title']}\n";
            echo "   Priority: {$priority} | Complexity: {$rec['complexity']}\n";
            
            if ($verbose) {
                echo "   Description: {$rec['description']}\n";
                if (!empty($rec['instructions'])) {
                    echo "   Instructions: " . substr($rec['instructions'], 0, 200) . "...\n";
                }
            }
            echo "\n";
        }
    }
    
    // Performance Issues
    if (!empty($results['performance_metrics'])) {
        echo "=== PERFORMANCE ISSUES ===\n";
        foreach ($results['performance_metrics'] as $perf) {
            echo "🐌 Slow operation: {$perf['operation']} took {$perf['duration_ms']}ms\n";
        }
        echo "\n";
    }
    
    echo "Checkup ID: {$results['checkup_id']}\n";
    echo "View detailed results at: http://your-domain.com/admin/system-checkup.php\n";
    
    $logger->info("CLI checkup completed successfully", [
        'checkup_id' => $results['checkup_id'],
        'duration_ms' => $results['duration_ms'],
        'errors_found' => count($results['errors_found']),
        'recommendations_generated' => count($results['recommendations'])
    ]);
    
} catch (Exception $e) {
    echo "❌ Checkup failed: " . $e->getMessage() . "\n";
    
    if ($verbose) {
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
    $logger->error("CLI checkup failed", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    exit(1);
}
?>