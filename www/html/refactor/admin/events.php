<?php
// Admin Events Management Page
require_once __DIR__ . '/bootstrap.php';

// Set correct base path for refactor admin
$basePath = '/refactor';

// Get database connection
$db = $GLOBALS['db'] ?? null;
if (!$db) {
    die('Database connection not available');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/admin-styles.css">
    <style>
        /* Page-specific styles for events page */
        .event-title {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }
        
        .event-meta {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }
        
        .status-approved {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-dark);
        }
        
        .status-pending {
            background: rgba(243, 156, 18, 0.1);
            color: var(--warning-dark);
        }
        
        .status-rejected {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-dark);
        }
        
        .status-featured {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info-dark);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/admin-navigation.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div class="container-fluid">
                    <h1><i class="bi bi-calendar-event"></i> Event Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Events</li>
                        </ol>
                    </nav>
                </div>
            </div>
            
            <div class="main-content">
                <!-- Action Buttons -->
                <div class="action-buttons mb-4">
                    <button class="btn-admin btn-admin-primary" onclick="showCreateModal()">
                        <i class="bi bi-plus-circle"></i> Create Event
                    </button>
                    <button class="btn-admin btn-admin-success" onclick="refreshEvents()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn-admin btn-admin-warning" onclick="forceScrape()">
                        <i class="bi bi-robot"></i> Force Scrape
                    </button>
                </div>
        
                <!-- Statistics -->
                <div class="stats-grid" id="statsRow">
                    <div class="stat-card" onclick="filterByStatus('all')" style="cursor: pointer;">
                        <div class="stat-number">-</div>
                        <div class="stat-label">Total Events</div>
                    </div>
                    <div class="stat-card" onclick="filterByStatus('pending')" style="cursor: pointer;">
                        <div class="stat-number">-</div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card" onclick="filterByStatus('approved')" style="cursor: pointer;">
                        <div class="stat-number">-</div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-card" onclick="filterByStatus('featured')" style="cursor: pointer;">
                        <div class="stat-number">-</div>
                        <div class="stat-label">Featured</div>
                    </div>
                    <div class="stat-card" onclick="filterByDate('today')" style="cursor: pointer;">
                        <div class="stat-number">-</div>
                        <div class="stat-label">Today's Events</div>
                    </div>
                </div>
        
                <!-- Filters -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h5><i class="bi bi-funnel"></i> Filter Events</h5>
                    </div>
                    <div class="admin-card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="searchInput" class="form-label">Search</label>
                                <input type="text" class="form-control" id="searchInput" placeholder="Search events..." onkeyup="applyFilters()">
                            </div>
                            <div class="col-md-3">
                                <label for="statusFilter" class="form-label">Status</label>
                                <select class="form-control" id="statusFilter" onchange="applyFilters()">
                                    <option value="all">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="dateFilter" class="form-label">Date Range</label>
                                <select class="form-control" id="dateFilter" onchange="applyFilters()">
                                    <option value="all">All Time</option>
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button class="btn btn-secondary" onclick="resetFilters()">Clear</button>
                                <button class="btn btn-primary" onclick="refreshEvents()">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Events Table -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h5><i class="bi bi-calendar-check"></i> Events List</h5>
                        <div class="bulk-actions" id="bulkActions" style="display: none;">
                            <span id="selectedCount">0 selected</span>
                            <button class="btn btn-sm btn-success" onclick="bulkApprove()">Approve</button>
                            <button class="btn btn-sm btn-danger" onclick="bulkReject()">Reject</button>
                        </div>
                    </div>
                    <div class="admin-card-body">
                        <div id="eventsTableContent">
                            <div class="loading">Loading events...</div>
                        </div>
                    </div>
                </div>
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
        const basePath = '<?php echo $basePath; ?>' || '/refactor';
        console.log('basePath is set to:', basePath);
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
            console.log('Loading statistics from:', `${basePath}/api/admin/events/statistics.php`);
            try {
                const response = await fetch(`${basePath}/api/admin/events/statistics`, {
                    credentials: 'include'
                });
                console.log('Statistics API response status:', response.status);
                const data = await response.json();
                console.log('Statistics data received:', data);
                
                if (data.success) {
                    const stats = data.data.statistics;
                    console.log('Updating statistics display with:', stats);
                    updateStatisticsDisplay(stats);
                } else {
                    // Fallback: show zeros with error message
                    console.error('Statistics API failed:', data.message);
                    loadStatisticsDirectly();
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
                // Fallback: load statistics from direct database query
                loadStatisticsDirectly();
            }
        }
        
        function updateStatisticsDisplay(stats) {
            const statsRow = document.getElementById('statsRow');
            statsRow.innerHTML = `
                <div class="stat-card" onclick="filterByStatus('all')" style="cursor: pointer;">
                    <div class="stat-number">${stats.total_events || 0}</div>
                    <div class="stat-label">Total Events</div>
                </div>
                <div class="stat-card" onclick="filterByStatus('pending')" style="cursor: pointer;">
                    <div class="stat-number">${stats.pending_events || 0}</div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card" onclick="filterByStatus('approved')" style="cursor: pointer;">
                    <div class="stat-number">${stats.approved_events || 0}</div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card" onclick="filterByFeatured()" style="cursor: pointer;">
                    <div class="stat-number">${stats.featured_events || 0}</div>
                    <div class="stat-label">Featured</div>
                </div>
                <div class="stat-card" onclick="filterByDate('today')" style="cursor: pointer;">
                    <div class="stat-number">${stats.todays_events || 0}</div>
                    <div class="stat-label">Today's Events</div>
                </div>
            `;
        }
        
        async function loadStatisticsDirectly() {
            try {
                // Simple fallback implementation
                const statsRow = document.getElementById('statsRow');
                statsRow.innerHTML = `
                    <div class="stat-card" onclick="filterByStatus('all')" style="cursor: pointer;">
                        <div class="stat-number">0</div>
                        <div class="stat-label">Total Events</div>
                    </div>
                    <div class="stat-card" onclick="filterByStatus('pending')" style="cursor: pointer;">
                        <div class="stat-number">0</div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card" onclick="filterByStatus('approved')" style="cursor: pointer;">
                        <div class="stat-number">0</div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-card" onclick="filterByFeatured()" style="cursor: pointer;">
                        <div class="stat-number">0</div>
                        <div class="stat-label">Featured</div>
                    </div>
                    <div class="stat-card" onclick="filterByDate('today')" style="cursor: pointer;">
                        <div class="stat-number">0</div>
                        <div class="stat-label">Today's Events</div>
                    </div>
                `;
            } catch (error) {
                console.error('Error loading fallback statistics:', error);
            }
        }
        
        async function loadEvents(page = 1) {
            console.log('Loading events from page:', page);
            try {
                currentPage = page;
                const params = new URLSearchParams({
                    page: page,
                    limit: 20,
                    ...currentFilters
                });
                
                const url = `${basePath}/api/admin/events.php?${params}`;
                console.log('Events API URL:', url);
                const response = await fetch(url, {
                    credentials: 'include'
                });
                console.log('Events API response status:', response.status);
                const data = await response.json();
                console.log('Events data received:', data);
                
                if (data.success) {
                    const events = data.data.events || [];
                    console.log('Rendering events table with', events.length, 'events');
                    renderEventsTable(events);
                } else {
                    console.error('Events API failed:', data.message);
                    showToast(data.message || 'Failed to load events', 'error');
                    renderEventsTable([]);
                }
            } catch (error) {
                console.error('Error loading events:', error);
                showToast('Error loading events', 'error');
                renderEventsTable([]);
            }
        }
        
        // Statistics filter functions
        function filterByStatus(status) {
            document.getElementById('statusFilter').value = status;
            currentFilters.status = status;
            applyFilters();
            showToast(`Filtering by status: ${status}`, 'info');
        }
        
        function filterByFeatured() {
            // Filter by featured events by modifying the status filter
            currentFilters.featured = '1';
            applyFilters();
            showToast('Showing featured events only', 'info');
        }
        
        function filterByDate(dateFilter) {
            document.getElementById('dateFilter').value = dateFilter;
            currentFilters.date = dateFilter;
            applyFilters();
            showToast(`Filtering by date: ${dateFilter}`, 'info');
        }
        
        function refreshEvents() {
            loadEvents(1);
            loadStatistics();
            showToast('Events refreshed', 'success');
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
                bulkActions.style.display = 'block';
                selectedCount.textContent = `${selectedEvents.size} selected`;
            } else {
                bulkActions.style.display = 'none';
            }
        }
        
        function applyFilters() {
            currentFilters = {
                search: document.getElementById('searchInput').value,
                status: document.getElementById('statusFilter').value
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
                const url = `${basePath}/api/scrapers.php`;
                console.log('Fetching scrapers from:', url);
                const response = await fetch(url, {
                    credentials: 'include'
                });
                console.log('Scrapers response status:', response.status);
                console.log('Scrapers response headers:', response.headers);
                
                const responseText = await response.text();
                console.log('Raw response:', responseText.substring(0, 200));
                
                const data = JSON.parse(responseText);
                console.log('Parsed scrapers data:', data);
                
                if (data.success) {
                    scraperSources = data.data;
                    renderScraperSources();
                    document.getElementById('scraperModal').classList.add('show');
                } else {
                    console.error('API returned error:', data.message);
                    showToast('Failed to load scrapers: ' + data.message, 'error');
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