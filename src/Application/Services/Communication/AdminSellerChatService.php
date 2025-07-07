<?php

declare(strict_types=1);

namespace YFEvents\Application\Services\Communication;

use YFEvents\Domain\Communication\Services\ChannelService;
use YFEvents\Domain\Communication\Services\MessageService;
use YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface;
use YFEvents\Domain\Communication\Repositories\ParticipantRepositoryInterface;
use YFEvents\Domain\Communication\Entities\Channel;
use YFEvents\Domain\Communication\Entities\Message;
use YFEvents\Domain\Communication\Entities\Participant;

/**
 * Service specifically for admin-seller chat functionality
 * Manages the two global channels: Support and Selling Tips
 */
class AdminSellerChatService
{
    private const SUPPORT_CHANNEL_TITLE = 'Support';
    private const TIPS_CHANNEL_TITLE = 'Tips & Tricks';
    
    public function __construct(
        private CommunicationService $communicationService,
        private ChannelService $channelService,
        private MessageService $messageService,
        private ChannelRepositoryInterface $channelRepository,
        private ParticipantRepositoryInterface $participantRepository
    ) {}
    
    /**
     * Ensure user is in both global channels
     * Automatically joins them if they're not already members
     */
    public function ensureUserInGlobalChannels(int $userId, string $userRole): void
    {
        $channels = $this->getGlobalChannels();
        
        foreach ($channels as $channel) {
            // Check if user is already a participant
            if (!$this->participantRepository->isUserInChannel($channel->getId(), $userId)) {
                // Determine role based on user type
                $participantRole = $this->isAdminRole($userRole) ? 'admin' : 'member';
                
                // Add user to channel
                $this->channelService->addParticipant(
                    $channel->getId(),
                    $userId,
                    $participantRole
                );
            }
        }
    }
    
    /**
     * Get the two global channels
     * @return Channel[] Array with 'support' and 'tips' keys
     */
    public function getGlobalChannels(): array
    {
        $channels = [];
        
        // Find support channel
        $supportChannels = $this->channelRepository->findPublicChannels(10, 0);
        foreach ($supportChannels as $channel) {
            if ($channel->getName() === self::SUPPORT_CHANNEL_TITLE) {
                $channels['support'] = $channel;
                break;
            }
        }
        
        // Find tips channel
        foreach ($supportChannels as $channel) {
            if ($channel->getName() === self::TIPS_CHANNEL_TITLE) {
                $channels['tips'] = $channel;
                break;
            }
        }
        
        // Validate we found both channels
        if (!isset($channels['support']) || !isset($channels['tips'])) {
            throw new \RuntimeException('Global chat channels not found. Please run seed script.');
        }
        
        return $channels;
    }
    
    /**
     * Get user's channels with unread counts
     * Ensures they're in global channels first
     */
    public function getUserChannelsWithUnread(int $userId, string $userRole): array
    {
        // Ensure user is in global channels
        $this->ensureUserInGlobalChannels($userId, $userRole);
        
        // Get channels with unread counts
        return $this->communicationService->getUserChannelsWithUnread($userId);
    }
    
    /**
     * Send a message to the support channel
     */
    public function sendSupportMessage(int $userId, string $content): ?Message
    {
        $channels = $this->getGlobalChannels();
        $supportChannel = $channels['support'];
        
        return $this->communicationService->sendMessage([
            'channel_id' => $supportChannel->getId(),
            'user_id' => $userId,
            'content' => $content
        ]);
    }
    
    /**
     * Send a message to the tips channel
     */
    public function sendTipsMessage(int $userId, string $content): ?Message
    {
        $channels = $this->getGlobalChannels();
        $tipsChannel = $channels['tips'];
        
        return $this->communicationService->sendMessage([
            'channel_id' => $tipsChannel->getId(),
            'user_id' => $userId,
            'content' => $content
        ]);
    }
    
    /**
     * Get messages from a channel
     */
    public function getChannelMessages(int $channelId, int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->communicationService->getChannelMessages($channelId, $userId, $limit, $offset);
    }
    
    /**
     * Mark channel as read
     */
    public function markChannelAsRead(int $channelId, int $userId): bool
    {
        return $this->communicationService->markChannelAsRead($channelId, $userId);
    }
    
    /**
     * Get support channel ID
     */
    public function getSupportChannelId(): int
    {
        $channels = $this->getGlobalChannels();
        return $channels['support']->getId();
    }
    
    /**
     * Get tips channel ID
     */
    public function getTipsChannelId(): int
    {
        $channels = $this->getGlobalChannels();
        return $channels['tips']->getId();
    }
    
    /**
     * Add all users with a specific role to global channels
     * Useful for batch operations like adding all existing sellers
     */
    public function addRoleUsersToGlobalChannels(array $userIds, string $userRole): void
    {
        foreach ($userIds as $userId) {
            $this->ensureUserInGlobalChannels($userId, $userRole);
        }
    }
    
    /**
     * Check if a role is an admin role
     */
    private function isAdminRole(string $role): bool
    {
        return in_array($role, ['super_admin', 'calendar_admin', 'shop_moderator']);
    }
}