<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Http;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;

/**
 * Simple HTTP router for handling requests
 */
class Router
{
    private array $routes = [];
    private ContainerInterface $container;
    private ConfigInterface $config;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    /**
     * Register a GET route
     */
    public function get(string $path, string $controller, string $method): void
    {
        $this->addRoute('GET', $path, $controller, $method);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, string $controller, string $method): void
    {
        $this->addRoute('POST', $path, $controller, $method);
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, string $controller, string $method): void
    {
        $this->addRoute('PUT', $path, $controller, $method);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, string $controller, string $method): void
    {
        $this->addRoute('DELETE', $path, $controller, $method);
    }

    /**
     * Add a route to the router
     */
    private function addRoute(string $method, string $path, string $controller, string $action): void
    {
        $pattern = $this->convertPathToRegex($path);
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'path' => $path,
            'controller' => $controller,
            'action' => $action
        ];
    }

    /**
     * Dispatch the current request
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getCurrentPath();

        $pathMatches = [];
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                $pathMatches[] = $route;
                $allowedMethods[] = $route['method'];
                
                if ($route['method'] === $method) {
                    $this->executeRoute($route, $matches);
                    return;
                }
            }
        }

        // Check if path exists but method is wrong
        if (!empty($pathMatches)) {
            $this->handleMethodNotAllowed($allowedMethods);
            return;
        }

        // No route found at all
        $this->handleNotFound();
    }

    /**
     * Execute a matched route
     */
    private function executeRoute(array $route, array $matches): void
    {
        try {
            $controllerClass = $route['controller'];
            $action = $route['action'];

            // Instantiate controller with dependencies from container
            $config = $this->container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class);
            $controller = new $controllerClass($this->container, $config);

            // Extract path parameters
            $params = array_slice($matches, 1);
            
            // Add parameters to $_GET for easy access
            if (!empty($params)) {
                $_GET = array_merge($_GET, $this->extractPathParams($route['path'], $params));
            }

            // Call the controller method
            $controller->$action();

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get the current request path
     */
    private function getCurrentPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        // Strip base path if running in subdirectory
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        
        if ($basePath !== '/' && strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }
        
        // Ensure path starts with /
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Convert route path to regex pattern
     */
    private function convertPathToRegex(string $path): string
    {
        // Convert {param} to regex capture groups
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '([^/]+)', $path);
        
        // Escape forward slashes and add anchors
        $pattern = '#^' . str_replace('/', '\/', $pattern) . '$#';
        
        return $pattern;
    }

    /**
     * Extract path parameters from matches
     */
    private function extractPathParams(string $routePath, array $matches): array
    {
        $params = [];
        
        // Find parameter names in route path
        if (preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $routePath, $paramMatches)) {
            $paramNames = $paramMatches[1];
            
            foreach ($paramNames as $index => $name) {
                if (isset($matches[$index])) {
                    $params[$name] = $matches[$index];
                }
            }
        }
        
        return $params;
    }

    /**
     * Handle 404 not found
     */
    private function handleNotFound(): void
    {
        ErrorHandler::handle404($this->getCurrentPath(), $_SERVER['REQUEST_METHOD']);
    }

    /**
     * Handle 405 method not allowed
     */
    private function handleMethodNotAllowed(array $allowedMethods): void
    {
        ErrorHandler::handle405($this->getCurrentPath(), $_SERVER['REQUEST_METHOD'], $allowedMethods);
    }

    /**
     * Handle errors
     */
    private function handleError(\Exception $e): void
    {
        $debug = $this->config->get('app.debug', false);
        ErrorHandler::handle500($e, $debug);
    }

    /**
     * Get all registered routes (for debugging)
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}