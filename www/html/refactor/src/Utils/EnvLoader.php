<?php

declare(strict_types=1);

namespace YakimaFinds\Utils;

class EnvLoader
{
    private static bool $loaded = false;

    public static function load(string $envFile = null): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = $envFile ?: dirname(__DIR__, 2) . '/.env';
        
        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
            
            if (!array_key_exists($name, $_SERVER)) {
                $_SERVER[$name] = $value;
            }
            
            putenv(sprintf('%s=%s', $name, $value));
        }
        
        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        self::load();
        
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
}