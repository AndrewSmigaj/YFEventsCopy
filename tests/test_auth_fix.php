<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Container\Container;
use YFEvents\Infrastructure\Config\Config;
use YFEvents\Infrastructure\Providers\ServiceProvider;

echo "Testing Communication API Auth Fix\n";
echo "==================================\n\n";

try {
    // Bootstrap
    $container = new Container();
    $config = new Config();
    
    // Register container
    $container->singleton(\YFEvents\Infrastructure\Container\ContainerInterface::class, function() use ($container) {
        return $container;
    });
    
    // Register services
    $serviceProvider = new ServiceProvider($container);
    $serviceProvider->register();
    
    // Test that controllers can be instantiated
    $controllers = [
        'ChannelApiController',
        'MessageApiController', 
        'AnnouncementApiController',
        'NotificationApiController'
    ];
    
    foreach ($controllers as $controllerName) {
        $className = "\\YFEvents\\Presentation\\Api\\Controllers\\Communication\\$controllerName";
        $controller = $container->resolve($className);
        echo "✅ $controllerName instantiated successfully\n";
        
        // Check that it has the right parent class
        if ($controller instanceof \YFEvents\Presentation\Http\Controllers\BaseController) {
            echo "  ✓ Extends BaseController\n";
        } else {
            echo "  ✗ Does NOT extend BaseController!\n";
        }
    }
    
    echo "\n✅ All API controllers updated successfully!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}