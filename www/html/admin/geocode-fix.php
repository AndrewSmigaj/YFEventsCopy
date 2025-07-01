<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

// Load environment configuration
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';

use YFEvents\Utils\GeocodeService;

$geocoder = new GeocodeService();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'verify_all':
            $type = $_POST['type'] ?? 'shops';
            $results = verifyAllGeocoding($db, $type);
            echo json_encode($results);
            exit;
            
        case 'fix_single':
            $type = $_POST['type'] ?? 'shops';
            $id = $_POST['id'] ?? 0;
            $result = fixSingleGeocoding($db, $type, $id, $geocoder);
            echo json_encode($result);
            exit;
            
        case 'fix_all':
            $type = $_POST['type'] ?? 'shops';
            $results = fixAllGeocoding($db, $type, $geocoder);
            echo json_encode($results);
            exit;
    }
}

function verifyAllGeocoding($db, $type = 'shops') {
    $table = $type === 'shops' ? 'local_shops' : 'events';
    $nameField = $type === 'shops' ? 'name' : 'title';
    
    $query = "SELECT id, $nameField as name, address, city, state, zip, latitude, longitude 
              FROM $table 
              WHERE address IS NOT NULL AND address != ''
              ORDER BY id";
              
    $stmt = $db->query($query);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = [
        'total' => count($items),
        'missing' => 0,
        'suspicious' => 0,
        'items' => []
    ];
    
    foreach ($items as $item) {
        $fullAddress = trim($item['address'] . ', ' . $item['city'] . ', ' . $item['state'] . ' ' . $item['zip']);
        $hasCoords = !empty($item['latitude']) && !empty($item['longitude']);
        
        // Check for suspicious coordinates
        $suspicious = false;
        if ($hasCoords) {
            // Check if coordinates are in Yakima area (roughly)
            $lat = floatval($item['latitude']);
            $lng = floatval($item['longitude']);
            
            // Yakima is roughly at 46.6°N, 120.5°W
            // Check if within reasonable bounds (about 50 miles)
            if ($lat < 46.0 || $lat > 47.2 || $lng < -121.5 || $lng > -119.5) {
                $suspicious = true;
            }
            
            // Check for default/zero coordinates
            if (abs($lat) < 0.1 || abs($lng) < 0.1) {
                $suspicious = true;
            }
        }
        
        if (!$hasCoords) {
            $results['missing']++;
        } else if ($suspicious) {
            $results['suspicious']++;
        }
        
        $results['items'][] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'address' => $fullAddress,
            'latitude' => $item['latitude'],
            'longitude' => $item['longitude'],
            'has_coords' => $hasCoords,
            'suspicious' => $suspicious
        ];
    }
    
    return $results;
}

function fixSingleGeocoding($db, $type, $id, $geocoder) {
    $table = $type === 'shops' ? 'local_shops' : 'events';
    $nameField = $type === 'shops' ? 'name' : 'title';
    
    // Get the item
    $query = "SELECT id, $nameField as name, address, city, state, zip, latitude, longitude 
              FROM $table 
              WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        return ['success' => false, 'error' => 'Item not found'];
    }
    
    // Build full address
    $fullAddress = trim($item['address'] . ', ' . $item['city'] . ', ' . $item['state'] . ' ' . $item['zip']);
    
    // Clear cache for this address to force fresh geocoding
    $cacheFile = __DIR__ . '/../../../cache/geocode/' . md5($fullAddress) . '.json';
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
    
    // Geocode the address
    $coords = $geocoder->geocode($fullAddress);
    
    if ($coords) {
        // Update the database
        $updateQuery = "UPDATE $table SET latitude = ?, longitude = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([$coords['lat'], $coords['lng'], $id]);
        
        return [
            'success' => true,
            'id' => $id,
            'name' => $item['name'],
            'address' => $fullAddress,
            'old_coords' => [
                'lat' => $item['latitude'],
                'lng' => $item['longitude']
            ],
            'new_coords' => $coords
        ];
    } else {
        return [
            'success' => false,
            'id' => $id,
            'name' => $item['name'],
            'address' => $fullAddress,
            'error' => 'Geocoding failed'
        ];
    }
}

function fixAllGeocoding($db, $type, $geocoder) {
    // First get all items that need fixing
    $verification = verifyAllGeocoding($db, $type);
    $results = [
        'total' => 0,
        'fixed' => 0,
        'failed' => 0,
        'items' => []
    ];
    
    foreach ($verification['items'] as $item) {
        if (!$item['has_coords'] || $item['suspicious']) {
            $results['total']++;
            $result = fixSingleGeocoding($db, $type, $item['id'], $geocoder);
            
            if ($result['success']) {
                $results['fixed']++;
            } else {
                $results['failed']++;
            }
            
            $results['items'][] = $result;
            
            // Add a small delay to avoid hitting API limits
            usleep(250000); // 250ms delay
        }
    }
    
    return $results;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geocoding Verification & Fix - YFEvents Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .description {
            color: #666;
            margin-bottom: 30px;
        }
        .controls {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            align-items: center;
        }
        .controls select, .controls button {
            padding: 10px 15px;
            font-size: 16px;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .results {
            margin-top: 20px;
        }
        .summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .summary h3 {
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .missing {
            color: #dc3545;
            font-weight: bold;
        }
        .suspicious {
            color: #ffc107;
            font-weight: bold;
        }
        .good {
            color: #28a745;
        }
        .coords {
            font-family: monospace;
            font-size: 12px;
        }
        .action-btn {
            padding: 5px 10px;
            font-size: 12px;
        }
        .progress {
            display: none;
            margin-top: 20px;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #007bff;
            transition: width 0.3s;
            width: 0%;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        #map {
            height: 400px;
            margin-top: 20px;
            display: none;
        }
        .map-controls {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="./" class="back-link">← Back to Admin</a>
        
        <h1>Geocoding Verification & Fix Tool</h1>
        <p class="description">
            This tool helps verify and fix incorrect geocoding for shops and events. 
            It checks for missing coordinates and suspicious locations (outside Yakima area).
        </p>
        
        <div class="controls">
            <select id="type">
                <option value="shops">Shops</option>
                <option value="events">Events</option>
            </select>
            <button onclick="verifyAll()">Verify All</button>
            <button onclick="fixAll()" id="fixAllBtn" style="display:none;">Fix All Issues</button>
            <button onclick="toggleMap()" id="mapToggle" style="display:none;">Show Map</button>
        </div>
        
        <div class="progress" id="progress">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <p id="progressText">Processing...</p>
        </div>
        
        <div id="results"></div>
        
        <div id="map"></div>
    </div>
    
    <script>
        let map = null;
        let markers = [];
        let verificationData = null;
        
        function verifyAll() {
            const type = document.getElementById('type').value;
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<p>Loading...</p>';
            
            fetch('geocode-fix.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=verify_all&type=${type}`
            })
            .then(r => r.json())
            .then(data => {
                verificationData = data;
                displayResults(data);
                document.getElementById('fixAllBtn').style.display = 
                    (data.missing > 0 || data.suspicious > 0) ? 'inline-block' : 'none';
                document.getElementById('mapToggle').style.display = 'inline-block';
            });
        }
        
        function displayResults(data) {
            const resultsDiv = document.getElementById('results');
            
            let html = `
                <div class="summary">
                    <h3>Summary</h3>
                    <p>Total items: ${data.total}</p>
                    <p class="missing">Missing coordinates: ${data.missing}</p>
                    <p class="suspicious">Suspicious coordinates: ${data.suspicious}</p>
                    <p class="good">Good coordinates: ${data.total - data.missing - data.suspicious}</p>
                </div>
            `;
            
            if (data.missing > 0 || data.suspicious > 0) {
                html += `
                    <h3>Items with Issues</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Coordinates</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.items.forEach(item => {
                    if (!item.has_coords || item.suspicious) {
                        const status = !item.has_coords ? 
                            '<span class="missing">Missing</span>' : 
                            '<span class="suspicious">Suspicious</span>';
                        
                        const coords = item.has_coords ? 
                            `<span class="coords">${parseFloat(item.latitude).toFixed(6)}, ${parseFloat(item.longitude).toFixed(6)}</span>` : 
                            'N/A';
                        
                        html += `
                            <tr>
                                <td>${item.id}</td>
                                <td>${item.name}</td>
                                <td>${item.address}</td>
                                <td>${coords}</td>
                                <td>${status}</td>
                                <td>
                                    <button class="action-btn" onclick="fixSingle(${item.id})">Fix</button>
                                </td>
                            </tr>
                        `;
                    }
                });
                
                html += '</tbody></table>';
            }
            
            resultsDiv.innerHTML = html;
        }
        
        function fixSingle(id) {
            const type = document.getElementById('type').value;
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Fixing...';
            
            fetch('geocode-fix.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=fix_single&type=${type}&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    btn.textContent = 'Fixed!';
                    btn.style.background = '#28a745';
                    
                    // Update the display
                    setTimeout(() => verifyAll(), 1000);
                } else {
                    btn.textContent = 'Failed';
                    btn.style.background = '#dc3545';
                    alert('Failed to geocode: ' + (data.error || 'Unknown error'));
                }
            });
        }
        
        function fixAll() {
            if (!confirm('This will re-geocode all items with missing or suspicious coordinates. Continue?')) {
                return;
            }
            
            const type = document.getElementById('type').value;
            const progress = document.getElementById('progress');
            const progressFill = document.getElementById('progressFill');
            const progressText = document.getElementById('progressText');
            
            progress.style.display = 'block';
            document.getElementById('fixAllBtn').disabled = true;
            
            fetch('geocode-fix.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=fix_all&type=${type}`
            })
            .then(r => r.json())
            .then(data => {
                progress.style.display = 'none';
                document.getElementById('fixAllBtn').disabled = false;
                
                let message = `Fixed ${data.fixed} out of ${data.total} items.`;
                if (data.failed > 0) {
                    message += ` ${data.failed} failed.`;
                }
                
                alert(message);
                verifyAll();
            });
        }
        
        function toggleMap() {
            const mapDiv = document.getElementById('map');
            const mapToggle = document.getElementById('mapToggle');
            
            if (mapDiv.style.display === 'none' || !mapDiv.style.display) {
                mapDiv.style.display = 'block';
                mapToggle.textContent = 'Hide Map';
                initMap();
            } else {
                mapDiv.style.display = 'none';
                mapToggle.textContent = 'Show Map';
            }
        }
        
        function initMap() {
            if (!map && typeof google !== 'undefined') {
                map = new google.maps.Map(document.getElementById('map'), {
                    center: {lat: 46.600825, lng: -120.503357}, // Yakima Finds: 111 S. 2nd St
                    zoom: 11
                });
                
                if (verificationData) {
                    displayMarkers();
                }
            }
        }
        
        function displayMarkers() {
            // Clear existing markers
            markers.forEach(m => m.setMap(null));
            markers = [];
            
            verificationData.items.forEach(item => {
                if (item.has_coords) {
                    const lat = parseFloat(item.latitude);
                    const lng = parseFloat(item.longitude);
                    
                    const marker = new google.maps.Marker({
                        position: {lat: lat, lng: lng},
                        map: map,
                        title: item.name,
                        icon: item.suspicious ? 
                            'http://maps.google.com/mapfiles/ms/icons/yellow-dot.png' : 
                            'http://maps.google.com/mapfiles/ms/icons/green-dot.png'
                    });
                    
                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div>
                                <h4>${item.name}</h4>
                                <p>${item.address}</p>
                                <p>Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}</p>
                                ${item.suspicious ? '<p style="color: #ffc107;">⚠️ Suspicious location</p>' : ''}
                            </div>
                        `
                    });
                    
                    marker.addListener('click', () => {
                        infoWindow.open(map, marker);
                    });
                    
                    markers.push(marker);
                }
            });
        }
    </script>
    
    <?php
    // Load Google Maps API key from environment
    $googleMapsApiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
    if ($googleMapsApiKey): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleMapsApiKey) ?>&callback=initMap" async defer></script>
    <?php endif; ?>
</body>
</html>