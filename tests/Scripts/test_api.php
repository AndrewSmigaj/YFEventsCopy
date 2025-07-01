#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/bootstrap.php';

// Initialize session
session_start();

// Simulate logged-in user
$_SESSION['user_id'] = 3; // test@yakimafinds.com
$_SESSION['user'] = [
    'id' => 3,
    'email' => 'test@yakimafinds.com',
    'username' => 'testuser',
    'first_name' => 'Test',
    'last_name' => 'User',
    'role' => 'admin'
];

echo "Testing Communication API Endpoints\n";
echo "==================================\n\n";

// Get container
$container = \YFEvents\Infrastructure\Container\Container::getInstance();

// Test 1: Get channels
echo "1. Testing GET /api/communication/channels\n";
try {
    $channelService = $container->resolve(\YFEvents\Domain\Communication\Services\ChannelService::class);
    $channels = $channelService->getPublicChannels();
    echo "   ✓ Found " . count($channels) . " channels\n";
    foreach ($channels as $channel) {
        echo "     - " . $channel->getName() . " (" . $channel->getType()->getValue() . ")\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Create a test message
echo "\n2. Testing message creation\n";
try {
    $messageService = $container->resolve(\YFEvents\Domain\Communication\Services\MessageService::class);
    $channelRepo = $container->resolve(\YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface::class);
    
    // Get general channel
    $generalChannel = $channelRepo->findBySlug('general');
    if ($generalChannel) {
        $message = $messageService->createMessage([
            'channel_id' => $generalChannel->getId(),
            'user_id' => 3,
            'content' => 'Test message from API test script',
            'type' => 'text'
        ]);
        if ($message) {
            echo "   ✓ Created message ID: " . $message->getId() . "\n";
        } else {
            echo "   ✗ Failed to create message (user may not be a participant)\n";
        }
    } else {
        echo "   ✗ General channel not found\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Get messages
echo "\n3. Testing GET messages from general channel\n";
try {
    $messageService = $container->resolve(\YFEvents\Domain\Communication\Services\MessageService::class);
    $channelRepo = $container->resolve(\YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface::class);
    
    $generalChannel = $channelRepo->findBySlug('general');
    if ($generalChannel) {
        $messages = $messageService->getChannelMessages($generalChannel->getId(), 10, 0);
        echo "   ✓ Found " . count($messages) . " messages\n";
        foreach ($messages as $msg) {
            echo "     - " . substr($msg->getContent(), 0, 50) . "...\n";
        }
    }
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Channel participation
echo "\n4. Testing channel participation\n";
try {
    $channelService = $container->resolve(\YFEvents\Domain\Communication\Services\ChannelService::class);
    $channelRepo = $container->resolve(\YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface::class);
    
    $generalChannel = $channelRepo->findBySlug('general');
    if ($generalChannel) {
        // Add user to channel if not already a participant
        $participantRepo = $container->resolve(\YFEvents\Domain\Communication\Repositories\ParticipantRepositoryInterface::class);
        $participant = $participantRepo->findByChannelIdAndUserId($generalChannel->getId(), 3);
        
        if (!$participant) {
            $participant = new \YFEvents\Domain\Communication\Entities\Participant(
                null,
                $generalChannel->getId(),
                3,
                'member',
                new \DateTimeImmutable(),
                null,
                null,
                'all',
                'daily',
                false
            );
            $participantRepo->save($participant);
            echo "   ✓ Added user as participant\n";
        } else {
            echo "   ✓ User is already a participant\n";
        }
    }
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n==================================\n";
echo "API Testing Complete!\n\n";
echo "Next steps:\n";
echo "1. Visit http://[your-domain]/login.php\n";
echo "2. Login with test@yakimafinds.com / test123\n";
echo "3. Visit http://[your-domain]/communication/\n";
echo "4. Test the communication interface\n";