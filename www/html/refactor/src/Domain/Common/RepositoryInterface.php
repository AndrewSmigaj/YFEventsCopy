<?php

declare(strict_types=1);

namespace YFEvents\Domain\Common;

/**
 * Base repository interface for data access
 */
interface RepositoryInterface
{
    /**
     * Find entity by ID
     */
    public function findById(int $id): ?EntityInterface;

    /**
     * Find all entities with optional criteria
     */
    public function findAll(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array;

    /**
     * Find entities by specific criteria
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array;

    /**
     * Find single entity by criteria
     */
    public function findOneBy(array $criteria): ?EntityInterface;

    /**
     * Save entity (create or update)
     */
    public function save(EntityInterface $entity): EntityInterface;

    /**
     * Delete entity
     */
    public function delete(EntityInterface $entity): bool;

    /**
     * Count entities matching criteria
     */
    public function count(array $criteria = []): int;
}