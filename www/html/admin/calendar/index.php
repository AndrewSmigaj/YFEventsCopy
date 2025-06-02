<?php
// Safe Advanced Admin Dashboard with error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to start session with error handling
$sessionStarted = false;
$sessionError = '';

try {
    if (session_status() === PHP_SESSION_NONE) {
        $sessionStarted = @session_start();
        if (!$sessionStarted) {
            $sessionError = error_get_last()['message'] ?? 'Unknown session error';
        }
    } else {
        $sessionStarted = true;
    }
} catch (Exception $e) {
    $sessionError = $e->getMessage();
}

// Check admin authentication only if session started
$isAdmin = false;
if ($sessionStarted && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $isAdmin = true;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Admin - YFEvents</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; }
        .menu { display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap; }
        .menu a { padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .menu a:hover { background: #0056b3; }
        .info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Advanced Admin Dashboard</h1>
        
        <?php if (!$sessionStarted): ?>
            <div class="error">
                <strong>Session Error:</strong> <?= htmlspecialchars($sessionError) ?>
                <br>The admin features may not work properly without sessions.
            </div>
        <?php endif; ?>
        
        <?php if (!$isAdmin): ?>
            <div class="warning">
                <strong>Not Logged In:</strong> You are not logged in as admin. 
                <a href="/admin/login.php">Login here</a> or continue with limited access.
            </div>
        <?php endif; ?>
        
        <div class="info">
            <p>Welcome to the Advanced Admin interface for YFEvents.</p>
            <p>This interface provides enhanced management capabilities for events, sources, and shops.</p>
        </div>
        
        <div class="menu">
            <a href="/admin/calendar/events.php"><i class="fas fa-calendar"></i> Manage Events</a>
            <a href="/admin/calendar/sources.php"><i class="fas fa-rss"></i> Event Sources</a>
            <a href="/admin/calendar/shops.php"><i class="fas fa-store"></i> Local Shops</a>
            <a href="/admin/geocode-fix.php"><i class="fas fa-map-marker-alt"></i> Fix Geocoding</a>
            <a href="/admin/"><i class="fas fa-arrow-left"></i> Back to Main Admin</a>
        </div>
        
        <div class="info">
            <h3>System Status</h3>
            <ul>
                <li>PHP Version: <?= phpversion() ?></li>
                <li>Session Status: <?= $sessionStarted ? 'Working' : 'Failed' ?></li>
                <li>Admin Login: <?= $isAdmin ? 'Yes' : 'No' ?></li>
            </ul>
        </div>
        
        <div class="info">
            <h3>Features</h3>
            <ul>
                <li><strong>Events:</strong> Approve/reject pending events, edit details, manage categories</li>
                <li><strong>Sources:</strong> Configure event scrapers, test sources, view scraping logs</li>
                <li><strong>Shops:</strong> Manage local business directory, add photos, update information</li>
                <li><strong>Geocoding:</strong> Fix missing or incorrect location coordinates</li>
            </ul>
        </div>
    </div>
</body>
</html>