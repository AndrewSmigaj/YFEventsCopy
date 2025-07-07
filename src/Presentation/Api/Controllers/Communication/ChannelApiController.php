<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Api\Controllers\Communication;

use YFEvents\Presentation\Http\Controllers\BaseController;
use YFEvents\Application\Services\Communication\CommunicationService;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Channel API controller
 */
class ChannelApiController extends BaseController
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
     * Get all channels for the current user
     */
    public function index(): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();
            if (!$userId) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            $channelsWithUnread = $this->communicationService->getUserChannelsWithUnread($userId);
            
            $data = [];
            foreach ($channelsWithUnread as $item) {
                $channel = $item['channel'];
                $data[] = [
                    'id' => $channel->getId(),
                    'name' => $channel->getName(),
                    'slug' => $channel->getSlug(),
                    'type' => $channel->getType()->getValue(),
                    'description' => $channel->getDescription(),
                    'participant_count' => $channel->getParticipantCount(),
                    'unread_count' => $item['unread_count'],
                    'is_archived' => $channel->isArchived(),
                    'last_activity_at' => $channel->getLastActivityAt()?->format('c'),
                    'created_at' => $channel->getCreatedAt()->format('c')
                ];
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Create a new channel
     */
    public function store(): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();
            if (!$userId) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            $data = $this->getInput();
            $data['created_by_user_id'] = $userId;
            
            $channel = $this->communicationService->createChannel($data);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'id' => $channel->getId(),
                    'name' => $channel->getName(),
                    'slug' => $channel->getSlug(),
                    'type' => $channel->getType()->getValue(),
                    'description' => $channel->getDescription()
                ]
            ], 201);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Get channel details
     */
    public function show(): void
    {
        try {
            $input = $this->getInput();
            if (!isset($input['id'])) {
                $this->errorResponse('Channel ID is required');
                return;
            }
            $id = (int) $input['id'];
            $userId = $this->getAuthenticatedUserId();
            if (!$userId) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            // This method would need to be added to CommunicationService
            $channel = $this->communicationService->getChannelById($id, $userId);
            
            if (!$channel) {
                $this->errorResponse('Channel not found', 404);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'id' => $channel->getId(),
                    'name' => $channel->getName(),
                    'slug' => $channel->getSlug(),
                    'type' => $channel->getType()->getValue(),
                    'description' => $channel->getDescription(),
                    'participant_count' => $channel->getParticipantCount(),
                    'message_count' => $channel->getMessageCount(),
                    'is_archived' => $channel->isArchived(),
                    'settings' => $channel->getSettings(),
                    'created_at' => $channel->getCreatedAt()->format('c'),
                    'updated_at' => $channel->getUpdatedAt()->format('c')
                ]
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Update channel
     */
    public function update(): void
    {
        try {
            $input = $this->getInput();
            if (!isset($input['id'])) {
                $this->errorResponse('Channel ID is required');
                return;
            }
            $id = (int) $input['id'];
            $userId = $this->getAuthenticatedUserId();
            if (!$userId) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            // This method would need to be added to CommunicationService
            $channel = $this->communicationService->updateChannel($id, $userId, $input);
            
            if (!$channel) {
                $this->errorResponse('Channel not found or access denied', 404);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'id' => $channel->getId(),
                    'name' => $channel->getName(),
                    'description' => $channel->getDescription()
                ]
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Delete channel
     */
    public function delete(): void
    {
        try {
            $input = $this->getInput();
            if (!isset($input['id'])) {
                $this->errorResponse('Channel ID is required');
                return;
            }
            $id = (int) $input['id'];
            $userId = $this->getAuthenticatedUserId();
            if (!$userId) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            // This method would need to be added to CommunicationService
            $result = $this->communicationService->deleteChannel($id, $userId);
            
            if (!$result) {
                $this->errorResponse('Channel not found or access denied', 404);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Channel deleted successfully'
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Join a channel
     */
    public function join(): void
    {
        try {
            $input = $this->getInput();
            if (!isset($input['id'])) {
                $this->errorResponse('Channel ID is required');
                return;
            }
            $id = (int) $input['id'];
            $userId = $this->getAuthenticatedUserId();
            if (!$userId) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            $participant = $this->communicationService->joinChannel($id, $userId);
            
            if (!$participant) {
                $this->errorResponse('Already a member of this channel');
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Successfully joined channel'
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Leave a channel
     */
    public function leave(): void
    {
        try {
            $input = $this->getInput();
            if (!isset($input['id'])) {
                $this->errorResponse('Channel ID is required');
                return;
            }
            $id = (int) $input['id'];
            $userId = $this->getAuthenticatedUserId();
            if (!$userId) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            $result = $this->communicationService->leaveChannel($id, $userId);
            
            if (!$result) {
                $this->errorResponse('Not a member of this channel');
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Successfully left channel'
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Mark channel as read
     */
    public function markAsRead(): void
    {
        try {
            $input = $this->getInput();
            if (!isset($input['id'])) {
                $this->errorResponse('Channel ID is required');
                return;
            }
            $id = (int) $input['id'];
            $userId = $this->getAuthenticatedUserId();
            if (!$userId) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            // This method would need to be added to CommunicationService
            $result = $this->communicationService->markChannelAsRead($id, $userId);
            
            if (!$result) {
                $this->errorResponse('Channel not found or access denied', 404);
                return;
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Channel marked as read'
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Get total unread message count for user
     */
    public function unreadCount(): void
    {
        try {
            $userId = $this->getAuthenticatedUserId();
            if (!$userId) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            // Get all channels with unread counts
            $channelsWithUnread = $this->communicationService->getUserChannelsWithUnread($userId);
            
            // Calculate total unread
            $totalUnread = 0;
            foreach ($channelsWithUnread as $item) {
                $totalUnread += $item['unread_count'];
            }
            
            $this->jsonResponse([
                'success' => true,
                'unread' => $totalUnread,
                'data' => [
                    'total_unread' => $totalUnread,
                    'channels' => array_map(function($item) {
                        return [
                            'channel_id' => $item['channel']->getId(),
                            'channel_name' => $item['channel']->getName(),
                            'unread_count' => $item['unread_count']
                        ];
                    }, $channelsWithUnread)
                ]
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
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