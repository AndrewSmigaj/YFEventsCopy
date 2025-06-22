<?php

namespace YFEvents\Scrapers;

class YakimaValleyEventScraper
{
    /**
     * Parse HTML content with the Yakima Valley events format
     */
    public static function parseEvents($html, $baseUrl = '', $currentYear = null)
    {
        $events = [];
        $currentYear = $currentYear ?: date('Y');
        
        error_log("[YakimaValleyEventScraper] Starting parse with baseUrl: $baseUrl, year: $currentYear");
        error_log("[YakimaValleyEventScraper] HTML length: " . strlen($html));
        
        // Create DOM parser
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        
        // Try multiple selectors to find events
        $selectors = [
            '//li[a[@href and h2 and h3]]' => 'original selector',
            '//div[contains(@class, "event")]' => 'div with event class',
            '//article[contains(@class, "event")]' => 'article with event class',
            '//div[contains(@class, "eventsFeatured")]//li' => 'featured events list items',
            '//ul[contains(@class, "eventsFeatured")]//li' => 'ul featured events',
            '//*[@itemtype="http://schema.org/Event"]' => 'schema.org Event markup'
        ];
        
        $eventNodes = null;
        $usedSelector = null;
        
        foreach ($selectors as $selector => $description) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                error_log("[YakimaValleyEventScraper] Found {$nodes->length} nodes with selector: $description");
                $eventNodes = $nodes;
                $usedSelector = $selector;
                break;
            }
        }
        
        if (!$eventNodes || $eventNodes->length === 0) {
            error_log("[YakimaValleyEventScraper] No event nodes found with any selector");
            
            // Log some page structure for debugging
            $h2Count = $xpath->query('//h2')->length;
            $h3Count = $xpath->query('//h3')->length;
            $linkCount = $xpath->query('//a[@href]')->length;
            error_log("[YakimaValleyEventScraper] Page structure - h2: $h2Count, h3: $h3Count, links: $linkCount");
            
            return $events;
        }
        
        error_log("[YakimaValleyEventScraper] Processing {$eventNodes->length} event nodes");
        
        foreach ($eventNodes as $node) {
            try {
                $event = [];
                
                // Get the link element
                $linkNode = $xpath->query('.//a[@href]', $node)->item(0);
                if (!$linkNode) continue;
                
                // Extract URL
                $eventUrl = $linkNode->getAttribute('href');
                if ($baseUrl && strpos($eventUrl, 'http') !== 0) {
                    $eventUrl = rtrim($baseUrl, '/') . '/' . ltrim($eventUrl, '/');
                }
                $event['external_url'] = $eventUrl;
                
                // Extract title from h2
                $titleNode = $xpath->query('.//h2', $linkNode)->item(0);
                if (!$titleNode) continue;
                $event['title'] = trim($titleNode->textContent);
                
                // Extract date from h3
                $dateNode = $xpath->query('.//h3', $linkNode)->item(0);
                if (!$dateNode) continue;
                $dateText = trim($dateNode->textContent);
                
                // Parse dates (handles single dates and date ranges)
                $dates = self::parseDateString($dateText, $currentYear, $event['title'], $node);
                if (!$dates) continue;
                
                $event['start_datetime'] = $dates['start'];
                $event['end_datetime'] = $dates['end'];
                
                // Extract venue/member (organization)
                $memberNode = $xpath->query('.//p[@class="member"]', $linkNode)->item(0);
                if ($memberNode) {
                    $event['venue'] = trim($memberNode->textContent);
                }
                
                // Extract location (city)
                $pNodes = $xpath->query('.//p[not(@class)]', $linkNode);
                foreach ($pNodes as $pNode) {
                    $text = trim($pNode->textContent);
                    if ($text && !isset($event['location'])) {
                        $event['location'] = $text;
                        $event['address'] = $text . ', WA'; // Add state for geocoding
                        break;
                    }
                }
                
                // Combine venue and location for full location
                if (!empty($event['venue']) && !empty($event['location'])) {
                    $event['full_location'] = $event['venue'] . ', ' . $event['location'];
                } else {
                    $event['full_location'] = $event['venue'] ?? $event['location'] ?? '';
                }
                
                // Extract categories from icons
                $categories = [];
                $catDivs = $xpath->query('.//div[contains(@class, "catIcon")]', $linkNode);
                foreach ($catDivs as $catDiv) {
                    $catClass = $catDiv->getAttribute('class');
                    if (preg_match('/(\w+)Cat/', $catClass, $matches)) {
                        $categories[] = self::mapCategoryClass($matches[1]);
                    }
                }
                $event['categories'] = array_unique($categories);
                
                // Build description (source attribution handled separately by UI)
                $description = [];
                if (!empty($event['venue'])) {
                    $description[] = "Venue: " . $event['venue'];
                }
                if (!empty($event['location'])) {
                    $description[] = "Location: " . $event['location'];
                }
                if (!empty($categories)) {
                    $description[] = "Categories: " . implode(', ', $categories);
                }
                
                // Note: Source attribution and external links are handled by the calendar UI
                // using the external_url and source_name fields
                
                $event['description'] = implode("\n", $description);
                
                // Add to events array
                $events[] = $event;
                error_log("[YakimaValleyEventScraper] Successfully parsed event: {$event['title']}");
                
            } catch (\Exception $e) {
                // Skip this event if parsing fails
                error_log("[YakimaValleyEventScraper] Error parsing event: " . $e->getMessage());
                continue;
            }
        }
        
        error_log("[YakimaValleyEventScraper] Total events parsed: " . count($events));
        
        return $events;
    }
    
    /**
     * Parse date string like "May 23 - 25" or "May 31" with intelligent time defaults
     */
    private static function parseDateString($dateStr, $year, $eventTitle = '', $eventNode = null)
    {
        $dateStr = trim($dateStr);
        $startTime = null;
        $endTime = null;
        
        // First, try to extract any time information from the full event node if provided
        if ($eventNode) {
            $fullText = $eventNode->textContent;
            
            // Common time patterns
            $timePatterns = [
                '/(\d{1,2}):(\d{2})\s*(am|pm|AM|PM)/i' => 'standard',
                '/(\d{1,2})\s*(am|pm|AM|PM)/i' => 'hour_only',
                '/from\s+(\d{1,2}(?::\d{2})?)\s*(am|pm|AM|PM)?\s*(?:to|until|-|–)\s*(\d{1,2}(?::\d{2})?)\s*(am|pm|AM|PM)?/i' => 'range',
                '/starts?\s+at\s+(\d{1,2}(?::\d{2})?)\s*(am|pm|AM|PM)?/i' => 'starts_at',
                '/doors?\s+(?:open)?\s*at\s+(\d{1,2}(?::\d{2})?)\s*(am|pm|AM|PM)?/i' => 'doors_at'
            ];
            
            foreach ($timePatterns as $pattern => $type) {
                if (preg_match($pattern, $fullText, $timeMatch)) {
                    error_log("[YakimaValleyEventScraper] Found time pattern ($type): " . $timeMatch[0]);
                    
                    switch ($type) {
                        case 'standard':
                            $hour = intval($timeMatch[1]);
                            $minute = intval($timeMatch[2]);
                            $ampm = strtolower($timeMatch[3]);
                            if ($ampm === 'pm' && $hour !== 12) $hour += 12;
                            if ($ampm === 'am' && $hour === 12) $hour = 0;
                            $startTime = sprintf('%02d:%02d:00', $hour, $minute);
                            break;
                            
                        case 'hour_only':
                            $hour = intval($timeMatch[1]);
                            $ampm = strtolower($timeMatch[2]);
                            if ($ampm === 'pm' && $hour !== 12) $hour += 12;
                            if ($ampm === 'am' && $hour === 12) $hour = 0;
                            $startTime = sprintf('%02d:00:00', $hour);
                            break;
                            
                        case 'range':
                            // Handle time ranges - for now just use start time
                            $startHour = intval($timeMatch[1]);
                            if (isset($timeMatch[2]) && strtolower($timeMatch[2]) === 'pm' && $startHour !== 12) {
                                $startHour += 12;
                            }
                            $startTime = sprintf('%02d:00:00', $startHour);
                            break;
                    }
                    
                    if ($startTime) break; // Use first time found
                }
            }
        }
        
        // If no time found, use intelligent defaults based on event type
        if (!$startTime) {
            $startTime = self::getDefaultTimeForEvent($eventTitle);
            $endTime = self::getDefaultEndTime($startTime);
            error_log("[YakimaValleyEventScraper] Using default time for '$eventTitle': $startTime");
        } else {
            // If we found a start time but no end time, estimate end time
            $endTime = self::getDefaultEndTime($startTime);
        }
        
        // Parse the date part
        // Handle date range (e.g., "May 23 - 25" or "May 30 - Jul 25")
        if (preg_match('/^(\w+)\s+(\d+)\s*-\s*(\w+\s+)?(\d+)$/', $dateStr, $matches)) {
            $startMonth = $matches[1];
            $startDay = $matches[2];
            
            if (!empty($matches[3])) {
                // Different months (e.g., "May 30 - Jul 25")
                $endMonth = trim($matches[3]);
                $endDay = $matches[4];
            } else {
                // Same month (e.g., "May 23 - 25")
                $endMonth = $startMonth;
                $endDay = $matches[4];
            }
            
            $startDate = date('Y-m-d', strtotime("$startMonth $startDay, $year"));
            $endDate = date('Y-m-d', strtotime("$endMonth $endDay, $year"));
            
            // Handle year boundary (if end date is before start date, it's next year)
            if ($endDate < $startDate) {
                $endDate = date('Y-m-d', strtotime("$endMonth $endDay, " . ($year + 1)));
            }
            
            // For multi-day events, use all-day times unless specific times were found
            if ($startDate !== $endDate && !$eventNode) {
                return [
                    'start' => $startDate . ' 00:00:00',
                    'end' => $endDate . ' 23:59:59'
                ];
            }
            
            return [
                'start' => $startDate . ' ' . $startTime,
                'end' => $endDate . ' ' . $endTime
            ];
        }
        
        // Handle single date (e.g., "May 31")
        if (preg_match('/^(\w+)\s+(\d+)$/', $dateStr, $matches)) {
            $month = $matches[1];
            $day = $matches[2];
            
            $date = date('Y-m-d', strtotime("$month $day, $year"));
            
            return [
                'start' => $date . ' ' . $startTime,
                'end' => $date . ' ' . $endTime
            ];
        }
        
        return null;
    }
    
    /**
     * Get intelligent default time based on event title/type
     */
    private static function getDefaultTimeForEvent($title)
    {
        $title = strtolower($title);
        
        // Event type patterns and their typical start times
        $timePatterns = [
            // Morning events
            'breakfast' => '08:00:00',
            'brunch' => '10:00:00',
            'morning' => '09:00:00',
            'sunrise' => '06:00:00',
            
            // Afternoon events
            'lunch' => '12:00:00',
            'afternoon' => '14:00:00',
            'matinee' => '14:00:00',
            
            // Evening events
            'dinner' => '18:00:00',
            'happy hour' => '17:00:00',
            'trivia' => '19:00:00',
            'bingo' => '19:00:00',
            'comedy' => '20:00:00',
            'concert' => '19:00:00',
            'live music' => '19:00:00',
            'show' => '19:00:00',
            'night' => '20:00:00',
            'party' => '20:00:00',
            
            // Market/Fair events
            'market' => '09:00:00',
            'farmers market' => '08:00:00',
            'fair' => '10:00:00',
            'festival' => '10:00:00',
            
            // Tour events
            'tour' => '10:00:00',
            'tasting' => '12:00:00',
            'wine' => '12:00:00',
            'brewery' => '14:00:00',
            'brewing' => '14:00:00'
        ];
        
        // Check each pattern
        foreach ($timePatterns as $pattern => $time) {
            if (strpos($title, $pattern) !== false) {
                return $time;
            }
        }
        
        // Default to 10 AM for unknown event types
        return '10:00:00';
    }
    
    /**
     * Get default end time based on start time
     */
    private static function getDefaultEndTime($startTime)
    {
        // Parse the start time
        $startHour = intval(substr($startTime, 0, 2));
        
        // Estimate duration based on start time
        if ($startHour < 12) {
            // Morning events: 2-3 hours
            $duration = 3;
        } elseif ($startHour < 17) {
            // Afternoon events: 3-4 hours
            $duration = 4;
        } else {
            // Evening events: 2-3 hours
            $duration = 3;
        }
        
        $endHour = $startHour + $duration;
        if ($endHour >= 24) {
            $endHour = 23; // Cap at 11 PM
        }
        
        return sprintf('%02d:00:00', $endHour);
    }
    
    /**
     * Map category class names to categories
     */
    private static function mapCategoryClass($className)
    {
        $classMap = [
            'wine' => 'Wine & Spirits',
            'food' => 'Food & Dining',
            'music' => 'Music & Entertainment',
            'arts' => 'Arts & Culture',
            'family' => 'Family Friendly',
            'outdoor' => 'Outdoor Activities',
            'beer' => 'Beer & Breweries'
        ];
        
        return $classMap[$className] ?? 'Other';
    }
}