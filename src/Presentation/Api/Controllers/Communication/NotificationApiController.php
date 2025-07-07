<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Api\Controllers\Communication;

use YFEvents\Presentation\Http\Controllers\BaseController;
use YFEvents\Application\Services\Communication\CommunicationService;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Notification API controller
 */
class NotificationApiController extends BaseController
{
    private CommunicationService $communicationService;
    
    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->communicationService = $container->resolve(CommunicationService::class);
        
        // Set CORS headers for API
        $this->setCorsHeaders();
    }
    
    /**
     * Get user notifications
     */
    public function index(): void
    {
        try {
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            $input = $this->getInput();
            $unreadOnly = isset($input['unread_only']) && $input['unread_only'] === 'true';
            $page = (int) ($input['page'] ?? 1);
            $limit = min(50, max(1, (int) ($input['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            // This would need to be implemented in the service/repository
            $notifications = $this->getNotifications($userId, $unreadOnly, $limit, $offset);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $notifications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Mark notifications as read
     */
    public function markRead(): void
    {
        try {
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            $data = $this->getInput();
            
            if (empty($data['notification_ids']) || !is_array($data['notification_ids'])) {
                $this->errorResponse('notification_ids array is required');
                return;
            }
            
            // This would need to be implemented
            $count = $this->markNotificationsAsRead($userId, $data['notification_ids']);
            
            $this->jsonResponse([
                'success' => true,
                'message' => sprintf('%d notifications marked as read', $count)
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Update notification preferences
     */
    public function updatePreferences(): void
    {
        try {
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            $data = $this->getInput();
            
            // Validate preferences
            $validPreferences = ['all', 'mentions', 'none'];
            $validFrequencies = ['real-time', 'daily', 'weekly', 'none'];
            
            if (isset($data['notification_preference']) && 
                !in_array($data['notification_preference'], $validPreferences)) {
                $this->errorResponse('Invalid notification preference');
                return;
            }
            
            if (isset($data['email_digest_frequency']) && 
                !in_array($data['email_digest_frequency'], $validFrequencies)) {
                $this->errorResponse('Invalid email digest frequency');
                return;
            }
            
            // This would need to be implemented
            $this->updateUserPreferences($userId, $data);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Preferences updated successfully'
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Get notification count
     */
    public function count(): void
    {
        try {
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            
            // This would need to be implemented
            $unreadCount = $this->getUnreadNotificationCount($userId);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'unread_count' => $unreadCount
                ]
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Get notifications (placeholder - would be implemented with NotificationRepository)
     */
    private function getNotifications(int $userId, bool $unreadOnly, int $limit, int $offset): array
    {
        // Placeholder implementation
        return [
            [
                'id' => 1,
                'type' => 'mention',
                'title' => 'You were mentioned in General Discussion',
                'content' => '@johndoe can you help with this?',
                'channel' => [
                    'id' => 1,
                    'name' => 'General Discussion'
                ],
                'message_id' => 123,
                'is_read' => false,
                'created_at' => date('c')
            ]
        ];
    }
    
    /**
     * Mark notifications as read (placeholder)
     */
    private function markNotificationsAsRead(int $userId, array $notificationIds): int
    {
        // Placeholder implementation
        // Would verify ownership and update database
        return count($notificationIds);
    }
    
    /**
     * Update user preferences (placeholder)
     */
    private function updateUserPreferences(int $userId, array $preferences): void
    {
        // Placeholder implementation
        // Would update participant records for all user's channels
    }
    
    /**
     * Get unread notification count (placeholder)
     */
    private function getUnreadNotificationCount(int $userId): int
    {
        // Placeholder implementation
        return 3;
    }
    
    /**
     * Set CORS headers for API access
     */
    private function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}