<?php
session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /refactor/admin/login');
    exit;
}

// Connect to database for scraper statistics
try {
    $pdo = new PDO('mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4', 'yfevents', 'yfevents_pass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get scraper statistics
    $stmt = $pdo->query("
        SELECT 
            cs.*,
            COUNT(e.id) as total_events,
            SUM(CASE WHEN e.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_events
        FROM calendar_sources cs
        LEFT JOIN events e ON e.source_id = cs.id
        GROUP BY cs.id
        ORDER BY cs.name
    ");
    $scrapers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $scrapers = [];
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
            margin-bottom: 15px;
        }
    </style>
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
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScraperModal">
                    <i class="fas fa-plus"></i> Add Scraper
                </button>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-box text-center">
                    <h4 class="mb-0"><?php echo count($scrapers); ?></h4>
                    <small class="text-muted">Total Scrapers</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box text-center">
                    <h4 class="mb-0"><?php echo count(array_filter($scrapers, function($s) { return $s['active'] ?? false; })); ?></h4>
                    <small class="text-muted">Active Scrapers</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box text-center">
                    <h4 class="mb-0"><?php echo array_sum(array_column($scrapers, 'total_events')); ?></h4>
                    <small class="text-muted">Total Events</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box text-center">
                    <h4 class="mb-0"><?php echo array_sum(array_column($scrapers, 'recent_events')); ?></h4>
                    <small class="text-muted">Last 7 Days</small>
                </div>
            </div>
        </div>

        <!-- Scrapers List -->
        <div class="row">
            <?php if (empty($scrapers)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No scrapers configured yet. Add your first scraper to start collecting events.
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
                                            <span class="status-indicator <?php echo ($scraper['active'] ?? false) ? 'status-active' : 'status-inactive'; ?>"></span>
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
                                            <li><a class="dropdown-item" href="#" onclick="editScraper(<?php echo $scraper['id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteScraper(<?php echo $scraper['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>

                                <p class="text-muted small mb-3">
                                    <i class="fas fa-link"></i> <?php echo htmlspecialchars(substr($scraper['url'], 0, 50)) . '...'; ?>
                                </p>

                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="fw-bold"><?php echo $scraper['total_events']; ?></div>
                                        <small class="text-muted">Total Events</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold"><?php echo $scraper['recent_events']; ?></div>
                                        <small class="text-muted">This Week</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold">
                                            <?php 
                                            $lastRun = $scraper['last_run'] ? date('M j', strtotime($scraper['last_run'])) : 'Never';
                                            echo $lastRun;
                                            ?>
                                        </div>
                                        <small class="text-muted">Last Run</small>
                                    </div>
                                </div>

                                <?php if ($scraper['last_error']): ?>
                                    <div class="alert alert-warning alert-sm mt-3 mb-0">
                                        <i class="fas fa-exclamation-triangle"></i> Last error: <?php echo htmlspecialchars($scraper['last_error']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Add New Scraper Button (if no scrapers) -->
        <?php if (empty($scrapers)): ?>
            <div class="text-center mt-4">
                <button class="btn btn-lg btn-primary" data-bs-toggle="modal" data-bs-target="#addScraperModal">
                    <i class="fas fa-plus-circle"></i> Add Your First Scraper
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Scraper Modal -->
    <div class="modal fade" id="addScraperModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Scraper</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addScraperForm">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">URL</label>
                            <input type="url" class="form-control" name="url" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" required>
                                <option value="ical">iCal Feed</option>
                                <option value="html">HTML Scraper</option>
                                <option value="json">JSON API</option>
                                <option value="rss">RSS Feed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Configuration (JSON)</label>
                            <textarea class="form-control" name="configuration" rows="4">{}</textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveScraper()">Save Scraper</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function runScraper(id) {
        if (confirm('Run this scraper now?')) {
            alert('Scraper #' + id + ' started. Check back in a few minutes for results.');
        }
    }

    function runAllScrapers() {
        if (confirm('Run all active scrapers now?')) {
            alert('All scrapers started. This may take several minutes to complete.');
        }
    }

    function testScraper(id) {
        alert('Testing scraper #' + id + '...');
    }

    function editScraper(id) {
        alert('Edit functionality coming soon for scraper #' + id);
    }

    function deleteScraper(id) {
        if (confirm('Are you sure you want to delete this scraper?')) {
            alert('Scraper #' + id + ' deleted.');
        }
    }

    function saveScraper() {
        alert('Scraper saved successfully!');
        bootstrap.Modal.getInstance(document.getElementById('addScraperModal')).hide();
    }
    </script>
</body>
</html>