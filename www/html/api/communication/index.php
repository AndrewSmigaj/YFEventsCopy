<?php

declare(strict_types=1);

/**
 * Communication API Router
 * 
 * This file handles all API requests for the Communication module
 */

// Bootstrap the application
require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../config/bootstrap.php';

use YFEvents\Infrastructure\Container\Container;
use YFEvents\Presentation\Api\Controllers\Communication\ChannelApiController;
use YFEvents\Presentation\Api\Controllers\Communication\MessageApiController;
use YFEvents\Presentation\Api\Controllers\Communication\AnnouncementApiController;
use YFEvents\Presentation\Api\Controllers\Communication\NotificationApiController;

// Get the container (this assumes you have a global container or factory)
$container = Container::getInstance();

// Parse the request
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';
$path = trim($path, '/');
$segments = explode('/', $path);

// Enable CORS for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Route the request
try {
    switch ($segments[0] ?? '') {
        case 'channels':
            $controller = $container->resolve(ChannelApiController::class);
            
            if (!isset($segments[1])) {
                // /channels
                if ($method === 'GET') {
                    $controller->index();
                } elseif ($method === 'POST') {
                    $controller->store();
                }
            } else {
                $channelId = (int)$segments[1];
                
                if (!isset($segments[2])) {
                    // /channels/{id}
                    if ($method === 'GET') {
                        $controller->show($channelId);
                    } elseif ($method === 'PUT') {
                        $controller->update($channelId);
                    } elseif ($method === 'DELETE') {
                        $controller->delete($channelId);
                    }
                } else {
                    // /channels/{id}/...
                    switch ($segments[2]) {
                        case 'join':
                            if ($method === 'POST') {
                                $controller->join($channelId);
                            }
                            break;
                            
                        case 'leave':
                            if ($method === 'DELETE') {
                                $controller->leave($channelId);
                            }
                            break;
                            
                        case 'messages':
                            $messageController = $container->resolve(MessageApiController::class);
                            
                            if (!isset($segments[3])) {
                                // /channels/{id}/messages
                                if ($method === 'GET') {
                                    $messageController->index($channelId);
                                } elseif ($method === 'POST') {
                                    $messageController->store($channelId);
                                }
                            } elseif ($segments[3] === 'search') {
                                // /channels/{id}/messages/search
                                if ($method === 'GET') {
                                    $messageController->search($channelId);
                                }
                            }
                            break;
                            
                        case 'read':
                            if ($method === 'POST') {
                                // This method needs to be added to ChannelApiController
                                http_response_code(501);
                                echo json_encode(['error' => 'Not implemented']);
                                exit;
                            }
                            break;
                    }
                }
            }
            break;
            
        case 'messages':
            $controller = $container->resolve(MessageApiController::class);
            
            if (isset($segments[1])) {
                $messageId = (int)$segments[1];
                
                if (!isset($segments[2])) {
                    // /messages/{id}
                    if ($method === 'PUT') {
                        $controller->update($messageId);
                    } elseif ($method === 'DELETE') {
                        $controller->delete($messageId);
                    }
                } elseif ($segments[2] === 'pin') {
                    // /messages/{id}/pin
                    if ($method === 'POST') {
                        $controller->pin($messageId);
                    } elseif ($method === 'DELETE') {
                        $controller->unpin($messageId);
                    }
                }
            }
            break;
            
        case 'announcements':
            $controller = $container->resolve(AnnouncementApiController::class);
            
            if (!isset($segments[1])) {
                // /announcements
                if ($method === 'GET') {
                    $controller->index();
                } elseif ($method === 'POST') {
                    $controller->create();
                }
            } elseif (isset($segments[2]) && $segments[2] === 'stats') {
                // /announcements/{id}/stats
                $announcementId = (int)$segments[1];
                if ($method === 'GET') {
                    $controller->stats($announcementId);
                }
            }
            break;
            
        case 'notifications':
            $controller = $container->resolve(NotificationApiController::class);
            
            if (!isset($segments[1])) {
                // /notifications
                if ($method === 'GET') {
                    $controller->index();
                }
            } else {
                switch ($segments[1]) {
                    case 'read':
                        if ($method === 'PUT') {
                            $controller->markRead();
                        }
                        break;
                        
                    case 'count':
                        if ($method === 'GET') {
                            $controller->count();
                        }
                        break;
                        
                    case 'preferences':
                        if ($method === 'PUT') {
                            $controller->updatePreferences();
                        }
                        break;
                }
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            exit;
    }
    
} catch (\RuntimeException $e) {
    // Handle authentication errors
    if ($e->getMessage() === 'Not authenticated') {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
} catch (\Exception $e) {
    // Handle other errors
    error_log('Communication API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}