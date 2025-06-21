<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Api\Controllers\Communication;

use YFEvents\Application\Services\Communication\CommunicationService;

/**
 * Notification API controller
 */
class NotificationApiController
{
    private CommunicationService $communicationService;
    
    public function __construct(CommunicationService $communicationService)
    {
        $this->communicationService = $communicationService;
    }
    
    /**
     * Get user notifications
     */
    public function index(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = ($page - 1) * $limit;
            
            // This would need to be implemented in the service/repository
            $notifications = $this->getNotifications($userId, $unreadOnly, $limit, $offset);
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => $notifications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Mark notifications as read
     */
    public function markRead(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $data = $this->getJsonInput();
            
            if (empty($data['notification_ids']) || !is_array($data['notification_ids'])) {
                $this->sendErrorResponse('notification_ids array is required');
                return;
            }
            
            // This would need to be implemented
            $count = $this->markNotificationsAsRead($userId, $data['notification_ids']);
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => sprintf('%d notifications marked as read', $count)
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Update notification preferences
     */
    public function updatePreferences(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $data = $this->getJsonInput();
            
            // Validate preferences
            $validPreferences = ['all', 'mentions', 'none'];
            $validFrequencies = ['real-time', 'daily', 'weekly', 'none'];
            
            if (isset($data['notification_preference']) && 
                !in_array($data['notification_preference'], $validPreferences)) {
                $this->sendErrorResponse('Invalid notification preference');
                return;
            }
            
            if (isset($data['email_digest_frequency']) && 
                !in_array($data['email_digest_frequency'], $validFrequencies)) {
                $this->sendErrorResponse('Invalid email digest frequency');
                return;
            }
            
            // This would need to be implemented
            $this->updateUserPreferences($userId, $data);
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Preferences updated successfully'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Get notification count
     */
    public function count(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // This would need to be implemented
            $unreadCount = $this->getUnreadNotificationCount($userId);
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => [
                    'unread_count' => $unreadCount
                ]
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
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
     * Get current user ID from session
     */
    private function getCurrentUserId(): int
    {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            throw new \RuntimeException('Not authenticated');
        }
        
        return (int)$_SESSION['user_id'];
    }
    
    /**
     * Get JSON input from request
     */
    private function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON input');
        }
        
        return $data ?? [];
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Send error response
     */
    private function sendErrorResponse(string $message, int $statusCode = 400): void
    {
        $this->sendJsonResponse([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
}