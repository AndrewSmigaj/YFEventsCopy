<?php

namespace YFEvents\Models;

use PDO;

class ForumTopic {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Get topic by slug
     */
    public function getBySlug($slug) {
        $stmt = $this->db->prepare("
            SELECT ft.*, fc.name as category_name, fc.slug as category_slug, u.username
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            JOIN users u ON ft.user_id = u.id
            WHERE ft.slug = ? AND ft.is_approved = TRUE
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get topics by category
     */
    public function getTopicsByCategory($categoryId, $limit = 20, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT ft.*, u.username, 
                   (SELECT username FROM users u2 
                    JOIN forum_posts fp ON u2.id = fp.user_id 
                    WHERE fp.id = ft.last_post_id) as last_post_username
            FROM forum_topics ft
            JOIN users u ON ft.user_id = u.id
            WHERE ft.category_id = ? AND ft.is_approved = TRUE
            ORDER BY ft.is_pinned DESC, ft.last_post_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$categoryId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Search topics
     */
    public function searchTopics($query, $categoryId = null, $limit = 20) {
        $sql = "
            SELECT ft.*, fc.name as category_name, u.username
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            JOIN users u ON ft.user_id = u.id
            WHERE ft.is_approved = TRUE
            AND (ft.title LIKE ? OR ft.content LIKE ?)
        ";
        
        $params = ["%$query%", "%$query%"];
        
        if ($categoryId) {
            $sql .= " AND ft.category_id = ?";
            $params[] = $categoryId;
        }
        
        $sql .= " ORDER BY ft.last_post_date DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new topic
     */
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO forum_topics (category_id, user_id, title, slug, content, is_approved)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['category_id'],
            $data['user_id'],
            $data['title'],
            $data['slug'],
            $data['content'],
            $data['is_approved'] ?? true
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Increment view count
     */
    public function incrementViews($topicId) {
        $stmt = $this->db->prepare("UPDATE forum_topics SET views = views + 1 WHERE id = ?");
        return $stmt->execute([$topicId]);
    }
    
    /**
     * Update topic
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['title', 'content', 'is_pinned', 'is_locked', 'is_approved'])) {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        $sql = "UPDATE forum_topics SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Delete topic
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM forum_topics WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get recent topics
     */
    public function getRecentTopics($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT ft.*, fc.name as category_name, fc.slug as category_slug, u.username
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            JOIN users u ON ft.user_id = u.id
            WHERE ft.is_approved = TRUE
            ORDER BY ft.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}