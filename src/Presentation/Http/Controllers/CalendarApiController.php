<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Application\Services\CalendarService;
use DateTimeImmutable;

/**
 * Controller for unified calendar API
 * Provides a single endpoint for retrieving both events and estate sales
 */
class CalendarApiController extends BaseController
{
    public function __construct(
        private readonly CalendarService $calendarService
    ) {}

    /**
     * Get unified calendar data combining events and estate sales
     * 
     * Query parameters:
     * - start: Start date (Y-m-d)
     * - end: End date (Y-m-d)
     * - types: Comma-separated list of types to include (event,sale)
     */
    public function getUnifiedCalendar(): void
    {
        try {
            // Get query parameters
            $startParam = $_GET['start'] ?? date('Y-m-01'); // Default to start of current month
            $endParam = $_GET['end'] ?? date('Y-m-t'); // Default to end of current month
            $typesParam = $_GET['types'] ?? 'event,sale'; // Default to both
            
            // Parse dates
            $startDate = new DateTimeImmutable($startParam);
            $endDate = new DateTimeImmutable($endParam);
            
            // Parse types
            $types = array_map('trim', explode(',', $typesParam));
            
            // Validate types
            $validTypes = ['event', 'sale'];
            $types = array_intersect($types, $validTypes);
            
            if (empty($types)) {
                $types = $validTypes; // Default to all if none valid
            }
            
            // Get unified calendar data
            $filters = ['types' => $types];
            $calendarData = $this->calendarService->getUnifiedCalendarData(
                $startDate, 
                $endDate, 
                $filters
            );
            
            // Return JSON response
            $this->jsonResponse($calendarData);
            
        } catch (\Exception $e) {
            $this->errorResponse('Failed to fetch calendar data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get calendar data for a specific month
     * 
     * Query parameters:
     * - month: Month number (1-12)
     * - year: Year (YYYY)
     * - types: Comma-separated list of types to include (event,sale)
     */
    public function getMonthlyCalendar(): void
    {
        try {
            $month = (int)($_GET['month'] ?? date('n'));
            $year = (int)($_GET['year'] ?? date('Y'));
            $typesParam = $_GET['types'] ?? 'event,sale';
            
            // Validate month and year
            if ($month < 1 || $month > 12) {
                $this->errorResponse('Invalid month. Must be between 1 and 12.', 400);
                return;
            }
            
            if ($year < 2020 || $year > 2030) {
                $this->errorResponse('Invalid year. Must be between 2020 and 2030.', 400);
                return;
            }
            
            // Calculate start and end dates for the month
            $startDate = new DateTimeImmutable("$year-$month-01");
            $endDate = new DateTimeImmutable($startDate->format('Y-m-t'));
            
            // Parse types
            $types = array_map('trim', explode(',', $typesParam));
            $validTypes = ['event', 'sale'];
            $types = array_intersect($types, $validTypes);
            
            if (empty($types)) {
                $types = $validTypes;
            }
            
            // Get calendar data
            $filters = ['types' => $types];
            $calendarData = $this->calendarService->getUnifiedCalendarData(
                $startDate, 
                $endDate, 
                $filters
            );
            
            // Add month metadata
            $response = [
                'month' => $month,
                'year' => $year,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'types' => $types,
                'items' => $calendarData
            ];
            
            $this->jsonResponse($response);
            
        } catch (\Exception $e) {
            $this->errorResponse('Failed to fetch monthly calendar: ' . $e->getMessage(), 500);
        }
    }
}