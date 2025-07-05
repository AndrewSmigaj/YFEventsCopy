#!/usr/bin/env php
<?php
/**
 * Fix the most critical broken links identified in the analysis
 */

echo "=== YFEvents Broken Links Fixer ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$fixes = [];

// 1. Fix removed admin page references in admin dashboard
$file = __DIR__ . '/../www/html/admin/dashboard.php';
if (file_exists($file)) {
    echo "Fixing admin dashboard links...\n";
    $content = file_get_contents($file);
    
    // Comment out or remove broken admin links
    $replacements = [
        // Users & Permissions - these were never implemented
        '<li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>' 
            => '<!-- <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li> -->',
        '<li><a href="permissions.php"><i class="fas fa-key"></i> Permissions</a></li>' 
            => '<!-- <li><a href="permissions.php"><i class="fas fa-key"></i> Permissions</a></li> -->',
        
        // System tools that were removed
        '<li><a href="system-status.php"><i class="fas fa-server"></i> System Status</a></li>' 
            => '<!-- <li><a href="system-status.php"><i class="fas fa-server"></i> System Status</a></li> -->',
        '<li><a href="logs.php"><i class="fas fa-file-alt"></i> Logs</a></li>' 
            => '<!-- <li><a href="logs.php"><i class="fas fa-file-alt"></i> Logs</a></li> -->',
        '<li><a href="database.php"><i class="fas fa-database"></i> Database</a></li>' 
            => '<!-- <li><a href="database.php"><i class="fas fa-database"></i> Database</a></li> -->',
        '<li><a href="backup.php"><i class="fas fa-save"></i> Backup</a></li>' 
            => '<!-- <li><a href="backup.php"><i class="fas fa-save"></i> Backup</a></li> -->',
        
        // Theme config moved
        '<li><a href="theme-config.php"><i class="fas fa-palette"></i> Theme Config</a></li>' 
            => '<!-- <li><a href="theme-config.php"><i class="fas fa-palette"></i> Theme Config</a></li> -->',
        
        // Fix relative paths to modules
        '<li><a href="../modules/yfclaim/www/admin/"><i class="fas fa-gavel"></i> YFClaim Admin</a></li>'
            => '<li><a href="/modules/yfclaim/admin/"><i class="fas fa-gavel"></i> YFClaim Admin</a></li>',
        '<li><a href="../modules/yfauth/www/admin/"><i class="fas fa-lock"></i> YFAuth Admin</a></li>'
            => '<!-- <li><a href="../modules/yfauth/www/admin/"><i class="fas fa-lock"></i> YFAuth Admin</a></li> -->',
        '<li><a href="../modules/yftheme/www/admin/"><i class="fas fa-paint-brush"></i> YFTheme Admin</a></li>'
            => '<!-- <li><a href="../modules/yftheme/www/admin/"><i class="fas fa-paint-brush"></i> YFTheme Admin</a></li> -->',
        
        // Fix refactor path
        '<li><a href="../refactor/"><i class="fas fa-code"></i> New Interface</a></li>'
            => '<li><a href="/"><i class="fas fa-home"></i> Main Site</a></li>',
        
        // Fix css diagnostic path
        '<li><a href="../css-diagnostic.php"><i class="fas fa-bug"></i> CSS Diagnostic</a></li>'
            => '<li><a href="/css-diagnostic.php"><i class="fas fa-bug"></i> CSS Diagnostic</a></li>',
    ];
    
    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            $content = str_replace($old, $new, $content);
            $fixes[] = "Updated link in admin dashboard: " . substr($old, 0, 50) . "...";
        }
    }
    
    file_put_contents($file, $content);
}

// 2. Fix YFAuth module dashboard refactor links
$file = __DIR__ . '/../modules/yfauth/www/dashboard.php';
if (file_exists($file)) {
    echo "\nFixing YFAuth dashboard links...\n";
    $content = file_get_contents($file);
    
    $replacements = [
        '<a href="/refactor/">Main Site</a>' => '<a href="/">Main Site</a>',
        '<a href="/refactor/shops">Shop Directory</a>' => '<a href="/calendar.php#shops">Shop Directory</a>',
        'href="/refactor/"' => 'href="/"',
        'href="/refactor/shops"' => 'href="/calendar.php#shops"',
    ];
    
    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            $content = str_replace($old, $new, $content);
            $fixes[] = "Updated refactor link in YFAuth dashboard";
        }
    }
    
    file_put_contents($file, $content);
}

// 3. Fix YFAuth index.php refactor links
$file = __DIR__ . '/../modules/yfauth/www/index.php';
if (file_exists($file)) {
    echo "\nFixing YFAuth index links...\n";
    $content = file_get_contents($file);
    
    $replacements = [
        '<a href="/refactor/">Calendar & Events</a>' => '<a href="/">Calendar & Events</a>',
        '<a href="/refactor/admin/">Admin Panel</a>' => '<a href="/admin/">Admin Panel</a>',
        '<a href="/refactor/shops/">Shop Directory</a>' => '<a href="/calendar.php#shops">Shop Directory</a>',
        '<a href="/refactor/shops/submit/">Submit a Shop</a>' => '<a href="/shops/submit">Submit a Shop</a>',
    ];
    
    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            $content = str_replace($old, $new, $content);
            $fixes[] = "Updated refactor link in YFAuth index";
        }
    }
    
    file_put_contents($file, $content);
}

// 4. Fix theme config references
$files = [
    __DIR__ . '/../public/css-diagnostic.php',
    __DIR__ . '/../modules/yftheme/install.php',
    __DIR__ . '/../modules/yftheme/www/index.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "\nFixing theme config link in " . basename($file) . "...\n";
        $content = file_get_contents($file);
        
        // Comment out theme-config.php links
        $content = str_replace(
            '<a href="/admin/theme-config.php">',
            '<!-- <a href="/admin/theme-config.php"> --><!-- Theme config removed --><!-- ',
            $content
        );
        $content = str_replace(
            'href="/admin/theme-config.php"',
            'href="#" title="Theme config has been removed"',
            $content
        );
        
        file_put_contents($file, $content);
        $fixes[] = "Fixed theme config link in " . basename($file);
    }
}

// 5. Fix YFClaim admin login paths
$adminFiles = glob(__DIR__ . '/../modules/yfclaim/www/admin/*.php');
foreach ($adminFiles as $file) {
    $content = file_get_contents($file);
    $changed = false;
    
    // Fix various login paths
    $replacements = [
        '../../../www/html/admin/login.php' => '/admin/login',
        '/www/html/admin/login.php' => '/admin/login',
        '/admin/login.php' => '/admin/login',
    ];
    
    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            $content = str_replace($old, $new, $content);
            $changed = true;
        }
    }
    
    if ($changed) {
        file_put_contents($file, $content);
        $fixes[] = "Fixed login paths in " . basename($file);
    }
}

// 6. Remove references to offers.php (from old bidding system)
$files = [
    __DIR__ . '/../www/html/yfclaim-admin.php',
    __DIR__ . '/../modules/yfclaim/www/admin/buyers.php',
    __DIR__ . '/../modules/yfclaim/www/admin/reports.php',
    __DIR__ . '/../modules/yfclaim/www/admin/sales.php',
    __DIR__ . '/../modules/yfclaim/www/admin/sellers.php',
    __DIR__ . '/../modules/yfclaim/www/admin/index.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $changed = false;
        
        // Comment out offers.php links
        if (strpos($content, 'offers.php') !== false) {
            $content = preg_replace(
                '/<li><a href="[^"]*offers\.php[^"]*"[^>]*>.*?<\/a><\/li>/s',
                '<!-- Offers system removed -->',
                $content
            );
            $changed = true;
        }
        
        if ($changed) {
            file_put_contents($file, $content);
            $fixes[] = "Removed offers.php references from " . basename($file);
        }
    }
}

// 7. Fix missing YFClaim files
$file = __DIR__ . '/../modules/yfclaim/www/admin/index.php';
if (file_exists($file)) {
    $content = file_get_contents($file);
    
    // Change inquiries.php to contact management (future feature)
    $content = str_replace(
        '<li><a href="inquiries.php">Inquiries</a></li>',
        '<li><a href="#" title="Contact system coming soon">Inquiries (Coming Soon)</a></li>',
        $content
    );
    
    file_put_contents($file, $content);
    $fixes[] = "Updated inquiries link to coming soon";
}

// 8. Fix seller dashboard paths
$files = glob(__DIR__ . '/../modules/yfclaim/www/dashboard/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    $changed = false;
    
    $replacements = [
        'href="/seller/dashboard"' => 'href="/modules/yfclaim/seller-dashboard"',
        'href="/seller/sales"' => 'href="/modules/yfclaim/seller-dashboard?section=sales"',
        'href="/seller/sale/new"' => 'href="/modules/yfclaim/seller-dashboard?section=create-sale"',
        'href="/seller/sale/create"' => 'href="/modules/yfclaim/seller-dashboard?section=create-sale"',
    ];
    
    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            $content = str_replace($old, $new, $content);
            $changed = true;
        }
    }
    
    if ($changed) {
        file_put_contents($file, $content);
        $fixes[] = "Fixed seller dashboard paths in " . basename($file);
    }
}

// Summary
echo "\n=== Summary ===\n";
echo "Total fixes applied: " . count($fixes) . "\n\n";

foreach ($fixes as $fix) {
    echo "✓ $fix\n";
}

echo "\nBroken links have been fixed!\n";
echo "\nNote: Some issues require manual intervention:\n";
echo "- PHP variable interpolation in templates needs proper base path setup\n";
echo "- Missing YFAuth pages (logout.php, profile.php, etc.) need to be created\n";
echo "- API endpoint /api/communication/sync needs implementation\n";