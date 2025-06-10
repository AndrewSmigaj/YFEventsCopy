<?php
// Advanced error handling and logging for admin pages

// Create logs directory if it doesn't exist
$logDir = dirname(__DIR__, 3) . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $logDir . '/admin_errors.log');

// Custom error handler
function adminErrorHandler($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED'
    ];
    
    $type = isset($errorTypes[$errno]) ? $errorTypes[$errno] : 'UNKNOWN';
    
    // Log to file
    $logMessage = sprintf(
        "[%s] %s: %s in %s on line %d | URL: %s | IP: %s | User-Agent: %s\n",
        date('Y-m-d H:i:s'),
        $type,
        $errstr,
        $errfile,
        $errline,
        $_SERVER['REQUEST_URI'] ?? 'CLI',
        $_SERVER['REMOTE_ADDR'] ?? 'CLI',
        $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
    );
    
    $logFile = dirname(__DIR__, 3) . '/logs/admin_errors.log';
    error_log($logMessage, 3, $logFile);
    
    // For fatal errors, show user-friendly error page
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send 500 header
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }
        
        // Show error page
        include dirname(__DIR__) . '/error_500.php';
        exit;
    }
    
    // Don't execute PHP internal error handler
    return true;
}

// Set error handler
set_error_handler('adminErrorHandler');

// Exception handler
function adminExceptionHandler($exception) {
    $logMessage = sprintf(
        "[%s] EXCEPTION: %s in %s on line %d\nStack trace:\n%s\nURL: %s | IP: %s\n\n",
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString(),
        $_SERVER['REQUEST_URI'] ?? 'CLI',
        $_SERVER['REMOTE_ADDR'] ?? 'CLI'
    );
    
    $logFile = dirname(__DIR__, 3) . '/logs/admin_errors.log';
    error_log($logMessage, 3, $logFile);
    
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Send 500 header
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
    }
    
    // Show error page
    include dirname(__DIR__) . '/error_500.php';
    exit;
}

set_exception_handler('adminExceptionHandler');

// Shutdown handler for fatal errors
function adminShutdownHandler() {
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        adminErrorHandler($error['type'], $error['message'], $error['file'], $error['line']);
    }
}

register_shutdown_function('adminShutdownHandler');