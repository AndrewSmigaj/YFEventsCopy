<?php
// Load environment configuration
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Get Google Maps API Key from environment
$googleMapsApiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Event Calendar Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        
        .controls {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        button {
            margin-right: 10px;
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        
        button:hover {
            background: #0056b3;
        }
        
        #loading {
            margin-left: 10px;
            color: #666;
        }
        
        .event {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-left: 3px solid #007bff;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .event:hover {
            background: #f0f0f0;
            transform: translateX(5px);
        }
        
        .map-container {
            height: 500px;
            width: 100%;
            margin: 20px 0;
            border: 1px solid #ddd;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #ddd;
            padding: 1px;
        }
        
        .calendar-cell {
            background: white;
            padding: 8px;
            min-height: 80px;
            font-size: 14px;
        }
        
        .calendar-cell.header {
            background: #007bff;
            color: white;
            text-align: center;
            font-weight: bold;
            min-height: auto;
        }
        
        .calendar-cell .event {
            font-size: 11px;
            margin: 2px 0;
            padding: 2px 4px;
            background: #e3f2fd;
            border-left: 2px solid #1976d2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <h1>Simple Event Calendar Test</h1>
    
    <div class="controls">
        <button onclick="loadEvents()">Load Events</button>
        <button onclick="showCalendar()">Show Calendar</button>
        <button onclick="showMap()">Show Map</button>
        <span id="loading"></span>
    </div>
    
    <div id="month-display"></div>
    
    <div id="calendar-container"></div>
    
    <div id="map-container" class="map-container" style="display: none;"></div>
    
    <div id="events-list"></div>
    
    <?php if ($googleMapsApiKey): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($googleMapsApiKey) ?>&libraries=places"></script>
    <?php else: ?>
    <script>
        // No Google Maps API key configured
        console.warn('Google Maps API key not configured. Map functionality will be limited.');
    </script>
    <?php endif; ?>
    
    <script>
        let events = [];
        let map = null;
        let markers = [];
        
        async function loadEvents() {
            const loading = document.getElementById('loading');
            loading.textContent = 'Loading events...';
            
            try {
                const response = await fetch('/api/events-simple.php?start=2025-05-01&end=2025-05-31');
                const data = await response.json();
                
                if (data.success) {
                    events = data.events;
                    loading.textContent = `Loaded ${events.length} events`;
                    
                    // Display events list
                    const listHtml = events.map(event => `
                        <div class="event" onclick="alert('${event.title}')">
                            <strong>${event.title}</strong><br>
                            ${new Date(event.start_datetime).toLocaleDateString()} 
                            at ${new Date(event.start_datetime).toLocaleTimeString()}<br>
                            ${event.location}
                        </div>
                    `).join('');
                    
                    document.getElementById('events-list').innerHTML = '<h3>Events List:</h3>' + listHtml;
                } else {
                    loading.textContent = 'Error loading events';
                }
            } catch (error) {
                loading.textContent = 'Error: ' + error.message;
                console.error(error);
            }
        }
        
        function showCalendar() {
            const container = document.getElementById('calendar-container');
            const monthDisplay = document.getElementById('month-display');
            
            // May 2025
            const year = 2025;
            const month = 4; // May (0-indexed)
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startDay = firstDay.getDay();
            
            monthDisplay.innerHTML = '<h2>May 2025</h2>';
            
            // Create calendar grid
            let html = '<div class="calendar-grid">';
            
            // Header
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            days.forEach(day => {
                html += `<div class="calendar-cell header">${day}</div>`;
            });
            
            // Empty cells before first day
            for (let i = 0; i < startDay; i++) {
                html += '<div class="calendar-cell"></div>';
            }
            
            // Days of month
            for (let day = 1; day <= lastDay.getDate(); day++) {
                const cellDate = new Date(year, month, day);
                const dateStr = cellDate.toISOString().split('T')[0];
                
                // Find events for this day
                const dayEvents = events.filter(event => {
                    const eventDate = new Date(event.start_datetime).toISOString().split('T')[0];
                    return eventDate === dateStr;
                });
                
                html += `<div class="calendar-cell">
                    <div style="font-weight: bold;">${day}</div>`;
                
                dayEvents.forEach(event => {
                    const time = new Date(event.start_datetime).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    html += `<div class="event" title="${event.location}">${time} ${event.title}</div>`;
                });
                
                html += '</div>';
            }
            
            html += '</div>';
            container.innerHTML = html;
            
            document.getElementById('map-container').style.display = 'none';
        }
        
        function showMap() {
            <?php if ($googleMapsApiKey): ?>
            const mapContainer = document.getElementById('map-container');
            mapContainer.style.display = 'block';
            
            // Initialize map if not already done
            if (!map) {
                map = new google.maps.Map(mapContainer, {
                    center: {lat: 46.6021, lng: -120.5059}, // Yakima coordinates
                    zoom: 11
                });
            }
            
            // Clear existing markers
            markers.forEach(marker => marker.setMap(null));
            markers = [];
            
            // Add markers for events with coordinates
            events.forEach(event => {
                if (event.latitude && event.longitude) {
                    const marker = new google.maps.Marker({
                        position: {lat: parseFloat(event.latitude), lng: parseFloat(event.longitude)},
                        map: map,
                        title: event.title
                    });
                    
                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div>
                                <h4>${event.title}</h4>
                                <p>${new Date(event.start_datetime).toLocaleString()}</p>
                                <p>${event.location}</p>
                            </div>
                        `
                    });
                    
                    marker.addListener('click', () => {
                        infoWindow.open(map, marker);
                    });
                    
                    markers.push(marker);
                }
            });
            
            // Fit bounds to show all markers
            if (markers.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                markers.forEach(marker => bounds.extend(marker.getPosition()));
                map.fitBounds(bounds);
            }
            
            document.getElementById('calendar-container').innerHTML = '';
            <?php else: ?>
            alert('Google Maps API key not configured. Please set GOOGLE_MAPS_API_KEY in your .env file.');
            <?php endif; ?>
        }
        
        // Load events on page load
        window.onload = loadEvents;
    </script>
</body>
</html>