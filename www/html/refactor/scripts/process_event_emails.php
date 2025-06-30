<?php

declare(strict_types=1);

/**
 * Cron script to process incoming Facebook event emails
 * Run every 15 minutes with crontab entry:
 * 0,15,30,45 * * * * /usr/bin/php /path/to/process_event_emails.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use YakimaFinds\Infrastructure\Services\EmailEventProcessor;
use YakimaFinds\Infrastructure\Config\ConfigManager;

// Setup error logging
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/email_processing.log');

// Ensure logs directory exists
$logsDir = dirname(__DIR__) . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

function logMessage(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logFile = dirname(__DIR__) . '/logs/email_processing.log';
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

function setupDatabase(): PDO
{
    $config = require dirname(__DIR__) . '/config/database.php';
    $dbConfig = $config['database'];
    
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    
    return new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

try {
    logMessage("Starting email processing...");
    
    // Check if IMAP extension is loaded
    if (!extension_loaded('imap')) {
        throw new Exception('IMAP extension is not loaded. Please install php-imap.');
    }
    
    // Setup database connection
    $pdo = setupDatabase();
    
    // Load email configuration
    $emailConfig = require dirname(__DIR__) . '/config/email.php';
    
    // Create processor
    $processor = new EmailEventProcessor($pdo, $emailConfig);
    
    // Process emails
    $events = $processor->processIncomingEmails();
    
    $eventCount = count($events);
    logMessage("Processed {$eventCount} events from emails");
    
    if ($eventCount > 0) {
        foreach ($events as $event) {
            logMessage("Added event: {$event['title']} (ID: {$event['id']})");
        }
    }
    
    // Log statistics
    $stats = $processor->getProcessingStats();
    logMessage("Statistics: " . json_encode($stats));
    
    logMessage("Email processing completed successfully");

} catch (Exception $e) {
    $errorMessage = "Email processing failed: " . $e->getMessage();
    logMessage($errorMessage);
    
    // Also log to error_log
    error_log($errorMessage);
    
    // Exit with error code for cron monitoring
    exit(1);
}

// Optional: Clean up old log files (keep last 30 days)
$logFile = dirname(__DIR__) . '/logs/email_processing.log';
if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) { // 10MB
    // Rotate log file
    $backupFile = dirname(__DIR__) . '/logs/email_processing_' . date('Y-m-d') . '.log';
    rename($logFile, $backupFile);
    
    // Clean old backup files
    $logFiles = glob(dirname(__DIR__) . '/logs/email_processing_*.log');
    foreach ($logFiles as $file) {
        if (filemtime($file) < strtotime('-30 days')) {
            unlink($file);
        }
    }
}