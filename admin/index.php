<?php
/**
 * Admin Interface Redirect
 * This directory contains an outdated admin interface with security vulnerabilities.
 * Redirecting to the secure admin panel.
 */

// Send proper redirect headers
header('HTTP/1.1 301 Moved Permanently');
header('Location: /admin/');

// Provide fallback message in case redirect fails
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Interface Moved</title>
    <meta http-equiv="refresh" content="0; url=/admin/">
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background: #f5f5f5;
        }
        .message {
            background: white;
            border: 1px solid #ddd;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        p { color: #666; margin: 20px 0; }
        a {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
            color: #856404;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="message">
        <h1>Admin Interface Moved</h1>
        <p>This admin interface has been deprecated for security reasons.</p>
        <p><a href="/admin/">Click here to access the secure admin panel</a></p>
        <div class="security-notice">
            <strong>Security Notice:</strong> The previous admin interface contained hardcoded credentials and has been replaced with a secure authentication system.
        </div>
    </div>
</body>
</html>