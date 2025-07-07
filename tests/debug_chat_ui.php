<?php
// Debug chat UI issues

session_start();

// Set up a test session
$_SESSION = [
    'auth' => [
        'user_id' => 1,
        'username' => 'testuser',
        'email' => 'test@example.com',
        'roles' => ['seller']
    ],
    'seller' => [
        'seller_id' => 1,
        'company_name' => 'Test Company',
        'contact_name' => 'Test User'
    ]
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Chat UI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .debug-info {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #f0f0f0;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            max-width: 300px;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            z-index: 9999;
        }
        .error { color: red; }
        .success { color: green; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h1>Chat UI Debug Page</h1>
    
    <div class="debug-info" id="debug-info">
        <h3>Debug Console</h3>
        <div id="debug-log"></div>
    </div>
    
    <iframe id="chat-iframe" 
            src="/communication/embedded?seller_id=1" 
            style="width: 100%; height: 600px; border: 1px solid #ccc;">
    </iframe>
    
    <script>
        const debugLog = document.getElementById('debug-log');
        
        function log(message, type = 'info') {
            const entry = document.createElement('div');
            entry.className = type;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            debugLog.appendChild(entry);
            debugLog.scrollTop = debugLog.scrollHeight;
        }
        
        // Monitor iframe loading
        const iframe = document.getElementById('chat-iframe');
        
        iframe.onload = function() {
            log('Iframe loaded', 'success');
            
            try {
                // Try to access iframe content
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                
                // Check for required elements
                const elements = [
                    'messages-container',
                    'messages-area',
                    'message-input-area',
                    'message-input-wrapper',
                    'message-form',
                    'channel-header'
                ];
                
                elements.forEach(id => {
                    const el = iframeDoc.getElementById(id);
                    if (el) {
                        log(`✓ Found #${id}`, 'success');
                        if (el.style.display === 'none') {
                            log(`  └ Hidden (display: none)`, 'warning');
                        }
                    } else {
                        log(`✗ Missing #${id}`, 'error');
                    }
                });
                
                // Check if CommunicationApp is available
                if (iframe.contentWindow.CommunicationApp) {
                    log('✓ CommunicationApp loaded', 'success');
                    
                    // Check current channel
                    if (iframe.contentWindow.CommunicationApp.currentChannel) {
                        log(`Current channel: ${iframe.contentWindow.CommunicationApp.currentChannel.name}`, 'info');
                    } else {
                        log('No channel selected', 'warning');
                    }
                } else {
                    log('✗ CommunicationApp not found', 'error');
                }
                
                // Monitor console errors in iframe
                iframe.contentWindow.addEventListener('error', function(e) {
                    log(`JS Error: ${e.message} at ${e.filename}:${e.lineno}`, 'error');
                });
                
            } catch (e) {
                log(`Cannot access iframe content: ${e.message}`, 'error');
            }
        };
        
        iframe.onerror = function(e) {
            log('Iframe failed to load', 'error');
        };
        
        // Test API directly
        fetch('/api/communication/channels', {
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                log(`API: Found ${data.data.length} channels`, 'success');
            } else {
                log(`API Error: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            log(`API Fetch Error: ${error.message}`, 'error');
        });
    </script>
</body>
</html>