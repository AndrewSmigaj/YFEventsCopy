<?php

declare(strict_types=1);

use YakimaFinds\Infrastructure\Http\Router;
use YakimaFinds\Presentation\Api\Controllers\EventApiController;
use YakimaFinds\Presentation\Api\Controllers\ShopApiController;
use YakimaFinds\Presentation\Http\Controllers\HomeController;
use YakimaFinds\Presentation\Http\Controllers\AdminEventController;
use YakimaFinds\Presentation\Http\Controllers\AdminShopController;
use YakimaFinds\Presentation\Http\Controllers\UserController;

/**
 * API routes for the application
 */

/** @var Router $router */

// Health check
$router->get('/api/health', HomeController::class, 'health');

// Public API routes - specific routes before parameterized ones
$router->get('/api/events', EventApiController::class, 'index');
$router->get('/api/events/calendar', EventApiController::class, 'calendar');
$router->get('/api/events/featured', EventApiController::class, 'featured');
$router->get('/api/events/upcoming', EventApiController::class, 'upcoming');
$router->get('/api/events/nearby', EventApiController::class, 'nearby');
$router->get('/api/events/{id}', EventApiController::class, 'show');
$router->post('/api/events', EventApiController::class, 'store');

// Shop API routes - specific routes before parameterized ones
$router->get('/api/shops', ShopApiController::class, 'index');
$router->get('/api/shops/statistics', ShopApiController::class, 'getStatistics');
$router->get('/api/shops/featured', ShopApiController::class, 'featured');
$router->get('/api/shops/map', ShopApiController::class, 'map');
$router->get('/api/shops/nearby', ShopApiController::class, 'nearby');
$router->get('/api/shops/{id}', ShopApiController::class, 'show');
$router->get('/api/shops/categories/{category_id}', ShopApiController::class, 'byCategory');
$router->post('/api/shops', ShopApiController::class, 'store');

// Admin API routes
$router->get('/api/scrapers', AdminEventController::class, 'getScrapers');
$router->get('/api/scrapers/statistics', AdminEventController::class, 'getScraperStatistics');
$router->post('/api/scrapers/run', AdminEventController::class, 'runScraper');
$router->post('/api/scrapers/run-all', AdminEventController::class, 'runAllScrapers');
$router->post('/api/scrapers/test', AdminEventController::class, 'testScraper');
$router->post('/api/scrapers/delete', AdminEventController::class, 'deleteScraper');

// Admin Events API
$router->get('/admin/events', AdminEventController::class, 'getAllEvents');
$router->get('/admin/events/statistics', AdminEventController::class, 'getEventStatistics');
$router->post('/admin/events/create', AdminEventController::class, 'createEvent');
$router->post('/admin/events/update', AdminEventController::class, 'updateEvent');
$router->post('/admin/events/approve', AdminEventController::class, 'approveEvent');
$router->post('/admin/events/delete', AdminEventController::class, 'deleteEvent');
$router->post('/admin/events/bulk-approve', AdminEventController::class, 'bulkApproveEvents');
$router->post('/admin/events/bulk-reject', AdminEventController::class, 'bulkRejectEvents');

// Admin Scrapers
$router->post('/admin/scrapers/run', AdminEventController::class, 'runScraper');

// (Shop statistics route moved up to avoid route conflicts)

// User management routes
$router->get('/api/users', UserController::class, 'index');
$router->get('/api/users/statistics', UserController::class, 'statistics');
$router->get('/api/users/show', UserController::class, 'show');
$router->post('/api/users', UserController::class, 'store');
$router->put('/api/users', UserController::class, 'update');
$router->delete('/api/users', UserController::class, 'delete');
$router->post('/api/users/toggle-status', UserController::class, 'toggleStatus');
$router->post('/api/users/reset-password', UserController::class, 'resetPassword');
$router->get('/api/users/activity-logs', UserController::class, 'getActivityLogs');

// Role and permission management routes
$router->get('/api/roles', UserController::class, 'getRoles');
$router->get('/api/permissions', UserController::class, 'getPermissions');
$router->get('/api/users/permissions', UserController::class, 'getUserPermissions');
$router->post('/api/users/roles', UserController::class, 'updateUserRoles');
$router->get('/api/users/check-permission', UserController::class, 'checkUserPermission');

// Other admin routes
$router->get('/api/admin/events', AdminEventController::class, 'getAllEvents');
$router->get('/api/admin/shops', AdminShopController::class, 'getAllShops');
$router->get('/api/admin/shops/statistics', AdminShopController::class, 'getShopStatistics');