<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use Exception;
use PDO;

/**
 * Admin controller for system settings management
 */
class AdminSettingsController extends BaseController
{
    private PDO $pdo;
    private string $settingsPath;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $connection = $container->resolve(ConnectionInterface::class);
        $this->pdo = $connection->getConnection();
        $this->settingsPath = dirname(__DIR__, 4) . '/config';
    }

    /**
     * Show system settings page
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
        echo $this->renderSettingsPage($basePath);
    }

    /**
     * Get all system settings
     */
    public function getSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $settings = [];

            // General settings
            $settings['general'] = [
                'site_name' => $this->config->get('app.name', 'YFEvents'),
                'site_description' => $this->config->get('app.description', ''),
                'timezone' => $this->config->get('app.timezone', 'America/Los_Angeles'),
                'admin_email' => $this->config->get('app.admin_email', ''),
                'maintenance_mode' => $this->config->get('app.maintenance_mode', false),
                'debug_mode' => $this->config->get('app.debug', false)
            ];

            // Database settings (sanitized)
            $settings['database'] = [
                'host' => $this->config->get('database.host', 'localhost'),
                'port' => $this->config->get('database.port', 3306),
                'database' => $this->config->get('database.database', ''),
                'charset' => $this->config->get('database.charset', 'utf8mb4'),
                'connection_status' => $this->checkDatabaseConnection()
            ];

            // Email settings
            $settings['email'] = [
                'enabled' => $this->config->get('mail.enabled', false),
                'driver' => $this->config->get('mail.driver', 'smtp'),
                'host' => $this->config->get('mail.host', ''),
                'port' => $this->config->get('mail.port', 587),
                'encryption' => $this->config->get('mail.encryption', 'tls'),
                'from_address' => $this->config->get('mail.from.address', ''),
                'from_name' => $this->config->get('mail.from.name', '')
            ];

            // API settings
            $settings['api'] = [
                'google_maps_key' => $this->maskApiKey($this->config->get('services.google.maps_api_key', '')),
                'rate_limit' => $this->config->get('api.rate_limit', 60),
                'cors_enabled' => $this->config->get('api.cors_enabled', true),
                'cors_origins' => $this->config->get('api.cors_origins', ['*'])
            ];

            // Cache settings
            $settings['cache'] = [
                'driver' => $this->config->get('cache.driver', 'file'),
                'ttl' => $this->config->get('cache.ttl', 3600),
                'enabled' => $this->config->get('cache.enabled', true)
            ];

            // Security settings
            $settings['security'] = [
                'session_lifetime' => $this->config->get('security.session_lifetime', 120),
                'password_min_length' => $this->config->get('security.password_min_length', 8),
                'max_login_attempts' => $this->config->get('security.max_login_attempts', 5),
                'lockout_duration' => $this->config->get('security.lockout_duration', 30)
            ];

            // Module settings
            $settings['modules'] = [
                'events' => $this->config->get('modules.events.enabled', true),
                'shops' => $this->config->get('modules.shops.enabled', true),
                'claims' => $this->config->get('modules.claims.enabled', true),
                'communication' => $this->config->get('modules.communication.enabled', true),
                'classifieds' => $this->config->get('modules.classifieds.enabled', false)
            ];

            $this->successResponse([
                'settings' => $settings
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update system setting
     */
    public function updateSetting(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $category = $input['category'] ?? '';
            $key = $input['key'] ?? '';
            $value = $input['value'] ?? null;

            if (!$category || !$key) {
                $this->errorResponse('Category and key are required');
                return;
            }

            // Validate and update setting based on category
            switch ($category) {
                case 'general':
                    $this->updateGeneralSetting($key, $value);
                    break;
                
                case 'email':
                    $this->updateEmailSetting($key, $value);
                    break;
                
                case 'api':
                    $this->updateApiSetting($key, $value);
                    break;
                
                case 'security':
                    $this->updateSecuritySetting($key, $value);
                    break;
                
                case 'modules':
                    $this->updateModuleSetting($key, $value);
                    break;
                
                default:
                    $this->errorResponse('Invalid settings category');
                    return;
            }

            // Log the change
            $this->logSettingChange($category, $key, $value);

            $this->successResponse([
                'message' => 'Setting updated successfully',
                'category' => $category,
                'key' => $key
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to update setting: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk update settings
     */
    public function updateBulkSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $settings = $input['settings'] ?? [];
            $updated = [];

            foreach ($settings as $category => $categorySettings) {
                foreach ($categorySettings as $key => $value) {
                    try {
                        switch ($category) {
                            case 'general':
                                $this->updateGeneralSetting($key, $value);
                                break;
                            case 'email':
                                $this->updateEmailSetting($key, $value);
                                break;
                            case 'api':
                                $this->updateApiSetting($key, $value);
                                break;
                            case 'security':
                                $this->updateSecuritySetting($key, $value);
                                break;
                            case 'modules':
                                $this->updateModuleSetting($key, $value);
                                break;
                        }
                        $updated[] = "$category.$key";
                    } catch (Exception $e) {
                        // Log individual errors but continue
                        error_log("Failed to update $category.$key: " . $e->getMessage());
                    }
                }
            }

            $this->successResponse([
                'message' => 'Settings updated successfully',
                'updated' => $updated,
                'count' => count($updated)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to update settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get system information
     */
    public function getSystemInfo(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $info = [
                'php' => [
                    'version' => PHP_VERSION,
                    'sapi' => PHP_SAPI,
                    'extensions' => get_loaded_extensions(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size')
                ],
                'server' => [
                    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'hostname' => gethostname(),
                    'os' => PHP_OS,
                    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
                ],
                'database' => [
                    'version' => $this->getDatabaseVersion(),
                    'size' => $this->getDatabaseSize(),
                    'tables' => $this->getDatabaseTableCount()
                ],
                'storage' => [
                    'disk_total' => disk_total_space('/'),
                    'disk_free' => disk_free_space('/'),
                    'disk_used' => disk_total_space('/') - disk_free_space('/')
                ],
                'application' => [
                    'version' => '2.0.0',
                    'environment' => $this->config->get('app.env', 'production'),
                    'debug_mode' => $this->config->get('app.debug', false),
                    'base_path' => dirname(__DIR__, 4)
                ]
            ];

            $this->successResponse([
                'system_info' => $info
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load system info: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clear application cache
     */
    public function clearCache(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $cachePath = dirname(__DIR__, 4) . '/storage/cache';
            $cleared = 0;

            if (is_dir($cachePath)) {
                $files = glob($cachePath . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                        $cleared++;
                    }
                }
            }

            $this->successResponse([
                'message' => 'Cache cleared successfully',
                'files_cleared' => $cleared
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to clear cache: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export settings
     */
    public function exportSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $settings = [
                'general' => [
                    'site_name' => $this->config->get('app.name'),
                    'site_description' => $this->config->get('app.description'),
                    'timezone' => $this->config->get('app.timezone'),
                    'admin_email' => $this->config->get('app.admin_email')
                ],
                'email' => [
                    'enabled' => $this->config->get('mail.enabled'),
                    'driver' => $this->config->get('mail.driver'),
                    'host' => $this->config->get('mail.host'),
                    'port' => $this->config->get('mail.port'),
                    'encryption' => $this->config->get('mail.encryption'),
                    'from_address' => $this->config->get('mail.from.address'),
                    'from_name' => $this->config->get('mail.from.name')
                ],
                'api' => [
                    'rate_limit' => $this->config->get('api.rate_limit'),
                    'cors_enabled' => $this->config->get('api.cors_enabled'),
                    'cors_origins' => $this->config->get('api.cors_origins')
                ],
                'security' => [
                    'session_lifetime' => $this->config->get('security.session_lifetime'),
                    'password_min_length' => $this->config->get('security.password_min_length'),
                    'max_login_attempts' => $this->config->get('security.max_login_attempts'),
                    'lockout_duration' => $this->config->get('security.lockout_duration')
                ],
                'modules' => [
                    'events' => $this->config->get('modules.events.enabled'),
                    'shops' => $this->config->get('modules.shops.enabled'),
                    'claims' => $this->config->get('modules.claims.enabled'),
                    'communication' => $this->config->get('modules.communication.enabled'),
                    'classifieds' => $this->config->get('modules.classifieds.enabled')
                ],
                'exported_at' => date('Y-m-d H:i:s'),
                'version' => '2.0.0'
            ];

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="yfevents_settings_' . date('Y-m-d') . '.json"');
            echo json_encode($settings, JSON_PRETTY_PRINT);

        } catch (Exception $e) {
            $this->errorResponse('Failed to export settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import settings
     */
    public function importSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $settings = $input['settings'] ?? null;

            if (!$settings || !is_array($settings)) {
                $this->errorResponse('Invalid settings data');
                return;
            }

            $imported = 0;
            $errors = [];

            foreach ($settings as $category => $categorySettings) {
                if (!is_array($categorySettings)) {
                    continue;
                }

                foreach ($categorySettings as $key => $value) {
                    try {
                        switch ($category) {
                            case 'general':
                                $this->updateGeneralSetting($key, $value);
                                break;
                            case 'email':
                                $this->updateEmailSetting($key, $value);
                                break;
                            case 'api':
                                $this->updateApiSetting($key, $value);
                                break;
                            case 'security':
                                $this->updateSecuritySetting($key, $value);
                                break;
                            case 'modules':
                                $this->updateModuleSetting($key, $value);
                                break;
                        }
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "$category.$key: " . $e->getMessage();
                    }
                }
            }

            $this->successResponse([
                'message' => 'Settings imported',
                'imported' => $imported,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to import settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update general setting
     */
    private function updateGeneralSetting(string $key, $value): void
    {
        $allowedKeys = ['site_name', 'site_description', 'timezone', 'admin_email', 'maintenance_mode', 'debug_mode'];
        
        if (!in_array($key, $allowedKeys)) {
            throw new Exception("Invalid general setting key: $key");
        }

        // Validate specific settings
        if ($key === 'timezone' && !in_array($value, timezone_identifiers_list())) {
            throw new Exception('Invalid timezone');
        }

        if ($key === 'admin_email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }

        // Update in database
        $stmt = $this->pdo->prepare("
            INSERT INTO system_settings (category, `key`, value, updated_at) 
            VALUES ('general', :key, :value, NOW())
            ON DUPLICATE KEY UPDATE value = :value, updated_at = NOW()
        ");
        $stmt->execute([
            'key' => $key,
            'value' => is_bool($value) ? ($value ? '1' : '0') : (string)$value
        ]);
    }

    /**
     * Update email setting
     */
    private function updateEmailSetting(string $key, $value): void
    {
        $allowedKeys = ['enabled', 'driver', 'host', 'port', 'encryption', 'from_address', 'from_name'];
        
        if (!in_array($key, $allowedKeys)) {
            throw new Exception("Invalid email setting key: $key");
        }

        // Validate specific settings
        if ($key === 'from_address' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid from email address');
        }

        if ($key === 'port' && (!is_numeric($value) || $value < 1 || $value > 65535)) {
            throw new Exception('Invalid port number');
        }

        // Update in database
        $stmt = $this->pdo->prepare("
            INSERT INTO system_settings (category, `key`, value, updated_at) 
            VALUES ('email', :key, :value, NOW())
            ON DUPLICATE KEY UPDATE value = :value, updated_at = NOW()
        ");
        $stmt->execute([
            'key' => $key,
            'value' => is_bool($value) ? ($value ? '1' : '0') : (string)$value
        ]);
    }

    /**
     * Update API setting
     */
    private function updateApiSetting(string $key, $value): void
    {
        $allowedKeys = ['google_maps_key', 'rate_limit', 'cors_enabled', 'cors_origins'];
        
        if (!in_array($key, $allowedKeys)) {
            throw new Exception("Invalid API setting key: $key");
        }

        // Update in database
        $stmt = $this->pdo->prepare("
            INSERT INTO system_settings (category, `key`, value, updated_at) 
            VALUES ('api', :key, :value, NOW())
            ON DUPLICATE KEY UPDATE value = :value, updated_at = NOW()
        ");
        
        $valueToStore = $value;
        if (is_array($value)) {
            $valueToStore = json_encode($value);
        } elseif (is_bool($value)) {
            $valueToStore = $value ? '1' : '0';
        }
        
        $stmt->execute([
            'key' => $key,
            'value' => (string)$valueToStore
        ]);
    }

    /**
     * Update security setting
     */
    private function updateSecuritySetting(string $key, $value): void
    {
        $allowedKeys = ['session_lifetime', 'password_min_length', 'max_login_attempts', 'lockout_duration'];
        
        if (!in_array($key, $allowedKeys)) {
            throw new Exception("Invalid security setting key: $key");
        }

        // Validate numeric values
        if (!is_numeric($value) || $value < 0) {
            throw new Exception('Security settings must be positive numbers');
        }

        // Update in database
        $stmt = $this->pdo->prepare("
            INSERT INTO system_settings (category, `key`, value, updated_at) 
            VALUES ('security', :key, :value, NOW())
            ON DUPLICATE KEY UPDATE value = :value, updated_at = NOW()
        ");
        $stmt->execute([
            'key' => $key,
            'value' => (string)$value
        ]);
    }

    /**
     * Update module setting
     */
    private function updateModuleSetting(string $key, $value): void
    {
        $allowedKeys = ['events', 'shops', 'claims', 'communication', 'classifieds'];
        
        if (!in_array($key, $allowedKeys)) {
            throw new Exception("Invalid module key: $key");
        }

        // Update in database
        $stmt = $this->pdo->prepare("
            INSERT INTO system_settings (category, `key`, value, updated_at) 
            VALUES ('modules', :key, :value, NOW())
            ON DUPLICATE KEY UPDATE value = :value, updated_at = NOW()
        ");
        $stmt->execute([
            'key' => $key,
            'value' => $value ? '1' : '0'
        ]);
    }

    /**
     * Log setting change
     */
    private function logSettingChange(string $category, string $key, $value): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? 0;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_activity_log (user_id, action, details, created_at)
                VALUES (:user_id, :action, :details, NOW())
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'action' => 'setting_changed',
                'details' => json_encode([
                    'category' => $category,
                    'key' => $key,
                    'value' => is_array($value) ? json_encode($value) : $value
                ])
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the operation
            error_log('Failed to log setting change: ' . $e->getMessage());
        }
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get database version
     */
    private function getDatabaseVersion(): string
    {
        try {
            $stmt = $this->pdo->query('SELECT VERSION()');
            return $stmt->fetchColumn() ?: 'Unknown';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get database size
     */
    private function getDatabaseSize(): string
    {
        try {
            $dbName = $this->config->get('database.database');
            $stmt = $this->pdo->prepare("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.tables 
                WHERE table_schema = :db_name
            ");
            $stmt->execute(['db_name' => $dbName]);
            $size = $stmt->fetchColumn();
            return $size ? $size . ' MB' : '0 MB';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get database table count
     */
    private function getDatabaseTableCount(): int
    {
        try {
            $dbName = $this->config->get('database.database');
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = :db_name
            ");
            $stmt->execute(['db_name' => $dbName]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Mask API key for display
     */
    private function maskApiKey(string $key): string
    {
        if (strlen($key) < 8) {
            return $key;
        }
        
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }

    private function renderSettingsPage(string $basePath): string
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
    <title>System Settings - YFEvents Admin</title>
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
            background: linear-gradient(135deg, #6f42c1 0%, #28a745 100%);
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
            display: flex;
            height: calc(100vh - 70px);
        }
        
        .sidebar {
            width: 250px;
            background: white;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .nav-item {
            display: block;
            padding: 12px 16px;
            margin-bottom: 5px;
            color: #495057;
            text-decoration: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .nav-item:hover {
            background: #f1f3f5;
            color: #6f42c1;
        }
        
        .nav-item.active {
            background: #6f42c1;
            color: white;
        }
        
        .content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
        
        .settings-section {
            display: none;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .settings-section.active {
            display: block;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 20px;
        }
        
        .setting-group {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .setting-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .setting-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .setting-label {
            flex: 0 0 300px;
            font-weight: 500;
            color: #495057;
        }
        
        .setting-control {
            flex: 1;
        }
        
        .setting-description {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 26px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #28a745;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #6f42c1;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a32a3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
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
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e9ecef;
        }
        
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .info-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
        }
        
        .info-card h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #495057;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .info-value {
            color: #343a40;
            font-weight: 500;
            font-size: 0.9rem;
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #6f42c1;
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
        <h1>⚙️ System Settings</h1>
        <div class="user-info">
            <span>Welcome, {$username}</span>
            <a href="{$basePath}/admin/dashboard" class="back-btn">← Back to Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a class="nav-item active" data-section="general" onclick="showSection('general')">General Settings</a>
            <a class="nav-item" data-section="email" onclick="showSection('email')">Email Configuration</a>
            <a class="nav-item" data-section="api" onclick="showSection('api')">API Settings</a>
            <a class="nav-item" data-section="security" onclick="showSection('security')">Security</a>
            <a class="nav-item" data-section="modules" onclick="showSection('modules')">Modules</a>
            <a class="nav-item" data-section="cache" onclick="showSection('cache')">Cache Management</a>
            <a class="nav-item" data-section="system" onclick="showSection('system')">System Information</a>
            <a class="nav-item" data-section="backup" onclick="showSection('backup')">Backup & Export</a>
        </div>
        
        <div class="content">
            <div id="alert" class="alert"></div>
            
            <!-- General Settings -->
            <div id="general-section" class="settings-section active">
                <h2 class="section-title">General Settings</h2>
                
                <div class="setting-group">
                    <div class="setting-row">
                        <div class="setting-label">
                            Site Name
                            <div class="setting-description">The name of your website</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" id="site_name" placeholder="YFEvents">
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Site Description
                            <div class="setting-description">Brief description of your website</div>
                        </div>
                        <div class="setting-control">
                            <textarea id="site_description" rows="3" placeholder="Event management and local business directory"></textarea>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Timezone
                            <div class="setting-description">Default timezone for the application</div>
                        </div>
                        <div class="setting-control">
                            <select id="timezone">
                                <option value="America/Los_Angeles">Pacific Time (US & Canada)</option>
                                <option value="America/Denver">Mountain Time (US & Canada)</option>
                                <option value="America/Chicago">Central Time (US & Canada)</option>
                                <option value="America/New_York">Eastern Time (US & Canada)</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Admin Email
                            <div class="setting-description">Primary administrator email address</div>
                        </div>
                        <div class="setting-control">
                            <input type="email" id="admin_email" placeholder="admin@example.com">
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Maintenance Mode
                            <div class="setting-description">Enable to show maintenance page to visitors</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" id="maintenance_mode">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Debug Mode
                            <div class="setting-description">Enable detailed error messages (disable in production)</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" id="debug_mode">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="saveGeneralSettings()">Save Changes</button>
                    <button class="btn btn-secondary" onclick="resetSection('general')">Reset</button>
                </div>
            </div>
            
            <!-- Email Settings -->
            <div id="email-section" class="settings-section">
                <h2 class="section-title">Email Configuration</h2>
                
                <div class="setting-group">
                    <div class="setting-row">
                        <div class="setting-label">
                            Email Enabled
                            <div class="setting-description">Enable email functionality</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" id="email_enabled">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Mail Driver
                            <div class="setting-description">Email sending method</div>
                        </div>
                        <div class="setting-control">
                            <select id="mail_driver">
                                <option value="smtp">SMTP</option>
                                <option value="sendmail">Sendmail</option>
                                <option value="mail">PHP Mail</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            SMTP Host
                            <div class="setting-description">SMTP server hostname</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" id="smtp_host" placeholder="smtp.gmail.com">
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            SMTP Port
                            <div class="setting-description">SMTP server port (usually 587 for TLS)</div>
                        </div>
                        <div class="setting-control">
                            <input type="number" id="smtp_port" placeholder="587">
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Encryption
                            <div class="setting-description">Email encryption method</div>
                        </div>
                        <div class="setting-control">
                            <select id="mail_encryption">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="">None</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            From Address
                            <div class="setting-description">Default sender email address</div>
                        </div>
                        <div class="setting-control">
                            <input type="email" id="from_address" placeholder="noreply@example.com">
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            From Name
                            <div class="setting-description">Default sender name</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" id="from_name" placeholder="YFEvents">
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="saveEmailSettings()">Save Changes</button>
                    <button class="btn btn-success" onclick="testEmailConnection()">Test Connection</button>
                    <button class="btn btn-secondary" onclick="resetSection('email')">Reset</button>
                </div>
            </div>
            
            <!-- API Settings -->
            <div id="api-section" class="settings-section">
                <h2 class="section-title">API Settings</h2>
                
                <div class="setting-group">
                    <div class="setting-row">
                        <div class="setting-label">
                            Google Maps API Key
                            <div class="setting-description">API key for Google Maps integration</div>
                        </div>
                        <div class="setting-control">
                            <input type="text" id="google_maps_key" placeholder="AIza...">
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            API Rate Limit
                            <div class="setting-description">Maximum API requests per minute per IP</div>
                        </div>
                        <div class="setting-control">
                            <input type="number" id="rate_limit" placeholder="60">
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            CORS Enabled
                            <div class="setting-description">Enable Cross-Origin Resource Sharing</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" id="cors_enabled">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            CORS Origins
                            <div class="setting-description">Allowed origins (one per line, * for all)</div>
                        </div>
                        <div class="setting-control">
                            <textarea id="cors_origins" rows="4" placeholder="*"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="saveApiSettings()">Save Changes</button>
                    <button class="btn btn-secondary" onclick="resetSection('api')">Reset</button>
                </div>
            </div>
            
            <!-- Security Settings -->
            <div id="security-section" class="settings-section">
                <h2 class="section-title">Security Settings</h2>
                
                <div class="setting-group">
                    <div class="setting-row">
                        <div class="setting-label">
                            Session Lifetime
                            <div class="setting-description">Session timeout in minutes</div>
                        </div>
                        <div class="setting-control">
                            <input type="number" id="session_lifetime" placeholder="120">
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Password Minimum Length
                            <div class="setting-description">Minimum required password length</div>
                        </div>
                        <div class="setting-control">
                            <input type="number" id="password_min_length" placeholder="8">
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Max Login Attempts
                            <div class="setting-description">Maximum failed login attempts before lockout</div>
                        </div>
                        <div class="setting-control">
                            <input type="number" id="max_login_attempts" placeholder="5">
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Lockout Duration
                            <div class="setting-description">Account lockout duration in minutes</div>
                        </div>
                        <div class="setting-control">
                            <input type="number" id="lockout_duration" placeholder="30">
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="saveSecuritySettings()">Save Changes</button>
                    <button class="btn btn-secondary" onclick="resetSection('security')">Reset</button>
                </div>
            </div>
            
            <!-- Module Settings -->
            <div id="modules-section" class="settings-section">
                <h2 class="section-title">Module Management</h2>
                
                <div class="setting-group">
                    <div class="setting-row">
                        <div class="setting-label">
                            Events Module
                            <div class="setting-description">Enable event management functionality</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" id="module_events">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Shops Module
                            <div class="setting-description">Enable local business directory</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" id="module_shops">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Claims Module
                            <div class="setting-description">Enable estate sales claims system</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" id="module_claims">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Communication Module
                            <div class="setting-description">Enable communication hub</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" id="module_communication">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-row">
                        <div class="setting-label">
                            Classifieds Module
                            <div class="setting-description">Enable classifieds marketplace</div>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" id="module_classifieds">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="saveModuleSettings()">Save Changes</button>
                    <button class="btn btn-secondary" onclick="resetSection('modules')">Reset</button>
                </div>
            </div>
            
            <!-- Cache Management -->
            <div id="cache-section" class="settings-section">
                <h2 class="section-title">Cache Management</h2>
                
                <div class="setting-group">
                    <div class="info-card">
                        <h4>Cache Statistics</h4>
                        <div class="info-item">
                            <span class="info-label">Cache Driver:</span>
                            <span class="info-value" id="cache-driver">File</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Cache TTL:</span>
                            <span class="info-value" id="cache-ttl">3600 seconds</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Cache Enabled:</span>
                            <span class="info-value" id="cache-enabled">Yes</span>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-danger" onclick="clearCache()">Clear All Cache</button>
                    <button class="btn btn-primary" onclick="refreshCacheStats()">Refresh Stats</button>
                </div>
            </div>
            
            <!-- System Information -->
            <div id="system-section" class="settings-section">
                <h2 class="section-title">System Information</h2>
                
                <div class="loading" id="system-loading">
                    <div class="spinner"></div>
                    <p>Loading system information...</p>
                </div>
                
                <div class="system-info-grid" id="system-info" style="display: none;">
                    <div class="info-card">
                        <h4>PHP Information</h4>
                        <div id="php-info"></div>
                    </div>
                    
                    <div class="info-card">
                        <h4>Server Information</h4>
                        <div id="server-info"></div>
                    </div>
                    
                    <div class="info-card">
                        <h4>Database Information</h4>
                        <div id="database-info"></div>
                    </div>
                    
                    <div class="info-card">
                        <h4>Storage Information</h4>
                        <div id="storage-info"></div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="refreshSystemInfo()">Refresh</button>
                </div>
            </div>
            
            <!-- Backup & Export -->
            <div id="backup-section" class="settings-section">
                <h2 class="section-title">Backup & Export</h2>
                
                <div class="setting-group">
                    <h3>Export Settings</h3>
                    <p>Download all system settings as a JSON file for backup or migration.</p>
                    <button class="btn btn-primary" onclick="exportSettings()">Export Settings</button>
                </div>
                
                <div class="setting-group">
                    <h3>Import Settings</h3>
                    <p>Import settings from a previously exported JSON file.</p>
                    <input type="file" id="import-file" accept=".json" style="margin-bottom: 10px;">
                    <button class="btn btn-warning" onclick="importSettings()">Import Settings</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentSettings = {};
        
        // Show section
        function showSection(section) {
            // Update nav
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-section="\${section}"]`).classList.add('active');
            
            // Update content
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(`\${section}-section`).classList.add('active');
            
            // Load data for the section
            if (section === 'system') {
                loadSystemInfo();
            }
        }
        
        // Load settings on page load
        async function loadSettings() {
            try {
                const response = await fetch('{$basePath}/admin/settings/get');
                const data = await response.json();
                
                if (data.success) {
                    currentSettings = data.data.settings;
                    populateSettings();
                }
            } catch (error) {
                console.error('Error loading settings:', error);
            }
        }
        
        // Populate form fields with current settings
        function populateSettings() {
            // General settings
            document.getElementById('site_name').value = currentSettings.general?.site_name || '';
            document.getElementById('site_description').value = currentSettings.general?.site_description || '';
            document.getElementById('timezone').value = currentSettings.general?.timezone || 'America/Los_Angeles';
            document.getElementById('admin_email').value = currentSettings.general?.admin_email || '';
            document.getElementById('maintenance_mode').checked = currentSettings.general?.maintenance_mode || false;
            document.getElementById('debug_mode').checked = currentSettings.general?.debug_mode || false;
            
            // Email settings
            document.getElementById('email_enabled').checked = currentSettings.email?.enabled || false;
            document.getElementById('mail_driver').value = currentSettings.email?.driver || 'smtp';
            document.getElementById('smtp_host').value = currentSettings.email?.host || '';
            document.getElementById('smtp_port').value = currentSettings.email?.port || '587';
            document.getElementById('mail_encryption').value = currentSettings.email?.encryption || 'tls';
            document.getElementById('from_address').value = currentSettings.email?.from_address || '';
            document.getElementById('from_name').value = currentSettings.email?.from_name || '';
            
            // API settings
            document.getElementById('google_maps_key').value = currentSettings.api?.google_maps_key || '';
            document.getElementById('rate_limit').value = currentSettings.api?.rate_limit || '60';
            document.getElementById('cors_enabled').checked = currentSettings.api?.cors_enabled || false;
            document.getElementById('cors_origins').value = Array.isArray(currentSettings.api?.cors_origins) 
                ? currentSettings.api.cors_origins.join('\\n') 
                : '*';
            
            // Security settings
            document.getElementById('session_lifetime').value = currentSettings.security?.session_lifetime || '120';
            document.getElementById('password_min_length').value = currentSettings.security?.password_min_length || '8';
            document.getElementById('max_login_attempts').value = currentSettings.security?.max_login_attempts || '5';
            document.getElementById('lockout_duration').value = currentSettings.security?.lockout_duration || '30';
            
            // Module settings
            document.getElementById('module_events').checked = currentSettings.modules?.events !== false;
            document.getElementById('module_shops').checked = currentSettings.modules?.shops !== false;
            document.getElementById('module_claims').checked = currentSettings.modules?.claims !== false;
            document.getElementById('module_communication').checked = currentSettings.modules?.communication !== false;
            document.getElementById('module_classifieds').checked = currentSettings.modules?.classifieds || false;
            
            // Cache info
            document.getElementById('cache-driver').textContent = currentSettings.cache?.driver || 'File';
            document.getElementById('cache-ttl').textContent = (currentSettings.cache?.ttl || 3600) + ' seconds';
            document.getElementById('cache-enabled').textContent = currentSettings.cache?.enabled ? 'Yes' : 'No';
        }
        
        // Save general settings
        async function saveGeneralSettings() {
            const settings = {
                site_name: document.getElementById('site_name').value,
                site_description: document.getElementById('site_description').value,
                timezone: document.getElementById('timezone').value,
                admin_email: document.getElementById('admin_email').value,
                maintenance_mode: document.getElementById('maintenance_mode').checked,
                debug_mode: document.getElementById('debug_mode').checked
            };
            
            await saveBulkSettings({ general: settings });
        }
        
        // Save email settings
        async function saveEmailSettings() {
            const settings = {
                enabled: document.getElementById('email_enabled').checked,
                driver: document.getElementById('mail_driver').value,
                host: document.getElementById('smtp_host').value,
                port: document.getElementById('smtp_port').value,
                encryption: document.getElementById('mail_encryption').value,
                from_address: document.getElementById('from_address').value,
                from_name: document.getElementById('from_name').value
            };
            
            await saveBulkSettings({ email: settings });
        }
        
        // Save API settings
        async function saveApiSettings() {
            const settings = {
                google_maps_key: document.getElementById('google_maps_key').value,
                rate_limit: document.getElementById('rate_limit').value,
                cors_enabled: document.getElementById('cors_enabled').checked,
                cors_origins: document.getElementById('cors_origins').value.split('\\n').filter(o => o.trim())
            };
            
            await saveBulkSettings({ api: settings });
        }
        
        // Save security settings
        async function saveSecuritySettings() {
            const settings = {
                session_lifetime: document.getElementById('session_lifetime').value,
                password_min_length: document.getElementById('password_min_length').value,
                max_login_attempts: document.getElementById('max_login_attempts').value,
                lockout_duration: document.getElementById('lockout_duration').value
            };
            
            await saveBulkSettings({ security: settings });
        }
        
        // Save module settings
        async function saveModuleSettings() {
            const settings = {
                events: document.getElementById('module_events').checked,
                shops: document.getElementById('module_shops').checked,
                claims: document.getElementById('module_claims').checked,
                communication: document.getElementById('module_communication').checked,
                classifieds: document.getElementById('module_classifieds').checked
            };
            
            await saveBulkSettings({ modules: settings });
        }
        
        // Save bulk settings
        async function saveBulkSettings(settings) {
            try {
                const response = await fetch('{$basePath}/admin/settings/bulk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ settings })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Settings saved successfully!', 'success');
                    loadSettings(); // Reload settings
                } else {
                    showAlert(data.error || 'Failed to save settings', 'error');
                }
            } catch (error) {
                showAlert('Error saving settings: ' + error.message, 'error');
            }
        }
        
        // Test email connection
        async function testEmailConnection() {
            try {
                const response = await fetch('{$basePath}/admin/email/test-connection', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Email connection successful!', 'success');
                } else {
                    showAlert(data.error || 'Email connection failed', 'error');
                }
            } catch (error) {
                showAlert('Error testing connection: ' + error.message, 'error');
            }
        }
        
        // Clear cache
        async function clearCache() {
            if (!confirm('Are you sure you want to clear all cache?')) {
                return;
            }
            
            try {
                const response = await fetch('{$basePath}/admin/settings/clear-cache', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Cache cleared successfully!', 'success');
                } else {
                    showAlert(data.error || 'Failed to clear cache', 'error');
                }
            } catch (error) {
                showAlert('Error clearing cache: ' + error.message, 'error');
            }
        }
        
        // Load system info
        async function loadSystemInfo() {
            document.getElementById('system-loading').style.display = 'block';
            document.getElementById('system-info').style.display = 'none';
            
            try {
                const response = await fetch('{$basePath}/admin/settings/system-info');
                const data = await response.json();
                
                if (data.success) {
                    displaySystemInfo(data.data.system_info);
                }
            } catch (error) {
                showAlert('Error loading system info: ' + error.message, 'error');
            } finally {
                document.getElementById('system-loading').style.display = 'none';
                document.getElementById('system-info').style.display = 'grid';
            }
        }
        
        // Display system info
        function displaySystemInfo(info) {
            // PHP info
            let phpHtml = '';
            phpHtml += `<div class="info-item"><span class="info-label">Version:</span><span class="info-value">\${info.php.version}</span></div>`;
            phpHtml += `<div class="info-item"><span class="info-label">SAPI:</span><span class="info-value">\${info.php.sapi}</span></div>`;
            phpHtml += `<div class="info-item"><span class="info-label">Memory Limit:</span><span class="info-value">\${info.php.memory_limit}</span></div>`;
            phpHtml += `<div class="info-item"><span class="info-label">Max Execution Time:</span><span class="info-value">\${info.php.max_execution_time}</span></div>`;
            document.getElementById('php-info').innerHTML = phpHtml;
            
            // Server info
            let serverHtml = '';
            serverHtml += `<div class="info-item"><span class="info-label">Software:</span><span class="info-value">\${info.server.software}</span></div>`;
            serverHtml += `<div class="info-item"><span class="info-label">Hostname:</span><span class="info-value">\${info.server.hostname}</span></div>`;
            serverHtml += `<div class="info-item"><span class="info-label">OS:</span><span class="info-value">\${info.server.os}</span></div>`;
            document.getElementById('server-info').innerHTML = serverHtml;
            
            // Database info
            let dbHtml = '';
            dbHtml += `<div class="info-item"><span class="info-label">Version:</span><span class="info-value">\${info.database.version}</span></div>`;
            dbHtml += `<div class="info-item"><span class="info-label">Size:</span><span class="info-value">\${info.database.size}</span></div>`;
            dbHtml += `<div class="info-item"><span class="info-label">Tables:</span><span class="info-value">\${info.database.tables}</span></div>`;
            document.getElementById('database-info').innerHTML = dbHtml;
            
            // Storage info
            let storageHtml = '';
            const totalGB = (info.storage.disk_total / 1024 / 1024 / 1024).toFixed(2);
            const freeGB = (info.storage.disk_free / 1024 / 1024 / 1024).toFixed(2);
            const usedGB = (info.storage.disk_used / 1024 / 1024 / 1024).toFixed(2);
            const usedPercent = ((info.storage.disk_used / info.storage.disk_total) * 100).toFixed(1);
            
            storageHtml += `<div class="info-item"><span class="info-label">Total:</span><span class="info-value">\${totalGB} GB</span></div>`;
            storageHtml += `<div class="info-item"><span class="info-label">Used:</span><span class="info-value">\${usedGB} GB (\${usedPercent}%)</span></div>`;
            storageHtml += `<div class="info-item"><span class="info-label">Free:</span><span class="info-value">\${freeGB} GB</span></div>`;
            document.getElementById('storage-info').innerHTML = storageHtml;
        }
        
        // Export settings
        async function exportSettings() {
            try {
                window.location.href = '{$basePath}/admin/settings/export';
            } catch (error) {
                showAlert('Error exporting settings: ' + error.message, 'error');
            }
        }
        
        // Import settings
        async function importSettings() {
            const fileInput = document.getElementById('import-file');
            const file = fileInput.files[0];
            
            if (!file) {
                showAlert('Please select a file to import', 'error');
                return;
            }
            
            try {
                const text = await file.text();
                const settings = JSON.parse(text);
                
                const response = await fetch('{$basePath}/admin/settings/import', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ settings })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(`Settings imported successfully! \${data.data.imported} settings imported.`, 'success');
                    loadSettings(); // Reload settings
                } else {
                    showAlert(data.error || 'Failed to import settings', 'error');
                }
            } catch (error) {
                showAlert('Error importing settings: ' + error.message, 'error');
            }
        }
        
        // Reset section to original values
        function resetSection(section) {
            populateSettings();
            showAlert('Settings reset to current values', 'info');
        }
        
        // Refresh cache stats
        function refreshCacheStats() {
            loadSettings();
        }
        
        // Refresh system info
        function refreshSystemInfo() {
            loadSystemInfo();
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadSettings();
        });
    </script>
</body>
</html>
HTML;
    }
}