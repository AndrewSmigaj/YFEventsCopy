<?php
// YFEvents Main Entry Point

// Check if database is configured
$envFile = __DIR__ . '/../../.env';
if (!file_exists($envFile)) {
    // Show setup page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>YFEvents - Setup Required</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .container { max-width: 600px; margin: 0 auto; }
            .warning { background: #fff3cd; padding: 20px; border-radius: 5px; border: 1px solid #ffeaa7; }
            .success { background: #d4edda; padding: 20px; border-radius: 5px; border: 1px solid #c3e6cb; }
            code { background: #f8f9fa; padding: 2px 5px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>YFEvents Installation</h1>
            
            <div class="success">
                <h2>✅ Web Server Running</h2>
                <p>PHP Version: <?php echo phpversion(); ?></p>
            </div>
            
            <div class="warning">
                <h2>⚠️ Configuration Required</h2>
                <p>The <code>.env</code> file is missing. Please create it from the template:</p>
                <pre>cp /home/robug/YFEvents/.env.example /home/robug/YFEvents/.env</pre>
                <p>Then update it with your database credentials.</p>
            </div>
            
            <p><a href="info.php">View PHP Info</a> | <a href="calendar.php">Try Calendar Anyway</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Redirect to calendar
header('Location: /calendar.php');
exit;
?>
