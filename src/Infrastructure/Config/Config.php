<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Config;

use InvalidArgumentException;
use RuntimeException;

/**
 * Configuration management class
 */
class Config implements ConfigInterface
{
    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getNestedValue($this->config, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->setNestedValue($this->config, $key, $value);
    }

    public function has(string $key): bool
    {
        return $this->hasNestedKey($this->config, $key);
    }

    public function all(): array
    {
        return $this->config;
    }

    public function loadFromFile(string $filepath): void
    {
        if (!file_exists($filepath)) {
            throw new InvalidArgumentException("Configuration file not found: {$filepath}");
        }

        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'php':
                $config = require $filepath;
                if (!is_array($config)) {
                    throw new RuntimeException("PHP configuration file must return an array");
                }
                break;

            case 'json':
                $content = file_get_contents($filepath);
                $config = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException("Invalid JSON in configuration file: " . json_last_error_msg());
                }
                break;

            default:
                throw new InvalidArgumentException("Unsupported configuration file format: {$extension}");
        }

        $this->loadFromArray($config);
    }

    public function loadFromArray(array $config): void
    {
        $this->config = array_merge_recursive($this->config, $config);
    }

    /**
     * Get nested value using dot notation
     */
    private function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        if (strpos($key, '.') === false) {
            return $array[$key] ?? $default;
        }

        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set nested value using dot notation
     */
    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        if (strpos($key, '.') === false) {
            $array[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    /**
     * Check if nested key exists using dot notation
     */
    private function hasNestedKey(array $array, string $key): bool
    {
        if (strpos($key, '.') === false) {
            return array_key_exists($key, $array);
        }

        $keys = explode('.', $key);
        $current = $array;

        foreach ($keys as $k) {
            if (!is_array($current) || !array_key_exists($k, $current)) {
                return false;
            }
            $current = $current[$k];
        }

        return true;
    }
}