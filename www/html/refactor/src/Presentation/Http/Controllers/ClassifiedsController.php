<?php

declare(strict_types=1);

namespace YakimaFinds\Presentation\Http\Controllers;

use YakimaFinds\Infrastructure\Config\ConfigInterface;
use YakimaFinds\Infrastructure\Container\Container;

class ClassifiedsController extends BaseController
{
    private string $modulePath;
    
    public function __construct(Container $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->modulePath = __DIR__ . '/../../../../../../../modules/yfclassifieds/www';
    }

    /**
     * Show classifieds gallery
     */
    public function showClassifiedsPage(): void
    {
        // Check if module is enabled
        $modulesConfig = require __DIR__ . '/../../../Infrastructure/Config/modules.php';
        if (!isset($modulesConfig['modules']['yfclassifieds']['enabled']) || 
            !$modulesConfig['modules']['yfclassifieds']['enabled']) {
            $this->handleNotFound();
            return;
        }
        
        // Include the module's index page
        require $this->modulePath . '/index.php';
    }

    /**
     * Show individual classified item
     */
    public function showItemPage(): void
    {
        // Check if module is enabled
        $modulesConfig = require __DIR__ . '/../../../Infrastructure/Config/modules.php';
        if (!isset($modulesConfig['modules']['yfclassifieds']['enabled']) || 
            !$modulesConfig['modules']['yfclassifieds']['enabled']) {
            $this->handleNotFound();
            return;
        }
        
        // Include the module's item page
        require $this->modulePath . '/item.php';
    }

    /**
     * Handle 404 Not Found
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Module Not Found</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f8f9fa;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 0;
        }
        p {
            font-size: 1.2rem;
            color: #6c757d;
            margin: 1rem 0;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <p>YF Classifieds module is not enabled</p>
        <p><a href="/refactor/">Return to homepage</a></p>
    </div>
</body>
</html>
HTML;
    }
}