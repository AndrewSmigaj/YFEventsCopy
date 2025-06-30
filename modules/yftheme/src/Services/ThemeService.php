<?php

namespace YFEvents\Modules\YFTheme\Services;

use YFEvents\Modules\YFTheme\Models\ThemeModel;
use YFEvents\Modules\YFAuth\Services\SessionService;
use PDO;
use Exception;

/**
 * Theme Service
 * Handles theme operations, caching, and compilation
 */
class ThemeService
{
    private PDO $db;
    private ThemeModel $themeModel;
    private SessionService $sessionService;
    private string $cacheDir;
    private bool $debugMode;
    private array $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->themeModel = new ThemeModel($db);
        $this->sessionService = new SessionService($db);
        $this->cacheDir = $config['cache_dir'] ?? __DIR__ . '/../../cache';
        $this->debugMode = $config['debug'] ?? false;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $this->ensureCacheDirectory();
    }

    /**
     * Get current theme CSS
     */
    public function getThemeCSS(array $scope = []): string
    {
        $cacheKey = $this->getCacheKey($scope);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.css';
        
        // Check cache
        if (!$this->debugMode && file_exists($cacheFile)) {
            $cacheAge = time() - filemtime($cacheFile);
            if ($cacheAge < $this->config['cache_ttl']) {
                return file_get_contents($cacheFile);
            }
        }
        
        // Generate CSS
        $css = $this->generateCompleteCSS($scope);
        
        // Save to cache
        file_put_contents($cacheFile, $css);
        
        return $css;
    }

    /**
     * Generate complete CSS including base styles
     */
    private function generateCompleteCSS(array $scope = []): string
    {
        $css = '';
        
        // Add CSS reset if enabled
        if ($this->config['include_reset']) {
            $css .= $this->getCSSReset();
        }
        
        // Add theme variables
        $css .= $this->themeModel->generateCSS($scope);
        
        // Add component styles
        if ($this->config['include_components']) {
            $css .= $this->getComponentStyles();
        }
        
        // Add custom CSS if exists
        $customCSS = $this->getCustomCSS();
        if ($customCSS) {
            $css .= "\n/* Custom CSS */\n" . $customCSS;
        }
        
        // Minify if not in debug mode
        if (!$this->debugMode && $this->config['minify']) {
            $css = $this->minifyCSS($css);
        }
        
        return $css;
    }

    /**
     * Get CSS reset
     */
    private function getCSSReset(): string
    {
        return <<<CSS
/* CSS Reset - Modern Normalize */
*, *::before, *::after {
    box-sizing: border-box;
}

* {
    margin: 0;
    padding: 0;
}

html {
    font-family: var(--font-family-base);
    font-size: var(--font-size-base);
    line-height: var(--line-height-base);
    -webkit-text-size-adjust: 100%;
    -webkit-tap-highlight-color: transparent;
}

body {
    margin: 0;
    color: var(--text-primary);
    background-color: var(--background-color);
    font-weight: var(--font-weight-normal);
}

h1, h2, h3, h4, h5, h6 {
    margin-top: 0;
    margin-bottom: var(--spacing-medium);
    font-family: var(--font-family-heading);
    font-weight: var(--font-weight-bold);
    line-height: 1.2;
}

h1 { font-size: var(--font-size-h1); }
h2 { font-size: var(--font-size-h2); }
h3 { font-size: var(--font-size-h3); }

p {
    margin-top: 0;
    margin-bottom: var(--spacing-medium);
}

a {
    color: var(--primary-color);
    text-decoration: none;
    transition: opacity var(--transition-speed);
}

a:hover {
    opacity: var(--hover-opacity);
}

img {
    max-width: 100%;
    height: auto;
    vertical-align: middle;
}

button, input, select, textarea {
    font-family: inherit;
    font-size: 100%;
    line-height: inherit;
    margin: 0;
}

button {
    cursor: pointer;
}


CSS;
    }

    /**
     * Get component styles
     */
    private function getComponentStyles(): string
    {
        return <<<CSS

/* Component Styles */

/* Buttons */
.btn {
    display: inline-block;
    padding: var(--button-padding);
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-normal);
    line-height: var(--line-height-base);
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
    cursor: pointer;
    user-select: none;
    border: 1px solid transparent;
    border-radius: var(--button-border-radius);
    transition: all var(--transition-speed);
    background-color: var(--primary-color);
    color: white;
}

.btn:hover {
    background-color: var(--primary-dark);
    opacity: 1;
}

.btn-secondary {
    background-color: var(--secondary-color);
}

.btn-secondary:hover {
    background-color: var(--secondary-color);
    filter: brightness(0.9);
}

/* Cards */
.card {
    background-color: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: var(--card-border-radius);
    padding: var(--card-padding);
    margin-bottom: var(--spacing-medium);
    box-shadow: var(--shadow-small);
    transition: box-shadow var(--transition-speed);
}

.card:hover {
    box-shadow: var(--shadow-medium);
}

/* Forms */
.form-control {
    display: block;
    width: 100%;
    padding: var(--input-padding);
    font-size: var(--font-size-base);
    line-height: var(--line-height-base);
    color: var(--text-primary);
    background-color: var(--background-color);
    border: var(--input-border-width) solid var(--border-color);
    border-radius: var(--input-border-radius);
    transition: border-color var(--transition-speed);
}

.form-control:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 3px rgba(var(--primary-color), 0.1);
}

/* Alerts */
.alert {
    padding: var(--spacing-medium);
    margin-bottom: var(--spacing-medium);
    border: 1px solid transparent;
    border-radius: var(--button-border-radius);
}

.alert-success {
    color: var(--success-color);
    background-color: rgba(76, 175, 80, 0.1);
    border-color: var(--success-color);
}

.alert-warning {
    color: var(--warning-color);
    background-color: rgba(255, 152, 0, 0.1);
    border-color: var(--warning-color);
}

.alert-error {
    color: var(--error-color);
    background-color: rgba(244, 67, 54, 0.1);
    border-color: var(--error-color);
}

.alert-info {
    color: var(--info-color);
    background-color: rgba(33, 150, 243, 0.1);
    border-color: var(--info-color);
}

/* Container */
.container {
    width: 100%;
    max-width: var(--container-max-width);
    margin: 0 auto;
    padding: 0 var(--spacing-medium);
}

/* Grid */
.row {
    display: flex;
    flex-wrap: wrap;
    margin-left: calc(var(--spacing-medium) * -1);
    margin-right: calc(var(--spacing-medium) * -1);
}

.col {
    flex: 1;
    padding-left: var(--spacing-medium);
    padding-right: var(--spacing-medium);
}


CSS;
    }

    /**
     * Get custom CSS from database
     */
    private function getCustomCSS(): ?string
    {
        // This could be stored in a settings table
        // For now, check for a custom.css file
        $customFile = $this->cacheDir . '/custom.css';
        if (file_exists($customFile)) {
            return file_get_contents($customFile);
        }
        
        return null;
    }

    /**
     * Apply theme updates
     */
    public function applyThemeUpdates(array $updates, int $userId): bool
    {
        try {
            // Validate updates
            foreach ($updates as $variableId => $value) {
                if (!$this->validateVariableValue($variableId, $value)) {
                    throw new Exception("Invalid value for variable ID: {$variableId}");
                }
            }
            
            // Apply updates
            $result = $this->themeModel->updateVariables($updates, $userId, 'Theme editor update');
            
            if ($result) {
                // Clear all caches
                $this->clearAllCaches();
                
                // Trigger theme update event
                $this->triggerThemeUpdateEvent($updates);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Failed to apply theme updates: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate variable value
     */
    private function validateVariableValue(int $variableId, string $value): bool
    {
        $sql = "SELECT type, constraints FROM theme_variables WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$variableId]);
        $variable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$variable) {
            return false;
        }
        
        $constraints = json_decode($variable['constraints'], true) ?: [];
        
        switch ($variable['type']) {
            case 'color':
                return $this->validateColor($value);
                
            case 'size':
            case 'spacing':
                return $this->validateSize($value, $constraints);
                
            case 'number':
                return $this->validateNumber($value, $constraints);
                
            case 'select':
                return $this->validateSelect($value, $constraints);
                
            case 'font':
                return $this->validateFont($value);
                
            case 'gradient':
                return $this->validateGradient($value);
                
            case 'shadow':
                return $this->validateShadow($value);
                
            case 'border':
                return $this->validateBorder($value);
                
            default:
                return true;
        }
    }

    /**
     * Validate color value
     */
    private function validateColor(string $value): bool
    {
        // Hex color
        if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value)) {
            return true;
        }
        
        // RGB/RGBA
        if (preg_match('/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[\d.]+\s*)?\)$/', $value)) {
            return true;
        }
        
        // HSL/HSLA
        if (preg_match('/^hsla?\(\s*\d+\s*,\s*\d+%?\s*,\s*\d+%?\s*(,\s*[\d.]+\s*)?\)$/', $value)) {
            return true;
        }
        
        // Named colors
        $namedColors = ['transparent', 'white', 'black', 'red', 'blue', 'green', 'yellow', 'orange', 'purple'];
        if (in_array(strtolower($value), $namedColors)) {
            return true;
        }
        
        return false;
    }

    /**
     * Validate size value
     */
    private function validateSize(string $value, array $constraints): bool
    {
        // Check format (number + unit)
        if (!preg_match('/^-?\d+(\.\d+)?(px|em|rem|%|vh|vw|vmin|vmax|pt|cm|mm|in|pc|ex|ch)$/', $value)) {
            return false;
        }
        
        // Check constraints
        if (isset($constraints['min']) || isset($constraints['max'])) {
            $numericValue = floatval($value);
            
            if (isset($constraints['min']) && $numericValue < $constraints['min']) {
                return false;
            }
            
            if (isset($constraints['max']) && $numericValue > $constraints['max']) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate number value
     */
    private function validateNumber(string $value, array $constraints): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        $numericValue = floatval($value);
        
        if (isset($constraints['min']) && $numericValue < $constraints['min']) {
            return false;
        }
        
        if (isset($constraints['max']) && $numericValue > $constraints['max']) {
            return false;
        }
        
        if (isset($constraints['step'])) {
            $step = floatval($constraints['step']);
            $min = $constraints['min'] ?? 0;
            $diff = $numericValue - $min;
            
            if (fmod($diff, $step) != 0) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate select value
     */
    private function validateSelect(string $value, array $options): bool
    {
        return in_array($value, $options);
    }

    /**
     * Validate font value
     */
    private function validateFont(string $value): bool
    {
        // Basic validation - should contain at least one valid font
        return strlen($value) > 0 && !preg_match('/[<>]/', $value);
    }

    /**
     * Validate gradient value
     */
    private function validateGradient(string $value): bool
    {
        return strpos($value, 'gradient') !== false || strpos($value, 'linear') !== false || strpos($value, 'radial') !== false;
    }

    /**
     * Validate shadow value
     */
    private function validateShadow(string $value): bool
    {
        // Basic shadow validation
        return preg_match('/\d+px/', $value) && (strpos($value, 'rgba') !== false || strpos($value, '#') !== false);
    }

    /**
     * Validate border value
     */
    private function validateBorder(string $value): bool
    {
        // Border shorthand validation
        return preg_match('/\d+px/', $value) || $value === 'none';
    }

    /**
     * Generate live preview
     */
    public function generateLivePreview(array $changes): array
    {
        $preview = [
            'css' => '',
            'affected_elements' => []
        ];
        
        // Get variable info
        $variableIds = array_keys($changes);
        $placeholders = str_repeat('?,', count($variableIds) - 1) . '?';
        
        $sql = "
            SELECT id, name, css_variable, type, preview_element 
            FROM theme_variables 
            WHERE id IN ({$placeholders})
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($variableIds);
        $variables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate preview CSS
        $css = ":root {\n";
        foreach ($variables as $var) {
            if (isset($changes[$var['id']])) {
                $css .= "    {$var['css_variable']}: {$changes[$var['id']]};\n";
                
                if ($var['preview_element']) {
                    $preview['affected_elements'][] = $var['preview_element'];
                }
            }
        }
        $css .= "}\n";
        
        $preview['css'] = $css;
        
        return $preview;
    }

    /**
     * Get cache key for scope
     */
    private function getCacheKey(array $scope): string
    {
        $parts = ['theme'];
        
        foreach ($scope as $type => $value) {
            $parts[] = $type . '_' . $value;
        }
        
        return implode('_', $parts);
    }

    /**
     * Clear all theme caches
     */
    public function clearAllCaches(): void
    {
        $files = glob($this->cacheDir . '/theme_*.css');
        foreach ($files as $file) {
            unlink($file);
        }
        
        // Also clear model cache
        $this->themeModel = new ThemeModel($this->db);
    }

    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Minify CSS
     */
    private function minifyCSS(string $css): string
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove spaces around specific characters
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        
        // Remove trailing semicolon before closing brace
        $css = str_replace(';}', '}', $css);
        
        return trim($css);
    }

    /**
     * Trigger theme update event
     */
    private function triggerThemeUpdateEvent(array $updates): void
    {
        // This could trigger webhooks, clear CDN cache, etc.
        // For now, just log
        error_log("Theme updated with " . count($updates) . " changes");
    }

    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'cache_ttl' => 3600, // 1 hour
            'include_reset' => true,
            'include_components' => true,
            'minify' => true,
            'debug' => false
        ];
    }

    /**
     * Get theme scope for current request
     */
    public function getCurrentScope(): array
    {
        $scope = [];
        
        // Page scope
        $currentPage = $_SERVER['REQUEST_URI'] ?? '/';
        $scope['page'] = $currentPage;
        
        // Module scope
        if (preg_match('/\/modules\/([^\/]+)/', $currentPage, $matches)) {
            $scope['module'] = $matches[1];
        }
        
        // User group scope
        $user = $this->sessionService->getCurrentUser();
        if ($user) {
            $roles = $user->getRoles();
            if (!empty($roles)) {
                $scope['user_group'] = $roles[0]['name'];
            }
        }
        
        return $scope;
    }

    /**
     * Generate SCSS variables file
     */
    public function generateSCSSVariables(): string
    {
        $scss = "// YFTheme SCSS Variables\n";
        $scss .= "// Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $categories = $this->themeModel->getVariablesByCategory(true);
        
        foreach ($categories as $category) {
            $scss .= "// {$category['display_name']}\n";
            
            foreach ($category['variables'] as $var) {
                $name = str_replace('-', '_', $var['name']);
                $value = $var['current_value'] ?? $var['default_value'];
                
                $scss .= "\${$name}: {$value};\n";
            }
            
            $scss .= "\n";
        }
        
        return $scss;
    }
}