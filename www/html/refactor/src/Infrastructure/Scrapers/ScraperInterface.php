<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Scrapers;

/**
 * Scraper Interface
 * 
 * Common interface for all event scrapers
 */
interface ScraperInterface
{
    /**
     * Scrape events for a specific location
     * 
     * @param string $location Location to scrape
     * @param array $options Additional scraping options
     * @return array Array of Event objects
     */
    public function scrapeLocation(string $location, array $options = []): array;
}