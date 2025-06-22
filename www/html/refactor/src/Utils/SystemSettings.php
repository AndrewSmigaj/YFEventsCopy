<?php

namespace YFEvents\Utils;

class SystemSettings
{
    private $db;
    private static $cache = [];
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    /**
     * Get a system setting value
     */
    public static function get($key, $default = null, $db = null)
    {
        global $pdo;
        if (!$db) $db = $pdo;
        
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        try {
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            
            $value = $result ? $result['setting_value'] : $default;
            self::$cache[$key] = $value;
            
            return $value;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * Set a system setting value
     */
    public static function set($key, $value, $description = null, $db = null)
    {
        global $pdo;
        if (!$db) $db = $pdo;
        
        try {
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                description = COALESCE(VALUES(description), description)
            ");
            $stmt->execute([$key, $value, $description]);
            
            // Update cache
            self::$cache[$key] = $value;
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if unapproved events should be shown
     */
    public static function showUnapprovedEvents($db = null)
    {
        return self::get('show_unapproved_events', '0', $db) === '1';
    }
    
    /**
     * Get disclaimer text for unapproved events
     */
    public static function getUnapprovedEventsDisclaimer($db = null)
    {
        return self::get('unapproved_events_disclaimer', 
            'These events are automatically imported and have not been verified. Details may be incomplete or inaccurate.',
            $db
        );
    }
    
    /**
     * Clear the settings cache
     */
    public static function clearCache()
    {
        self::$cache = [];
    }
}