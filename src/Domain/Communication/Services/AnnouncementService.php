<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Services;

use YFEvents\Domain\Communication\Entities\Channel;
use YFEvents\Domain\Communication\Entities\Message;
use YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface;
use YFEvents\Domain\Communication\Repositories\MessageRepositoryInterface;
use YFEvents\Domain\Communication\Repositories\ParticipantRepositoryInterface;

/**
 * Announcement and broadcast message service
 */
class AnnouncementService
{
    private ChannelRepositoryInterface $channelRepository;
    private MessageRepositoryInterface $messageRepository;
    private ParticipantRepositoryInterface $participantRepository;
    private MessageService $messageService;
    
    public function __construct(
        ChannelRepositoryInterface $channelRepository,
        MessageRepositoryInterface $messageRepository,
        ParticipantRepositoryInterface $participantRepository,
        MessageService $messageService
    ) {
        $this->channelRepository = $channelRepository;
        $this->messageRepository = $messageRepository;
        $this->participantRepository = $participantRepository;
        $this->messageService = $messageService;
    }
    
    /**
     * Create a system-wide announcement
     */
    public function createSystemAnnouncement(string $title, string $content, int $authorId): array
    {
        $announcementChannels = [];
        
        // Find all announcement channels
        $channels = $this->channelRepository->findBy(['type' => 'announcement']);
        
        foreach ($channels as $channel) {
            $message = $this->createAnnouncementMessage($channel->getId(), $title, $content, $authorId);
            if ($message) {
                $announcementChannels[] = [
                    'channel' => $channel,
                    'message' => $message
                ];
            }
        }
        
        return $announcementChannels;
    }
    
    /**
     * Create an event-specific announcement
     */
    public function createEventAnnouncement(int $eventId, string $title, string $content, int $authorId): array
    {
        $announcementChannels = [];
        
        // Find all channels associated with this event
        $channels = $this->channelRepository->findByEventId($eventId);
        
        foreach ($channels as $channel) {
            $message = $this->createAnnouncementMessage($channel->getId(), $title, $content, $authorId);
            if ($message) {
                $announcementChannels[] = [
                    'channel' => $channel,
                    'message' => $message
                ];
            }
        }
        
        return $announcementChannels;
    }
    
    /**
     * Create a vendor-specific announcement
     */
    public function createVendorAnnouncement(int $shopId, string $title, string $content, int $authorId): array
    {
        $announcementChannels = [];
        
        // Find all channels associated with this shop/vendor
        $channels = $this->channelRepository->findByShopId($shopId);
        
        foreach ($channels as $channel) {
            $message = $this->createAnnouncementMessage($channel->getId(), $title, $content, $authorId);
            if ($message) {
                $announcementChannels[] = [
                    'channel' => $channel,
                    'message' => $message
                ];
            }
        }
        
        return $announcementChannels;
    }
    
    /**
     * Create an announcement in specific channels
     */
    public function createTargetedAnnouncement(array $channelIds, string $title, string $content, int $authorId): array
    {
        $announcementChannels = [];
        
        foreach ($channelIds as $channelId) {
            $channel = $this->channelRepository->findById($channelId);
            if ($channel) {
                $message = $this->createAnnouncementMessage($channelId, $title, $content, $authorId);
                if ($message) {
                    $announcementChannels[] = [
                        'channel' => $channel,
                        'message' => $message
                    ];
                }
            }
        }
        
        return $announcementChannels;
    }
    
    /**
     * Get announcement statistics
     */
    public function getAnnouncementStats(int $messageId): array
    {
        $message = $this->messageRepository->findById($messageId);
        if (!$message || $message->getContentType()->getValue() !== 'announcement') {
            return [];
        }
        
        $channel = $this->channelRepository->findById($message->getChannelId());
        if (!$channel) {
            return [];
        }
        
        // Get participant count for reach
        $participantCount = $this->participantRepository->countByChannelId($channel->getId());
        
        // Get read count (participants who have read past this message)
        $readCount = 0;
        $participants = $this->participantRepository->findByChannelId($channel->getId());
        foreach ($participants as $participant) {
            if ($participant->getLastReadMessageId() >= $message->getId()) {
                $readCount++;
            }
        }
        
        return [
            'message_id' => $message->getId(),
            'channel_id' => $channel->getId(),
            'channel_name' => $channel->getName(),
            'total_reach' => $participantCount,
            'read_count' => $readCount,
            'read_percentage' => $participantCount > 0 ? round(($readCount / $participantCount) * 100, 2) : 0,
            'created_at' => $message->getCreatedAt()->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get all announcements for a user
     */
    public function getUserAnnouncements(int $userId, int $limit = 20, int $offset = 0): array
    {
        $announcements = [];
        
        // Get user's channels
        $participants = $this->participantRepository->findByUserId($userId);
        
        foreach ($participants as $participant) {
            $channel = $this->channelRepository->findById($participant->getChannelId());
            if ($channel && ($channel->getType()->isAnnouncement() || $channel->getType()->isEvent())) {
                // Get announcement messages from this channel
                $messages = $this->messageRepository->findBy(
                    [
                        'channel_id' => $channel->getId(),
                        'content_type' => 'announcement'
                    ],
                    ['created_at' => 'DESC'],
                    10
                );
                
                foreach ($messages as $message) {
                    $announcements[] = [
                        'message' => $message,
                        'channel' => $channel,
                        'is_read' => $participant->getLastReadMessageId() >= $message->getId()
                    ];
                }
            }
        }
        
        // Sort by date and apply pagination
        usort($announcements, function ($a, $b) {
            return $b['message']->getCreatedAt() <=> $a['message']->getCreatedAt();
        });
        
        return array_slice($announcements, $offset, $limit);
    }
    
    /**
     * Create an announcement message
     */
    private function createAnnouncementMessage(int $channelId, string $title, string $content, int $authorId): ?Message
    {
        $formattedContent = "**{$title}**\n\n{$content}";
        
        $message = $this->messageService->createMessage([
            'channel_id' => $channelId,
            'user_id' => $authorId,
            'content' => $formattedContent,
            'content_type' => 'announcement'
        ]);
        
        // Auto-pin announcements
        if ($message) {
            $this->messageService->pinMessage($message->getId());
        }
        
        return $message;
    }
}