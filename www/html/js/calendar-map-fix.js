// Map view fixes for YFEvents calendar
// This file contains patches to ensure map markers display correctly

// Override the initializeMap function to add Yakima Finds location
const originalInitializeMap = YakimaCalendar.prototype.initializeMap;
YakimaCalendar.prototype.initializeMap = function() {
    console.log('Initializing map with fixes...');
    
    const mapEl = document.getElementById('map');
    if (!mapEl) {
        console.error('Map element not found');
        return;
    }
    
    // Yakima Finds location at 111 S 2nd St, Yakima, WA
    // Corrected coordinates for exact address
    const yakimaFindsLocation = { lat: 46.600825, lng: -120.503357 }; // 111 S. 2nd St
    
    this.map = new google.maps.Map(mapEl, {
        center: yakimaFindsLocation,
        zoom: 13,
        styles: this.options.mapOptions.styles || []
    });
    
    this.infoWindow = new google.maps.InfoWindow();
    
    // Add Yakima Finds marker
    this.yakimaFindsMarker = new google.maps.Marker({
        position: yakimaFindsLocation,
        map: this.map,
        title: 'Yakima Finds',
        icon: {
            url: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png',
            scaledSize: new google.maps.Size(40, 40)
        }
    });
    
    this.yakimaFindsMarker.addListener('click', () => {
        this.infoWindow.setContent(`
            <div style="padding: 10px;">
                <h3>Yakima Finds</h3>
                <p>111 S 2nd St, Yakima, WA</p>
                <p>Your local event calendar hub!</p>
            </div>
        `);
        this.infoWindow.open(this.map, this.yakimaFindsMarker);
    });
    
    // Load shops and update markers
    this.loadShops().then(() => {
        console.log('Shops loaded:', this.shops.length);
        this.updateMapMarkers();
    });
    
    // Update markers immediately with current events
    console.log('Current events:', this.events.length);
    this.updateMapMarkers();
    
    // Update nearby events when map bounds change
    this.map.addListener('bounds_changed', () => {
        this.updateNearbyEvents();
    });
};

// Override the updateMapMarkers function with better defaults
const originalUpdateMapMarkers = YakimaCalendar.prototype.updateMapMarkers;
YakimaCalendar.prototype.updateMapMarkers = function() {
    if (!this.map) {
        console.error('Map not initialized');
        return;
    }
    
    console.log('Updating map markers...');
    console.log('Events available:', this.events.length);
    console.log('Shops available:', this.shops.length);
    
    // Clear existing markers
    this.clearMarkers();
    
    // Default to showing both events and shops
    const showEvents = document.getElementById('show-events')?.checked !== false;
    const showShops = document.getElementById('show-shops')?.checked !== false;
    
    console.log('Show events:', showEvents, 'Show shops:', showShops);
    
    // Add event markers
    if (showEvents) {
        let eventCount = 0;
        this.events.forEach(event => {
            if (event.latitude && event.longitude) {
                const lat = parseFloat(event.latitude);
                const lng = parseFloat(event.longitude);
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = new google.maps.Marker({
                        position: { lat: lat, lng: lng },
                        map: this.map,
                        title: event.title,
                        icon: {
                            url: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                            scaledSize: new google.maps.Size(32, 32)
                        }
                    });
                    
                    marker.addListener('click', () => {
                        const eventDate = new Date(event.start_datetime);
                        this.infoWindow.setContent(`
                            <div style="padding: 10px; max-width: 300px;">
                                <h3>${event.title}</h3>
                                <p><strong>Date:</strong> ${eventDate.toLocaleDateString()}</p>
                                <p><strong>Time:</strong> ${eventDate.toLocaleTimeString()}</p>
                                <p><strong>Location:</strong> ${event.location}</p>
                                ${event.description ? `<p>${event.description}</p>` : ''}
                            </div>
                        `);
                        this.infoWindow.open(this.map, marker);
                    });
                    
                    this.markers.push(marker);
                    eventCount++;
                }
            }
        });
        console.log('Added', eventCount, 'event markers');
    }
    
    // Add shop markers
    if (showShops) {
        let shopCount = 0;
        this.shops.forEach(shop => {
            if (shop.latitude && shop.longitude) {
                const lat = parseFloat(shop.latitude);
                const lng = parseFloat(shop.longitude);
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = new google.maps.Marker({
                        position: { lat: lat, lng: lng },
                        map: this.map,
                        title: shop.name,
                        icon: {
                            url: 'http://maps.google.com/mapfiles/ms/icons/green-dot.png',
                            scaledSize: new google.maps.Size(32, 32)
                        }
                    });
                    
                    marker.addListener('click', () => {
                        this.infoWindow.setContent(`
                            <div style="padding: 10px; max-width: 300px;">
                                ${shop.image_url ? `<img src="${shop.image_url}" style="width: 100%; max-width: 280px; height: auto; margin-bottom: 10px; border-radius: 5px;">` : ''}
                                <h3>${shop.name}</h3>
                                ${shop.description ? `<p>${shop.description}</p>` : ''}
                                <p><strong>Address:</strong> ${shop.address}</p>
                                ${shop.phone ? `<p><strong>Phone:</strong> ${shop.phone}</p>` : ''}
                                ${shop.website ? `<p><a href="${shop.website}" target="_blank">Visit Website</a></p>` : ''}
                            </div>
                        `);
                        this.infoWindow.open(this.map, marker);
                    });
                    
                    this.markers.push(marker);
                    shopCount++;
                }
            }
        });
        console.log('Added', shopCount, 'shop markers');
    }
    
    console.log('Total markers on map:', this.markers.length);
};

// Ensure clearMarkers function exists
if (!YakimaCalendar.prototype.clearMarkers) {
    YakimaCalendar.prototype.clearMarkers = function() {
        if (this.markers) {
            this.markers.forEach(marker => marker.setMap(null));
            this.markers = [];
        }
    };
}

console.log('Calendar map fixes loaded');