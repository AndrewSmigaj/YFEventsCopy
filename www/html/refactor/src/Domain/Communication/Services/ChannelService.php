<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Services;

use YFEvents\Domain\Communication\Entities\Channel;
use YFEvents\Domain\Communication\Entities\Participant;
use YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface;
use YFEvents\Domain\Communication\Repositories\ParticipantRepositoryInterface;

/**
 * Channel management service
 */
class ChannelService
{
    private ChannelRepositoryInterface $channelRepository;
    private ParticipantRepositoryInterface $participantRepository;
    
    public function __construct(
        ChannelRepositoryInterface $channelRepository,
        ParticipantRepositoryInterface $participantRepository
    ) {
        $this->channelRepository = $channelRepository;
        $this->participantRepository = $participantRepository;
    }
    
    public function createChannel(array $data): Channel
    {
        // Generate slug from name if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }
        
        // Ensure slug is unique
        $baseSlug = $data['slug'];
        $counter = 1;
        while ($this->channelRepository->findBySlug($data['slug'])) {
            $data['slug'] = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        // Create channel
        $channel = new Channel(
            null,
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['type'] ?? 'public',
            $data['created_by_user_id'],
            $data['event_id'] ?? null,
            $data['shop_id'] ?? null,
            false,
            $data['settings'] ?? []
        );
        
        $channel = $this->channelRepository->save($channel);
        
        // Add creator as admin participant
        $this->addParticipant($channel->getId(), $data['created_by_user_id'], 'admin');
        
        return $channel;
    }
    
    public function updateChannel(int $channelId, array $data): ?Channel
    {
        $channel = $this->channelRepository->findById($channelId);
        if (!$channel) {
            return null;
        }
        
        if (isset($data['name'])) {
            $channel->setName($data['name']);
        }
        
        if (isset($data['description'])) {
            $channel->setDescription($data['description']);
        }
        
        if (isset($data['settings'])) {
            foreach ($data['settings'] as $key => $value) {
                $channel->setSetting($key, $value);
            }
        }
        
        return $this->channelRepository->save($channel);
    }
    
    public function archiveChannel(int $channelId): bool
    {
        $channel = $this->channelRepository->findById($channelId);
        if (!$channel) {
            return false;
        }
        
        $channel->archive();
        $this->channelRepository->save($channel);
        
        return true;
    }
    
    public function unarchiveChannel(int $channelId): bool
    {
        $channel = $this->channelRepository->findById($channelId);
        if (!$channel) {
            return false;
        }
        
        $channel->unarchive();
        $this->channelRepository->save($channel);
        
        return true;
    }
    
    public function deleteChannel(int $channelId): bool
    {
        return $this->channelRepository->delete($channelId);
    }
    
    public function addParticipant(int $channelId, int $userId, string $role = 'member'): ?Participant
    {
        // Check if already participant
        if ($this->participantRepository->isUserInChannel($channelId, $userId)) {
            return null;
        }
        
        $participant = new Participant(
            null,
            $channelId,
            $userId,
            $role
        );
        
        $participant = $this->participantRepository->save($participant);
        
        // Update participant count
        $count = $this->participantRepository->countByChannelId($channelId);
        $this->channelRepository->updateParticipantCount($channelId, $count);
        
        return $participant;
    }
    
    public function removeParticipant(int $channelId, int $userId): bool
    {
        $result = $this->participantRepository->deleteByChannelIdAndUserId($channelId, $userId);
        
        if ($result) {
            // Update participant count
            $count = $this->participantRepository->countByChannelId($channelId);
            $this->channelRepository->updateParticipantCount($channelId, $count);
        }
        
        return $result;
    }
    
    public function updateParticipantRole(int $channelId, int $userId, string $role): bool
    {
        $participant = $this->participantRepository->findByChannelIdAndUserId($channelId, $userId);
        if (!$participant) {
            return false;
        }
        
        $participant->setRole($role);
        $this->participantRepository->save($participant);
        
        return true;
    }
    
    public function canUserAccessChannel(int $channelId, int $userId): bool
    {
        $channel = $this->channelRepository->findById($channelId);
        if (!$channel) {
            return false;
        }
        
        // Public channels are accessible to all
        if ($channel->getType()->isPublic()) {
            return true;
        }
        
        // For other types, user must be a participant
        return $this->participantRepository->isUserInChannel($channelId, $userId);
    }
    
    public function canUserManageChannel(int $channelId, int $userId): bool
    {
        $channel = $this->channelRepository->findById($channelId);
        if (!$channel) {
            return false;
        }
        
        // Channel creator can always manage
        if ($channel->getCreatedByUserId() === $userId) {
            return true;
        }
        
        // Check if user is admin participant
        $participant = $this->participantRepository->findByChannelIdAndUserId($channelId, $userId);
        return $participant && $participant->isAdmin();
    }
    
    public function getUserChannels(int $userId, bool $includeArchived = false): array
    {
        return $this->channelRepository->findUserChannels($userId, $includeArchived);
    }
    
    public function getPublicChannels(int $limit = 50, int $offset = 0): array
    {
        return $this->channelRepository->findPublicChannels($limit, $offset);
    }
    
    public function searchChannels(string $query, int $limit = 20): array
    {
        return $this->channelRepository->searchChannels($query, $limit);
    }
    
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        return $slug;
    }
}