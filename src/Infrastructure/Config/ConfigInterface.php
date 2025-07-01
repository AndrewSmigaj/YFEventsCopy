<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Config;

/**
 * Configuration interface
 */
interface ConfigInterface
{
    /**
     * Get configuration value by key
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set configuration value
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool;

    /**
     * Get all configuration values
     */
    public function all(): array;

    /**
     * Load configuration from file
     */
    public function loadFromFile(string $filepath): void;

    /**
     * Load configuration from array
     */
    public function loadFromArray(array $config): void;
}