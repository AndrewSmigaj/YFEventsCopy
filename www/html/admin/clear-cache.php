<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Unauthorized');
}

// Clear opcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully.<br>";
} else {
    echo "OPcache is not available.<br>";
}

// Clear any file stat cache
clearstatcache(true);
echo "File stat cache cleared.<br>";

// Also try to clear realpath cache
if (function_exists('realpath_cache_size')) {
    echo "Realpath cache size before: " . realpath_cache_size() . " bytes<br>";
}

echo "<br>Cache clearing complete. <a href='/admin/'>Return to admin</a>";
?>