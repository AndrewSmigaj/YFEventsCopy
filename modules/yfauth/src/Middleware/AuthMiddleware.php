<?php

namespace YFEvents\Modules\YFAuth\Middleware;

use YFEvents\Modules\YFAuth\Services\SessionService;
use YFEvents\Modules\YFAuth\Services\JWTService;
use YFEvents\Modules\YFAuth\Services\RateLimitService;
use PDO;

/**
 * Authentication Middleware
 * Handles both session and JWT authentication
 */
class AuthMiddleware
{
    private PDO $db;
    private SessionService $sessionService;
    private JWTService $jwtService;
    private RateLimitService $rateLimiter;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->sessionService = new SessionService($db);
        $this->jwtService = new JWTService($db);
        $this->rateLimiter = new RateLimitService($db);
    }

    /**
     * Require authentication (session or JWT)
     */
    public function requireAuth(): array
    {
        // Try session authentication first
        $user = $this->sessionService->getCurrentUser();
        
        if ($user) {
            return [
                'authenticated' => true,
                'user' => $user,
                'method' => 'session'
            ];
        }

        // Try JWT authentication
        $token = $this->extractBearerToken();
        if ($token) {
            $payload = $this->jwtService->validateToken($token);
            if ($payload) {
                $user = \YFEvents\Modules\YFAuth\Models\User::find($this->db, $payload['sub']);
                if ($user && $user->is_active) {
                    return [
                        'authenticated' => true,
                        'user' => $user,
                        'method' => 'jwt',
                        'token_payload' => $payload
                    ];
                }
            }
        }

        // Try remember me cookie
        $user = $this->sessionService->checkRememberMe();
        if ($user) {
            return [
                'authenticated' => true,
                'user' => $user,
                'method' => 'remember_me'
            ];
        }

        return [
            'authenticated' => false,
            'error' => 'Authentication required'
        ];
    }

    /**
     * Require specific permission
     */
    public function requirePermission(string $permission): array
    {
        $auth = $this->requireAuth();
        
        if (!$auth['authenticated']) {
            return $auth;
        }

        $user = $auth['user'];
        if (!$user->hasPermission($permission)) {
            return [
                'authenticated' => true,
                'authorized' => false,
                'error' => "Permission '{$permission}' required"
            ];
        }

        return array_merge($auth, ['authorized' => true]);
    }

    /**
     * Require specific role
     */
    public function requireRole(string $role): array
    {
        $auth = $this->requireAuth();
        
        if (!$auth['authenticated']) {
            return $auth;
        }

        $user = $auth['user'];
        if (!$user->hasRole($role)) {
            return [
                'authenticated' => true,
                'authorized' => false,
                'error' => "Role '{$role}' required"
            ];
        }

        return array_merge($auth, ['authorized' => true]);
    }

    /**
     * Rate limiting middleware
     */
    public function rateLimit(string $action, int $maxAttempts = 60, int $windowSeconds = 3600): array
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "{$action}:{$ipAddress}";

        if (!$this->rateLimiter->attempt($key, $maxAttempts, $windowSeconds)) {
            return [
                'rate_limited' => true,
                'error' => 'Rate limit exceeded',
                'retry_after' => $this->rateLimiter->timeUntilReset($key, $windowSeconds)
            ];
        }

        return [
            'rate_limited' => false,
            'remaining_attempts' => $this->rateLimiter->remainingAttempts($key, $maxAttempts, $windowSeconds)
        ];
    }

    /**
     * CSRF protection middleware
     */
    public function requireCSRF(): array
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return ['csrf_valid' => true]; // GET requests don't need CSRF
        }

        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        
        if (!$token) {
            return [
                'csrf_valid' => false,
                'error' => 'CSRF token missing'
            ];
        }

        if (!$this->sessionService->validateCSRFToken($token)) {
            return [
                'csrf_valid' => false,
                'error' => 'Invalid CSRF token'
            ];
        }

        return ['csrf_valid' => true];
    }

    /**
     * Security headers middleware
     */
    public function addSecurityHeaders(): void
    {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy (basic)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
        
        // Strict Transport Security (if HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * API Authentication wrapper
     */
    public function apiAuth(callable $callback, array $requiredPermissions = []): void
    {
        try {
            // Add security headers
            $this->addSecurityHeaders();
            
            // Set JSON content type
            header('Content-Type: application/json');
            
            // Rate limiting
            $rateLimit = $this->rateLimit('api_request', 100, 3600);
            if ($rateLimit['rate_limited']) {
                http_response_code(429);
                echo json_encode([
                    'error' => $rateLimit['error'],
                    'retry_after' => $rateLimit['retry_after']
                ]);
                return;
            }

            // Authentication
            $auth = $this->requireAuth();
            if (!$auth['authenticated']) {
                http_response_code(401);
                echo json_encode(['error' => $auth['error']]);
                return;
            }

            // Permission check
            foreach ($requiredPermissions as $permission) {
                if (!$auth['user']->hasPermission($permission)) {
                    http_response_code(403);
                    echo json_encode(['error' => "Permission '{$permission}' required"]);
                    return;
                }
            }

            // CSRF check for non-GET requests
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                $csrf = $this->requireCSRF();
                if (!$csrf['csrf_valid']) {
                    http_response_code(403);
                    echo json_encode(['error' => $csrf['error']]);
                    return;
                }
            }

            // Call the protected endpoint
            $callback($auth['user'], $auth);

        } catch (\Exception $e) {
            error_log("API Auth error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    /**
     * Admin panel authentication wrapper
     */
    public function adminAuth(callable $callback, array $requiredPermissions = []): void
    {
        try {
            // Add security headers
            $this->addSecurityHeaders();
            
            // Rate limiting
            $rateLimit = $this->rateLimit('admin_access', 30, 3600);
            if ($rateLimit['rate_limited']) {
                http_response_code(429);
                include __DIR__ . '/../templates/error.php';
                return;
            }

            // Authentication
            $auth = $this->requireAuth();
            if (!$auth['authenticated']) {
                header('Location: /modules/yfauth/www/admin/login.php');
                return;
            }

            // Permission check
            foreach ($requiredPermissions as $permission) {
                if (!$auth['user']->hasPermission($permission)) {
                    http_response_code(403);
                    include __DIR__ . '/../templates/forbidden.php';
                    return;
                }
            }

            // Call the protected page
            $callback($auth['user'], $auth);

        } catch (\Exception $e) {
            error_log("Admin Auth error: " . $e->getMessage());
            http_response_code(500);
            include __DIR__ . '/../templates/error.php';
        }
    }

    /**
     * Extract Bearer token from Authorization header
     */
    private function extractBearerToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Generate CSRF token for forms
     */
    public function getCSRFToken(): string
    {
        return $this->sessionService->generateCSRFToken();
    }

    /**
     * Generate CSRF hidden input field
     */
    public function csrfField(): string
    {
        $token = $this->getCSRFToken();
        return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
    }
}