<?php
// Temporary test access page for advanced admin
session_start();

// Check for temporary access token
$bypassToken = $_GET['token'] ?? '';
if ($bypassToken === 'YakFind2025Admin') {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_bypass'] = true;
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>Admin Access Granted</title></head><body>\n";
    echo "<h1>Temporary Admin Access Granted</h1>\n";
    echo "<p>You now have admin access for this session.</p>\n";
    echo "<p><a href='./index.php'>Go to Advanced Admin Dashboard</a></p>\n";
    echo "</body></html>\n";
} else {
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>Access Test</title></head><body>\n";
    echo "<h1>Advanced Admin Access Test</h1>\n";
    echo "<p>To test the advanced admin without login, use:</p>\n";
    echo "<code>" . $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?token=YakFind2025Admin</code>\n";
    echo "</body></html>\n";
}
?>