<?php
/**
 * API Keys Configuration
 * This file contains API keys for external services
 * 
 * IMPORTANT: This file should NEVER be committed to version control!
 * Copy api_keys.example.php to api_keys.php and add your actual keys.
 */

// Segmind API Key for LLM-based intelligent scraping
// Get your key from: https://segmind.com/
define('SEGMIND_API_KEY', 'YOUR_SEGMIND_API_KEY_HERE');

// Google Maps API Key (if not using .env)
// define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY_HERE');

// Note: You can also set these as environment variables:
// putenv('SEGMIND_API_KEY=your_actual_key_here');
// putenv('GOOGLE_MAPS_API_KEY=your_actual_key_here');