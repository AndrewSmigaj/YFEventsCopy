<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Scrapers;

use YakimaFinds\Domain\Common\EntityInterface;
use DateTimeInterface;
use DateTime;

class ScrapingSource implements EntityInterface
{
    public function __construct(
        private ?int $id,
        private string $name,
        private string $url,
        private string $type,
        private array $configuration,
        private bool $active,
        private int $priority,
        private string $schedule,
        private ?DateTimeInterface $lastScraped,
        private ?DateTimeInterface $lastSuccess,
        private int $successCount,
        private int $errorCount,
        private ?string $lastError,
        private array $metadata,
        private DateTimeInterface $createdAt,
        private ?DateTimeInterface $updatedAt = null
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getSchedule(): string
    {
        return $this->schedule;
    }

    public function getLastScraped(): ?DateTimeInterface
    {
        return $this->lastScraped;
    }

    public function getLastSuccess(): ?DateTimeInterface
    {
        return $this->lastSuccess;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Get configuration value with dot notation
     */
    public function getConfig(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->configuration;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }

    /**
     * Check if source is healthy
     */
    public function isHealthy(): bool
    {
        if (!$this->active) {
            return false;
        }
        
        // Consider healthy if success rate > 80%
        $totalAttempts = $this->successCount + $this->errorCount;
        if ($totalAttempts === 0) {
            return true; // New source
        }
        
        return ($this->successCount / $totalAttempts) >= 0.8;
    }

    /**
     * Check if source needs scraping
     */
    public function needsScraping(): bool
    {
        if (!$this->active) {
            return false;
        }
        
        if (!$this->lastScraped) {
            return true; // Never scraped
        }
        
        // Parse schedule (for now, assume hourly if not specified)
        $interval = $this->parseScheduleInterval();
        $nextRun = clone $this->lastScraped;
        $nextRun->modify("+{$interval} minutes");
        
        return new DateTime() >= $nextRun;
    }

    /**
     * Get success rate as percentage
     */
    public function getSuccessRate(): float
    {
        $total = $this->successCount + $this->errorCount;
        if ($total === 0) {
            return 100.0;
        }
        
        return ($this->successCount / $total) * 100;
    }

    /**
     * Record successful scrape
     */
    public function recordSuccess(int $eventsFound): self
    {
        return $this->update([
            'last_scraped' => (new DateTime())->format('Y-m-d H:i:s'),
            'last_success' => (new DateTime())->format('Y-m-d H:i:s'),
            'success_count' => $this->successCount + 1,
            'last_error' => null,
            'metadata' => array_merge($this->metadata, [
                'last_events_found' => $eventsFound,
                'last_scrape_duration' => $this->metadata['last_scrape_duration'] ?? null
            ])
        ]);
    }

    /**
     * Record failed scrape
     */
    public function recordError(string $error): self
    {
        return $this->update([
            'last_scraped' => (new DateTime())->format('Y-m-d H:i:s'),
            'error_count' => $this->errorCount + 1,
            'last_error' => $error
        ]);
    }

    /**
     * Update scrape timing
     */
    public function updateTiming(float $duration): self
    {
        return $this->update([
            'metadata' => array_merge($this->metadata, [
                'last_scrape_duration' => $duration,
                'avg_duration' => $this->calculateAverageDuration($duration)
            ])
        ]);
    }

    /**
     * Activate source
     */
    public function activate(): self
    {
        return $this->update(['active' => true]);
    }

    /**
     * Deactivate source
     */
    public function deactivate(): self
    {
        return $this->update(['active' => false]);
    }

    /**
     * Update configuration
     */
    public function updateConfiguration(array $config): self
    {
        return $this->update(['configuration' => $config]);
    }

    /**
     * Update source data
     */
    public function update(array $data): self
    {
        return new self(
            id: $this->id,
            name: $data['name'] ?? $this->name,
            url: $data['url'] ?? $this->url,
            type: $data['type'] ?? $this->type,
            configuration: $data['configuration'] ?? $this->configuration,
            active: $data['active'] ?? $this->active,
            priority: $data['priority'] ?? $this->priority,
            schedule: $data['schedule'] ?? $this->schedule,
            lastScraped: isset($data['last_scraped']) ? new DateTime($data['last_scraped']) : $this->lastScraped,
            lastSuccess: isset($data['last_success']) ? new DateTime($data['last_success']) : $this->lastSuccess,
            successCount: $data['success_count'] ?? $this->successCount,
            errorCount: $data['error_count'] ?? $this->errorCount,
            lastError: $data['last_error'] ?? $this->lastError,
            metadata: $data['metadata'] ?? $this->metadata,
            createdAt: $this->createdAt,
            updatedAt: new DateTime()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'type' => $this->type,
            'configuration' => $this->configuration,
            'active' => $this->active,
            'priority' => $this->priority,
            'schedule' => $this->schedule,
            'last_scraped' => $this->lastScraped?->format('Y-m-d H:i:s'),
            'last_success' => $this->lastSuccess?->format('Y-m-d H:i:s'),
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'last_error' => $this->lastError,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'success_rate' => $this->getSuccessRate(),
            'is_healthy' => $this->isHealthy(),
            'needs_scraping' => $this->needsScraping()
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'],
            url: $data['url'],
            type: $data['type'],
            configuration: $data['configuration'] ?? [],
            active: $data['active'] ?? true,
            priority: $data['priority'] ?? 5,
            schedule: $data['schedule'] ?? '0 */6 * * *',
            lastScraped: isset($data['last_scraped']) ? new DateTime($data['last_scraped']) : null,
            lastSuccess: isset($data['last_success']) ? new DateTime($data['last_success']) : null,
            successCount: $data['success_count'] ?? 0,
            errorCount: $data['error_count'] ?? 0,
            lastError: $data['last_error'] ?? null,
            metadata: $data['metadata'] ?? [],
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : new DateTime(),
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }

    private function parseScheduleInterval(): int
    {
        // Simple cron parsing - in production would use a proper cron parser
        // For now, default to 6 hours (360 minutes)
        return 360;
    }

    private function calculateAverageDuration(float $newDuration): float
    {
        $currentAvg = $this->metadata['avg_duration'] ?? $newDuration;
        $totalScrapes = $this->successCount + $this->errorCount + 1;
        
        return (($currentAvg * ($totalScrapes - 1)) + $newDuration) / $totalScrapes;
    }
}