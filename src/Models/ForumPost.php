<?php

namespace YFEvents\Models;

use PDO;

class ForumPost {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    /**
     * Get posts by topic
     */
    public function getPostsByTopic($topicId, $limit = 20, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT fp.*, u.username, u.role,
                   (SELECT COUNT(*) FROM forum_votes fv WHERE fv.post_id = fp.id AND fv.vote_type = 'up') as upvotes,
                   (SELECT COUNT(*) FROM forum_votes fv WHERE fv.post_id = fp.id AND fv.vote_type = 'down') as downvotes,
                   (SELECT COUNT(*) FROM forum_posts WHERE parent_post_id = fp.id) as reply_count
            FROM forum_posts fp
            JOIN users u ON fp.user_id = u.id
            WHERE fp.topic_id = ? AND fp.is_approved = TRUE
            ORDER BY fp.created_at ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$topicId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent posts
     */
    public function getRecentPosts($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT fp.*, u.username, ft.title as topic_title, ft.slug as topic_slug,
                   fc.name as category_name, fc.slug as category_slug
            FROM forum_posts fp
            JOIN users u ON fp.user_id = u.id
            JOIN forum_topics ft ON fp.topic_id = ft.id
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE fp.is_approved = TRUE
            ORDER BY fp.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new post
     */
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO forum_posts (topic_id, user_id, parent_post_id, content, is_approved)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['topic_id'],
            $data['user_id'],
            $data['parent_post_id'] ?? null,
            $data['content'],
            $data['is_approved'] ?? true
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Update post
     */
    public function update($id, $content, $editedBy = null) {
        $stmt = $this->db->prepare("
            UPDATE forum_posts 
            SET content = ?, is_edited = TRUE, edit_count = edit_count + 1, 
                edited_by = ?, edited_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([$content, $editedBy, $id]);
    }
    
    /**
     * Delete post
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM forum_posts WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get post by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT fp.*, u.username
            FROM forum_posts fp
            JOIN users u ON fp.user_id = u.id
            WHERE fp.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Approve/unapprove post
     */
    public function setApproval($id, $approved) {
        $stmt = $this->db->prepare("UPDATE forum_posts SET is_approved = ? WHERE id = ?");
        return $stmt->execute([$approved, $id]);
    }
}