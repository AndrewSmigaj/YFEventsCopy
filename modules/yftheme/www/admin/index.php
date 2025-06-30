<?php

/**
 * YFTheme Admin Interface
 * Visual theme editor for managing site appearance
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use YFEvents\Modules\YFTheme\Models\ThemeModel;
use YFEvents\Modules\YFTheme\Services\ThemeService;
use YFEvents\Modules\YFAuth\Middleware\AuthMiddleware;

global $pdo;

// Initialize services
$themeModel = new ThemeModel($pdo);
$themeService = new ThemeService($pdo);
$authMiddleware = new AuthMiddleware($pdo);

// Check authentication and permissions
$authMiddleware->adminAuth(function($user) use ($themeModel, $themeService) {
    // Get theme data
    $categories = $themeModel->getVariablesByCategory(false);
    $presets = $themeModel->getPresets();
    $activePreset = $themeModel->getActivePreset();
    
    renderThemeEditor($categories, $presets, $activePreset, $user);
}, ['theme.view']);

function renderThemeEditor($categories, $presets, $activePreset, $user) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Editor - YFEvents</title>
    
    <!-- Theme CSS -->
    <link rel="stylesheet" href="/modules/yftheme/api/theme">
    
    <!-- Theme Editor CSS -->
    <link rel="stylesheet" href="/modules/yftheme/www/assets/css/theme-editor.css">
    
    <!-- Color picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/classic.min.css">
    
    <!-- Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="theme-editor-body">
    <!-- Header -->
    <header class="theme-editor-header">
        <div class="header-content">
            <h1><i class="material-icons">palette</i> Theme Editor</h1>
            
            <div class="header-actions">
                <button id="save-theme" class="btn btn-primary">
                    <i class="material-icons">save</i> Save Changes
                </button>
                
                <button id="reset-theme" class="btn btn-secondary">
                    <i class="material-icons">refresh</i> Reset
                </button>
                
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" data-toggle="dropdown">
                        <i class="material-icons">more_vert</i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="#" id="export-theme">Export Theme</a>
                        <a href="#" id="import-theme">Import Theme</a>
                        <a href="#" id="view-history">View History</a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="theme-editor-container">
        <!-- Sidebar -->
        <aside class="theme-sidebar">
            <!-- Presets -->
            <div class="sidebar-section">
                <h3><i class="material-icons">style</i> Presets</h3>
                <div class="preset-list">
                    <?php foreach ($presets as $preset): ?>
                    <div class="preset-item <?= $preset['is_active'] ? 'active' : '' ?>" 
                         data-preset-id="<?= $preset['id'] ?>">
                        <div class="preset-info">
                            <h4><?= htmlspecialchars($preset['display_name']) ?></h4>
                            <p><?= htmlspecialchars($preset['description']) ?></p>
                        </div>
                        
                        <div class="preset-actions">
                            <button class="btn-icon apply-preset" title="Apply Preset">
                                <i class="material-icons">check</i>
                            </button>
                            
                            <?php if (!$preset['is_default']): ?>
                            <button class="btn-icon delete-preset" title="Delete Preset">
                                <i class="material-icons">delete</i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <button class="btn btn-outline create-preset">
                        <i class="material-icons">add</i> Create Preset
                    </button>
                </div>
            </div>
            
            <!-- Categories -->
            <div class="sidebar-section">
                <h3><i class="material-icons">category</i> Categories</h3>
                <nav class="category-nav">
                    <?php foreach ($categories as $category): ?>
                    <a href="#category-<?= $category['id'] ?>" class="category-link" 
                       data-category="<?= $category['id'] ?>">
                        <i class="material-icons"><?= $category['icon'] ?></i>
                        <?= htmlspecialchars($category['display_name']) ?>
                        <span class="variable-count"><?= count($category['variables']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            
            <!-- Search -->
            <div class="sidebar-section">
                <h3><i class="material-icons">search</i> Search</h3>
                <div class="search-box">
                    <input type="text" id="variable-search" placeholder="Search variables...">
                    <i class="material-icons search-icon">search</i>
                </div>
            </div>
        </aside>
        
        <!-- Editor Panel -->
        <main class="theme-editor-main">
            <!-- Categories -->
            <?php foreach ($categories as $category): ?>
            <section class="category-section" id="category-<?= $category['id'] ?>">
                <div class="category-header">
                    <h2>
                        <i class="material-icons"><?= $category['icon'] ?></i>
                        <?= htmlspecialchars($category['display_name']) ?>
                    </h2>
                    <p><?= htmlspecialchars($category['description']) ?></p>
                </div>
                
                <div class="variables-grid">
                    <?php foreach ($category['variables'] as $variable): ?>
                    <div class="variable-item" data-variable-id="<?= $variable['id'] ?>" 
                         data-variable-name="<?= $variable['name'] ?>">
                        <div class="variable-header">
                            <label for="var-<?= $variable['id'] ?>">
                                <?= htmlspecialchars($variable['display_name']) ?>
                            </label>
                            
                            <?php if ($variable['description']): ?>
                            <i class="material-icons help-icon" title="<?= htmlspecialchars($variable['description']) ?>">
                                help_outline
                            </i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="variable-control">
                            <?php renderVariableControl($variable); ?>
                        </div>
                        
                        <div class="variable-info">
                            <code><?= htmlspecialchars($variable['css_variable']) ?></code>
                            
                            <?php if ($variable['current_value'] !== $variable['default_value']): ?>
                            <button class="btn-icon reset-variable" title="Reset to default">
                                <i class="material-icons">refresh</i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
        </main>
        
        <!-- Preview Panel -->
        <aside class="theme-preview">
            <div class="preview-header">
                <h3><i class="material-icons">visibility</i> Live Preview</h3>
                
                <div class="preview-controls">
                    <select id="preview-mode">
                        <option value="components">Components</option>
                        <option value="page">Full Page</option>
                        <option value="iframe">In Frame</option>
                    </select>
                    
                    <button id="refresh-preview" class="btn-icon" title="Refresh Preview">
                        <i class="material-icons">refresh</i>
                    </button>
                </div>
            </div>
            
            <div class="preview-content">
                <div id="component-preview" class="preview-mode active">
                    <?php renderComponentPreview(); ?>
                </div>
                
                <div id="page-preview" class="preview-mode">
                    <iframe id="preview-iframe" src="/admin/"></iframe>
                </div>
                
                <div id="iframe-preview" class="preview-mode">
                    <iframe id="full-preview-iframe" src="/"></iframe>
                </div>
            </div>
        </aside>
    </div>
    
    <!-- Modals -->
    <div id="create-preset-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Preset</h3>
                <button class="btn-icon close-modal">
                    <i class="material-icons">close</i>
                </button>
            </div>
            
            <form id="create-preset-form">
                <div class="form-group">
                    <label for="preset-name">Name</label>
                    <input type="text" id="preset-name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="preset-display-name">Display Name</label>
                    <input type="text" id="preset-display-name" name="display_name" required>
                </div>
                
                <div class="form-group">
                    <label for="preset-description">Description</label>
                    <textarea id="preset-description" name="description"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Preset</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="import-theme-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Import Theme</h3>
                <button class="btn-icon close-modal">
                    <i class="material-icons">close</i>
                </button>
            </div>
            
            <form id="import-theme-form">
                <div class="form-group">
                    <label for="theme-file">Theme File</label>
                    <input type="file" id="theme-file" name="theme_file" accept=".json">
                </div>
                
                <div class="form-group">
                    <label for="theme-json">Or paste JSON</label>
                    <textarea id="theme-json" name="theme_json" rows="10" placeholder="Paste theme JSON here..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import Theme</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-spinner">
            <i class="material-icons rotating">refresh</i>
            <p>Applying changes...</p>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.min.js"></script>
    <script src="/modules/yftheme/www/assets/js/color-picker.js"></script>
    <script src="/modules/yftheme/www/assets/js/theme-editor.js"></script>
    
    <script>
        // Initialize theme editor
        document.addEventListener('DOMContentLoaded', function() {
            window.ThemeEditor.init({
                apiUrl: '/modules/yftheme/api',
                csrfToken: '<?= $authMiddleware->getCSRFToken() ?>',
                categories: <?= json_encode($categories) ?>,
                presets: <?= json_encode($presets) ?>,
                activePreset: <?= json_encode($activePreset) ?>,
                user: {
                    id: <?= $user->id ?>,
                    permissions: <?= json_encode(array_column($user->getPermissions(), 'name')) ?>
                }
            });
        });
    </script>
</body>
</html>

<?php
}

function renderVariableControl($variable) {
    $value = $variable['current_value'] ?? $variable['default_value'];
    $id = "var-{$variable['id']}";
    
    switch ($variable['type']) {
        case 'color':
            echo "<div class='color-picker-wrapper'>";
            echo "<input type='text' id='{$id}' class='color-input' value='{$value}' data-type='color'>";
            echo "<div class='color-preview' style='background-color: {$value}'></div>";
            echo "</div>";
            break;
            
        case 'size':
        case 'spacing':
            $unit = 'px';
            $numValue = preg_replace('/[^0-9.-]/', '', $value);
            if (preg_match('/[a-z%]+$/', $value, $matches)) {
                $unit = $matches[0];
            }
            
            echo "<div class='size-input-wrapper'>";
            echo "<input type='number' id='{$id}' class='size-input' value='{$numValue}' data-type='{$variable['type']}'>";
            echo "<select class='unit-select'>";
            foreach (['px', 'em', 'rem', '%', 'vh', 'vw'] as $u) {
                $selected = $u === $unit ? 'selected' : '';
                echo "<option value='{$u}' {$selected}>{$u}</option>";
            }
            echo "</select>";
            echo "</div>";
            break;
            
        case 'number':
            $constraints = $variable['constraints'] ?: [];
            $min = $constraints['min'] ?? '';
            $max = $constraints['max'] ?? '';
            $step = $constraints['step'] ?? 'any';
            
            echo "<input type='number' id='{$id}' class='number-input' value='{$value}' ";
            echo "min='{$min}' max='{$max}' step='{$step}' data-type='number'>";
            break;
            
        case 'select':
            echo "<select id='{$id}' class='select-input' data-type='select'>";
            $options = $variable['options'] ?: [];
            foreach ($options as $option) {
                $selected = $option === $value ? 'selected' : '';
                echo "<option value='{$option}' {$selected}>{$option}</option>";
            }
            echo "</select>";
            break;
            
        case 'font':
            echo "<input type='text' id='{$id}' class='font-input' value='{$value}' ";
            echo "placeholder='Font family...' data-type='font'>";
            break;
            
        case 'gradient':
            echo "<input type='text' id='{$id}' class='gradient-input' value='{$value}' ";
            echo "placeholder='CSS gradient...' data-type='gradient'>";
            break;
            
        case 'shadow':
            echo "<input type='text' id='{$id}' class='shadow-input' value='{$value}' ";
            echo "placeholder='CSS box-shadow...' data-type='shadow'>";
            break;
            
        case 'border':
            echo "<input type='text' id='{$id}' class='border-input' value='{$value}' ";
            echo "placeholder='CSS border...' data-type='border'>";
            break;
            
        default:
            echo "<input type='text' id='{$id}' class='text-input' value='{$value}' data-type='text'>";
            break;
    }
}

function renderComponentPreview() {
?>
<div class="component-preview-content">
    <!-- Typography -->
    <section class="preview-section">
        <h3>Typography</h3>
        <h1>Heading 1</h1>
        <h2>Heading 2</h2>
        <h3>Heading 3</h3>
        <p>This is a paragraph with <a href="#">a link</a> and some <strong>bold text</strong>.</p>
        <p class="text-small">Small text example</p>
        <p class="text-large">Large text example</p>
    </section>
    
    <!-- Buttons -->
    <section class="preview-section">
        <h3>Buttons</h3>
        <button class="btn btn-primary">Primary Button</button>
        <button class="btn btn-secondary">Secondary Button</button>
        <button class="btn btn-outline">Outline Button</button>
    </section>
    
    <!-- Cards -->
    <section class="preview-section">
        <h3>Cards</h3>
        <div class="card">
            <h4>Card Title</h4>
            <p>This is a card with some content. Cards are great for organizing information.</p>
            <button class="btn btn-primary">Action</button>
        </div>
    </section>
    
    <!-- Forms -->
    <section class="preview-section">
        <h3>Forms</h3>
        <form class="preview-form">
            <div class="form-group">
                <label for="preview-input">Text Input</label>
                <input type="text" id="preview-input" class="form-control" placeholder="Enter text...">
            </div>
            
            <div class="form-group">
                <label for="preview-select">Select</label>
                <select id="preview-select" class="form-control">
                    <option>Option 1</option>
                    <option>Option 2</option>
                    <option>Option 3</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="preview-textarea">Textarea</label>
                <textarea id="preview-textarea" class="form-control" rows="3" placeholder="Enter message..."></textarea>
            </div>
        </form>
    </section>
    
    <!-- Alerts -->
    <section class="preview-section">
        <h3>Alerts</h3>
        <div class="alert alert-success">Success alert</div>
        <div class="alert alert-warning">Warning alert</div>
        <div class="alert alert-error">Error alert</div>
        <div class="alert alert-info">Info alert</div>
    </section>
    
    <!-- Colors -->
    <section class="preview-section">
        <h3>Colors</h3>
        <div class="color-swatches">
            <div class="color-swatch bg-primary">Primary</div>
            <div class="color-swatch bg-secondary">Secondary</div>
            <div class="color-swatch bg-accent">Accent</div>
            <div class="color-swatch bg-success">Success</div>
            <div class="color-swatch bg-warning">Warning</div>
            <div class="color-swatch bg-error">Error</div>
        </div>
    </section>
</div>
<?php
}
?>