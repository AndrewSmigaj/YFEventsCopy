<?php
require_once __DIR__ . '/../../../../config/database.php';

// Try to load the SimpleThemeService first (fallback)
if (file_exists(__DIR__ . '/../../src/Services/SimpleThemeService.php')) {
    require_once __DIR__ . '/../../src/Services/SimpleThemeService.php';
    class_alias('YFEvents\Modules\YFTheme\Services\SimpleThemeService', 'ThemeService');
} else {
    // Fallback to basic functionality
    class ThemeService {
        private $pdo;
        public function __construct($pdo) { $this->pdo = $pdo; }
        public function getThemeCategories() { return []; }
        public function getAllThemeVariables() { return []; }
        public function getThemePresets() { return []; }
        public function getCurrentThemeVariables() { return []; }
        public function updateThemeVariable($id, $value) { return false; }
        public function applyThemePreset($id) { return false; }
        public function resetToDefaultTheme() { return false; }
        public function isInstalled() { return false; }
    }
}

// Simple authentication check (adapt to your auth system)
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // For now, we'll allow access - in production, implement proper auth
    // header('Location: /admin/login.php');
    // exit;
}

// Setup database connection - following pattern from public/admin/email-events.php
$config = require __DIR__ . '/../../../../config/database.php';
$dbConfig = $config['database'];
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$themeService = new ThemeService($pdo);
$message = '';

// Handle form submissions
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_variable':
                    $variableId = (int)$_POST['variable_id'];
                    $value = $_POST['value'];
                    $themeService->updateThemeVariable($variableId, $value);
                    $message = '<div class="alert alert-success">Theme variable updated successfully!</div>';
                    break;
                
                case 'apply_preset':
                    $presetId = (int)$_POST['preset_id'];
                    $themeService->applyThemePreset($presetId);
                    $message = '<div class="alert alert-success">Theme preset applied successfully!</div>';
                    break;
                
                case 'reset_to_default':
                    $themeService->resetToDefaultTheme();
                    $message = '<div class="alert alert-info">Theme reset to default values!</div>';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Get current theme data
try {
    $categories = $themeService->getThemeCategories();
    $variables = $themeService->getAllThemeVariables();
    $presets = $themeService->getThemePresets();
    $currentTheme = $themeService->getCurrentThemeVariables();
} catch (Exception $e) {
    $categories = [];
    $variables = [];
    $presets = [];
    $currentTheme = [];
    $message = '<div class="alert alert-error">Error loading theme data: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Editor - YFEvents</title>
    <link rel="stylesheet" href="/css/calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .theme-editor-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .theme-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .theme-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        
        .theme-section h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            border-bottom: 2px solid var(--light-gray);
            padding-bottom: 10px;
        }
        
        .variable-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .variable-item {
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            padding: 15px;
            background: #fafafa;
        }
        
        .variable-item label {
            display: block;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .variable-item input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--medium-gray);
            border-radius: 4px;
            font-size: 14px;
        }
        
        .variable-item input[type="color"] {
            height: 40px;
            padding: 2px;
        }
        
        .preset-item {
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #219a52;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .btn-danger {
            background: var(--accent-color);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-info {
            background: #cce7ff;
            border: 1px solid #99d3ff;
            color: #004085;
        }
        
        .preview-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            border: 1px solid var(--medium-gray);
            display: inline-block;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        .nav-links {
            margin-bottom: 30px;
        }
        
        .nav-links a {
            color: var(--secondary-color);
            text-decoration: none;
            margin-right: 20px;
            font-weight: bold;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="theme-editor-container">
        <div class="nav-links">
            <a href="/admin/"><i class="fas fa-arrow-left"></i> Back to Admin</a>
            <a href="/calendar.php"><i class="fas fa-calendar"></i> View Calendar</a>
        </div>
        
        <div class="theme-header">
            <h1><i class="fas fa-palette"></i> Theme Editor</h1>
            <p>Customize the appearance of your YFEvents calendar and admin interface</p>
        </div>
        
        <?= $message ?>
        
        <!-- Quick Actions -->
        <div class="theme-section">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="reset_to_default">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Reset all theme settings to default?')">
                        <i class="fas fa-undo"></i> Reset to Default
                    </button>
                </form>
                
                <a href="/modules/yftheme/api/theme" target="_blank" class="btn btn-primary">
                    <i class="fas fa-eye"></i> Preview Theme CSS
                </a>
                
                <button type="button" class="btn btn-success" onclick="generateCSS()">
                    <i class="fas fa-refresh"></i> Regenerate CSS
                </button>
            </div>
        </div>
        
        <!-- Theme Presets -->
        <?php if (!empty($presets)): ?>
        <div class="theme-section">
            <h3><i class="fas fa-swatchbook"></i> Theme Presets</h3>
            <?php foreach ($presets as $preset): ?>
            <div class="preset-item">
                <div>
                    <strong><?= htmlspecialchars($preset['name']) ?></strong>
                    <p style="margin: 5px 0 0 0; color: var(--dark-gray);"><?= htmlspecialchars($preset['description']) ?></p>
                </div>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="apply_preset">
                    <input type="hidden" name="preset_id" value="<?= $preset['id'] ?>">
                    <button type="submit" class="btn btn-primary">Apply</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Theme Variables by Category -->
        <?php if (!empty($variables)): ?>
        <?php 
        $variablesByCategory = [];
        foreach ($variables as $variable) {
            $categoryName = $variable['category_name'] ?? 'General';
            $variablesByCategory[$categoryName][] = $variable;
        }
        ?>
        
        <?php foreach ($variablesByCategory as $categoryName => $categoryVariables): ?>
        <div class="theme-section">
            <h3><i class="fas fa-cog"></i> <?= htmlspecialchars($categoryName) ?></h3>
            <div class="variable-grid">
                <?php foreach ($categoryVariables as $variable): ?>
                <div class="variable-item">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_variable">
                        <input type="hidden" name="variable_id" value="<?= $variable['id'] ?>">
                        
                        <label for="var_<?= $variable['id'] ?>">
                            <?= htmlspecialchars($variable['display_name']) ?>
                            <?php if ($variable['type'] === 'color'): ?>
                                <span class="color-preview" style="background-color: <?= htmlspecialchars($variable['default_value'] ?? '#000000') ?>"></span>
                            <?php endif; ?>
                        </label>
                        
                        <?php
                        $inputType = 'text';
                        $currentValue = $currentTheme[$variable['css_variable']] ?? $variable['default_value'] ?? '';
                        
                        switch ($variable['type']) {
                            case 'color':
                                $inputType = 'color';
                                break;
                            case 'number':
                                $inputType = 'number';
                                break;
                            case 'url':
                                $inputType = 'url';
                                break;
                        }
                        ?>
                        
                        <input 
                            type="<?= $inputType ?>" 
                            id="var_<?= $variable['id'] ?>" 
                            name="value" 
                            value="<?= htmlspecialchars($currentValue) ?>"
                            placeholder="<?= htmlspecialchars($variable['default_value'] ?? '') ?>"
                            onchange="this.form.submit()"
                        >
                        
                        <?php if ($variable['description']): ?>
                        <small style="color: var(--dark-gray); display: block; margin-top: 5px;">
                            <?= htmlspecialchars($variable['description']) ?>
                        </small>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php else: ?>
        <div class="theme-section">
            <h3><i class="fas fa-exclamation-triangle"></i> No Theme Variables Found</h3>
            <p>The theme system appears to not be properly initialized. Please check:</p>
            <ul>
                <li>Database tables have been created</li>
                <li>Default theme variables have been inserted</li>
                <li>ThemeService is properly configured</li>
            </ul>
            <a href="/modules/yftheme/install.php" class="btn btn-primary">Initialize Theme System</a>
        </div>
        <?php endif; ?>
        
        <!-- Live Preview -->
        <div class="preview-section">
            <h3><i class="fas fa-eye"></i> Live Preview</h3>
            <p>Changes will be applied immediately to the calendar. <a href="/calendar.php" target="_blank">Open Calendar in New Tab</a> to see changes.</p>
            
            <div style="border: 1px solid var(--light-gray); border-radius: var(--border-radius); padding: 20px; margin-top: 15px; background: var(--light-gray);">
                <h4 style="color: var(--primary-color);">Sample Calendar Elements</h4>
                <button class="btn btn-primary" style="margin: 5px;">Primary Button</button>
                <button class="btn btn-success" style="margin: 5px;">Success Button</button>
                <button class="btn btn-warning" style="margin: 5px;">Warning Button</button>
                <div style="background: white; padding: 15px; margin: 15px 0; border-radius: var(--border-radius); box-shadow: var(--shadow);">
                    <h5 style="color: var(--secondary-color);">Sample Event Card</h5>
                    <p>This is how events will appear with the current theme settings.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function generateCSS() {
        fetch('/modules/yftheme/api/regenerate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('CSS regenerated successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error regenerating CSS');
        });
    }
    
    // Update color previews when color inputs change
    document.addEventListener('DOMContentLoaded', function() {
        const colorInputs = document.querySelectorAll('input[type="color"]');
        colorInputs.forEach(input => {
            const preview = input.parentElement.querySelector('.color-preview');
            if (preview) {
                input.addEventListener('input', function() {
                    preview.style.backgroundColor = this.value;
                });
            }
        });
    });
    </script>
</body>
</html>