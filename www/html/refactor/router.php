<?php
// PHP built-in server router script

// Force all requests through our main index.php
// This prevents PHP's built-in server from serving files in subdirectories

// Get the request path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Allow direct access to test scripts only
if ($path === '/test_request.php') {
    require_once __DIR__ . '/test_request.php';
    return;
}

// Check if it's a static file (not PHP)
$file = __DIR__ . $path;
if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    // Only serve CSS, JS, images, etc.
    $allowed_extensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, $allowed_extensions)) {
        return false; // Let PHP serve the static file
    }
}

// Force everything else through index.php
// This includes all /admin/* routes
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php' . ($_SERVER['PATH_INFO'] ?? '');
require_once __DIR__ . '/index.php';