<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Scrapers;

use YakimaFinds\Domain\Events\Event;

class ScrapingResult
{
    private array $events = [];
    private array $errors = [];
    private array $warnings = [];
    private array $metadata = [];

    public function __construct(
        private bool $success,
        private string $sourceType,
        private ?string $message = null,
        private float $duration = 0.0
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Add scraped event
     */
    public function addEvent(Event $event): self
    {
        $this->events[] = $event;
        return $this;
    }

    /**
     * Add multiple events
     */
    public function addEvents(array $events): self
    {
        foreach ($events as $event) {
            if ($event instanceof Event) {
                $this->events[] = $event;
            }
        }
        return $this;
    }

    /**
     * Add error
     */
    public function addError(string $error): self
    {
        $this->errors[] = $error;
        return $this;
    }

    /**
     * Add warning
     */
    public function addWarning(string $warning): self
    {
        $this->warnings[] = $warning;
        return $this;
    }

    /**
     * Set metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Add metadata
     */
    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Set duration
     */
    public function setDuration(float $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    /**
     * Get event count
     */
    public function getEventCount(): int
    {
        return count($this->events);
    }

    /**
     * Get error count
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Get warning count
     */
    public function getWarningCount(): int
    {
        return count($this->warnings);
    }

    /**
     * Check if result has errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if result has warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get summary statistics
     */
    public function getStatistics(): array
    {
        return [
            'success' => $this->success,
            'events_found' => $this->getEventCount(),
            'errors' => $this->getErrorCount(),
            'warnings' => $this->getWarningCount(),
            'duration' => $this->duration,
            'events_per_second' => $this->duration > 0 ? $this->getEventCount() / $this->duration : 0
        ];
    }

    /**
     * Convert to array for logging/storage
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'source_type' => $this->sourceType,
            'message' => $this->message,
            'duration' => $this->duration,
            'event_count' => $this->getEventCount(),
            'events' => array_map(fn($event) => $event->toArray(), $this->events),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
            'statistics' => $this->getStatistics()
        ];
    }

    /**
     * Create successful result
     */
    public static function success(string $sourceType, string $message = null): self
    {
        return new self(true, $sourceType, $message);
    }

    /**
     * Create failed result
     */
    public static function failure(string $sourceType, string $message): self
    {
        return new self(false, $sourceType, $message);
    }

    /**
     * Create result with events
     */
    public static function withEvents(string $sourceType, array $events, string $message = null): self
    {
        $result = new self(true, $sourceType, $message);
        return $result->addEvents($events);
    }

    /**
     * Merge with another result
     */
    public function merge(ScrapingResult $other): self
    {
        $this->addEvents($other->getEvents());
        
        foreach ($other->getErrors() as $error) {
            $this->addError($error);
        }
        
        foreach ($other->getWarnings() as $warning) {
            $this->addWarning($warning);
        }
        
        $this->metadata = array_merge($this->metadata, $other->getMetadata());
        $this->duration += $other->getDuration();
        
        // Result is successful only if both are successful
        $this->success = $this->success && $other->isSuccess();
        
        return $this;
    }
}