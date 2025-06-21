<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Repositories;

use YFEvents\Domain\Communication\Entities\Participant;

/**
 * Participant repository interface
 */
interface ParticipantRepositoryInterface
{
    public function findById(int $id): ?Participant;
    
    public function findByChannelIdAndUserId(int $channelId, int $userId): ?Participant;
    
    public function findByChannelId(int $channelId, int $limit = 100, int $offset = 0): array;
    
    public function findByUserId(int $userId, bool $includeMuted = false): array;
    
    
    
    public function deleteByChannelIdAndUserId(int $channelId, int $userId): bool;
    
    public function countByChannelId(int $channelId): int;
    
    public function findChannelAdmins(int $channelId): array;
    
    public function updateLastRead(int $channelId, int $userId, int $messageId): bool;
    
    public function findForDigest(string $frequency): array;
    
    public function isUserInChannel(int $channelId, int $userId): bool;
    
    public function findUsersToNotify(int $channelId, string $notificationType = 'all'): array;
}