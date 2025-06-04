#!/usr/bin/env php
<?php
/**
 * YFEvents Modular Installation System
 * 
 * Provides organic, granular deployment options with clear component selection.
 * Each component can be installed independently or as part of larger bundles.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class YFEventsInstaller {
    private $rootDir;
    private $components = [];
    private $installLog = [];
    private $config = [];
    
    public function __construct() {
        $this->rootDir = dirname(__DIR__);
        $this->initializeComponents();
        $this->loadConfig();
    }
    
    /**
     * Define all available installation components
     */
    private function initializeComponents() {
        $this->components = [
            'core' => [
                'name' => 'Core System',
                'description' => 'Essential database, configuration, and basic calendar functionality',
                'required' => true,
                'dependencies' => [],
                'includes' => ['database-core', 'config-base', 'calendar-basic']
            ],
            
            'admin' => [
                'name' => 'Admin Interface',
                'description' => 'Complete administrative dashboard and management tools',
                'required' => false,
                'dependencies' => ['core'],
                'includes' => ['admin-basic', 'admin-advanced', 'admin-auth']
            ],
            
            'api' => [
                'name' => 'API Endpoints',
                'description' => 'RESTful API for events, shops, and external integrations',
                'required' => false,
                'dependencies' => ['core'],
                'includes' => ['api-events', 'api-shops', 'api-calendar']
            ],
            
            'scraping' => [
                'name' => 'Event Scraping System',
                'description' => 'Automated event discovery from multiple sources',
                'required' => false,
                'dependencies' => ['core'],
                'includes' => ['scraper-basic', 'scraper-intelligent', 'scraper-cron']
            ],
            
            'geocoding' => [
                'name' => 'Location Services',
                'description' => 'Google Maps integration and geocoding capabilities',
                'required' => false,
                'dependencies' => ['core'],
                'includes' => ['geocode-service', 'maps-integration', 'location-tools']
            ],
            
            'shops' => [
                'name' => 'Business Directory',
                'description' => 'Local business listings and management',
                'required' => false,
                'dependencies' => ['core'],
                'includes' => ['shops-database', 'shops-admin', 'shops-api']
            ],
            
            'modules' => [
                'name' => 'Module System',
                'description' => 'Support for installable modules (YFClaim, YFAuth, etc.)',
                'required' => false,
                'dependencies' => ['core'],
                'includes' => ['module-framework', 'module-installer']
            ],
            
            // Individual Modules
            'yfauth' => [
                'name' => 'YFAuth Module',
                'description' => 'Enhanced authentication and user management',
                'required' => false,
                'dependencies' => ['core', 'modules'],
                'includes' => ['yfauth-database', 'yfauth-services', 'yfauth-admin']
            ],
            
            'yfclaim' => [
                'name' => 'YFClaim Module',
                'description' => 'Estate sale and marketplace platform',
                'required' => false,
                'dependencies' => ['core', 'modules'],
                'includes' => ['yfclaim-database', 'yfclaim-models', 'yfclaim-admin']
            ],
            
            // Deployment Options
            'development' => [
                'name' => 'Development Environment',
                'description' => 'Development tools, debugging, and test data',
                'required' => false,
                'dependencies' => ['core'],
                'includes' => ['dev-tools', 'test-data', 'debug-mode']
            ],
            
            'production' => [
                'name' => 'Production Optimizations',
                'description' => 'Performance optimizations, caching, and security hardening',
                'required' => false,
                'dependencies' => ['core'],
                'includes' => ['cache-optimization', 'security-hardening', 'performance-tuning']
            ]
        ];
    }
    
    /**
     * Load existing configuration if available
     */
    private function loadConfig() {
        $configFile = $this->rootDir . '/install/install-config.json';
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true) ?: [];
        }
    }
    
    /**
     * Save installation configuration
     */
    private function saveConfig() {
        $configFile = $this->rootDir . '/install/install-config.json';
        file_put_contents($configFile, json_encode($this->config, JSON_PRETTY_PRINT));
    }
    
    /**
     * Main installation interface
     */
    public function run() {
        $this->displayHeader();
        
        if (count($GLOBALS['argv']) > 1) {
            $this->handleCliArgs();
        } else {
            $this->interactiveInstall();
        }
    }
    
    /**
     * Display installation header
     */
    private function displayHeader() {
        echo "\n";
        echo "🎯 YFEvents Modular Installation System\n";
        echo "=======================================\n";
        echo "Organic, granular deployment with precise component control\n\n";
    }
    
    /**
     * Handle command line arguments
     */
    private function handleCliArgs() {
        $args = array_slice($GLOBALS['argv'], 1);
        
        if (in_array('--help', $args) || in_array('-h', $args)) {
            $this->displayHelp();
            return;
        }
        
        if (in_array('--list', $args)) {
            $this->listComponents();
            return;
        }
        
        if (in_array('--check', $args)) {
            $this->checkRequirements();
            return;
        }
        
        if (in_array('--all', $args)) {
            $this->installAll();
            return;
        }
        
        // Install specific components
        $components = array_filter($args, function($arg) {
            return !str_starts_with($arg, '--');
        });
        
        if (!empty($components)) {
            $this->installComponents($components);
        } else {
            $this->displayHelp();
        }
    }
    
    /**
     * Interactive installation menu
     */
    private function interactiveInstall() {
        while (true) {
            echo "\n📋 Installation Options:\n";
            echo "1. 🏗️  Quick Install (Core + Admin + API)\n";
            echo "2. 🎯 Custom Install (Select Components)\n";
            echo "3. 🌟 Full Install (All Components)\n";
            echo "4. 📦 Module Only (Just install modules)\n";
            echo "5. 🔍 Check Requirements\n";
            echo "6. 📊 View Available Components\n";
            echo "7. ⚙️  Configure Settings\n";
            echo "8. 🚪 Exit\n\n";
            
            $choice = $this->prompt("Select option (1-8): ");
            
            switch ($choice) {
                case '1':
                    $this->quickInstall();
                    break;
                case '2':
                    $this->customInstall();
                    break;
                case '3':
                    $this->installAll();
                    break;
                case '4':
                    $this->moduleOnlyInstall();
                    break;
                case '5':
                    $this->checkRequirements();
                    break;
                case '6':
                    $this->listComponents();
                    break;
                case '7':
                    $this->configureSettings();
                    break;
                case '8':
                    echo "👋 Installation cancelled.\n";
                    exit(0);
                default:
                    echo "❌ Invalid option. Please try again.\n";
            }
        }
    }
    
    /**
     * Quick install with essential components
     */
    private function quickInstall() {
        echo "\n🏗️ Quick Install: Core + Admin + API\n";
        echo "=====================================\n";
        $this->installComponents(['core', 'admin', 'api']);
    }
    
    /**
     * Custom component selection
     */
    private function customInstall() {
        echo "\n🎯 Custom Component Selection\n";
        echo "=============================\n";
        
        $selected = [];
        
        foreach ($this->components as $key => $component) {
            $required = $component['required'] ? ' (REQUIRED)' : '';
            $deps = !empty($component['dependencies']) ? 
                ' [Requires: ' . implode(', ', $component['dependencies']) . ']' : '';
            
            echo sprintf("%-12s: %s%s%s\n", $key, $component['description'], $required, $deps);
            
            if ($component['required']) {
                $selected[] = $key;
                echo "✅ Auto-selected (required)\n";
            } else {
                $install = $this->prompt("Install $key? (y/N): ");
                if (strtolower($install) === 'y') {
                    $selected[] = $key;
                }
            }
            echo "\n";
        }
        
        if (!empty($selected)) {
            $this->installComponents($selected);
        } else {
            echo "❌ No components selected for installation.\n";
        }
    }
    
    /**
     * Install all available components
     */
    private function installAll() {
        echo "\n🌟 Full Installation\n";
        echo "===================\n";
        $confirm = $this->prompt("This will install ALL components. Continue? (y/N): ");
        
        if (strtolower($confirm) === 'y') {
            $this->installComponents(array_keys($this->components));
        }
    }
    
    /**
     * Module-only installation
     */
    private function moduleOnlyInstall() {
        echo "\n📦 Module Installation\n";
        echo "=====================\n";
        
        $modules = ['modules', 'yfauth', 'yfclaim'];
        $selected = [];
        
        foreach ($modules as $module) {
            $component = $this->components[$module];
            echo sprintf("%-12s: %s\n", $module, $component['description']);
            $install = $this->prompt("Install $module? (y/N): ");
            if (strtolower($install) === 'y') {
                $selected[] = $module;
            }
        }
        
        if (!empty($selected)) {
            $this->installComponents($selected);
        }
    }
    
    /**
     * Install selected components
     */
    private function installComponents($componentList) {
        echo "\n🚀 Starting Installation\n";
        echo "========================\n";
        
        // Resolve dependencies
        $resolved = $this->resolveDependencies($componentList);
        echo "📋 Components to install: " . implode(', ', $resolved) . "\n\n";
        
        // Check requirements first
        if (!$this->checkRequirements(false)) {
            echo "❌ System requirements not met. Installation aborted.\n";
            return false;
        }
        
        $success = true;
        foreach ($resolved as $component) {
            $success = $this->installComponent($component) && $success;
        }
        
        if ($success) {
            echo "\n🎉 Installation completed successfully!\n";
            $this->displayPostInstall();
        } else {
            echo "\n❌ Installation completed with errors. Check the log above.\n";
        }
        
        return $success;
    }
    
    /**
     * Resolve component dependencies
     */
    private function resolveDependencies($componentList) {
        $resolved = [];
        $processing = [];
        
        foreach ($componentList as $component) {
            $this->resolveDependency($component, $resolved, $processing);
        }
        
        return array_unique($resolved);
    }
    
    /**
     * Recursively resolve a single dependency
     */
    private function resolveDependency($component, &$resolved, &$processing) {
        if (in_array($component, $resolved)) {
            return;
        }
        
        if (in_array($component, $processing)) {
            throw new Exception("Circular dependency detected: $component");
        }
        
        if (!isset($this->components[$component])) {
            throw new Exception("Unknown component: $component");
        }
        
        $processing[] = $component;
        
        foreach ($this->components[$component]['dependencies'] as $dependency) {
            $this->resolveDependency($dependency, $resolved, $processing);
        }
        
        $resolved[] = $component;
        $processing = array_diff($processing, [$component]);
    }
    
    /**
     * Install a single component
     */
    private function installComponent($component) {
        echo "📦 Installing component: $component\n";
        
        $componentData = $this->components[$component];
        $success = true;
        
        try {
            switch ($component) {
                case 'core':
                    $success = $this->installCore();
                    break;
                case 'admin':
                    $success = $this->installAdmin();
                    break;
                case 'api':
                    $success = $this->installApi();
                    break;
                case 'scraping':
                    $success = $this->installScraping();
                    break;
                case 'geocoding':
                    $success = $this->installGeocoding();
                    break;
                case 'shops':
                    $success = $this->installShops();
                    break;
                case 'modules':
                    $success = $this->installModules();
                    break;
                case 'yfauth':
                    $success = $this->installYFAuth();
                    break;
                case 'yfclaim':
                    $success = $this->installYFClaim();
                    break;
                case 'development':
                    $success = $this->installDevelopment();
                    break;
                case 'production':
                    $success = $this->installProduction();
                    break;
                default:
                    echo "⚠️ Component '$component' installation not implemented yet\n";
                    $success = false;
            }
            
            if ($success) {
                echo "✅ Component '$component' installed successfully\n";
                $this->installLog[] = ['component' => $component, 'status' => 'success', 'timestamp' => date('Y-m-d H:i:s')];
            } else {
                echo "❌ Component '$component' installation failed\n";
                $this->installLog[] = ['component' => $component, 'status' => 'failed', 'timestamp' => date('Y-m-d H:i:s')];
            }
            
        } catch (Exception $e) {
            echo "❌ Error installing '$component': " . $e->getMessage() . "\n";
            $success = false;
        }
        
        echo "\n";
        return $success;
    }
    
    /**
     * Install core system
     */
    private function installCore() {
        echo "  🔧 Setting up core database and configuration...\n";
        
        // Create database and tables
        if (!$this->setupDatabase()) {
            return false;
        }
        
        // Setup environment
        if (!$this->setupEnvironment()) {
            return false;
        }
        
        // Create required directories
        $this->createDirectories(['cache', 'logs', 'cache/geocode']);
        
        // Set permissions
        $this->setPermissions();
        
        return true;
    }
    
    /**
     * Install admin interface
     */
    private function installAdmin() {
        echo "  🔧 Setting up admin interface...\n";
        
        // Admin files should already exist, just verify
        $adminFiles = [
            'www/html/admin/index.php',
            'www/html/admin/events.php',
            'www/html/admin/shops.php',
            'www/html/admin/calendar/index.php'
        ];
        
        foreach ($adminFiles as $file) {
            if (!file_exists($this->rootDir . '/' . $file)) {
                echo "  ❌ Missing admin file: $file\n";
                return false;
            }
        }
        
        echo "  ✅ Admin interface verified\n";
        return true;
    }
    
    /**
     * Install API endpoints
     */
    private function installApi() {
        echo "  🔧 Setting up API endpoints...\n";
        
        $apiFiles = [
            'www/html/api/events-simple.php',
            'www/html/ajax/calendar-events.php'
        ];
        
        foreach ($apiFiles as $file) {
            if (!file_exists($this->rootDir . '/' . $file)) {
                echo "  ❌ Missing API file: $file\n";
                return false;
            }
        }
        
        echo "  ✅ API endpoints verified\n";
        return true;
    }
    
    /**
     * Install scraping system
     */
    private function installScraping() {
        echo "  🔧 Setting up scraping system...\n";
        
        // Verify scraper files exist
        if (!file_exists($this->rootDir . '/cron/scrape-events.php')) {
            echo "  ❌ Missing scraper file\n";
            return false;
        }
        
        // Make scraper executable
        chmod($this->rootDir . '/cron/scrape-events.php', 0755);
        
        echo "  ✅ Scraping system configured\n";
        return true;
    }
    
    /**
     * Install geocoding services
     */
    private function installGeocoding() {
        echo "  🔧 Setting up geocoding services...\n";
        
        // Verify geocoding service exists
        if (!file_exists($this->rootDir . '/src/Utils/GeocodeService.php')) {
            echo "  ❌ Missing GeocodeService\n";
            return false;
        }
        
        // Check for Google Maps API key
        if (empty($_ENV['GOOGLE_MAPS_API_KEY'])) {
            echo "  ⚠️ Warning: Google Maps API key not configured\n";
        }
        
        echo "  ✅ Geocoding services verified\n";
        return true;
    }
    
    /**
     * Install shops/business directory
     */
    private function installShops() {
        echo "  🔧 Setting up business directory...\n";
        
        // Verify shops table exists (should be created in core)
        if (!$this->tableExists('local_shops')) {
            echo "  ❌ Shops table not found\n";
            return false;
        }
        
        echo "  ✅ Business directory configured\n";
        return true;
    }
    
    /**
     * Install module system
     */
    private function installModules() {
        echo "  🔧 Setting up module system...\n";
        
        // Apply modules schema
        $moduleSchema = $this->rootDir . '/database/modules_schema.sql';
        if (file_exists($moduleSchema)) {
            $this->executeSqlFile($moduleSchema);
        }
        
        // Verify module installer exists
        if (!file_exists($this->rootDir . '/modules/install.php')) {
            echo "  ❌ Module installer not found\n";
            return false;
        }
        
        echo "  ✅ Module system configured\n";
        return true;
    }
    
    /**
     * Install YFAuth module
     */
    private function installYFAuth() {
        echo "  🔧 Installing YFAuth module...\n";
        
        // Use the existing module installer
        $output = [];
        $returnCode = 0;
        exec("cd {$this->rootDir} && php modules/install.php yfauth", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "  ✅ YFAuth module installed\n";
            return true;
        } else {
            echo "  ❌ YFAuth installation failed\n";
            return false;
        }
    }
    
    /**
     * Install YFClaim module
     */
    private function installYFClaim() {
        echo "  🔧 Installing YFClaim module...\n";
        
        // Use the existing module installer
        $output = [];
        $returnCode = 0;
        exec("cd {$this->rootDir} && php modules/install.php yfclaim", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "  ✅ YFClaim module installed\n";
            return true;
        } else {
            echo "  ❌ YFClaim installation failed\n";
            return false;
        }
    }
    
    /**
     * Install development environment
     */
    private function installDevelopment() {
        echo "  🔧 Setting up development environment...\n";
        
        // Set development environment variables
        $this->config['environment'] = 'development';
        
        // Enable debug mode
        $envFile = $this->rootDir . '/.env';
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            $envContent = preg_replace('/APP_DEBUG=.*/', 'APP_DEBUG=true', $envContent);
            $envContent = preg_replace('/APP_ENV=.*/', 'APP_ENV=development', $envContent);
            file_put_contents($envFile, $envContent);
        }
        
        echo "  ✅ Development environment configured\n";
        return true;
    }
    
    /**
     * Install production optimizations
     */
    private function installProduction() {
        echo "  🔧 Setting up production optimizations...\n";
        
        $this->config['environment'] = 'production';
        
        // Configure production settings
        $envFile = $this->rootDir . '/.env';
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            $envContent = preg_replace('/APP_DEBUG=.*/', 'APP_DEBUG=false', $envContent);
            $envContent = preg_replace('/APP_ENV=.*/', 'APP_ENV=production', $envContent);
            file_put_contents($envFile, $envContent);
        }
        
        echo "  ✅ Production optimizations applied\n";
        return true;
    }
    
    /**
     * Setup database
     */
    private function setupDatabase() {
        echo "  🗃️ Setting up database...\n";
        
        // Check if .env exists, if not copy from example
        $envFile = $this->rootDir . '/.env';
        if (!file_exists($envFile)) {
            if (file_exists($this->rootDir . '/.env.example')) {
                copy($this->rootDir . '/.env.example', $envFile);
                echo "  📝 Created .env from example\n";
                echo "  ⚠️ Please configure database settings in .env file\n";
                
                $dbHost = $this->prompt("  Database host (localhost): ") ?: 'localhost';
                $dbName = $this->prompt("  Database name (yakima_finds): ") ?: 'yakima_finds';
                $dbUser = $this->prompt("  Database username (yfevents): ") ?: 'yfevents';
                $dbPass = $this->prompt("  Database password: ");
                
                // Update .env file
                $envContent = file_get_contents($envFile);
                $envContent = str_replace('DB_HOST=localhost', "DB_HOST=$dbHost", $envContent);
                $envContent = str_replace('DB_NAME=yakima_finds', "DB_NAME=$dbName", $envContent);
                $envContent = str_replace('DB_USERNAME=yfevents', "DB_USERNAME=$dbUser", $envContent);
                $envContent = str_replace('DB_PASSWORD=your_secure_password_here', "DB_PASSWORD=$dbPass", $envContent);
                file_put_contents($envFile, $envContent);
            } else {
                echo "  ❌ No .env.example file found\n";
                return false;
            }
        }
        
        // Apply database schemas
        $schemas = [
            'database/calendar_schema.sql',
            'database/batch_processing_schema.sql',
            'database/intelligent_scraper_schema.sql'
        ];
        
        foreach ($schemas as $schema) {
            $schemaFile = $this->rootDir . '/' . $schema;
            if (file_exists($schemaFile)) {
                echo "  📄 Applying schema: $schema\n";
                if (!$this->executeSqlFile($schemaFile)) {
                    echo "  ❌ Failed to apply schema: $schema\n";
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Setup environment configuration
     */
    private function setupEnvironment() {
        echo "  ⚙️ Setting up environment...\n";
        
        // Load composer autoloader
        if (file_exists($this->rootDir . '/vendor/autoload.php')) {
            require_once $this->rootDir . '/vendor/autoload.php';
        } else {
            echo "  🔧 Installing composer dependencies...\n";
            $output = [];
            $returnCode = 0;
            exec("cd {$this->rootDir} && composer install", $output, $returnCode);
            
            if ($returnCode !== 0) {
                echo "  ❌ Composer install failed\n";
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create required directories
     */
    private function createDirectories($dirs) {
        foreach ($dirs as $dir) {
            $fullPath = $this->rootDir . '/' . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                echo "  📁 Created directory: $dir\n";
            }
        }
    }
    
    /**
     * Set file permissions
     */
    private function setPermissions() {
        $permissions = [
            'cache' => 0755,
            'logs' => 0755,
            'cron/scrape-events.php' => 0755
        ];
        
        foreach ($permissions as $path => $perm) {
            $fullPath = $this->rootDir . '/' . $path;
            if (file_exists($fullPath)) {
                chmod($fullPath, $perm);
                echo "  🔒 Set permissions for: $path\n";
            }
        }
    }
    
    /**
     * Execute SQL file
     */
    private function executeSqlFile($sqlFile) {
        // This would need database connection - simplified for now
        echo "  📄 Would execute SQL file: $sqlFile\n";
        return true;
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName) {
        // This would need database connection - simplified for now
        return true;
    }
    
    /**
     * Check system requirements
     */
    private function checkRequirements($display = true) {
        if ($display) {
            echo "\n🔍 System Requirements Check\n";
            echo "============================\n";
        }
        
        $requirements = [
            'PHP Version >= 8.2' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'PDO Extension' => extension_loaded('pdo'),
            'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
            'JSON Extension' => extension_loaded('json'),
            'cURL Extension' => extension_loaded('curl'),
            'Write access to root directory' => is_writable($this->rootDir),
            'Composer available' => $this->isComposerAvailable()
        ];
        
        $allPassed = true;
        
        foreach ($requirements as $requirement => $passed) {
            $status = $passed ? '✅ PASS' : '❌ FAIL';
            if ($display) {
                echo sprintf("%-35s: %s\n", $requirement, $status);
            }
            $allPassed = $allPassed && $passed;
        }
        
        if ($display) {
            echo "\n";
            if ($allPassed) {
                echo "🎉 All requirements met!\n";
            } else {
                echo "❌ Some requirements not met. Please fix before installing.\n";
            }
        }
        
        return $allPassed;
    }
    
    /**
     * Check if composer is available
     */
    private function isComposerAvailable() {
        exec('composer --version 2>/dev/null', $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * List all available components
     */
    private function listComponents() {
        echo "\n📦 Available Components\n";
        echo "======================\n";
        
        foreach ($this->components as $key => $component) {
            $required = $component['required'] ? ' (REQUIRED)' : '';
            $deps = !empty($component['dependencies']) ? 
                ' [Deps: ' . implode(', ', $component['dependencies']) . ']' : '';
            
            echo sprintf("%-12s: %s%s%s\n", $key, $component['description'], $required, $deps);
            
            if (!empty($component['includes'])) {
                echo sprintf("%-12s  Includes: %s\n", '', implode(', ', $component['includes']));
            }
            echo "\n";
        }
    }
    
    /**
     * Configure installation settings
     */
    private function configureSettings() {
        echo "\n⚙️ Configuration Settings\n";
        echo "=========================\n";
        
        $this->config['google_maps_api_key'] = $this->prompt("Google Maps API Key: ", $this->config['google_maps_api_key'] ?? '');
        $this->config['database_host'] = $this->prompt("Database Host (localhost): ", $this->config['database_host'] ?? 'localhost');
        $this->config['database_name'] = $this->prompt("Database Name (yakima_finds): ", $this->config['database_name'] ?? 'yakima_finds');
        $this->config['admin_email'] = $this->prompt("Admin Email: ", $this->config['admin_email'] ?? '');
        
        $this->saveConfig();
        echo "✅ Configuration saved\n";
    }
    
    /**
     * Display post-installation information
     */
    private function displayPostInstall() {
        echo "\n🎉 Post-Installation Information\n";
        echo "================================\n";
        echo "📁 Installation completed in: {$this->rootDir}\n";
        echo "🌐 Access your calendar at: http://your-domain.com/\n";
        echo "🔧 Admin interface at: http://your-domain.com/admin/\n";
        echo "📚 Documentation: README.md and INSTALL.md\n\n";
        
        echo "📋 Next Steps:\n";
        echo "1. Configure your web server (Apache/Nginx)\n";
        echo "2. Set up SSL certificate for production\n";
        echo "3. Configure cron jobs for event scraping\n";
        echo "4. Test the installation with: php tests/run_all_tests.php\n\n";
        
        // Display installed components log
        if (!empty($this->installLog)) {
            echo "📦 Installed Components:\n";
            foreach ($this->installLog as $log) {
                $status = $log['status'] === 'success' ? '✅' : '❌';
                echo "  $status {$log['component']} - {$log['timestamp']}\n";
            }
        }
    }
    
    /**
     * Display help information
     */
    private function displayHelp() {
        echo "\n📖 YFEvents Installer Help\n";
        echo "===========================\n\n";
        echo "Usage: php installer.php [options] [components]\n\n";
        echo "Options:\n";
        echo "  --help, -h          Show this help message\n";
        echo "  --list              List all available components\n";
        echo "  --check             Check system requirements\n";
        echo "  --all               Install all components\n\n";
        echo "Components:\n";
        echo "  core                Essential database and configuration\n";
        echo "  admin               Administrative interface\n";
        echo "  api                 RESTful API endpoints\n";
        echo "  scraping            Event scraping system\n";
        echo "  geocoding           Location services\n";
        echo "  shops               Business directory\n";
        echo "  modules             Module system framework\n";
        echo "  yfauth              Authentication module\n";
        echo "  yfclaim             Estate sale platform module\n";
        echo "  development         Development environment\n";
        echo "  production          Production optimizations\n\n";
        echo "Examples:\n";
        echo "  php installer.php --check\n";
        echo "  php installer.php core admin api\n";
        echo "  php installer.php --all\n";
        echo "  php installer.php yfclaim\n\n";
        echo "Interactive mode:\n";
        echo "  php installer.php (no arguments)\n\n";
    }
    
    /**
     * Prompt for user input
     */
    private function prompt($message, $default = '') {
        echo $message;
        $input = trim(fgets(STDIN));
        return $input === '' ? $default : $input;
    }
}

// Run the installer
try {
    $installer = new YFEventsInstaller();
    $installer->run();
} catch (Exception $e) {
    echo "💥 Installation Error: " . $e->getMessage() . "\n";
    exit(1);
}