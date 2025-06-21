<?php

namespace YFEvents\Services;

use YFEvents\Utils\EnvLoader;

/**
 * Centralized authentication service for YFEvents
 */
class AuthService
{
    /**
     * Verify admin credentials
     */
    public static function verifyAdminCredentials(string $username, string $password): bool
    {
        // Load environment variables
        EnvLoader::load();
        
        // Get admin credentials from environment
        $adminUsername = EnvLoader::get('ADMIN_USERNAME', 'admin');
        $adminPasswordHash = EnvLoader::get('ADMIN_PASSWORD_HASH');
        
        // Check username
        if ($username !== $adminUsername) {
            return false;
        }
        
        // Verify password
        if ($adminPasswordHash) {
            // Use bcrypt verification if hash is provided
            return password_verify($password, $adminPasswordHash);
        } else {
            // Log warning - should not be used in production
            error_log('WARNING: ADMIN_PASSWORD_HASH not set in .env file. Admin authentication is disabled.');
            return false;
        }
    }
    
    /**
     * Start admin session
     */
    public static function startAdminSession(string $username): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Check if admin is logged in
     */
    public static function isAdminLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check basic login status
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            return false;
        }
        
        // Check session timeout (optional - 2 hours)
        $sessionTimeout = 7200; // 2 hours
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
            self::logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Logout admin
     */
    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Unset all session variables
        $_SESSION = array();
        
        // Destroy the session
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-42000, '/');
        }
        
        session_destroy();
    }
    
    /**
     * Generate password hash for .env file
     */
    public static function generatePasswordHash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Get database credentials from environment
     */
    public static function getDatabaseCredentials(): array
    {
        EnvLoader::load();
        
        return [
            'host' => EnvLoader::get('DB_HOST', 'localhost'),
            'name' => EnvLoader::get('DB_NAME', 'yakima_finds'),
            'user' => EnvLoader::get('DB_USER', 'root'),
            'password' => EnvLoader::get('DB_PASSWORD', ''),
        ];
    }
    
    /**
     * Get email credentials from environment
     */
    public static function getEmailCredentials(): array
    {
        EnvLoader::load();
        
        return [
            'host' => EnvLoader::get('SMTP_HOST', 'smtp.gmail.com'),
            'port' => (int)EnvLoader::get('SMTP_PORT', 587),
            'username' => EnvLoader::get('SMTP_USERNAME', ''),
            'password' => EnvLoader::get('SMTP_PASSWORD', ''),
            'from_email' => EnvLoader::get('SMTP_FROM_EMAIL', ''),
            'from_name' => EnvLoader::get('SMTP_FROM_NAME', 'YakimaFinds'),
        ];
    }
}