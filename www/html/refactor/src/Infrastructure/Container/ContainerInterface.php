<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Container;

/**
 * Dependency injection container interface
 */
interface ContainerInterface
{
    /**
     * Bind a concrete implementation to an abstract identifier
     */
    public function bind(string $abstract, string|callable $concrete): void;

    /**
     * Bind a singleton instance
     */
    public function singleton(string $abstract, string|callable $concrete): void;

    /**
     * Bind an existing instance
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * Resolve a dependency from the container
     */
    public function resolve(string $abstract): object;

    /**
     * Check if a binding exists
     */
    public function has(string $abstract): bool;

    /**
     * Get all bindings
     */
    public function getBindings(): array;
}