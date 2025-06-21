<?php

/**
 * Firecrawl Configuration
 * 
 * Configuration for Firecrawl API integration
 */

return [
    // API Configuration
    'api_key' => $_ENV['FIRECRAWL_API_KEY'] ?? '',
    'api_endpoint' => $_ENV['FIRECRAWL_API_ENDPOINT'] ?? 'https://api.firecrawl.dev/v0',
    
    // Request Configuration
    'timeout' => 30, // seconds
    'max_retries' => 3,
    'retry_delay' => 1, // seconds
    
    // Rate Limiting
    'rate_limit' => [
        'delay' => 2, // seconds between requests
        'max_concurrent' => 1,
        'requests_per_minute' => 30,
        'backoff_multiplier' => 2
    ],
    
    // Scraping Configuration
    'scraping' => [
        'max_events_per_scrape' => 50,
        'wait_for_dynamic_content' => 3000, // milliseconds
        'screenshot' => false,
        'only_main_content' => true
    ],
    
    // User Agent
    'user_agent' => 'YFEvents/2.0 (https://yakimafinds.com; events@yakimafinds.com)',
    
    // Eventbrite Specific Settings
    'eventbrite' => [
        'base_url' => 'https://www.eventbrite.com/d',
        'default_state' => 'wa',
        'categories' => [
            'music',
            'arts',
            'food-drink',
            'community',
            'business',
            'sports-fitness',
            'health-wellness',
            'science-tech',
            'travel-outdoor',
            'charity-causes',
            'spirituality',
            'family-education',
            'holiday',
            'government-politics',
            'fashion-beauty',
            'home-lifestyle',
            'auto-boat-air',
            'hobbies-special-interest',
            'other'
        ]
    ],
    
    // Error Handling
    'error_handling' => [
        'log_errors' => true,
        'throw_exceptions' => true,
        'notify_on_failure' => false
    ],
    
    // Robots.txt Compliance
    'robots_compliance' => [
        'check_robots_txt' => true,
        'cache_robots_txt' => true,
        'cache_duration' => 86400 // 24 hours
    ]
];