<?php

namespace YFEvents\Models;

use PDO;

class ShopModel extends BaseModel
{
    protected $table = 'local_shops';
    
    /**
     * Get shops with optional filtering
     */
    public function getShops($filters = [])
    {
        $sql = "SELECT s.*, 
                       sc.name as category_name,
                       sc.icon as category_icon,
                       so.first_name, so.last_name, so.email as owner_email,
                       si.filename as primary_image
                FROM local_shops s
                LEFT JOIN shop_categories sc ON s.category_id = sc.id
                LEFT JOIN shop_owners so ON s.owner_id = so.id
                LEFT JOIN shop_images si ON s.id = si.shop_id AND si.is_primary = 1
                WHERE 1=1";
        
        $params = [];
        
        // Status filter
        if (isset($filters['status'])) {
            $sql .= " AND s.status = :status";
            $params['status'] = $filters['status'];
        }
        
        // Category filter
        if (isset($filters['category'])) {
            $sql .= " AND sc.slug = :category";
            $params['category'] = $filters['category'];
        }
        
        // Featured filter
        if (isset($filters['featured'])) {
            $sql .= " AND s.featured = :featured";
            $params['featured'] = $filters['featured'];
        }
        
        // Location-based filter (radius in miles)
        if (isset($filters['latitude']) && isset($filters['longitude']) && isset($filters['radius'])) {
            $sql .= " AND (6371 * acos(cos(radians(:lat)) * cos(radians(s.latitude)) * 
                     cos(radians(s.longitude) - radians(:lng)) + 
                     sin(radians(:lat)) * sin(radians(s.latitude)))) <= :radius";
            $params['lat'] = $filters['latitude'];
            $params['lng'] = $filters['longitude'];
            $params['radius'] = $filters['radius'];
        }
        
        // Search filter
        if (isset($filters['search'])) {
            $sql .= " AND (s.name LIKE :search OR s.description LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY s.featured DESC, s.name ASC";
        
        // Pagination
        if (isset($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params['limit'] = (int)$filters['limit'];
            
            if (isset($filters['offset'])) {
                $sql .= " OFFSET :offset";
                $params['offset'] = (int)$filters['offset'];
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get shop by ID with full details
     */
    public function getShopById($id)
    {
        $sql = "SELECT s.*, 
                       sc.name as category_name,
                       sc.slug as category_slug,
                       so.first_name, so.last_name, so.email as owner_email,
                       GROUP_CONCAT(DISTINCT si.filename ORDER BY si.sort_order) as images
                FROM local_shops s
                LEFT JOIN shop_categories sc ON s.category_id = sc.id
                LEFT JOIN shop_owners so ON s.owner_id = so.id
                LEFT JOIN shop_images si ON s.id = si.shop_id
                WHERE s.id = :id
                GROUP BY s.id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get shop by slug
     */
    public function getShopBySlug($slug)
    {
        // Note: Would need to add slug field to shops table
        return $this->getShopById($slug);
    }
    
    /**
     * Create new shop
     */
    public function createShop($data)
    {
        // Geocode address if provided
        if (!empty($data['address']) && empty($data['latitude'])) {
            $coordinates = $this->geocodeAddress($data['address']);
            if ($coordinates) {
                $data['latitude'] = $coordinates['lat'];
                $data['longitude'] = $coordinates['lng'];
            }
        }
        
        $sql = "INSERT INTO local_shops (name, description, address, latitude, longitude, 
                phone, email, website, category_id, operating_hours, payment_methods, 
                amenities, featured, verified, owner_id, status) 
                VALUES (:name, :description, :address, :latitude, :longitude, 
                :phone, :email, :website, :category_id, :operating_hours, :payment_methods, 
                :amenities, :featured, :verified, :owner_id, :status)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'operating_hours' => isset($data['operating_hours']) ? json_encode($data['operating_hours']) : null,
            'payment_methods' => isset($data['payment_methods']) ? json_encode($data['payment_methods']) : null,
            'amenities' => isset($data['amenities']) ? json_encode($data['amenities']) : null,
            'featured' => $data['featured'] ?? 0,
            'verified' => $data['verified'] ?? 0,
            'owner_id' => $data['owner_id'] ?? null,
            'status' => $data['status'] ?? 'pending'
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Update shop
     */
    public function updateShop($id, $data)
    {
        // Geocode address if changed
        if (!empty($data['address']) && 
            (empty($data['latitude']) || $this->addressChanged($id, $data['address']))) {
            $coordinates = $this->geocodeAddress($data['address']);
            if ($coordinates) {
                $data['latitude'] = $coordinates['lat'];
                $data['longitude'] = $coordinates['lng'];
            }
        }
        
        $setParts = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $setParts[] = "$key = :$key";
                if (in_array($key, ['operating_hours', 'payment_methods', 'amenities']) && is_array($value)) {
                    $params[$key] = json_encode($value);
                } else {
                    $params[$key] = $value;
                }
            }
        }
        
        if (empty($setParts)) {
            return false;
        }
        
        $sql = "UPDATE local_shops SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Delete shop
     */
    public function deleteShop($id)
    {
        $sql = "DELETE FROM local_shops WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Get shops near a location
     */
    public function getNearbyShops($latitude, $longitude, $radius = 5, $category = null)
    {
        $sql = "SELECT s.*, 
                       sc.name as category_name,
                       sc.icon as category_icon,
                       si.filename as primary_image,
                       (6371 * acos(cos(radians(:lat)) * cos(radians(s.latitude)) * 
                        cos(radians(s.longitude) - radians(:lng)) + 
                        sin(radians(:lat)) * sin(radians(s.latitude)))) AS distance
                FROM local_shops s
                LEFT JOIN shop_categories sc ON s.category_id = sc.id
                LEFT JOIN shop_images si ON s.id = si.shop_id AND si.is_primary = 1
                WHERE s.status = 'active'
                AND s.latitude IS NOT NULL 
                AND s.longitude IS NOT NULL";
        
        $params = [
            'lat' => $latitude,
            'lng' => $longitude,
            'radius' => $radius
        ];
        
        if ($category) {
            $sql .= " AND sc.slug = :category";
            $params['category'] = $category;
        }
        
        $sql .= " HAVING distance <= :radius
                 ORDER BY distance ASC, s.featured DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get events near a shop
     */
    public function getEventsNearShop($shopId, $radius = 1)
    {
        $shop = $this->getShopById($shopId);
        if (!$shop || !$shop['latitude'] || !$shop['longitude']) {
            return [];
        }
        
        $eventModel = new EventModel($this->db);
        return $eventModel->getNearbyEvents($shop['latitude'], $shop['longitude'], $radius);
    }
    
    /**
     * Get all shop categories
     */
    public function getCategories($parentId = null)
    {
        $sql = "SELECT * FROM shop_categories WHERE active = 1";
        $params = [];
        
        if ($parentId !== null) {
            $sql .= " AND parent_id = :parent_id";
            $params['parent_id'] = $parentId;
        } else {
            $sql .= " AND parent_id IS NULL";
        }
        
        $sql .= " ORDER BY sort_order ASC, name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get category tree
     */
    public function getCategoryTree()
    {
        $categories = $this->getCategories();
        $tree = [];
        
        foreach ($categories as $category) {
            $category['children'] = $this->getCategories($category['id']);
            $tree[] = $category;
        }
        
        return $tree;
    }
    
    /**
     * Search shops
     */
    public function searchShops($query, $filters = [])
    {
        $filters['search'] = $query;
        return $this->getShops($filters);
    }
    
    /**
     * Get featured shops
     */
    public function getFeaturedShops($limit = 10)
    {
        return $this->getShops([
            'featured' => 1,
            'status' => 'active',
            'limit' => $limit
        ]);
    }
    
    /**
     * Check if shop is currently open
     */
    public function isShopOpen($shopId)
    {
        $shop = $this->getShopById($shopId);
        if (!$shop || !$shop['operating_hours']) {
            return null; // Unknown
        }
        
        $hours = json_decode($shop['operating_hours'], true);
        $now = new \DateTime();
        $dayOfWeek = strtolower($now->format('l')); // monday, tuesday, etc.
        
        if (!isset($hours[$dayOfWeek])) {
            return false; // Closed today
        }
        
        $todayHours = $hours[$dayOfWeek];
        if ($todayHours['closed']) {
            return false;
        }
        
        $currentTime = $now->format('H:i');
        return $currentTime >= $todayHours['open'] && $currentTime <= $todayHours['close'];
    }
    
    /**
     * Geocode address (placeholder)
     */
    private function geocodeAddress($address)
    {
        // This would use Google Maps Geocoding API
        // For now, return null - implement based on API key availability
        return null;
    }
    
    /**
     * Check if address has changed for a shop
     */
    private function addressChanged($id, $newAddress)
    {
        $sql = "SELECT address FROM local_shops WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $current && $current['address'] !== $newAddress;
    }
    
    /**
     * Add images to shop
     */
    public function addShopImage($shopId, $filename, $altText = '', $isPrimary = false)
    {
        // If this is primary, unset other primary images
        if ($isPrimary) {
            $sql = "UPDATE shop_images SET is_primary = 0 WHERE shop_id = :shop_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['shop_id' => $shopId]);
        }
        
        $sql = "INSERT INTO shop_images (shop_id, filename, alt_text, is_primary) 
                VALUES (:shop_id, :filename, :alt_text, :is_primary)";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            'shop_id' => $shopId,
            'filename' => $filename,
            'alt_text' => $altText,
            'is_primary' => $isPrimary ? 1 : 0
        ]);
    }
    
    /**
     * Get shop images
     */
    public function getShopImages($shopId)
    {
        $sql = "SELECT * FROM shop_images WHERE shop_id = :shop_id ORDER BY is_primary DESC, sort_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['shop_id' => $shopId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}