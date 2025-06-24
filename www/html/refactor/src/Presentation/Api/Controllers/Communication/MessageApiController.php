<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Api\Controllers\Communication;

use YFEvents\Application\Services\Communication\CommunicationService;

/**
 * Message API controller
 */
class MessageApiController
{
    private CommunicationService $communicationService;
    
    public function __construct(CommunicationService $communicationService)
    {
        $this->communicationService = $communicationService;
        if (session_status() === PHP_SESSION_NONE) {
            // Try to start session, but suppress warnings if already started or permission issues
            @session_start();
        }
    }
    
    /**
     * Get current user ID from session
     */
    private function getCurrentUserId(): int
    {
        if (!isset($_SESSION['user_id'])) {
            $this->sendErrorResponse('Authentication required', 401);
            exit;
        }
        return (int)$_SESSION['user_id'];
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
     * Send JSON error response
     */
    private function sendErrorResponse(string $message, int $statusCode = 400): void
    {
        $this->sendJsonResponse([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
    
    /**
     * Send JSON error response (alias for compatibility)
     */
    private function sendJsonError(string $message, int $statusCode = 400): void
    {
        $this->sendErrorResponse($message, $statusCode);
    }
    
    /**
     * Get JSON input
     */
    private function getJsonInput(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return $data ?? [];
    }
    
    /**
     * Get messages for a channel
     */
    public function index(): void
    {
        try {
            $channelId = (int)($_GET['channelId'] ?? 0);
            if (!$channelId) {
                $this->sendErrorResponse('Channel ID required', 400);
                return;
            }
            
            $userId = $this->getCurrentUserId();
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            
            $result = $this->communicationService->getChannelMessages($channelId, $userId, $limit, $offset);
            
            $messages = [];
            foreach ($result['messages'] as $message) {
                $messages[] = $this->formatMessage($message);
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => $messages,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $result['total_count'],
                    'has_more' => $result['has_more']
                ],
                'unread_count' => $result['unread_count']
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Send a message to a channel
     */
    public function store(): void
    {
        try {
            $channelId = (int)($_GET['channelId'] ?? 0);
            if (!$channelId) {
                $this->sendErrorResponse('Channel ID required', 400);
                return;
            }
            
            $userId = $this->getCurrentUserId();
            $data = $this->getJsonInput();
            
            $messageData = [
                'channel_id' => $channelId,
                'user_id' => $userId,
                'content' => $data['content'] ?? '',
                'parent_message_id' => $data['parent_message_id'] ?? null,
                'yfclaim_item_id' => $data['yfclaim_item_id'] ?? null
            ];
            
            // Handle attachments if provided
            if (!empty($data['attachments'])) {
                $messageData['attachments'] = $data['attachments'];
            }
            
            // Handle location data for picks
            $metadata = [];
            if (!empty($data['location_name'])) {
                $metadata['location_name'] = $data['location_name'];
            }
            if (!empty($data['location_address'])) {
                $metadata['location_address'] = $data['location_address'];
            }
            if (isset($data['location_latitude'])) {
                $metadata['location_latitude'] = $data['location_latitude'];
            }
            if (isset($data['location_longitude'])) {
                $metadata['location_longitude'] = $data['location_longitude'];
            }
            if (!empty($data['event_date'])) {
                $metadata['event_date'] = $data['event_date'];
            }
            if (!empty($data['event_start_time'])) {
                $metadata['event_start_time'] = $data['event_start_time'];
            }
            if (!empty($data['event_end_time'])) {
                $metadata['event_end_time'] = $data['event_end_time'];
            }
            
            if (!empty($metadata)) {
                $messageData['metadata'] = $metadata;
            }
            
            $message = $this->communicationService->sendMessage($messageData);
            
            if (!$message) {
                $this->sendErrorResponse('Failed to send message');
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => $this->formatMessage($message)
            ], 201);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Update a message
     */
    public function update(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $data = $this->getJsonInput();
            
            if (empty($data['content'])) {
                $this->sendErrorResponse('Content is required');
                return;
            }
            
            $message = $this->communicationService->updateMessage($id, $userId, $data['content']);
            
            if (!$message) {
                $this->sendErrorResponse('Message not found or access denied', 404);
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => $this->formatMessage($message)
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Delete a message
     */
    public function delete(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $result = $this->communicationService->deleteMessage($id, $userId);
            
            if (!$result) {
                $this->sendErrorResponse('Message not found or access denied', 404);
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Search messages in a channel
     */
    public function search(int $channelId): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $query = $_GET['q'] ?? '';
            $limit = (int)($_GET['limit'] ?? 50);
            
            if (strlen($query) < 3) {
                $this->sendErrorResponse('Search query must be at least 3 characters');
                return;
            }
            
            $messages = $this->communicationService->searchChannelMessages($channelId, $userId, $query, $limit);
            
            $formattedMessages = [];
            foreach ($messages as $message) {
                $formattedMessages[] = $this->formatMessage($message);
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => $formattedMessages,
                'query' => $query
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Pin a message
     */
    public function pin(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // This method would need to be added to CommunicationService
            $result = $this->communicationService->pinMessage($id, $userId);
            
            if (!$result) {
                $this->sendErrorResponse('Message not found or access denied', 404);
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Message pinned successfully'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Unpin a message
     */
    public function unpin(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // This method would need to be added to CommunicationService
            $result = $this->communicationService->unpinMessage($id, $userId);
            
            if (!$result) {
                $this->sendErrorResponse('Message not found or access denied', 404);
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Message unpinned successfully'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Format message for API response
     */
    private function formatMessage($message): array
    {
        // This assumes we have a way to get user information
        // In practice, you'd inject a UserService or similar
        
        $data = [
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'content_type' => $message->getContentType()->getValue(),
            'user' => [
                'id' => $message->getUserId(),
                'name' => 'User ' . $message->getUserId(), // Placeholder
                'avatar' => '/uploads/avatars/default.jpg' // Placeholder
            ],
            'parent_message_id' => $message->getParentMessageId(),
            'yfclaim_item_id' => $message->getYfclaimItemId(),
            'is_pinned' => $message->isPinned(),
            'is_edited' => $message->isEdited(),
            'reply_count' => $message->getReplyCount(),
            'reaction_count' => $message->getReactionCount(),
            'attachments' => [], // Would need attachment repository
            'reactions' => [], // Would need reaction repository
            'created_at' => $message->getCreatedAt()->format('c'),
            'updated_at' => $message->getUpdatedAt()->format('c')
        ];
        
        // Include metadata if present (for picks location data)
        $metadata = $message->getMetadata();
        if (!empty($metadata)) {
            // Add location fields directly to response for backward compatibility
            if (isset($metadata['location_name'])) {
                $data['location_name'] = $metadata['location_name'];
            }
            if (isset($metadata['location_address'])) {
                $data['location_address'] = $metadata['location_address'];
            }
            if (isset($metadata['location_latitude'])) {
                $data['location_latitude'] = $metadata['location_latitude'];
            }
            if (isset($metadata['location_longitude'])) {
                $data['location_longitude'] = $metadata['location_longitude'];
            }
            if (isset($metadata['event_date'])) {
                $data['event_date'] = $metadata['event_date'];
            }
            if (isset($metadata['event_start_time'])) {
                $data['event_start_time'] = $metadata['event_start_time'];
            }
            if (isset($metadata['event_end_time'])) {
                $data['event_end_time'] = $metadata['event_end_time'];
            }
        }
        
        return $data;
    }
    
}