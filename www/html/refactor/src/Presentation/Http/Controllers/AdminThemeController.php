<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use Exception;
use PDO;

/**
 * Admin controller for theme management
 */
class AdminThemeController extends BaseController
{
    private PDO $pdo;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $connection = $container->resolve(ConnectionInterface::class);
        $this->pdo = $connection->getConnection();
    }

    /**
     * Show theme management page
     */
    public function index(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderThemePage($basePath);
    }

    /**
     * Get all theme settings
     */
    public function getSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $sql = "SELECT * FROM theme_settings ORDER BY category, sort_order, id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $settings = $stmt->fetchAll();

            // Group by category
            $grouped = [];
            foreach ($settings as $setting) {
                $grouped[$setting['category']][] = $setting;
            }

            $this->successResponse([
                'settings' => $grouped
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load theme settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update theme setting
     */
    public function updateSetting(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $id = (int)($_GET['id'] ?? 0);

            if ($id <= 0) {
                $this->errorResponse('Invalid setting ID');
                return;
            }

            $value = $input['value'] ?? '';

            $sql = "UPDATE theme_settings SET value = :value, updated_at = NOW() WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'value' => $value,
                'id' => $id
            ]);

            $this->successResponse([
                'message' => 'Setting updated successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to update setting: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update multiple settings at once
     */
    public function updateBulkSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $settings = $input['settings'] ?? [];

            $this->pdo->beginTransaction();

            $sql = "UPDATE theme_settings SET value = :value, updated_at = NOW() WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);

            foreach ($settings as $id => $value) {
                $stmt->execute([
                    'value' => $value,
                    'id' => (int)$id
                ]);
            }

            $this->pdo->commit();

            $this->successResponse([
                'message' => 'Settings updated successfully',
                'updated' => count($settings)
            ]);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->errorResponse('Failed to update settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate CSS from theme settings
     */
    public function generateCSS(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $sql = "SELECT * FROM theme_settings WHERE category = 'colors' OR category = 'typography'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $settings = $stmt->fetchAll();

            $css = ":root {\n";
            foreach ($settings as $setting) {
                if (!empty($setting['value'])) {
                    $css .= "    --{$setting['key']}: {$setting['value']};\n";
                }
            }
            $css .= "}\n";

            // Save to file
            $cssPath = dirname(__DIR__, 4) . '/public/css/theme-custom.css';
            file_put_contents($cssPath, $css);

            $this->successResponse([
                'message' => 'CSS generated successfully',
                'css' => $css
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to generate CSS: ' . $e->getMessage(), 500);
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
            $input = $this->getInput();
            $preset = $input['preset'] ?? '';

            $presets = $this->getThemePresets();
            
            if (!isset($presets[$preset])) {
                $this->errorResponse('Invalid preset selected');
                return;
            }

            $this->pdo->beginTransaction();

            $sql = "UPDATE theme_settings SET value = :value WHERE `key` = :key";
            $stmt = $this->pdo->prepare($sql);

            foreach ($presets[$preset] as $key => $value) {
                $stmt->execute([
                    'value' => $value,
                    'key' => $key
                ]);
            }

            $this->pdo->commit();

            $this->successResponse([
                'message' => 'Preset applied successfully',
                'preset' => $preset
            ]);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->errorResponse('Failed to apply preset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get available theme presets
     */
    private function getThemePresets(): array
    {
        return [
            'default' => [
                'primary_color' => '#007bff',
                'secondary_color' => '#6c757d',
                'success_color' => '#28a745',
                'danger_color' => '#dc3545',
                'warning_color' => '#ffc107',
                'info_color' => '#17a2b8',
                'font_family' => 'Arial, sans-serif',
                'header_font' => 'Georgia, serif'
            ],
            'modern' => [
                'primary_color' => '#6366f1',
                'secondary_color' => '#64748b',
                'success_color' => '#10b981',
                'danger_color' => '#ef4444',
                'warning_color' => '#f59e0b',
                'info_color' => '#3b82f6',
                'font_family' => 'Inter, sans-serif',
                'header_font' => 'Inter, sans-serif'
            ],
            'dark' => [
                'primary_color' => '#1a1a1a',
                'secondary_color' => '#4a4a4a',
                'success_color' => '#00d97e',
                'danger_color' => '#e63757',
                'warning_color' => '#f5803e',
                'info_color' => '#39afd1',
                'font_family' => 'Roboto, sans-serif',
                'header_font' => 'Roboto, sans-serif'
            ]
        ];
    }

    /**
     * Render theme management page
     */
    private function renderThemePage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Management - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .theme-section {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .setting-group {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        .setting-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .color-input-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .color-preview {
            width: 40px;
            height: 40px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
        }
        .preset-card {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .preset-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .preset-colors {
            display: flex;
            gap: 5px;
            margin-top: 0.5rem;
        }
        .preset-color {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .nav-tabs .nav-link {
            color: #495057;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Theme Management</h1>
                <a href="{$basePath}/admin/dashboard" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#colors">
                    <i class="bi bi-palette"></i> Colors
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#typography">
                    <i class="bi bi-fonts"></i> Typography
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#seo">
                    <i class="bi bi-search"></i> SEO
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#social">
                    <i class="bi bi-share"></i> Social Media
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#presets">
                    <i class="bi bi-magic"></i> Presets
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Colors Tab -->
            <div class="tab-pane fade show active" id="colors">
                <div class="theme-section">
                    <h4 class="mb-4">Color Settings</h4>
                    <div id="color-settings">
                        <div class="text-center p-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Typography Tab -->
            <div class="tab-pane fade" id="typography">
                <div class="theme-section">
                    <h4 class="mb-4">Typography Settings</h4>
                    <div id="typography-settings">
                        <div class="text-center p-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEO Tab -->
            <div class="tab-pane fade" id="seo">
                <div class="theme-section">
                    <h4 class="mb-4">SEO Settings</h4>
                    <div id="seo-settings">
                        <div class="text-center p-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Media Tab -->
            <div class="tab-pane fade" id="social">
                <div class="theme-section">
                    <h4 class="mb-4">Social Media Settings</h4>
                    <div id="social-settings">
                        <div class="text-center p-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Presets Tab -->
            <div class="tab-pane fade" id="presets">
                <div class="theme-section">
                    <h4 class="mb-4">Theme Presets</h4>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="preset-card" onclick="applyPreset('default')">
                                <h5>Default Theme</h5>
                                <p class="text-muted">Classic Bootstrap colors</p>
                                <div class="preset-colors">
                                    <div class="preset-color" style="background: #007bff"></div>
                                    <div class="preset-color" style="background: #6c757d"></div>
                                    <div class="preset-color" style="background: #28a745"></div>
                                    <div class="preset-color" style="background: #dc3545"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="preset-card" onclick="applyPreset('modern')">
                                <h5>Modern Theme</h5>
                                <p class="text-muted">Contemporary design</p>
                                <div class="preset-colors">
                                    <div class="preset-color" style="background: #6366f1"></div>
                                    <div class="preset-color" style="background: #64748b"></div>
                                    <div class="preset-color" style="background: #10b981"></div>
                                    <div class="preset-color" style="background: #ef4444"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="preset-card" onclick="applyPreset('dark')">
                                <h5>Dark Theme</h5>
                                <p class="text-muted">Dark mode colors</p>
                                <div class="preset-colors">
                                    <div class="preset-color" style="background: #1a1a1a"></div>
                                    <div class="preset-color" style="background: #4a4a4a"></div>
                                    <div class="preset-color" style="background: #00d97e"></div>
                                    <div class="preset-color" style="background: #e63757"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="text-end mt-4">
            <button class="btn btn-secondary" onclick="generateCSS()">
                <i class="bi bi-code-slash"></i> Generate CSS
            </button>
            <button class="btn btn-primary" onclick="saveAllSettings()">
                <i class="bi bi-save"></i> Save All Changes
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const basePath = '{$basePath}';
        let currentSettings = {};

        document.addEventListener('DOMContentLoaded', () => {
            loadSettings();
        });

        async function loadSettings() {
            try {
                const response = await fetch(`\${basePath}/admin/theme/settings`);
                const data = await response.json();

                if (data.success) {
                    currentSettings = data.settings;
                    renderSettings();
                } else {
                    showError('Failed to load theme settings');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        function renderSettings() {
            renderColorSettings();
            renderTypographySettings();
            renderSEOSettings();
            renderSocialSettings();
        }

        function renderColorSettings() {
            const container = document.getElementById('color-settings');
            const colors = currentSettings.colors || [];
            
            if (colors.length === 0) {
                container.innerHTML = '<p class="text-muted">No color settings found</p>';
                return;
            }

            container.innerHTML = colors.map(setting => `
                <div class="setting-group">
                    <label class="form-label">\${escapeHtml(setting.label)}</label>
                    <div class="color-input-wrapper">
                        <input type="color" 
                               class="form-control form-control-color" 
                               id="setting-\${setting.id}" 
                               value="\${setting.value || '#000000'}"
                               onchange="updateSetting(\${setting.id}, this.value)">
                        <input type="text" 
                               class="form-control" 
                               value="\${setting.value || ''}"
                               onchange="updateColorInput(\${setting.id}, this.value)">
                    </div>
                    <small class="text-muted">\${escapeHtml(setting.description || '')}</small>
                </div>
            `).join('');
        }

        function renderTypographySettings() {
            const container = document.getElementById('typography-settings');
            const typography = currentSettings.typography || [];
            
            if (typography.length === 0) {
                container.innerHTML = '<p class="text-muted">No typography settings found</p>';
                return;
            }

            container.innerHTML = typography.map(setting => `
                <div class="setting-group">
                    <label class="form-label">\${escapeHtml(setting.label)}</label>
                    <input type="text" 
                           class="form-control" 
                           id="setting-\${setting.id}" 
                           value="\${setting.value || ''}"
                           onchange="updateSetting(\${setting.id}, this.value)"
                           placeholder="\${escapeHtml(setting.description || '')}">
                </div>
            `).join('');
        }

        function renderSEOSettings() {
            const container = document.getElementById('seo-settings');
            const seo = currentSettings.seo || [];
            
            if (seo.length === 0) {
                container.innerHTML = '<p class="text-muted">No SEO settings found</p>';
                return;
            }

            container.innerHTML = seo.map(setting => `
                <div class="setting-group">
                    <label class="form-label">\${escapeHtml(setting.label)}</label>
                    \${setting.type === 'textarea' ? `
                        <textarea class="form-control" 
                                  id="setting-\${setting.id}" 
                                  rows="3"
                                  onchange="updateSetting(\${setting.id}, this.value)">\${setting.value || ''}</textarea>
                    ` : `
                        <input type="text" 
                               class="form-control" 
                               id="setting-\${setting.id}" 
                               value="\${setting.value || ''}"
                               onchange="updateSetting(\${setting.id}, this.value)">
                    `}
                    <small class="text-muted">\${escapeHtml(setting.description || '')}</small>
                </div>
            `).join('');
        }

        function renderSocialSettings() {
            const container = document.getElementById('social-settings');
            const social = currentSettings.social || [];
            
            if (social.length === 0) {
                container.innerHTML = '<p class="text-muted">No social media settings found</p>';
                return;
            }

            container.innerHTML = social.map(setting => `
                <div class="setting-group">
                    <label class="form-label">\${escapeHtml(setting.label)}</label>
                    <input type="url" 
                           class="form-control" 
                           id="setting-\${setting.id}" 
                           value="\${setting.value || ''}"
                           onchange="updateSetting(\${setting.id}, this.value)"
                           placeholder="https://...">
                    <small class="text-muted">\${escapeHtml(setting.description || '')}</small>
                </div>
            `).join('');
        }

        function updateSetting(id, value) {
            // Update in memory
            for (const category in currentSettings) {
                const index = currentSettings[category].findIndex(s => s.id === id);
                if (index !== -1) {
                    currentSettings[category][index].value = value;
                    break;
                }
            }
        }

        function updateColorInput(id, value) {
            document.getElementById(`setting-\${id}`).value = value;
            updateSetting(id, value);
        }

        async function saveAllSettings() {
            const settings = {};
            
            // Collect all settings
            for (const category in currentSettings) {
                currentSettings[category].forEach(setting => {
                    settings[setting.id] = setting.value;
                });
            }

            try {
                const response = await fetch(`\${basePath}/admin/theme/settings/bulk`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ settings })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Settings saved successfully');
                } else {
                    showError(data.error || 'Failed to save settings');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        async function generateCSS() {
            try {
                const response = await fetch(`\${basePath}/admin/theme/generate-css`, {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    alert('CSS generated successfully');
                } else {
                    showError(data.error || 'Failed to generate CSS');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        async function applyPreset(preset) {
            if (!confirm(`Apply the \${preset} theme preset? This will override current color settings.`)) {
                return;
            }

            try {
                const response = await fetch(`\${basePath}/admin/theme/apply-preset`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ preset })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Preset applied successfully');
                    loadSettings(); // Reload to show new values
                } else {
                    showError(data.error || 'Failed to apply preset');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showError(message) {
            alert('Error: ' + message);
        }
    </script>
</body>
</html>
HTML;
    }
}