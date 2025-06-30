<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Providers;

use YakimaFinds\Infrastructure\Container\ContainerInterface;
use YakimaFinds\Infrastructure\Config\ConfigInterface;
use YakimaFinds\Infrastructure\Config\Config;
use YakimaFinds\Infrastructure\Database\ConnectionInterface;
use YakimaFinds\Infrastructure\Database\Connection;
use YakimaFinds\Domain\Events\EventRepositoryInterface;
use YakimaFinds\Infrastructure\Repositories\EventRepository;
use YakimaFinds\Domain\Events\EventServiceInterface;
use YakimaFinds\Domain\Events\EventService;
use YakimaFinds\Domain\Shops\ShopRepositoryInterface;
use YakimaFinds\Infrastructure\Repositories\ShopRepository;
use YakimaFinds\Domain\Shops\ShopServiceInterface;
use YakimaFinds\Domain\Shops\ShopService;
use YakimaFinds\Domain\Admin\AdminServiceInterface;
use YakimaFinds\Domain\Admin\AdminService;

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