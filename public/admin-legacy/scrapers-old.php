<?php
// Admin Scrapers Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login');
    exit;
}

$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath === '/') {
    $basePath = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scraper Management - YFEvents Admin</title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        /* Page-specific styles for scrapers page */
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .nav-links a.active {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            color: #2c3e50;
        }
        
        .actions-row {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .scrapers-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .scraper-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .scraper-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .scraper-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .scraper-info {
            flex: 1;
        }
        
        .scraper-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .scraper-url {
            font-size: 0.9rem;
            color: #667eea;
            word-break: break-all;
            margin-bottom: 0.5rem;
        }
        
        .scraper-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        .scraper-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border-radius: 4px;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-running {
            background: #fff3cd;
            color: #856404;
        }
        
        .scraper-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-desc {
            font-size: 0.8rem;
            color: #7f8c8d;
            text-transform: uppercase;
        }
        
        .scraping-progress {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .progress-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .progress-items {
            display: grid;
            gap: 1rem;
        }
        
        .progress-item {
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .progress-info {
            flex: 1;
        }
        
        .progress-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .progress-status {
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        .progress-bar {
            width: 200px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #667eea;
            transition: width 0.3s;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.5rem;
            color: #2c3e50;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #7f8c8d;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .form-help {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-top: 0.25rem;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .sources-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 1rem;
        }
        
        .source-item {
            padding: 0.75rem;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .source-item:hover {
            background: #f8f9fa;
        }
        
        .source-item.selected {
            background: #e3f2fd;
            border-color: #667eea;
        }
        
        .source-item input[type="checkbox"] {
            margin-right: 0.5rem;
        }
        
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #333;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
            z-index: 2000;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast.success {
            background: #27ae60;
        }
        
        .toast.error {
            background: #e74c3c;
        }
        
        .loading {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #555;
        }
        
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }
            
            .scraper-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .scraper-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .progress-item {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .progress-bar {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>🛠️ YFEvents Admin</h1>
            <nav class="nav-links">
                <a href="<?= $basePath ?>/admin">Dashboard</a>
                <a href="<?= $basePath ?>/admin/events">Events</a>
                <a href="<?= $basePath ?>/admin/shops">Shops</a>
                <a href="<?= $basePath ?>/admin/scrapers" class="active">Scrapers</a>
                <a href="<?= $basePath ?>/admin/users">Users</a>
                <a href="#" onclick="logout()">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <h2 class="page-title">Scraper Management</h2>
            <div class="actions-row">
                <button class="btn btn-primary" onclick="showAddModal()">
                    <span>+</span> Add Source
                </button>
                <button class="btn btn-success" onclick="showRunModal()">
                    <span>▶️</span> Run Scrapers
                </button>
                <button class="btn btn-warning" onclick="runAllScrapers()">
                    <span>🔄</span> Run All Now
                </button>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row" id="statsRow">
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Total Sources</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Active Sources</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Events Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Last Run</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Success Rate</div>
            </div>
        </div>
        
        <!-- Scraping Progress (hidden by default) -->
        <div id="scrapingProgress" class="scraping-progress" style="display: none;">
            <div class="progress-header">
                <h3 class="progress-title">Scraping in Progress...</h3>
                <button class="btn btn-danger" onclick="stopScraping()">Stop</button>
            </div>
            <div id="progressItems" class="progress-items">
                <!-- Progress items will be added dynamically -->
            </div>
        </div>
        
        <!-- Scrapers List -->
        <div id="scrapersContainer">
            <div class="loading">Loading scrapers...</div>
        </div>
    </div>
    
    <!-- Add/Edit Scraper Modal -->
    <div id="scraperModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add Scraper Source</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="scraperForm">
                <input type="hidden" id="scraperId" name="id">
                
                <div class="form-group">
                    <label for="scraperName">Source Name *</label>
                    <input type="text" id="scraperName" name="name" required>
                    <div class="form-help">A descriptive name for this source</div>
                </div>
                
                <div class="form-group">
                    <label for="scraperUrl">Source URL *</label>
                    <input type="url" id="scraperUrl" name="url" required>
                    <div class="form-help">The URL to scrape events from</div>
                </div>
                
                <div class="form-group">
                    <label for="scraperType">Source Type *</label>
                    <select id="scraperType" name="type" required>
                        <option value="">Select Type</option>
                        <option value="ical">iCal Feed</option>
                        <option value="json">JSON API</option>
                        <option value="html">HTML Page</option>
                        <option value="rss">RSS Feed</option>
                        <option value="custom">Custom Scraper</option>
                    </select>
                    <div class="form-help">The format of the source data</div>
                </div>
                
                <div class="form-group">
                    <label for="scraperConfig">Configuration (JSON)</label>
                    <textarea id="scraperConfig" name="configuration" placeholder='{
  "selector": ".event-item",
  "title": "h3",
  "date": ".event-date",
  "location": ".event-location"
}'></textarea>
                    <div class="form-help">JSON configuration for parsing (optional)</div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="scraperActive" name="is_active" checked>
                        Active (Enable automatic scraping)
                    </label>
                </div>
                
                <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Source</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Run Scrapers Modal -->
    <div id="runModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Run Scrapers</h2>
                <button class="close-btn" onclick="closeRunModal()">&times;</button>
            </div>
            <div>
                <p style="margin-bottom: 1rem;">Select which sources to scrape:</p>
                <div class="sources-list" id="sourcesList">
                    <div class="loading">Loading sources...</div>
                </div>
                <div style="margin-top: 1rem;">
                    <label class="checkbox-label">
                        <input type="checkbox" id="selectAllSources" onchange="toggleAllSources(this)">
                        Select All
                    </label>
                </div>
                <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeRunModal()">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="startSelectedScrapers()">
                        <span>▶️</span> Start Scraping
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        let scrapersData = [];
        let scrapingInterval = null;
        let currentScrapingJob = null;
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadScrapers();
        });
        
        async function loadStatistics() {
            try {
                const response = await fetch(`${basePath}/api/scrapers/statistics`);
                const data = await response.json();
                
                if (data.success) {
                    const stats = data.data;
                    const statsRow = document.getElementById('statsRow');
                    
                    const lastRun = stats.last_run ? new Date(stats.last_run).toLocaleString() : 'Never';
                    const successRate = stats.total_runs > 0 
                        ? Math.round((stats.successful_runs / stats.total_runs) * 100) + '%'
                        : 'N/A';
                    
                    statsRow.innerHTML = `
                        <div class="stat-card">
                            <div class="stat-value">${stats.total_sources || 0}</div>
                            <div class="stat-label">Total Sources</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.active_sources || 0}</div>
                            <div class="stat-label">Active Sources</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.events_today || 0}</div>
                            <div class="stat-label">Events Today</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value" style="font-size: 1.2rem;">${lastRun}</div>
                            <div class="stat-label">Last Run</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${successRate}</div>
                            <div class="stat-label">Success Rate</div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }
        
        async function loadScrapers() {
            try {
                const response = await fetch(`${basePath}/api/scrapers`);
                const data = await response.json();
                
                if (data.success) {
                    scrapersData = data.data;
                    renderScrapers();
                } else {
                    showToast(data.message || 'Failed to load scrapers', 'error');
                }
            } catch (error) {
                console.error('Error loading scrapers:', error);
                showToast('Error loading scrapers', 'error');
            }
        }
        
        function renderScrapers() {
            const container = document.getElementById('scrapersContainer');
            
            if (scrapersData.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h3>No scrapers configured</h3>
                        <p>Add a scraper source to start collecting events.</p>
                        <button class="btn btn-primary" onclick="showAddModal()" style="margin-top: 1rem;">
                            <span>+</span> Add First Source
                        </button>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="scrapers-grid">';
            
            scrapersData.forEach(scraper => {
                const lastRun = scraper.last_run ? new Date(scraper.last_run).toLocaleString() : 'Never';
                const successRate = scraper.total_runs > 0 
                    ? Math.round((scraper.successful_runs / scraper.total_runs) * 100) 
                    : 0;
                
                html += `
                    <div class="scraper-card">
                        <div class="scraper-header">
                            <div class="scraper-info">
                                <h3 class="scraper-name">${escapeHtml(scraper.name)}</h3>
                                <div class="scraper-url">${escapeHtml(scraper.url)}</div>
                                <div class="scraper-meta">
                                    <span>Type: ${scraper.type.toUpperCase()}</span>
                                    <span>Last Run: ${lastRun}</span>
                                    <span class="status-indicator status-${scraper.is_active ? 'active' : 'inactive'}">
                                        ${scraper.is_active ? '✓ Active' : '✗ Inactive'}
                                    </span>
                                </div>
                            </div>
                            <div class="scraper-actions">
                                <button class="btn btn-primary action-btn" onclick="editScraper(${scraper.id})">Edit</button>
                                <button class="btn btn-success action-btn" onclick="testScraper(${scraper.id})">Test</button>
                                <button class="btn btn-warning action-btn" onclick="runScraper(${scraper.id})">Run Now</button>
                                <button class="btn btn-danger action-btn" onclick="deleteScraper(${scraper.id})">Delete</button>
                            </div>
                        </div>
                        <div class="scraper-stats">
                            <div class="stat-item">
                                <div class="stat-number">${scraper.events_found || 0}</div>
                                <div class="stat-desc">Events Found</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">${scraper.total_runs || 0}</div>
                                <div class="stat-desc">Total Runs</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">${successRate}%</div>
                                <div class="stat-desc">Success Rate</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">${scraper.avg_runtime || 0}s</div>
                                <div class="stat-desc">Avg Runtime</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Scraper Source';
            document.getElementById('scraperForm').reset();
            document.getElementById('scraperId').value = '';
            document.getElementById('scraperActive').checked = true;
            document.getElementById('scraperModal').classList.add('show');
        }
        
        function editScraper(scraperId) {
            const scraper = scrapersData.find(s => s.id === scraperId);
            if (!scraper) return;
            
            document.getElementById('modalTitle').textContent = 'Edit Scraper Source';
            document.getElementById('scraperId').value = scraper.id;
            document.getElementById('scraperName').value = scraper.name;
            document.getElementById('scraperUrl').value = scraper.url;
            document.getElementById('scraperType').value = scraper.type;
            document.getElementById('scraperConfig').value = scraper.configuration || '';
            document.getElementById('scraperActive').checked = scraper.is_active;
            
            document.getElementById('scraperModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('scraperModal').classList.remove('show');
        }
        
        document.getElementById('scraperForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = {
                id: formData.get('id') || undefined,
                name: formData.get('name'),
                url: formData.get('url'),
                type: formData.get('type'),
                configuration: formData.get('configuration'),
                is_active: document.getElementById('scraperActive').checked
            };
            
            // Validate JSON configuration if provided
            if (data.configuration) {
                try {
                    JSON.parse(data.configuration);
                } catch (e) {
                    showToast('Invalid JSON configuration', 'error');
                    return;
                }
            }
            
            const isEdit = !!data.id;
            const url = isEdit ? `${basePath}/api/scrapers/update` : `${basePath}/api/scrapers/create`;
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message || `Scraper ${isEdit ? 'updated' : 'created'} successfully`, 'success');
                    closeModal();
                    loadScrapers();
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to save scraper', 'error');
                }
            } catch (error) {
                console.error('Error saving scraper:', error);
                showToast('Error saving scraper', 'error');
            }
        });
        
        async function testScraper(scraperId) {
            showToast('Testing scraper...', 'success');
            
            try {
                const response = await fetch(`${basePath}/api/scrapers/test`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: scraperId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`Test successful! Found ${result.data.events_found} events`, 'success');
                } else {
                    showToast(result.message || 'Test failed', 'error');
                }
            } catch (error) {
                console.error('Error testing scraper:', error);
                showToast('Error testing scraper', 'error');
            }
        }
        
        async function runScraper(scraperId) {
            if (!confirm('Run this scraper now?')) return;
            
            startScrapingUI([scraperId]);
            
            try {
                const response = await fetch(`${basePath}/api/scrapers/run`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ source_ids: [scraperId] })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Scraping completed successfully', 'success');
                    loadScrapers();
                    loadStatistics();
                } else {
                    showToast(result.message || 'Scraping failed', 'error');
                }
            } catch (error) {
                console.error('Error running scraper:', error);
                showToast('Error running scraper', 'error');
            } finally {
                stopScrapingUI();
            }
        }
        
        async function deleteScraper(scraperId) {
            if (!confirm('Are you sure you want to delete this scraper?')) return;
            
            try {
                const response = await fetch(`${basePath}/api/scrapers/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: scraperId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Scraper deleted successfully', 'success');
                    loadScrapers();
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to delete scraper', 'error');
                }
            } catch (error) {
                console.error('Error deleting scraper:', error);
                showToast('Error deleting scraper', 'error');
            }
        }
        
        function showRunModal() {
            renderSourcesList();
            document.getElementById('runModal').classList.add('show');
        }
        
        function closeRunModal() {
            document.getElementById('runModal').classList.remove('show');
        }
        
        function renderSourcesList() {
            const container = document.getElementById('sourcesList');
            
            if (scrapersData.length === 0) {
                container.innerHTML = '<p>No sources available</p>';
                return;
            }
            
            let html = '';
            scrapersData.forEach(scraper => {
                if (scraper.is_active) {
                    html += `
                        <label class="source-item">
                            <input type="checkbox" value="${scraper.id}" checked>
                            <span>
                                <strong>${escapeHtml(scraper.name)}</strong>
                                <small style="display: block; color: #7f8c8d;">
                                    ${scraper.type.toUpperCase()} • ${escapeHtml(scraper.url)}
                                </small>
                            </span>
                        </label>
                    `;
                }
            });
            
            container.innerHTML = html || '<p>No active sources</p>';
        }
        
        function toggleAllSources(checkbox) {
            const checkboxes = document.querySelectorAll('#sourcesList input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }
        
        async function startSelectedScrapers() {
            const selectedIds = [];
            document.querySelectorAll('#sourcesList input[type="checkbox"]:checked').forEach(cb => {
                selectedIds.push(parseInt(cb.value));
            });
            
            if (selectedIds.length === 0) {
                showToast('Please select at least one source', 'error');
                return;
            }
            
            closeRunModal();
            startScrapingUI(selectedIds);
            
            try {
                const response = await fetch(`${basePath}/api/scrapers/run`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ source_ids: selectedIds })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`Scraping completed! Processed ${selectedIds.length} sources`, 'success');
                    loadScrapers();
                    loadStatistics();
                } else {
                    showToast(result.message || 'Scraping failed', 'error');
                }
            } catch (error) {
                console.error('Error running scrapers:', error);
                showToast('Error running scrapers', 'error');
            } finally {
                stopScrapingUI();
            }
        }
        
        async function runAllScrapers() {
            if (!confirm('Run all active scrapers now?')) return;
            
            const activeScrapers = scrapersData.filter(s => s.is_active);
            if (activeScrapers.length === 0) {
                showToast('No active scrapers to run', 'error');
                return;
            }
            
            const scraperIds = activeScrapers.map(s => s.id);
            startScrapingUI(scraperIds);
            
            try {
                const response = await fetch(`${basePath}/api/scrapers/run-all`, {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`All scrapers completed! Processed ${activeScrapers.length} sources`, 'success');
                    loadScrapers();
                    loadStatistics();
                } else {
                    showToast(result.message || 'Scraping failed', 'error');
                }
            } catch (error) {
                console.error('Error running all scrapers:', error);
                showToast('Error running all scrapers', 'error');
            } finally {
                stopScrapingUI();
            }
        }
        
        function startScrapingUI(scraperIds) {
            const progressDiv = document.getElementById('scrapingProgress');
            const progressItems = document.getElementById('progressItems');
            
            progressDiv.style.display = 'block';
            progressItems.innerHTML = '';
            
            scraperIds.forEach(id => {
                const scraper = scrapersData.find(s => s.id === id);
                if (scraper) {
                    const itemHtml = `
                        <div class="progress-item" id="progress-${id}">
                            <div class="progress-info">
                                <div class="progress-name">${escapeHtml(scraper.name)}</div>
                                <div class="progress-status">Starting...</div>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 0%"></div>
                            </div>
                        </div>
                    `;
                    progressItems.insertAdjacentHTML('beforeend', itemHtml);
                }
            });
            
            // Simulate progress
            let progress = 0;
            scrapingInterval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress > 90) progress = 90;
                
                scraperIds.forEach(id => {
                    const fill = document.querySelector(`#progress-${id} .progress-fill`);
                    if (fill) {
                        fill.style.width = `${progress}%`;
                    }
                });
            }, 500);
        }
        
        function stopScrapingUI() {
            if (scrapingInterval) {
                clearInterval(scrapingInterval);
                scrapingInterval = null;
            }
            
            // Complete all progress bars
            document.querySelectorAll('.progress-fill').forEach(fill => {
                fill.style.width = '100%';
            });
            
            document.querySelectorAll('.progress-status').forEach(status => {
                status.textContent = 'Completed';
            });
            
            // Hide after delay
            setTimeout(() => {
                document.getElementById('scrapingProgress').style.display = 'none';
            }, 2000);
        }
        
        function stopScraping() {
            // In a real implementation, this would cancel the scraping job
            stopScrapingUI();
            showToast('Scraping stopped', 'success');
        }
        
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        async function logout() {
            try {
                const response = await fetch(`${basePath}/admin/logout`, { method: 'POST' });
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = `${basePath}/admin/login`;
                }
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = `${basePath}/admin/login`;
            }
        }
    </script>
</body>
</html>