<?php

declare(strict_types=1);

namespace YFEvents\Application;

use YFEvents\Infrastructure\Container\Container;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Providers\ServiceProvider;

/**
 * Application bootstrap class
 */
class Bootstrap
{
    private static ?ContainerInterface $container = null;

    /**
     * Bootstrap the application
     */
    public static function boot(): ContainerInterface
    {
        if (self::$container === null) {
            self::$container = new Container();
            
            // Register services
            $serviceProvider = new ServiceProvider(self::$container);
            $serviceProvider->register();
        }

        return self::$container;
    }

    /**
     * Get the container instance
     */
    public static function getContainer(): ?ContainerInterface
    {
        return self::$container;
    }

    /**
     * Reset the application (useful for testing)
     */
    public static function reset(): void
    {
        self::$container = null;
    }
}