<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use PDO;
use Exception;

/**
 * Theme customization and SEO controller
 */
class ThemeController extends BaseController
{
    private PDO $pdo;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        
        // Get database connection from container
        $connection = $container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
        $this->pdo = $connection->getConnection();
    }

    /**
     * Get all theme settings grouped by category
     */
    public function getSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $sql = "
                SELECT * FROM theme_settings 
                ORDER BY category, sort_order, id
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $settings = $stmt->fetchAll();
            
            // Group by category
            $grouped = [];
            foreach ($settings as $setting) {
                $grouped[$setting['category']][] = $setting;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $grouped
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading theme settings: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load theme settings'
            ], 500);
        }
    }

    /**
     * Update theme settings
     */
    public function updateSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $settings = $input['settings'] ?? [];
            
            if (empty($settings)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'No settings provided'
                ], 400);
                return;
            }
            
            $this->pdo->beginTransaction();
            
            $sql = "
                UPDATE theme_settings 
                SET setting_value = ?, updated_at = NOW() 
                WHERE setting_key = ?
            ";
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$value, $key]);
            }
            
            $this->pdo->commit();
            
            // Generate CSS file
            $this->generateThemeCSS();
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Theme settings updated successfully'
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Error updating theme settings: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update theme settings'
            ], 500);
        }
    }

    /**
     * Get theme presets
     */
    public function getPresets(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $sql = "SELECT * FROM theme_presets ORDER BY is_default DESC, name";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $presets = $stmt->fetchAll();
            
            // Decode JSON settings
            foreach ($presets as &$preset) {
                $preset['settings'] = json_decode($preset['settings'], true);
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $presets
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading theme presets: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load theme presets'
            ], 500);
        }
    }

    /**
     * Apply theme preset
     */
    public function applyPreset(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $presetId = (int)($input['preset_id'] ?? 0);
            
            if (!$presetId) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Preset ID is required'
                ], 400);
                return;
            }
            
            // Get preset
            $sql = "SELECT settings FROM theme_presets WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$presetId]);
            $preset = $stmt->fetch();
            
            if (!$preset) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Preset not found'
                ], 404);
                return;
            }
            
            $settings = json_decode($preset['settings'], true);
            
            // Apply settings
            $this->pdo->beginTransaction();
            
            $updateSql = "
                UPDATE theme_settings 
                SET setting_value = ?, updated_at = NOW() 
                WHERE setting_key = ?
            ";
            $updateStmt = $this->pdo->prepare($updateSql);
            
            foreach ($settings as $key => $value) {
                $updateStmt->execute([$value, $key]);
            }
            
            $this->pdo->commit();
            
            // Generate CSS file
            $this->generateThemeCSS();
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Theme preset applied successfully'
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Error applying theme preset: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to apply theme preset'
            ], 500);
        }
    }

    /**
     * Save custom preset
     */
    public function savePreset(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $name = $input['name'] ?? '';
            $description = $input['description'] ?? '';
            
            if (empty($name)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Preset name is required'
                ], 400);
                return;
            }
            
            // Get current settings
            $sql = "SELECT setting_key, setting_value FROM theme_settings";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Insert preset
            $insertSql = "
                INSERT INTO theme_presets (name, description, settings, created_at) 
                VALUES (?, ?, ?, NOW())
            ";
            $insertStmt = $this->pdo->prepare($insertSql);
            $insertStmt->execute([$name, $description, json_encode($settings)]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Theme preset saved successfully',
                'data' => ['id' => $this->pdo->lastInsertId()]
            ]);
            
        } catch (Exception $e) {
            error_log("Error saving theme preset: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to save theme preset'
            ], 500);
        }
    }

    /**
     * Get SEO settings
     */
    public function getSEOSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $pageType = $_GET['page_type'] ?? null;
            
            $sql = "SELECT * FROM seo_settings";
            $params = [];
            
            if ($pageType) {
                $sql .= " WHERE page_type = ?";
                $params[] = $pageType;
            }
            
            $sql .= " ORDER BY page_type, page_identifier";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $settings = $stmt->fetchAll();
            
            // Decode JSON fields
            foreach ($settings as &$setting) {
                $setting['schema_markup'] = $setting['schema_markup'] ? json_decode($setting['schema_markup'], true) : null;
                $setting['custom_meta'] = $setting['custom_meta'] ? json_decode($setting['custom_meta'], true) : null;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $settings
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading SEO settings: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load SEO settings'
            ], 500);
        }
    }

    /**
     * Update SEO settings
     */
    public function updateSEOSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $required = ['page_type'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => "Field '{$field}' is required"
                    ], 400);
                    return;
                }
            }
            
            // Encode JSON fields
            $schemaMarkup = !empty($input['schema_markup']) ? json_encode($input['schema_markup']) : null;
            $customMeta = !empty($input['custom_meta']) ? json_encode($input['custom_meta']) : null;
            
            $sql = "
                INSERT INTO seo_settings (
                    page_type, page_identifier, meta_title, meta_description, meta_keywords,
                    og_title, og_description, og_image, og_type,
                    twitter_card, twitter_title, twitter_description, twitter_image,
                    canonical_url, robots, schema_markup, custom_meta, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    meta_title = VALUES(meta_title),
                    meta_description = VALUES(meta_description),
                    meta_keywords = VALUES(meta_keywords),
                    og_title = VALUES(og_title),
                    og_description = VALUES(og_description),
                    og_image = VALUES(og_image),
                    og_type = VALUES(og_type),
                    twitter_card = VALUES(twitter_card),
                    twitter_title = VALUES(twitter_title),
                    twitter_description = VALUES(twitter_description),
                    twitter_image = VALUES(twitter_image),
                    canonical_url = VALUES(canonical_url),
                    robots = VALUES(robots),
                    schema_markup = VALUES(schema_markup),
                    custom_meta = VALUES(custom_meta),
                    updated_at = NOW()
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $input['page_type'],
                $input['page_identifier'] ?? null,
                $input['meta_title'] ?? null,
                $input['meta_description'] ?? null,
                $input['meta_keywords'] ?? null,
                $input['og_title'] ?? null,
                $input['og_description'] ?? null,
                $input['og_image'] ?? null,
                $input['og_type'] ?? 'website',
                $input['twitter_card'] ?? 'summary',
                $input['twitter_title'] ?? null,
                $input['twitter_description'] ?? null,
                $input['twitter_image'] ?? null,
                $input['canonical_url'] ?? null,
                $input['robots'] ?? 'index, follow',
                $schemaMarkup,
                $customMeta
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'SEO settings updated successfully'
            ]);
            
        } catch (Exception $e) {
            error_log("Error updating SEO settings: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update SEO settings'
            ], 500);
        }
    }

    /**
     * Get social media settings
     */
    public function getSocialMediaSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $sql = "
                SELECT * FROM social_media_settings 
                ORDER BY sort_order, platform
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $settings = $stmt->fetchAll();
            
            // Decode JSON settings
            foreach ($settings as &$setting) {
                $setting['settings'] = $setting['settings'] ? json_decode($setting['settings'], true) : null;
                // Hide sensitive data
                if ($setting['app_secret']) {
                    $setting['app_secret'] = substr($setting['app_secret'], 0, 4) . str_repeat('*', 20);
                }
                if ($setting['access_token']) {
                    $setting['access_token'] = substr($setting['access_token'], 0, 4) . str_repeat('*', 20);
                }
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $settings
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading social media settings: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load social media settings'
            ], 500);
        }
    }

    /**
     * Update social media settings
     */
    public function updateSocialMediaSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $platform = $input['platform'] ?? '';
            
            if (empty($platform)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Platform is required'
                ], 400);
                return;
            }
            
            // Don't update sensitive fields if they contain masked values
            $updateFields = [];
            $params = [];
            
            $allowedFields = [
                'enabled', 'username', 'url', 'share_template', 
                'icon_class', 'color', 'sort_order'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "{$field} = ?";
                    $params[] = $field === 'enabled' ? ($input[$field] ? 1 : 0) : $input[$field];
                }
            }
            
            // Handle sensitive fields
            if (isset($input['app_id'])) {
                $updateFields[] = "app_id = ?";
                $params[] = $input['app_id'];
            }
            
            if (isset($input['app_secret']) && !str_contains($input['app_secret'], '*')) {
                $updateFields[] = "app_secret = ?";
                $params[] = $input['app_secret'];
            }
            
            if (isset($input['access_token']) && !str_contains($input['access_token'], '*')) {
                $updateFields[] = "access_token = ?";
                $params[] = $input['access_token'];
            }
            
            if (isset($input['settings'])) {
                $updateFields[] = "settings = ?";
                $params[] = json_encode($input['settings']);
            }
            
            if (empty($updateFields)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'No fields to update'
                ], 400);
                return;
            }
            
            $updateFields[] = "updated_at = NOW()";
            $params[] = $platform;
            
            $sql = "UPDATE social_media_settings SET " . implode(', ', $updateFields) . " WHERE platform = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Social media settings updated successfully'
            ]);
            
        } catch (Exception $e) {
            error_log("Error updating social media settings: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update social media settings'
            ], 500);
        }
    }

    /**
     * Generate theme CSS file
     */
    private function generateThemeCSS(): void
    {
        try {
            // Get all theme settings
            $sql = "SELECT setting_key, setting_value FROM theme_settings";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Generate CSS
            $css = ":root {\n";
            
            // Colors
            foreach ($settings as $key => $value) {
                if (str_contains($key, '_color')) {
                    $cssVar = '--' . str_replace('_', '-', $key);
                    $css .= "    {$cssVar}: {$value};\n";
                }
            }
            
            // Typography
            $css .= "    --font-family: {$settings['font_family']};\n";
            $css .= "    --heading-font: {$settings['heading_font']};\n";
            $css .= "    --font-size-base: {$settings['font_size_base']}px;\n";
            $css .= "    --line-height: {$settings['line_height']};\n";
            
            // Layout
            $css .= "    --container-width: {$settings['container_width']}px;\n";
            $css .= "    --header-height: {$settings['header_height']}px;\n";
            $css .= "    --sidebar-width: {$settings['sidebar_width']}px;\n";
            $css .= "    --border-radius: {$settings['border_radius']}px;\n";
            
            $css .= "}\n\n";
            
            // Apply to elements
            $css .= "body {\n";
            $css .= "    font-family: var(--font-family);\n";
            $css .= "    font-size: var(--font-size-base);\n";
            $css .= "    line-height: var(--line-height);\n";
            $css .= "    color: var(--text-color);\n";
            $css .= "    background-color: var(--background-color);\n";
            $css .= "}\n\n";
            
            $css .= "h1, h2, h3, h4, h5, h6 {\n";
            $css .= "    font-family: var(--heading-font);\n";
            $css .= "}\n\n";
            
            $css .= "a {\n";
            $css .= "    color: var(--link-color);\n";
            $css .= "}\n\n";
            
            $css .= ".container {\n";
            $css .= "    max-width: var(--container-width);\n";
            $css .= "}\n\n";
            
            $css .= ".btn-primary {\n";
            $css .= "    background-color: var(--primary-color);\n";
            $css .= "    border-color: var(--primary-color);\n";
            $css .= "}\n\n";
            
            $css .= ".btn-secondary {\n";
            $css .= "    background-color: var(--secondary-color);\n";
            $css .= "    border-color: var(--secondary-color);\n";
            $css .= "}\n";
            
            // Write to file
            $cssPath = dirname(__DIR__, 4) . '/public/css/theme-custom.css';
            file_put_contents($cssPath, $css);
            
        } catch (Exception $e) {
            error_log("Error generating theme CSS: " . $e->getMessage());
        }
    }

    /**
     * Export theme settings
     */
    public function exportSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            // Get all settings
            $tables = ['theme_settings', 'seo_settings', 'social_media_settings', 'theme_presets'];
            $export = [];
            
            foreach ($tables as $table) {
                $sql = "SELECT * FROM {$table}";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $export[$table] = $stmt->fetchAll();
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $export,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("Error exporting theme settings: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to export theme settings'
            ], 500);
        }
    }
}