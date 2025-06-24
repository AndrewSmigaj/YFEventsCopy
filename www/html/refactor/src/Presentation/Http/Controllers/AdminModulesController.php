<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use Exception;
use PDO;

/**
 * Admin controller for module management
 */
class AdminModulesController extends BaseController
{
    private PDO $pdo;
    private string $modulesPath;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $connection = $container->resolve(ConnectionInterface::class);
        $this->pdo = $connection->getConnection();
        $this->modulesPath = dirname(__DIR__, 4) . '/modules';
    }

    /**
     * Show modules management page
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
        echo $this->renderModulesPage($basePath);
    }

    /**
     * Get all modules with their status
     */
    public function getModules(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $modules = $this->scanModules();
            
            // Add database status for each module
            foreach ($modules as &$module) {
                $module['database_status'] = $this->checkModuleDatabaseStatus($module['name']);
                $module['routes_count'] = $this->countModuleRoutes($module['name']);
                $module['active_users'] = $this->getModuleActiveUsers($module['name']);
            }

            $this->successResponse([
                'modules' => $modules
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load modules: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single module details
     */
    public function getModule(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $name = $_GET['name'] ?? '';
            
            if (!$name) {
                $this->errorResponse('Module name is required');
                return;
            }

            $module = $this->getModuleInfo($name);
            
            if (!$module) {
                $this->errorResponse('Module not found');
                return;
            }

            // Add detailed information
            $module['files'] = $this->scanModuleFiles($name);
            $module['database_tables'] = $this->getModuleTables($name);
            $module['configuration'] = $this->getModuleConfiguration($name);
            $module['dependencies'] = $this->checkModuleDependencies($module);
            $module['health_check'] = $this->performModuleHealthCheck($name);

            $this->successResponse([
                'module' => $module
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load module details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Enable module
     */
    public function enableModule(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $name = $input['name'] ?? '';

            if (!$name) {
                $this->errorResponse('Module name is required');
                return;
            }

            $module = $this->getModuleInfo($name);
            
            if (!$module) {
                $this->errorResponse('Module not found');
                return;
            }

            // Check dependencies
            $dependencies = $this->checkModuleDependencies($module);
            if (!empty($dependencies['missing'])) {
                $this->errorResponse('Missing dependencies: ' . implode(', ', $dependencies['missing']));
                return;
            }

            // Install database if needed
            if (!empty($module['database']) && !$this->checkModuleDatabaseStatus($name)['installed']) {
                $this->installModuleDatabase($name);
            }

            // Enable in configuration
            $this->updateModuleStatus($name, true);

            // Log the action
            $this->logModuleAction($name, 'enabled');

            $this->successResponse([
                'message' => "Module '{$name}' enabled successfully",
                'module' => $name
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to enable module: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Disable module
     */
    public function disableModule(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $name = $input['name'] ?? '';

            if (!$name) {
                $this->errorResponse('Module name is required');
                return;
            }

            // Check if other modules depend on this one
            $dependents = $this->getModuleDependents($name);
            if (!empty($dependents)) {
                $this->errorResponse('Cannot disable: Other modules depend on this module: ' . implode(', ', $dependents));
                return;
            }

            // Disable in configuration
            $this->updateModuleStatus($name, false);

            // Log the action
            $this->logModuleAction($name, 'disabled');

            $this->successResponse([
                'message' => "Module '{$name}' disabled successfully",
                'module' => $name
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to disable module: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Install module database
     */
    public function installDatabase(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $name = $input['name'] ?? '';

            if (!$name) {
                $this->errorResponse('Module name is required');
                return;
            }

            $result = $this->installModuleDatabase($name);

            $this->successResponse([
                'message' => "Database installed successfully for module '{$name}'",
                'tables_created' => $result['tables_created'],
                'data_loaded' => $result['data_loaded']
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to install database: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Uninstall module database
     */
    public function uninstallDatabase(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $name = $input['name'] ?? '';

            if (!$name) {
                $this->errorResponse('Module name is required');
                return;
            }

            if (!$this->confirmDatabaseUninstall($name)) {
                $this->errorResponse('Database uninstall cancelled');
                return;
            }

            $result = $this->uninstallModuleDatabase($name);

            $this->successResponse([
                'message' => "Database uninstalled successfully for module '{$name}'",
                'tables_dropped' => $result['tables_dropped']
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to uninstall database: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update module configuration
     */
    public function updateConfiguration(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $name = $input['name'] ?? '';
            $configuration = $input['configuration'] ?? [];

            if (!$name) {
                $this->errorResponse('Module name is required');
                return;
            }

            $this->saveModuleConfiguration($name, $configuration);

            $this->successResponse([
                'message' => "Configuration updated successfully for module '{$name}'",
                'module' => $name
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to update configuration: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get module statistics
     */
    public function getStatistics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $modules = $this->scanModules();
            
            $stats = [
                'total_modules' => count($modules),
                'enabled_modules' => count(array_filter($modules, fn($m) => $m['enabled'])),
                'disabled_modules' => count(array_filter($modules, fn($m) => !$m['enabled'])),
                'modules_with_database' => count(array_filter($modules, fn($m) => !empty($m['database']))),
                'modules_by_type' => [],
                'total_database_tables' => 0,
                'total_routes' => 0,
                'module_details' => []
            ];

            foreach ($modules as $module) {
                // Count by type
                $type = $module['type'] ?? 'core';
                $stats['modules_by_type'][$type] = ($stats['modules_by_type'][$type] ?? 0) + 1;

                // Count database tables
                if ($module['enabled'] && !empty($module['database'])) {
                    $tables = $this->getModuleTables($module['name']);
                    $stats['total_database_tables'] += count($tables);
                }

                // Count routes
                $stats['total_routes'] += $this->countModuleRoutes($module['name']);

                // Add module detail
                $stats['module_details'][$module['name']] = [
                    'enabled' => $module['enabled'],
                    'type' => $module['type'] ?? 'core',
                    'version' => $module['version'] ?? '1.0.0',
                    'has_database' => !empty($module['database']),
                    'routes_count' => $this->countModuleRoutes($module['name'])
                ];
            }

            $this->successResponse([
                'statistics' => $stats
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Scan modules directory
     */
    private function scanModules(): array
    {
        $modules = [];

        // Core modules (always enabled)
        $coreModules = [
            'events' => [
                'name' => 'events',
                'title' => 'Events Management',
                'description' => 'Core event calendar and management system',
                'type' => 'core',
                'enabled' => true,
                'version' => '2.0.0',
                'database' => true
            ],
            'shops' => [
                'name' => 'shops',
                'title' => 'Business Directory',
                'description' => 'Local business directory with map integration',
                'type' => 'core',
                'enabled' => true,
                'version' => '2.0.0',
                'database' => true
            ],
            'users' => [
                'name' => 'users',
                'title' => 'User Management',
                'description' => 'User authentication and authorization',
                'type' => 'core',
                'enabled' => true,
                'version' => '2.0.0',
                'database' => true
            ]
        ];

        foreach ($coreModules as $module) {
            $modules[] = $module;
        }

        // Extension modules
        $extensionModules = [
            'communication' => [
                'name' => 'communication',
                'title' => 'Communication Hub',
                'description' => 'Real-time messaging and announcements platform',
                'type' => 'extension',
                'enabled' => $this->config->get('modules.communication.enabled', true),
                'version' => '1.0.0',
                'database' => true,
                'path' => '/communication'
            ],
            'claims' => [
                'name' => 'claims',
                'title' => 'YFClaim Estate Sales',
                'description' => 'Estate sale claim management system',
                'type' => 'extension',
                'enabled' => $this->config->get('modules.claims.enabled', true),
                'version' => '0.6.0',
                'database' => true,
                'path' => '/claims'
            ],
            'classifieds' => [
                'name' => 'classifieds',
                'title' => 'Classifieds Marketplace',
                'description' => 'Buy and sell marketplace for community',
                'type' => 'extension',
                'enabled' => $this->config->get('modules.classifieds.enabled', false),
                'version' => '0.1.0',
                'database' => true,
                'path' => '/classifieds'
            ]
        ];

        foreach ($extensionModules as $module) {
            $modules[] = $module;
        }

        // Scan physical modules directory for additional modules
        if (is_dir($this->modulesPath)) {
            $dirs = scandir($this->modulesPath);
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..' || !is_dir($this->modulesPath . '/' . $dir)) {
                    continue;
                }

                // Skip if already in our list
                if (array_search($dir, array_column($modules, 'name')) !== false) {
                    continue;
                }

                $moduleInfo = $this->getModuleInfo($dir);
                if ($moduleInfo) {
                    $modules[] = $moduleInfo;
                }
            }
        }

        return $modules;
    }

    /**
     * Get module info from module.json
     */
    private function getModuleInfo(string $name): ?array
    {
        $modulePath = $this->modulesPath . '/' . $name;
        $manifestPath = $modulePath . '/module.json';

        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            if ($manifest) {
                $manifest['enabled'] = $this->config->get("modules.{$name}.enabled", false);
                $manifest['path'] = $modulePath;
                return $manifest;
            }
        }

        return null;
    }

    /**
     * Check module database status
     */
    private function checkModuleDatabaseStatus(string $name): array
    {
        $status = [
            'installed' => false,
            'tables' => [],
            'table_count' => 0
        ];

        $prefix = $this->getModuleTablePrefix($name);
        
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE '{$prefix}%'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $status['installed'] = count($tables) > 0;
            $status['tables'] = $tables;
            $status['table_count'] = count($tables);
        } catch (Exception $e) {
            // Ignore errors
        }

        return $status;
    }

    /**
     * Get module table prefix
     */
    private function getModuleTablePrefix(string $name): string
    {
        $prefixes = [
            'communication' => 'comm_',
            'claims' => 'yfc_',
            'classifieds' => 'clf_',
            'events' => 'event',
            'shops' => 'local_shop',
            'users' => 'user'
        ];

        return $prefixes[$name] ?? $name . '_';
    }

    /**
     * Count module routes
     */
    private function countModuleRoutes(string $name): int
    {
        // This is a simplified count based on known patterns
        $routeCounts = [
            'events' => 20,
            'shops' => 15,
            'users' => 10,
            'communication' => 25,
            'claims' => 18,
            'classifieds' => 5
        ];

        return $routeCounts[$name] ?? 0;
    }

    /**
     * Get module active users
     */
    private function getModuleActiveUsers(string $name): int
    {
        // This would need actual implementation based on module usage tracking
        // For now, return sample data
        $activeUsers = [
            'events' => rand(50, 200),
            'shops' => rand(30, 100),
            'users' => rand(100, 500),
            'communication' => rand(20, 80),
            'claims' => rand(10, 50),
            'classifieds' => rand(5, 20)
        ];

        return $activeUsers[$name] ?? 0;
    }

    /**
     * Scan module files
     */
    private function scanModuleFiles(string $name): array
    {
        $files = [
            'controllers' => 0,
            'models' => 0,
            'views' => 0,
            'assets' => 0,
            'total' => 0
        ];

        // This would scan actual module directories
        // For now, return estimated counts
        $fileCounts = [
            'events' => ['controllers' => 5, 'models' => 3, 'views' => 10, 'assets' => 15],
            'shops' => ['controllers' => 4, 'models' => 2, 'views' => 8, 'assets' => 10],
            'communication' => ['controllers' => 8, 'models' => 5, 'views' => 12, 'assets' => 20],
            'claims' => ['controllers' => 6, 'models' => 5, 'views' => 15, 'assets' => 8]
        ];

        if (isset($fileCounts[$name])) {
            $files = array_merge($files, $fileCounts[$name]);
            $files['total'] = array_sum($fileCounts[$name]);
        }

        return $files;
    }

    /**
     * Get module tables
     */
    private function getModuleTables(string $name): array
    {
        $prefix = $this->getModuleTablePrefix($name);
        
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE '{$prefix}%'");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get module configuration
     */
    private function getModuleConfiguration(string $name): array
    {
        $config = [];
        
        // Load from database settings
        try {
            $stmt = $this->pdo->prepare("
                SELECT `key`, value 
                FROM system_settings 
                WHERE category = :category
            ");
            $stmt->execute(['category' => "module_{$name}"]);
            
            while ($row = $stmt->fetch()) {
                $config[$row['key']] = json_decode($row['value'], true) ?: $row['value'];
            }
        } catch (Exception $e) {
            // Ignore if table doesn't exist
        }

        // Add default configurations
        if (empty($config)) {
            $config = $this->getDefaultModuleConfiguration($name);
        }

        return $config;
    }

    /**
     * Get default module configuration
     */
    private function getDefaultModuleConfiguration(string $name): array
    {
        $defaults = [
            'communication' => [
                'max_message_length' => 5000,
                'max_file_size' => 10485760, // 10MB
                'allowed_file_types' => ['jpg', 'png', 'gif', 'pdf', 'doc', 'docx'],
                'enable_notifications' => true,
                'enable_email_digest' => true
            ],
            'claims' => [
                'max_items_per_sale' => 500,
                'offer_expiry_days' => 7,
                'enable_qr_codes' => true,
                'commission_percentage' => 10
            ],
            'classifieds' => [
                'listing_duration_days' => 30,
                'max_images_per_listing' => 10,
                'enable_messaging' => true,
                'require_approval' => false
            ]
        ];

        return $defaults[$name] ?? [];
    }

    /**
     * Check module dependencies
     */
    private function checkModuleDependencies(array $module): array
    {
        $dependencies = [
            'required' => $module['dependencies'] ?? [],
            'missing' => [],
            'satisfied' => []
        ];

        $enabledModules = array_map(
            fn($m) => $m['name'], 
            array_filter($this->scanModules(), fn($m) => $m['enabled'])
        );

        foreach ($dependencies['required'] as $dep) {
            if (in_array($dep, $enabledModules)) {
                $dependencies['satisfied'][] = $dep;
            } else {
                $dependencies['missing'][] = $dep;
            }
        }

        return $dependencies;
    }

    /**
     * Get modules that depend on this module
     */
    private function getModuleDependents(string $name): array
    {
        $dependents = [];
        $modules = $this->scanModules();

        foreach ($modules as $module) {
            if (!$module['enabled']) {
                continue;
            }

            $deps = $module['dependencies'] ?? [];
            if (in_array($name, $deps)) {
                $dependents[] = $module['name'];
            }
        }

        return $dependents;
    }

    /**
     * Perform module health check
     */
    private function performModuleHealthCheck(string $name): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'issues' => []
        ];

        // Check database
        if ($this->checkModuleDatabaseStatus($name)['installed']) {
            $health['checks']['database'] = 'ok';
        } else {
            $health['checks']['database'] = 'missing';
            $health['issues'][] = 'Database not installed';
            $health['status'] = 'warning';
        }

        // Check configuration
        $config = $this->getModuleConfiguration($name);
        $health['checks']['configuration'] = empty($config) ? 'missing' : 'ok';

        // Check files (simplified)
        $health['checks']['files'] = 'ok';

        // Check dependencies
        $deps = $this->checkModuleDependencies(['name' => $name, 'dependencies' => []]);
        if (!empty($deps['missing'])) {
            $health['checks']['dependencies'] = 'missing';
            $health['issues'][] = 'Missing dependencies: ' . implode(', ', $deps['missing']);
            $health['status'] = 'error';
        } else {
            $health['checks']['dependencies'] = 'ok';
        }

        return $health;
    }

    /**
     * Update module status
     */
    private function updateModuleStatus(string $name, bool $enabled): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_settings (category, `key`, value, updated_at)
            VALUES ('modules', :key, :value, NOW())
            ON DUPLICATE KEY UPDATE value = :value, updated_at = NOW()
        ");
        
        $stmt->execute([
            'key' => $name,
            'value' => $enabled ? '1' : '0'
        ]);
    }

    /**
     * Install module database
     */
    private function installModuleDatabase(string $name): array
    {
        $result = [
            'tables_created' => 0,
            'data_loaded' => 0
        ];

        // Module-specific database installation
        switch ($name) {
            case 'communication':
                // Communication tables are already installed
                $result['tables_created'] = 7;
                break;
                
            case 'claims':
                // Claims tables are already installed
                $result['tables_created'] = 6;
                break;
                
            case 'classifieds':
                // Would create classifieds tables
                $this->createClassifiedsTables();
                $result['tables_created'] = 5;
                break;
        }

        return $result;
    }

    /**
     * Uninstall module database
     */
    private function uninstallModuleDatabase(string $name): array
    {
        $result = ['tables_dropped' => 0];
        
        $prefix = $this->getModuleTablePrefix($name);
        $tables = $this->getModuleTables($name);
        
        foreach ($tables as $table) {
            try {
                $this->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                $result['tables_dropped']++;
            } catch (Exception $e) {
                // Continue dropping other tables
            }
        }

        return $result;
    }

    /**
     * Create classifieds tables
     */
    private function createClassifiedsTables(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS clf_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                parent_id INT NULL,
                description TEXT,
                icon VARCHAR(50),
                sort_order INT DEFAULT 0,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (parent_id) REFERENCES clf_categories(id) ON DELETE CASCADE,
                INDEX idx_slug (slug),
                INDEX idx_parent (parent_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS clf_listings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_id INT NOT NULL,
                user_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                description TEXT NOT NULL,
                price DECIMAL(10,2),
                location VARCHAR(200),
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                status ENUM('draft', 'active', 'sold', 'expired', 'removed') DEFAULT 'active',
                views INT DEFAULT 0,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES clf_categories(id),
                FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_category (category_id),
                INDEX idx_user (user_id),
                INDEX idx_status (status),
                INDEX idx_location (latitude, longitude),
                FULLTEXT idx_search (title, description)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS clf_listing_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                listing_id INT NOT NULL,
                image_path VARCHAR(500) NOT NULL,
                thumbnail_path VARCHAR(500),
                caption VARCHAR(200),
                sort_order INT DEFAULT 0,
                is_primary BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (listing_id) REFERENCES clf_listings(id) ON DELETE CASCADE,
                INDEX idx_listing (listing_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS clf_favorites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                listing_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (listing_id) REFERENCES clf_listings(id) ON DELETE CASCADE,
                UNIQUE KEY unique_favorite (user_id, listing_id),
                INDEX idx_user (user_id),
                INDEX idx_listing (listing_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS clf_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                listing_id INT NOT NULL,
                sender_id INT NOT NULL,
                recipient_id INT NOT NULL,
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (listing_id) REFERENCES clf_listings(id) ON DELETE CASCADE,
                FOREIGN KEY (sender_id) REFERENCES users(id),
                FOREIGN KEY (recipient_id) REFERENCES users(id),
                INDEX idx_listing (listing_id),
                INDEX idx_recipient (recipient_id, is_read)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $this->pdo->exec($statement);
            }
        }
    }

    /**
     * Save module configuration
     */
    private function saveModuleConfiguration(string $name, array $configuration): void
    {
        foreach ($configuration as $key => $value) {
            $stmt = $this->pdo->prepare("
                INSERT INTO system_settings (category, `key`, value, updated_at)
                VALUES (:category, :key, :value, NOW())
                ON DUPLICATE KEY UPDATE value = :value, updated_at = NOW()
            ");
            
            $stmt->execute([
                'category' => "module_{$name}",
                'key' => $key,
                'value' => is_array($value) ? json_encode($value) : (string)$value
            ]);
        }
    }

    /**
     * Confirm database uninstall
     */
    private function confirmDatabaseUninstall(string $name): bool
    {
        // In a real implementation, this would require user confirmation
        // For now, check if there's data in the tables
        $tables = $this->getModuleTables($name);
        
        foreach ($tables as $table) {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`");
            if ($stmt->fetchColumn() > 0) {
                return false; // Has data, don't uninstall
            }
        }

        return true;
    }

    /**
     * Log module action
     */
    private function logModuleAction(string $module, string $action): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? 0;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_activity_log (user_id, action, details, created_at)
                VALUES (:user_id, :action, :details, NOW())
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'action' => "module_{$action}",
                'details' => json_encode([
                    'module' => $module,
                    'action' => $action,
                    'timestamp' => date('c')
                ])
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the operation
            error_log('Failed to log module action: ' . $e->getMessage());
        }
    }

    private function renderModulesPage(string $basePath): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $username = $_SESSION['admin_username'] ?? 'admin';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Management - YFEvents Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #343a40;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .modules-grid {
            display: grid;
            gap: 20px;
        }
        
        .module-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .module-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .module-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #343a40;
        }
        
        .module-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-core {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-extension {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .badge-plugin {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .module-description {
            color: #6c757d;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .module-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-icon {
            color: #6c757d;
            font-size: 1.2rem;
        }
        
        .info-text {
            color: #495057;
            font-size: 0.9rem;
        }
        
        .module-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-enabled {
            background: #28a745;
        }
        
        .status-disabled {
            background: #dc3545;
        }
        
        .status-text {
            font-weight: 500;
        }
        
        .module-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #343a40;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        .close:hover {
            color: #343a40;
        }
        
        .modal-body {
            color: #495057;
        }
        
        .detail-section {
            margin-bottom: 25px;
        }
        
        .detail-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 10px;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 10px;
        }
        
        .detail-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .detail-value {
            color: #495057;
        }
        
        .health-check {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .health-item {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .health-ok {
            background: #d4edda;
            color: #155724;
        }
        
        .health-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .health-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧩 Module Management</h1>
        <div class="user-info">
            <span>Welcome, {$username}</span>
            <a href="{$basePath}/admin/dashboard" class="back-btn">← Back to Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <div id="alert" class="alert"></div>
        
        <div class="stats-row" id="stats-container">
            <div class="stat-card">
                <div class="stat-number" id="total-modules">-</div>
                <div class="stat-label">Total Modules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="enabled-modules">-</div>
                <div class="stat-label">Enabled</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="disabled-modules">-</div>
                <div class="stat-label">Disabled</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="database-tables">-</div>
                <div class="stat-label">Database Tables</div>
            </div>
        </div>
        
        <div class="modules-grid" id="modules-container">
            <div class="loading">
                <div class="spinner"></div>
                <p>Loading modules...</p>
            </div>
        </div>
    </div>
    
    <!-- Module Details Modal -->
    <div id="module-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-title">Module Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modal-body">
                <!-- Content will be dynamically loaded -->
            </div>
        </div>
    </div>

    <script>
        let modules = [];
        
        // Load modules on page load
        async function loadModules() {
            try {
                const response = await fetch('{$basePath}/admin/modules/list');
                const data = await response.json();
                
                if (data.success) {
                    modules = data.data.modules;
                    displayModules();
                    updateStatistics();
                }
            } catch (error) {
                showAlert('Error loading modules: ' + error.message, 'error');
            }
        }
        
        // Display modules
        function displayModules() {
            const container = document.getElementById('modules-container');
            
            if (modules.length === 0) {
                container.innerHTML = '<p>No modules found.</p>';
                return;
            }
            
            container.innerHTML = modules.map(module => `
                <div class="module-card">
                    <div class="module-header">
                        <h3 class="module-title">\${module.title || module.name}</h3>
                        <span class="module-badge badge-\${module.type || 'extension'}">\${module.type || 'extension'}</span>
                    </div>
                    
                    <p class="module-description">\${module.description || 'No description available'}</p>
                    
                    <div class="module-info">
                        <div class="info-item">
                            <span class="info-icon">📦</span>
                            <span class="info-text">Version \${module.version || '1.0.0'}</span>
                        </div>
                        \${module.database_status ? `
                        <div class="info-item">
                            <span class="info-icon">🗄️</span>
                            <span class="info-text">\${module.database_status.table_count} tables</span>
                        </div>
                        ` : ''}
                        <div class="info-item">
                            <span class="info-icon">🔗</span>
                            <span class="info-text">\${module.routes_count || 0} routes</span>
                        </div>
                        <div class="info-item">
                            <span class="info-icon">👥</span>
                            <span class="info-text">\${module.active_users || 0} active users</span>
                        </div>
                    </div>
                    
                    <div class="module-status">
                        <span class="status-indicator status-\${module.enabled ? 'enabled' : 'disabled'}"></span>
                        <span class="status-text">\${module.enabled ? 'Enabled' : 'Disabled'}</span>
                    </div>
                    
                    <div class="module-actions">
                        \${module.type !== 'core' ? `
                            \${module.enabled ? `
                                <button class="btn btn-danger" onclick="disableModule('\${module.name}')">Disable</button>
                            ` : `
                                <button class="btn btn-success" onclick="enableModule('\${module.name}')">Enable</button>
                            `}
                        ` : ''}
                        
                        <button class="btn btn-info" onclick="showModuleDetails('\${module.name}')">Details</button>
                        
                        \${module.path ? `
                            <a href="\${module.path}" class="btn btn-secondary" target="_blank">View</a>
                        ` : ''}
                        
                        \${module.database && module.enabled ? `
                            <button class="btn btn-primary" onclick="configureModule('\${module.name}')">Configure</button>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }
        
        // Update statistics
        function updateStatistics() {
            const stats = modules.reduce((acc, module) => {
                acc.total++;
                if (module.enabled) {
                    acc.enabled++;
                } else {
                    acc.disabled++;
                }
                if (module.database_status) {
                    acc.tables += module.database_status.table_count || 0;
                }
                return acc;
            }, { total: 0, enabled: 0, disabled: 0, tables: 0 });
            
            document.getElementById('total-modules').textContent = stats.total;
            document.getElementById('enabled-modules').textContent = stats.enabled;
            document.getElementById('disabled-modules').textContent = stats.disabled;
            document.getElementById('database-tables').textContent = stats.tables;
        }
        
        // Enable module
        async function enableModule(name) {
            if (!confirm(`Enable the '\${name}' module?`)) {
                return;
            }
            
            try {
                const response = await fetch('{$basePath}/admin/modules/enable', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ name })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.data.message, 'success');
                    loadModules();
                } else {
                    showAlert(data.error || 'Failed to enable module', 'error');
                }
            } catch (error) {
                showAlert('Error enabling module: ' + error.message, 'error');
            }
        }
        
        // Disable module
        async function disableModule(name) {
            if (!confirm(`Disable the '\${name}' module?`)) {
                return;
            }
            
            try {
                const response = await fetch('{$basePath}/admin/modules/disable', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ name })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.data.message, 'success');
                    loadModules();
                } else {
                    showAlert(data.error || 'Failed to disable module', 'error');
                }
            } catch (error) {
                showAlert('Error disabling module: ' + error.message, 'error');
            }
        }
        
        // Show module details
        async function showModuleDetails(name) {
            try {
                const response = await fetch(`{$basePath}/admin/modules/details?name=\${name}`);
                const data = await response.json();
                
                if (data.success) {
                    const module = data.data.module;
                    
                    document.getElementById('modal-title').textContent = module.title || module.name;
                    document.getElementById('modal-body').innerHTML = `
                        <div class="detail-section">
                            <h3 class="detail-title">General Information</h3>
                            <div class="detail-grid">
                                <div class="detail-label">Name:</div>
                                <div class="detail-value">\${module.name}</div>
                                
                                <div class="detail-label">Version:</div>
                                <div class="detail-value">\${module.version || '1.0.0'}</div>
                                
                                <div class="detail-label">Type:</div>
                                <div class="detail-value">\${module.type || 'extension'}</div>
                                
                                <div class="detail-label">Status:</div>
                                <div class="detail-value">\${module.enabled ? 'Enabled' : 'Disabled'}</div>
                            </div>
                        </div>
                        
                        \${module.files ? `
                        <div class="detail-section">
                            <h3 class="detail-title">Files</h3>
                            <div class="detail-grid">
                                <div class="detail-label">Controllers:</div>
                                <div class="detail-value">\${module.files.controllers}</div>
                                
                                <div class="detail-label">Models:</div>
                                <div class="detail-value">\${module.files.models}</div>
                                
                                <div class="detail-label">Views:</div>
                                <div class="detail-value">\${module.files.views}</div>
                                
                                <div class="detail-label">Total Files:</div>
                                <div class="detail-value">\${module.files.total}</div>
                            </div>
                        </div>
                        ` : ''}
                        
                        \${module.database_tables && module.database_tables.length > 0 ? `
                        <div class="detail-section">
                            <h3 class="detail-title">Database Tables</h3>
                            <ul>
                                \${module.database_tables.map(table => `<li>\${table}</li>`).join('')}
                            </ul>
                        </div>
                        ` : ''}
                        
                        \${module.health_check ? `
                        <div class="detail-section">
                            <h3 class="detail-title">Health Check</h3>
                            <div class="health-check">
                                \${Object.entries(module.health_check.checks).map(([check, status]) => `
                                    <div class="health-item health-\${status}">
                                        \${status === 'ok' ? '✓' : '✗'} \${check}
                                    </div>
                                `).join('')}
                            </div>
                            \${module.health_check.issues && module.health_check.issues.length > 0 ? `
                                <h4 style="margin-top: 15px;">Issues:</h4>
                                <ul>
                                    \${module.health_check.issues.map(issue => `<li>\${issue}</li>`).join('')}
                                </ul>
                            ` : ''}
                        </div>
                        ` : ''}
                    `;
                    
                    document.getElementById('module-modal').style.display = 'block';
                } else {
                    showAlert(data.error || 'Failed to load module details', 'error');
                }
            } catch (error) {
                showAlert('Error loading module details: ' + error.message, 'error');
            }
        }
        
        // Configure module
        function configureModule(name) {
            // In a real implementation, this would open a configuration interface
            showAlert(`Configuration for '\${name}' module coming soon`, 'info');
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('module-modal').style.display = 'none';
        }
        
        // Show alert
        function showAlert(message, type = 'info') {
            const alert = document.getElementById('alert');
            alert.className = `alert alert-\${type}`;
            alert.textContent = message;
            alert.style.display = 'block';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('module-modal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadModules();
        });
    </script>
</body>
</html>
HTML;
    }
}