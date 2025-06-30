<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Database;

use PDO;

/**
 * Database connection interface
 */
interface ConnectionInterface
{
    /**
     * Get PDO connection
     */
    public function getConnection(): PDO;

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool;

    /**
     * Commit transaction
     */
    public function commit(): bool;

    /**
     * Rollback transaction
     */
    public function rollback(): bool;

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool;

    /**
     * Execute query and return statement
     */
    public function prepare(string $sql): \PDOStatement;

    /**
     * Execute query with parameters
     */
    public function execute(string $sql, array $params = []): \PDOStatement;

    /**
     * Get last inserted ID
     */
    public function lastInsertId(): string;
}