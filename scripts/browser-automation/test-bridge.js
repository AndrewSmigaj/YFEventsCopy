/**
 * Database Bridge Test Script
 * ==========================
 * 
 * Tests the PHP database bridge connectivity
 */

const http = require('http');

async function callPhpBridge(action, data = null) {
    return new Promise((resolve, reject) => {
        const bridgeUrl = `http://backoffice.yakimafinds.com/scripts/browser-automation/database-bridge.php?action=${action}`;
        const postData = data ? JSON.stringify(data) : null;
        
        const options = {
            method: data ? 'POST' : 'GET',
            headers: {
                'Content-Type': 'application/json',
                'User-Agent': 'YFEvents-Browser-Scraper-Test'
            }
        };
        
        if (postData) {
            options.headers['Content-Length'] = Buffer.byteLength(postData);
        }
        
        const req = http.request(bridgeUrl, options, (res) => {
            let responseData = '';
            
            res.on('data', (chunk) => {
                responseData += chunk;
            });
            
            res.on('end', () => {
                try {
                    const jsonResponse = JSON.parse(responseData);
                    resolve(jsonResponse);
                } catch (e) {
                    reject(new Error(`Invalid JSON response: ${responseData}`));
                }
            });
        });
        
        req.on('error', (error) => {
            reject(new Error(`HTTP request failed: ${error.message}`));
        });
        
        if (postData) {
            req.write(postData);
        }
        
        req.end();
    });
}

async function runTests() {
    console.log('🧪 Testing Database Bridge...\n');
    
    try {
        // Test 1: Connection Test
        console.log('1. Testing database connection...');
        const testResult = await callPhpBridge('test');
        
        if (testResult.success) {
            console.log(`   ✅ SUCCESS: ${testResult.message}`);
            console.log(`   📊 Total events in database: ${testResult.total_events}`);
        } else {
            console.log(`   ❌ FAILED: ${testResult.message}`);
            process.exit(1);
        }
        
        // Test 2: Statistics
        console.log('\n2. Getting event statistics...');
        const statsResult = await callPhpBridge('stats');
        
        if (statsResult.error) {
            console.log(`   ❌ FAILED: ${statsResult.error}`);
        } else {
            console.log('   ✅ SUCCESS: Statistics retrieved');
            console.log(`   📈 Stats:`);
            console.log(`      - Total events: ${statsResult.total_events}`);
            console.log(`      - Pending: ${statsResult.pending_events}`);
            console.log(`      - Approved: ${statsResult.approved_events}`);
            console.log(`      - From Eventbrite: ${statsResult.eventbrite_events}`);
            console.log(`      - From Meetup: ${statsResult.meetup_events}`);
            console.log(`      - Browser scraped: ${statsResult.browser_scraped_events}`);
            console.log(`      - Recent (7 days): ${statsResult.recent_events}`);
        }
        
        // Test 3: Save Test Event
        console.log('\n3. Testing event save...');
        const testEvent = {
            title: 'Browser Automation Test Event',
            start_date: '2025-07-01 18:00:00',
            end_date: '2025-07-01 20:00:00',
            location: 'Test Venue, Yakima, WA',
            description: 'This is a test event created by the browser automation system test suite.',
            external_event_id: 'browser_test_' + Date.now(),
            url: 'https://example.com/test-event'
        };
        
        const saveResult = await callPhpBridge('save', testEvent);
        
        if (saveResult.success) {
            console.log(`   ✅ SUCCESS: ${saveResult.message}`);
            if (saveResult.action === 'inserted') {
                console.log(`   🆔 Event ID: ${saveResult.id}`);
            }
        } else {
            console.log(`   ❌ FAILED: ${saveResult.message}`);
        }
        
        console.log('\n🎉 All tests completed successfully!');
        console.log('\n📋 Ready to use:');
        console.log('   npm run eventbrite    # Scrape Eventbrite');
        console.log('   npm run meetup        # Scrape Meetup');
        console.log('   npm test             # Test mode');
        
    } catch (error) {
        console.log(`\n❌ Bridge test failed: ${error.message}`);
        console.log('\n🔧 Troubleshooting:');
        console.log('1. Ensure web server is running (Apache/Nginx)');
        console.log('2. Check database-bridge.php is accessible via web');
        console.log('3. Verify YFEvents refactored system database config');
        console.log('4. Test manually: curl http://localhost/scripts/browser-automation/database-bridge.php?action=test');
        process.exit(1);
    }
}

runTests();