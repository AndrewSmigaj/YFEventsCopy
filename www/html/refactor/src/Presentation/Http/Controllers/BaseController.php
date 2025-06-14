<?php

declare(strict_types=1);

namespace YakimaFinds\Presentation\Http\Controllers;

use YakimaFinds\Infrastructure\Container\ContainerInterface;
use YakimaFinds\Infrastructure\Config\ConfigInterface;

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
        session_start();
        return isset($_SESSION['user_id']) || isset($_SESSION['admin_logged_in']);
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
}