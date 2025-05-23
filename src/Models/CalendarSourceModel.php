<?php

namespace YakimaFinds\Models;

use PDO;

class CalendarSourceModel extends BaseModel
{
    protected $table = 'calendar_sources';
    
    /**
     * Get all sources
     */
    public function getSources($activeOnly = false)
    {
        $sql = "SELECT cs.*, 
                       COUNT(e.id) as event_count,
                       MAX(sl.completed_at) as last_successful_scrape,
                       sl_latest.status as last_scrape_status
                FROM calendar_sources cs
                LEFT JOIN events e ON cs.id = e.source_id
                LEFT JOIN scraping_logs sl ON cs.id = sl.source_id AND sl.status = 'success'
                LEFT JOIN (
                    SELECT source_id, status, completed_at,
                           ROW_NUMBER() OVER (PARTITION BY source_id ORDER BY started_at DESC) as rn
                    FROM scraping_logs
                ) sl_latest ON cs.id = sl_latest.source_id AND sl_latest.rn = 1";
        
        if ($activeOnly) {
            $sql .= " WHERE cs.active = 1";
        }
        
        $sql .= " GROUP BY cs.id ORDER BY cs.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get source by ID
     */
    public function getSourceById($id)
    {
        $sql = "SELECT * FROM calendar_sources WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new source
     */
    public function createSource($data)
    {
        $sql = "INSERT INTO calendar_sources (name, url, scrape_type, scrape_config, 
                active, created_by) 
                VALUES (:name, :url, :scrape_type, :scrape_config, :active, :created_by)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'name' => $data['name'],
            'url' => $data['url'],
            'scrape_type' => $data['scrape_type'],
            'scrape_config' => isset($data['scrape_config']) ? json_encode($data['scrape_config']) : null,
            'active' => $data['active'] ?? 1,
            'created_by' => $data['created_by'] ?? null
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Update source
     */
    public function updateSource($id, $data)
    {
        $setParts = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $setParts[] = "$key = :$key";
                if ($key === 'scrape_config' && is_array($value)) {
                    $params[$key] = json_encode($value);
                } else {
                    $params[$key] = $value;
                }
            }
        }
        
        if (empty($setParts)) {
            return false;
        }
        
        $sql = "UPDATE calendar_sources SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Delete source
     */
    public function deleteSource($id)
    {
        // First set all events from this source to have no source
        $sql = "UPDATE events SET source_id = NULL WHERE source_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        // Then delete the source
        $sql = "DELETE FROM calendar_sources WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Update last scraped time
     */
    public function updateLastScraped($id)
    {
        $sql = "UPDATE calendar_sources SET last_scraped = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Get active sources for scraping
     */
    public function getActiveSources()
    {
        return $this->getSources(true);
    }
    
    /**
     * Log scraping start
     */
    public function logScrapingStart($sourceId)
    {
        $sql = "INSERT INTO scraping_logs (source_id, status) VALUES (:source_id, 'running')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['source_id' => $sourceId]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Log scraping completion
     */
    public function logScrapingComplete($logId, $eventsFound, $eventsAdded, $status = 'success', $errorMessage = null)
    {
        $sql = "UPDATE scraping_logs SET 
                completed_at = NOW(),
                status = :status,
                events_found = :events_found,
                events_added = :events_added,
                error_message = :error_message
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $logId,
            'status' => $status,
            'events_found' => $eventsFound,
            'events_added' => $eventsAdded,
            'error_message' => $errorMessage
        ]);
    }
    
    /**
     * Get scraping logs for a source
     */
    public function getScrapingLogs($sourceId, $limit = 50)
    {
        $sql = "SELECT * FROM scraping_logs 
                WHERE source_id = :source_id 
                ORDER BY started_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'source_id' => $sourceId,
            'limit' => $limit
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent scraping activity
     */
    public function getRecentActivity($limit = 20)
    {
        $sql = "SELECT sl.*, cs.name as source_name
                FROM scraping_logs sl
                JOIN calendar_sources cs ON sl.source_id = cs.id
                ORDER BY sl.started_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['limit' => $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Test source configuration
     */
    public function testSource($id)
    {
        $source = $this->getSourceById($id);
        if (!$source) {
            return ['success' => false, 'error' => 'Source not found'];
        }
        
        switch ($source['scrape_type']) {
            case 'ical':
                return $this->testICalSource($source);
            case 'html':
                return $this->testHtmlSource($source);
            case 'json':
                return $this->testJsonSource($source);
            default:
                return ['success' => false, 'error' => 'Unsupported scrape type'];
        }
    }
    
    /**
     * Test iCal source
     */
    private function testICalSource($source)
    {
        try {
            $content = file_get_contents($source['url']);
            if ($content === false) {
                return ['success' => false, 'error' => 'Failed to fetch iCal file'];
            }
            
            // Basic iCal validation
            if (strpos($content, 'BEGIN:VCALENDAR') === false) {
                return ['success' => false, 'error' => 'Invalid iCal format'];
            }
            
            $eventCount = substr_count($content, 'BEGIN:VEVENT');
            return [
                'success' => true, 
                'message' => "iCal source accessible. Found {$eventCount} events."
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Test HTML source
     */
    private function testHtmlSource($source)
    {
        try {
            $content = file_get_contents($source['url']);
            if ($content === false) {
                return ['success' => false, 'error' => 'Failed to fetch HTML page'];
            }
            
            $config = json_decode($source['scrape_config'], true);
            if (!$config || !isset($config['selectors'])) {
                return ['success' => false, 'error' => 'Invalid scrape configuration'];
            }
            
            // Basic HTML validation
            $dom = new \DOMDocument();
            @$dom->loadHTML($content);
            
            return [
                'success' => true, 
                'message' => 'HTML source accessible and parseable.'
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Test JSON source
     */
    private function testJsonSource($source)
    {
        try {
            $content = file_get_contents($source['url']);
            if ($content === false) {
                return ['success' => false, 'error' => 'Failed to fetch JSON data'];
            }
            
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'Invalid JSON format'];
            }
            
            return [
                'success' => true, 
                'message' => 'JSON source accessible and valid.'
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Deactivate source after repeated failures
     */
    public function deactivateFailedSource($sourceId, $failureThreshold = 5)
    {
        $sql = "SELECT COUNT(*) as failure_count 
                FROM scraping_logs 
                WHERE source_id = :source_id 
                AND status = 'error' 
                AND started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['source_id' => $sourceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['failure_count'] >= $failureThreshold) {
            $this->updateSource($sourceId, ['active' => 0]);
            return true;
        }
        
        return false;
    }
}