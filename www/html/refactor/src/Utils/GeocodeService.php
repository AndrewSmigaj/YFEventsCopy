<?php

namespace YakimaFinds\Utils;

class GeocodeService
{
    private $apiKey;
    private $cacheDir;
    
    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey ?: $_ENV['GOOGLE_MAPS_API_KEY'] ?? null;
        $this->cacheDir = __DIR__ . '/../../cache/geocode/';
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Geocode an address to coordinates
     */
    public function geocode($address)
    {
        if (empty($address) || !$this->apiKey) {
            return null;
        }
        
        // Check cache first
        $cacheKey = md5($address);
        $cacheFile = $this->cacheDir . $cacheKey . '.json';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) {
                return $cached;
            }
        }
        
        // Make API request
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
            'address' => $address,
            'key' => $this->apiKey,
            'region' => 'us' // Bias towards US results
        ]);
        
        $response = $this->makeRequest($url);
        if (!$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if ($data['status'] !== 'OK' || empty($data['results'])) {
            return null;
        }
        
        $result = $data['results'][0];
        $coordinates = [
            'lat' => $result['geometry']['location']['lat'],
            'lng' => $result['geometry']['location']['lng'],
            'formatted_address' => $result['formatted_address'],
            'place_id' => $result['place_id']
        ];
        
        // Cache the result
        file_put_contents($cacheFile, json_encode($coordinates));
        
        return $coordinates;
    }
    
    /**
     * Reverse geocode coordinates to address
     */
    public function reverseGeocode($lat, $lng)
    {
        if (!$this->apiKey) {
            return null;
        }
        
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
            'latlng' => $lat . ',' . $lng,
            'key' => $this->apiKey
        ]);
        
        $response = $this->makeRequest($url);
        if (!$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if ($data['status'] !== 'OK' || empty($data['results'])) {
            return null;
        }
        
        $result = $data['results'][0];
        return [
            'formatted_address' => $result['formatted_address'],
            'place_id' => $result['place_id'],
            'components' => $this->parseAddressComponents($result['address_components'])
        ];
    }
    
    /**
     * Get place details by place ID
     */
    public function getPlaceDetails($placeId)
    {
        if (!$this->apiKey || !$placeId) {
            return null;
        }
        
        $url = 'https://maps.googleapis.com/maps/api/place/details/json?' . http_build_query([
            'place_id' => $placeId,
            'fields' => 'name,formatted_address,geometry,formatted_phone_number,website,opening_hours',
            'key' => $this->apiKey
        ]);
        
        $response = $this->makeRequest($url);
        if (!$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if ($data['status'] !== 'OK' || empty($data['result'])) {
            return null;
        }
        
        return $data['result'];
    }
    
    /**
     * Search for places near a location
     */
    public function searchNearby($lat, $lng, $radius = 1000, $type = null, $keyword = null)
    {
        if (!$this->apiKey) {
            return [];
        }
        
        $params = [
            'location' => $lat . ',' . $lng,
            'radius' => $radius,
            'key' => $this->apiKey
        ];
        
        if ($type) {
            $params['type'] = $type;
        }
        
        if ($keyword) {
            $params['keyword'] = $keyword;
        }
        
        $url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?' . http_build_query($params);
        
        $response = $this->makeRequest($url);
        if (!$response) {
            return [];
        }
        
        $data = json_decode($response, true);
        
        if ($data['status'] !== 'OK' || empty($data['results'])) {
            return [];
        }
        
        return $data['results'];
    }
    
    /**
     * Calculate distance between two coordinates
     */
    public function calculateDistance($lat1, $lng1, $lat2, $lng2, $unit = 'miles')
    {
        $earthRadius = $unit === 'miles' ? 3959 : 6371; // miles or kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    /**
     * Validate coordinates
     */
    public function validateCoordinates($lat, $lng)
    {
        $lat = (float)$lat;
        $lng = (float)$lng;
        
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }
    
    /**
     * Get coordinates from various location formats
     */
    public function parseLocation($location)
    {
        if (empty($location)) {
            return null;
        }
        
        // Check if it's already coordinates (lat,lng format)
        if (preg_match('/^(-?\d+\.?\d*),\s*(-?\d+\.?\d*)$/', $location, $matches)) {
            $lat = (float)$matches[1];
            $lng = (float)$matches[2];
            
            if ($this->validateCoordinates($lat, $lng)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }
        
        // Try geocoding as address
        return $this->geocode($location);
    }
    
    /**
     * Batch geocode multiple addresses
     */
    public function batchGeocode($addresses, $delayMs = 100)
    {
        $results = [];
        
        foreach ($addresses as $index => $address) {
            $results[$index] = $this->geocode($address);
            
            // Add delay to respect API rate limits
            if ($delayMs > 0 && $index < count($addresses) - 1) {
                usleep($delayMs * 1000);
            }
        }
        
        return $results;
    }
    
    /**
     * Make HTTP request with error handling
     */
    private function makeRequest($url)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Yakima Finds Calendar 1.0'
            ]
        ]);
        
        return @file_get_contents($url, false, $context);
    }
    
    /**
     * Parse address components from Google response
     */
    private function parseAddressComponents($components)
    {
        $parsed = [];
        
        foreach ($components as $component) {
            $types = $component['types'];
            $value = $component['long_name'];
            
            if (in_array('street_number', $types)) {
                $parsed['street_number'] = $value;
            } elseif (in_array('route', $types)) {
                $parsed['street_name'] = $value;
            } elseif (in_array('locality', $types)) {
                $parsed['city'] = $value;
            } elseif (in_array('administrative_area_level_1', $types)) {
                $parsed['state'] = $component['short_name'];
            } elseif (in_array('postal_code', $types)) {
                $parsed['zip'] = $value;
            } elseif (in_array('country', $types)) {
                $parsed['country'] = $component['short_name'];
            }
        }
        
        return $parsed;
    }
    
    /**
     * Clear geocoding cache
     */
    public function clearCache()
    {
        $files = glob($this->cacheDir . '*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        return count($files);
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats()
    {
        $files = glob($this->cacheDir . '*.json');
        $totalSize = 0;
        $oldestFile = null;
        $newestFile = null;
        
        foreach ($files as $file) {
            $size = filesize($file);
            $mtime = filemtime($file);
            
            $totalSize += $size;
            
            if (!$oldestFile || $mtime < filemtime($oldestFile)) {
                $oldestFile = $file;
            }
            
            if (!$newestFile || $mtime > filemtime($newestFile)) {
                $newestFile = $file;
            }
        }
        
        return [
            'files' => count($files),
            'total_size' => $totalSize,
            'oldest_file' => $oldestFile ? filemtime($oldestFile) : null,
            'newest_file' => $newestFile ? filemtime($newestFile) : null
        ];
    }
}