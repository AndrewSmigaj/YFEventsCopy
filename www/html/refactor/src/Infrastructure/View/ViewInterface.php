<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\View;

/**
 * Interface for view rendering
 */
interface ViewInterface
{
    /**
     * Render a view template with data
     */
    public function render(string $view, array $data = []): string;
    
    /**
     * Render a view with a layout
     */
    public function renderWithLayout(string $view, array $data = [], string $layout = 'default'): string;
    
    /**
     * Add global data available to all views
     */
    public function addGlobal(string $key, mixed $value): void;
    
    /**
     * Add multiple global data values
     */
    public function addGlobals(array $data): void;
    
    /**
     * Include a partial view
     */
    public function partial(string $partial, array $data = []): string;
    
    /**
     * Escape HTML output
     */
    public function escape(string $value): string;
    
    /**
     * Generate a URL
     */
    public function url(string $path = ''): string;
    
    /**
     * Generate an asset URL
     */
    public function asset(string $path): string;
}