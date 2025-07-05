<?php

declare(strict_types=1);

/**
 * Communication module service registrations
 * 
 * Add these registrations to your main container configuration
 */

use YFEvents\Infrastructure\Container\Container;
use YFEvents\Infrastructure\Database\ConnectionInterface;

// Import repositories
use YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface;
use YFEvents\Domain\Communication\Repositories\MessageRepositoryInterface;
use YFEvents\Domain\Communication\Repositories\ParticipantRepositoryInterface;
use YFEvents\Infrastructure\Repositories\Communication\ChannelRepository;
use YFEvents\Infrastructure\Repositories\Communication\MessageRepository;
use YFEvents\Infrastructure\Repositories\Communication\ParticipantRepository;

// Import services
use YFEvents\Domain\Communication\Services\ChannelService;
use YFEvents\Domain\Communication\Services\MessageService;
use YFEvents\Domain\Communication\Services\AnnouncementService;
use YFEvents\Application\Services\Communication\CommunicationService;

// Import controllers
use YFEvents\Presentation\Api\Controllers\Communication\ChannelApiController;
use YFEvents\Presentation\Api\Controllers\Communication\MessageApiController;
use YFEvents\Presentation\Api\Controllers\Communication\AnnouncementApiController;
use YFEvents\Presentation\Api\Controllers\Communication\NotificationApiController;

return function (Container $container) {
    
    // Register repository interfaces
    $container->bind(ChannelRepositoryInterface::class, function($container) {
        return new ChannelRepository(
            $container->resolve(ConnectionInterface::class)
        );
    });
    
    $container->bind(MessageRepositoryInterface::class, function($container) {
        return new MessageRepository(
            $container->resolve(ConnectionInterface::class)
        );
    });
    
    $container->bind(ParticipantRepositoryInterface::class, function($container) {
        return new ParticipantRepository(
            $container->resolve(ConnectionInterface::class)
        );
    });
    
    // Register domain services
    $container->bind(ChannelService::class, function($container) {
        return new ChannelService(
            $container->resolve(ChannelRepositoryInterface::class),
            $container->resolve(ParticipantRepositoryInterface::class)
        );
    });
    
    $container->bind(MessageService::class, function($container) {
        return new MessageService(
            $container->resolve(MessageRepositoryInterface::class),
            $container->resolve(ChannelRepositoryInterface::class),
            $container->resolve(ParticipantRepositoryInterface::class)
        );
    });
    
    $container->bind(AnnouncementService::class, function($container) {
        return new AnnouncementService(
            $container->resolve(ChannelRepositoryInterface::class),
            $container->resolve(MessageRepositoryInterface::class),
            $container->resolve(ParticipantRepositoryInterface::class),
            $container->resolve(MessageService::class)
        );
    });
    
    // Register application service
    $container->bind(CommunicationService::class, function($container) {
        return new CommunicationService(
            $container->resolve(ChannelService::class),
            $container->resolve(MessageService::class),
            $container->resolve(AnnouncementService::class)
        );
    });
    
    // Register API controllers
    $container->bind(ChannelApiController::class, function($container) {
        return new ChannelApiController(
            $container->resolve(CommunicationService::class)
        );
    });
    
    $container->bind(MessageApiController::class, function($container) {
        return new MessageApiController(
            $container->resolve(CommunicationService::class)
        );
    });
    
    $container->bind(AnnouncementApiController::class, function($container) {
        return new AnnouncementApiController(
            $container->resolve(CommunicationService::class)
        );
    });
    
    $container->bind(NotificationApiController::class, function($container) {
        return new NotificationApiController(
            $container->resolve(CommunicationService::class)
        );
    });
};