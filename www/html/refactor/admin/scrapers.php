<?php
require_once __DIR__ . '/../vendor/autoload.php';
use YFEvents\Helpers\PathHelper;

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: ' . PathHelper::adminUrl('login'));
    exit;
}

// Connect to database
try {
    $pdo = new PDO('mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4', 'yfevents', 'yfevents_pass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all scrapers
    $stmt = $pdo->query("
        SELECT 
            cs.*,
            COUNT(e.id) as total_events,
            MAX(e.created_at) as last_event_date
        FROM calendar_sources cs
        LEFT JOIN events e ON e.source_id = cs.id
        GROUP BY cs.id
        ORDER BY cs.name
    ");
    $scrapers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT cs.id) as total_scrapers,
            SUM(cs.active) as active_scrapers,
            COUNT(DISTINCT e.id) as total_events,
            COUNT(DISTINCT CASE WHEN e.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN e.id END) as recent_events
        FROM calendar_sources cs
        LEFT JOIN events e ON e.source_id = cs.id
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $scrapers = [];
    $stats = ['total_scrapers' => 0, 'active_scrapers' => 0, 'total_events' => 0, 'recent_events' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Scrapers - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .scraper-card {
            border: none;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .scraper-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .source-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .source-type.ical { background-color: #e3f2fd; color: #1976d2; }
        .source-type.html { background-color: #fff3e0; color: #f57c00; }
        .source-type.json { background-color: #e8f5e9; color: #388e3c; }
        .source-type.rss { background-color: #fce4ec; color: #c2185b; }
        .source-type.intelligent { background-color: #f3e5f5; color: #7b1fa2; }
        .source-type.firecrawl { background-color: #ffebee; color: #c62828; }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-active { background-color: #4caf50; }
        .status-inactive { background-color: #f44336; }
        .stats-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
    </style>
    <script>
        const basePath = '<?= PathHelper::getBasePath() ?>';
    </script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="./dashboard.php">
                <i class="fas fa-calendar-alt"></i> YFEvents Admin
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['admin_username'] ?? 'Admin'; ?>
                </span>
                <a class="btn btn-outline-light btn-sm" href="../">
                    <i class="fas fa-home"></i> View Site
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3">
                    <i class="fas fa-spider"></i> Event Scrapers
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Scrapers</li>
                    </ol>
                </nav>
            </div>
            <div class="col-auto">
                <button class="btn btn-success" onclick="runAllScrapers()">
                    <i class="fas fa-play"></i> Run All Scrapers
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-box">
                    <h4 class="mb-0"><?php echo $stats['total_scrapers']; ?></h4>
                    <small class="text-muted">Total Scrapers</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-box">
                    <h4 class="mb-0"><?php echo $stats['active_scrapers'] ?? 0; ?></h4>
                    <small class="text-muted">Active Scrapers</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-box">
                    <h4 class="mb-0"><?php echo $stats['total_events']; ?></h4>
                    <small class="text-muted">Total Events</small>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-box">
                    <h4 class="mb-0"><?php echo $stats['recent_events']; ?></h4>
                    <small class="text-muted">Last 7 Days</small>
                </div>
            </div>
        </div>

        <!-- Scrapers List -->
        <div class="row">
            <?php if (empty($scrapers)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No scrapers configured yet.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($scrapers as $scraper): ?>
                    <div class="col-md-6">
                        <div class="card scraper-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-1">
                                            <span class="status-indicator <?php echo $scraper['active'] ? 'status-active' : 'status-inactive'; ?>"></span>
                                            <?php echo htmlspecialchars($scraper['name']); ?>
                                        </h5>
                                        <span class="source-type <?php echo strtolower($scraper['scrape_type'] ?? 'html'); ?>">
                                            <?php echo strtoupper($scraper['scrape_type'] ?? 'HTML'); ?>
                                        </span>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="runScraper(<?php echo $scraper['id']; ?>)">
                                                <i class="fas fa-play"></i> Run Now
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="testScraper(<?php echo $scraper['id']; ?>)">
                                                <i class="fas fa-vial"></i> Test
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>

                                <p class="text-muted small mb-3">
                                    <i class="fas fa-link"></i> 
                                    <a href="<?php echo htmlspecialchars($scraper['url']); ?>" target="_blank" class="text-muted">
                                        <?php echo htmlspecialchars(substr($scraper['url'], 0, 50)) . (strlen($scraper['url']) > 50 ? '...' : ''); ?>
                                    </a>
                                </p>

                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="fw-bold"><?php echo $scraper['total_events'] ?? 0; ?></div>
                                        <small class="text-muted">Total Events</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold">
                                            <?php 
                                            echo $scraper['last_scraped'] ? date('M j', strtotime($scraper['last_scraped'])) : 'Never';
                                            ?>
                                        </div>
                                        <small class="text-muted">Last Run</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold">
                                            <?php 
                                            echo $scraper['last_event_date'] ? date('M j', strtotime($scraper['last_event_date'])) : 'N/A';
                                            ?>
                                        </div>
                                        <small class="text-muted">Last Event</small>
                                    </div>
                                </div>

                                <?php if (!empty($scraper['last_error'])): ?>
                                    <div class="alert alert-warning alert-sm mt-3 mb-0">
                                        <i class="fas fa-exclamation-triangle"></i> Error: <?php echo htmlspecialchars(substr($scraper['last_error'], 0, 100)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function showToast(message, type = 'info') {
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.innerHTML = toastHtml;
        document.body.appendChild(toastContainer);
        
        const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
        toast.show();
        
        setTimeout(() => toastContainer.remove(), 5000);
    }

    async function runScraper(id) {
        if (!confirm('Run this scraper now?')) return;
        
        try {
            showToast('Starting scraper...', 'info');
            
            const response = await fetch(`${basePath}/api/scrapers/run-simple.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ scraper_id: id }),
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(`Scraper completed! Found ${data.events_count || 0} events.`, 'success');
                // Reload page to show updated stats
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast(data.message || 'Failed to run scraper', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error running scraper. Please try again.', 'error');
        }
    }

    async function testScraper(id) {
        try {
            showToast('Testing scraper...', 'info');
            
            const response = await fetch(`${basePath}/api/scrapers/test.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ scraper_id: id }),
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(`Test successful! Found ${data.test_results?.count || 0} events.`, 'success');
            } else {
                showToast(data.message || 'Test failed', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error testing scraper', 'error');
        }
    }

    async function runAllScrapers() {
        if (!confirm('Run all active scrapers now? This may take several minutes.')) return;
        
        try {
            showToast('Starting all scrapers...', 'info');
            
            const response = await fetch(`${basePath}/api/scrapers/run-all-simple.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include'
            });
            
            const data = await response.json();
            console.log('Scraper response:', data);
            
            if (data.success) {
                let message = 'All scrapers completed! ';
                if (data.events_found && data.events_found > 0) {
                    message += `Found ${data.events_found} events`;
                    if (data.events_added && data.events_added > 0) {
                        message += `, added ${data.events_added} new`;
                    } else {
                        message += ' (all already exist)';
                    }
                } else if (data.total_events && data.total_events > 0) {
                    message += `Processed ${data.total_events} events`;
                } else {
                    message += 'Check console for details';
                }
                showToast(message, 'success');
                // Reload page to show updated stats
                setTimeout(() => location.reload(), 3000);
            } else {
                showToast(data.message || 'Failed to run scrapers', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error running scrapers. Please try again.', 'error');
        }
    }
    </script>
</body>
</html>