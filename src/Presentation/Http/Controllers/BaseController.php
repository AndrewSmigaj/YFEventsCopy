<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;

/**
 * Base controller with common functionality
 */
abstract class BaseController
{
    public function __construct(
        protected ContainerInterface $container,
        protected ConfigInterface $config
    ) {}

    /**
     * Render a JSON response
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Render an error response
     */
    protected function errorResponse(string $message, int $statusCode = 400, array $details = []): void
    {
        $this->jsonResponse([
            'error' => true,
            'message' => $message,
            'details' => $details
        ], $statusCode);
    }

    /**
     * Render a success response
     */
    protected function successResponse(array $data = [], string $message = 'Success'): void
    {
        $this->jsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Get request input
     */
    protected function getInput(): array
    {
        $input = [];
        
        // GET parameters
        $input = array_merge($input, $_GET);
        
        // POST parameters
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = array_merge($input, $_POST);
            
            // JSON body
            $jsonInput = json_decode(file_get_contents('php://input'), true);
            if (is_array($jsonInput)) {
                $input = array_merge($input, $jsonInput);
            }
        }
        
        return $input;
    }

    /**
     * Validate required fields
     */
    protected function validateRequired(array $input, array $requiredFields): array
    {
        $missing = [];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /**
     * Get pagination parameters
     */
    protected function getPaginationParams(array $input): array
    {
        return [
            'page' => max(1, (int) ($input['page'] ?? 1)),
            'limit' => min(100, max(1, (int) ($input['limit'] ?? 20))),
            'offset' => ((int) ($input['page'] ?? 1) - 1) * min(100, max(1, (int) ($input['limit'] ?? 20)))
        ];
    }

    /**
     * Check if request is authenticated (basic session check)
     */
    protected function requireAuth(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) || isset($_SESSION['admin_logged_in']) || 
               isset($_SESSION['auth']['user_id']);
    }
    
    /**
     * Get authenticated user ID
     */
    protected function getAuthenticatedUserId(): ?int
    {
        if (!$this->requireAuth()) {
            return null;
        }
        
        // Check YFAuth session first
        if (isset($_SESSION['auth']['user_id'])) {
            return (int) $_SESSION['auth']['user_id'];
        }
        
        // Check legacy session
        if (isset($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }
        
        // Admin sessions don't have user_id
        return null;
    }

    /**
     * Require admin authentication
     */
    protected function requireAdmin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
            $this->errorResponse('Admin authentication required', 401);
            return false;
        }
        return true;
    }

    /**
     * Check if current user has specific permission
     */
    protected function requirePermission(string $permission): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            $this->errorResponse('Authentication required', 401);
            return false;
        }
        
        // For now, also check admin session as fallback
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
            return true; // Admins have all permissions
        }
        
        // TODO: Implement actual permission checking using PermissionService
        // This is a placeholder that will be enhanced when sessions are properly integrated
        $this->errorResponse('Insufficient permissions', 403);
        return false;
    }

    /**
     * Check if current user can manage specific resource (for "own" permissions)
     */
    protected function requireResourcePermission(string $basePermission, int $resourceOwnerId = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            $this->errorResponse('Authentication required', 401);
            return false;
        }
        
        // Admin override
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
            return true;
        }
        
        // TODO: Implement actual permission checking
        // For now, return true if user owns the resource
        if ($resourceOwnerId && $userId === $resourceOwnerId) {
            return true;
        }
        
        $this->errorResponse('Insufficient permissions', 403);
        return false;
    }
}