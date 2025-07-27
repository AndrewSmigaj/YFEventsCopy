<?php

declare(strict_types=1);

namespace YFEvents\Application\Services;

use YFEvents\Modules\YFAuth\Services\AuthService as YFAuthService;
use PDO;
use RuntimeException;

/**
 * Unified authentication service wrapping YFAuth
 * Provides a simple interface for authentication across the application
 */
class AuthService
{
    private YFAuthService $yfAuthService;
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->yfAuthService = new YFAuthService($pdo);
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Authenticate user with username/email and password
     * 
     * @param string $username Username or email
     * @param string $password Plain text password
     * @return array ['success' => bool, 'user' => array|null, 'error' => string|null]
     */
    public function login(string $username, string $password): array
    {
        try {
            $result = $this->yfAuthService->authenticate($username, $password);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
        
        if ($result['success']) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Set standardized session variables
            $_SESSION['auth'] = [
                'user_id' => $result['user']['id'],
                'username' => $result['user']['username'],
                'email' => $result['user']['email'],
                'roles' => array_column($result['user']['roles'] ?? [], 'name'),
                'permissions' => $result['user']['permissions'] ?? [],
                'session_id' => $result['session_id'],
                'login_time' => time(),
                'last_activity' => time()
            ];
            
            // Last login is updated by YFAuth internally
        }
        
        return $result;
    }
    
    /**
     * Log out current user
     */
    public function logout(): void
    {
        // YFAuth doesn't have destroySession, just clear our session
        
        // Clear all auth session data
        unset($_SESSION['auth']);
        
        // Also clear any legacy session variables
        $legacyKeys = [
            'user_id', 'user_role', 'username',
            'yfclaim_seller_id', 'yfclaim_seller_name',
            'claim_seller_logged_in', 'claim_seller_id',
            'seller_email', 'seller_name', 'company_name'
        ];
        
        foreach ($legacyKeys as $key) {
            unset($_SESSION[$key]);
        }
        
        // Regenerate session ID
        session_regenerate_id(true);
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        if (!isset($_SESSION['auth']['user_id'])) {
            return false;
        }
        
        // Check session timeout (2 hours)
        if (isset($_SESSION['auth']['last_activity'])) {
            $timeout = 2 * 60 * 60; // 2 hours
            if (time() - $_SESSION['auth']['last_activity'] > $timeout) {
                $this->logout();
                return false;
            }
        }
        
        // Update last activity
        $_SESSION['auth']['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['auth']['user_id'],
            'username' => $_SESSION['auth']['username'],
            'email' => $_SESSION['auth']['email'],
            'roles' => $_SESSION['auth']['roles'],
            'permissions' => $_SESSION['auth']['permissions']
        ];
    }
    
    /**
     * Check if current user has a specific role
     */
    public function hasRole(string $role): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        return in_array($role, $_SESSION['auth']['roles'] ?? []);
    }
    
    /**
     * Check if current user has any of the specified roles
     */
    public function hasAnyRole(array $roles): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        return !empty(array_intersect($roles, $_SESSION['auth']['roles'] ?? []));
    }
    
    /**
     * Require user to have a specific role
     * Redirects to login if not authenticated or lacks role
     */
    public function requireRole(string $role, string $redirectTo = '/login'): void
    {
        if (!$this->isAuthenticated()) {
            header("Location: $redirectTo");
            exit;
        }
        
        if (!$this->hasRole($role)) {
            http_response_code(403);
            echo "Access denied. You do not have the required role: $role";
            exit;
        }
    }
    
    /**
     * Require user to be authenticated
     * Redirects to login if not authenticated
     */
    public function requireAuth(string $redirectTo = '/login'): void
    {
        if (!$this->isAuthenticated()) {
            // Store intended URL for redirect after login
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            header("Location: $redirectTo");
            exit;
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get redirect URL after login (and clear it)
     */
    public function getIntendedUrl(string $default = '/admin'): string
    {
        $url = $_SESSION['intended_url'] ?? $default;
        unset($_SESSION['intended_url']);
        return $url;
    }
}