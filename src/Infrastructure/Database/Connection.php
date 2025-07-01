<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database connection implementation
 */
class Connection implements ConnectionInterface
{
    private PDO $pdo;

    public function __construct(
        private string $host,
        private string $database,
        private string $username,
        private string $password,
        private array $options = []
    ) {
        $this->connect();
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    public function prepare(string $sql): \PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    public function execute(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Establish database connection
     */
    private function connect(): void
    {
        $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
        
        $defaultOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        // Use default options for now (options parameter was causing JSON encoding issues)
        $options = $defaultOptions;

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }
}