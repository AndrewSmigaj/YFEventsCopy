<?php

/**
 * Optimized Scraper Configuration
 * Configuration settings for the concurrent scraping system
 */

return [
    // Execution limits
    'max_execution_time' => 1800, // 30 minutes for cron jobs
    'memory_limit' => '512M',
    
    // Worker configuration
    'max_workers' => 4,
    'single_worker_max_jobs' => 20,
    'worker_heartbeat_interval' => 30,
    'worker_timeout' => 300, // 5 minutes
    
    // Queue configuration
    'queue_batch_size' => 10,
    'queue_cleanup_interval' => 3600, // 1 hour
    'max_retry_attempts' => 3,
    'retry_backoff_multiplier' => 2,
    
    // Rate limiting
    'rate_limits' => [
        'source' => [
            'max_requests' => 5,
            'window_seconds' => 3600 // 1 hour
        ],
        'domain' => [
            'max_requests' => 20,
            'window_seconds' => 3600 // 1 hour
        ],
        'worker' => [
            'max_requests' => 100,
            'window_seconds' => 3600 // 1 hour
        ],
        'global' => [
            'max_requests' => 1000,
            'window_seconds' => 3600 // 1 hour
        ]
    ],
    
    // Scheduling priorities
    'priority_weights' => [
        'time_factor' => 1.0,
        'reliability_factor' => 0.8,
        'performance_factor' => 0.6,
        'freshness_factor' => 0.4,
        'error_factor' => 1.2
    ],
    
    // Performance thresholds
    'performance_thresholds' => [
        'min_events_found' => 2,
        'max_duration_ms' => 30000, // 30 seconds
        'min_success_rate' => 0.7,
        'max_error_count' => 5
    ],
    
    // Redis configuration (optional)
    'redis' => [
        'enabled' => extension_loaded('redis') && !empty($_ENV['REDIS_HOST']),
        'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
        'port' => $_ENV['REDIS_PORT'] ?? 6379,
        'password' => $_ENV['REDIS_PASSWORD'] ?? null,
        'database' => $_ENV['REDIS_DATABASE'] ?? 0,
        'prefix' => 'yfevents:scraper:'
    ],
    
    // Logging configuration
    'logging' => [
        'level' => 'info', // debug, info, warning, error
        'log_file' => '/tmp/optimized_scraper.log',
        'max_log_size' => 50 * 1024 * 1024, // 50MB
        'log_rotation' => true
    ],
    
    // Monitoring and alerts
    'monitoring' => [
        'enable_alerts' => true,
        'alert_thresholds' => [
            'queue_size' => 100,
            'failed_jobs_percent' => 20,
            'avg_processing_time' => 60, // seconds
            'dead_workers' => 3
        ],
        'alert_channels' => [
            'email' => $_ENV['ALERT_EMAIL'] ?? null,
            'webhook' => $_ENV['ALERT_WEBHOOK'] ?? null,
            'slack' => $_ENV['SLACK_WEBHOOK'] ?? null
        ]
    ],
    
    // Scraper-specific settings
    'scrapers' => [
        'ical' => [
            'timeout' => 30,
            'max_events' => 1000,
            'cache_duration' => 3600
        ],
        'html' => [
            'timeout' => 45,
            'max_events' => 500,
            'user_agent' => 'Mozilla/5.0 (compatible; YFEvents-Scraper/1.0)',
            'follow_redirects' => true,
            'max_redirects' => 5
        ],
        'json' => [
            'timeout' => 30,
            'max_events' => 1000,
            'validate_json' => true
        ],
        'yakima_valley' => [
            'timeout' => 60,
            'max_events' => 200,
            'parse_timeout' => 30
        ],
        'intelligent' => [
            'timeout' => 120,
            'max_events' => 100,
            'api_timeout' => 60,
            'model' => 'gpt-4o-mini',
            'max_retries' => 2
        ],
        'firecrawl_enhanced' => [
            'timeout' => 90,
            'max_events' => 300,
            'api_timeout' => 60,
            'fallback_enabled' => true
        ]
    ],
    
    // Maintenance settings
    'maintenance' => [
        'auto_optimize_threshold' => 0.5, // Success rate below 50%
        'auto_disable_threshold' => 0.2, // Success rate below 20%
        'health_check_interval' => 86400, // 24 hours
        'cleanup_retention_days' => 30
    ],
    
    // Feature flags
    'features' => [
        'intelligent_scheduling' => true,
        'rate_limiting' => true,
        'auto_optimization' => true,
        'performance_monitoring' => true,
        'concurrent_processing' => true,
        'redis_caching' => extension_loaded('redis'),
        'process_control' => function_exists('pcntl_fork')
    ]
];