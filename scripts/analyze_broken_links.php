#!/usr/bin/env php
<?php
/**
 * Analyze and categorize broken links from the link checker results
 */

$resultsFile = __DIR__ . '/../link_check_incremental.json';
if (!file_exists($resultsFile)) {
    echo "No results file found. Run check_all_links.php first.\n";
    exit(1);
}

$data = json_decode(file_get_contents($resultsFile), true);

echo "=== YFEvents Broken Links Analysis ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Progress: " . $data['progress'] . "\n";
echo "Total Broken Links: " . $data['brokenLinks'] . "\n\n";

// Categorize errors
$categories = [
    'php_variables' => [
        'pattern' => '/<\?=|<\?php|\$\{/',
        'title' => 'PHP Variable Interpolation Issues',
        'description' => 'Links containing unresolved PHP variables'
    ],
    'removed_admin' => [
        'pattern' => '/admin\/(theme-config|system-status|users|permissions|logs|database|backup)\.php/',
        'title' => 'Removed Admin Pages',
        'description' => 'References to admin pages that were removed in cleanup'
    ],
    'refactor_paths' => [
        'pattern' => '/\/refactor\//',
        'title' => 'Old Refactor Paths',
        'description' => 'References to the old /refactor/ directory'
    ],
    'missing_modules' => [
        'pattern' => '/modules\/yfauth\/www\/(logout|profile|security|forgot-password)\.php/',
        'title' => 'Missing Module Pages',
        'description' => 'YFAuth module pages that don\'t exist yet'
    ],
    'relative_path_issues' => [
        'pattern' => '/\/scripts\/\.\./',
        'title' => 'Relative Path Issues',
        'description' => 'Links with incorrect relative paths'
    ],
    'api_endpoints' => [
        'pattern' => '/\/api\//',
        'title' => 'API Endpoints',
        'description' => 'API endpoints that may need implementation'
    ],
    'other' => [
        'pattern' => null,
        'title' => 'Other Issues',
        'description' => 'Miscellaneous broken links'
    ]
];

// Categorize each error
$categorized = [];
foreach ($categories as $key => $cat) {
    $categorized[$key] = [];
}

foreach ($data['errors'] as $error) {
    $url = $error['url'];
    $found = false;
    
    foreach ($categories as $key => $cat) {
        if ($cat['pattern'] && preg_match($cat['pattern'], $url)) {
            $categorized[$key][] = $error;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $categorized['other'][] = $error;
    }
}

// Print categorized results
foreach ($categories as $key => $cat) {
    if (empty($categorized[$key])) {
        continue;
    }
    
    echo "## " . $cat['title'] . " (" . count($categorized[$key]) . ")\n";
    echo $cat['description'] . "\n\n";
    
    // Group by source file
    $bySource = [];
    foreach ($categorized[$key] as $error) {
        foreach ($error['sources'] as $source) {
            if (!isset($bySource[$source])) {
                $bySource[$source] = [];
            }
            $bySource[$source][] = $error['url'];
        }
    }
    
    foreach ($bySource as $source => $urls) {
        echo "  In $source:\n";
        foreach (array_unique($urls) as $url) {
            // Clean up URL for display
            $displayUrl = str_replace('http://localhost', '', $url);
            $displayUrl = preg_replace('/\/scripts\/\.\./', '', $displayUrl);
            echo "    - $displayUrl\n";
        }
        echo "\n";
    }
}

// Generate fix recommendations
echo "\n## Recommended Fixes\n\n";

if (!empty($categorized['php_variables'])) {
    echo "### 1. Fix PHP Variable Interpolation\n";
    echo "Files with unresolved PHP variables in links:\n";
    $files = [];
    foreach ($categorized['php_variables'] as $error) {
        foreach ($error['sources'] as $source) {
            $files[$source] = true;
        }
    }
    foreach (array_keys($files) as $file) {
        echo "  - $file\n";
    }
    echo "\n";
}

if (!empty($categorized['removed_admin'])) {
    echo "### 2. Update Admin Links\n";
    echo "Remove or update references to deleted admin pages in:\n";
    $files = [];
    foreach ($categorized['removed_admin'] as $error) {
        foreach ($error['sources'] as $source) {
            $files[$source] = true;
        }
    }
    foreach (array_keys($files) as $file) {
        echo "  - $file\n";
    }
    echo "\n";
}

if (!empty($categorized['refactor_paths'])) {
    echo "### 3. Update Refactor Paths\n";
    echo "Replace /refactor/ with correct paths in:\n";
    $files = [];
    foreach ($categorized['refactor_paths'] as $error) {
        foreach ($error['sources'] as $source) {
            $files[$source] = true;
        }
    }
    foreach (array_keys($files) as $file) {
        echo "  - $file\n";
    }
    echo "\n";
}

// Save detailed report
$reportFile = __DIR__ . '/../broken_links_analysis_' . date('Y-m-d_His') . '.json';
file_put_contents($reportFile, json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => [
        'total_broken' => $data['brokenLinks'],
        'progress' => $data['progress'],
        'categories' => array_map('count', $categorized)
    ],
    'categorized' => $categorized,
    'recommendations' => [
        'php_variables' => array_keys($files ?? []),
        'removed_admin' => array_keys($files ?? []),
        'refactor_paths' => array_keys($files ?? [])
    ]
], JSON_PRETTY_PRINT));

echo "\nDetailed report saved to: " . basename($reportFile) . "\n";