<?php

namespace YFEvents\Utils;

/**
 * Simple environment variable loader
 */
class EnvLoader
{
    private static array $env = [];
    private static bool $loaded = false;
    
    /**
     * Load environment variables from .env file
     */
    public static function load(string $path = null): void
    {
        if (self::$loaded) {
            return;
        }
        
        $envFile = $path ?? dirname(__DIR__, 2) . '/.env';
        
        if (!file_exists($envFile)) {
            // Try to find .env in parent directories
            $dir = dirname(__DIR__, 2);
            while ($dir !== '/' && !file_exists($envFile = $dir . '/.env')) {
                $dir = dirname($dir);
            }
        }
        
        if (!file_exists($envFile)) {
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                self::$env[$key] = $value;
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable
     */
    public static function get(string $key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
    
    /**
     * Check if environment variable exists
     */
    public static function has(string $key): bool
    {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$env[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
    }
}