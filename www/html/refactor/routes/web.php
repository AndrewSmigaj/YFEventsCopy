<?php

declare(strict_types=1);

use YakimaFinds\Infrastructure\Http\Router;
use YakimaFinds\Presentation\Http\Controllers\EventController;
use YakimaFinds\Presentation\Http\Controllers\AdminEventController;
use YakimaFinds\Presentation\Http\Controllers\ShopController;
use YakimaFinds\Presentation\Http\Controllers\AdminShopController;
use YakimaFinds\Presentation\Http\Controllers\AdminDashboardController;
use YakimaFinds\Presentation\Http\Controllers\HomeController;
use YakimaFinds\Presentation\Http\Controllers\AuthController;
use YakimaFinds\Presentation\Http\Controllers\ClaimsController;

/**
 * Web routes for the application
 */

/** @var Router $router */

// Home route
$router->get('/', HomeController::class, 'index');

// Debug route
$router->get('/debug', HomeController::class, 'debug');

// Combined map view
$router->get('/map', HomeController::class, 'showCombinedMap');

// Authentication routes
$router->get('/admin/login', AuthController::class, 'showAdminLogin');
$router->post('/admin/login', AuthController::class, 'processAdminLogin');
$router->post('/admin/logout', AuthController::class, 'adminLogout');
$router->get('/admin/status', AuthController::class, 'adminStatus');

// Public event routes (HTML pages)
$router->get('/events', EventController::class, 'showEventsPage');
$router->get('/events/featured', EventController::class, 'showFeaturedEventsPage');
$router->get('/events/upcoming', EventController::class, 'showUpcomingEventsPage');
$router->get('/events/calendar', EventController::class, 'showCalendarPage');
$router->get('/events/submit', EventController::class, 'showSubmitEventPage');

// Event API routes
$router->get('/api/events', EventController::class, 'searchEvents');
$router->get('/api/events/calendar', EventController::class, 'getCalendarEvents');
$router->get('/api/events/featured', EventController::class, 'getFeaturedEvents');
$router->get('/api/events/upcoming', EventController::class, 'getUpcomingEvents');
$router->get('/api/events/{id}', EventController::class, 'getEvent');
$router->get('/api/events/nearby', EventController::class, 'getEventsNearLocation');
$router->post('/api/events/submit', EventController::class, 'submitEvent');

// Admin event routes
$router->get('/admin/events', AdminEventController::class, 'getAllEvents');
$router->post('/admin/events/create', AdminEventController::class, 'createEvent');
$router->post('/admin/events/{id}/update', AdminEventController::class, 'updateEvent');
$router->post('/admin/events/{id}/delete', AdminEventController::class, 'deleteEvent');
$router->post('/admin/events/{id}/approve', AdminEventController::class, 'approveEvent');
$router->post('/admin/events/{id}/reject', AdminEventController::class, 'rejectEvent');
$router->post('/admin/events/bulk-approve', AdminEventController::class, 'bulkApproveEvents');
$router->post('/admin/events/bulk-reject', AdminEventController::class, 'bulkRejectEvents');
$router->get('/admin/events/statistics', AdminEventController::class, 'getEventStatistics');

// Public shop routes (HTML pages)
$router->get('/shops', ShopController::class, 'showShopsPage');
$router->get('/shops/featured', ShopController::class, 'showFeaturedShopsPage');
$router->get('/shops/map', ShopController::class, 'showShopsMapPage');
$router->get('/shops/submit', ShopController::class, 'showSubmitShopPage');
$router->get('/shops/{id}', ShopController::class, 'showShopDetailsPage');

// Shop API routes
$router->get('/api/shops', ShopController::class, 'getShops');
$router->get('/api/shops/map', ShopController::class, 'getShopsForMap');
$router->get('/api/shops/featured', ShopController::class, 'getFeaturedShops');
$router->get('/api/shops/nearby', ShopController::class, 'getShopsNearLocation');
$router->get('/api/shops/{id}', ShopController::class, 'getShop');
$router->post('/api/shops/submit', ShopController::class, 'submitShop');

// YFClaim estate sales routes
$router->get('/claims', ClaimsController::class, 'showClaimsPage');
$router->get('/claims/upcoming', ClaimsController::class, 'showUpcomingClaimsPage');
$router->get('/seller/register', ClaimsController::class, 'showSellerRegistration');
$router->get('/buyer/offers', ClaimsController::class, 'showBuyerOffers');

// Admin shop routes
$router->get('/admin/shops', AdminShopController::class, 'getAllShops');
$router->post('/admin/shops/create', AdminShopController::class, 'createShop');
$router->post('/admin/shops/{id}/update', AdminShopController::class, 'updateShop');
$router->post('/admin/shops/{id}/delete', AdminShopController::class, 'deleteShop');
$router->post('/admin/shops/{id}/approve', AdminShopController::class, 'approveShop');
$router->post('/admin/shops/{id}/reject', AdminShopController::class, 'rejectShop');
$router->post('/admin/shops/{id}/verify', AdminShopController::class, 'verifyShop');
$router->post('/admin/shops/{id}/feature', AdminShopController::class, 'featureShop');
$router->post('/admin/shops/bulk-approve', AdminShopController::class, 'bulkApproveShops');
$router->post('/admin/shops/bulk-reject', AdminShopController::class, 'bulkRejectShops');
$router->get('/admin/shops/statistics', AdminShopController::class, 'getShopStatistics');

// Admin dashboard routes
$router->get('/admin/dashboard', AdminDashboardController::class, 'getDashboard');
$router->get('/admin/dashboard/data', AdminDashboardController::class, 'getDashboardData');
$router->get('/admin/dashboard/statistics', AdminDashboardController::class, 'getStatistics');
$router->get('/admin/dashboard/health', AdminDashboardController::class, 'getSystemHealth');
$router->get('/admin/dashboard/activity', AdminDashboardController::class, 'getRecentActivity');
$router->get('/admin/dashboard/performance', AdminDashboardController::class, 'getPerformanceMetrics');
$router->get('/admin/dashboard/moderation', AdminDashboardController::class, 'getModerationQueue');
$router->get('/admin/dashboard/users', AdminDashboardController::class, 'getUserActivity');
$router->get('/admin/dashboard/top-content', AdminDashboardController::class, 'getTopContent');
$router->get('/admin/dashboard/alerts', AdminDashboardController::class, 'getSystemAlerts');
$router->get('/admin/dashboard/analytics', AdminDashboardController::class, 'getAnalytics');
$router->get('/admin/dashboard/export', AdminDashboardController::class, 'exportData');