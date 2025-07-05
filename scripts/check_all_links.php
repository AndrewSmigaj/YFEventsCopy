#!/usr/bin/env php
<?php
/**
 * Comprehensive Link Checker for YFEvents
 * Systematically checks all links, forms, and resources across the application
 */

require_once __DIR__ . '/../vendor/autoload.php';

class ComprehensiveLinkChecker
{
    private array $results = [];
    private array $checkedUrls = []; // Cache to avoid duplicate checks
    private array $errors = [];
    private array $warnings = [];
    private int $totalLinks = 0;
    private int $brokenLinks = 0;
    private float $startTime;
    
    // Configuration
    private array $config = [
        'base_url' => 'http://localhost',
        'max_redirects' => 5,
        'timeout' => 10,
        'rate_limit' => 2, // requests per second
        'check_external' => false,
        'follow_redirects' => true,
        'check_fragments' => true,
        'user_agent' => 'YFEvents Link Checker/1.0'
    ];
    
    // Paths to scan
    private array $scanPaths = [
        'public' => __DIR__ . '/../public',
        'www_html' => __DIR__ . '/../www/html',
        'modules' => __DIR__ . '/../modules',
        'routes' => __DIR__ . '/../routes'
    ];
    
    // Link patterns to extract
    private array $linkPatterns = [
        'href' => '/<(?:a|link)[^>]+href=["\']([^"\']+)["\'][^>]*>/i',
        'src' => '/<(?:img|script|iframe)[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
        'action' => '/<form[^>]+action=["\']([^"\']+)["\'][^>]*>/i',
        'data_url' => '/data-(?:url|href|src)=["\']([^"\']+)["\']/i',
        'js_url' => '/(?:url:|href:|\.load\(|fetch\(|\.ajax\(|window\.location(?:\.href)?(?:\s*=\s*|\s*\(\s*))["\']([^"\']+)["\']/i',
        'css_url' => '/url\(["\']?([^"\')\s]+)["\']?\)/i'
    ];
    
    // Session cookies for authenticated checks
    private array $sessionCookies = [];
    
    public function __construct()
    {
        $this->startTime = microtime(true);
        
        // Load config from .env if available
        if (file_exists(__DIR__ . '/../.env')) {
            $env = parse_ini_file(__DIR__ . '/../.env');
            if (isset($env['APP_URL'])) {
                $this->config['base_url'] = rtrim($env['APP_URL'], '/');
            }
        }
    }
    
    /**
     * Run the comprehensive link check
     */
    public function run(): void
    {
        $this->log("=== YFEvents Link Checker ===");
        $this->log("Start time: " . date('Y-m-d H:i:s'));
        $this->log("Base URL: " . $this->config['base_url']);
        $this->log("");
        
        // Phase 1: Scan filesystem for all links
        $this->log("Phase 1: Scanning filesystem for links...");
        $allLinks = $this->scanFilesystem();
        
        // Phase 2: Check route definitions
        $this->log("\nPhase 2: Checking route definitions...");
        $this->checkRoutes();
        
        // Phase 3: Check each unique link
        $this->log("\nPhase 3: Checking all links...");
        $this->checkLinks($allLinks);
        
        // Phase 4: Test authenticated areas
        $this->log("\nPhase 4: Testing authenticated areas...");
        $this->testAuthenticatedAreas();
        
        // Phase 5: Generate report
        $this->log("\nPhase 5: Generating report...");
        $this->generateReport();
    }
    
    /**
     * Scan filesystem for all links
     */
    private function scanFilesystem(): array
    {
        $allLinks = [];
        
        foreach ($this->scanPaths as $name => $path) {
            if (!is_dir($path)) {
                $this->log("  ⚠️  Path not found: $path");
                continue;
            }
            
            $this->log("  Scanning $name: $path");
            $links = $this->scanDirectory($path);
            $this->log("    Found " . count($links) . " links");
            
            foreach ($links as $link => $sources) {
                if (!isset($allLinks[$link])) {
                    $allLinks[$link] = [];
                }
                $allLinks[$link] = array_merge($allLinks[$link], $sources);
            }
        }
        
        $this->totalLinks = count($allLinks);
        $this->log("  Total unique links found: " . $this->totalLinks);
        
        return $allLinks;
    }
    
    /**
     * Recursively scan directory for links
     */
    private function scanDirectory(string $dir): array
    {
        $links = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $this->shouldScanFile($file->getPathname())) {
                $content = file_get_contents($file->getPathname());
                $fileLinks = $this->extractLinks($content, $file->getPathname());
                
                foreach ($fileLinks as $link) {
                    $normalizedLink = $this->normalizeLink($link, $file->getPathname());
                    if ($normalizedLink && $this->shouldCheckLink($normalizedLink)) {
                        if (!isset($links[$normalizedLink])) {
                            $links[$normalizedLink] = [];
                        }
                        $links[$normalizedLink][] = $this->getRelativePath($file->getPathname());
                    }
                }
            }
        }
        
        return $links;
    }
    
    /**
     * Check if file should be scanned
     */
    private function shouldScanFile(string $path): bool
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $scanExtensions = ['php', 'html', 'htm', 'js', 'css'];
        
        // Skip vendor, node_modules, etc
        if (preg_match('/\/(vendor|node_modules|\.git|storage|cache|logs)\//i', $path)) {
            return false;
        }
        
        return in_array($ext, $scanExtensions);
    }
    
    /**
     * Extract links from content
     */
    private function extractLinks(string $content, string $sourcePath): array
    {
        $links = [];
        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION);
        
        // Choose patterns based on file type
        $patterns = [];
        if (in_array($ext, ['php', 'html', 'htm'])) {
            $patterns = ['href', 'src', 'action', 'data_url'];
        } elseif ($ext === 'js') {
            $patterns = ['js_url'];
        } elseif ($ext === 'css') {
            $patterns = ['css_url'];
        }
        
        foreach ($patterns as $patternName) {
            if (isset($this->linkPatterns[$patternName])) {
                preg_match_all($this->linkPatterns[$patternName], $content, $matches);
                if (!empty($matches[1])) {
                    $links = array_merge($links, $matches[1]);
                }
            }
        }
        
        return array_unique($links);
    }
    
    /**
     * Normalize link to absolute URL
     */
    private function normalizeLink(string $link, string $sourcePath): ?string
    {
        // Skip certain link types
        if (preg_match('/^(javascript:|mailto:|tel:|#|data:)/i', $link)) {
            return null;
        }
        
        // External links
        if (preg_match('/^https?:\/\//i', $link)) {
            return $this->config['check_external'] ? $link : null;
        }
        
        // Protocol-relative
        if (strpos($link, '//') === 0) {
            return $this->config['check_external'] ? 'http:' . $link : null;
        }
        
        // Absolute path
        if (strpos($link, '/') === 0) {
            return $this->config['base_url'] . $link;
        }
        
        // Relative path - need to resolve based on source
        $sourceDir = dirname($sourcePath);
        $webRoot = realpath(__DIR__ . '/../');
        
        // Calculate relative path from web root
        $relativeDir = str_replace($webRoot, '', $sourceDir);
        $relativeDir = str_replace('\\', '/', $relativeDir);
        
        // Map filesystem path to web path
        $webPath = $this->mapFileSystemToWeb($relativeDir);
        
        return $this->config['base_url'] . $webPath . '/' . $link;
    }
    
    /**
     * Map filesystem path to web accessible path
     */
    private function mapFileSystemToWeb(string $path): string
    {
        // Remove leading slash
        $path = ltrim($path, '/');
        
        // Map directories to web paths
        $mappings = [
            'www/html' => '',
            'public' => '',
            'modules/yfclaim/www' => '/modules/yfclaim',
            'modules/yfauth/www' => '/modules/yfauth',
            'modules/yftheme/www' => '/modules/yftheme'
        ];
        
        foreach ($mappings as $filesystem => $web) {
            if (strpos($path, $filesystem) === 0) {
                return $web . substr($path, strlen($filesystem));
            }
        }
        
        return '/' . $path;
    }
    
    /**
     * Check if link should be checked
     */
    private function shouldCheckLink(string $link): bool
    {
        // Skip certain patterns
        $skipPatterns = [
            '/\.(jpg|jpeg|png|gif|ico|svg|webp)$/i',
            '/\.(woff|woff2|ttf|eot)$/i',
            '/\.(zip|pdf|doc|docx)$/i'
        ];
        
        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $link)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get relative path for display
     */
    private function getRelativePath(string $path): string
    {
        $root = realpath(__DIR__ . '/../');
        return str_replace($root . '/', '', $path);
    }
    
    /**
     * Check route definitions
     */
    private function checkRoutes(): void
    {
        $routeFile = __DIR__ . '/../routes/web.php';
        if (!file_exists($routeFile)) {
            $this->log("  ⚠️  Route file not found");
            return;
        }
        
        // Parse routes - simplified for now
        $content = file_get_contents($routeFile);
        preg_match_all('/\$router->(?:get|post)\([\'"]([^\'"]+)[\'"]/', $content, $matches);
        
        $routes = array_unique($matches[1]);
        $this->log("  Found " . count($routes) . " route definitions");
        
        // Add routes to check list
        foreach ($routes as $route) {
            // Convert route pattern to URL
            $url = $this->config['base_url'] . $route;
            $url = preg_replace('/\{[^}]+\}/', '1', $url); // Replace placeholders
            
            $this->results[$url] = [
                'source' => ['routes/web.php'],
                'type' => 'route',
                'status' => 'pending'
            ];
        }
    }
    
    /**
     * Check all links
     */
    private function checkLinks(array $links): void
    {
        $count = 0;
        $total = count($links);
        
        foreach ($links as $url => $sources) {
            $count++;
            
            // Rate limiting
            if ($count > 1) {
                usleep(1000000 / $this->config['rate_limit']); // Microseconds
            }
            
            // Progress indicator and incremental save
            if ($count % 10 === 0) {
                $this->log(sprintf("  Progress: %d/%d (%.1f%%)", $count, $total, ($count/$total)*100));
                // Save incremental results
                $this->saveIncrementalResults();
            }
            
            // Check if already tested
            if (isset($this->checkedUrls[$url])) {
                $this->results[$url] = $this->checkedUrls[$url];
                $this->results[$url]['sources'] = array_merge(
                    $this->results[$url]['sources'] ?? [],
                    $sources
                );
                continue;
            }
            
            // Check the link
            $result = $this->checkLink($url);
            $result['sources'] = $sources;
            
            $this->results[$url] = $result;
            $this->checkedUrls[$url] = $result;
            
            // Track broken links
            if ($result['status'] >= 400 || $result['status'] === 0) {
                $this->brokenLinks++;
                $this->errors[] = [
                    'url' => $url,
                    'status' => $result['status'],
                    'sources' => $sources
                ];
            } elseif ($result['time'] > 2.0) {
                $this->warnings[] = [
                    'url' => $url,
                    'type' => 'slow',
                    'time' => $result['time'],
                    'sources' => $sources
                ];
            }
        }
    }
    
    /**
     * Save incremental results to avoid losing data on timeout
     */
    private function saveIncrementalResults(): void
    {
        $tempFile = __DIR__ . '/../link_check_incremental.json';
        file_put_contents($tempFile, json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'progress' => count($this->checkedUrls) . '/' . $this->totalLinks,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'brokenLinks' => $this->brokenLinks
        ], JSON_PRETTY_PRINT));
    }
    
    /**
     * Check individual link
     */
    private function checkLink(string $url): array
    {
        $startTime = microtime(true);
        $result = [
            'status' => 0,
            'time' => 0,
            'size' => 0,
            'type' => 'unknown',
            'redirects' => []
        ];
        
        // Parse URL
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            $result['status'] = -1;
            $result['error'] = 'Invalid URL';
            return $result;
        }
        
        // Check if internal
        $isInternal = strpos($url, $this->config['base_url']) === 0;
        
        if ($isInternal) {
            // For internal links, we can also check file existence
            $path = str_replace($this->config['base_url'], '', $url);
            $path = parse_url($path, PHP_URL_PATH);
            
            // Map URL to filesystem
            $filePath = $this->mapUrlToFilesystem($path);
            if ($filePath && file_exists($filePath)) {
                $result['status'] = 200;
                $result['type'] = 'file';
                $result['size'] = filesize($filePath);
            } else {
                // Try HTTP request
                $result = $this->httpCheck($url);
            }
        } else {
            // External link
            if ($this->config['check_external']) {
                $result = $this->httpCheck($url);
            } else {
                $result['status'] = -2;
                $result['type'] = 'external_skipped';
            }
        }
        
        $result['time'] = microtime(true) - $startTime;
        return $result;
    }
    
    /**
     * Map URL path to filesystem path
     */
    private function mapUrlToFilesystem(string $path): ?string
    {
        // Remove query string
        $path = strtok($path, '?');
        
        // Try different document roots
        $roots = [
            __DIR__ . '/../public',
            __DIR__ . '/../www/html',
            __DIR__ . '/../'
        ];
        
        foreach ($roots as $root) {
            $fullPath = $root . $path;
            
            // Try exact match
            if (file_exists($fullPath)) {
                return $fullPath;
            }
            
            // Try with index.php
            if (is_dir($fullPath) && file_exists($fullPath . '/index.php')) {
                return $fullPath . '/index.php';
            }
            
            // Try with .php extension
            if (file_exists($fullPath . '.php')) {
                return $fullPath . '.php';
            }
        }
        
        return null;
    }
    
    /**
     * Perform HTTP check
     */
    private function httpCheck(string $url): array
    {
        $result = [
            'status' => 0,
            'type' => 'http',
            'redirects' => []
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $this->config['follow_redirects'],
            CURLOPT_MAXREDIRS => $this->config['max_redirects'],
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_USERAGENT => $this->config['user_agent'],
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true, // HEAD request
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        // Add session cookies if available
        if (!empty($this->sessionCookies)) {
            $cookieString = '';
            foreach ($this->sessionCookies as $name => $value) {
                $cookieString .= "$name=$value; ";
            }
            curl_setopt($ch, CURLOPT_COOKIE, rtrim($cookieString));
        }
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        
        if (curl_errno($ch)) {
            $result['error'] = curl_error($ch);
        } else {
            $result['status'] = $info['http_code'];
            $result['size'] = $info['download_content_length'];
            
            // Track redirects
            if ($info['redirect_count'] > 0) {
                $result['final_url'] = $info['url'];
                $result['redirect_count'] = $info['redirect_count'];
            }
        }
        
        curl_close($ch);
        return $result;
    }
    
    /**
     * Test authenticated areas
     */
    private function testAuthenticatedAreas(): void
    {
        $this->log("  Testing admin area access...");
        
        // Test unauthenticated access (should redirect)
        $adminUrls = [
            '/admin/dashboard',
            '/admin/events',
            '/admin/shops',
            '/admin/scrapers'
        ];
        
        foreach ($adminUrls as $path) {
            $url = $this->config['base_url'] . $path;
            $result = $this->checkLink($url);
            
            if ($result['status'] === 200) {
                $this->warnings[] = [
                    'url' => $url,
                    'type' => 'security',
                    'message' => 'Admin page accessible without authentication'
                ];
            }
        }
        
        // TODO: Add authenticated session testing
    }
    
    /**
     * Generate final report
     */
    private function generateReport(): void
    {
        $duration = microtime(true) - $this->startTime;
        $reportFile = __DIR__ . '/../link_check_report_' . date('Y-m-d_His') . '.txt';
        
        $report = $this->formatReport($duration);
        
        // Output to console
        echo "\n" . $report;
        
        // Save to file
        file_put_contents($reportFile, $report);
        $this->log("\nReport saved to: " . basename($reportFile));
    }
    
    /**
     * Format the report
     */
    private function formatReport(float $duration): string
    {
        $report = <<<EOT
=== YFEvents Comprehensive Link Check Report ===
Generated: {date}
Base URL: {base_url}
Duration: {duration}

SUMMARY
-------
Total Links Found: {total_links}
Links Checked: {checked_links}
Broken Links: {broken_links} ({broken_percent}%)
Warnings: {warnings_count}
External Links Skipped: {external_skipped}

EOT;
        
        $checkedCount = count($this->checkedUrls);
        $externalSkipped = 0;
        
        foreach ($this->results as $url => $result) {
            if ($result['status'] === -2) {
                $externalSkipped++;
            }
        }
        
        $report = str_replace([
            '{date}',
            '{base_url}',
            '{duration}',
            '{total_links}',
            '{checked_links}',
            '{broken_links}',
            '{broken_percent}',
            '{warnings_count}',
            '{external_skipped}'
        ], [
            date('Y-m-d H:i:s'),
            $this->config['base_url'],
            sprintf('%.2f seconds', $duration),
            $this->totalLinks,
            $checkedCount,
            $this->brokenLinks,
            $checkedCount > 0 ? sprintf('%.1f', ($this->brokenLinks / $checkedCount) * 100) : '0',
            count($this->warnings),
            $externalSkipped
        ], $report);
        
        // Add broken links section
        if (!empty($this->errors)) {
            $report .= "\nBROKEN LINKS\n";
            $report .= "------------\n";
            foreach ($this->errors as $error) {
                $report .= sprintf("\n❌ %s (Status: %d)\n", $error['url'], $error['status']);
                $report .= "   Found in:\n";
                foreach (array_slice($error['sources'], 0, 3) as $source) {
                    $report .= "   - $source\n";
                }
                if (count($error['sources']) > 3) {
                    $report .= sprintf("   ... and %d more\n", count($error['sources']) - 3);
                }
            }
        }
        
        // Add warnings section
        if (!empty($this->warnings)) {
            $report .= "\nWARNINGS\n";
            $report .= "--------\n";
            foreach ($this->warnings as $warning) {
                if ($warning['type'] === 'slow') {
                    $report .= sprintf("\n⚠️  Slow response: %s (%.2fs)\n", $warning['url'], $warning['time']);
                } elseif ($warning['type'] === 'security') {
                    $report .= sprintf("\n⚠️  Security: %s\n   %s\n", $warning['url'], $warning['message']);
                }
            }
        }
        
        // Add sample of working links
        $report .= "\nSAMPLE WORKING LINKS\n";
        $report .= "-------------------\n";
        $workingCount = 0;
        foreach ($this->results as $url => $result) {
            if ($result['status'] === 200 && $workingCount < 10) {
                $report .= sprintf("✓ %s\n", $url);
                $workingCount++;
            }
        }
        
        return $report;
    }
    
    /**
     * Log message with timestamp
     */
    private function log(string $message): void
    {
        echo "[" . date('H:i:s') . "] " . $message . "\n";
    }
}

// Run the checker
$checker = new ComprehensiveLinkChecker();
$checker->run();