#!/usr/bin/env python3
"""
Eventbrite Scraper for Yakima Events
====================================

Scrapes Eventbrite search results for "Yakima" events and extracts event details
from individual event pages using JSON-LD metadata or HTML fallback.

Requirements:
- Python 3
- requests
- beautifulsoup4

Usage:
    python3 eventbrite_scraper.py

Output:
    yakima_eventbrite_events.csv
"""

import requests
import json
import csv
import time
import logging
import re
from urllib.parse import urljoin, urlparse
from datetime import datetime
from bs4 import BeautifulSoup

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('eventbrite_scraper.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

class EventbriteScraper:
    def __init__(self):
        self.base_url = "https://www.eventbrite.com"
        self.search_url = "https://www.eventbrite.com/d/online/yakima/"
        self.session = requests.Session()
        
        # Set a polite user agent
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (YakimaFinds Event Calendar Bot) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        })
        
        self.events = []
        
    def scrape_search_results(self):
        """Scrape the main search results page for event links"""
        logger.info(f"Fetching search results from: {self.search_url}")
        
        try:
            response = self.session.get(self.search_url, timeout=10)
            response.raise_for_status()
            
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Find event links - Eventbrite uses various selectors
            event_links = set()
            
            # Common selectors for event links
            selectors = [
                'a[href*="/e/"]',  # Standard event links
                'a[data-event-id]',  # Event cards with data attributes
                '.event-card a',   # Event card containers
                '.search-event-card-wrapper a',  # Search result cards
                '[data-testid="event-card"] a'   # Modern data-testid approach
            ]
            
            for selector in selectors:
                links = soup.select(selector)
                for link in links:
                    href = link.get('href')
                    if href and '/e/' in href:
                        # Convert relative URLs to absolute
                        full_url = urljoin(self.base_url, href)
                        # Clean URL (remove query parameters)
                        clean_url = full_url.split('?')[0]
                        event_links.add(clean_url)
            
            logger.info(f"Found {len(event_links)} unique event links")
            
            if not event_links:
                logger.warning("No event links found. Page structure may have changed.")
                # Debug: Save the page content for inspection
                with open('debug_search_page.html', 'w', encoding='utf-8') as f:
                    f.write(response.text)
                logger.info("Saved search page to debug_search_page.html for inspection")
            
            return list(event_links)
            
        except requests.RequestException as e:
            logger.error(f"Error fetching search results: {e}")
            return []
    
    def extract_json_ld(self, soup):
        """Extract event data from JSON-LD structured data"""
        try:
            # Find JSON-LD script tags
            json_scripts = soup.find_all('script', type='application/ld+json')
            
            for script in json_scripts:
                try:
                    data = json.loads(script.string)
                    
                    # Handle both single objects and arrays
                    if isinstance(data, list):
                        for item in data:
                            if item.get('@type') == 'Event':
                                return self.parse_json_ld_event(item)
                    elif data.get('@type') == 'Event':
                        return self.parse_json_ld_event(data)
                        
                except json.JSONDecodeError:
                    continue
                    
        except Exception as e:
            logger.warning(f"Error parsing JSON-LD: {e}")
            
        return None
    
    def parse_json_ld_event(self, data):
        """Parse event data from JSON-LD format"""
        event = {}
        
        # Basic event info
        event['title'] = data.get('name', '')
        event['description'] = data.get('description', '')
        event['url'] = data.get('url', '')
        
        # Dates
        start_date = data.get('startDate', '')
        end_date = data.get('endDate', '')
        event['start_date'] = self.format_date(start_date)
        event['end_date'] = self.format_date(end_date)
        
        # Location
        location = data.get('location', {})
        if isinstance(location, dict):
            event['venue_name'] = location.get('name', '')
            address = location.get('address', {})
            if isinstance(address, dict):
                city = address.get('addressLocality', '')
                state = address.get('addressRegion', '')
                event['venue_location'] = f"{city}, {state}".strip(', ')
            else:
                event['venue_location'] = str(address) if address else ''
        else:
            event['venue_name'] = str(location) if location else ''
            event['venue_location'] = ''
        
        # Organizer
        organizer = data.get('organizer', {})
        if isinstance(organizer, dict):
            event['organizer'] = organizer.get('name', '')
        else:
            event['organizer'] = str(organizer) if organizer else ''
        
        # Image
        image = data.get('image', '')
        if isinstance(image, list) and image:
            image = image[0]
        if isinstance(image, dict):
            image = image.get('url', '')
        event['image_url'] = str(image) if image else ''
        
        return event
    
    def extract_html_fallback(self, soup, url):
        """Fallback HTML parsing when JSON-LD is not available"""
        event = {'url': url}
        
        # Title - try multiple selectors
        title_selectors = [
            'h1.listing-hero-title',
            'h1[data-automation="event-title"]',
            '.event-title h1',
            'h1.event-title',
            'h1'
        ]
        
        for selector in title_selectors:
            title_elem = soup.select_one(selector)
            if title_elem:
                event['title'] = title_elem.get_text().strip()
                break
        else:
            event['title'] = ''
        
        # Date/Time - look for datetime attributes or text patterns
        date_selectors = [
            '[datetime]',
            '.event-details time',
            '.listing-hero-date',
            '.date-info'
        ]
        
        dates_found = []
        for selector in date_selectors:
            date_elems = soup.select(selector)
            for elem in date_elems:
                # Try datetime attribute first
                dt = elem.get('datetime')
                if dt:
                    dates_found.append(self.format_date(dt))
                else:
                    # Try to parse text content
                    text = elem.get_text().strip()
                    parsed_date = self.parse_date_text(text)
                    if parsed_date:
                        dates_found.append(parsed_date)
        
        # Assign dates
        event['start_date'] = dates_found[0] if dates_found else ''
        event['end_date'] = dates_found[1] if len(dates_found) > 1 else ''
        
        # Venue
        venue_selectors = [
            '.venue-name',
            '.location-info .name',
            '[data-automation="venue-name"]'
        ]
        
        for selector in venue_selectors:
            venue_elem = soup.select_one(selector)
            if venue_elem:
                event['venue_name'] = venue_elem.get_text().strip()
                break
        else:
            event['venue_name'] = ''
        
        # Location
        location_selectors = [
            '.venue-address',
            '.location-info .address',
            '[data-automation="venue-address"]'
        ]
        
        for selector in location_selectors:
            location_elem = soup.select_one(selector)
            if location_elem:
                event['venue_location'] = location_elem.get_text().strip()
                break
        else:
            event['venue_location'] = ''
        
        # Organizer
        organizer_selectors = [
            '.organizer-name',
            '.organizer-info .name',
            '[data-automation="organizer-name"]'
        ]
        
        for selector in organizer_selectors:
            organizer_elem = soup.select_one(selector)
            if organizer_elem:
                event['organizer'] = organizer_elem.get_text().strip()
                break
        else:
            event['organizer'] = ''
        
        # Image
        image_selectors = [
            '.event-hero-image img',
            '.listing-hero-image img',
            '.event-image img'
        ]
        
        for selector in image_selectors:
            img_elem = soup.select_one(selector)
            if img_elem:
                src = img_elem.get('src') or img_elem.get('data-src')
                if src:
                    event['image_url'] = urljoin(url, src)
                    break
        else:
            event['image_url'] = ''
        
        return event
    
    def format_date(self, date_str):
        """Format date string to YYYY-MM-DD HH:MM:SS"""
        if not date_str:
            return ''
        
        try:
            # Handle ISO format dates
            if 'T' in date_str:
                dt = datetime.fromisoformat(date_str.replace('Z', '+00:00'))
                return dt.strftime('%Y-%m-%d %H:%M:%S')
            
            # Try other common formats
            formats = [
                '%Y-%m-%d %H:%M:%S',
                '%Y-%m-%d',
                '%m/%d/%Y %H:%M',
                '%m/%d/%Y'
            ]
            
            for fmt in formats:
                try:
                    dt = datetime.strptime(date_str, fmt)
                    return dt.strftime('%Y-%m-%d %H:%M:%S')
                except ValueError:
                    continue
                    
        except Exception as e:
            logger.warning(f"Could not parse date '{date_str}': {e}")
        
        return date_str
    
    def parse_date_text(self, text):
        """Try to extract date from text content"""
        # This is a simplified parser - could be expanded
        date_patterns = [
            r'(\d{1,2}/\d{1,2}/\d{4})',
            r'(\w+ \d{1,2}, \d{4})',
            r'(\d{4}-\d{2}-\d{2})'
        ]
        
        for pattern in date_patterns:
            match = re.search(pattern, text)
            if match:
                return self.format_date(match.group(1))
        
        return None
    
    def scrape_event_page(self, url):
        """Scrape individual event page for details"""
        logger.info(f"Scraping event: {url}")
        
        try:
            response = self.session.get(url, timeout=10)
            response.raise_for_status()
            
            soup = BeautifulSoup(response.content, 'html.parser')
            
            # Try JSON-LD first
            event = self.extract_json_ld(soup)
            
            # Fall back to HTML parsing
            if not event or not event.get('title'):
                logger.info(f"JSON-LD not found for {url}, using HTML fallback")
                event = self.extract_html_fallback(soup, url)
            
            # Ensure URL is set
            event['url'] = url
            
            # Fill in missing fields with empty strings
            required_fields = ['title', 'start_date', 'end_date', 'venue_name', 'venue_location', 'organizer', 'image_url']
            for field in required_fields:
                if field not in event:
                    event[field] = ''
            
            return event
            
        except requests.RequestException as e:
            logger.error(f"Error fetching event page {url}: {e}")
            return None
        except Exception as e:
            logger.error(f"Error parsing event page {url}: {e}")
            return None
    
    def save_to_csv(self, filename='yakima_eventbrite_events.csv'):
        """Save events to CSV file"""
        if not self.events:
            logger.warning("No events to save")
            return
        
        fieldnames = ['title', 'start_date', 'end_date', 'venue_name', 'venue_location', 'organizer', 'url', 'image_url']
        
        with open(filename, 'w', newline='', encoding='utf-8') as csvfile:
            writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
            writer.writeheader()
            
            for event in self.events:
                writer.writerow(event)
        
        logger.info(f"Saved {len(self.events)} events to {filename}")
    
    def run(self):
        """Main scraping process"""
        logger.info("Starting Eventbrite scraper for Yakima events")
        
        # Get event links from search page
        event_links = self.scrape_search_results()
        
        if not event_links:
            logger.error("No events found on search page")
            return
        
        # Scrape each event page
        for i, url in enumerate(event_links, 1):
            logger.info(f"Processing event {i}/{len(event_links)}")
            
            event = self.scrape_event_page(url)
            if event and event.get('title'):
                self.events.append(event)
                logger.info(f"✅ Scraped: {event['title']}")
            else:
                logger.warning(f"❌ Failed to scrape event: {url}")
            
            # Be polite - pause between requests
            if i < len(event_links):
                time.sleep(2)
        
        # Save results
        self.save_to_csv()
        
        logger.info(f"Scraping complete. Found {len(self.events)} events out of {len(event_links)} pages.")

def main():
    scraper = EventbriteScraper()
    scraper.run()

if __name__ == "__main__":
    main()