<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\View;

use YFEvents\Infrastructure\Config\ConfigInterface;

/**
 * Factory for creating view instances
 */
class ViewFactory
{
    private ConfigInterface $config;
    private ?ViewInterface $instance = null;
    
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }
    
    /**
     * Create or get the view instance
     */
    public function create(): ViewInterface
    {
        if ($this->instance === null) {
            $this->instance = new View($this->config);
            $this->setupGlobalData();
        }
        
        return $this->instance;
    }
    
    /**
     * Setup global data available to all views
     */
    private function setupGlobalData(): void
    {
        if ($this->instance === null) {
            return;
        }
        
        // Add base path for URLs
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }
        
        $this->instance->addGlobals([
            'basePath' => $basePath,
            'appName' => $this->config->get('app.name', 'YFEvents'),
            'appVersion' => $this->config->get('app.version', '2.0'),
        ]);
        
        // Add user data if available in session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['admin_username'])) {
            $this->instance->addGlobal('username', $_SESSION['admin_username']);
        }
        
        if (isset($_SESSION['user_id'])) {
            $this->instance->addGlobal('userId', $_SESSION['user_id']);
        }
    }
}