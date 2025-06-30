<?php
declare(strict_types=1);

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$basePath = '/refactor';

// Setup database connection
$config = require dirname(__DIR__) . '/config/database.php';
$dbConfig = $config['database'];
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Handle scraper execution
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'run_scraper':
            $config = $_POST['config'] ?? 'eventbrite';
            $location = $_POST['location'] ?? 'Yakima, WA';
            $pages = (int)($_POST['pages'] ?? 3);
            $csvOnly = isset($_POST['csv_only']);
            
            try {
                // Build command  
                $scriptPath = '/home/robug/YFEvents/scripts/browser-automation';
                $command = 'cd ' . escapeshellarg($scriptPath) . ' && ';
                $command .= 'node scraper.js';
                $command .= ' --config=' . escapeshellarg($config);
                $command .= ' --location=' . escapeshellarg($location);
                $command .= ' --pages=' . escapeshellarg((string)$pages);
                $command .= ' --headless';
                
                if ($csvOnly) {
                    $command .= ' --csv-only';
                }
                
                $command .= ' 2>&1';
                
                // Execute in background
                $output = shell_exec($command);
                
                if ($output) {
                    $message = "Scraper executed successfully. Output: " . substr($output, 0, 500);
                    $messageType = 'success';
                } else {
                    $message = "Scraper executed but no output received.";
                    $messageType = 'warning';
                }
                
            } catch (Exception $e) {
                $message = "Error executing scraper: " . $e->getMessage();
                $messageType = 'error';
            }
            break;
            
        case 'test_connection':
            try {
                $bridgeUrl = 'http://backoffice.yakimafinds.com/scripts/browser-automation/database-bridge.php?action=test';
                $response = @file_get_contents($bridgeUrl);
                
                if ($response) {
                    $data = json_decode($response, true);
                    if ($data && $data['success']) {
                        $message = "Database bridge connection successful. Total events: " . $data['total_events'];
                        $messageType = 'success';
                    } else {
                        $message = "Database bridge error: " . ($data['message'] ?? 'Unknown error');
                        $messageType = 'error';
                    }
                } else {
                    $message = "Could not reach database bridge. Check web server configuration.";
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = "Connection test failed: " . $e->getMessage();
                $messageType = 'error';
            }
            break;
    }
}

// Get scraper statistics
function getScraperStats($pdo): array {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_events,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_events,
            COUNT(CASE WHEN external_event_id LIKE 'browser_%' THEN 1 END) as browser_scraped_events,
            COUNT(CASE WHEN external_event_id LIKE 'browser_eventbrite_%' THEN 1 END) as eventbrite_events,
            COUNT(CASE WHEN external_event_id LIKE 'browser_meetup_%' THEN 1 END) as meetup_events,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as recent_events
        FROM events
    ");
    
    return $stmt->fetch();
}

$stats = getScraperStats($pdo);

// Get recent browser-scraped events
$stmt = $pdo->prepare("
    SELECT id, title, start_datetime, location, status, external_event_id, created_at
    FROM events 
    WHERE external_event_id LIKE 'browser_%'
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recentScrapedEvents = $stmt->fetchAll();

// Available scraper configurations
$availableConfigs = [
    'eventbrite' => [
        'name' => 'Eventbrite',
        'description' => 'Major event platform with strong anti-bot protection',
        'difficulty' => 'High',
        'recommended_pages' => 3
    ],
    'meetup' => [
        'name' => 'Meetup.com',
        'description' => 'Community events and meetups',
        'difficulty' => 'Medium',
        'recommended_pages' => 5
    ],
    'facebook-events' => [
        'name' => 'Facebook Events',
        'description' => 'Facebook public events (experimental)',
        'difficulty' => 'Very High',
        'recommended_pages' => 2
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browser Scrapers - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        .scraper-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .difficulty-badge {
            font-size: 0.75rem;
        }
        .execution-form {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-top: 1rem;
        }
        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            border-radius: 0.375rem;
            padding: 1rem;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.875rem;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-online { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-offline { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <nav class="col-md-2 d-md-block bg-light sidebar">
                <div class="sidebar-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/events.php">Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/shops.php">Shops</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/scrapers.php">Event Scrapers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="<?= $basePath ?>/admin/browser-scrapers.php">Browser Scrapers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/email-events.php">Email Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/email-config.php">Email Config</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/theme.php">Theme</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/settings.php">Settings</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ml-sm-auto col-lg-10 px-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>🤖 Browser Automation Scrapers</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="test_connection">
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                🧪 Test Connection
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="scraper-card">
                            <h6>📊 Total Events</h6>
                            <h3><?= $stats['total_events'] ?></h3>
                            <small><?= $stats['recent_events'] ?> in last 24h</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="scraper-card">
                            <h6>🤖 Browser Scraped</h6>
                            <h3><?= $stats['browser_scraped_events'] ?></h3>
                            <small>Automated collection</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="scraper-card">
                            <h6>🎫 Eventbrite</h6>
                            <h3><?= $stats['eventbrite_events'] ?></h3>
                            <small>Major platform</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="scraper-card">
                            <h6>👥 Meetup</h6>
                            <h3><?= $stats['meetup_events'] ?></h3>
                            <small>Community events</small>
                        </div>
                    </div>
                </div>

                <!-- Scraper Configurations -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h3>🔧 Available Scrapers</h3>
                        <p class="text-muted">Browser automation scrapers use real browsers to bypass anti-bot protection</p>
                    </div>
                </div>

                <div class="row">
                    <?php foreach ($availableConfigs as $configId => $config): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?= htmlspecialchars($config['name']) ?></h5>
                                    <span class="badge difficulty-badge <?= 
                                        $config['difficulty'] === 'High' ? 'bg-warning' : 
                                        ($config['difficulty'] === 'Very High' ? 'bg-danger' : 'bg-success') 
                                    ?>">
                                        <?= htmlspecialchars($config['difficulty']) ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="card-text"><?= htmlspecialchars($config['description']) ?></p>
                                    
                                    <div class="execution-form">
                                        <form method="post">
                                            <input type="hidden" name="action" value="run_scraper">
                                            <input type="hidden" name="config" value="<?= htmlspecialchars($configId) ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Location</label>
                                                <input type="text" name="location" class="form-control" value="Yakima, WA" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Max Pages</label>
                                                <input type="number" name="pages" class="form-control" 
                                                       value="<?= $config['recommended_pages'] ?>" min="1" max="10" required>
                                                <small class="form-text text-muted">Recommended: <?= $config['recommended_pages'] ?></small>
                                            </div>
                                            
                                            <div class="mb-3 form-check">
                                                <input type="checkbox" name="csv_only" class="form-check-input" id="csv_<?= $configId ?>">
                                                <label class="form-check-label" for="csv_<?= $configId ?>">
                                                    CSV export only (skip database)
                                                </label>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                                🚀 Run Scraper
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Recent Scraped Events -->
                <div class="row mt-4">
                    <div class="col-md-8">
                        <h3>📋 Recent Browser-Scraped Events</h3>
                        <?php if (empty($recentScrapedEvents)): ?>
                            <div class="alert alert-info">
                                No events have been scraped via browser automation yet. Run a scraper above to get started!
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Location</th>
                                            <th>Source</th>
                                            <th>Status</th>
                                            <th>Scraped</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentScrapedEvents as $event): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($event['title']) ?></strong>
                                                </td>
                                                <td>
                                                    <?= $event['start_datetime'] ? date('M j, Y g:i A', strtotime($event['start_datetime'])) : 'TBD' ?>
                                                </td>
                                                <td><?= htmlspecialchars($event['location']) ?></td>
                                                <td>
                                                    <?php
                                                    $source = 'Unknown';
                                                    if (strpos($event['external_event_id'], 'browser_eventbrite_') === 0) {
                                                        $source = 'Eventbrite';
                                                    } elseif (strpos($event['external_event_id'], 'browser_meetup_') === 0) {
                                                        $source = 'Meetup';
                                                    } elseif (strpos($event['external_event_id'], 'browser_facebook_') === 0) {
                                                        $source = 'Facebook';
                                                    }
                                                    ?>
                                                    <span class="badge bg-secondary"><?= $source ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $event['status'] === 'approved' ? 'success' : ($event['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                                        <?= ucfirst($event['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?= date('M j, g:i A', strtotime($event['created_at'])) ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <h3>ℹ️ System Information</h3>
                        
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <span class="status-indicator status-online"></span>
                                    Browser Automation
                                </h6>
                                <p class="card-text small">
                                    Node.js scraper with Puppeteer for bypassing anti-bot protection
                                </p>
                                
                                <h6 class="card-title mt-3">
                                    <span class="status-indicator status-online"></span>
                                    Database Bridge
                                </h6>
                                <p class="card-text small">
                                    PHP bridge connecting scrapers to YFEvents database
                                </p>
                                
                                <h6 class="card-title mt-3">
                                    <span class="status-indicator status-warning"></span>
                                    Anti-Detection
                                </h6>
                                <p class="card-text small">
                                    Random delays, human behavior simulation, cookie handling
                                </p>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-body">
                                <h6 class="card-title">🛠️ Manual Commands</h6>
                                <div class="log-viewer">
                                    <div># Navigate to scraper directory</div>
                                    <div>cd /scripts/browser-automation</div>
                                    <div></div>
                                    <div># Install dependencies (once)</div>
                                    <div>npm install</div>
                                    <div></div>
                                    <div># Run specific scrapers</div>
                                    <div>npm run eventbrite</div>
                                    <div>npm run meetup</div>
                                    <div></div>
                                    <div># Custom location</div>
                                    <div>node scraper.js --config=eventbrite \</div>
                                    <div>  --location="Seattle, WA" --pages=3</div>
                                    <div></div>
                                    <div># Debug mode</div>
                                    <div>npm test</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usage Instructions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h5>💡 Usage Tips</h5>
                            <ul class="mb-0">
                                <li><strong>Start Small:</strong> Use 2-3 pages for initial testing</li>
                                <li><strong>Eventbrite:</strong> Has strong anti-bot protection, may require multiple attempts</li>
                                <li><strong>Meetup:</strong> More reliable, good for regular automated runs</li>
                                <li><strong>Review Events:</strong> All scraped events need approval in the Events admin</li>
                                <li><strong>Rate Limiting:</strong> Wait between runs to avoid being blocked</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh stats every 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        
        // Form validation
        document.querySelectorAll('form[method="post"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '⏳ Running...';
                    
                    // Re-enable after 10 seconds
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = submitBtn.innerHTML.replace('⏳ Running...', '🚀 Run Scraper');
                    }, 10000);
                }
            });
        });
    </script>
</body>
</html>