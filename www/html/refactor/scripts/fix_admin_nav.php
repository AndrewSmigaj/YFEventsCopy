<?php

// Fix navigation in all admin pages

$adminPages = [
    'shops.php',
    'claims.php', 
    'scrapers.php',
    'users.php',
    'settings.php',
    'theme.php',
];

foreach ($adminPages as $page) {
    $file = "/home/robug/YFEvents/www/html/refactor/admin/$page";
    
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    
    // Find the navigation section and add Email Events and Email Config after Scrapers
    $pattern = '/<a href="<\?= \$basePath \?>\/admin\/scrapers\.php">Scrapers<\/a>\s*<a href="<\?= \$basePath \?>\/admin\/users\.php">Users<\/a>/';
    
    $replacement = '<a href="<?= $basePath ?>/admin/scrapers.php">Scrapers</a>
                <a href="<?= $basePath ?>/admin/email-events.php">Email Events</a>
                <a href="<?= $basePath ?>/admin/email-config.php">Email Config</a>
                <a href="<?= $basePath ?>/admin/users.php">Users</a>';
    
    $newContent = preg_replace($pattern, $replacement, $content, 1, $count);
    
    if ($count > 0) {
        file_put_contents($file, $newContent);
        echo "✓ Updated: $page\n";
    } else {
        echo "✗ No match found in: $page\n";
    }
}

echo "\nDone!\n";