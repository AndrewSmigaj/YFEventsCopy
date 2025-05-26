<?php
// Test AJAX endpoint directly
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing Calendar AJAX Endpoint</h1>";

// Test 1: Direct database query
echo "<h2>1. Direct Database Test</h2>";
try {
    require_once dirname(dirname(__DIR__)) . '/config/database.php';
    
    $result = $pdo->query("SELECT COUNT(*) as count FROM events WHERE status = 'approved'");
    $count = $result->fetch();
    echo "<p>✓ Found {$count['count']} approved events in database</p>";
    
    // Show first few events
    $events = $pdo->query("SELECT id, title, start_datetime, location FROM events WHERE status = 'approved' LIMIT 3")->fetchAll();
    echo "<pre>" . print_r($events, true) . "</pre>";
} catch (Exception $e) {
    echo "<p>✗ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 2: Check AJAX endpoint directly
echo "<h2>2. AJAX Endpoint URL Test</h2>";
$baseUrl = "http://" . $_SERVER['HTTP_HOST'];
echo "<p>Testing: <a href='/ajax/calendar-events.php' target='_blank'>/ajax/calendar-events.php</a></p>";

// Test 3: Fetch with curl
echo "<h2>3. CURL Test of AJAX Endpoint</h2>";
$ch = curl_init($baseUrl . '/ajax/calendar-events.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

echo "<p>HTTP Code: $httpCode</p>";
echo "<p>Headers:</p><pre>" . htmlspecialchars($header) . "</pre>";
echo "<p>Response Body:</p><pre>" . htmlspecialchars($body) . "</pre>";

// Test 4: Check JavaScript errors
echo "<h2>4. JavaScript Console Test</h2>";
echo '<p>Open browser console (F12) and check for errors on the <a href="/calendar.php" target="_blank">calendar page</a></p>';

// Test 5: Check file paths
echo "<h2>5. File Path Verification</h2>";
$files = [
    '/ajax/calendar-events.php' => dirname(__DIR__) . '/ajax/calendar-events.php',
    '/js/calendar.js' => dirname(__DIR__) . '/js/calendar.js',
    '/css/calendar.css' => dirname(__DIR__) . '/css/calendar.css',
];

foreach ($files as $url => $path) {
    if (file_exists($path)) {
        echo "<p>✓ $url exists at $path</p>";
    } else {
        echo "<p>✗ $url NOT FOUND at $path</p>";
    }
}
?>