<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Discovery;

/**
 * Simple request tracker for runtime discovery
 * Generates unique request IDs to link all logs for a single request
 */
class RequestTracker
{
    private static ?string $requestId = null;
    
    /**
     * Get or generate request ID for current request
     */
    public static function getRequestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = uniqid('req_', true);
        }
        return self::$requestId;
    }
    
    /**
     * Reset request ID (useful for testing)
     */
    public static function reset(): void
    {
        self::$requestId = null;
    }
}