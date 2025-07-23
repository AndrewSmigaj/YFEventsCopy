<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Container;

use ReflectionClass;
use ReflectionParameter;
use InvalidArgumentException;
use RuntimeException;
use YFEvents\Infrastructure\Discovery\RequestTracker;
use YakimaFinds\Utils\SystemLogger;

/**
 * Simple dependency injection container
 */
class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];
    private ?SystemLogger $logger = null;
    private bool $runtimeDiscoveryEnabled;

    public function __construct()
    {
        // Check if runtime discovery is enabled
        $this->runtimeDiscoveryEnabled = getenv('ENABLE_RUNTIME_DISCOVERY') === 'true';
        
        // Logger will be initialized later when PDO is available
    }

    /**
     * Initialize logger after PDO is available
     */
    private function initializeLogger(): void
    {
        if ($this->runtimeDiscoveryEnabled && !$this->logger && isset($this->instances[\PDO::class])) {
            try {
                $this->logger = SystemLogger::create($this->instances[\PDO::class], 'runtime_discovery');
            } catch (\Exception $e) {
                error_log("[runtime_discovery] Container: Failed to initialize logger: " . $e->getMessage());
            }
        }
    }

    public function bind(string $abstract, string|callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, string|callable $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function resolve(string $abstract): object
    {
        // Try to initialize logger if not done yet
        $this->initializeLogger();
        
        // Return existing instance if bound
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check for singleton
        if (isset($this->singletons[$abstract])) {
            if (!isset($this->instances[$abstract])) {
                $instance = $this->build($this->singletons[$abstract]);
                $this->instances[$abstract] = $instance;
                $this->logResolution($abstract, get_class($instance), 'singleton');
            }
            return $this->instances[$abstract];
        }

        // Check for regular binding
        if (isset($this->bindings[$abstract])) {
            $instance = $this->build($this->bindings[$abstract]);
            $this->logResolution($abstract, get_class($instance), 'binding');
            return $instance;
        }

        // Try to auto-resolve if it's a class
        if (class_exists($abstract)) {
            $instance = $this->build($abstract);
            $this->logResolution($abstract, get_class($instance), 'auto-resolve');
            return $instance;
        }

        throw new RuntimeException("Unable to resolve binding for: {$abstract}");
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || 
               isset($this->singletons[$abstract]) || 
               isset($this->instances[$abstract]) ||
               class_exists($abstract);
    }

    public function getBindings(): array
    {
        return array_merge($this->bindings, $this->singletons, array_keys($this->instances));
    }

    /**
     * Log service resolution
     */
    private function logResolution(string $abstract, string $concrete, string $type): void
    {
        if ($this->logger) {
            // Extract namespace from concrete class
            $namespace = substr($concrete, 0, strrpos($concrete, '\\') ?: 0);
            
            $this->logger->info('SERVICE_RESOLVED', [
                'abstract' => $abstract,
                'concrete' => $concrete,
                'namespace' => $namespace,
                'type' => $type,
                'request_id' => RequestTracker::getRequestId()
            ]);
        }
    }

    /**
     * Build an instance of the given concrete type
     */
    private function build(string|callable $concrete): object
    {
        // If it's a callable, call it with the container
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        // If it's a string, try to build the class
        if (!class_exists($concrete)) {
            throw new InvalidArgumentException("Class {$concrete} does not exist");
        }

        $reflector = new ReflectionClass($concrete);

        // Check if class is instantiable
        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Class {$concrete} is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        // If no constructor, just instantiate
        if (is_null($constructor)) {
            return new $concrete;
        }

        // Get constructor parameters
        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveDependency($parameter);
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Resolve a single dependency
     */
    private function resolveDependency(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        // If no type hint, check for default value
        if (!$type) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new RuntimeException("Cannot resolve parameter {$parameter->getName()} without type hint");
        }

        // Handle union types (PHP 8+)
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if (!$unionType->isBuiltin()) {
                    return $this->resolve($unionType->getName());
                }
            }
        }

        // Handle named types
        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();

            // If it's a built-in type, check for default value
            if ($type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }
                throw new RuntimeException("Cannot resolve built-in type {$typeName} for parameter {$parameter->getName()}");
            }

            // Try to resolve the class
            return $this->resolve($typeName);
        }

        throw new RuntimeException("Cannot resolve parameter {$parameter->getName()}");
    }
}