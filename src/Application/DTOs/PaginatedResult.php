<?php

declare(strict_types=1);

namespace YFEvents\Application\DTOs;

/**
 * Data Transfer Object for paginated results
 * Used by services to return paginated data with metadata
 */
class PaginatedResult
{
    /**
     * @param array $items The items for the current page
     * @param int $total Total number of items across all pages
     * @param int $page Current page number (1-based)
     * @param int $perPage Number of items per page
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage
    ) {}
    
    /**
     * Get items for current page
     */
    public function getItems(): array
    {
        return $this->items;
    }
    
    /**
     * Get total number of items
     */
    public function getTotal(): int
    {
        return $this->total;
    }
    
    /**
     * Get current page number
     */
    public function getPage(): int
    {
        return $this->page;
    }
    
    /**
     * Get items per page
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }
    
    /**
     * Calculate total number of pages
     */
    public function getTotalPages(): int
    {
        return (int)ceil($this->total / max(1, $this->perPage));
    }
    
    /**
     * Check if there is a next page
     */
    public function hasNextPage(): bool
    {
        return $this->page < $this->getTotalPages();
    }
    
    /**
     * Check if there is a previous page
     */
    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }
    
    /**
     * Get offset for database queries
     */
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}