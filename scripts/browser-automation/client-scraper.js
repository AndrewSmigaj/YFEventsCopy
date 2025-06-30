#!/usr/bin/env node

/**
 * Client-side Browser Scraper for YFEvents
 * Run this on your local machine with Chrome installed
 * 
 * Usage:
 * node client-scraper.js --config=eventbrite --location="Yakima, WA" --pages=3
 * 
 * This generates CSV files you can upload via the admin interface
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

class ClientScraper {
    constructor() {
        this.outputDir = path.join(__dirname, 'output');
        this.ensureOutputDir();
    }

    ensureOutputDir() {
        if (!fs.existsSync(this.outputDir)) {
            fs.mkdirSync(this.outputDir, { recursive: true });
        }
    }

    async run(configName, options = {}) {
        const config = this.loadConfig(configName);
        const {
            location = 'Yakima, WA',
            pages = 3,
            headless = true
        } = options;

        console.log(`🚀 Starting ${config.site_name} scraper...`);
        console.log(`📍 Location: ${location}`);
        console.log(`📄 Max pages: ${pages}`);
        
        const browser = await puppeteer.launch({
            headless: headless ? 'new' : false,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ]
        });

        try {
            const events = await this.scrapeEvents(browser, config, location, pages);
            const csvFile = this.generateCSV(events, configName);
            const uploadInstructions = this.generateUploadInstructions(csvFile);
            
            console.log(`\n✅ Scraping completed!`);
            console.log(`📊 Found ${events.length} events`);
            console.log(`💾 Saved to: ${csvFile}`);
            console.log(`\n${uploadInstructions}`);
            
            return { events, csvFile };
        } finally {
            await browser.close();
        }
    }

    loadConfig(configName) {
        const configPath = path.join(__dirname, 'configs', `${configName}.json`);
        if (!fs.existsSync(configPath)) {
            throw new Error(`Config file not found: ${configPath}`);
        }
        return JSON.parse(fs.readFileSync(configPath, 'utf8'));
    }

    async scrapeEvents(browser, config, location, maxPages) {
        const page = await browser.newPage();
        const events = [];
        
        // Set user agent and viewport
        await page.setUserAgent(config.user_agent || 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        await page.setViewport({ width: 1366, height: 768 });
        
        try {
            // Navigate to search URL
            const searchUrl = config.search_url.replace('{location}', encodeURIComponent(location));
            console.log(`🔍 Navigating to: ${searchUrl}`);
            
            await page.goto(searchUrl, { waitUntil: 'networkidle2', timeout: 30000 });
            
            // Handle initial page load
            if (config.initial_wait) {
                await page.waitForTimeout(config.initial_wait);
            }
            
            // Wait for events to load
            if (config.selectors.event_container) {
                await page.waitForSelector(config.selectors.event_container, { timeout: 10000 });
            }
            
            for (let pageNum = 1; pageNum <= maxPages; pageNum++) {
                console.log(`📄 Scraping page ${pageNum}...`);
                
                const pageEvents = await this.scrapePage(page, config);
                events.push(...pageEvents);
                
                console.log(`   Found ${pageEvents.length} events on page ${pageNum}`);
                
                // Check for next page
                if (pageNum < maxPages) {
                    const hasNextPage = await this.goToNextPage(page, config);
                    if (!hasNextPage) {
                        console.log(`📄 No more pages available after page ${pageNum}`);
                        break;
                    }
                    
                    // Wait between pages
                    await page.waitForTimeout(2000 + Math.random() * 3000);
                }
            }
            
        } catch (error) {
            console.error(`❌ Error during scraping: ${error.message}`);
        } finally {
            await page.close();
        }
        
        return events;
    }

    async scrapePage(page, config) {
        const events = [];
        
        try {
            // Get all event links
            const eventLinks = await page.evaluate((selectors) => {
                const containers = document.querySelectorAll(selectors.event_container);
                const links = [];
                
                containers.forEach(container => {
                    const linkElement = container.querySelector(selectors.event_link || 'a');
                    if (linkElement) {
                        const href = linkElement.href;
                        if (href && !href.includes('javascript:') && !href.startsWith('#')) {
                            links.push(href);
                        }
                    }
                });
                
                return links;
            }, config.selectors);
            
            console.log(`   Found ${eventLinks.length} event links`);
            
            // Process each event (limit to avoid overwhelming)
            const linksToProcess = eventLinks.slice(0, 20);
            
            for (let i = 0; i < linksToProcess.length; i++) {
                const link = linksToProcess[i];
                try {
                    const event = await this.scrapeEventDetails(page, link, config);
                    if (event) {
                        events.push(event);
                    }
                    
                    // Random delay between events
                    await page.waitForTimeout(500 + Math.random() * 1000);
                } catch (error) {
                    console.error(`   ⚠️  Error scraping event ${i + 1}: ${error.message}`);
                }
            }
            
        } catch (error) {
            console.error(`❌ Error scraping page: ${error.message}`);
        }
        
        return events;
    }

    async scrapeEventDetails(page, eventUrl, config) {
        try {
            await page.goto(eventUrl, { waitUntil: 'networkidle2', timeout: 15000 });
            
            const event = await page.evaluate((selectors) => {
                const getText = (selector) => {
                    const element = document.querySelector(selector);
                    return element ? element.textContent.trim() : '';
                };
                
                const getAttr = (selector, attr) => {
                    const element = document.querySelector(selector);
                    return element ? element.getAttribute(attr) : '';
                };
                
                return {
                    title: getText(selectors.title),
                    description: getText(selectors.description),
                    start_date: getText(selectors.start_date),
                    start_time: getText(selectors.start_time),
                    end_date: getText(selectors.end_date),
                    end_time: getText(selectors.end_time),
                    location: getText(selectors.location),
                    address: getText(selectors.address),
                    organizer: getText(selectors.organizer),
                    price: getText(selectors.price),
                    category: getText(selectors.category),
                    url: window.location.href
                };
            }, config.selectors);
            
            // Only return if we have minimum required data
            if (event.title && (event.start_date || event.start_time)) {
                return {
                    ...event,
                    source: config.site_name,
                    scraped_at: new Date().toISOString()
                };
            }
            
        } catch (error) {
            console.error(`Error scraping event details: ${error.message}`);
        }
        
        return null;
    }

    async goToNextPage(page, config) {
        try {
            const nextButton = await page.$(config.selectors.next_page);
            if (!nextButton) {
                return false;
            }
            
            // Check if button is disabled
            const isDisabled = await page.evaluate(el => {
                return el.disabled || el.classList.contains('disabled') || 
                       el.getAttribute('aria-disabled') === 'true';
            }, nextButton);
            
            if (isDisabled) {
                return false;
            }
            
            await nextButton.click();
            await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 });
            
            return true;
        } catch (error) {
            console.log(`No next page available: ${error.message}`);
            return false;
        }
    }

    generateCSV(events, configName) {
        const timestamp = new Date().toISOString().replace(/:/g, '-').split('.')[0];
        const filename = `${configName}-events-${timestamp}.csv`;
        const filepath = path.join(this.outputDir, filename);
        
        if (events.length === 0) {
            console.log('⚠️  No events to export');
            return null;
        }
        
        // CSV Headers
        const headers = [
            'title', 'description', 'start_date', 'start_time', 'end_date', 'end_time',
            'location', 'address', 'organizer', 'price', 'category', 'url', 'source', 'scraped_at'
        ];
        
        // Generate CSV content
        const csvRows = [headers.join(',')];
        
        events.forEach(event => {
            const row = headers.map(header => {
                const value = event[header] || '';
                // Escape quotes and wrap in quotes if contains comma
                const escaped = value.toString().replace(/"/g, '""');
                return escaped.includes(',') ? `"${escaped}"` : escaped;
            });
            csvRows.push(row.join(','));
        });
        
        fs.writeFileSync(filepath, csvRows.join('\n'), 'utf8');
        return filepath;
    }

    generateUploadInstructions(csvFile) {
        if (!csvFile) return '';
        
        return `
📤 UPLOAD INSTRUCTIONS:

1. Go to your admin interface: https://backoffice.yakimafinds.com/refactor/admin/browser-scrapers.php

2. Look for "Upload CSV" or "Import Events" section

3. Upload the file: ${path.basename(csvFile)}

4. The system will automatically:
   - Parse event data
   - Check for duplicates
   - Add events to approval queue
   - Send notifications

5. Review imported events in Events admin section

💡 TIP: Keep the CSV file for your records!
        `;
    }
}

// CLI Interface
async function main() {
    const args = process.argv.slice(2);
    const options = {};
    
    // Parse command line arguments
    args.forEach(arg => {
        if (arg.startsWith('--')) {
            const [key, value] = arg.substring(2).split('=');
            if (value !== undefined) {
                options[key] = value;
            } else {
                options[key] = true;
            }
        }
    });
    
    const configName = options.config || 'eventbrite';
    
    console.log('🤖 YFEvents Client-side Browser Scraper');
    console.log('=======================================');
    
    try {
        const scraper = new ClientScraper();
        await scraper.run(configName, {
            location: options.location || 'Yakima, WA',
            pages: parseInt(options.pages) || 3,
            headless: !options.debug
        });
    } catch (error) {
        console.error(`❌ Scraper failed: ${error.message}`);
        process.exit(1);
    }
}

if (require.main === module) {
    main();
}

module.exports = ClientScraper;