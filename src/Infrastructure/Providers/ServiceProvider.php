<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Providers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Infrastructure\Config\Config;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use YFEvents\Infrastructure\Database\Connection;
use YFEvents\Domain\Events\EventRepositoryInterface;
use YFEvents\Infrastructure\Repositories\EventRepository;
use YFEvents\Domain\Events\EventServiceInterface;
use YFEvents\Domain\Events\EventService;
use YFEvents\Domain\Shops\ShopRepositoryInterface;
use YFEvents\Infrastructure\Repositories\ShopRepository;
use YFEvents\Domain\Shops\ShopServiceInterface;
use YFEvents\Domain\Shops\ShopService;
use YFEvents\Domain\Admin\AdminServiceInterface;
use YFEvents\Domain\Admin\AdminService;
use YFEvents\Domain\Claims\SaleRepositoryInterface;
use YFEvents\Domain\Claims\ItemRepositoryInterface;
use YFEvents\Domain\Claims\OfferRepositoryInterface;
use YFEvents\Domain\Claims\SellerRepositoryInterface;
use YFEvents\Infrastructure\Repositories\Claims\SaleRepository;
use YFEvents\Infrastructure\Repositories\Claims\ItemRepository;
use YFEvents\Infrastructure\Repositories\Claims\OfferRepository;
use YFEvents\Infrastructure\Repositories\Claims\SellerRepository;
use YFEvents\Application\Services\ClaimService;
use YFEvents\Application\Services\CalendarService;
use YFEvents\Infrastructure\Services\QRCodeService;
use YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface;
use YFEvents\Domain\Communication\Repositories\MessageRepositoryInterface;
use YFEvents\Domain\Communication\Repositories\ParticipantRepositoryInterface;
use YFEvents\Infrastructure\Repositories\Communication\ChannelRepository;
use YFEvents\Infrastructure\Repositories\Communication\MessageRepository;
use YFEvents\Infrastructure\Repositories\Communication\ParticipantRepository;
use YFEvents\Domain\Communication\Services\ChannelService;
use YFEvents\Domain\Communication\Services\MessageService;
use YFEvents\Domain\Communication\Services\AnnouncementService;
use YFEvents\Application\Services\Communication\CommunicationService;
use YFEvents\Application\Services\Communication\AdminSellerChatService;
use YFEvents\Application\Services\YFClaim\InquiryService;
use YFEvents\Domain\YFClaim\Repositories\InquiryRepositoryInterface;
use YFEvents\Infrastructure\Repositories\YFClaim\InquiryRepository;
use YFEvents\Infrastructure\Discovery\RequestTracker;
use YakimaFinds\Utils\SystemLogger;

/**
 * Main service provider for dependency injection
 */
class ServiceProvider
{
    private ?SystemLogger $logger = null;
    private bool $runtimeDiscoveryEnabled;
    
    public function __construct(private ContainerInterface $container) 
    {
        $this->runtimeDiscoveryEnabled = getenv('ENABLE_RUNTIME_DISCOVERY') === 'true';
    }

    /**
     * Register all services in the container
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerDatabase();
        
        // Initialize logger after database is available
        $this->initializeLogger();
        
        $this->registerRepositories();
        $this->registerServices();
        
        if ($this->logger) {
            $this->logger->info('SERVICE_PROVIDER_COMPLETE', [
                'request_id' => RequestTracker::getRequestId()
            ]);
        }
    }
    
    /**
     * Initialize logger after database is available
     */
    private function initializeLogger(): void
    {
        if ($this->runtimeDiscoveryEnabled && !$this->logger) {
            try {
                $db = $this->container->resolve(\PDO::class);
                $this->logger = SystemLogger::create($db, 'runtime_discovery');
            } catch (\Exception $e) {
                error_log("[runtime_discovery] ServiceProvider: Failed to initialize logger: " . $e->getMessage());
            }
        }
    }

    /**
     * Register configuration services
     */
    private function registerConfig(): void
    {
        $this->container->singleton(ConfigInterface::class, function ($container) {
            $config = new Config();
            
            // Load configuration files
            $configPath = __DIR__ . '/../../../config';
            
            // Debug: check if config path exists
            if (!is_dir($configPath)) {
                throw new \RuntimeException("Config directory not found: $configPath");
            }
            
            if (file_exists($configPath . '/app.php')) {
                $config->loadFromFile($configPath . '/app.php');
            }
            
            if (file_exists($configPath . '/database.php')) {
                $config->loadFromFile($configPath . '/database.php');
            }

            // Load environment variables
            if (file_exists(__DIR__ . '/../../../.env')) {
                $this->loadEnvironment($config);
            }

            return $config;
        });
    }

    /**
     * Register database services
     */
    private function registerDatabase(): void
    {
        $this->container->singleton(ConnectionInterface::class, function ($container) {
            $config = $container->resolve(ConfigInterface::class);
            
            return new Connection(
                host: $config->get('database.host', 'localhost'),
                database: $config->get('database.name', 'yakima_finds'),
                username: $config->get('database.username', 'yfevents'),
                password: $config->get('database.password', ''),
                options: $config->get('database.options', [])
            );
        });
        
        // Register PDO for services that need it directly
        $this->container->singleton(\PDO::class, function ($container) {
            $connection = $container->resolve(ConnectionInterface::class);
            return $connection->getConnection();
        });
    }

    /**
     * Register repository services
     */
    private function registerRepositories(): void
    {
        $this->container->bind(EventRepositoryInterface::class, function ($container) {
            return new EventRepository($container->resolve(ConnectionInterface::class));
        });

        $this->container->bind(ShopRepositoryInterface::class, function ($container) {
            return new ShopRepository($container->resolve(ConnectionInterface::class));
        });
        
        // Claims repositories
        $this->container->bind(SaleRepositoryInterface::class, function ($container) {
            return new SaleRepository($container->resolve(ConnectionInterface::class));
        });
        
        $this->container->bind(ItemRepositoryInterface::class, function ($container) {
            return new ItemRepository($container->resolve(ConnectionInterface::class));
        });
        
        $this->container->bind(OfferRepositoryInterface::class, function ($container) {
            return new OfferRepository($container->resolve(ConnectionInterface::class));
        });
        
        $this->container->bind(SellerRepositoryInterface::class, function ($container) {
            return new SellerRepository($container->resolve(ConnectionInterface::class));
        });
        
        // Communication repositories
        $this->container->bind(ChannelRepositoryInterface::class, function ($container) {
            return new ChannelRepository($container->resolve(ConnectionInterface::class));
        });
        
        $this->container->bind(MessageRepositoryInterface::class, function ($container) {
            return new MessageRepository($container->resolve(ConnectionInterface::class));
        });
        
        $this->container->bind(ParticipantRepositoryInterface::class, function ($container) {
            return new ParticipantRepository($container->resolve(ConnectionInterface::class));
        });
        
        // YFClaim repositories
        $this->container->bind(InquiryRepositoryInterface::class, function ($container) {
            return new InquiryRepository($container->resolve(ConnectionInterface::class));
        });
    }

    /**
     * Register domain services
     */
    private function registerServices(): void
    {
        $this->container->bind(EventServiceInterface::class, function ($container) {
            return new EventService(
                $container->resolve(EventRepositoryInterface::class),
                $container->resolve(ConnectionInterface::class)
            );
        });

        $this->container->bind(ShopServiceInterface::class, function ($container) {
            return new ShopService(
                $container->resolve(ShopRepositoryInterface::class),
                $container->resolve(ConnectionInterface::class)
            );
        });

        $this->container->bind(AdminServiceInterface::class, function ($container) {
            return new AdminService(
                $container->resolve(EventServiceInterface::class),
                $container->resolve(ShopServiceInterface::class),
                $container->resolve(ConnectionInterface::class)
            );
        });
        
        // QR Code Service
        $this->container->singleton(QRCodeService::class, function ($container) {
            return new QRCodeService($container->resolve(ConfigInterface::class));
        });
        
        // Claim Service
        $this->container->bind(ClaimService::class, function ($container) {
            return new ClaimService(
                $container->resolve(SaleRepositoryInterface::class),
                $container->resolve(ItemRepositoryInterface::class),
                $container->resolve(QRCodeService::class)
            );
        });
        
        // Calendar Service
        $this->container->bind(CalendarService::class, function ($container) {
            return new CalendarService(
                $container->resolve(EventServiceInterface::class),
                $container->resolve(ClaimService::class)
            );
        });
        
        // Communication domain services
        $this->container->bind(ChannelService::class, function ($container) {
            return new ChannelService(
                $container->resolve(ChannelRepositoryInterface::class),
                $container->resolve(ParticipantRepositoryInterface::class)
            );
        });
        
        $this->container->bind(MessageService::class, function ($container) {
            return new MessageService(
                $container->resolve(MessageRepositoryInterface::class),
                $container->resolve(ChannelRepositoryInterface::class),
                $container->resolve(ParticipantRepositoryInterface::class)
            );
        });
        
        $this->container->bind(AnnouncementService::class, function ($container) {
            return new AnnouncementService(
                $container->resolve(ChannelRepositoryInterface::class),
                $container->resolve(MessageRepositoryInterface::class),
                $container->resolve(ParticipantRepositoryInterface::class),
                $container->resolve(MessageService::class)
            );
        });
        
        // Communication application service
        $this->container->bind(CommunicationService::class, function ($container) {
            return new CommunicationService(
                $container->resolve(ChannelService::class),
                $container->resolve(MessageService::class),
                $container->resolve(AnnouncementService::class)
            );
        });
        
        // YFClaim services
        $this->container->bind(InquiryService::class, function ($container) {
            $connection = $container->resolve(ConnectionInterface::class);
            return new InquiryService(
                $container->resolve(InquiryRepositoryInterface::class),
                $connection->getConnection()
            );
        });
        
        // Auth Service
        $this->container->singleton(\YFEvents\Application\Services\AuthService::class, function ($container) {
            return new \YFEvents\Application\Services\AuthService(
                $container->resolve(\PDO::class)
            );
        });
    }

    /**
     * Load environment variables into config
     */
    private function loadEnvironment(ConfigInterface $config): void
    {
        $envFile = __DIR__ . '/../../../.env';
        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '"\'');
                
                // Map environment variables to config structure
                switch ($key) {
                    case 'DB_HOST':
                        $config->set('database.host', $value);
                        break;
                    case 'DB_NAME':
                        $config->set('database.name', $value);
                        break;
                    case 'DB_USER':
                        $config->set('database.username', $value);
                        break;
                    case 'DB_PASS':
                        $config->set('database.password', $value);
                        break;
                    case 'APP_ENV':
                        $config->set('app.environment', $value);
                        break;
                    case 'APP_DEBUG':
                        $config->set('app.debug', $value === 'true');
                        break;
                    case 'LOG_LEVEL':
                        $config->set('logging.level', $value);
                        break;
                }
            }
        }
    }
}