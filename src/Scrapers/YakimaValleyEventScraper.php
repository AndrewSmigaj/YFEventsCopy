<?php

namespace YakimaFinds\Scrapers;

class YakimaValleyEventScraper
{
    /**
     * Parse HTML content with the Yakima Valley events format
     */
    public static function parseEvents($html, $baseUrl = '', $currentYear = null)
    {
        $events = [];
        $currentYear = $currentYear ?: date('Y');
        
        // Create DOM parser
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        
        // Find all event list items
        $eventNodes = $xpath->query('//li[a[@href and h2 and h3]]');
        
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
                $dates = self::parseDateString($dateText, $currentYear);
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
                
                // Build description
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
                $event['description'] = implode("\n", $description);
                
                // Add to events array
                $events[] = $event;
                
            } catch (\Exception $e) {
                // Skip this event if parsing fails
                continue;
            }
        }
        
        return $events;
    }
    
    /**
     * Parse date string like "May 23 - 25" or "May 31"
     */
    private static function parseDateString($dateStr, $year)
    {
        $dateStr = trim($dateStr);
        
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
            
            return [
                'start' => $startDate . ' 00:00:00',
                'end' => $endDate . ' 23:59:59'
            ];
        }
        
        // Handle single date (e.g., "May 31")
        if (preg_match('/^(\w+)\s+(\d+)$/', $dateStr, $matches)) {
            $month = $matches[1];
            $day = $matches[2];
            
            $date = date('Y-m-d', strtotime("$month $day, $year"));
            
            return [
                'start' => $date . ' 00:00:00',
                'end' => $date . ' 23:59:59'
            ];
        }
        
        return null;
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