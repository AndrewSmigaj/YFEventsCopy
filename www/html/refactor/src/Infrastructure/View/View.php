<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\View;

use YFEvents\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Simple but effective view templating system
 */
class View implements ViewInterface
{
    private string $viewsPath;
    private string $layoutsPath;
    private ConfigInterface $config;
    private array $globalData = [];
    
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
        $basePath = $config->get('paths.base', dirname(__DIR__, 3));
        $this->viewsPath = $basePath . '/views';
        $this->layoutsPath = $basePath . '/views/layouts';
    }
    
    /**
     * Render a view template with data
     */
    public function render(string $view, array $data = []): string
    {
        $viewFile = $this->resolveViewPath($view);
        
        if (!file_exists($viewFile)) {
            throw new Exception("View not found: {$view} (looked in {$viewFile})");
        }
        
        // Merge with global data
        $data = array_merge($this->globalData, $data);
        
        // Extract data to make it available in the view
        extract($data, EXTR_SKIP);
        
        // Start output buffering
        ob_start();
        
        try {
            // Include the view file
            include $viewFile;
            
            return ob_get_clean();
        } catch (Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }
    
    /**
     * Render a view with a layout
     */
    public function renderWithLayout(string $view, array $data = [], string $layout = 'default'): string
    {
        // First render the view content
        $data['content'] = $this->render($view, $data);
        
        // Then render the layout with the content
        return $this->renderLayout($layout, $data);
    }
    
    /**
     * Render just a layout
     */
    public function renderLayout(string $layout, array $data = []): string
    {
        $layoutFile = $this->layoutsPath . '/' . str_replace('.', '/', $layout) . '.php';
        
        if (!file_exists($layoutFile)) {
            throw new Exception("Layout not found: {$layout} (looked in {$layoutFile})");
        }
        
        // Merge with global data
        $data = array_merge($this->globalData, $data);
        
        // Extract data
        extract($data, EXTR_SKIP);
        
        ob_start();
        
        try {
            include $layoutFile;
            return ob_get_clean();
        } catch (Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }
    
    /**
     * Add global data available to all views
     */
    public function addGlobal(string $key, mixed $value): void
    {
        $this->globalData[$key] = $value;
    }
    
    /**
     * Add multiple global data values
     */
    public function addGlobals(array $data): void
    {
        $this->globalData = array_merge($this->globalData, $data);
    }
    
    /**
     * Include a partial view
     */
    public function partial(string $partial, array $data = []): string
    {
        return $this->render('partials/' . $partial, $data);
    }
    
    /**
     * Escape HTML output
     */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Alias for escape
     */
    public function e(string $value): string
    {
        return $this->escape($value);
    }
    
    /**
     * Generate a URL
     */
    public function url(string $path = ''): string
    {
        $basePath = $this->config->get('app.base_path', '');
        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }
    
    /**
     * Generate an asset URL
     */
    public function asset(string $path): string
    {
        return $this->url('assets/' . ltrim($path, '/'));
    }
    
    /**
     * Check if a section exists
     */
    private array $sections = [];
    
    public function section(string $name): void
    {
        $this->sections[$name] = ob_get_clean();
        ob_start();
    }
    
    public function endSection(): void
    {
        ob_end_clean();
    }
    
    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }
    
    /**
     * Start capturing a section
     */
    public function startSection(string $name): void
    {
        ob_start();
        $this->currentSection = $name;
    }
    
    /**
     * Stop capturing a section
     */
    public function stopSection(): void
    {
        if (isset($this->currentSection)) {
            $this->sections[$this->currentSection] = ob_get_clean();
            unset($this->currentSection);
        }
    }
    
    /**
     * Resolve view path from dot notation
     */
    private function resolveViewPath(string $view): string
    {
        $path = str_replace('.', '/', $view);
        return $this->viewsPath . '/' . $path . '.php';
    }
}