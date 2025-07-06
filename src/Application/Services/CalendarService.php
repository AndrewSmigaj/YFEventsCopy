<?php

declare(strict_types=1);

namespace YFEvents\Application\Services;

use YFEvents\Domain\Events\EventServiceInterface;
use DateTimeInterface;
use DateTime;

/**
 * Service for aggregating calendar data from multiple sources
 * Combines events and estate sales into a unified calendar view
 */
class CalendarService
{
    public function __construct(
        private readonly EventServiceInterface $eventService,
        private readonly ClaimService $claimService
    ) {}

    /**
     * Get unified calendar data combining events and estate sales
     * 
     * @param DateTimeInterface $startDate Start of date range
     * @param DateTimeInterface $endDate End of date range
     * @param array $filters Optional filters ['types' => ['event', 'sale']]
     * @return array Array of calendar items in unified format
     */
    public function getUnifiedCalendarData(
        DateTimeInterface $startDate, 
        DateTimeInterface $endDate, 
        array $filters = []
    ): array {
        $calendarItems = [];
        
        // Get types to include (default to both)
        $includeTypes = $filters['types'] ?? ['event', 'sale'];
        
        // Get events if requested
        if (in_array('event', $includeTypes)) {
            $events = $this->getEventsForCalendar($startDate, $endDate);
            $calendarItems = array_merge($calendarItems, $events);
        }
        
        // Get estate sales if requested
        if (in_array('sale', $includeTypes)) {
            $sales = $this->getSalesForCalendar($startDate, $endDate);
            $calendarItems = array_merge($calendarItems, $sales);
        }
        
        // Sort by start date
        usort($calendarItems, function($a, $b) {
            return strtotime($a['start']) - strtotime($b['start']);
        });
        
        return $calendarItems;
    }
    
    /**
     * Get events formatted for calendar display
     */
    private function getEventsForCalendar(
        DateTimeInterface $startDate, 
        DateTimeInterface $endDate
    ): array {
        // Use existing event service to get events
        $filters = [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'status' => 'approved'
        ];
        
        $events = $this->eventService->searchEvents('', $filters);
        
        // Transform to calendar format
        $calendarEvents = [];
        foreach ($events as $event) {
            $calendarEvents[] = [
                'id' => 'event_' . $event->getId(),
                'type' => 'event',
                'title' => $event->getTitle(),
                'start' => $event->getStartDateTime()->format('c'),
                'end' => $event->getEndDateTime()->format('c'),
                'description' => $event->getDescription(),
                'location' => $event->getLocation(),
                'url' => '/events/' . $event->getId(),
                'className' => 'calendar-event',
                'backgroundColor' => '#667eea',
                'borderColor' => '#5a67d8',
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'source' => 'events',
                    'address' => $event->getAddress(),
                    'coordinates' => [
                        'lat' => $event->getLatitude(),
                        'lng' => $event->getLongitude()
                    ],
                    'externalUrl' => $event->getExternalUrl()
                ]
            ];
        }
        
        return $calendarEvents;
    }
    
    /**
     * Get estate sales formatted for calendar display
     */
    private function getSalesForCalendar(
        DateTimeInterface $startDate, 
        DateTimeInterface $endDate
    ): array {
        // Get sales from claim service
        $sales = $this->claimService->getSalesForCalendar($startDate, $endDate);
        
        // Transform to calendar format
        $calendarSales = [];
        foreach ($sales as $sale) {
            // Determine sale phase and dates
            $now = new DateTime();
            $saleStart = new DateTime($sale->getClaimStartDate());
            $saleEnd = new DateTime($sale->getClaimEndDate());
            
            // Check if preview period exists and is active
            $previewStartDate = $sale->getPreviewStartDate();
            $previewEndDate = $sale->getPreviewEndDate();
            
            if ($previewStartDate && $previewEndDate) {
                $previewStart = $previewStartDate instanceof DateTime ? $previewStartDate : new DateTime($previewStartDate);
                $previewEnd = $previewEndDate instanceof DateTime ? $previewEndDate : new DateTime($previewEndDate);
                
                if ($previewStart <= $endDate && $previewEnd >= $startDate) {
                    $calendarSales[] = $this->formatSaleForCalendar($sale, 'preview', $previewStart, $previewEnd);
                }
            }
            
            // Add main sale period
            if ($saleStart <= $endDate && $saleEnd >= $startDate) {
                $phase = ($now >= $saleStart && $now <= $saleEnd) ? 'active' : 'upcoming';
                $calendarSales[] = $this->formatSaleForCalendar($sale, $phase, $saleStart, $saleEnd);
            }
        }
        
        return $calendarSales;
    }
    
    /**
     * Format a sale for calendar display
     */
    private function formatSaleForCalendar(
        $sale, 
        string $phase, 
        DateTime $start, 
        DateTime $end
    ): array {
        $phaseText = match($phase) {
            'preview' => ' (Preview)',
            'active' => ' (Active)',
            'upcoming' => ' (Upcoming)',
            default => ''
        };
        
        $backgroundColor = match($phase) {
            'preview' => '#9f7aea',
            'active' => '#48bb78',
            'upcoming' => '#4299e1',
            default => '#718096'
        };
        
        return [
            'id' => 'sale_' . $sale->getId() . '_' . $phase,
            'type' => 'sale',
            'title' => $sale->getTitle() . $phaseText,
            'start' => $start->format('c'),
            'end' => $end->format('c'),
            'description' => $sale->getDescription(),
            'location' => $sale->getAddress() . ', ' . $sale->getCity() . ', ' . $sale->getState(),
            'url' => '/claims/sale?id=' . $sale->getId(),
            'className' => 'calendar-sale calendar-sale-' . $phase,
            'backgroundColor' => $backgroundColor,
            'borderColor' => $backgroundColor,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'source' => 'claims',
                'subType' => $phase,
                'sellerName' => $sale->getSellerName(),
                'address' => $sale->getAddress() . ', ' . $sale->getCity() . ', ' . $sale->getState() . ' ' . $sale->getZip(),
                'coordinates' => [
                    'lat' => $sale->getLatitude(),
                    'lng' => $sale->getLongitude()
                ],
                'itemCount' => $sale->getItemCount()
            ]
        ];
    }
}