<?php
/**
 * Module Management Admin Interface
 * 
 * Allows enabling/disabling modules and viewing module information
 */

require_once __DIR__ . '/bootstrap.php';

use YFEvents\Infrastructure\Config\ConfigManager;

$configManager = ConfigManager::getInstance();
$modulesConfig = require __DIR__ . '/../src/Infrastructure/Config/modules.php';
$modules = $modulesConfig['modules'];

// Handle module enable/disable actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $moduleId = $_POST['module_id'] ?? '';
    $action = $_POST['action'];
    
    if (isset($modules[$moduleId])) {
        if ($action === 'toggle') {
            $modules[$moduleId]['enabled'] = !$modules[$moduleId]['enabled'];
            
            // Save updated module configuration
            $configContent = "<?php\n/**\n * Module Configuration\n * \n * Define all available modules and their settings\n */\n\nreturn " . var_export(['modules' => $modules], true) . ";";
            file_put_contents(__DIR__ . '/../src/Infrastructure/Config/modules.php', $configContent);
            
            header('Location: modules.php?success=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Management - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .module-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .module-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .module-card.disabled {
            background-color: #f8f9fa;
            opacity: 0.7;
        }
        
        .module-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .module-status {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .status-enabled {
            background-color: #28a745;
            color: white;
        }
        
        .status-disabled {
            background-color: #6c757d;
            color: white;
        }
        
        .version-badge {
            background-color: #e9ecef;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .admin-nav {
            background-color: #2c3e50;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .admin-nav a {
            color: #ecf0f1;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin: 0 0.5rem;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .admin-nav a:hover {
            background-color: #34495e;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 28px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 28px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #28a745;
        }
        
        input:checked + .slider:before {
            transform: translateX(32px);
        }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="container">
            <a href="/refactor/admin/dashboard">← Back to Dashboard</a>
            <a href="/refactor/admin/modules.php" class="active">Module Management</a>
        </div>
    </nav>

    <div class="container">
        <div class="row mb-4">
            <div class="col">
                <h1>Module Management</h1>
                <p class="text-muted">Enable or disable modules to control which features are available in YFEvents</p>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Module status updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <?php foreach ($modules as $moduleId => $module): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="module-card position-relative <?= !$module['enabled'] ? 'disabled' : '' ?>">
                        <div class="module-status">
                            <span class="status-badge <?= $module['enabled'] ? 'status-enabled' : 'status-disabled' ?>">
                                <?= $module['enabled'] ? 'Enabled' : 'Disabled' ?>
                            </span>
                        </div>
                        
                        <div class="text-center">
                            <div class="module-icon"><?= $module['icon'] ?></div>
                            <h5><?= htmlspecialchars($module['name']) ?></h5>
                            <span class="version-badge">v<?= htmlspecialchars($module['version']) ?></span>
                        </div>
                        
                        <p class="mt-3 mb-4"><?= htmlspecialchars($module['description']) ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="module_id" value="<?= htmlspecialchars($moduleId) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" <?= $module['enabled'] ? 'checked' : '' ?> 
                                           onchange="this.form.submit()"
                                           <?= in_array($moduleId, ['events', 'shops']) ? 'disabled title="Core modules cannot be disabled"' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </form>
                            
                            <?php if ($module['enabled'] && $module['admin_menu'] && isset($module['admin_menu']['items'][0])): ?>
                                <a href="<?= htmlspecialchars($module['admin_menu']['items'][0]['url']) ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    Manage →
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (in_array($moduleId, ['events', 'shops'])): ?>
                            <small class="text-muted d-block mt-2">Core module (always enabled)</small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row mt-5">
            <div class="col">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Module System Information</h5>
                        <p class="card-text">The module system allows you to control which features are available in YFEvents. Core modules (Events and Shops) cannot be disabled as they provide essential functionality.</p>
                        <ul>
                            <li><strong>Events Calendar</strong> - Core event management functionality</li>
                            <li><strong>Local Business Directory</strong> - Core shop/business directory</li>
                            <li><strong>YFClaim</strong> - Estate sale claiming platform</li>
                            <li><strong>YF Classifieds</strong> - Local classified ads (coming soon)</li>
                            <li><strong>YFAuth</strong> - User authentication system</li>
                            <li><strong>YFTheme</strong> - Theme customization</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>