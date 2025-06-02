<?php

namespace YFEvents\Scrapers;

class ScraperFactory
{
    /**
     * Create a scraper instance based on type
     */
    public static function create($type, $configuration = null)
    {
        $config = is_string($configuration) ? json_decode($configuration, true) : $configuration;
        
        switch ($type) {
            case 'ical':
                return new ICalScraper($config);
                
            case 'html':
                return new HtmlScraper($config);
                
            case 'json':
                return new JsonScraper($config);
                
            case 'yakima_valley':
                return new YakimaValleyEventScraper($config);
                
            case 'intelligent':
                return new IntelligentScraper($config);
                
            default:
                return null;
        }
    }
    
    /**
     * Get available scraper types
     */
    public static function getAvailableTypes()
    {
        return [
            'ical' => 'iCal/ICS Calendar Feed',
            'html' => 'HTML Web Scraping',
            'json' => 'JSON API',
            'yakima_valley' => 'Yakima Valley Events',
            'intelligent' => 'AI-Powered Scraping'
        ];
    }
    
    /**
     * Get configuration template for a scraper type
     */
    public static function getConfigurationTemplate($type)
    {
        switch ($type) {
            case 'ical':
                return [
                    'url' => 'https://example.com/events.ics'
                ];
                
            case 'html':
                return [
                    'selectors' => [
                        'event_container' => '.event-item',
                        'title' => '.event-title',
                        'datetime' => '.event-date',
                        'location' => '.event-location',
                        'description' => '.event-description'
                    ]
                ];
                
            case 'json':
                return [
                    'events_path' => 'data.events',
                    'field_mapping' => [
                        'title' => 'name',
                        'start_datetime' => 'start_date',
                        'end_datetime' => 'end_date',
                        'location' => 'venue.name',
                        'description' => 'details'
                    ]
                ];
                
            case 'yakima_valley':
                return [
                    'category_filter' => null // Optional category filter
                ];
                
            case 'intelligent':
                return [
                    'api_key' => '',
                    'model' => 'gpt-4o-mini',
                    'prompt_template' => null
                ];
                
            default:
                return [];
        }
    }
}