<?php

declare(strict_types=1);

use YakimaFinds\Infrastructure\Http\Router;
use YakimaFinds\Presentation\Api\Controllers\EventApiController;
use YakimaFinds\Presentation\Api\Controllers\ShopApiController;

/**
 * API routes for the application
 */

/** @var Router $router */

// Public API routes
$router->get('/api/events', EventApiController::class, 'index');
$router->get('/api/events/{id}', EventApiController::class, 'show');
$router->get('/api/events/calendar', EventApiController::class, 'calendar');
$router->get('/api/events/featured', EventApiController::class, 'featured');
$router->get('/api/events/upcoming', EventApiController::class, 'upcoming');
$router->get('/api/events/nearby', EventApiController::class, 'nearby');
$router->post('/api/events', EventApiController::class, 'store');

// Shop API routes
$router->get('/api/shops', ShopApiController::class, 'index');
$router->get('/api/shops/{id}', ShopApiController::class, 'show');
$router->get('/api/shops/featured', ShopApiController::class, 'featured');
$router->get('/api/shops/map', ShopApiController::class, 'map');
$router->get('/api/shops/nearby', ShopApiController::class, 'nearby');
$router->get('/api/shops/categories/{category_id}', ShopApiController::class, 'byCategory');
$router->post('/api/shops', ShopApiController::class, 'store');