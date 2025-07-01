// Map control functionality for toggling pins
document.addEventListener('DOMContentLoaded', function() {
    // Create map control panel
    const mapView = document.getElementById('map-view');
    if (mapView) {
        // Add control panel HTML
        const controlsHTML = `
            <div class="map-controls-panel" style="position: absolute; top: 10px; right: 10px; background: white; padding: 15px; border-radius: 5px; box-shadow: 0 2px 6px rgba(0,0,0,0.3); z-index: 100;">
                <h4 style="margin: 0 0 10px 0;">Map Layers</h4>
                <div style="margin-bottom: 5px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="toggle-events" checked style="margin-right: 8px;">
                        <span style="color: #0066cc;">Events (Blue)</span>
                    </label>
                </div>
                <div style="margin-bottom: 5px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="toggle-shops" checked style="margin-right: 8px;">
                        <span style="color: #00aa00;">Shops (Green)</span>
                    </label>
                </div>
                <div style="margin-bottom: 5px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="toggle-yakima" checked style="margin-right: 8px;">
                        <span style="color: #cc0000;">Yakima Finds (Red)</span>
                    </label>
                </div>
                <hr style="margin: 10px 0;">
                <button id="toggle-all" style="width: 100%; padding: 5px; cursor: pointer;">Toggle All</button>
            </div>
        `;
        
        // Find map container and add controls
        const mapContainer = mapView.querySelector('.map-container');
        if (mapContainer) {
            mapContainer.style.position = 'relative';
            mapContainer.insertAdjacentHTML('beforeend', controlsHTML);
        }
        
        // Setup event listeners
        document.getElementById('toggle-events')?.addEventListener('change', function() {
            toggleMarkers('events', this.checked);
        });
        
        document.getElementById('toggle-shops')?.addEventListener('change', function() {
            toggleMarkers('shops', this.checked);
        });
        
        document.getElementById('toggle-yakima')?.addEventListener('change', function() {
            toggleYakimaFinds(this.checked);
        });
        
        document.getElementById('toggle-all')?.addEventListener('click', function() {
            const allChecked = document.getElementById('toggle-events').checked && 
                             document.getElementById('toggle-shops').checked && 
                             document.getElementById('toggle-yakima').checked;
            
            document.getElementById('toggle-events').checked = !allChecked;
            document.getElementById('toggle-shops').checked = !allChecked;
            document.getElementById('toggle-yakima').checked = !allChecked;
            
            toggleMarkers('events', !allChecked);
            toggleMarkers('shops', !allChecked);
            toggleYakimaFinds(!allChecked);
        });
    }
});

// Toggle functions
function toggleMarkers(type, show) {
    if (window.calendar && window.calendar.markers) {
        window.calendar.markers.forEach(marker => {
            if (type === 'events' && marker.icon.url.includes('blue')) {
                marker.setVisible(show);
            } else if (type === 'shops' && marker.icon.url.includes('green')) {
                marker.setVisible(show);
            }
        });
    }
}

function toggleYakimaFinds(show) {
    if (window.calendar && window.calendar.yakimaFindsMarker) {
        window.calendar.yakimaFindsMarker.setVisible(show);
    }
}