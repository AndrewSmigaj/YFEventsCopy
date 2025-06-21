<?php

declare(strict_types=1);

/**
 * Communication API routes
 * 
 * Add these routes to your main API routing configuration
 */

use YFEvents\Presentation\Api\Controllers\Communication\ChannelApiController;
use YFEvents\Presentation\Api\Controllers\Communication\MessageApiController;
use YFEvents\Presentation\Api\Controllers\Communication\AnnouncementApiController;
use YFEvents\Presentation\Api\Controllers\Communication\NotificationApiController;

// The router variable should be provided by your main routing file
// Example: $router = new YakimaFinds\Infrastructure\Http\Router();

// Channel management routes
$router->group('/api/communication', function($router) {
    
    // Channel endpoints
    $router->get('/channels', [ChannelApiController::class, 'index']);
    $router->post('/channels', [ChannelApiController::class, 'store']);
    $router->get('/channels/{id}', [ChannelApiController::class, 'show']);
    $router->put('/channels/{id}', [ChannelApiController::class, 'update']);
    $router->delete('/channels/{id}', [ChannelApiController::class, 'delete']);
    
    // Channel participation
    $router->post('/channels/{id}/join', [ChannelApiController::class, 'join']);
    $router->delete('/channels/{id}/leave', [ChannelApiController::class, 'leave']);
    
    // Channel messages
    $router->get('/channels/{id}/messages', [MessageApiController::class, 'index']);
    $router->post('/channels/{id}/messages', [MessageApiController::class, 'store']);
    $router->get('/channels/{id}/messages/search', [MessageApiController::class, 'search']);
    $router->post('/channels/{id}/read', [ChannelApiController::class, 'markAsRead']);
    
    // Message management
    $router->put('/messages/{id}', [MessageApiController::class, 'update']);
    $router->delete('/messages/{id}', [MessageApiController::class, 'delete']);
    $router->post('/messages/{id}/pin', [MessageApiController::class, 'pin']);
    $router->delete('/messages/{id}/pin', [MessageApiController::class, 'unpin']);
    
    // Announcements
    $router->get('/announcements', [AnnouncementApiController::class, 'index']);
    $router->post('/announcements', [AnnouncementApiController::class, 'create']);
    $router->get('/announcements/{id}/stats', [AnnouncementApiController::class, 'stats']);
    
    // Notifications
    $router->get('/notifications', [NotificationApiController::class, 'index']);
    $router->put('/notifications/read', [NotificationApiController::class, 'markRead']);
    $router->get('/notifications/count', [NotificationApiController::class, 'count']);
    $router->put('/notifications/preferences', [NotificationApiController::class, 'updatePreferences']);
});

// Alternative route definitions if using a different router
// These can be adapted to your specific routing implementation

/*
// Laravel-style routes
Route::prefix('api/communication')->group(function () {
    Route::get('channels', [ChannelApiController::class, 'index']);
    Route::post('channels', [ChannelApiController::class, 'store']);
    // ... etc
});

// Slim Framework style
$app->group('/api/communication', function (RouteCollectorProxy $group) {
    $group->get('/channels', ChannelApiController::class . ':index');
    $group->post('/channels', ChannelApiController::class . ':store');
    // ... etc
});

// FastRoute style
$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addGroup('/api/communication', function (FastRoute\RouteCollector $r) {
        $r->get('/channels', [ChannelApiController::class, 'index']);
        $r->post('/channels', [ChannelApiController::class, 'store']);
        // ... etc
    });
});
*/