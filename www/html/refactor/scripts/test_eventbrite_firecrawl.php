<?php

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Http\FirecrawlClient;
use YFEvents\Utils\EnvLoader;

// Load environment variables
EnvLoader::load(__DIR__ . '/../.env');

$apiKey = $_ENV['FIRECRAWL_API_KEY'] ?? '';

echo "Testing Eventbrite with Firecrawl...\n";

try {
    $client = new FirecrawlClient($apiKey);
    
    // Test Eventbrite Yakima page
    $url = 'https://www.eventbrite.com/d/wa--yakima/events/';
    echo "Scraping: $url\n";
    echo "This may take 30-60 seconds...\n\n";
    
    $response = $client->scrape([
        'url' => $url,
        'formats' => ['markdown', 'html'],
        'waitFor' => 5000
    ]);
    
    echo "✅ Success! Response structure:\n";
    echo "Keys: " . implode(', ', array_keys($response)) . "\n";
    
    if (isset($response['markdown'])) {
        // Look for event patterns in markdown
        $markdown = $response['markdown'];
        
        // Try to find event titles (usually in headers or links)
        preg_match_all('/#{2,3}\s*([^\n]+)|(?:\[([^\]]+)\]\([^\)]+eventbrite\.com[^\)]+\))/i', $markdown, $matches);
        
        $potentialEvents = array_merge(
            array_filter($matches[1]),
            array_filter($matches[2])
        );
        
        echo "\nPotential events found: " . count($potentialEvents) . "\n";
        if (!empty($potentialEvents)) {
            echo "First few events:\n";
            foreach (array_slice($potentialEvents, 0, 5) as $i => $event) {
                echo ($i + 1) . ". " . trim($event) . "\n";
            }
        }
        
        // Save full markdown for analysis
        file_put_contents(__DIR__ . '/eventbrite_scrape.md', $markdown);
        echo "\nFull markdown saved to: eventbrite_scrape.md\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}