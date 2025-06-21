<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\ValueObjects;

/**
 * Channel type value object
 */
class ChannelType
{
    public const PUBLIC = 'public';
    public const PRIVATE = 'private';
    public const EVENT = 'event';
    public const VENDOR = 'vendor';
    public const ANNOUNCEMENT = 'announcement';
    
    private const VALID_TYPES = [
        self::PUBLIC,
        self::PRIVATE,
        self::EVENT,
        self::VENDOR,
        self::ANNOUNCEMENT
    ];
    
    private string $value;
    
    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid channel type: {$value}");
        }
        
        $this->value = $value;
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
    
    public function isPublic(): bool
    {
        return $this->value === self::PUBLIC;
    }
    
    public function isPrivate(): bool
    {
        return $this->value === self::PRIVATE;
    }
    
    public function isEvent(): bool
    {
        return $this->value === self::EVENT;
    }
    
    public function isVendor(): bool
    {
        return $this->value === self::VENDOR;
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