<?php

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Http\FirecrawlClient;
use YFEvents\Utils\EnvLoader;

// Load environment variables
EnvLoader::load(__DIR__ . '/../.env');

$apiKey = $_ENV['FIRECRAWL_API_KEY'] ?? '';

echo "Testing simple Firecrawl scrape...\n";

try {
    $client = new FirecrawlClient($apiKey);
    
    // Test with a simple page first
    $response = $client->scrape([
        'url' => 'https://example.com',
        'formats' => ['markdown']
    ]);
    
    echo "✅ Success! Response structure:\n";
    echo "Keys: " . implode(', ', array_keys($response)) . "\n";
    
    if (isset($response['markdown'])) {
        echo "\nMarkdown content (first 200 chars):\n";
        echo substr($response['markdown'], 0, 200) . "...\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}