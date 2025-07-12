#!/usr/bin/env php
<?php
/**
 * Validate that all required PHP modules are installed
 */

$required = [
    'pdo' => 'Database abstraction',
    'pdo_mysql' => 'MySQL driver', 
    'json' => 'JSON encoding/decoding',
    'curl' => 'HTTP requests',
    'mbstring' => 'Multi-byte string support',
    'dom' => 'HTML/XML parsing',
    'session' => 'Session management',
    'filter' => 'Input validation',
    'fileinfo' => 'File type detection',
    'zip' => 'Archive support'
];

$missing = [];
$loaded = get_loaded_extensions();

echo "Checking PHP modules...\n\n";

foreach ($required as $module => $description) {
    if (in_array($module, $loaded)) {
        echo "✓ $module - $description\n";
    } else {
        echo "✗ $module - $description (MISSING)\n";
        $missing[] = $module;
    }
}

// Check optional modules
echo "\nOptional modules:\n";
$optional = ['redis' => 'Caching', 'pcntl' => 'Process control', 'posix' => 'Process signals'];
foreach ($optional as $module => $description) {
    if (in_array($module, $loaded)) {
        echo "✓ $module - $description\n";
    } else {
        echo "- $module - $description (not installed)\n";
    }
}

if (!empty($missing)) {
    echo "\nERROR: Missing required modules: " . implode(', ', $missing) . "\n";
    exit(1);
} else {
    echo "\nAll required modules are installed!\n";
    exit(0);
}