<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Repositories;

use YFEvents\Domain\Communication\Entities\Channel;

/**
 * Channel repository interface
 */
interface ChannelRepositoryInterface
{
    public function findById(int $id): ?Channel;
    
    public function findBySlug(string $slug): ?Channel;
    
    public function findByEventId(int $eventId): array;
    
    public function findByShopId(int $shopId): array;
    
    public function findPublicChannels(int $limit = 50, int $offset = 0): array;
    
    public function findUserChannels(int $userId, bool $includeArchived = false): array;
    
    
    
    public function countParticipants(int $channelId): int;
    
    public function isUserParticipant(int $channelId, int $userId): bool;
    
    public function updateMessageCount(int $channelId, int $count): bool;
    
    public function updateParticipantCount(int $channelId, int $count): bool;
    
    public function updateLastActivity(int $channelId): bool;
    
    public function searchChannels(string $query, int $limit = 20): array;
}