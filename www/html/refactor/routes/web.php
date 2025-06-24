<?php

declare(strict_types=1);

use YFEvents\Infrastructure\Http\Router;
use YFEvents\Presentation\Http\Controllers\EventController;
use YFEvents\Presentation\Http\Controllers\AdminEventController;
use YFEvents\Presentation\Http\Controllers\ShopController;
use YFEvents\Presentation\Http\Controllers\AdminShopController;
use YFEvents\Presentation\Http\Controllers\AdminDashboardController;
use YFEvents\Presentation\Http\Controllers\AdminUsersController;
use YFEvents\Presentation\Http\Controllers\AdminEmailController;
use YFEvents\Presentation\Http\Controllers\AdminThemeController;
use YFEvents\Presentation\Http\Controllers\AdminScraperController;
use YFEvents\Presentation\Http\Controllers\AdminClaimsController;
use YFEvents\Presentation\Http\Controllers\AdminSettingsController;
use YFEvents\Presentation\Http\Controllers\AdminModulesController;
use YFEvents\Presentation\Http\Controllers\AdminCommunicationController;
use YFEvents\Presentation\Http\Controllers\HomeController;
use YFEvents\Presentation\Http\Controllers\AuthController;
use YFEvents\Presentation\Http\Controllers\ClaimsController;
use YFEvents\Presentation\Http\Controllers\ClassifiedsController;

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

// Admin static pages will be handled by Apache directly, not through router

// Public event routes (HTML pages)
$router->get('/events', EventController::class, 'showEventsPage');
$router->get('/events/featured', EventController::class, 'showFeaturedEventsPage');
$router->get('/events/upcoming', EventController::class, 'showUpcomingEventsPage');
$router->get('/events/calendar', EventController::class, 'showCalendarPage');
$router->get('/events/submit', EventController::class, 'showSubmitEventPage');
$router->get('/events/{id}', EventController::class, 'showEventDetailPage');

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

// YFClaim estate sales routes (public)
$router->get('/claims', ClaimsController::class, 'showClaimsPage');
$router->get('/claims/upcoming', ClaimsController::class, 'showUpcomingClaimsPage');
$router->get('/claims/sale', ClaimsController::class, 'showSale');
$router->get('/claims/item/{id}', ClaimsController::class, 'showItem');

// YFClaim seller routes
$router->get('/seller/register', ClaimsController::class, 'showSellerRegistration');
$router->post('/seller/register', ClaimsController::class, 'processSellerRegistration');
$router->get('/seller/login', ClaimsController::class, 'showSellerLogin');
$router->post('/seller/login', ClaimsController::class, 'processSellerLogin');
$router->get('/seller/dashboard', ClaimsController::class, 'showSellerDashboard');
$router->get('/seller/sale/new', ClaimsController::class, 'showCreateSale');
$router->post('/seller/sale/create', ClaimsController::class, 'createSale');
$router->get('/seller/sale/{id}/edit', ClaimsController::class, 'showEditSale');
$router->post('/seller/sale/{id}/update', ClaimsController::class, 'updateSale');
$router->get('/seller/sale/{id}/items', ClaimsController::class, 'manageSaleItems');
$router->post('/seller/logout', ClaimsController::class, 'sellerLogout');

// YFClaim buyer routes
$router->get('/buyer/auth', ClaimsController::class, 'showBuyerAuth');
$router->post('/buyer/auth/send', ClaimsController::class, 'sendBuyerAuthCode');
$router->post('/buyer/auth/verify', ClaimsController::class, 'verifyBuyerAuthCode');
$router->get('/buyer/offers', ClaimsController::class, 'showBuyerOffers');
$router->post('/buyer/logout', ClaimsController::class, 'buyerLogout');

// YFClaim API routes
$router->post('/api/claims/offer', ClaimsController::class, 'submitOffer');
$router->get('/api/claims/offers/{buyerId}', ClaimsController::class, 'getBuyerOffers');
$router->post('/api/claims/item/{id}/claim', ClaimsController::class, 'claimItem');
$router->get('/api/claims/sale/{id}/items', ClaimsController::class, 'getSaleItemsApi');
$router->post('/api/claims/seller/items/add', ClaimsController::class, 'addSaleItem');
$router->post('/api/claims/seller/items/{id}/update', ClaimsController::class, 'updateSaleItem');
$router->post('/api/claims/seller/items/{id}/delete', ClaimsController::class, 'deleteSaleItem');

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

// Admin user management routes
$router->get('/admin/users', AdminUsersController::class, 'index');
$router->get('/admin/users/list', AdminUsersController::class, 'getUsers');
$router->get('/admin/users/{id}', AdminUsersController::class, 'getUser');
$router->post('/admin/users/create', AdminUsersController::class, 'createUser');
$router->post('/admin/users/{id}/update', AdminUsersController::class, 'updateUser');
$router->post('/admin/users/{id}/delete', AdminUsersController::class, 'deleteUser');
$router->post('/admin/users/{id}/reset-password', AdminUsersController::class, 'resetPassword');
$router->get('/admin/users/statistics', AdminUsersController::class, 'getUserStatistics');

// Admin email management routes
$router->get('/admin/email-events', AdminEmailController::class, 'showEmailEvents');
$router->get('/admin/email-config', AdminEmailController::class, 'showEmailConfig');
$router->post('/admin/email/process', AdminEmailController::class, 'processEmails');
$router->get('/admin/email/history', AdminEmailController::class, 'getEmailHistory');
$router->get('/admin/email/config', AdminEmailController::class, 'getEmailConfig');
$router->post('/admin/email/config', AdminEmailController::class, 'updateEmailConfig');
$router->post('/admin/email/test-connection', AdminEmailController::class, 'testConnection');
$router->post('/admin/email/upload', AdminEmailController::class, 'uploadEmail');

// Admin theme management routes
$router->get('/admin/theme', AdminThemeController::class, 'index');
$router->get('/admin/theme/settings', AdminThemeController::class, 'getSettings');
$router->post('/admin/theme/settings/{id}', AdminThemeController::class, 'updateSetting');
$router->post('/admin/theme/settings/bulk', AdminThemeController::class, 'updateBulkSettings');
$router->post('/admin/theme/generate-css', AdminThemeController::class, 'generateCSS');
$router->post('/admin/theme/apply-preset', AdminThemeController::class, 'applyPreset');

// Admin scraper management routes
$router->get('/admin/scrapers', AdminScraperController::class, 'index');
$router->get('/admin/scrapers/list', AdminScraperController::class, 'getScrapers');
$router->get('/admin/scrapers/{id}', AdminScraperController::class, 'getScraper');
$router->post('/admin/scrapers/create', AdminScraperController::class, 'createScraper');
$router->post('/admin/scrapers/{id}/update', AdminScraperController::class, 'updateScraper');
$router->post('/admin/scrapers/{id}/delete', AdminScraperController::class, 'deleteScraper');
$router->post('/admin/scrapers/{id}/run', AdminScraperController::class, 'runScraper');
$router->post('/admin/scrapers/test', AdminScraperController::class, 'testScraper');
$router->get('/admin/scrapers/statistics', AdminScraperController::class, 'getStatistics');

// Admin claims management routes
$router->get('/admin/claims', AdminClaimsController::class, 'index');
$router->get('/admin/claims/statistics', AdminClaimsController::class, 'getStatistics');
$router->get('/admin/claims/sales', AdminClaimsController::class, 'getSales');
$router->get('/admin/claims/sales/{id}', AdminClaimsController::class, 'getSaleDetails');
$router->post('/admin/claims/sales/{id}/update', AdminClaimsController::class, 'updateSale');
$router->post('/admin/claims/sales/{id}/delete', AdminClaimsController::class, 'deleteSale');
$router->get('/admin/claims/offers', AdminClaimsController::class, 'getOffers');
$router->post('/admin/claims/offers/{id}/update', AdminClaimsController::class, 'updateOffer');
$router->get('/admin/claims/sellers', AdminClaimsController::class, 'getSellers');
$router->post('/admin/claims/sellers/{id}/toggle', AdminClaimsController::class, 'toggleSellerStatus');

// Admin settings management routes
$router->get('/admin/settings', AdminSettingsController::class, 'index');
$router->get('/admin/settings/get', AdminSettingsController::class, 'getSettings');
$router->post('/admin/settings/update', AdminSettingsController::class, 'updateSetting');
$router->post('/admin/settings/bulk', AdminSettingsController::class, 'updateBulkSettings');
$router->get('/admin/settings/system-info', AdminSettingsController::class, 'getSystemInfo');
$router->post('/admin/settings/clear-cache', AdminSettingsController::class, 'clearCache');
$router->get('/admin/settings/export', AdminSettingsController::class, 'exportSettings');
$router->post('/admin/settings/import', AdminSettingsController::class, 'importSettings');

// Admin module management routes
$router->get('/admin/modules', AdminModulesController::class, 'index');
$router->get('/admin/modules/list', AdminModulesController::class, 'getModules');
$router->get('/admin/modules/details', AdminModulesController::class, 'getModule');
$router->post('/admin/modules/enable', AdminModulesController::class, 'enableModule');
$router->post('/admin/modules/disable', AdminModulesController::class, 'disableModule');
$router->post('/admin/modules/install-db', AdminModulesController::class, 'installDatabase');
$router->post('/admin/modules/uninstall-db', AdminModulesController::class, 'uninstallDatabase');
$router->post('/admin/modules/configure', AdminModulesController::class, 'updateConfiguration');
$router->get('/admin/modules/statistics', AdminModulesController::class, 'getStatistics');

// Admin communication management routes
$router->get('/admin/communication', AdminCommunicationController::class, 'index');
$router->get('/admin/communication/statistics', AdminCommunicationController::class, 'getStatistics');
$router->get('/admin/communication/channels', AdminCommunicationController::class, 'getChannels');
$router->post('/admin/communication/channels', AdminCommunicationController::class, 'createChannel');
$router->post('/admin/communication/channels/{id}', AdminCommunicationController::class, 'updateChannel');
$router->delete('/admin/communication/channels/{id}', AdminCommunicationController::class, 'deleteChannel');
$router->get('/admin/communication/participants', AdminCommunicationController::class, 'getParticipants');
$router->post('/admin/communication/participants', AdminCommunicationController::class, 'addParticipant');
$router->delete('/admin/communication/participants', AdminCommunicationController::class, 'removeParticipant');
$router->get('/admin/communication/messages', AdminCommunicationController::class, 'getMessages');
$router->delete('/admin/communication/messages/{id}', AdminCommunicationController::class, 'deleteMessage');
$router->post('/admin/communication/messages/{id}/flag', AdminCommunicationController::class, 'toggleMessageFlag');
$router->get('/admin/communication/announcements', AdminCommunicationController::class, 'getAnnouncements');
$router->post('/admin/communication/announcements', AdminCommunicationController::class, 'createAnnouncement');
$router->post('/admin/communication/announcements/{id}', AdminCommunicationController::class, 'updateAnnouncement');
$router->delete('/admin/communication/announcements/{id}', AdminCommunicationController::class, 'deleteAnnouncement');
$router->get('/admin/communication/notifications', AdminCommunicationController::class, 'getNotificationSettings');

// Classifieds routes (module-based)
$router->get('/classifieds', ClassifiedsController::class, 'showClassifiedsPage');
$router->get('/classifieds/item/{id}', ClassifiedsController::class, 'showItemPage');
$router->get('/classifieds/category/{slug}', ClassifiedsController::class, 'showCategoryPage');