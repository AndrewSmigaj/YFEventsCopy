<?php
// Admin Scrapers Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /refactor/admin/login');
    exit;
}

// Set correct base path for refactor admin
$basePath = '/refactor';
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
        .scrapers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--spacing-lg);
            padding: var(--spacing-lg);
        }
        
        .scraper-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            transition: var(--transition-normal);
        }
        
        .scraper-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .scraper-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }
        
        .scraper-name {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-semibold);
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .scraper-url {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            word-break: break-all;
        }
        
        .scraper-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-md);
            margin: var(--spacing-md) 0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-item-value {
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-bold);
            color: var(--gray-800);
        }
        
        .stat-item-label {
            font-size: var(--font-size-xs);
            color: var(--gray-600);
            text-transform: uppercase;
        }
        
        .scraper-actions {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
        }
        
        .actions-row {
            display: flex;
            gap: var(--spacing-md);
        }
        
        @media (max-width: 768px) {
            .scrapers-grid {
                grid-template-columns: 1fr;
            }
            
            .scraper-stats {
                grid-template-columns: 1fr;
            }
            
            .scraper-actions {
                flex-direction: column;
            }
            
            .actions-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>🛠️ YFEvents Admin</h1>
            <nav class="nav-links">
                <a href="<?= $basePath ?>/admin/index.php">Dashboard</a>
                <a href="<?= $basePath ?>/admin/events.php">Events</a>
                <a href="<?= $basePath ?>/admin/shops.php">Shops</a>
                <a href="<?= $basePath ?>/admin/claims.php">Claims</a>
                <a href="<?= $basePath ?>/admin/scrapers.php" class="active">Scrapers</a>
                <a href="<?= $basePath ?>/admin/users.php">Users</a>
                <a href="<?= $basePath ?>/admin/settings.php">Settings</a>
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
        <div class="stats-grid" id="statsRow">
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
        
        <!-- Scrapers Container -->
        <div class="table-container">
            <div class="table-header">
                <h3>Scraper Sources</h3>
                <div>
                    <button class="btn btn-sm btn-secondary" onclick="refreshScrapers()">
                        <span>🔄</span> Refresh
                    </button>
                </div>
            </div>
            <div id="scrapersContainer">
                <div class="loading">Loading scrapers...</div>
            </div>
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
                </div>
                
                <div class="form-group">
                    <label for="scraperUrl">URL *</label>
                    <input type="url" id="scraperUrl" name="url" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="scraperType">Type *</label>
                        <select id="scraperType" name="type" required>
                            <option value="">Select Type</option>
                            <option value="ical">iCal/ICS Calendar</option>
                            <option value="html">HTML Scraping</option>
                            <option value="json">JSON API</option>
                            <option value="eventbrite">Eventbrite</option>
                            <option value="facebook">Facebook Events</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="scraperActive" name="active" checked>
                            Active
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="scraperConfig">Configuration (JSON)</label>
                    <textarea id="scraperConfig" name="config" placeholder='{"selectors": {"title": ".event-title", "date": ".event-date"}}'></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Scraper</button>
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
            <div id="runContent">
                <p>Select scrapers to run:</p>
                <div id="scrapersList">
                    <div class="loading">Loading scrapers...</div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeRunModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="startScraping()">Start Scraping</button>
                </div>
            </div>
            <div id="runProgress" style="display: none;">
                <h3>Scraping Progress</h3>
                <div id="progressContent">
                    <div class="loading">Scraping in progress...</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        const apiBasePath = '<?= $basePath ?>'; // API calls should use same base path
        let scrapersData = [];
        let selectedScrapers = new Set();
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadScrapers();
        });
        
        async function loadStatistics() {
            try {
                const response = await fetch(`${apiBasePath}/api/scrapers/statistics`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    const stats = data.data.statistics;
                    const statsRow = document.getElementById('statsRow');
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
                            <div class="stat-value">${stats.scraped_sources || 0}</div>
                            <div class="stat-label">Scraped Sources</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.recent_scrapes || 0}</div>
                            <div class="stat-label">Recent Scrapes</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${Math.round((stats.active_sources / stats.total_sources) * 100) || 0}%</div>
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
                const response = await fetch(`${apiBasePath}/api/scrapers`, {
                    credentials: 'include'
                });
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
                const status = scraper.status === 'active' ? 'active' : 'inactive';
                
                html += `
                    <div class="scraper-card">
                        <div class="scraper-header">
                            <div>
                                <div class="scraper-name">${escapeHtml(scraper.name)}</div>
                                <div class="scraper-url">${scraper.url}</div>
                            </div>
                            <span class="badge badge-${status === 'active' ? 'success' : 'secondary'}">${status}</span>
                        </div>
                        
                        <div class="scraper-stats">
                            <div class="stat-item">
                                <div class="stat-item-value">${scraper.type}</div>
                                <div class="stat-item-label">Type</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-item-value">${lastRun.split(' ')[0] || 'Never'}</div>
                                <div class="stat-item-label">Last Run</div>
                            </div>
                        </div>
                        
                        <div class="scraper-actions">
                            <button class="btn btn-sm btn-primary" onclick="runScraper(${scraper.id})">
                                <span>▶️</span> Run
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="testScraper(${scraper.id})">
                                <span>🧪</span> Test
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="editScraper(${scraper.id})">
                                <span>✏️</span> Edit
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteScraper(${scraper.id})">
                                <span>🗑️</span> Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        async function runAllScrapers() {
            if (!confirm('Are you sure you want to run all active scrapers?')) return;
            
            try {
                showToast('Starting all scrapers...', 'info');
                const response = await fetch(`${apiBasePath}/api/scrapers/run-all`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`Scraping completed! Found ${result.data.total_events_found} events, added ${result.data.total_events_added}`, 'success');
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to run scrapers', 'error');
                }
            } catch (error) {
                console.error('Error running scrapers:', error);
                showToast('Error running scrapers', 'error');
            }
        }
        
        async function runScraper(scraperId) {
            try {
                showToast('Running scraper...', 'info');
                const response = await fetch(`${apiBasePath}/api/scrapers/run`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({ source_ids: [scraperId] })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const scraperResult = result.data.results[0];
                    showToast(`Scraper completed! Found ${scraperResult.events_found} events, added ${scraperResult.events_added}`, 'success');
                } else {
                    showToast(result.message || 'Failed to run scraper', 'error');
                }
            } catch (error) {
                console.error('Error running scraper:', error);
                showToast('Error running scraper', 'error');
            }
        }
        
        function refreshScrapers() {
            loadScrapers();
            loadStatistics();
        }
        
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Scraper Source';
            document.getElementById('scraperForm').reset();
            document.getElementById('scraperId').value = '';
            document.getElementById('scraperModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('scraperModal').classList.remove('show');
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
        
        // Additional functions for edit, test, delete would go here
        async function testScraper(scraperId) {
            showToast('Testing scraper...', 'info');
            // Implementation would call test endpoint
        }
        
        async function editScraper(scraperId) {
            showToast('Edit functionality coming soon', 'info');
            // Implementation would populate form and show modal
        }
        
        async function deleteScraper(scraperId) {
            if (!confirm('Are you sure you want to delete this scraper?')) return;
            showToast('Delete functionality coming soon', 'info');
            // Implementation would call delete endpoint
        }
    </script>
</body>
</html>