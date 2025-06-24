<?php

namespace YFEvents\Utils;

use YFEvents\Infrastructure\Database\ConnectionInterface;
use PDO;
use Exception;

class SystemSettings
{
    private PDO $db;
    private array $cache = [];
    
    public function __construct(ConnectionInterface $connection)
    {
        $this->db = $connection->getConnection();
    }
    
    /**
     * Get a system setting value
     */
    public function get(string $key, $default = null)
    {
        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            $value = $result ? $result['setting_value'] : $default;
            $this->cache[$key] = $value;
            
            return $value;
        } catch (Exception $e) {
            error_log("Error getting system setting '$key': " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Set a system setting value
     */
    public function set(string $key, $value, ?string $description = null): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                description = COALESCE(VALUES(description), description)
            ");
            $stmt->execute([$key, $value, $description]);
            
            // Update cache
            $this->cache[$key] = $value;
            
            return true;
        } catch (Exception $e) {
            error_log("Error setting system setting '$key': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if unapproved events should be shown
     */
    public function showUnapprovedEvents(): bool
    {
        return $this->get('show_unapproved_events', '0') === '1';
    }
    
    /**
     * Get disclaimer text for unapproved events
     */
    public function getUnapprovedEventsDisclaimer(): string
    {
        return $this->get('unapproved_events_disclaimer', 
            'These events are automatically imported and have not been verified. Details may be incomplete or inaccurate.'
        );
    }
    
    /**
     * Clear the settings cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}