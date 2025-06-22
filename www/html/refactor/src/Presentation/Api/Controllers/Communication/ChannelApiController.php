<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Api\Controllers\Communication;

use YFEvents\Application\Services\Communication\CommunicationService;

/**
 * Channel API controller
 */
class ChannelApiController
{
    private CommunicationService $communicationService;
    
    public function __construct(CommunicationService $communicationService)
    {
        $this->communicationService = $communicationService;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Get current user ID from session
     */
    private function getCurrentUserId(): int
    {
        if (!isset($_SESSION['user_id'])) {
            $this->sendJsonError('Authentication required', 401);
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
    private function sendJsonError(string $message, int $statusCode = 400): void
    {
        $this->sendJsonResponse([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
    
    /**
     * Get all channels for the current user
     */
    public function index(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
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
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage());
        }
    }
    
    /**
     * Create a new channel
     */
    public function store(): void
    {
        try {
            $data = $this->getJsonInput();
            $data['created_by_user_id'] = $this->getCurrentUserId();
            
            $channel = $this->communicationService->createChannel($data);
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => [
                    'id' => $channel->getId(),
                    'name' => $channel->getName(),
                    'slug' => $channel->getSlug(),
                    'type' => $channel->getType()->getValue(),
                    'description' => $channel->getDescription()
                ]
            ], 201);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage());
        }
    }
    
    /**
     * Get channel details
     */
    public function show(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // This method would need to be added to CommunicationService
            $channel = $this->communicationService->getChannelById($id, $userId);
            
            if (!$channel) {
                $this->sendJsonError('Channel not found', 404);
                return;
            }
            
            $this->sendJsonResponse([
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
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage());
        }
    }
    
    /**
     * Update channel
     */
    public function update(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $data = $this->getJsonInput();
            
            // This method would need to be added to CommunicationService
            $channel = $this->communicationService->updateChannel($id, $userId, $data);
            
            if (!$channel) {
                $this->sendJsonError('Channel not found or access denied', 404);
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => [
                    'id' => $channel->getId(),
                    'name' => $channel->getName(),
                    'description' => $channel->getDescription()
                ]
            ]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage());
        }
    }
    
    /**
     * Delete channel
     */
    public function delete(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // This method would need to be added to CommunicationService
            $result = $this->communicationService->deleteChannel($id, $userId);
            
            if (!$result) {
                $this->sendJsonError('Channel not found or access denied', 404);
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Channel deleted successfully'
            ]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage());
        }
    }
    
    /**
     * Join a channel
     */
    public function join(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $participant = $this->communicationService->joinChannel($id, $userId);
            
            if (!$participant) {
                $this->sendJsonError('Already a member of this channel');
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Successfully joined channel'
            ]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage());
        }
    }
    
    /**
     * Leave a channel
     */
    public function leave(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $result = $this->communicationService->leaveChannel($id, $userId);
            
            if (!$result) {
                $this->sendJsonError('Not a member of this channel');
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Successfully left channel'
            ]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage());
        }
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
    
}