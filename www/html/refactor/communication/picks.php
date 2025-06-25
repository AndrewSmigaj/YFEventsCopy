<?php
// Configure session to use same directory as main app
$sessionDir = dirname(__DIR__) . '/sessions';
if (is_writable($sessionDir)) {
    ini_set('session.save_path', $sessionDir);
}

// Load API keys configuration
$apiKeysFile = dirname(__DIR__) . '/config/api_keys.php';
if (file_exists($apiKeysFile)) {
    require_once $apiKeysFile;
}

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /refactor/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Include autoloader
require_once __DIR__ . '/../vendor/autoload.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Picks - Estate & Yard Sales | YFEvents</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        .picks-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .view-toggle {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .view-toggle .btn {
            flex: 1;
        }
        
        #map-view {
            height: 600px;
            display: none;
        }
        
        .pick-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s;
        }
        
        .pick-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .pick-location {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .pick-date {
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .location-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .map-marker-info {
            max-width: 250px;
        }
        
        .map-marker-info h6 {
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .map-marker-info .date {
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="picks-header">
        <div class="container">
            <h1><i class="fas fa-map-marked-alt"></i> Picks - Estate & Yard Sales</h1>
            <p>Share and discover local estate sales, yard sales, and special events</p>
        </div>
    </div>
    
    <div class="container">
        <div class="view-toggle">
            <button class="btn btn-primary active" onclick="showForumView()">
                <i class="fas fa-list"></i> Forum View
            </button>
            <button class="btn btn-outline-primary" onclick="showMapView()">
                <i class="fas fa-map"></i> Map View
            </button>
        </div>
        
        <!-- Forum View -->
        <div id="forum-view">
            <!-- New Pick Form -->
            <div class="location-form">
                <h4>Share a New Pick</h4>
                <form id="new-pick-form">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Location Name</label>
                                <input type="text" class="form-control" id="location-name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Event Date</label>
                                <input type="date" class="form-control" id="event-date" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-control" id="location-address" placeholder="Start typing address..." required>
                        <small class="text-muted">Address will be geocoded automatically</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="event-start-time">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" id="event-end-time">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="pick-content" rows="3" required></textarea>
                    </div>
                    
                    <input type="hidden" id="location-lat">
                    <input type="hidden" id="location-lng">
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-map-pin"></i> Share Pick
                    </button>
                </form>
            </div>
            
            <!-- Picks List -->
            <div id="picks-list">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map View -->
        <div id="map-view"></div>
    </div>
    
    <!-- Google Maps API (using existing key from events) -->
    <script>
        let map;
        let markers = [];
        let picks = [];
        let autocomplete;
        const basePath = '/refactor';
        const picksChannelId = null; // Will be set after loading channels
        
        // Initialize Google Maps
        function initMap() {
            map = new google.maps.Map(document.getElementById('map-view'), {
                center: { lat: 46.6021, lng: -120.5059 }, // Yakima, WA
                zoom: 11
            });
            
            // Initialize autocomplete for address field
            const addressInput = document.getElementById('location-address');
            autocomplete = new google.maps.places.Autocomplete(addressInput);
            
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (place.geometry) {
                    document.getElementById('location-lat').value = place.geometry.location.lat();
                    document.getElementById('location-lng').value = place.geometry.location.lng();
                }
            });
        }
        
        // Toggle between views
        function showForumView() {
            document.getElementById('forum-view').style.display = 'block';
            document.getElementById('map-view').style.display = 'none';
            document.querySelector('.view-toggle .btn.active').classList.remove('active');
            document.querySelector('.view-toggle .btn:first-child').classList.add('active');
        }
        
        function showMapView() {
            document.getElementById('forum-view').style.display = 'none';
            document.getElementById('map-view').style.display = 'block';
            document.querySelector('.view-toggle .btn.active').classList.remove('active');
            document.querySelector('.view-toggle .btn:last-child').classList.add('active');
            
            // Refresh map
            google.maps.event.trigger(map, 'resize');
            updateMapMarkers();
        }
        
        // Load picks from API
        async function loadPicks() {
            try {
                // First get the Picks channel ID
                const channelsResponse = await fetch(`${basePath}/api/communication/channels`, {
                    credentials: 'same-origin'
                });
                const channelsData = await channelsResponse.json();
                
                if (channelsData.success) {
                    const picksChannel = channelsData.data.find(ch => ch.slug === 'picks');
                    if (picksChannel) {
                        window.picksChannelId = picksChannel.id;
                        
                        // Now load messages from the Picks channel
                        const response = await fetch(`${basePath}/api/communication/channels/${picksChannel.id}/messages`, {
                            credentials: 'same-origin'
                        });
                        const data = await response.json();
                        
                        if (data.success) {
                            console.log('Messages loaded:', data.data.length);
                            console.log('Messages with location:', data.data.filter(msg => msg.location_latitude).length);
                            picks = data.data.filter(msg => msg.location_latitude && msg.location_longitude);
                            renderPicks();
                            updateMapMarkers();
                        } else {
                            console.error('Failed to load messages:', data.error);
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading picks:', error);
            }
        }
        
        // Render picks in forum view
        function renderPicks() {
            const container = document.getElementById('picks-list');
            
            if (picks.length === 0) {
                container.innerHTML = '<p class="text-center text-muted">No picks shared yet. Be the first!</p>';
                return;
            }
            
            container.innerHTML = picks.map(pick => `
                <div class="pick-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h5>${escapeHtml(pick.location_name || 'Untitled Pick')}</h5>
                            <p class="pick-location">
                                <i class="fas fa-map-marker-alt"></i> ${escapeHtml(pick.location_address || 'Address not provided')}
                            </p>
                            <p>${escapeHtml(pick.content)}</p>
                            ${pick.event_start_time ? `
                                <p class="text-muted">
                                    <i class="fas fa-clock"></i> ${pick.event_start_time} 
                                    ${pick.event_end_time ? `- ${pick.event_end_time}` : ''}
                                </p>
                            ` : ''}
                        </div>
                        <div>
                            <span class="pick-date">
                                <i class="fas fa-calendar"></i> ${formatDate(pick.event_date)}
                            </span>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="showOnMap(${pick.location_latitude}, ${pick.location_longitude})">
                            <i class="fas fa-map"></i> Show on Map
                        </button>
                    </div>
                </div>
            `).join('');
        }
        
        // Update map markers
        function updateMapMarkers() {
            // Clear existing markers
            markers.forEach(marker => marker.setMap(null));
            markers = [];
            
            // Add new markers
            picks.forEach(pick => {
                const marker = new google.maps.Marker({
                    position: { lat: pick.location_latitude, lng: pick.location_longitude },
                    map: map,
                    title: pick.location_name || 'Pick'
                });
                
                const infoWindow = new google.maps.InfoWindow({
                    content: `
                        <div class="map-marker-info">
                            <h6>${escapeHtml(pick.location_name || 'Untitled Pick')}</h6>
                            <p class="date"><i class="fas fa-calendar"></i> ${formatDate(pick.event_date)}</p>
                            <p>${escapeHtml(pick.location_address || '')}</p>
                            <p>${escapeHtml(pick.content)}</p>
                        </div>
                    `
                });
                
                marker.addListener('click', () => {
                    infoWindow.open(map, marker);
                });
                
                markers.push(marker);
            });
            
            // Fit bounds to show all markers
            if (markers.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                markers.forEach(marker => bounds.extend(marker.getPosition()));
                map.fitBounds(bounds);
            }
        }
        
        // Show specific location on map
        function showOnMap(lat, lng) {
            showMapView();
            map.setCenter({ lat: lat, lng: lng });
            map.setZoom(15);
        }
        
        // Submit new pick
        document.getElementById('new-pick-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const lat = parseFloat(document.getElementById('location-lat').value);
            const lng = parseFloat(document.getElementById('location-lng').value);
            
            if (!lat || !lng) {
                alert('Please select a valid address from the dropdown');
                return;
            }
            
            const data = {
                content: document.getElementById('pick-content').value,
                location_name: document.getElementById('location-name').value,
                location_address: document.getElementById('location-address').value,
                location_latitude: lat,
                location_longitude: lng,
                event_date: document.getElementById('event-date').value,
                event_start_time: document.getElementById('event-start-time').value || null,
                event_end_time: document.getElementById('event-end-time').value || null
            };
            
            try {
                // Debug: Show what we're sending
                console.log('Sending pick data:', data);
                console.log('Channel ID:', window.picksChannelId);
                console.log('URL:', `${basePath}/api/communication/channels/${window.picksChannelId}/messages`);
                
                const response = await fetch(`${basePath}/api/communication/channels/${window.picksChannelId}/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data),
                    credentials: 'same-origin' // Ensure cookies are sent
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                let result;
                const responseText = await response.text();
                console.log('Response text:', responseText);
                
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Failed to parse response:', parseError);
                    console.error('Response was:', responseText);
                    alert('Server error: Invalid response format');
                    return;
                }
                
                if (result.success) {
                    // Reset form
                    document.getElementById('new-pick-form').reset();
                    document.getElementById('location-lat').value = '';
                    document.getElementById('location-lng').value = '';
                    
                    // Reload picks
                    loadPicks();
                } else {
                    console.error('API Error:', result);
                    alert(result.error || 'Failed to share pick');
                }
            } catch (error) {
                console.error('Error submitting pick:', error);
                alert('Failed to share pick: ' + error.message);
            }
        });
        
        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return 'Date TBA';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        // Initialize on load
        window.addEventListener('load', () => {
            loadPicks();
        });
    </script>
    
    <!-- Load Google Maps API -->
    <script async defer 
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : ''; ?>&libraries=places&callback=initMap">
    </script>
</body>
</html>