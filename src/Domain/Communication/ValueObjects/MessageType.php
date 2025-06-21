<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\ValueObjects;

/**
 * Message type value object
 */
class MessageType
{
    public const TEXT = 'text';
    public const SYSTEM = 'system';
    public const ANNOUNCEMENT = 'announcement';
    
    private const VALID_TYPES = [
        self::TEXT,
        self::SYSTEM,
        self::ANNOUNCEMENT
    ];
    
    private string $value;
    
    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid message type: {$value}");
        }
        
        $this->value = $value;
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
    
    public function isText(): bool
    {
        return $this->value === self::TEXT;
    }
    
    public function isSystem(): bool
    {
        return $this->value === self::SYSTEM;
    }
    
    public function isAnnouncement(): bool
    {
        return $this->value === self::ANNOUNCEMENT;
    }
    
    public function __toString(): string
    {
        return $this->value;
    }
}