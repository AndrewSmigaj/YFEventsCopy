<?php
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';

$pageTitle = 'Intelligent Event Scraper';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar { background: #2c3e50; }
        .navbar-brand, .navbar-nav .nav-link { color: #ecf0f1 !important; }
        .content-wrapper { padding: 2rem 0; }
        
        .scraper-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .url-input-group {
            margin-bottom: 2rem;
        }
        
        .analysis-progress {
            display: none;
            margin: 2rem 0;
        }
        
        .progress-step {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            background: #f8f9fa;
            border-left: 4px solid #dee2e6;
        }
        
        .progress-step.active {
            background: #e7f3ff;
            border-left-color: #0066cc;
        }
        
        .progress-step.completed {
            background: #d4edda;
            border-left-color: #28a745;
        }
        
        .progress-step.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .event-preview {
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #fff;
        }
        
        .event-preview:hover {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .event-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .event-meta {
            color: #666;
            font-size: 0.9rem;
        }
        
        .event-meta i {
            width: 20px;
            color: #999;
        }
        
        .results-section {
            display: none;
            margin-top: 2rem;
        }
        
        .method-details {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
        }
        
        .method-json {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 1rem;
            border-radius: 0.25rem;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            white-space: pre;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #0066cc;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .stats-card {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .stats-card h3 {
            color: #0066cc;
            margin-bottom: 0.5rem;
        }
        
        .recent-sessions {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .session-item {
            padding: 0.75rem;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .session-item:hover {
            background: #f8f9fa;
        }
        
        .session-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .session-status.analyzing { background: #cfe2ff; color: #084298; }
        .session-status.events_found { background: #d1e7dd; color: #0f5132; }
        .session-status.no_events { background: #fff3cd; color: #664d03; }
        .session-status.error { background: #f8d7da; color: #842029; }
        .session-status.approved { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="./">YFEvents Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="./">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="calendar/">Calendar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="scrapers.php">Scrapers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="intelligent-scraper.php">AI Scraper</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="content-wrapper">
        <div class="scraper-container">
            <h1 class="mb-4">
                <i class="fas fa-robot"></i> Intelligent Event Scraper
            </h1>
            
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3 id="totalMethods">0</h3>
                        <p class="mb-0">Active Methods</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3 id="totalSessions">0</h3>
                        <p class="mb-0">Total Sessions</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3 id="successRate">0%</h3>
                        <p class="mb-0">Success Rate</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3 id="eventsFound">0</h3>
                        <p class="mb-0">Events Found</p>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Analyze Websites</h5>
                </div>
                <div class="card-body">
                    <!-- Single URL Analysis -->
                    <div class="url-input-group mb-4">
                        <label for="urlInput" class="form-label">Single URL Analysis:</label>
                        <div class="input-group">
                            <input type="url" 
                                   class="form-control" 
                                   id="urlInput" 
                                   placeholder="https://example.com/events"
                                   value="">
                            <button class="btn btn-primary" type="button" id="analyzeBtn">
                                <i class="fas fa-search"></i> Analyze
                            </button>
                        </div>
                        <small class="text-muted">
                            The AI will analyze the webpage to find events or event links automatically.
                        </small>
                    </div>
                    
                    <!-- CSV Batch Upload -->
                    <div class="csv-upload-group">
                        <label for="csvUpload" class="form-label">Batch CSV Upload (Title,URL format):</label>
                        <div class="input-group">
                            <input type="file" class="form-control" id="csvUpload" accept=".csv" onchange="handleCSVUpload()">
                            <button class="btn btn-success" type="button" id="uploadBtn" disabled>
                                <i class="fas fa-upload"></i> Process CSV
                            </button>
                        </div>
                        <small class="form-text text-muted">CSV format: Title,URL (one per line). Example: "Summer Festival,https://example.com/summer-events"</small>
                    </div>

                    <div class="analysis-progress" id="analysisProgress">
                        <h5 class="mb-3">Analysis Progress</h5>
                        
                        <div class="progress-step" id="step-fetch">
                            <i class="fas fa-download"></i> Fetching webpage content...
                        </div>
                        
                        <div class="progress-step" id="step-analyze">
                            <i class="fas fa-brain"></i> Analyzing with AI to find event patterns...
                        </div>
                        
                        <div class="progress-step" id="step-extract">
                            <i class="fas fa-filter"></i> Extracting event information...
                        </div>
                        
                        <div class="progress-step" id="step-method">
                            <i class="fas fa-cogs"></i> Generating reusable extraction method...
                        </div>
                    </div>

                    <div class="results-section" id="resultsSection">
                        <h5 class="mb-3">Results</h5>
                        
                        <div class="alert alert-info" id="resultsSummary">
                            <!-- Summary will be inserted here -->
                        </div>
                        
                        <div id="eventsContainer">
                            <!-- Events will be inserted here -->
                        </div>
                        
                        <div class="method-details" id="methodDetails" style="display: none;">
                            <h6>Generated Extraction Method</h6>
                            <div class="method-json" id="methodJson">
                                <!-- Method JSON will be inserted here -->
                            </div>
                        </div>
                        
                        <div class="mt-3" id="actionButtons" style="display: none;">
                            <button class="btn btn-success" id="approveBtn">
                                <i class="fas fa-check"></i> Approve & Save Method
                            </button>
                            <button class="btn btn-secondary" id="rejectBtn">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Batch Processing</h5>
                </div>
                <div class="card-body">
                    <div id="batchStatus" style="display: none;">
                        <div class="alert alert-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <span id="batchStatusText">Processing...</span>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewBatchLogs()">View Logs</button>
                            </div>
                            <div class="progress mt-2">
                                <div class="progress-bar" id="batchProgress" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    <div id="batchResults" style="display: none;">
                        <!-- Batch results will be shown here -->
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Sessions</h5>
                </div>
                <div class="card-body">
                    <div class="recent-sessions" id="recentSessions">
                        <!-- Recent sessions will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentSessionId = null;

        document.getElementById('analyzeBtn').addEventListener('click', analyzeUrl);
        document.getElementById('urlInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') analyzeUrl();
        });
        document.getElementById('uploadBtn').addEventListener('click', processBatchCSV);
        
        // CSV Upload handling
        let selectedCSVFile = null;
        
        function handleCSVUpload() {
            const fileInput = document.getElementById('csvUpload');
            const uploadBtn = document.getElementById('uploadBtn');
            
            if (fileInput.files.length > 0) {
                selectedCSVFile = fileInput.files[0];
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Process ' + selectedCSVFile.name;
            } else {
                selectedCSVFile = null;
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Process CSV';
            }
        }
        
        async function processBatchCSV() {
            if (!selectedCSVFile) {
                alert('Please select a CSV file first');
                return;
            }
            
            const formData = new FormData();
            formData.append('csv_file', selectedCSVFile);
            formData.append('action', 'batch_upload');
            
            try {
                document.getElementById('uploadBtn').disabled = true;
                document.getElementById('uploadBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                
                console.log('Uploading file:', selectedCSVFile.name, 'Size:', selectedCSVFile.size);
                
                const response = await fetch('api/intelligent-scrape.php', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', Object.fromEntries(response.headers.entries()));
                
                if (!response.ok) {
                    const text = await response.text();
                    console.error('HTTP Error:', response.status, text);
                    throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
                }
                
                const data = await response.json();
                console.log('Upload response:', data);
                
                if (data.success) {
                    document.getElementById('batchStatus').style.display = 'block';
                    document.getElementById('batchStatusText').textContent = `Processing ${data.total_urls} URLs...`;
                    
                    // Start polling for progress
                    pollBatchProgress(data.batch_id);
                } else {
                    alert('Error: ' + data.error);
                    document.getElementById('uploadBtn').disabled = false;
                    document.getElementById('uploadBtn').innerHTML = '<i class="fas fa-upload"></i> Process CSV';
                }
            } catch (error) {
                console.error('Upload error:', error);
                console.error('Error stack:', error.stack);
                
                let errorMsg = error.message;
                if (error.message.includes('Failed to fetch')) {
                    errorMsg += '\n\nThis could be due to:\n- Server error or timeout\n- Network connectivity issues\n- File too large\n- Server configuration problems';
                }
                
                alert('Upload failed: ' + errorMsg);
                document.getElementById('uploadBtn').disabled = false;
                document.getElementById('uploadBtn').innerHTML = '<i class="fas fa-upload"></i> Process CSV';
            }
        }
        
        async function pollBatchProgress(batchId) {
            try {
                const response = await fetch(`/admin/api/intelligent-scrape.php?action=batch_status&batch_id=${batchId}`);
                const data = await response.json();
                
                if (data.success) {
                    const progress = (data.processed / data.total) * 100;
                    document.getElementById('batchProgress').style.width = progress + '%';
                    document.getElementById('batchStatusText').textContent = 
                        `Processing: ${data.processed}/${data.total} (${data.success_count} successful, ${data.error_count} errors)`;
                    
                    if (data.status === 'completed') {
                        document.getElementById('batchStatus').style.display = 'none';
                        showBatchResults(data);
                        // Re-enable upload button
                        document.getElementById('uploadBtn').disabled = false;
                        document.getElementById('uploadBtn').innerHTML = '<i class="fas fa-upload"></i> Process CSV';
                    } else {
                        // Continue polling
                        setTimeout(() => pollBatchProgress(batchId), 2000);
                    }
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }
        
        function showBatchResults(data) {
            const resultsDiv = document.getElementById('batchResults');
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = `
                <div class="alert alert-success">
                    <h6><i class="fas fa-check-circle"></i> Batch Processing Complete</h6>
                    <div class="row">
                        <div class="col-md-3"><strong>Total URLs:</strong> ${data.total}</div>
                        <div class="col-md-3"><strong>Successful:</strong> ${data.success_count}</div>
                        <div class="col-md-3"><strong>Errors:</strong> ${data.error_count}</div>
                        <div class="col-md-3"><strong>Events Found:</strong> ${data.total_events || 0}</div>
                    </div>
                    <div class="mt-2">
                        <button class="btn btn-primary btn-sm" onclick="viewBatchLogs()">View Detailed Logs</button>
                        <button class="btn btn-secondary btn-sm" onclick="downloadResults(${data.batch_id})">Download Results</button>
                    </div>
                </div>
            `;
        }
        
        function viewBatchLogs() {
            window.open('/admin/api/intelligent-scrape.php?action=view_logs', '_blank');
        }
        
        function downloadResults(batchId) {
            window.open(`/admin/api/intelligent-scrape.php?action=download_results&batch_id=${batchId}`, '_blank');
        }

        async function analyzeUrl() {
            const url = document.getElementById('urlInput').value.trim();
            if (!url) {
                alert('Please enter a URL');
                return;
            }

            // Reset UI
            document.getElementById('analysisProgress').style.display = 'block';
            document.getElementById('resultsSection').style.display = 'none';
            resetProgressSteps();

            // Start analysis
            setStepActive('step-fetch');

            try {
                const response = await fetch('api/intelligent-scrape.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        action: 'analyze',
                        url: url 
                    })
                });

                // Check if response is ok
                if (!response.ok) {
                    const text = await response.text();
                    console.error('Response not OK:', response.status, text);
                    throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned non-JSON response');
                }
                
                const data = await response.json();
                console.log('Response data:', data);

                if (data.success) {
                    // Update progress based on what step we're at
                    setStepActive('step-analyze');
                    setTimeout(() => setStepActive('step-extract'), 500);
                    setTimeout(() => setStepActive('step-method'), 1000);
                    
                    currentSessionId = data.session_id;
                    setTimeout(() => showResults(data), 1500);
                } else {
                    // Show which step failed
                    if (data.error && data.error.includes('fetch')) {
                        setStepError('step-fetch', data.error);
                    } else if (data.error && data.error.includes('analyze')) {
                        setStepError('step-analyze', data.error);
                    } else {
                        setStepError('step-extract', data.error);
                    }
                    showError(data.error || 'Analysis failed');
                }
            } catch (error) {
                console.error('Full error:', error);
                showError('Network error: ' + error.message);
            }
        }

        function resetProgressSteps() {
            document.querySelectorAll('.progress-step').forEach(step => {
                step.classList.remove('active', 'completed', 'error');
            });
        }

        function setStepActive(stepId) {
            const step = document.getElementById(stepId);
            step.classList.add('active');
            
            // Mark previous steps as completed
            const steps = ['step-fetch', 'step-analyze', 'step-extract', 'step-method'];
            const currentIndex = steps.indexOf(stepId);
            
            for (let i = 0; i < currentIndex; i++) {
                document.getElementById(steps[i]).classList.remove('active');
                document.getElementById(steps[i]).classList.add('completed');
            }
        }

        function setStepError(stepId, message) {
            const step = document.getElementById(stepId);
            step.classList.remove('active');
            step.classList.add('error');
            if (message) {
                step.innerHTML += ' <span class="text-danger">' + message + '</span>';
            }
        }

        function showResults(data) {
            // Complete all progress steps
            document.querySelectorAll('.progress-step').forEach(step => {
                step.classList.remove('active');
                step.classList.add('completed');
            });

            // Show results section
            document.getElementById('resultsSection').style.display = 'block';

            // Summary
            const summary = document.getElementById('resultsSummary');
            if (data.events && data.events.length > 0) {
                summary.className = 'alert alert-success';
                summary.innerHTML = `
                    <i class="fas fa-check-circle"></i> 
                    Found ${data.events.length} event(s) on this page
                    ${data.used_existing ? ' (using existing method)' : ''}
                `;
            } else {
                summary.className = 'alert alert-warning';
                summary.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i> 
                    No events found on this page
                `;
            }

            // Events
            const container = document.getElementById('eventsContainer');
            container.innerHTML = '';

            if (data.events && data.events.length > 0) {
                data.events.forEach((event, index) => {
                    container.innerHTML += createEventPreview(event, index);
                });

                // Show action buttons
                document.getElementById('actionButtons').style.display = 'block';
            }

            // Method details
            if (data.method && !data.used_existing) {
                document.getElementById('methodDetails').style.display = 'block';
                document.getElementById('methodJson').textContent = JSON.stringify(data.method, null, 2);
            }

            // Load stats
            loadStats();
        }

        function createEventPreview(event, index) {
            const date = event.start_datetime ? new Date(event.start_datetime).toLocaleString() : 'No date';
            
            return `
                <div class="event-preview">
                    <div class="event-title">${escapeHtml(event.title || 'Untitled Event')}</div>
                    <div class="event-meta">
                        <div><i class="fas fa-calendar"></i> ${date}</div>
                        ${event.location ? `<div><i class="fas fa-map-marker-alt"></i> ${escapeHtml(event.location)}</div>` : ''}
                        ${event.description ? `<div><i class="fas fa-info-circle"></i> ${escapeHtml(event.description.substring(0, 200))}...</div>` : ''}
                        ${event.external_url ? `<div><i class="fas fa-link"></i> <a href="${event.external_url}" target="_blank">View Event</a></div>` : ''}
                    </div>
                </div>
            `;
        }

        function showError(message) {
            setStepError('step-analyze', message);
            
            document.getElementById('resultsSection').style.display = 'block';
            document.getElementById('resultsSummary').className = 'alert alert-danger';
            document.getElementById('resultsSummary').innerHTML = `
                <i class="fas fa-times-circle"></i> Error: ${escapeHtml(message)}
            `;
            document.getElementById('eventsContainer').innerHTML = '';
            document.getElementById('actionButtons').style.display = 'none';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Approve button
        document.getElementById('approveBtn').addEventListener('click', async () => {
            if (!currentSessionId) return;

            try {
                const response = await fetch('api/intelligent-scrape.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        action: 'approve',
                        session_id: currentSessionId 
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Method approved and saved! A new scraper source has been created.');
                    window.location.href = '/admin/scrapers.php';
                } else {
                    alert('Error: ' + (data.error || 'Failed to approve method'));
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        });

        // Load stats and recent sessions
        async function loadStats() {
            try {
                const response = await fetch('api/intelligent-scrape.php?action=stats');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('totalMethods').textContent = data.stats.total_methods;
                    document.getElementById('totalSessions').textContent = data.stats.total_sessions;
                    document.getElementById('successRate').textContent = data.stats.success_rate + '%';
                    document.getElementById('eventsFound').textContent = data.stats.total_events;

                    // Load recent sessions
                    const sessionsContainer = document.getElementById('recentSessions');
                    sessionsContainer.innerHTML = '';

                    data.recent_sessions.forEach(session => {
                        sessionsContainer.innerHTML += createSessionItem(session);
                    });
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }

        function createSessionItem(session) {
            const date = new Date(session.created_at).toLocaleString();
            const domain = new URL(session.url).hostname;
            
            return `
                <div class="session-item" onclick="loadSession('${session.url}')">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${domain}</strong>
                            <small class="text-muted d-block">${date}</small>
                        </div>
                        <span class="session-status ${session.status}">${session.status.replace('_', ' ')}</span>
                    </div>
                </div>
            `;
        }

        function loadSession(url) {
            document.getElementById('urlInput').value = url;
            analyzeUrl();
        }

        // Load stats on page load
        loadStats();
    </script>
</body>
</html>