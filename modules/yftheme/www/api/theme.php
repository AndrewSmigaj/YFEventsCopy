<?php
/**
 * Theme CSS API Endpoint
 * Serves the current theme CSS
 */

// Set proper content type for CSS
header('Content-Type: text/css; charset=utf-8');

// Enable caching (1 hour)
header('Cache-Control: public, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

try {
    require_once __DIR__ . '/../../../../config/database.php';
    
    // Try to load the theme service
    if (file_exists(__DIR__ . '/../../src/Services/SimpleThemeService.php')) {
        require_once __DIR__ . '/../../src/Services/SimpleThemeService.php';
        
        $themeService = new YFEvents\Modules\YFTheme\Services\SimpleThemeService($pdo);
        
        // Check if theme system is installed
        if ($themeService->isInstalled()) {
            echo $themeService->getThemeCSS();
        } else {
            // Theme not installed, return default CSS
            echo getDefaultThemeCSS();
        }
    } else {
        // Service not available, return default CSS
        echo getDefaultThemeCSS();
    }
    
} catch (Exception $e) {
    // Error occurred, return default CSS
    echo getDefaultThemeCSS();
}

/**
 * Get default theme CSS (fallback)
 */
function getDefaultThemeCSS(): string
{
    return <<<CSS
/* YFTheme Default CSS - Preserves existing styling */

:root {
    /* These variables match the original calendar.css */
    --primary-color: #2c3e50;
    --secondary-color: #3498db;
    --accent-color: #e74c3c;
    --success-color: #27ae60;
    --warning-color: #f39c12;
    --light-gray: #ecf0f1;
    --medium-gray: #bdc3c7;
    --dark-gray: #7f8c8d;
    --border-radius: 8px;
    --shadow: 0 2px 10px rgba(0,0,0,0.1);
    --transition: all 0.3s ease;
}

/* Theme system not installed - using defaults */
/* This ensures existing styles continue to work */

CSS;
}
?>