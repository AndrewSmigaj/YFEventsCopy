<?php
/**
 * API Keys Configuration Template
 * 
 * INSTRUCTIONS:
 * 1. Copy this file to api_keys.php
 * 2. Replace placeholder values with your actual API keys
 * 3. NEVER commit api_keys.php to version control
 * 
 * The api_keys.php file is already in .gitignore
 */

// Segmind API Key for LLM-based intelligent scraping
// Get your key from: https://segmind.com/
define('SEGMIND_API_KEY', 'your_segmind_api_key_here');

// Google Maps API Key (if not using .env)
// define('GOOGLE_MAPS_API_KEY', 'your_google_maps_api_key_here');

// Alternative: Use environment variables
// putenv('SEGMIND_API_KEY=your_actual_key_here');
// putenv('GOOGLE_MAPS_API_KEY=your_actual_key_here');

// Or load from .env file (recommended)
// $apiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';