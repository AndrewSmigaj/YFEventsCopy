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
use YFEvents\Infrastructure\Repositories\Claims\SaleRepository;
use YFEvents\Infrastructure\Repositories\Claims\ItemRepository;
use YFEvents\Infrastructure\Repositories\Claims\OfferRepository;
use YFEvents\Application\Services\ClaimService;
use YFEvents\Application\Services\CalendarService;
use YFEvents\Infrastructure\Services\QRCodeService;

/**
 * Main service provider for dependency injection
 */
class ServiceProvider
{
    public function __construct(private ContainerInterface $container) {}

    /**
     * Register all services in the container
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerDatabase();
        $this->registerRepositories();
        $this->registerServices();
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