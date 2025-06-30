# Intelligent Event Scraper

The Intelligent Event Scraper uses Large Language Models (LLMs) to automatically analyze websites and extract event information without requiring manual configuration.

## Features

- **Automatic Event Detection**: LLMs analyze webpage content to find events or event links
- **Pattern Recognition**: Learns extraction patterns for reuse on similar websites
- **Multiple LLM Support**: Integrates with Segmind API for access to LLaVA, Claude Sonnet, and Claude Opus models
- **Method Storage**: Successful extraction methods are saved for future use
- **Visual Interface**: Web-based UI for testing and approving scraping methods

## Setup

1. **Get Segmind API Key**
   - Sign up at https://segmind.com/
   - Get your API key from the dashboard
   - Copy `config/api_keys.example.php` to `config/api_keys.php`
   - Add your API key

2. **Run Database Migration**
   ```sql
   mysql -u your_user -p your_database < database/intelligent_scraper_schema.sql
   ```

3. **Include API Configuration**
   Add to your application bootstrap:
   ```php
   require_once __DIR__ . '/config/api_keys.php';
   ```

## Usage

### Web Interface

1. Navigate to `/admin/intelligent-scraper.php`
2. Enter a URL to analyze
3. The system will:
   - Fetch the webpage
   - Analyze with LLM to find events
   - Extract event information
   - Generate a reusable method
4. Review found events
5. Approve to save the method and create a scraper source

### Programmatic Usage

```php
use YakimaFinds\Scrapers\Intelligent\LLMScraper;

$scraper = new LLMScraper($db);

// Start analysis session
$sessionId = $scraper->startSession($url);

// Analyze URL
$result = $scraper->analyzeUrl($url);

if ($result['success']) {
    // Events found
    foreach ($result['events'] as $event) {
        echo $event['title'] . ' - ' . $event['start_datetime'] . "\n";
    }
    
    // Approve method for future use
    $scraper->approveMethod($sessionId);
}
```

## How It Works

1. **Initial Analysis**
   - Fetches webpage HTML content
   - Sends to LLM for pattern detection
   - LLM identifies event containers, fields, and patterns

2. **Event Extraction**
   - If events are directly found, extracts them
   - If event links are found, follows them for details
   - Normalizes data (dates, locations, etc.)

3. **Method Generation**
   - LLM creates extraction rules based on successful extraction
   - Includes CSS selectors, date patterns, URL construction rules
   - Stores confidence score and test results

4. **Reuse**
   - When scraping the same domain, checks for existing methods
   - Applies saved method for faster extraction
   - Updates success statistics

## Database Schema

### intelligent_scraper_methods
Stores LLM-generated extraction methods with selectors, patterns, and success metrics.

### intelligent_scraper_sessions
Tracks analysis sessions including found events and user feedback.

### intelligent_scraper_cache
Caches successful extractions for pattern learning.

### intelligent_scraper_patterns
Stores discovered URL patterns for different event types.

## API Integration

The system uses Segmind API endpoints:
- **LLaVA v1.6**: For visual webpage analysis
- **Claude 3 Sonnet**: For HTML content analysis and pattern detection
- **Claude 3 Opus**: For generating sophisticated extraction methods

## Best Practices

1. **Test Multiple Pages**: Test methods on various pages from the same site
2. **Review Results**: Always review extracted events before approving
3. **Monitor Success Rate**: Check method performance over time
4. **Update Methods**: Recreate methods if website structure changes
5. **API Usage**: Be mindful of API costs and rate limits

## Troubleshooting

- **No Events Found**: The page might not contain events or use complex JavaScript rendering
- **Extraction Errors**: Website structure might have changed; regenerate the method
- **API Errors**: Check API key and rate limits
- **Performance**: For large-scale scraping, use traditional configured scrapers