<?php
/**
 * Comprehensive Codebase Mapper and Verifier
 * Maps entire YFEvents codebase and verifies all components work together
 */

class CodebaseMapper {
    private $progressFile = __DIR__ . '/codebase_verification_progress.json';
    private $logFile = __DIR__ . '/codebase_verification.log';
    private $progress;
    private $startTime;
    
    public function __construct() {
        $this->startTime = time();
        $this->loadProgress();
        $this->log("=== Codebase Verification Started ===");
    }
    
    private function loadProgress() {
        if (file_exists($this->progressFile)) {
            $this->progress = json_decode(file_get_contents($this->progressFile), true);
        } else {
            $this->initializeProgress();
        }
    }
    
    private function saveProgress() {
        file_put_contents($this->progressFile, json_encode($this->progress, JSON_PRETTY_PRINT));
    }
    
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        echo $logMessage;
    }
    
    private function initializeProgress() {
        $this->progress = [
            'verification_id' => 'verify_' . date('Y_m_d_His'),
            'start_time' => date('c'),
            'status' => 'in_progress',
            'total_modules' => 0,
            'verified_modules' => 0,
            'total_files' => 0,
            'verified_files' => 0,
            'issues_found' => [],
            'modules' => [],
            'current_phase' => 'initialization',
            'phases' => [
                'initialization' => ['status' => 'in_progress', 'start_time' => date('c'), 'tasks' => []],
                'discovery' => ['status' => 'pending', 'tasks' => []],
                'verification' => ['status' => 'pending', 'tasks' => []],
                'integration_testing' => ['status' => 'pending', 'tasks' => []],
                'report_generation' => ['status' => 'pending', 'tasks' => []]
            ]
        ];
    }
    
    public function run() {
        $this->log("Starting comprehensive codebase verification");
        
        // Phase 1: Initialization
        $this->runInitialization();
        
        // Phase 2: Discovery
        $this->runDiscovery();
        
        // Phase 3: Verification
        $this->runVerification();
        
        // Phase 4: Integration Testing
        $this->runIntegrationTesting();
        
        // Phase 5: Report Generation
        $this->generateReport();
        
        $this->log("=== Codebase Verification Completed ===");
    }
    
    private function runInitialization() {
        $this->updatePhase('initialization', 'in_progress');
        $this->log("Phase 1: Initialization");
        
        // Check environment
        $this->addTask('initialization', 'check_php_version', 'Checking PHP version');
        $phpVersion = phpversion();
        $this->log("PHP Version: $phpVersion");
        if (version_compare($phpVersion, '7.4.0', '>=')) {
            $this->completeTask('initialization', 'check_php_version', true, "PHP $phpVersion meets requirements");
        } else {
            $this->completeTask('initialization', 'check_php_version', false, "PHP $phpVersion is below required 7.4.0");
        }
        
        // Check required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'mbstring', 'dom', 'simplexml'];
        foreach ($requiredExtensions as $ext) {
            $taskId = "check_ext_$ext";
            $this->addTask('initialization', $taskId, "Checking PHP extension: $ext");
            if (extension_loaded($ext)) {
                $this->completeTask('initialization', $taskId, true, "Extension $ext is loaded");
            } else {
                $this->completeTask('initialization', $taskId, false, "Extension $ext is missing");
                $this->addIssue('critical', "Missing required PHP extension: $ext");
            }
        }
        
        // Check database connection
        $this->addTask('initialization', 'check_database', 'Checking database connection');
        try {
            require_once __DIR__ . '/config/database.php';
            global $pdo;
            if ($pdo && $pdo->query("SELECT 1")) {
                $this->completeTask('initialization', 'check_database', true, "Database connection successful");
            } else {
                throw new Exception("Database query failed");
            }
        } catch (Exception $e) {
            $this->completeTask('initialization', 'check_database', false, "Database connection failed: " . $e->getMessage());
            $this->addIssue('critical', "Database connection failed: " . $e->getMessage());
        }
        
        $this->updatePhase('initialization', 'completed');
    }
    
    private function runDiscovery() {
        $this->updatePhase('discovery', 'in_progress');
        $this->log("Phase 2: Discovery - Mapping codebase structure");
        
        // Discover main application structure
        $this->addTask('discovery', 'map_structure', 'Mapping directory structure');
        $structure = $this->mapDirectoryStructure(__DIR__);
        $this->progress['structure'] = $structure;
        $this->completeTask('discovery', 'map_structure', true, "Mapped " . count($structure['directories']) . " directories");
        
        // Discover modules
        $this->addTask('discovery', 'discover_modules', 'Discovering modules');
        $modules = $this->discoverModules();
        $this->progress['modules'] = $modules;
        $this->progress['total_modules'] = count($modules);
        $this->completeTask('discovery', 'discover_modules', true, "Found " . count($modules) . " modules");
        
        // Count files
        $this->addTask('discovery', 'count_files', 'Counting files');
        $fileCount = $this->countFiles(__DIR__);
        $this->progress['total_files'] = $fileCount['total'];
        $this->progress['file_types'] = $fileCount['by_type'];
        $this->completeTask('discovery', 'count_files', true, "Found {$fileCount['total']} files");
        
        $this->updatePhase('discovery', 'completed');
        $this->saveProgress();
    }
    
    private function runVerification() {
        $this->updatePhase('verification', 'in_progress');
        $this->log("Phase 3: Verification - Testing components");
        
        // Verify core models
        $this->verifyModels();
        
        // Verify scrapers
        $this->verifyScrapers();
        
        // Verify admin pages
        $this->verifyAdminPages();
        
        // Verify modules
        foreach ($this->progress['modules'] as $moduleName => $moduleInfo) {
            $this->verifyModule($moduleName, $moduleInfo);
        }
        
        $this->updatePhase('verification', 'completed');
        $this->saveProgress();
    }
    
    private function runIntegrationTesting() {
        $this->updatePhase('integration_testing', 'in_progress');
        $this->log("Phase 4: Integration Testing");
        
        // Test database schema
        $this->testDatabaseSchema();
        
        // Test API endpoints
        $this->testAPIEndpoints();
        
        // Test authentication flow
        $this->testAuthenticationFlow();
        
        $this->updatePhase('integration_testing', 'completed');
        $this->saveProgress();
    }
    
    private function mapDirectoryStructure($path, $depth = 0, $maxDepth = 5) {
        $structure = [
            'path' => $path,
            'name' => basename($path),
            'type' => 'directory',
            'directories' => [],
            'files' => []
        ];
        
        if ($depth >= $maxDepth) {
            return $structure;
        }
        
        // Skip certain directories
        $skipDirs = ['vendor', 'node_modules', '.git', 'logs', 'cache', 'tmp'];
        
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $itemPath = $path . '/' . $item;
            
            if (is_dir($itemPath)) {
                if (!in_array($item, $skipDirs)) {
                    $structure['directories'][] = $this->mapDirectoryStructure($itemPath, $depth + 1, $maxDepth);
                }
            } else {
                $structure['files'][] = [
                    'name' => $item,
                    'path' => $itemPath,
                    'size' => filesize($itemPath),
                    'extension' => pathinfo($item, PATHINFO_EXTENSION)
                ];
            }
        }
        
        return $structure;
    }
    
    private function discoverModules() {
        $modules = [];
        $modulesPath = __DIR__ . '/modules';
        
        if (is_dir($modulesPath)) {
            $modulesDirs = scandir($modulesPath);
            foreach ($modulesDirs as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                
                $modulePath = $modulesPath . '/' . $dir;
                if (is_dir($modulePath)) {
                    $moduleInfo = $this->analyzeModule($dir, $modulePath);
                    $modules[$dir] = $moduleInfo;
                    $this->log("Discovered module: $dir");
                }
            }
        }
        
        return $modules;
    }
    
    private function analyzeModule($name, $path) {
        $moduleInfo = [
            'name' => $name,
            'path' => $path,
            'has_config' => file_exists($path . '/config.php'),
            'has_models' => is_dir($path . '/src/Models'),
            'has_controllers' => is_dir($path . '/src/Controllers'),
            'has_views' => is_dir($path . '/views') || is_dir($path . '/templates'),
            'has_www' => is_dir($path . '/www'),
            'has_admin' => is_dir($path . '/www/admin'),
            'has_api' => is_dir($path . '/api') || is_dir($path . '/www/api'),
            'has_tests' => is_dir($path . '/tests'),
            'files' => []
        ];
        
        // Count files in module
        $fileCount = $this->countFiles($path);
        $moduleInfo['file_count'] = $fileCount['total'];
        $moduleInfo['file_types'] = $fileCount['by_type'];
        
        return $moduleInfo;
    }
    
    private function countFiles($path) {
        $count = ['total' => 0, 'by_type' => []];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count['total']++;
                $ext = $file->getExtension();
                if (!isset($count['by_type'][$ext])) {
                    $count['by_type'][$ext] = 0;
                }
                $count['by_type'][$ext]++;
            }
        }
        
        return $count;
    }
    
    private function verifyModels() {
        $this->addTask('verification', 'verify_models', 'Verifying core models');
        $modelsPath = __DIR__ . '/src/Models';
        $issues = [];
        
        if (is_dir($modelsPath)) {
            $models = glob($modelsPath . '/*.php');
            foreach ($models as $modelFile) {
                $modelName = basename($modelFile, '.php');
                try {
                    require_once $modelFile;
                    $className = "YFEvents\\Models\\$modelName";
                    if (class_exists($className)) {
                        $this->log("Model verified: $modelName");
                    } else {
                        $issues[] = "Class not found: $className";
                    }
                } catch (Exception $e) {
                    $issues[] = "Error loading $modelName: " . $e->getMessage();
                }
            }
        }
        
        if (empty($issues)) {
            $this->completeTask('verification', 'verify_models', true, "All models verified");
        } else {
            $this->completeTask('verification', 'verify_models', false, implode('; ', $issues));
            foreach ($issues as $issue) {
                $this->addIssue('error', $issue);
            }
        }
    }
    
    private function verifyScrapers() {
        $this->addTask('verification', 'verify_scrapers', 'Verifying scrapers');
        $scrapersPath = __DIR__ . '/src/Scrapers';
        $issues = [];
        
        if (is_dir($scrapersPath)) {
            $scrapers = glob($scrapersPath . '/*.php');
            foreach ($scrapers as $scraperFile) {
                $scraperName = basename($scraperFile, '.php');
                try {
                    require_once $scraperFile;
                    $className = "YFEvents\\Scrapers\\$scraperName";
                    if (class_exists($className)) {
                        $this->log("Scraper verified: $scraperName");
                    } else {
                        $issues[] = "Class not found: $className";
                    }
                } catch (Exception $e) {
                    $issues[] = "Error loading $scraperName: " . $e->getMessage();
                }
            }
        }
        
        if (empty($issues)) {
            $this->completeTask('verification', 'verify_scrapers', true, "All scrapers verified");
        } else {
            $this->completeTask('verification', 'verify_scrapers', false, implode('; ', $issues));
            foreach ($issues as $issue) {
                $this->addIssue('error', $issue);
            }
        }
    }
    
    private function verifyAdminPages() {
        $this->addTask('verification', 'verify_admin_pages', 'Verifying admin pages');
        $adminPath = __DIR__ . '/www/html/admin';
        $issues = [];
        
        $adminPages = [
            'index.php' => 'Admin Dashboard',
            'login.php' => 'Admin Login',
            'events.php' => 'Events Management',
            'shops.php' => 'Shops Management',
            'scrapers.php' => 'Scrapers Management',
            'settings.php' => 'Settings'
        ];
        
        foreach ($adminPages as $file => $name) {
            $filePath = $adminPath . '/' . $file;
            if (file_exists($filePath)) {
                // Check PHP syntax
                $output = [];
                $return = 0;
                exec("php -l '$filePath' 2>&1", $output, $return);
                
                if ($return === 0) {
                    $this->log("Admin page verified: $name ($file)");
                } else {
                    $issues[] = "$name has syntax errors: " . implode(' ', $output);
                }
            } else {
                $issues[] = "$name not found at $filePath";
            }
        }
        
        if (empty($issues)) {
            $this->completeTask('verification', 'verify_admin_pages', true, "All admin pages verified");
        } else {
            $this->completeTask('verification', 'verify_admin_pages', false, implode('; ', $issues));
            foreach ($issues as $issue) {
                $this->addIssue('error', $issue);
            }
        }
    }
    
    private function verifyModule($moduleName, $moduleInfo) {
        $taskId = "verify_module_$moduleName";
        $this->addTask('verification', $taskId, "Verifying module: $moduleName");
        
        $issues = [];
        
        // Check for essential files
        if (!$moduleInfo['has_config'] && !$moduleInfo['has_models']) {
            $issues[] = "Module appears to be incomplete (no config or models)";
        }
        
        // Verify models if they exist
        if ($moduleInfo['has_models']) {
            $modelsPath = $moduleInfo['path'] . '/src/Models';
            $models = glob($modelsPath . '/*.php');
            foreach ($models as $modelFile) {
                $output = [];
                $return = 0;
                exec("php -l '$modelFile' 2>&1", $output, $return);
                if ($return !== 0) {
                    $issues[] = "Model " . basename($modelFile) . " has syntax errors";
                }
            }
        }
        
        // Verify admin pages if they exist
        if ($moduleInfo['has_admin']) {
            $adminPath = $moduleInfo['path'] . '/www/admin';
            $adminFiles = glob($adminPath . '/*.php');
            foreach ($adminFiles as $adminFile) {
                $output = [];
                $return = 0;
                exec("php -l '$adminFile' 2>&1", $output, $return);
                if ($return !== 0) {
                    $issues[] = "Admin page " . basename($adminFile) . " has syntax errors";
                }
            }
        }
        
        if (empty($issues)) {
            $this->completeTask('verification', $taskId, true, "Module verified successfully");
            $this->progress['verified_modules']++;
        } else {
            $this->completeTask('verification', $taskId, false, implode('; ', $issues));
            foreach ($issues as $issue) {
                $this->addIssue('warning', "Module $moduleName: $issue");
            }
        }
    }
    
    private function testDatabaseSchema() {
        $this->addTask('integration_testing', 'test_db_schema', 'Testing database schema');
        
        try {
            // Load database connection
            require_once __DIR__ . '/config/database.php';
            global $pdo;
            
            if (!$pdo) {
                throw new Exception("Database connection not available");
            }
            
            // Get all tables
            $tables = [];
            $result = $pdo->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            $this->log("Found " . count($tables) . " tables in database");
            
            // Check core tables
            $coreTables = ['events', 'calendar_sources', 'local_shops', 'event_categories'];
            $missingTables = array_diff($coreTables, $tables);
            
            if (empty($missingTables)) {
                $this->completeTask('integration_testing', 'test_db_schema', true, 
                    "All core tables present. Total tables: " . count($tables));
            } else {
                $this->completeTask('integration_testing', 'test_db_schema', false, 
                    "Missing tables: " . implode(', ', $missingTables));
                foreach ($missingTables as $table) {
                    $this->addIssue('critical', "Missing required table: $table");
                }
            }
            
        } catch (Exception $e) {
            $this->completeTask('integration_testing', 'test_db_schema', false, 
                "Database error: " . $e->getMessage());
            $this->addIssue('critical', "Cannot test database schema: " . $e->getMessage());
        }
    }
    
    private function testAPIEndpoints() {
        $this->addTask('integration_testing', 'test_api_endpoints', 'Testing API endpoints');
        
        $endpoints = [
            '/api/events' => 'GET',
            '/api/shops' => 'GET',
            '/api/calendar-sources' => 'GET'
        ];
        
        $baseUrl = 'http://localhost';
        $issues = [];
        
        foreach ($endpoints as $endpoint => $method) {
            $ch = curl_init($baseUrl . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $this->log("API endpoint OK: $method $endpoint (HTTP $httpCode)");
            } else {
                $issues[] = "$method $endpoint returned HTTP $httpCode";
            }
        }
        
        if (empty($issues)) {
            $this->completeTask('integration_testing', 'test_api_endpoints', true, 
                "All API endpoints responding");
        } else {
            $this->completeTask('integration_testing', 'test_api_endpoints', false, 
                implode('; ', $issues));
            foreach ($issues as $issue) {
                $this->addIssue('warning', "API: $issue");
            }
        }
    }
    
    private function testAuthenticationFlow() {
        $this->addTask('integration_testing', 'test_auth_flow', 'Testing authentication flow');
        
        // Test admin login page accessibility
        $loginUrl = 'http://localhost/admin/login.php';
        $ch = curl_init($loginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $this->completeTask('integration_testing', 'test_auth_flow', true, 
                "Admin login page accessible");
        } else {
            $this->completeTask('integration_testing', 'test_auth_flow', false, 
                "Admin login page returned HTTP $httpCode");
            $this->addIssue('error', "Cannot access admin login page");
        }
    }
    
    private function generateReport() {
        $this->updatePhase('report_generation', 'in_progress');
        $this->log("Phase 5: Generating comprehensive report");
        
        $report = [
            'summary' => [
                'verification_id' => $this->progress['verification_id'],
                'start_time' => $this->progress['start_time'],
                'end_time' => date('c'),
                'duration_seconds' => time() - strtotime($this->progress['start_time']),
                'total_modules' => $this->progress['total_modules'],
                'verified_modules' => $this->progress['verified_modules'],
                'total_files' => $this->progress['total_files'],
                'total_issues' => count($this->progress['issues_found']),
                'critical_issues' => count(array_filter($this->progress['issues_found'], 
                    function($i) { return $i['severity'] === 'critical'; })),
                'status' => empty(array_filter($this->progress['issues_found'], 
                    function($i) { return $i['severity'] === 'critical'; })) ? 'PASSED' : 'FAILED'
            ],
            'modules' => $this->progress['modules'],
            'issues' => $this->progress['issues_found'],
            'phases' => $this->progress['phases'],
            'file_types' => $this->progress['file_types'] ?? []
        ];
        
        // Save detailed report
        $reportPath = __DIR__ . '/codebase_verification_report_' . date('Y_m_d_His') . '.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        $this->log("Detailed report saved to: $reportPath");
        
        // Generate human-readable summary
        $this->generateHumanReadableSummary($report);
        
        $this->updatePhase('report_generation', 'completed');
        $this->progress['status'] = 'completed';
        $this->saveProgress();
    }
    
    private function generateHumanReadableSummary($report) {
        $summaryPath = __DIR__ . '/codebase_verification_summary.md';
        
        $summary = "# YFEvents Codebase Verification Summary\n\n";
        $summary .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $summary .= "## Overall Status: **{$report['summary']['status']}**\n\n";
        
        $summary .= "## Summary Statistics\n";
        $summary .= "- Total Modules: {$report['summary']['total_modules']}\n";
        $summary .= "- Verified Modules: {$report['summary']['verified_modules']}\n";
        $summary .= "- Total Files: {$report['summary']['total_files']}\n";
        $summary .= "- Total Issues: {$report['summary']['total_issues']}\n";
        $summary .= "- Critical Issues: {$report['summary']['critical_issues']}\n";
        $summary .= "- Verification Duration: " . gmdate("H:i:s", $report['summary']['duration_seconds']) . "\n\n";
        
        if (!empty($report['file_types'])) {
            $summary .= "## File Distribution\n";
            arsort($report['file_types']);
            foreach ($report['file_types'] as $ext => $count) {
                $summary .= "- .$ext: $count files\n";
            }
            $summary .= "\n";
        }
        
        $summary .= "## Modules\n";
        foreach ($report['modules'] as $module) {
            $summary .= "### {$module['name']}\n";
            $summary .= "- Path: {$module['path']}\n";
            $summary .= "- Files: {$module['file_count']}\n";
            $summary .= "- Components: ";
            $components = [];
            if ($module['has_models']) $components[] = "Models";
            if ($module['has_controllers']) $components[] = "Controllers";
            if ($module['has_views']) $components[] = "Views";
            if ($module['has_admin']) $components[] = "Admin";
            if ($module['has_api']) $components[] = "API";
            $summary .= implode(', ', $components) . "\n\n";
        }
        
        if (!empty($report['issues'])) {
            $summary .= "## Issues Found\n";
            
            // Group by severity
            $issuesBySeverity = [];
            foreach ($report['issues'] as $issue) {
                $issuesBySeverity[$issue['severity']][] = $issue;
            }
            
            foreach (['critical', 'error', 'warning', 'info'] as $severity) {
                if (isset($issuesBySeverity[$severity])) {
                    $summary .= "\n### " . ucfirst($severity) . " Issues\n";
                    foreach ($issuesBySeverity[$severity] as $issue) {
                        $summary .= "- {$issue['message']}\n";
                    }
                }
            }
        }
        
        $summary .= "\n## Verification Phases\n";
        foreach ($report['phases'] as $phaseName => $phase) {
            $status = $phase['status'] === 'completed' ? '✓' : '✗';
            $summary .= "- $status " . ucwords(str_replace('_', ' ', $phaseName)) . "\n";
        }
        
        file_put_contents($summaryPath, $summary);
        $this->log("Human-readable summary saved to: $summaryPath");
    }
    
    private function updatePhase($phaseName, $status) {
        $this->progress['phases'][$phaseName]['status'] = $status;
        if ($status === 'completed') {
            $this->progress['phases'][$phaseName]['end_time'] = date('c');
        }
        $this->progress['current_phase'] = $phaseName;
        $this->saveProgress();
    }
    
    private function addTask($phase, $taskId, $description) {
        $this->progress['phases'][$phase]['tasks'][$taskId] = [
            'description' => $description,
            'status' => 'in_progress',
            'start_time' => date('c')
        ];
        $this->saveProgress();
    }
    
    private function completeTask($phase, $taskId, $success, $message = '') {
        $this->progress['phases'][$phase]['tasks'][$taskId]['status'] = $success ? 'success' : 'failed';
        $this->progress['phases'][$phase]['tasks'][$taskId]['end_time'] = date('c');
        $this->progress['phases'][$phase]['tasks'][$taskId]['message'] = $message;
        $this->saveProgress();
    }
    
    private function addIssue($severity, $message) {
        $this->progress['issues_found'][] = [
            'severity' => $severity,
            'message' => $message,
            'timestamp' => date('c')
        ];
        $this->saveProgress();
    }
}

// Run the mapper
$mapper = new CodebaseMapper();
$mapper->run();