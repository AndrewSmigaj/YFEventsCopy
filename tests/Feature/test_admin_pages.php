<?php
// Test script for admin pages
session_start();

// Set up test authentication
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_user'] = [
    'id' => 1,
    'username' => 'test_admin',
    'email' => 'admin@test.com'
];

// Color codes for output
$GREEN = "\033[0;32m";
$RED = "\033[0;31m";
$YELLOW = "\033[0;33m";
$BLUE = "\033[0;34m";
$NC = "\033[0m"; // No Color

echo "\n{$BLUE}=== Testing Admin Pages ==={$NC}\n\n";

// Test pages
$pages = [
    '/admin/index.php' => 'Admin Dashboard',
    '/admin/events.php' => 'Events Management',
    '/admin/shops.php' => 'Shops Management',
    '/admin/users.php' => 'Users Management',
    '/admin/scrapers.php' => 'Scrapers Management',
];

$results = [];

foreach ($pages as $page => $name) {
    echo "{$YELLOW}Testing: {$name} ({$page}){$NC}\n";
    
    $result = [
        'name' => $name,
        'path' => $page,
        'tests' => []
    ];
    
    // Test 1: Check if file exists
    $fullPath = __DIR__ . $page;
    if (file_exists($fullPath)) {
        echo "  {$GREEN}✓{$NC} File exists\n";
        $result['tests']['file_exists'] = true;
        
        // Test 2: Check if file is readable
        if (is_readable($fullPath)) {
            echo "  {$GREEN}✓{$NC} File is readable\n";
            $result['tests']['readable'] = true;
            
            // Test 3: Check for PHP syntax errors
            $output = shell_exec("php -l {$fullPath} 2>&1");
            if (strpos($output, 'No syntax errors') !== false) {
                echo "  {$GREEN}✓{$NC} No PHP syntax errors\n";
                $result['tests']['syntax_valid'] = true;
            } else {
                echo "  {$RED}✗{$NC} PHP syntax error: " . trim($output) . "\n";
                $result['tests']['syntax_valid'] = false;
                $result['syntax_error'] = trim($output);
            }
            
            // Test 4: Check file content for basic structure
            $content = file_get_contents($fullPath);
            
            // Check for session authentication
            if (strpos($content, 'admin_logged_in') !== false || strpos($content, 'SESSION') !== false) {
                echo "  {$GREEN}✓{$NC} Has authentication check\n";
                $result['tests']['has_auth'] = true;
            } else {
                echo "  {$YELLOW}⚠{$NC} No authentication check found\n";
                $result['tests']['has_auth'] = false;
            }
            
            // Check for HTML structure
            if (strpos($content, '<!DOCTYPE html>') !== false || strpos($content, '<html') !== false) {
                echo "  {$GREEN}✓{$NC} Has HTML structure\n";
                $result['tests']['has_html'] = true;
            } else {
                echo "  {$YELLOW}⚠{$NC} No HTML structure found\n";
                $result['tests']['has_html'] = false;
            }
            
            // Check for JavaScript
            if (strpos($content, '<script') !== false) {
                echo "  {$GREEN}✓{$NC} Contains JavaScript\n";
                $result['tests']['has_js'] = true;
                
                // Count API calls
                preg_match_all('/fetch\s*\([\'"]([^\'"]+)[\'"]/i', $content, $apiMatches);
                if (!empty($apiMatches[1])) {
                    echo "  {$BLUE}ℹ{$NC} Found " . count($apiMatches[1]) . " API calls:\n";
                    foreach ($apiMatches[1] as $api) {
                        echo "    - {$api}\n";
                    }
                    $result['api_calls'] = $apiMatches[1];
                }
            } else {
                echo "  {$YELLOW}⚠{$NC} No JavaScript found\n";
                $result['tests']['has_js'] = false;
            }
            
            // Check for forms
            if (strpos($content, '<form') !== false) {
                echo "  {$GREEN}✓{$NC} Contains forms\n";
                $result['tests']['has_forms'] = true;
                
                // Count forms
                preg_match_all('/<form[^>]*>/i', $content, $formMatches);
                echo "  {$BLUE}ℹ{$NC} Found " . count($formMatches[0]) . " form(s)\n";
            } else {
                echo "  {$YELLOW}⚠{$NC} No forms found\n";
                $result['tests']['has_forms'] = false;
            }
            
            // Check for navigation links
            if (strpos($content, 'nav-links') !== false || strpos($content, '<nav') !== false) {
                echo "  {$GREEN}✓{$NC} Has navigation\n";
                $result['tests']['has_nav'] = true;
            } else {
                echo "  {$YELLOW}⚠{$NC} No navigation found\n";
                $result['tests']['has_nav'] = false;
            }
            
        } else {
            echo "  {$RED}✗{$NC} File is not readable\n";
            $result['tests']['readable'] = false;
        }
    } else {
        echo "  {$RED}✗{$NC} File does not exist\n";
        $result['tests']['file_exists'] = false;
    }
    
    $results[] = $result;
    echo "\n";
}

// Summary
echo "{$BLUE}=== Test Summary ==={$NC}\n\n";

$totalTests = 0;
$passedTests = 0;

foreach ($results as $result) {
    $pageTests = count($result['tests']);
    $pagePassed = count(array_filter($result['tests'], function($v) { return $v === true; }));
    $totalTests += $pageTests;
    $passedTests += $pagePassed;
    
    $status = $pagePassed === $pageTests ? "{$GREEN}PASS{$NC}" : 
              ($pagePassed > 0 ? "{$YELLOW}PARTIAL{$NC}" : "{$RED}FAIL{$NC}");
    
    echo sprintf("%-30s %s (%d/%d tests passed)\n", 
        $result['name'], 
        $status, 
        $pagePassed, 
        $pageTests
    );
}

echo "\n";
echo "Total: {$passedTests}/{$totalTests} tests passed ";
$percentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;
echo "({$percentage}%)\n";

// Save detailed results
file_put_contents('admin_test_results.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nDetailed results saved to admin_test_results.json\n";