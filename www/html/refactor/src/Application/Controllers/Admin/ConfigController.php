<?php

declare(strict_types=1);

namespace YakimaFinds\Application\Controllers\Admin;

use YakimaFinds\Application\Controllers\BaseController;
use YakimaFinds\Application\Http\Request;
use YakimaFinds\Application\Http\Response;
use YakimaFinds\Application\Services\ConfigService;
use YakimaFinds\Application\Validation\ConfigValidator;

class ConfigController extends BaseController
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly ConfigValidator $configValidator
    ) {}

    /**
     * Display system configuration
     */
    public function index(Request $request): Response
    {
        $configs = $this->configService->getAllConfigs();
        $categories = $this->configService->getConfigCategories();

        return $this->render('admin/config/index', [
            'configs' => $configs,
            'categories' => $categories
        ]);
    }

    /**
     * Update system configuration
     */
    public function update(Request $request): Response
    {
        $data = $request->all();
        
        $errors = $this->configValidator->validateUpdate($data);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 400);
        }

        try {
            $this->configService->updateConfigs($data);
            
            return $this->json(['success' => true, 'message' => 'Configuration updated successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reset configuration to defaults
     */
    public function reset(Request $request): Response
    {
        $category = $request->get('category');
        
        try {
            if ($category) {
                $this->configService->resetCategory($category);
                $message = "Configuration for '{$category}' reset to defaults";
            } else {
                $this->configService->resetAll();
                $message = 'All configuration reset to defaults';
            }
            
            return $this->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export configuration
     */
    public function export(Request $request): Response
    {
        $format = $request->get('format', 'json');
        
        try {
            $data = $this->configService->exportConfig($format);
            
            return $this->download($data['content'], $data['filename'], $data['mime_type']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Import configuration
     */
    public function import(Request $request): Response
    {
        $file = $request->file('config_file');
        
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        try {
            $this->configService->importConfig($file);
            
            return $this->json(['success' => true, 'message' => 'Configuration imported successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get configuration schema for validation
     */
    public function schema(Request $request): Response
    {
        $schema = $this->configService->getConfigSchema();
        
        return $this->json($schema);
    }

    /**
     * Test configuration settings
     */
    public function test(Request $request): Response
    {
        $category = $request->get('category');
        
        try {
            $results = $this->configService->testConfiguration($category);
            
            return $this->json($results);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Email settings
     */
    public function emailSettings(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->updateEmailSettings($request);
        }

        $emailConfig = $this->configService->getConfigsByCategory('email');
        
        return $this->render('admin/config/email', [
            'config' => $emailConfig
        ]);
    }

    /**
     * Database settings
     */
    public function databaseSettings(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->updateDatabaseSettings($request);
        }

        $dbConfig = $this->configService->getConfigsByCategory('database');
        $dbStats = $this->configService->getDatabaseStatistics();
        
        return $this->render('admin/config/database', [
            'config' => $dbConfig,
            'stats' => $dbStats
        ]);
    }

    /**
     * Cache settings
     */
    public function cacheSettings(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->updateCacheSettings($request);
        }

        $cacheConfig = $this->configService->getConfigsByCategory('cache');
        $cacheStats = $this->configService->getCacheStatistics();
        
        return $this->render('admin/config/cache', [
            'config' => $cacheConfig,
            'stats' => $cacheStats
        ]);
    }

    /**
     * API settings
     */
    public function apiSettings(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->updateApiSettings($request);
        }

        $apiConfig = $this->configService->getConfigsByCategory('api');
        $apiKeys = $this->configService->getApiKeys();
        
        return $this->render('admin/config/api', [
            'config' => $apiConfig,
            'api_keys' => $apiKeys
        ]);
    }

    /**
     * Scraper settings
     */
    public function scraperSettings(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->updateScraperSettings($request);
        }

        $scraperConfig = $this->configService->getConfigsByCategory('scraper');
        $scraperStatus = $this->configService->getScraperStatus();
        
        return $this->render('admin/config/scraper', [
            'config' => $scraperConfig,
            'status' => $scraperStatus
        ]);
    }

    /**
     * SEO settings
     */
    public function seoSettings(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->updateSeoSettings($request);
        }

        $seoConfig = $this->configService->getConfigsByCategory('seo');
        
        return $this->render('admin/config/seo', [
            'config' => $seoConfig
        ]);
    }

    /**
     * Security settings
     */
    public function securitySettings(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            return $this->updateSecuritySettings($request);
        }

        $securityConfig = $this->configService->getConfigsByCategory('security');
        $securityLog = $this->configService->getSecurityLog();
        
        return $this->render('admin/config/security', [
            'config' => $securityConfig,
            'security_log' => $securityLog
        ]);
    }

    /**
     * Generate new API key
     */
    public function generateApiKey(Request $request): Response
    {
        $name = $request->get('name');
        $permissions = $request->get('permissions', []);
        
        if (empty($name)) {
            return $this->json(['error' => 'API key name is required'], 400);
        }

        try {
            $apiKey = $this->configService->generateApiKey($name, $permissions);
            
            return $this->json([
                'success' => true,
                'api_key' => $apiKey,
                'message' => 'API key generated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Revoke API key
     */
    public function revokeApiKey(Request $request, int $keyId): Response
    {
        try {
            $this->configService->revokeApiKey($keyId);
            
            return $this->json(['success' => true, 'message' => 'API key revoked successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Clear cache
     */
    public function clearCache(Request $request): Response
    {
        $type = $request->get('type', 'all');
        
        try {
            $this->configService->clearCache($type);
            
            return $this->json(['success' => true, 'message' => 'Cache cleared successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send test email
     */
    public function sendTestEmail(Request $request): Response
    {
        $email = $request->get('email');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Valid email address is required'], 400);
        }

        try {
            $this->configService->sendTestEmail($email);
            
            return $this->json(['success' => true, 'message' => 'Test email sent successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Test database connection
     */
    public function testDatabaseConnection(Request $request): Response
    {
        try {
            $result = $this->configService->testDatabaseConnection();
            
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function updateEmailSettings(Request $request): Response
    {
        $data = $request->only(['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'from_email', 'from_name']);
        
        $errors = $this->configValidator->validateEmailSettings($data);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 400);
        }

        try {
            $this->configService->updateEmailSettings($data);
            
            return $this->json(['success' => true, 'message' => 'Email settings updated successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function updateDatabaseSettings(Request $request): Response
    {
        $data = $request->only(['max_connections', 'timeout', 'charset']);
        
        try {
            $this->configService->updateDatabaseSettings($data);
            
            return $this->json(['success' => true, 'message' => 'Database settings updated successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function updateCacheSettings(Request $request): Response
    {
        $data = $request->only(['driver', 'ttl', 'prefix']);
        
        try {
            $this->configService->updateCacheSettings($data);
            
            return $this->json(['success' => true, 'message' => 'Cache settings updated successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function updateApiSettings(Request $request): Response
    {
        $data = $request->only(['rate_limit', 'max_requests_per_minute', 'enable_cors']);
        
        try {
            $this->configService->updateApiSettings($data);
            
            return $this->json(['success' => true, 'message' => 'API settings updated successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function updateScraperSettings(Request $request): Response
    {
        $data = $request->only(['enabled', 'schedule', 'timeout', 'max_concurrent', 'user_agent']);
        
        try {
            $this->configService->updateScraperSettings($data);
            
            return $this->json(['success' => true, 'message' => 'Scraper settings updated successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function updateSeoSettings(Request $request): Response
    {
        $data = $request->only(['site_title', 'meta_description', 'keywords', 'google_analytics', 'google_search_console']);
        
        try {
            $this->configService->updateSeoSettings($data);
            
            return $this->json(['success' => true, 'message' => 'SEO settings updated successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    private function updateSecuritySettings(Request $request): Response
    {
        $data = $request->only(['max_login_attempts', 'lockout_duration', 'password_min_length', 'require_2fa']);
        
        try {
            $this->configService->updateSecuritySettings($data);
            
            return $this->json(['success' => true, 'message' => 'Security settings updated successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}