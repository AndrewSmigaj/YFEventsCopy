<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories;

use YFEvents\Domain\Events\Event;
use YFEvents\Domain\Events\EventRepositoryInterface;
use YFEvents\Infrastructure\Database\AbstractRepository;
use DateTimeInterface;
use DateTime;

/**
 * Event repository implementation
 */
class EventRepository extends AbstractRepository implements EventRepositoryInterface
{
    protected function getTableName(): string
    {
        return 'events';
    }

    protected function getEntityClass(): string
    {
        return Event::class;
    }

    public function findByDateRange(DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE start_datetime BETWEEN :start_date AND :end_date 
                ORDER BY start_datetime ASC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s')
        ]);

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Event::fromArray($data);
        }

        return $results;
    }

    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status], ['start_datetime' => 'ASC']);
    }

    public function findFeatured(int $limit = 10): array
    {
        return $this->findBy(
            ['featured' => 1, 'status' => 'approved'],
            ['start_datetime' => 'ASC'],
            $limit
        );
    }

    public function findUpcoming(int $limit = 10): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE start_datetime > NOW() AND status = 'approved'
                ORDER BY start_datetime ASC 
                LIMIT :limit";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Event::fromArray($data);
        }

        return $results;
    }

    public function findNearLocation(float $latitude, float $longitude, float $radiusMiles = 10): array
    {
        $sql = "SELECT *, 
                (6371 * acos(cos(radians(:lat)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(:lng)) + 
                sin(radians(:lat)) * sin(radians(latitude)))) AS distance 
                FROM {$this->getTableName()} 
                WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                AND status = 'approved'
                HAVING distance <= :radius 
                ORDER BY distance ASC, start_datetime ASC";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'lat' => $latitude,
            'lng' => $longitude,
            'radius' => $radiusMiles
        ]);

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // Remove distance from data before creating entity
            unset($data['distance']);
            $results[] = Event::fromArray($data);
        }

        return $results;
    }

    public function findBySource(int $sourceId): array
    {
        return $this->findBy(['source_id' => $sourceId], ['start_datetime' => 'ASC']);
    }

    public function findByExternalEventId(string $externalEventId): ?Event
    {
        $result = $this->findOneBy(['external_event_id' => $externalEventId]);
        return $result instanceof Event ? $result : null;
    }

    public function search(string $query, array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE 1=1";
        $params = [];

        // Text search
        if (!empty($query)) {
            $sql .= " AND (title LIKE :query OR description LIKE :query OR location LIKE :query)";
            $params['query'] = "%{$query}%";
        }

        // Apply filters
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params['status'] = $filters['status'];
        }

        if (isset($filters['featured'])) {
            $sql .= " AND featured = :featured";
            $params['featured'] = $filters['featured'] ? 1 : 0;
        }

        if (isset($filters['start_date'])) {
            $sql .= " AND start_datetime >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $sql .= " AND start_datetime <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        if (isset($filters['source_id'])) {
            $sql .= " AND source_id = :source_id";
            $params['source_id'] = $filters['source_id'];
        }

        $sql .= " ORDER BY start_datetime ASC";

        if (isset($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params['limit'] = (int) $filters['limit'];
        }

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === 'limit') {
                $stmt->bindValue(":$key", $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$key", $value);
            }
        }
        $stmt->execute();

        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Event::fromArray($data);
        }

        return $results;
    }

    public function getEventsByDate(DateTimeInterface $date): array
    {
        $startOfDay = (clone $date)->setTime(0, 0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);

        return $this->findByDateRange($startOfDay, $endOfDay);
    }

    public function countByStatus(): array
    {
        $sql = "SELECT status, COUNT(*) as count FROM {$this->getTableName()} GROUP BY status";
        $stmt = $this->connection->execute($sql);
        
        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[$row['status']] = (int) $row['count'];
        }

        return $results;
    }
}