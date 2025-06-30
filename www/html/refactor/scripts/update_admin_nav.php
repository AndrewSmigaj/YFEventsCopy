<?php

// Update all admin pages to include Email Config link in navigation

$adminPages = [
    '/home/robug/YFEvents/www/html/refactor/admin/events.php',
    '/home/robug/YFEvents/www/html/refactor/admin/shops.php',
    '/home/robug/YFEvents/www/html/refactor/admin/claims.php',
    '/home/robug/YFEvents/www/html/refactor/admin/scrapers.php',
    '/home/robug/YFEvents/www/html/refactor/admin/users.php',
    '/home/robug/YFEvents/www/html/refactor/admin/settings.php',
    '/home/robug/YFEvents/www/html/refactor/admin/theme.php',
];

foreach ($adminPages as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Check if already has Email Config link
    if (strpos($content, 'admin/email-config.php') !== false) {
        echo "Already updated: $file\n";
        continue;
    }
    
    // Add Email Config link after Email Events
    $pattern = '/<a[^>]*href="[^"]*\/admin\/email-events\.php"[^>]*>Email Events<\/a>/';
    $replacement = '$0
                <a href="<?= $basePath ?>/admin/email-config.php">Email Config</a>';
    
    $newContent = preg_replace($pattern, $replacement, $content);
    
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Updated: $file\n";
    } else {
        echo "No changes: $file\n";
    }
}

echo "\nAll admin pages updated with Email Config link.\n";