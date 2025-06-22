<?php

declare(strict_types=1);

namespace YFEvents\Domain\Common;

/**
 * Base interface for all domain entities
 */
interface EntityInterface
{
    /**
     * Get the unique identifier for this entity
     */
    public function getId(): ?int;

    /**
     * Convert entity to array representation
     */
    public function toArray(): array;

    /**
     * Create entity from array data
     */
    public static function fromArray(array $data): static;
}