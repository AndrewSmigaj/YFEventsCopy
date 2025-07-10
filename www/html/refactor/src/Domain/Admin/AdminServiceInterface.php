<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Admin;

/**
 * Service interface for admin system management
 */
interface AdminServiceInterface
{
    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStatistics(): array;

    /**
     * Get system health information
     */
    public function getSystemHealth(): array;

    /**
     * Get recent activity across all domains
     */
    public function getRecentActivity(int $limit = 50): array;

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array;

    /**
     * Get content moderation queue
     */
    public function getModerationQueue(): array;

    /**
     * Get user activity statistics
     */
    public function getUserActivityStats(): array;

    /**
     * Get content statistics by date range
     */
    public function getContentStatsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array;

    /**
     * Get top performing content
     */
    public function getTopPerformingContent(): array;

    /**
     * Get system alerts and warnings
     */
    public function getSystemAlerts(): array;

    /**
     * Export system data
     */
    public function exportSystemData(string $format = 'json', array $filters = []): array;
}