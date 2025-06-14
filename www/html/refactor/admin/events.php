<?php
// Admin Events Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /refactor/admin/login');
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
    <title>Event Management - YFEvents Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
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
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
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
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
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
        
        .events-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .bulk-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .bulk-actions.hidden {
            display: none;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .event-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .event-meta {
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-featured {
            background: #cce5ff;
            color: #004085;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 4px;
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
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
        
        .loading {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #555;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        .pagination button {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #f8f9fa;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .events-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
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
                <a href="<?= $basePath ?>/admin/events" class="active">Events</a>
                <a href="<?= $basePath ?>/admin/shops">Shops</a>
                <a href="<?= $basePath ?>/admin/scrapers">Scrapers</a>
                <a href="<?= $basePath ?>/admin/users">Users</a>
                <a href="#" onclick="logout()">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <h2 class="page-title">Event Management</h2>
            <button class="btn btn-primary" onclick="showCreateModal()">
                <span>+</span> Create Event
            </button>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row" id="statsRow">
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Featured</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Today's Events</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="searchInput">Search</label>
                    <input type="text" id="searchInput" placeholder="Search events...">
                </div>
                <div class="filter-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="dateFilter">Date Range</label>
                    <select id="dateFilter">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="featuredFilter">Featured</label>
                    <select id="featuredFilter">
                        <option value="all">All Events</option>
                        <option value="featured">Featured Only</option>
                        <option value="not-featured">Not Featured</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                <button class="btn btn-secondary" onclick="resetFilters()">Reset</button>
                <button class="btn btn-warning" onclick="forceScrape()">
                    <span>🔄</span> Force Scrape
                </button>
            </div>
        </div>
        
        <!-- Events Table -->
        <div class="events-table">
            <div class="table-header">
                <h3>Events List</h3>
                <div class="bulk-actions hidden" id="bulkActions">
                    <span id="selectedCount">0 selected</span>
                    <button class="btn btn-success action-btn" onclick="bulkApprove()">Approve</button>
                    <button class="btn btn-danger action-btn" onclick="bulkReject()">Reject</button>
                </div>
            </div>
            <div id="eventsTableContent">
                <div class="loading">Loading events...</div>
            </div>
        </div>
    </div>
    
    <!-- Event Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Create Event</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="eventForm">
                <input type="hidden" id="eventId" name="id">
                
                <div class="form-group">
                    <label for="eventTitle">Title *</label>
                    <input type="text" id="eventTitle" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="eventDescription">Description</label>
                    <textarea id="eventDescription" name="description"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="eventStartDate">Start Date *</label>
                        <input type="datetime-local" id="eventStartDate" name="start_datetime" required>
                    </div>
                    <div class="form-group">
                        <label for="eventEndDate">End Date</label>
                        <input type="datetime-local" id="eventEndDate" name="end_datetime">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="eventLocation">Location</label>
                    <input type="text" id="eventLocation" name="location">
                </div>
                
                <div class="form-group">
                    <label for="eventAddress">Address</label>
                    <input type="text" id="eventAddress" name="address">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="eventLatitude">Latitude</label>
                        <input type="number" step="any" id="eventLatitude" name="latitude">
                    </div>
                    <div class="form-group">
                        <label for="eventLongitude">Longitude</label>
                        <input type="number" step="any" id="eventLongitude" name="longitude">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="eventContact">Contact Info</label>
                    <input type="text" id="eventContact" name="contact_info">
                </div>
                
                <div class="form-group">
                    <label for="eventUrl">External URL</label>
                    <input type="url" id="eventUrl" name="external_url">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="eventStatus">Status</label>
                        <select id="eventStatus" name="status">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="eventFeatured" name="featured">
                            Featured Event
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Event</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Scraper Modal -->
    <div id="scraperModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Force Event Scraping</h2>
                <button class="close-btn" onclick="closeScraperModal()">&times;</button>
            </div>
            <div id="scraperContent">
                <p>Select which sources to scrape:</p>
                <div id="scraperSources">
                    <div class="loading">Loading sources...</div>
                </div>
                <div class="form-actions" style="margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeScraperModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="startScraping()">Start Scraping</button>
                </div>
            </div>
            <div id="scraperProgress" style="display: none;">
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
        let currentPage = 1;
        let selectedEvents = new Set();
        let currentFilters = {};
        let scraperSources = [];
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadEvents();
        });
        
        async function loadStatistics() {
            try {
                const response = await fetch(`${basePath}/admin/events/statistics`);
                const data = await response.json();
                
                if (data.success) {
                    const stats = data.data.statistics;
                    const statsRow = document.getElementById('statsRow');
                    statsRow.innerHTML = `
                        <div class="stat-card">
                            <div class="stat-value">${stats.total || 0}</div>
                            <div class="stat-label">Total Events</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.pending || 0}</div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.approved || 0}</div>
                            <div class="stat-label">Approved</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.featured || 0}</div>
                            <div class="stat-label">Featured</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.today || 0}</div>
                            <div class="stat-label">Today's Events</div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }
        
        async function loadEvents(page = 1) {
            try {
                currentPage = page;
                const params = new URLSearchParams({
                    page: page,
                    limit: 20,
                    ...currentFilters
                });
                
                const response = await fetch(`${basePath}/admin/events?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    renderEventsTable(data.data.events);
                } else {
                    showToast(data.message || 'Failed to load events', 'error');
                }
            } catch (error) {
                console.error('Error loading events:', error);
                showToast('Error loading events', 'error');
            }
        }
        
        function renderEventsTable(events) {
            const container = document.getElementById('eventsTableContent');
            
            if (events.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h3>No events found</h3>
                        <p>Try adjusting your filters or create a new event.</p>
                    </div>
                `;
                return;
            }
            
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" onchange="toggleSelectAll(this)">
                            </th>
                            <th>Event</th>
                            <th>Date & Time</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            events.forEach(event => {
                const startDate = new Date(event.start_datetime);
                const dateStr = startDate.toLocaleDateString();
                const timeStr = startDate.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                
                html += `
                    <tr>
                        <td>
                            <input type="checkbox" value="${event.id}" onchange="toggleEventSelection(${event.id})">
                        </td>
                        <td>
                            <div class="event-title">${escapeHtml(event.title)}</div>
                            <div class="event-meta">
                                ${event.source_id ? `Source ID: ${event.source_id}` : 'Manual'}
                                ${event.featured ? ' • <span class="status-badge status-featured">Featured</span>' : ''}
                            </div>
                        </td>
                        <td>
                            ${dateStr}<br>
                            <small>${timeStr}</small>
                        </td>
                        <td>
                            ${event.location ? escapeHtml(event.location) : '-'}<br>
                            <small>${event.address ? escapeHtml(event.address) : ''}</small>
                        </td>
                        <td>
                            <span class="status-badge status-${event.status}">${event.status}</span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-primary action-btn" onclick="editEvent(${event.id})">Edit</button>
                                ${event.status === 'pending' ? 
                                    `<button class="btn btn-success action-btn" onclick="approveEvent(${event.id})">Approve</button>` : 
                                    ''
                                }
                                <button class="btn btn-danger action-btn" onclick="deleteEvent(${event.id})">Delete</button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
                <div class="pagination">
                    <button onclick="loadEvents(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
                    <span>Page ${currentPage}</span>
                    <button onclick="loadEvents(${currentPage + 1})" ${events.length < 20 ? 'disabled' : ''}>Next</button>
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('tbody input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                const eventId = parseInt(cb.value);
                if (checkbox.checked) {
                    selectedEvents.add(eventId);
                } else {
                    selectedEvents.delete(eventId);
                }
            });
            updateBulkActions();
        }
        
        function toggleEventSelection(eventId) {
            if (selectedEvents.has(eventId)) {
                selectedEvents.delete(eventId);
            } else {
                selectedEvents.add(eventId);
            }
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedEvents.size > 0) {
                bulkActions.classList.remove('hidden');
                selectedCount.textContent = `${selectedEvents.size} selected`;
            } else {
                bulkActions.classList.add('hidden');
            }
        }
        
        function applyFilters() {
            currentFilters = {
                search: document.getElementById('searchInput').value,
                status: document.getElementById('statusFilter').value,
                featured: document.getElementById('featuredFilter').value
            };
            
            const dateFilter = document.getElementById('dateFilter').value;
            if (dateFilter !== 'all') {
                // Add date filtering logic here
            }
            
            loadEvents(1);
        }
        
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('dateFilter').value = 'all';
            document.getElementById('featuredFilter').value = 'all';
            currentFilters = {};
            loadEvents(1);
        }
        
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create Event';
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';
            document.getElementById('eventModal').classList.add('show');
        }
        
        async function editEvent(eventId) {
            try {
                const response = await fetch(`${basePath}/api/events/${eventId}`);
                const data = await response.json();
                
                if (data.success) {
                    const event = data.data;
                    document.getElementById('modalTitle').textContent = 'Edit Event';
                    document.getElementById('eventId').value = event.id;
                    document.getElementById('eventTitle').value = event.title;
                    document.getElementById('eventDescription').value = event.description || '';
                    document.getElementById('eventStartDate').value = event.start_datetime.slice(0, 16);
                    document.getElementById('eventEndDate').value = event.end_datetime ? event.end_datetime.slice(0, 16) : '';
                    document.getElementById('eventLocation').value = event.location || '';
                    document.getElementById('eventAddress').value = event.address || '';
                    document.getElementById('eventLatitude').value = event.latitude || '';
                    document.getElementById('eventLongitude').value = event.longitude || '';
                    document.getElementById('eventContact').value = event.contact_info || '';
                    document.getElementById('eventUrl').value = event.external_url || '';
                    document.getElementById('eventStatus').value = event.status;
                    document.getElementById('eventFeatured').checked = event.featured;
                    
                    document.getElementById('eventModal').classList.add('show');
                }
            } catch (error) {
                console.error('Error loading event:', error);
                showToast('Error loading event', 'error');
            }
        }
        
        function closeModal() {
            document.getElementById('eventModal').classList.remove('show');
        }
        
        document.getElementById('eventForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            data.featured = document.getElementById('eventFeatured').checked;
            
            const isEdit = !!data.id;
            const url = isEdit ? `${basePath}/admin/events/update` : `${basePath}/admin/events/create`;
            
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
                    showToast(result.message || `Event ${isEdit ? 'updated' : 'created'} successfully`, 'success');
                    closeModal();
                    loadEvents(currentPage);
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to save event', 'error');
                }
            } catch (error) {
                console.error('Error saving event:', error);
                showToast('Error saving event', 'error');
            }
        });
        
        async function approveEvent(eventId) {
            if (!confirm('Are you sure you want to approve this event?')) return;
            
            try {
                const response = await fetch(`${basePath}/admin/events/approve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: eventId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Event approved successfully', 'success');
                    loadEvents(currentPage);
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to approve event', 'error');
                }
            } catch (error) {
                console.error('Error approving event:', error);
                showToast('Error approving event', 'error');
            }
        }
        
        async function deleteEvent(eventId) {
            if (!confirm('Are you sure you want to delete this event?')) return;
            
            try {
                const response = await fetch(`${basePath}/admin/events/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: eventId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Event deleted successfully', 'success');
                    loadEvents(currentPage);
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to delete event', 'error');
                }
            } catch (error) {
                console.error('Error deleting event:', error);
                showToast('Error deleting event', 'error');
            }
        }
        
        async function bulkApprove() {
            if (!confirm(`Are you sure you want to approve ${selectedEvents.size} events?`)) return;
            
            try {
                const response = await fetch(`${basePath}/admin/events/bulk-approve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ event_ids: Array.from(selectedEvents) })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`${result.data.approved_count} events approved successfully`, 'success');
                    selectedEvents.clear();
                    updateBulkActions();
                    loadEvents(currentPage);
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to approve events', 'error');
                }
            } catch (error) {
                console.error('Error approving events:', error);
                showToast('Error approving events', 'error');
            }
        }
        
        async function bulkReject() {
            if (!confirm(`Are you sure you want to reject ${selectedEvents.size} events?`)) return;
            
            try {
                const response = await fetch(`${basePath}/admin/events/bulk-reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ event_ids: Array.from(selectedEvents) })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`${result.data.rejected_count} events rejected successfully`, 'success');
                    selectedEvents.clear();
                    updateBulkActions();
                    loadEvents(currentPage);
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to reject events', 'error');
                }
            } catch (error) {
                console.error('Error rejecting events:', error);
                showToast('Error rejecting events', 'error');
            }
        }
        
        async function forceScrape() {
            // Load available scraper sources
            try {
                const response = await fetch(`${basePath}/api/scrapers`);
                const data = await response.json();
                
                if (data.success) {
                    scraperSources = data.data;
                    renderScraperSources();
                    document.getElementById('scraperModal').classList.add('show');
                }
            } catch (error) {
                console.error('Error loading scraper sources:', error);
                showToast('Error loading scraper sources', 'error');
            }
        }
        
        function renderScraperSources() {
            const container = document.getElementById('scraperSources');
            
            if (scraperSources.length === 0) {
                container.innerHTML = '<p>No scraper sources available.</p>';
                return;
            }
            
            let html = '<div style="max-height: 300px; overflow-y: auto;">';
            scraperSources.forEach(source => {
                html += `
                    <label class="checkbox-label" style="display: block; margin-bottom: 0.75rem; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                        <input type="checkbox" value="${source.id}" checked>
                        <span>
                            <strong>${escapeHtml(source.name)}</strong>
                            <small style="display: block; color: #7f8c8d;">
                                ${source.url} • ${source.type}
                            </small>
                        </span>
                    </label>
                `;
            });
            html += '</div>';
            
            container.innerHTML = html;
        }
        
        async function startScraping() {
            const selectedSources = [];
            document.querySelectorAll('#scraperSources input[type="checkbox"]:checked').forEach(cb => {
                selectedSources.push(parseInt(cb.value));
            });
            
            if (selectedSources.length === 0) {
                showToast('Please select at least one source', 'error');
                return;
            }
            
            document.getElementById('scraperContent').style.display = 'none';
            document.getElementById('scraperProgress').style.display = 'block';
            
            const progressContent = document.getElementById('progressContent');
            progressContent.innerHTML = '<div class="loading">Starting scraper...</div>';
            
            try {
                const response = await fetch(`${basePath}/admin/scrapers/run`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ source_ids: selectedSources })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    progressContent.innerHTML = `
                        <div style="text-align: center; padding: 2rem;">
                            <p style="font-size: 1.2rem; color: #27ae60; margin-bottom: 1rem;">✓ Scraping completed successfully!</p>
                            <p>Processed ${selectedSources.length} sources</p>
                            <p style="margin-top: 1rem;">
                                <button class="btn btn-primary" onclick="closeScraperModal(); loadEvents();">View Results</button>
                            </p>
                        </div>
                    `;
                } else {
                    progressContent.innerHTML = `
                        <div style="text-align: center; padding: 2rem;">
                            <p style="font-size: 1.2rem; color: #e74c3c; margin-bottom: 1rem;">✗ Scraping failed</p>
                            <p>${result.message || 'An error occurred during scraping'}</p>
                            <p style="margin-top: 1rem;">
                                <button class="btn btn-secondary" onclick="closeScraperModal()">Close</button>
                            </p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error during scraping:', error);
                progressContent.innerHTML = `
                    <div style="text-align: center; padding: 2rem;">
                        <p style="font-size: 1.2rem; color: #e74c3c; margin-bottom: 1rem;">✗ Scraping error</p>
                        <p>An unexpected error occurred</p>
                        <p style="margin-top: 1rem;">
                            <button class="btn btn-secondary" onclick="closeScraperModal()">Close</button>
                        </p>
                    </div>
                `;
            }
        }
        
        function closeScraperModal() {
            document.getElementById('scraperModal').classList.remove('show');
            document.getElementById('scraperContent').style.display = 'block';
            document.getElementById('scraperProgress').style.display = 'none';
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
    </script>
</body>
</html>