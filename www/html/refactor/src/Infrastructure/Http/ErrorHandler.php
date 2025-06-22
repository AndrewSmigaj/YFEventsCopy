<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Http;

/**
 * Centralized error handling for HTTP responses
 */
class ErrorHandler
{
    /**
     * Generate a 404 Not Found response
     */
    public static function handle404(string $path, string $method, bool $isApiRequest = null): void
    {
        http_response_code(404);

        if (self::isApiRequest($isApiRequest)) {
            self::sendJsonError(404, 'Route not found', [
                'path' => $path,
                'method' => $method
            ]);
        } else {
            self::sendHtmlError(404, 'Page Not Found', 
                "The page you're looking for doesn't exist.",
                $path
            );
        }
    }

    /**
     * Generate a 405 Method Not Allowed response
     */
    public static function handle405(string $path, string $method, array $allowedMethods, bool $isApiRequest = null): void
    {
        http_response_code(405);
        header('Allow: ' . implode(', ', array_unique($allowedMethods)));

        if (self::isApiRequest($isApiRequest)) {
            self::sendJsonError(405, 'Method not allowed', [
                'path' => $path,
                'method' => $method,
                'allowed_methods' => array_unique($allowedMethods)
            ]);
        } else {
            self::sendHtmlError(405, 'Method Not Allowed', 
                "The {$method} method is not allowed for this resource. Allowed methods: " . implode(', ', $allowedMethods),
                $path
            );
        }
    }

    /**
     * Generate a 500 Internal Server Error response
     */
    public static function handle500(\Exception $e, bool $debug = false, bool $isApiRequest = null): void
    {
        http_response_code(500);

        $details = [];
        if ($debug) {
            $details = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }

        if (self::isApiRequest($isApiRequest)) {
            self::sendJsonError(500, 'Internal server error', $details);
        } else {
            $message = $debug ? $e->getMessage() : 'An unexpected error occurred. Please try again later.';
            self::sendHtmlError(500, 'Internal Server Error', $message);
        }
    }

    /**
     * Send JSON error response
     */
    private static function sendJsonError(int $statusCode, string $message, array $details = []): void
    {
        header('Content-Type: application/json');
        
        $response = [
            'error' => true,
            'message' => $message,
            'status_code' => $statusCode
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    /**
     * Send HTML error response
     */
    private static function sendHtmlError(int $statusCode, string $title, string $message, string $path = ''): void
    {
        header('Content-Type: text/html; charset=utf-8');
        
        $html = self::generateErrorPage($statusCode, $title, $message, $path);
        echo $html;
    }

    /**
     * Determine if request expects JSON response
     */
    private static function isApiRequest(?bool $override = null): bool
    {
        if ($override !== null) {
            return $override;
        }

        // Check if it's an API route
        $path = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($path, '/api/') !== false) {
            return true;
        }

        // Check Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }

        // Check Content-Type for JSON requests
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Generate HTML error page
     */
    private static function generateErrorPage(int $statusCode, string $title, string $message, string $path = ''): string
    {
        $homeUrl = self::getBaseUrl();
        $pathDisplay = self::getPathDisplay($path);
        $suggestions = self::getSuggestions($statusCode);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$statusCode} - {$title} | YFEvents</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 10px;
        }
        
        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 20px;
        }
        
        .error-message {
            color: #6c757d;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .error-path {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.9rem;
            color: #495057;
            margin-bottom: 30px;
            word-break: break-all;
        }
        
        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .suggestions {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e9ecef;
            text-align: left;
        }
        
        .suggestions h3 {
            color: #343a40;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .suggestions ul {
            color: #6c757d;
            padding-left: 20px;
        }
        
        .suggestions li {
            margin-bottom: 8px;
        }
        
        .suggestions a {
            color: #007bff;
            text-decoration: none;
        }
        
        .suggestions a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">{$statusCode}</div>
        <div class="error-title">{$title}</div>
        <div class="error-message">{$message}</div>
        
        {$pathDisplay}
        
        <div class="error-actions">
            <a href="{$homeUrl}" class="btn btn-primary">🏠 Go Home</a>
            <a href="javascript:history.back()" class="btn btn-secondary">← Go Back</a>
        </div>
        
        {$suggestions}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get path display HTML
     */
    private static function getPathDisplay(string $path): string
    {
        if (empty($path)) {
            return '';
        }

        return "<div class=\"error-path\">Requested: {$path}</div>";
    }

    /**
     * Get suggestions based on error code
     */
    private static function getSuggestions(int $statusCode): string
    {
        $baseUrl = self::getBaseUrl();
        
        switch ($statusCode) {
            case 404:
                return <<<HTML
<div class="suggestions">
    <h3>🔍 You might be looking for:</h3>
    <ul>
        <li><a href="{$baseUrl}/events">Browse Events</a></li>
        <li><a href="{$baseUrl}/shops">Local Shops</a></li>
        <li><a href="{$baseUrl}/claims">Estate Sales</a></li>
        <li><a href="{$baseUrl}/api/events">Events API</a></li>
    </ul>
</div>
HTML;
            
            case 405:
                return <<<HTML
<div class="suggestions">
    <h3>💡 Common API endpoints:</h3>
    <ul>
        <li><strong>GET</strong> <a href="{$baseUrl}/api/events">Events API</a></li>
        <li><strong>GET</strong> <a href="{$baseUrl}/api/shops">Shops API</a></li>
        <li><strong>GET</strong> <a href="{$baseUrl}/api/health">Health Check</a></li>
    </ul>
</div>
HTML;
            
            default:
                return <<<HTML
<div class="suggestions">
    <h3>🛠️ Try these instead:</h3>
    <ul>
        <li><a href="{$baseUrl}">Home Page</a></li>
        <li><a href="{$baseUrl}/api/health">System Health</a></li>
    </ul>
</div>
HTML;
        }
    }

    /**
     * Get base URL for the application
     */
    private static function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        
        if ($basePath === '/' || $basePath === '.') {
            $basePath = '';
        }
        
        return "{$protocol}://{$host}{$basePath}";
    }
}