<?php

declare(strict_types=1);

namespace YFEvents\Application\Services\Communication;

use YFEvents\Domain\Communication\Services\ChannelService;
use YFEvents\Domain\Communication\Services\MessageService;
use YFEvents\Domain\Communication\Services\AnnouncementService;
use YFEvents\Domain\Communication\Entities\Channel;
use YFEvents\Domain\Communication\Entities\Message;
use YFEvents\Domain\Communication\Entities\Participant;

/**
 * Main orchestration service for communication features
 */
class CommunicationService
{
    private ChannelService $channelService;
    private MessageService $messageService;
    private AnnouncementService $announcementService;
    
    public function __construct(
        ChannelService $channelService,
        MessageService $messageService,
        AnnouncementService $announcementService
    ) {
        $this->channelService = $channelService;
        $this->messageService = $messageService;
        $this->announcementService = $announcementService;
    }
    
    /**
     * Create a new communication channel
     */
    public function createChannel(array $data): Channel
    {
        // Validate required fields
        if (empty($data['name']) || empty($data['created_by_user_id'])) {
            throw new \InvalidArgumentException('Channel name and creator are required');
        }
        
        // Set defaults
        $data['type'] = $data['type'] ?? 'public';
        
        // Validate type-specific requirements
        if ($data['type'] === 'event' && empty($data['event_id'])) {
            throw new \InvalidArgumentException('Event channels require an event_id');
        }
        
        if ($data['type'] === 'vendor' && empty($data['shop_id'])) {
            throw new \InvalidArgumentException('Vendor channels require a shop_id');
        }
        
        return $this->channelService->createChannel($data);
    }
    
    /**
     * Send a message to a channel
     */
    public function sendMessage(array $data): ?Message
    {
        // Validate required fields
        if (empty($data['channel_id']) || empty($data['user_id']) || empty($data['content'])) {
            throw new \InvalidArgumentException('Channel, user, and content are required');
        }
        
        // Check if user can send to this channel
        if (!$this->channelService->canUserAccessChannel($data['channel_id'], $data['user_id'])) {
            throw new \RuntimeException('User does not have access to this channel');
        }
        
        return $this->messageService->createMessage($data);
    }
    
    /**
     * Send a message with YFClaim item reference
     */
    public function sendMessageWithYFClaimReference(array $data): ?Message
    {
        // Validate YFClaim reference
        if (empty($data['yfclaim_item_id'])) {
            throw new \InvalidArgumentException('YFClaim item ID is required');
        }
        
        // TODO: Validate that the item exists and user has permission to reference it
        // This would require injecting YFClaim repository/service
        
        return $this->sendMessage($data);
    }
    
    /**
     * Join a user to a channel
     */
    public function joinChannel(int $channelId, int $userId): ?Participant
    {
        // Check if user can join
        if (!$this->channelService->canUserAccessChannel($channelId, $userId)) {
            throw new \RuntimeException('User cannot join this channel');
        }
        
        return $this->channelService->addParticipant($channelId, $userId);
    }
    
    /**
     * Leave a channel
     */
    public function leaveChannel(int $channelId, int $userId): bool
    {
        return $this->channelService->removeParticipant($channelId, $userId);
    }
    
    /**
     * Get channel messages with metadata
     */
    public function getChannelMessages(int $channelId, int $userId, int $limit = 50, int $offset = 0): array
    {
        // Check access
        if (!$this->channelService->canUserAccessChannel($channelId, $userId)) {
            throw new \RuntimeException('User does not have access to this channel');
        }
        
        $messages = $this->messageService->getChannelMessages($channelId, $limit, $offset);
        $unreadCount = $this->messageService->getUnreadCount($channelId, $userId);
        
        return [
            'messages' => $messages,
            'unread_count' => $unreadCount,
            'total_count' => count($messages),
            'has_more' => count($messages) === $limit
        ];
    }
    
    /**
     * Mark channel as read
     */
    public function markChannelAsRead(int $channelId, int $userId): bool
    {
        return $this->messageService->markChannelAsRead($channelId, $userId);
    }
    
    /**
     * Update a message
     */
    public function updateMessage(int $messageId, int $userId, string $content): ?Message
    {
        $message = $this->getMessageIfUserCanEdit($messageId, $userId);
        if (!$message) {
            throw new \RuntimeException('Cannot edit this message');
        }
        
        return $this->messageService->updateMessage($messageId, $content);
    }
    
    /**
     * Delete a message
     */
    public function deleteMessage(int $messageId, int $userId): bool
    {
        $message = $this->getMessageIfUserCanDelete($messageId, $userId);
        if (!$message) {
            throw new \RuntimeException('Cannot delete this message');
        }
        
        return $this->messageService->deleteMessage($messageId);
    }
    
    /**
     * Create an announcement
     */
    public function createAnnouncement(string $type, array $data): array
    {
        // Validate announcement data
        if (empty($data['title']) || empty($data['content']) || empty($data['author_id'])) {
            throw new \InvalidArgumentException('Title, content, and author are required');
        }
        
        switch ($type) {
            case 'system':
                return $this->announcementService->createSystemAnnouncement(
                    $data['title'],
                    $data['content'],
                    $data['author_id']
                );
                
            case 'event':
                if (empty($data['event_id'])) {
                    throw new \InvalidArgumentException('Event ID is required for event announcements');
                }
                return $this->announcementService->createEventAnnouncement(
                    $data['event_id'],
                    $data['title'],
                    $data['content'],
                    $data['author_id']
                );
                
            case 'vendor':
                if (empty($data['shop_id'])) {
                    throw new \InvalidArgumentException('Shop ID is required for vendor announcements');
                }
                return $this->announcementService->createVendorAnnouncement(
                    $data['shop_id'],
                    $data['title'],
                    $data['content'],
                    $data['author_id']
                );
                
            case 'targeted':
                if (empty($data['channel_ids'])) {
                    throw new \InvalidArgumentException('Channel IDs are required for targeted announcements');
                }
                return $this->announcementService->createTargetedAnnouncement(
                    $data['channel_ids'],
                    $data['title'],
                    $data['content'],
                    $data['author_id']
                );
                
            default:
                throw new \InvalidArgumentException('Invalid announcement type');
        }
    }
    
    /**
     * Get user's channels with unread counts
     */
    public function getUserChannelsWithUnread(int $userId): array
    {
        $channels = $this->channelService->getUserChannels($userId);
        
        $result = [];
        foreach ($channels as $channel) {
            $unreadCount = $this->messageService->getUnreadCount($channel->getId(), $userId);
            
            $result[] = [
                'channel' => $channel,
                'unread_count' => $unreadCount
            ];
        }
        
        return $result;
    }
    
    /**
     * Search channels
     */
    public function searchChannels(string $query, int $limit = 20): array
    {
        return $this->channelService->searchChannels($query, $limit);
    }
    
    /**
     * Search messages in a channel
     */
    public function searchChannelMessages(int $channelId, int $userId, string $query, int $limit = 50): array
    {
        // Check access
        if (!$this->channelService->canUserAccessChannel($channelId, $userId)) {
            throw new \RuntimeException('User does not have access to this channel');
        }
        
        return $this->messageService->searchMessages($channelId, $query, $limit);
    }
    
    /**
     * Get user announcements
     */
    public function getUserAnnouncements(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->announcementService->getUserAnnouncements($userId, $limit, $offset);
    }
    
    /**
     * Check if user can edit a message
     */
    private function getMessageIfUserCanEdit(int $messageId, int $userId): ?Message
    {
        // For now, users can only edit their own messages
        // In the future, we might allow channel admins to edit any message
        $message = $this->messageService->getMessageById($messageId);
        if (!$message || $message->getUserId() !== $userId) {
            return null;
        }
        
        return $message;
    }
    
    /**
     * Check if user can delete a message
     */
    private function getMessageIfUserCanDelete(int $messageId, int $userId): ?Message
    {
        // Users can delete their own messages
        // Channel admins can delete any message in their channels
        $message = $this->messageService->getMessageById($messageId);
        if (!$message) {
            return null;
        }
        
        // Check if user owns the message
        if ($message->getUserId() === $userId) {
            return $message;
        }
        
        // Check if user is channel admin
        if ($this->channelService->canUserManageChannel($message->getChannelId(), $userId)) {
            return $message;
        }
        
        return null;
    }
}