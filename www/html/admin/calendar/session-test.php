<?php
session_start();

// Debug session info
echo "<!DOCTYPE html>\n";
echo "<html><head><title>Session Test</title></head><body>\n";
echo "<h1>Session Debug Info</h1>\n";
echo "<pre>\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Admin Logged In: " . (isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_logged_in'] ? 'YES' : 'NO') : 'NOT SET') . "\n";
echo "\nAll Session Variables:\n";
print_r($_SESSION);
echo "</pre>\n";
echo "<p><a href='./index.php'>Back to Advanced Admin</a></p>\n";
echo "</body></html>\n";
?>