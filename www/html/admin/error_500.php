<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Internal Server Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
            text-align: center;
        }
        .error-code {
            font-size: 72px;
            font-weight: bold;
            color: #dc3545;
            margin: 0;
        }
        .error-title {
            font-size: 24px;
            color: #333;
            margin: 20px 0;
        }
        .error-message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .error-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            text-align: left;
            font-family: monospace;
            font-size: 12px;
            color: #666;
            margin: 20px 0;
            display: none;
        }
        .show-details {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        .show-details:hover {
            text-decoration: underline;
        }
        .back-button {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .back-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">500</div>
        <div class="error-title">Internal Server Error</div>
        <div class="error-message">
            <p>Something went wrong while processing your request. Our team has been notified and is working to fix the issue.</p>
            <p>Please try again later or contact support if the problem persists.</p>
        </div>
        
        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
        <a href="#" class="show-details" onclick="document.getElementById('error-details').style.display='block'; this.style.display='none'; return false;">Show technical details</a>
        
        <div id="error-details" class="error-details">
            <strong>Request Details:</strong><br>
            URL: <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'Unknown') ?><br>
            Time: <?= date('Y-m-d H:i:s') ?><br>
            IP: <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown') ?><br>
            <br>
            <strong>Error Log Location:</strong><br>
            /home/robug/YFEvents/logs/admin_errors.log<br>
            <br>
            <strong>Recent Errors:</strong><br>
            <?php
            $logFile = dirname(__DIR__, 2) . '/logs/admin_errors.log';
            if (file_exists($logFile)) {
                $lines = array_slice(file($logFile), -10);
                foreach ($lines as $line) {
                    echo htmlspecialchars($line) . "<br>";
                }
            } else {
                echo "No error log found.";
            }
            ?>
        </div>
        <?php endif; ?>
        
        <a href="./" class="back-button">Back to Admin Dashboard</a>
    </div>
</body>
</html>