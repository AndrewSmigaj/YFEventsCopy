<?php

/**
 * Debug Firecrawl Connection
 */

require_once __DIR__ . '/../vendor/autoload.php';

use YakimaFinds\Utils\EnvLoader;

// Load environment variables
EnvLoader::load(__DIR__ . '/../.env');

$apiKey = $_ENV['FIRECRAWL_API_KEY'] ?? '';
echo "API Key (first 10 chars): " . substr($apiKey, 0, 10) . "...\n";

// Test direct API call
$url = 'https://api.firecrawl.dev/v0/scrape';
$headers = [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
];

$data = [
    'url' => 'https://example.com',
    'formats' => ['markdown']
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "\nHTTP Code: $httpCode\n";
echo "Error: $error\n";
echo "Response: " . substr($response, 0, 200) . "...\n";

$decoded = json_decode($response, true);
if (isset($decoded['error'])) {
    echo "API Error: " . $decoded['error'] . "\n";
}