<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Repositories;

use YFEvents\Domain\Communication\Entities\Message;

/**
 * Message repository interface
 */
interface MessageRepositoryInterface
{
    public function findById(int $id): ?Message;
    
    public function findByChannelId(int $channelId, int $limit = 50, int $offset = 0): array;
    
    public function findByParentMessageId(int $parentMessageId, int $limit = 20): array;
    
    public function findByEmailMessageId(string $emailMessageId): ?Message;
    
    public function findPinnedMessages(int $channelId): array;
    
    
    
    public function countReplies(int $messageId): int;
    
    public function searchMessages(int $channelId, string $query, int $limit = 50): array;
    
    public function findMentionsForUser(int $userId, int $limit = 20): array;
    
    public function getUnreadCount(int $channelId, int $userId, int $lastReadMessageId): int;
    
    public function findMessagesAfter(int $channelId, int $messageId, int $limit = 50): array;
    
    public function findMessagesBefore(int $channelId, int $messageId, int $limit = 50): array;
    
    public function markAsDeleted(int $messageId): bool;
    
    public function updateReplyCount(int $messageId, int $count): bool;
    
    public function updateReactionCount(int $messageId, int $count): bool;
}