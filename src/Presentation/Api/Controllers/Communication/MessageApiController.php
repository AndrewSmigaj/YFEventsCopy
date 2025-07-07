<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Api\Controllers\Communication;

use YFEvents\Presentation\Http\Controllers\BaseController;
use YFEvents\Application\Services\Communication\CommunicationService;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Message API controller
 */
class MessageApiController extends BaseController
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
     * Get messages for a channel
     */
    public function index(): void
    {
        try {
            $input = $this->getInput();
            
            if (!isset($input['channel_id'])) {
                $this->errorResponse('Channel ID is required');
                return;
            }
            
            $channelId = (int) $input['channel_id'];
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            
            $page = (int) ($input['page'] ?? 1);
            $limit = min(100, max(1, (int) ($input['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;
            
            $result = $this->communicationService->getChannelMessages($channelId, $userId, $limit, $offset);
            
            $messages = [];
            foreach ($result['messages'] as $message) {
                $messages[] = $this->formatMessage($message);
            }
            
            $this->jsonResponse([
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
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Send a message to a channel
     */
    public function store(): void
    {
        try {
            $input = $this->getInput();
            
            if (!isset($input['channel_id'])) {
                $this->errorResponse('Channel ID is required');
                return;
            }
            
            $channelId = (int) $input['channel_id'];
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            
            $messageData = [
                'channel_id' => $channelId,
                'user_id' => $userId,
                'content' => $input['content'] ?? '',
                'parent_message_id' => $input['parent_message_id'] ?? null,
                'yfclaim_item_id' => $input['yfclaim_item_id'] ?? null
            ];
            
            // Handle attachments if provided
            if (!empty($input['attachments'])) {
                $messageData['attachments'] = $input['attachments'];
            }
            
            $message = $this->communicationService->sendMessage($messageData);
            
            if (!$message) {
                $this->errorResponse('Failed to send message');
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $this->formatMessage($message)
            ], 201);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
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
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
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
                $this->errorResponse('Message not found or access denied', 404);
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
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
            
            $this->jsonResponse([
                'success' => true,
                'data' => $formattedMessages,
                'query' => $query
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Pin a message
     */
    public function pin(): void
    {
        try {
            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Message ID is required');
                return;
            }
            
            $id = (int) $input['id'];
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            
            // This method would need to be added to CommunicationService
            $result = $this->communicationService->pinMessage($id, $userId);
            
            if (!$result) {
                $this->errorResponse('Message not found or access denied', 404);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Message pinned successfully'
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Unpin a message
     */
    public function unpin(): void
    {
        try {
            $input = $this->getInput();
            
            if (!isset($input['id'])) {
                $this->errorResponse('Message ID is required');
                return;
            }
            
            $id = (int) $input['id'];
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            
            // This method would need to be added to CommunicationService
            $result = $this->communicationService->unpinMessage($id, $userId);
            
            if (!$result) {
                $this->errorResponse('Message not found or access denied', 404);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Message unpinned successfully'
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Format message for API response
     */
    private function formatMessage($message): array
    {
        // This assumes we have a way to get user information
        // In practice, you'd inject a UserService or similar
        
        return [
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
    }
    
    /**
     * Set CORS headers for API access
     */
    private function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}