<?php

namespace YFEvents\Modules\YFTheme\Services;

use PDO;
use Exception;

/**
 * Simple Theme Service
 * Lightweight theme service that works without complex dependencies
 */
class SimpleThemeService
{
    private PDO $db;
    private string $cacheDir;
    private bool $debugMode;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->cacheDir = $config['cache_dir'] ?? __DIR__ . '/../../www/css';
        $this->debugMode = $config['debug'] ?? false;
        
        $this->ensureCacheDirectory();
    }

    /**
     * Get current theme CSS
     */
    public function getThemeCSS(array $scope = []): string
    {
        try {
            $variables = $this->getCurrentThemeVariables($scope);
            
            $css = ":root {\n";
            foreach ($variables as $cssVar => $value) {
                $css .= "  {$cssVar}: {$value};\n";
            }
            $css .= "}\n\n";
            
            // Add theme-specific CSS if needed
            $css .= $this->getAdditionalThemeCSS();
            
            return $css;
        } catch (Exception $e) {
            // Return default CSS if there's an error
            return $this->getDefaultCSS();
        }
    }

    /**
     * Get current theme variables
     */
    public function getCurrentThemeVariables(array $scope = []): array
    {
        try {
            $sql = "SELECT css_variable, current_value FROM theme_variables WHERE current_value IS NOT NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $variables = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $variables[$row['css_variable']] = $row['current_value'];
            }
            
            return $variables;
        } catch (Exception $e) {
            return $this->getDefaultVariables();
        }
    }

    /**
     * Get all theme variables grouped by category
     */
    public function getAllThemeVariables(): array
    {
        try {
            $sql = "
                SELECT v.*, c.name as category_name, c.display_name as category_display_name
                FROM theme_variables v
                LEFT JOIN theme_categories c ON v.category_id = c.id
                ORDER BY c.sort_order, v.sort_order, v.display_name
            ";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get theme categories
     */
    public function getThemeCategories(): array
    {
        try {
            $sql = "SELECT * FROM theme_categories ORDER BY sort_order, display_name";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get theme presets
     */
    public function getThemePresets(): array
    {
        try {
            $sql = "SELECT * FROM theme_presets ORDER BY is_default DESC, name";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Update a theme variable
     */
    public function updateThemeVariable(int $variableId, string $value): bool
    {
        try {
            $sql = "UPDATE theme_variables SET current_value = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$value, $variableId]);
            
            if ($result) {
                $this->clearCache();
            }
            
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Apply a theme preset
     */
    public function applyThemePreset(int $presetId): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Get preset variables
            $sql = "
                SELECT v.id, pv.value 
                FROM theme_preset_variables pv
                JOIN theme_variables v ON pv.variable_id = v.id
                WHERE pv.preset_id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$presetId]);
            $presetVariables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update each variable
            foreach ($presetVariables as $var) {
                $sql = "UPDATE theme_variables SET current_value = ? WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$var['value'], $var['id']]);
            }
            
            $this->db->commit();
            $this->clearCache();
            
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Reset to default theme
     */
    public function resetToDefaultTheme(): bool
    {
        try {
            $sql = "UPDATE theme_variables SET current_value = default_value";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute();
            
            if ($result) {
                $this->clearCache();
            }
            
            return $result;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Generate and cache theme CSS
     */
    public function regenerateCSS(): bool
    {
        try {
            $css = $this->getThemeCSS();
            $cacheFile = $this->cacheDir . '/current-theme.css';
            
            $result = file_put_contents($cacheFile, $css);
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Clear theme cache
     */
    public function clearCache(): void
    {
        $cacheFiles = glob($this->cacheDir . '/theme-*.css');
        foreach ($cacheFiles as $file) {
            unlink($file);
        }
        
        // Regenerate current theme
        $this->regenerateCSS();
    }

    /**
     * Get default CSS variables (fallback)
     */
    private function getDefaultVariables(): array
    {
        return [
            '--primary-color' => '#2c3e50',
            '--secondary-color' => '#3498db',
            '--accent-color' => '#e74c3c',
            '--success-color' => '#27ae60',
            '--warning-color' => '#f39c12',
            '--light-gray' => '#ecf0f1',
            '--medium-gray' => '#bdc3c7',
            '--dark-gray' => '#7f8c8d',
            '--border-radius' => '8px',
            '--shadow' => '0 2px 10px rgba(0,0,0,0.1)',
            '--transition' => 'all 0.3s ease'
        ];
    }

    /**
     * Get default CSS (fallback)
     */
    private function getDefaultCSS(): string
    {
        $variables = $this->getDefaultVariables();
        
        $css = ":root {\n";
        foreach ($variables as $var => $value) {
            $css .= "  {$var}: {$value};\n";
        }
        $css .= "}\n";
        
        return $css;
    }

    /**
     * Get additional theme-specific CSS
     */
    private function getAdditionalThemeCSS(): string
    {
        return <<<CSS
/* YFTheme Generated CSS */

/* Ensure smooth transitions for theme changes */
*, *::before, *::after {
    transition: color 0.3s ease, background-color 0.3s ease, border-color 0.3s ease;
}

/* Theme-specific adjustments */
.theme-preview {
    --preview-bg: var(--light-gray);
    --preview-border: var(--medium-gray);
    --preview-text: var(--primary-color);
}

/* Dark theme adjustments */
body.theme-dark {
    background-color: #1a1a1a;
    color: #ffffff;
}

body.theme-dark .calendar-container {
    background-color: #1a1a1a;
}

body.theme-dark .calendar-header {
    background: linear-gradient(135deg, #2c2c2c, #4a4a4a);
}

/* High contrast theme */
body.theme-high-contrast {
    filter: contrast(150%);
}

body.theme-high-contrast * {
    border-width: 2px !important;
}

/* Ensure existing styles are preserved */
.btn, .button {
    transition: var(--transition);
}

.card, .panel, .section {
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
}

CSS;
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
     * Check if theme tables exist
     */
    public function isInstalled(): bool
    {
        try {
            $tables = ['theme_variables', 'theme_categories', 'theme_presets'];
            foreach ($tables as $table) {
                $sql = "SELECT 1 FROM {$table} LIMIT 1";
                $this->db->query($sql);
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get installation status
     */
    public function getInstallationStatus(): array
    {
        $status = [
            'installed' => false,
            'tables_exist' => false,
            'has_variables' => false,
            'has_categories' => false,
            'has_presets' => false,
            'css_writable' => false
        ];

        try {
            // Check if tables exist
            $tables = ['theme_variables', 'theme_categories', 'theme_presets'];
            $tablesExist = true;
            foreach ($tables as $table) {
                try {
                    $this->db->query("SELECT 1 FROM {$table} LIMIT 1");
                } catch (Exception $e) {
                    $tablesExist = false;
                    break;
                }
            }
            $status['tables_exist'] = $tablesExist;

            if ($tablesExist) {
                // Check if data exists
                $status['has_variables'] = $this->db->query("SELECT COUNT(*) FROM theme_variables")->fetchColumn() > 0;
                $status['has_categories'] = $this->db->query("SELECT COUNT(*) FROM theme_categories")->fetchColumn() > 0;
                $status['has_presets'] = $this->db->query("SELECT COUNT(*) FROM theme_presets")->fetchColumn() > 0;
            }

            // Check if CSS directory is writable
            $status['css_writable'] = is_writable($this->cacheDir);

            $status['installed'] = $status['tables_exist'] && $status['has_variables'] && $status['css_writable'];

        } catch (Exception $e) {
            // Leave all status values as false
        }

        return $status;
    }
}