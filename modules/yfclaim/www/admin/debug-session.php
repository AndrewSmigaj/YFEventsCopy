<?php
session_start();

echo "<h1>Session Debug</h1>";
echo "<h2>Current Session Data:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Session Configuration:</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session name: " . session_name() . "<br>";
echo "Session save path: " . session_save_path() . "<br>";
echo "Session cookie path: " . ini_get('session.cookie_path') . "<br>";
echo "Session cookie domain: " . ini_get('session.cookie_domain') . "<br>";

echo "<h2>Check Admin Login Status:</h2>";
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    echo "<span style='color: green;'>✓ Admin is logged in</span><br>";
    echo "Username: " . ($_SESSION['admin_username'] ?? 'Not set') . "<br>";
    echo "Login time: " . ($_SESSION['login_time'] ?? 'Not set') . "<br>";
} else {
    echo "<span style='color: red;'>✗ Admin is NOT logged in</span><br>";
}

echo "<h2>Actions:</h2>";
echo "<a href='/admin/login'>Go to Main Admin Login</a><br>";
echo "<a href='/modules/yfclaim/www/admin/sellers.php'>Test Sellers Page</a><br>";

// Set admin session for testing
if (isset($_GET['set_admin'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'YakFind';
    $_SESSION['login_time'] = time();
    echo "<br><span style='color: blue;'>Admin session set for testing!</span>";
    echo "<meta http-equiv='refresh' content='2'>";
}

echo "<br><br><a href='?set_admin=1'>Set Admin Session (for testing)</a>";
?>