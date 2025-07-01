<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Database;

use YFEvents\Domain\Common\EntityInterface;
use YFEvents\Domain\Common\RepositoryInterface;

/**
 * Abstract base repository with common database operations
 */
abstract class AbstractRepository implements RepositoryInterface
{
    protected ConnectionInterface $connection;
    protected string $table;
    protected string $entityClass;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the table name for this repository
     */
    abstract protected function getTableName(): string;

    /**
     * Get the entity class name for this repository
     */
    abstract protected function getEntityClass(): string;

    public function findById(int $id): ?EntityInterface
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }

        $entityClass = $this->getEntityClass();
        return $entityClass::fromArray($data);
    }

    public function findAll(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $sql = "SELECT * FROM {$this->getTableName()}";
        $params = [];

        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                $conditions[] = "$field = :$field";
                $params[$field] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        if (!empty($orderBy)) {
            $orderClauses = [];
            foreach ($orderBy as $field => $direction) {
                $orderClauses[] = "$field $direction";
            }
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }

        if ($limit !== null) {
            $sql .= " LIMIT $limit";
            if ($offset !== null) {
                $sql .= " OFFSET $offset";
            }
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        $results = [];
        $entityClass = $this->getEntityClass();
        
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $entityClass::fromArray($data);
        }

        return $results;
    }

    public function findOneBy(array $criteria): ?EntityInterface
    {
        $results = $this->findBy($criteria, [], 1);
        return $results[0] ?? null;
    }

    public function save(EntityInterface $entity): EntityInterface
    {
        if ($entity->getId() === null) {
            return $this->insert($entity);
        } else {
            return $this->update($entity);
        }
    }

    public function delete(EntityInterface $entity): bool
    {
        if ($entity->getId() === null) {
            return false;
        }

        $sql = "DELETE FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute(['id' => $entity->getId()]);
    }

    public function count(array $criteria = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()}";
        $params = [];

        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                $conditions[] = "$field = :$field";
                $params[$field] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Insert new entity
     */
    protected function insert(EntityInterface $entity): EntityInterface
    {
        $data = $entity->toArray();
        unset($data['id']); // Remove ID for insert

        $fields = array_keys($data);
        $placeholders = array_map(fn($field) => ":$field", $fields);

        $sql = "INSERT INTO {$this->getTableName()} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($data);

        $id = (int) $this->connection->lastInsertId();
        
        // Return entity with new ID
        $data['id'] = $id;
        $entityClass = $this->getEntityClass();
        return $entityClass::fromArray($data);
    }

    /**
     * Update existing entity
     */
    protected function update(EntityInterface $entity): EntityInterface
    {
        $data = $entity->toArray();
        $id = $data['id'];
        unset($data['id']);

        $setParts = [];
        foreach (array_keys($data) as $field) {
            $setParts[] = "$field = :$field";
        }

        $sql = "UPDATE {$this->getTableName()} SET " . implode(', ', $setParts) . " WHERE id = :id";
        $data['id'] = $id;

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($data);

        return $entity;
    }
}