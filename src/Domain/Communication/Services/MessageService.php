<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Services;

use YFEvents\Domain\Communication\Entities\Message;
use YFEvents\Domain\Communication\Entities\Attachment;
use YFEvents\Domain\Communication\Repositories\MessageRepositoryInterface;
use YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface;
use YFEvents\Domain\Communication\Repositories\ParticipantRepositoryInterface;

/**
 * Message handling service
 */
class MessageService
{
    private MessageRepositoryInterface $messageRepository;
    private ChannelRepositoryInterface $channelRepository;
    private ParticipantRepositoryInterface $participantRepository;
    
    public function __construct(
        MessageRepositoryInterface $messageRepository,
        ChannelRepositoryInterface $channelRepository,
        ParticipantRepositoryInterface $participantRepository
    ) {
        $this->messageRepository = $messageRepository;
        $this->channelRepository = $channelRepository;
        $this->participantRepository = $participantRepository;
    }
    
    public function createMessage(array $data): ?Message
    {
        // Verify user is participant in channel
        if (!$this->participantRepository->isUserInChannel($data['channel_id'], $data['user_id'])) {
            return null;
        }
        
        // Process mentions in content
        $mentions = $this->extractMentions($data['content']);
        
        $message = new Message(
            null,
            $data['channel_id'],
            $data['user_id'],
            $data['content'],
            $data['content_type'] ?? 'text',
            $data['parent_message_id'] ?? null,
            $data['yfclaim_item_id'] ?? null,
            false,
            false,
            false,
            ['mentions' => $mentions],
            null,
            null,
            null,
            $data['email_message_id'] ?? null
        );
        
        $message = $this->messageRepository->save($message);
        
        // Update channel activity
        $this->channelRepository->updateLastActivity($data['channel_id']);
        
        // Update message count
        $channel = $this->channelRepository->findById($data['channel_id']);
        if ($channel) {
            $channel->incrementMessageCount();
            $this->channelRepository->save($channel);
        }
        
        // Update parent message reply count if this is a reply
        if ($message->getParentMessageId()) {
            $replyCount = $this->messageRepository->countReplies($message->getParentMessageId());
            $this->messageRepository->updateReplyCount($message->getParentMessageId(), $replyCount);
        }
        
        return $message;
    }
    
    public function createMessageWithYFClaimReference(array $data): ?Message
    {
        // This allows vendors to reference their YFClaim items in discussions
        // The actual item validation would be done by checking YFClaim tables
        return $this->createMessage($data);
    }
    
    public function updateMessage(int $messageId, string $content): ?Message
    {
        $message = $this->messageRepository->findById($messageId);
        if (!$message || $message->isDeleted()) {
            return null;
        }
        
        // Update content and mentions
        $message->setContent($content);
        $mentions = $this->extractMentions($content);
        $message->addMetadata('mentions', $mentions);
        
        return $this->messageRepository->save($message);
    }
    
    public function deleteMessage(int $messageId): bool
    {
        $message = $this->messageRepository->findById($messageId);
        if (!$message) {
            return false;
        }
        
        return $this->messageRepository->markAsDeleted($messageId);
    }
    
    public function pinMessage(int $messageId): bool
    {
        $message = $this->messageRepository->findById($messageId);
        if (!$message || $message->isDeleted()) {
            return false;
        }
        
        $message->pin();
        $this->messageRepository->save($message);
        
        return true;
    }
    
    public function unpinMessage(int $messageId): bool
    {
        $message = $this->messageRepository->findById($messageId);
        if (!$message) {
            return false;
        }
        
        $message->unpin();
        $this->messageRepository->save($message);
        
        return true;
    }
    
    public function getChannelMessages(int $channelId, int $limit = 50, int $offset = 0): array
    {
        return $this->messageRepository->findByChannelId($channelId, $limit, $offset);
    }
    
    public function getMessageReplies(int $messageId, int $limit = 20): array
    {
        return $this->messageRepository->findByParentMessageId($messageId, $limit);
    }
    
    public function getPinnedMessages(int $channelId): array
    {
        return $this->messageRepository->findPinnedMessages($channelId);
    }
    
    public function searchMessages(int $channelId, string $query, int $limit = 50): array
    {
        return $this->messageRepository->searchMessages($channelId, $query, $limit);
    }
    
    public function getUserMentions(int $userId, int $limit = 20): array
    {
        return $this->messageRepository->findMentionsForUser($userId, $limit);
    }
    
    public function getMessageById(int $messageId): ?Message
    {
        return $this->messageRepository->findById($messageId);
    }
    
    public function getUnreadCount(int $channelId, int $userId): int
    {
        $participant = $this->participantRepository->findByChannelIdAndUserId($channelId, $userId);
        if (!$participant) {
            return 0;
        }
        
        $lastReadId = $participant->getLastReadMessageId() ?? 0;
        return $this->messageRepository->getUnreadCount($channelId, $userId, $lastReadId);
    }
    
    public function markChannelAsRead(int $channelId, int $userId): bool
    {
        // Get the latest message in the channel
        $messages = $this->messageRepository->findByChannelId($channelId, 1);
        if (empty($messages)) {
            return true;
        }
        
        $latestMessage = $messages[0];
        return $this->participantRepository->updateLastRead(
            $channelId,
            $userId,
            $latestMessage->getId()
        );
    }
    
    public function getMessagesAfter(int $channelId, int $messageId, int $limit = 50): array
    {
        return $this->messageRepository->findMessagesAfter($channelId, $messageId, $limit);
    }
    
    public function getMessagesBefore(int $channelId, int $messageId, int $limit = 50): array
    {
        return $this->messageRepository->findMessagesBefore($channelId, $messageId, $limit);
    }
    
    public function createSystemMessage(int $channelId, string $content): Message
    {
        return $this->createMessage([
            'channel_id' => $channelId,
            'user_id' => 1, // System user ID
            'content' => $content,
            'content_type' => 'system'
        ]);
    }
    
    public function notifyYFClaimActivity(int $itemId, string $activity): void
    {
        // This would send notifications to relevant channels about YFClaim activity
        // For example: "Item #123 has been marked as sold"
        // Implementation would depend on how channels are linked to YFClaim items
    }
    
    private function extractMentions(string $content): array
    {
        $mentions = [];
        
        // Extract @username mentions
        if (preg_match_all('/@(\w+)/', $content, $matches)) {
            $mentions = array_unique($matches[1]);
        }
        
        return $mentions;
    }
}