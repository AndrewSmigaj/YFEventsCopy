<?php

declare(strict_types=1);

/**
 * Core service registrations
 * 
 * Register core application services that are used throughout the system
 */

use YFEvents\Infrastructure\Container\Container;
use YFEvents\Infrastructure\Database\ConnectionInterface;

// Register repositories
$container->bind(\YFEvents\Domain\Events\EventRepositoryInterface::class, function($container) {
    return new \YFEvents\Infrastructure\Repositories\EventRepository(
        $container->resolve(ConnectionInterface::class)
    );
});

$container->bind(\YFEvents\Domain\Shops\ShopRepositoryInterface::class, function($container) {
    return new \YFEvents\Infrastructure\Repositories\ShopRepository(
        $container->resolve(ConnectionInterface::class)
    );
});

// Register services
$container->bind(\YFEvents\Domain\Events\EventServiceInterface::class, function($container) {
    return new \YFEvents\Domain\Events\EventService(
        $container->resolve(\YFEvents\Domain\Events\EventRepositoryInterface::class),
        $container->resolve(ConnectionInterface::class)
    );
});

$container->bind(\YFEvents\Domain\Shops\ShopServiceInterface::class, function($container) {
    return new \YFEvents\Domain\Shops\ShopService(
        $container->resolve(\YFEvents\Domain\Shops\ShopRepositoryInterface::class),
        $container->resolve(ConnectionInterface::class)
    );
});

$container->bind(\YFEvents\Domain\Admin\AdminServiceInterface::class, function($container) {
    return new \YFEvents\Domain\Admin\AdminService(
        $container->resolve(ConnectionInterface::class)
    );
});