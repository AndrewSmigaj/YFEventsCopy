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

$container->bind(\YFEvents\Domain\Users\UserRepositoryInterface::class, function($container) {
    return new \YFEvents\Infrastructure\Repositories\UserRepository(
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
        $container->resolve(\YFEvents\Domain\Events\EventServiceInterface::class),
        $container->resolve(\YFEvents\Domain\Shops\ShopServiceInterface::class),
        $container->resolve(ConnectionInterface::class)
    );
});

$container->bind(\YFEvents\Application\Services\UserService::class, function($container) {
    return new \YFEvents\Application\Services\UserService(
        $container->resolve(\YFEvents\Domain\Users\UserRepositoryInterface::class),
        $container->resolve(ConnectionInterface::class)
    );
});

$container->bind(\YFEvents\Infrastructure\Services\EmailService::class, function($container) {
    return new \YFEvents\Infrastructure\Services\EmailService(
        $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class)
    );
});

$container->bind(\YFEvents\Infrastructure\Services\EmailEventProcessor::class, function($container) {
    return new \YFEvents\Infrastructure\Services\EmailEventProcessor(
        $container->resolve(ConnectionInterface::class),
        $container->resolve(\YFEvents\Infrastructure\Config\ConfigInterface::class)
    );
});

// Register YFClaim repositories
$container->bind(\YFEvents\Domain\Claims\SellerRepositoryInterface::class, function($container) {
    return new \YFEvents\Infrastructure\Repositories\Claims\SellerRepository(
        $container->resolve(ConnectionInterface::class)
    );
});

$container->bind(\YFEvents\Domain\Claims\SaleRepositoryInterface::class, function($container) {
    return new \YFEvents\Infrastructure\Repositories\Claims\SaleRepository(
        $container->resolve(ConnectionInterface::class)
    );
});

$container->bind(\YFEvents\Domain\Claims\ItemRepositoryInterface::class, function($container) {
    return new \YFEvents\Infrastructure\Repositories\Claims\ItemRepository(
        $container->resolve(ConnectionInterface::class)
    );
});

$container->bind(\YFEvents\Domain\Claims\OfferRepositoryInterface::class, function($container) {
    return new \YFEvents\Infrastructure\Repositories\Claims\OfferRepository(
        $container->resolve(ConnectionInterface::class)
    );
});

$container->bind(\YFEvents\Domain\Claims\BuyerRepositoryInterface::class, function($container) {
    return new \YFEvents\Infrastructure\Repositories\Claims\BuyerRepository(
        $container->resolve(ConnectionInterface::class)
    );
});

// Register utility services
$container->bind(\YFEvents\Utils\SystemSettings::class, function($container) {
    return new \YFEvents\Utils\SystemSettings(
        $container->resolve(ConnectionInterface::class)
    );
});

$container->bind(\YFEvents\Infrastructure\Services\PermissionService::class, function($container) {
    return new \YFEvents\Infrastructure\Services\PermissionService(
        $container->resolve(ConnectionInterface::class)->getConnection()
    );
});