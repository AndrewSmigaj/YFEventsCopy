<?php
// URL Validation Tool for Intelligent Scraper
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once '../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urls'])) {
    $urls = array_filter(array_map('trim', explode("\n", $_POST['urls'])));
    $results = [];
    
    foreach ($urls as $url) {
        if (empty($url)) continue;
        
        $result = validateUrl($url);
        $results[] = $result;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['results' => $results]);
    exit;
}

function validateUrl($url) {
    $start = microtime(true);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_NOBODY => true, // HEAD request only
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (compatible; URLValidator/1.0)'
        ]
    ]);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    $time = round((microtime(true) - $start) * 1000);
    
    $status = 'unknown';
    $message = '';
    
    if ($error) {
        $status = 'error';
        $message = $error;
    } elseif ($httpCode >= 200 && $httpCode < 300) {
        $status = 'success';
        $message = "HTTP {$httpCode}";
    } elseif ($httpCode >= 300 && $httpCode < 400) {
        $status = 'redirect';
        $message = "HTTP {$httpCode} - Redirects to: " . ($info['redirect_url'] ?? 'unknown');
    } elseif ($httpCode >= 400) {
        $status = 'error';
        $message = "HTTP {$httpCode}";
    }
    
    return [
        'url' => $url,
        'status' => $status,
        'message' => $message,
        'http_code' => $httpCode,
        'response_time' => $time,
        'content_type' => $info['content_type'] ?? 'unknown'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Validator - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-check-circle"></i> URL Validator</h1>
                    <a href="/admin/" class="btn btn-secondary">← Back to Admin</a>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Validate URLs Before Batch Processing</h5>
                    </div>
                    <div class="card-body">
                        <form id="validateForm">
                            <div class="mb-3">
                                <label for="urls" class="form-label">Enter URLs to validate (one per line):</label>
                                <textarea class="form-control" id="urls" rows="10" placeholder="https://example.com/events
https://another-site.com/calendar
https://third-site.org/upcoming"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" id="validateBtn">
                                <i class="fas fa-check"></i> Validate URLs
                            </button>
                        </form>
                        
                        <div id="results" class="mt-4" style="display: none;">
                            <h6>Validation Results:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>URL</th>
                                            <th>Message</th>
                                            <th>Response Time</th>
                                            <th>Content Type</th>
                                        </tr>
                                    </thead>
                                    <tbody id="resultsBody">
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-success" onclick="exportGoodUrls()">
                                    <i class="fas fa-download"></i> Export Valid URLs as CSV
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let validationResults = [];
        
        document.getElementById('validateForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const urls = document.getElementById('urls').value;
            const btn = document.getElementById('validateBtn');
            
            if (!urls.trim()) {
                alert('Please enter some URLs to validate');
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';
            
            try {
                const response = await fetch('/admin/validate-urls.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'urls=' + encodeURIComponent(urls)
                });
                
                const data = await response.json();
                validationResults = data.results;
                displayResults(data.results);
                
            } catch (error) {
                console.error('Validation error:', error);
                alert('Error validating URLs: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Validate URLs';
            }
        });
        
        function displayResults(results) {
            const tbody = document.getElementById('resultsBody');
            tbody.innerHTML = '';
            
            results.forEach(result => {
                const row = document.createElement('tr');
                
                let statusBadge = '';
                switch (result.status) {
                    case 'success':
                        statusBadge = '<span class="badge bg-success">✓ Success</span>';
                        break;
                    case 'redirect':
                        statusBadge = '<span class="badge bg-warning">↪ Redirect</span>';
                        break;
                    case 'error':
                        statusBadge = '<span class="badge bg-danger">✗ Error</span>';
                        break;
                    default:
                        statusBadge = '<span class="badge bg-secondary">? Unknown</span>';
                }
                
                row.innerHTML = `
                    <td>${statusBadge}</td>
                    <td><small>${result.url}</small></td>
                    <td><small>${result.message}</small></td>
                    <td>${result.response_time}ms</td>
                    <td><small>${result.content_type}</small></td>
                `;
                
                tbody.appendChild(row);
            });
            
            document.getElementById('results').style.display = 'block';
        }
        
        function exportGoodUrls() {
            const goodUrls = validationResults
                .filter(r => r.status === 'success' || r.status === 'redirect')
                .map(r => `"Event Calendar",${r.url}`)
                .join('\n');
            
            const csv = 'Title,URL\n' + goodUrls;
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'validated_urls.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>