<?php
// Test that chat UI elements are properly shown/hidden

session_start();
$_SESSION = [
    'auth' => [
        'user_id' => 1,
        'username' => 'testuser',
        'email' => 'test@example.com',
        'roles' => ['seller']
    ]
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Chat UI Fix</title>
    <style>
        .test-result { margin: 10px; padding: 10px; border: 1px solid #ccc; }
        .pass { background: #d4edda; color: #155724; }
        .fail { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>Chat UI Test Results</h1>
    
    <div id="results"></div>
    
    <h2>Chat Interface</h2>
    <iframe id="chat-frame" src="/communication/embedded?seller_id=1" style="width: 100%; height: 500px; border: 1px solid #ccc;"></iframe>
    
    <script>
        const results = document.getElementById('results');
        
        function addResult(test, passed, details = '') {
            const div = document.createElement('div');
            div.className = 'test-result ' + (passed ? 'pass' : 'fail');
            div.innerHTML = `<strong>${passed ? '✓' : '✗'} ${test}</strong>${details ? '<br>' + details : ''}`;
            results.appendChild(div);
        }
        
        document.getElementById('chat-frame').onload = async function() {
            const iframe = this;
            const iframeWindow = iframe.contentWindow;
            const iframeDoc = iframe.contentDocument;
            
            // Wait a bit for JavaScript to initialize
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            // Test 1: Check if CommunicationApp exists
            addResult('CommunicationApp loaded', !!iframeWindow.CommunicationApp);
            
            // Test 2: Check required elements exist
            const elements = {
                'messages-area': 'Messages container',
                'message-input-wrapper': 'Message input area',
                'channel-header': 'Channel header',
                'message-form': 'Message form'
            };
            
            for (const [id, name] of Object.entries(elements)) {
                const el = iframeDoc.getElementById(id);
                addResult(`${name} exists`, !!el, el ? `Found #${id}` : `Missing #${id}`);
            }
            
            // Test 3: Simulate channel selection
            if (iframeWindow.CommunicationApp && iframeWindow.CommunicationApp.channels.length > 0) {
                const testChannel = iframeWindow.CommunicationApp.channels[0];
                
                // Call selectChannel
                await iframeWindow.CommunicationApp.selectChannel(testChannel);
                
                // Wait for UI updates
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // Check if elements are now visible
                const headerVisible = iframeDoc.getElementById('channel-header').style.display !== 'none';
                const inputVisible = iframeDoc.getElementById('message-input-wrapper').style.display !== 'none';
                
                addResult('Channel header shown after selection', headerVisible);
                addResult('Message input shown after selection', inputVisible);
                
                // Check if channel name is displayed
                const channelNameEl = iframeDoc.getElementById('channel-name');
                const hasChannelName = channelNameEl && channelNameEl.textContent.includes(testChannel.name);
                addResult('Channel name displayed', hasChannelName, 
                    hasChannelName ? `Shows: "${channelNameEl.textContent}"` : 'Channel name not set');
                
            } else {
                addResult('Channel selection test', false, 'No channels available to test');
            }
        };
    </script>
</body>
</html>