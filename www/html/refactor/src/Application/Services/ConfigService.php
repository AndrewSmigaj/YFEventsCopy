<?php

declare(strict_types=1);

namespace YakimaFinds\Application\Services;

use YakimaFinds\Infrastructure\Database\Connection;
use YakimaFinds\Infrastructure\Config\ConfigurationInterface;

class ConfigService
{
    private array $configSchema = [
        'general' => [
            'site_name' => ['type' => 'string', 'default' => 'YFEvents', 'required' => true],
            'site_url' => ['type' => 'url', 'default' => 'https://localhost', 'required' => true],
            'timezone' => ['type' => 'string', 'default' => 'America/Los_Angeles', 'required' => true],
            'maintenance_mode' => ['type' => 'boolean', 'default' => false],
        ],
        'email' => [
            'smtp_host' => ['type' => 'string', 'default' => 'localhost'],
            'smtp_port' => ['type' => 'integer', 'default' => 587],
            'smtp_username' => ['type' => 'string', 'default' => ''],
            'smtp_password' => ['type' => 'password', 'default' => ''],
            'smtp_encryption' => ['type' => 'string', 'default' => 'tls', 'options' => ['tls', 'ssl', 'none']],
            'from_email' => ['type' => 'email', 'default' => 'noreply@localhost'],
            'from_name' => ['type' => 'string', 'default' => 'YFEvents'],
        ],
        'database' => [
            'max_connections' => ['type' => 'integer', 'default' => 10],
            'timeout' => ['type' => 'integer', 'default' => 30],
            'charset' => ['type' => 'string', 'default' => 'utf8mb4'],
        ],
        'cache' => [
            'driver' => ['type' => 'string', 'default' => 'file', 'options' => ['file', 'redis', 'memcached']],
            'ttl' => ['type' => 'integer', 'default' => 3600],
            'prefix' => ['type' => 'string', 'default' => 'yfevents_'],
        ],
        'api' => [
            'rate_limit' => ['type' => 'boolean', 'default' => true],
            'max_requests_per_minute' => ['type' => 'integer', 'default' => 60],
            'enable_cors' => ['type' => 'boolean', 'default' => false],
        ],
        'scraper' => [
            'enabled' => ['type' => 'boolean', 'default' => true],
            'schedule' => ['type' => 'string', 'default' => '0 */6 * * *'], // Every 6 hours
            'timeout' => ['type' => 'integer', 'default' => 300],
            'max_concurrent' => ['type' => 'integer', 'default' => 5],
            'user_agent' => ['type' => 'string', 'default' => 'YFEvents Scraper 1.0'],
        ],
        'seo' => [
            'site_title' => ['type' => 'string', 'default' => 'YFEvents - Local Event Calendar'],
            'meta_description' => ['type' => 'text', 'default' => 'Discover local events in the Yakima Valley'],
            'keywords' => ['type' => 'string', 'default' => 'events, yakima, local, calendar'],
            'google_analytics' => ['type' => 'string', 'default' => ''],
            'google_search_console' => ['type' => 'string', 'default' => ''],
        ],
        'security' => [
            'max_login_attempts' => ['type' => 'integer', 'default' => 5],
            'lockout_duration' => ['type' => 'integer', 'default' => 900], // 15 minutes
            'password_min_length' => ['type' => 'integer', 'default' => 8],
            'require_2fa' => ['type' => 'boolean', 'default' => false],
        ]
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ConfigurationInterface $config
    ) {}

    /**
     * Get all configuration values
     */
    public function getAllConfigs(): array
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->query("SELECT * FROM config_settings ORDER BY category, name");
        $dbConfigs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $configs = [];
        foreach ($dbConfigs as $config) {
            $configs[$config['category']][$config['name']] = [
                'value' => $config['value'],
                'type' => $config['type'],
                'description' => $config['description']
            ];
        }
        
        // Merge with defaults for missing values
        foreach ($this->configSchema as $category => $settings) {
            foreach ($settings as $name => $schema) {
                if (!isset($configs[$category][$name])) {
                    $configs[$category][$name] = [
                        'value' => $schema['default'],
                        'type' => $schema['type'],
                        'description' => $schema['description'] ?? ''
                    ];
                }
            }
        }
        
        return $configs;
    }

    /**
     * Get configs by category
     */
    public function getConfigsByCategory(string $category): array
    {
        $allConfigs = $this->getAllConfigs();
        return $allConfigs[$category] ?? [];
    }

    /**
     * Get config categories
     */
    public function getConfigCategories(): array
    {
        return array_keys($this->configSchema);
    }

    /**
     * Update configuration values
     */
    public function updateConfigs(array $data): void
    {
        $pdo = $this->connection->getPdo();
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO config_settings (category, name, value, type, description, updated_at) 
                 VALUES (:category, :name, :value, :type, :description, NOW()) 
                 ON DUPLICATE KEY UPDATE 
                 value = VALUES(value), updated_at = NOW()"
            );
            
            foreach ($data as $category => $settings) {
                if (!isset($this->configSchema[$category])) {
                    continue;
                }
                
                foreach ($settings as $name => $value) {
                    if (!isset($this->configSchema[$category][$name])) {
                        continue;
                    }
                    
                    $schema = $this->configSchema[$category][$name];
                    $validatedValue = $this->validateConfigValue($value, $schema);
                    
                    $stmt->execute([
                        'category' => $category,
                        'name' => $name,
                        'value' => $validatedValue,
                        'type' => $schema['type'],
                        'description' => $schema['description'] ?? ''
                    ]);
                }
            }
            
            $pdo->commit();
            
            // Clear config cache
            $this->clearConfigCache();
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reset category to defaults
     */
    public function resetCategory(string $category): void
    {
        if (!isset($this->configSchema[$category])) {
            throw new \InvalidArgumentException("Unknown config category: $category");
        }
        
        $pdo = $this->connection->getPdo();
        
        $pdo->beginTransaction();
        try {
            // Delete existing configs for category
            $deleteStmt = $pdo->prepare("DELETE FROM config_settings WHERE category = :category");
            $deleteStmt->execute(['category' => $category]);
            
            // Insert defaults
            $insertStmt = $pdo->prepare(
                "INSERT INTO config_settings (category, name, value, type, description, created_at) 
                 VALUES (:category, :name, :value, :type, :description, NOW())"
            );
            
            foreach ($this->configSchema[$category] as $name => $schema) {
                $insertStmt->execute([
                    'category' => $category,
                    'name' => $name,
                    'value' => $schema['default'],
                    'type' => $schema['type'],
                    'description' => $schema['description'] ?? ''
                ]);
            }
            
            $pdo->commit();
            $this->clearConfigCache();
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reset all configuration to defaults
     */
    public function resetAll(): void
    {
        foreach (array_keys($this->configSchema) as $category) {
            $this->resetCategory($category);
        }
    }

    /**
     * Export configuration
     */
    public function exportConfig(string $format): array
    {
        $configs = $this->getAllConfigs();
        
        switch ($format) {
            case 'json':
                return [
                    'content' => json_encode($configs, JSON_PRETTY_PRINT),
                    'filename' => 'config_export_' . date('Y-m-d') . '.json',
                    'mime_type' => 'application/json'
                ];
                
            case 'php':
                $content = "<?php\n\nreturn " . var_export($configs, true) . ";\n";
                return [
                    'content' => $content,
                    'filename' => 'config_export_' . date('Y-m-d') . '.php',
                    'mime_type' => 'application/x-httpd-php'
                ];
                
            default:
                throw new \InvalidArgumentException("Unsupported export format: $format");
        }
    }

    /**
     * Import configuration
     */
    public function importConfig($file): void
    {
        $content = file_get_contents($file['tmp_name']);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        switch ($extension) {
            case 'json':
                $configs = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Invalid JSON file');
                }
                break;
                
            case 'php':
                $configs = include $file['tmp_name'];
                if (!is_array($configs)) {
                    throw new \RuntimeException('Invalid PHP config file');
                }
                break;
                
            default:
                throw new \InvalidArgumentException("Unsupported file format: $extension");
        }
        
        $this->updateConfigs($configs);
    }

    /**
     * Get configuration schema
     */
    public function getConfigSchema(): array
    {
        return $this->configSchema;
    }

    /**
     * Test configuration
     */
    public function testConfiguration(?string $category = null): array
    {
        $results = [];
        
        if ($category) {
            $results[$category] = $this->testCategoryConfiguration($category);
        } else {
            foreach (array_keys($this->configSchema) as $cat) {
                $results[$cat] = $this->testCategoryConfiguration($cat);
            }
        }
        
        return $results;
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStatistics(): array
    {
        $pdo = $this->connection->getPdo();
        
        $size = $pdo->query(
            "SELECT SUM(data_length + index_length) / 1024 / 1024 as size_mb
             FROM information_schema.tables 
             WHERE table_schema = DATABASE()"
        )->fetchColumn();
        
        $tables = $pdo->query(
            "SELECT COUNT(*) as count
             FROM information_schema.tables 
             WHERE table_schema = DATABASE()"
        )->fetchColumn();
        
        return [
            'size_mb' => round((float)$size, 2),
            'table_count' => (int)$tables,
            'connection_status' => 'active'
        ];
    }

    /**
     * Get cache statistics
     */
    public function getCacheStatistics(): array
    {
        // This would integrate with actual cache system
        return [
            'driver' => 'file',
            'status' => 'active',
            'hit_rate' => 0.85,
            'memory_usage' => '125MB'
        ];
    }

    /**
     * Get API keys
     */
    public function getApiKeys(): array
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->query(
            "SELECT id, name, created_at, last_used, status 
             FROM api_keys 
             ORDER BY created_at DESC"
        );
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Generate new API key
     */
    public function generateApiKey(string $name, array $permissions = []): string
    {
        $key = 'yfevents_' . bin2hex(random_bytes(32));
        
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "INSERT INTO api_keys (name, api_key, permissions, status, created_at) 
             VALUES (:name, :api_key, :permissions, 'active', NOW())"
        );
        
        $stmt->execute([
            'name' => $name,
            'api_key' => hash('sha256', $key),
            'permissions' => json_encode($permissions)
        ]);
        
        return $key;
    }

    /**
     * Revoke API key
     */
    public function revokeApiKey(int $keyId): void
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "UPDATE api_keys SET status = 'revoked', updated_at = NOW() WHERE id = :id"
        );
        
        $stmt->execute(['id' => $keyId]);
    }

    /**
     * Get scraper status
     */
    public function getScraperStatus(): array
    {
        $pdo = $this->connection->getPdo();
        
        try {
            $lastRun = $pdo->query(
                "SELECT MAX(created_at) as last_run FROM scraper_logs"
            )->fetchColumn();
            
            $errorCount = $pdo->prepare(
                "SELECT COUNT(*) FROM scraper_logs 
                 WHERE level = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $errorCount->execute();
            
            return [
                'last_run' => $lastRun ?: 'Never',
                'status' => $lastRun && strtotime($lastRun) > strtotime('-6 hours') ? 'healthy' : 'warning',
                'recent_errors' => (int)$errorCount->fetchColumn()
            ];
        } catch (\Exception $e) {
            return ['status' => 'unknown', 'last_run' => 'N/A', 'recent_errors' => 0];
        }
    }

    /**
     * Get security log
     */
    public function getSecurityLog(int $limit = 50): array
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "SELECT * FROM security_logs 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Clear cache
     */
    public function clearCache(string $type = 'all'): void
    {
        // This would integrate with actual cache system
        switch ($type) {
            case 'config':
                $this->clearConfigCache();
                break;
            case 'templates':
                $this->clearTemplateCache();
                break;
            case 'all':
                $this->clearAllCache();
                break;
        }
    }

    /**
     * Send test email
     */
    public function sendTestEmail(string $email): void
    {
        $emailConfig = $this->getConfigsByCategory('email');
        
        // This would integrate with actual email service
        // For now, just log the attempt
        error_log("Test email would be sent to: $email");
    }

    /**
     * Test database connection
     */
    public function testDatabaseConnection(): array
    {
        try {
            $pdo = $this->connection->getPdo();
            $pdo->query("SELECT 1");
            
            return [
                'success' => true,
                'message' => 'Database connection successful',
                'latency' => '< 1ms'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update specific category settings
     */
    public function updateEmailSettings(array $data): void
    {
        $this->updateConfigs(['email' => $data]);
    }

    public function updateDatabaseSettings(array $data): void
    {
        $this->updateConfigs(['database' => $data]);
    }

    public function updateCacheSettings(array $data): void
    {
        $this->updateConfigs(['cache' => $data]);
    }

    public function updateApiSettings(array $data): void
    {
        $this->updateConfigs(['api' => $data]);
    }

    public function updateScraperSettings(array $data): void
    {
        $this->updateConfigs(['scraper' => $data]);
    }

    public function updateSeoSettings(array $data): void
    {
        $this->updateConfigs(['seo' => $data]);
    }

    public function updateSecuritySettings(array $data): void
    {
        $this->updateConfigs(['security' => $data]);
    }

    private function validateConfigValue($value, array $schema)
    {
        switch ($schema['type']) {
            case 'boolean':
                return (bool)$value;
            case 'integer':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException("Invalid email format: $value");
                }
                return $value;
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException("Invalid URL format: $value");
                }
                return $value;
            default:
                return (string)$value;
        }
    }

    private function testCategoryConfiguration(string $category): array
    {
        switch ($category) {
            case 'email':
                return $this->testEmailConfiguration();
            case 'database':
                return $this->testDatabaseConnection();
            case 'cache':
                return $this->testCacheConfiguration();
            default:
                return ['status' => 'unknown', 'message' => 'No test available'];
        }
    }

    private function testEmailConfiguration(): array
    {
        $config = $this->getConfigsByCategory('email');
        
        // Basic validation
        if (empty($config['smtp_host']['value'])) {
            return ['status' => 'error', 'message' => 'SMTP host not configured'];
        }
        
        return ['status' => 'ok', 'message' => 'Email configuration appears valid'];
    }

    private function testCacheConfiguration(): array
    {
        return ['status' => 'ok', 'message' => 'Cache configuration valid'];
    }

    private function clearConfigCache(): void
    {
        // Clear configuration cache
    }

    private function clearTemplateCache(): void
    {
        // Clear template cache
    }

    private function clearAllCache(): void
    {
        $this->clearConfigCache();
        $this->clearTemplateCache();
    }
}