<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin: 20px 0; padding: 10px; border: 1px solid #ccc; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>YFEvents Calendar Debug</h1>
    
    <div class="debug-section">
        <h2>1. API Endpoints Test</h2>
        <button onclick="testEvents()">Test Events API</button>
        <button onclick="testShops()">Test Shops API</button>
        <div id="api-results"></div>
    </div>
    
    <div class="debug-section">
        <h2>2. Calendar State</h2>
        <button onclick="checkCalendar()">Check Calendar Object</button>
        <div id="calendar-state"></div>
    </div>
    
    <div class="debug-section">
        <h2>3. Console Log</h2>
        <div id="console-log" style="background: #000; color: #0f0; padding: 10px; font-family: monospace; height: 200px; overflow-y: auto;"></div>
    </div>
    
    <div class="debug-section">
        <h2>4. View Calendar</h2>
        <p><a href="/calendar.php" target="_blank">Open Calendar in New Tab</a></p>
    </div>

    <script>
        // Override console.log to display in our debug area
        const logArea = document.getElementById('console-log');
        const originalLog = console.log;
        const originalError = console.error;
        
        console.log = function(...args) {
            originalLog.apply(console, args);
            logArea.innerHTML += '<div style="color: #0f0;">[LOG] ' + args.join(' ') + '</div>';
            logArea.scrollTop = logArea.scrollHeight;
        };
        
        console.error = function(...args) {
            originalError.apply(console, args);
            logArea.innerHTML += '<div style="color: #f00;">[ERROR] ' + args.join(' ') + '</div>';
            logArea.scrollTop = logArea.scrollHeight;
        };
        
        async function testEvents() {
            const resultsDiv = document.getElementById('api-results');
            resultsDiv.innerHTML = '<p>Testing Events API...</p>';
            
            try {
                // Get current month range
                const now = new Date();
                const start = new Date(now.getFullYear(), now.getMonth(), 1);
                const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                
                const startStr = start.toISOString().split('T')[0];
                const endStr = end.toISOString().split('T')[0];
                
                console.log('Fetching events from:', startStr, 'to:', endStr);
                
                const response = await fetch(`/api/events-simple.php?start=${startStr}&end=${endStr}`);
                const data = await response.json();
                
                resultsDiv.innerHTML = `
                    <p class="success">✓ Events API Response:</p>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
            } catch (error) {
                resultsDiv.innerHTML = `<p class="error">✗ Error: ${error.message}</p>`;
                console.error('Events API Error:', error);
            }
        }
        
        async function testShops() {
            const resultsDiv = document.getElementById('api-results');
            resultsDiv.innerHTML = '<p>Testing Shops API...</p>';
            
            try {
                const response = await fetch('/api/shops/');
                const data = await response.json();
                
                resultsDiv.innerHTML = `
                    <p class="success">✓ Shops API Response:</p>
                    <pre>${JSON.stringify(data, null, 2)}</pre>
                `;
            } catch (error) {
                resultsDiv.innerHTML = `<p class="error">✗ Error: ${error.message}</p>`;
                console.error('Shops API Error:', error);
            }
        }
        
        function checkCalendar() {
            const stateDiv = document.getElementById('calendar-state');
            
            if (window.calendar) {
                stateDiv.innerHTML = `
                    <p class="success">✓ Calendar object exists</p>
                    <p>Current View: ${window.calendar.currentView || 'not set'}</p>
                    <p>Events loaded: ${window.calendar.events ? window.calendar.events.length : 0}</p>
                    <p>API Endpoint: ${window.calendar.options.apiEndpoint}</p>
                `;
            } else {
                stateDiv.innerHTML = '<p class="error">✗ Calendar object not found</p>';
            }
        }
        
        // Auto-test on load
        window.addEventListener('load', () => {
            console.log('Debug page loaded');
            setTimeout(() => {
                testEvents();
            }, 1000);
        });
    </script>
</body>
</html>