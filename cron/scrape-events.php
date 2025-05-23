#!/usr/bin/env php
<?php

/**
 * Event Scraping Cron Job
 * Run daily to scrape events from configured sources
 * 
 * Usage: php /path/to/scrape-events.php
 * Cron: 0 2 * * * php /var/www/html/admin/cron/scrape-events.php
 */

// Set script execution limits
ini_set('max_execution_time', 1800); // 30 minutes
ini_set('memory_limit', '512M');

// Set timezone
date_default_timezone_set('America/Los_Angeles'); // Pacific Time for Yakima

// Include necessary files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Models/EventModel.php';
require_once __DIR__ . '/../src/Models/CalendarSourceModel.php';
require_once __DIR__ . '/../src/Scrapers/EventScraper.php';

use YakimaFinds\Models\EventModel;
use YakimaFinds\Models\CalendarSourceModel;
use YakimaFinds\Scrapers\EventScraper;

// Logging function
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    // Log to file
    $logFile = __DIR__ . '/logs/scraping-' . date('Y-m-d') . '.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

// Main execution
try {
    logMessage("Starting event scraping process");
    
    // Get database connection
    $db = getDatabaseConnection();
    logMessage("Database connection established");
    
    // Initialize scraper
    $scraper = new EventScraper($db);
    logMessage("Event scraper initialized");
    
    // Scrape all active sources
    $results = $scraper->scrapeAllSources();
    
    // Log results
    $totalEventsFound = 0;
    $totalEventsAdded = 0;
    $successfulSources = 0;
    $failedSources = 0;
    
    foreach ($results as $sourceId => $result) {
        if ($result['success']) {
            $successfulSources++;
            $totalEventsFound += $result['events_found'];
            $totalEventsAdded += $result['events_added'];
            
            logMessage("Source {$sourceId}: SUCCESS - Found {$result['events_found']} events, added {$result['events_added']}");
        } else {
            $failedSources++;
            logMessage("Source {$sourceId}: FAILED - {$result['error']}", 'ERROR');
        }
    }
    
    // Summary
    logMessage("Scraping completed successfully");
    logMessage("Sources processed: " . count($results));
    logMessage("Successful sources: {$successfulSources}");
    logMessage("Failed sources: {$failedSources}");
    logMessage("Total events found: {$totalEventsFound}");
    logMessage("Total events added: {$totalEventsAdded}");
    
    // Send notification email if configured
    if (shouldSendNotification($successfulSources, $failedSources, $totalEventsAdded)) {
        sendNotificationEmail($successfulSources, $failedSources, $totalEventsFound, $totalEventsAdded);
    }
    
    // Cleanup old logs (keep 30 days)
    cleanupOldLogs();
    
    exit(0); // Success
    
} catch (Exception $e) {
    logMessage("CRITICAL ERROR: " . $e->getMessage(), 'ERROR');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    
    // Send error notification
    sendErrorNotification($e);
    
    exit(1); // Error
}

/**
 * Determine if notification email should be sent
 */
function shouldSendNotification($successful, $failed, $eventsAdded) {
    // Send notification if:
    // - Any sources failed
    // - More than 10 new events were added
    // - It's Monday (weekly summary)
    
    return $failed > 0 || $eventsAdded > 10 || date('N') == 1;
}

/**
 * Send notification email about scraping results
 */
function sendNotificationEmail($successful, $failed, $eventsFound, $eventsAdded) {
    $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@yakimafinds.com';
    $fromEmail = $_ENV['FROM_EMAIL'] ?? 'calendar@yakimafinds.com';
    
    $subject = "Yakima Events Calendar - Daily Scraping Report";
    
    $message = "
    <html>
    <head>
        <title>Daily Scraping Report</title>
    </head>
    <body>
        <h2>Yakima Events Calendar - Daily Scraping Report</h2>
        <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
        
        <h3>Summary</h3>
        <ul>
            <li>Successful sources: {$successful}</li>
            <li>Failed sources: {$failed}</li>
            <li>Total events found: {$eventsFound}</li>
            <li>New events added: {$eventsAdded}</li>
        </ul>
        
        " . ($failed > 0 ? "<p style='color: red;'><strong>Warning:</strong> Some sources failed to scrape. Please check the admin dashboard.</p>" : "") . "
        
        <p>
            <a href='https://yakimafinds.com/admin/calendar/'>View Admin Dashboard</a> |
            <a href='https://yakimafinds.com/events'>View Public Calendar</a>
        </p>
        
        <p><em>This is an automated message from the Yakima Events Calendar system.</em></p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: {$fromEmail}" . "\r\n";
    
    mail($adminEmail, $subject, $message, $headers);
    logMessage("Notification email sent to {$adminEmail}");
}

/**
 * Send error notification email
 */
function sendErrorNotification($exception) {
    $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@yakimafinds.com';
    $fromEmail = $_ENV['FROM_EMAIL'] ?? 'calendar@yakimafinds.com';
    
    $subject = "URGENT: Yakima Events Calendar Scraping Failed";
    
    $message = "
    <html>
    <head>
        <title>Scraping Error Alert</title>
    </head>
    <body>
        <h2 style='color: red;'>URGENT: Event Scraping Failed</h2>
        <p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
        
        <h3>Error Details</h3>
        <p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>
        <p><strong>File:</strong> " . $exception->getFile() . "</p>
        <p><strong>Line:</strong> " . $exception->getLine() . "</p>
        
        <h3>Stack Trace</h3>
        <pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>
        
        <p>Please check the system logs and admin dashboard immediately.</p>
        
        <p>
            <a href='https://yakimafinds.com/admin/calendar/'>View Admin Dashboard</a>
        </p>
        
        <p><em>This is an automated error alert from the Yakima Events Calendar system.</em></p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: {$fromEmail}" . "\r\n";
    
    mail($adminEmail, $subject, $message, $headers);
    logMessage("Error notification email sent to {$adminEmail}");
}

/**
 * Clean up old log files
 */
function cleanupOldLogs() {
    $logDir = __DIR__ . '/logs/';
    if (!is_dir($logDir)) {
        return;
    }
    
    $files = glob($logDir . 'scraping-*.log');
    $cutoffDate = strtotime('-30 days');
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffDate) {
            unlink($file);
            logMessage("Deleted old log file: " . basename($file));
        }
    }
}

/**
 * Get database connection
 */
function getDatabaseConnection() {
    // Load environment variables if .env file exists
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && $line[0] !== '#') {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
    }
    
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'yakima_finds';
    $username = $_ENV['DB_USER'] ?? 'root';
    $password = $_ENV['DB_PASS'] ?? '';
    
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    
    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        return $pdo;
        
    } catch (PDOException $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}

?>