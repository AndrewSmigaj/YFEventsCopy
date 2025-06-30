<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Scrapers;

use YakimaFinds\Domain\Scrapers\ScrapingResult;
use YakimaFinds\Domain\Scrapers\ScrapingSource;

interface ScraperInterface
{
    /**
     * Scrape events from a source
     */
    public function scrape(ScrapingSource $source): ScrapingResult;

    /**
     * Test if scraper can handle this source type
     */
    public function canHandle(ScrapingSource $source): bool;

    /**
     * Get scraper name for identification
     */
    public function getName(): string;

    /**
     * Get scraper version for tracking
     */
    public function getVersion(): string;

    /**
     * Validate source configuration
     */
    public function validateConfiguration(array $config): array;

    /**
     * Get configuration schema
     */
    public function getConfigurationSchema(): array;

    /**
     * Test source connectivity without full scrape
     */
    public function testSource(ScrapingSource $source): bool;
}