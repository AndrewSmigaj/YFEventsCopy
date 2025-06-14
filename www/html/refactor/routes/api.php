<?php

declare(strict_types=1);

use YakimaFinds\Infrastructure\Http\Router;
use YakimaFinds\Presentation\Api\Controllers\EventApiController;
use YakimaFinds\Presentation\Api\Controllers\ShopApiController;
use YakimaFinds\Presentation\Http\Controllers\HomeController;
use YakimaFinds\Presentation\Http\Controllers\AdminEventController;
use YakimaFinds\Presentation\Http\Controllers\AdminShopController;

/**
 * API routes for the application
 */

/** @var Router $router */

// Health check
$router->get('/api/health', HomeController::class, 'health');

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

// Admin API routes
$router->get('/api/scrapers', AdminEventController::class, 'getScrapers');
$router->post('/api/scrapers/run', AdminEventController::class, 'runScraper');
$router->post('/api/scrapers/run-all', AdminEventController::class, 'runAllScrapers');
$router->get('/api/admin/events', AdminEventController::class, 'getAllEvents');
$router->get('/api/admin/shops', AdminShopController::class, 'getAllShops');