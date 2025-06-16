/**
 * Modular Browser Automation Event Scraper
 * =========================================
 * 
 * A configurable browser automation framework for scraping events from
 * various sources that have anti-bot protection.
 * 
 * Features:
 * - Site-specific configuration files
 * - Headless browser automation with Puppeteer
 * - Anti-detection measures
 * - Database integration
 * - CSV export
 * - Modular architecture
 * 
 * Usage:
 *   node scraper.js --config=eventbrite --location="Yakima, WA"
 *   node scraper.js --config=meetup --debug
 *   node scraper.js --test
 */

const puppeteer = require('puppeteer');
const fs = require('fs').promises;
const path = require('path');
const { Command } = require('commander');
const winston = require('winston');
const createCsvWriter = require('csv-writer').createObjectCsvWriter;
const http = require('http');
const https = require('https');
const querystring = require('querystring');

class BrowserEventScraper {
    constructor(options = {}) {
        this.options = {
            headless: options.headless !== false,
            debug: options.debug || false,
            csvOnly: options.csvOnly || false,
            location: options.location || 'Yakima, WA',
            maxPages: options.maxPages || 10,
            configFile: options.config || 'eventbrite',
            outputDir: options.outputDir || './output',
            ...options
        };
        
        this.browser = null;
        this.page = null;
        this.config = null;
        this.events = [];
        
        this.setupLogger();
    }
    
    setupLogger() {
        this.logger = winston.createLogger({
            level: this.options.debug ? 'debug' : 'info',
            format: winston.format.combine(
                winston.format.timestamp(),
                winston.format.colorize(),
                winston.format.printf(({ timestamp, level, message }) => {
                    return `[${timestamp}] ${level}: ${message}`;
                })
            ),
            transports: [
                new winston.transports.Console(),
                new winston.transports.File({ 
                    filename: path.join(this.options.outputDir, 'scraper.log') 
                })
            ]
        });
    }
    
    async loadConfig(configName) {
        try {
            const configPath = path.join(__dirname, 'configs', `${configName}.json`);
            const configData = await fs.readFile(configPath, 'utf8');
            this.config = JSON.parse(configData);
            this.logger.info(`Loaded configuration: ${configName}`);
            return true;
        } catch (error) {
            this.logger.error(`Failed to load config ${configName}: ${error.message}`);
            return false;
        }
    }
    
    async initBrowser() {
        this.logger.info('Initializing browser...');
        
        const browserOptions = {
            headless: this.options.headless ? 'new' : false,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--single-process',
                '--disable-gpu',
                '--window-size=1920,1080'
            ]
        };
        
        if (!this.options.headless) {
            browserOptions.devtools = this.options.debug;
        }
        
        this.browser = await puppeteer.launch(browserOptions);
        this.page = await this.browser.newPage();
        
        // Set viewport and user agent
        await this.page.setViewport({ width: 1920, height: 1080 });
        await this.page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        );
        
        // Enable request interception for debugging
        if (this.options.debug) {
            await this.page.setRequestInterception(true);
            this.page.on('request', (req) => {
                if (req.url().includes('/api/') || req.url().includes('.json')) {
                    this.logger.debug(`API Request: ${req.method()} ${req.url()}`);
                }
                req.continue();
            });
        }
        
        this.logger.info('Browser initialized successfully');
    }
    
    async navigateToSearch() {
        const url = this.interpolateString(this.config.searchUrl, {
            location: encodeURIComponent(this.options.location),
            page: 1
        });
        
        this.logger.info(`Navigating to: ${url}`);
        
        try {
            await this.page.goto(url, { 
                waitUntil: 'networkidle0',
                timeout: 30000 
            });
            
            // Wait for the page to fully load
            if (this.config.waitSelectors && this.config.waitSelectors.length > 0) {
                for (const selector of this.config.waitSelectors) {
                    try {
                        await this.page.waitForSelector(selector, { timeout: 10000 });
                        this.logger.debug(`Found wait selector: ${selector}`);
                        break;
                    } catch (e) {
                        this.logger.debug(`Wait selector not found: ${selector}`);
                    }
                }
            }
            
            // Handle any initial popups or overlays
            if (this.config.dismissSelectors) {
                await this.dismissPopups();
            }
            
            return true;
        } catch (error) {
            this.logger.error(`Navigation failed: ${error.message}`);
            return false;
        }
    }
    
    async dismissPopups() {
        for (const selector of this.config.dismissSelectors) {
            try {
                const element = await this.page.$(selector);
                if (element) {
                    await element.click();
                    this.logger.debug(`Dismissed popup: ${selector}`);
                    await this.page.waitForTimeout(1000);
                }
            } catch (e) {
                // Ignore errors for dismiss selectors
            }
        }
    }
    
    async extractEvents() {
        this.logger.info('Extracting events from page...');
        
        try {
            // Wait for events to load
            const eventSelector = this.config.selectors.eventContainer;
            await this.page.waitForSelector(eventSelector, { timeout: 15000 });
            
            // Extract events using the configuration
            const events = await this.page.evaluate((config) => {
                const eventElements = document.querySelectorAll(config.selectors.eventContainer);
                const extractedEvents = [];
                
                eventElements.forEach((element, index) => {
                    try {
                        const event = {};
                        
                        // Extract each field based on configuration
                        for (const [field, fieldConfig] of Object.entries(config.selectors.fields)) {
                            let value = '';
                            
                            if (fieldConfig.selector) {
                                const fieldElement = element.querySelector(fieldConfig.selector);
                                if (fieldElement) {
                                    if (fieldConfig.attribute) {
                                        value = fieldElement.getAttribute(fieldConfig.attribute) || '';
                                    } else {
                                        value = fieldElement.textContent?.trim() || '';
                                    }
                                    
                                    // Apply transformation if specified
                                    if (fieldConfig.transform) {
                                        value = this.applyTransform(value, fieldConfig.transform);
                                    }
                                }
                            }
                            
                            event[field] = value;
                        }
                        
                        // Only include events with required fields
                        if (event.title && event.title.length > 3) {
                            extractedEvents.push(event);
                        }
                        
                    } catch (error) {
                        console.log(`Error extracting event ${index}:`, error);
                    }
                });
                
                return extractedEvents;
            }, this.config);
            
            this.logger.info(`Extracted ${events.length} events from page`);
            return events;
            
        } catch (error) {
            this.logger.error(`Event extraction failed: ${error.message}`);
            return [];
        }
    }
    
    async processPage(pageNum = 1) {
        this.logger.info(`Processing page ${pageNum}...`);
        
        try {
            // Navigate to specific page if needed
            if (pageNum > 1 && this.config.pagination) {
                await this.navigateToPage(pageNum);
            }
            
            // Extract events from current page
            const pageEvents = await this.extractEvents();
            
            // Process each event
            for (const event of pageEvents) {
                // Add metadata
                event.source = this.config.sourceName;
                event.scraped_at = new Date().toISOString();
                event.page_number = pageNum;
                
                // Validate and clean event data
                const cleanEvent = this.cleanEventData(event);
                if (cleanEvent) {
                    this.events.push(cleanEvent);
                    
                    if (this.options.debug) {
                        this.logger.debug(`Event: ${cleanEvent.title}`);
                    }
                }
            }
            
            return pageEvents.length;
            
        } catch (error) {
            this.logger.error(`Page processing failed: ${error.message}`);
            return 0;
        }
    }
    
    async navigateToPage(pageNum) {
        if (this.config.pagination.type === 'url') {
            const url = this.interpolateString(this.config.pagination.urlTemplate, {
                location: encodeURIComponent(this.options.location),
                page: pageNum
            });
            
            await this.page.goto(url, { waitUntil: 'networkidle0' });
            
        } else if (this.config.pagination.type === 'button') {
            const nextButton = await this.page.$(this.config.pagination.nextSelector);
            if (nextButton) {
                await nextButton.click();
                await this.page.waitForTimeout(2000);
            } else {
                throw new Error('Next button not found');
            }
        }
    }
    
    cleanEventData(event) {
        // Required fields
        if (!event.title || event.title.length < 3) {
            return null;
        }
        
        // Clean and format data
        const cleaned = {
            title: this.cleanText(event.title),
            start_date: this.formatDate(event.start_date),
            end_date: this.formatDate(event.end_date),
            venue_name: this.cleanText(event.venue_name || ''),
            venue_location: this.cleanText(event.venue_location || this.options.location),
            organizer: this.cleanText(event.organizer || ''),
            description: this.cleanText(event.description || ''),
            url: this.cleanUrl(event.url || ''),
            image_url: this.cleanUrl(event.image_url || ''),
            source: event.source,
            scraped_at: event.scraped_at,
            page_number: event.page_number
        };
        
        return cleaned;
    }
    
    cleanText(text) {
        if (!text) return '';
        return text.replace(/\s+/g, ' ').trim();
    }
    
    cleanUrl(url) {
        if (!url) return '';
        if (url.startsWith('http')) return url;
        if (url.startsWith('/')) return this.config.baseUrl + url;
        return url;
    }
    
    formatDate(dateStr) {
        if (!dateStr) return '';
        
        try {
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            return date.toISOString().slice(0, 19).replace('T', ' ');
        } catch (e) {
            return dateStr;
        }
    }
    
    interpolateString(template, variables) {
        return template.replace(/\{\{(\w+)\}\}/g, (match, key) => {
            return variables[key] || match;
        });
    }
    
    async saveToDatabase() {
        if (this.options.csvOnly || this.events.length === 0) {
            return;
        }
        
        try {
            this.logger.info('Saving events to database via PHP bridge...');
            
            // Prepare events for database
            const eventsToSave = this.events.map(event => {
                const externalId = `browser_${this.config.sourceId}_${this.hashString(event.url + event.title)}`;
                
                return {
                    title: event.title,
                    start_date: event.start_date || null,
                    end_date: event.end_date || null,
                    location: `${event.venue_name}, ${event.venue_location}`.replace(/^, /, ''),
                    description: `Organizer: ${event.organizer}\n\n${event.description}\n\nSource: ${event.source}`,
                    external_event_id: externalId,
                    url: event.url
                };
            });
            
            // Use the PHP database bridge
            const result = await this.callPhpBridge('batch-save', eventsToSave);
            
            if (result.success !== undefined || result.failed !== undefined) {
                this.logger.info(`✅ Database save complete:`);
                this.logger.info(`  - ${result.success || 0} events saved`);
                this.logger.info(`  - ${result.skipped || 0} events skipped (duplicates)`);
                this.logger.info(`  - ${result.failed || 0} events failed`);
                
                if (this.options.debug && result.details) {
                    result.details.forEach(detail => {
                        if (!detail.success) {
                            this.logger.debug(`Failed: ${detail.title} - ${detail.message}`);
                        }
                    });
                }
            } else {
                this.logger.error('Unexpected response from database bridge');
            }
            
        } catch (error) {
            this.logger.error(`Database save failed: ${error.message}`);
        }
    }
    
    async callPhpBridge(action, data = null) {
        return new Promise((resolve, reject) => {
            const bridgeUrl = `http://backoffice.yakimafinds.com/scripts/browser-automation/database-bridge.php?action=${action}`;
            const postData = data ? JSON.stringify(data) : null;
            
            const options = {
                method: data ? 'POST' : 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'User-Agent': 'YFEvents-Browser-Scraper'
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
    
    async testDatabaseConnection() {
        try {
            const result = await this.callPhpBridge('test');
            if (result.success) {
                this.logger.info(`✅ Database connection successful (${result.total_events} events in database)`);
                return true;
            } else {
                this.logger.error(`❌ Database connection failed: ${result.message}`);
                return false;
            }
        } catch (error) {
            this.logger.error(`❌ Database bridge error: ${error.message}`);
            return false;
        }
    }
    
    async saveToCsv() {
        if (this.events.length === 0) {
            this.logger.warn('No events to save to CSV');
            return;
        }
        
        try {
            // Ensure output directory exists
            await fs.mkdir(this.options.outputDir, { recursive: true });
            
            const csvPath = path.join(
                this.options.outputDir, 
                `${this.config.sourceId}_events_${new Date().toISOString().slice(0, 10)}.csv`
            );
            
            const csvWriter = createCsvWriter({
                path: csvPath,
                header: [
                    { id: 'title', title: 'Title' },
                    { id: 'start_date', title: 'Start Date' },
                    { id: 'end_date', title: 'End Date' },
                    { id: 'venue_name', title: 'Venue Name' },
                    { id: 'venue_location', title: 'Venue Location' },
                    { id: 'organizer', title: 'Organizer' },
                    { id: 'url', title: 'URL' },
                    { id: 'image_url', title: 'Image URL' },
                    { id: 'description', title: 'Description' },
                    { id: 'source', title: 'Source' }
                ]
            });
            
            await csvWriter.writeRecords(this.events);
            this.logger.info(`✅ Saved ${this.events.length} events to ${csvPath}`);
            
        } catch (error) {
            this.logger.error(`CSV save failed: ${error.message}`);
        }
    }
    
    hashString(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        return Math.abs(hash).toString(36);
    }
    
    async run() {
        try {
            this.logger.info(`Starting browser scraper for ${this.options.configFile}`);
            
            // Load configuration
            if (!await this.loadConfig(this.options.configFile)) {
                throw new Error(`Failed to load configuration: ${this.options.configFile}`);
            }
            
            // Test database connection (unless CSV-only mode)
            if (!this.options.csvOnly) {
                if (!await this.testDatabaseConnection()) {
                    this.logger.warn('Database connection failed, switching to CSV-only mode');
                    this.options.csvOnly = true;
                }
            }
            
            // Initialize browser
            await this.initBrowser();
            
            // Navigate to search page
            if (!await this.navigateToSearch()) {
                throw new Error('Failed to navigate to search page');
            }
            
            // Process pages
            let totalEvents = 0;
            let emptyPages = 0;
            
            for (let page = 1; page <= this.options.maxPages; page++) {
                const pageEvents = await this.processPage(page);
                totalEvents += pageEvents;
                
                if (pageEvents === 0) {
                    emptyPages++;
                    if (emptyPages >= 3) {
                        this.logger.info('Stopping after 3 consecutive empty pages');
                        break;
                    }
                } else {
                    emptyPages = 0;
                }
                
                // Break if we've reached the end
                if (page < this.options.maxPages && this.config.pagination) {
                    try {
                        // Check if next page exists
                        if (this.config.pagination.type === 'button') {
                            const hasNext = await this.page.$(this.config.pagination.nextSelector);
                            if (!hasNext) break;
                        }
                        
                        // Wait between pages
                        await this.page.waitForTimeout(2000);
                    } catch (e) {
                        break;
                    }
                }
            }
            
            // Save results
            await this.saveToDatabase();
            await this.saveToCsv();
            
            this.logger.info(`✅ Scraping complete! Found ${this.events.length} unique events`);
            
        } catch (error) {
            this.logger.error(`Scraping failed: ${error.message}`);
            throw error;
        } finally {
            if (this.browser) {
                await this.browser.close();
            }
        }
    }
}

// CLI interface
async function main() {
    const program = new Command();
    
    program
        .name('browser-scraper')
        .description('Modular browser automation event scraper')
        .version('1.0.0');
    
    program
        .option('-c, --config <name>', 'Configuration name', 'eventbrite')
        .option('-l, --location <location>', 'Search location', 'Yakima, WA')
        .option('-p, --pages <number>', 'Maximum pages to scrape', '10')
        .option('--csv-only', 'Export to CSV only (skip database)')
        .option('--headless', 'Run in headless mode', true)
        .option('--no-headless', 'Run with visible browser')
        .option('-d, --debug', 'Enable debug logging')
        .option('-o, --output <dir>', 'Output directory', './output')
        .option('--test', 'Run in test mode (single page)')
        .parse();
    
    const options = program.opts();
    
    // Convert string numbers to integers
    options.maxPages = parseInt(options.pages) || 10;
    
    // Test mode limits to single page
    if (options.test) {
        options.maxPages = 1;
        options.debug = true;
        options.headless = false;
    }
    
    try {
        // Ensure output directory exists
        await fs.mkdir(options.output, { recursive: true });
        
        const scraper = new BrowserEventScraper(options);
        await scraper.run();
        
        process.exit(0);
    } catch (error) {
        console.error('Fatal error:', error.message);
        process.exit(1);
    }
}

// Run if called directly
if (require.main === module) {
    main();
}

module.exports = { BrowserEventScraper };