<?php
/**
 * Path Helper Class
 * Centralizes URL and path generation for easy migration
 */

namespace YFEvents\Helpers;

class PathHelper
{
    private static $config = null;
    
    /**
     * Initialize configuration
     */
    private static function init()
    {
        if (self::$config === null) {
            // Load configuration
            $configFile = dirname(dirname(__DIR__)) . '/config/app.php';
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            } else {
                // Fallback configuration
                self::$config = [
                    'base_path' => '',
                    'admin_path' => '/admin',
                    'api_path' => '/api',
                    'assets_path' => '/assets',
                    'modules_path' => '/modules',
                    'communication_path' => '/communication'
                ];
            }
        }
    }
    
    /**
     * Get base path
     */
    public static function getBasePath()
    {
        self::init();
        return self::$config['base_path'] ?? '';
    }
    
    /**
     * Generate URL relative to base
     */
    public static function url($path = '')
    {
        self::init();
        $basePath = self::$config['base_path'] ?? '';
        $path = ltrim($path, '/');
        return $basePath . ($path ? '/' . $path : '');
    }
    
    /**
     * Generate admin URL
     */
    public static function adminUrl($path = '')
    {
        self::init();
        $adminPath = trim(self::$config['admin_path'] ?? '/admin', '/');
        $path = ltrim($path, '/');
        return self::url($adminPath . ($path ? '/' . $path : ''));
    }
    
    /**
     * Generate API URL
     */
    public static function apiUrl($path = '')
    {
        self::init();
        $apiPath = trim(self::$config['api_path'] ?? '/api', '/');
        $path = ltrim($path, '/');
        return self::url($apiPath . ($path ? '/' . $path : ''));
    }
    
    /**
     * Generate asset URL
     */
    public static function assetUrl($path = '')
    {
        self::init();
        $assetsPath = trim(self::$config['assets_path'] ?? '/assets', '/');
        $path = ltrim($path, '/');
        return self::url($assetsPath . ($path ? '/' . $path : ''));
    }
    
    /**
     * Generate module URL (absolute from site root)
     */
    public static function moduleUrl($path = '')
    {
        self::init();
        $modulesPath = self::$config['modules_path'] ?? '/modules';
        $path = ltrim($path, '/');
        return $modulesPath . ($path ? '/' . $path : '');
    }
    
    /**
     * Generate communication URL (absolute from site root)
     */
    public static function communicationUrl($path = '')
    {
        self::init();
        $commPath = self::$config['communication_path'] ?? '/communication';
        $path = ltrim($path, '/');
        return $commPath . ($path ? '/' . $path : '');
    }
    
    /**
     * Get configuration value
     */
    public static function config($key, $default = null)
    {
        self::init();
        return self::$config[$key] ?? $default;
    }
}