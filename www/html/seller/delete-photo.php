<?php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

// Check authentication
if (!isset($_SESSION['seller_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$sellerId = $_SESSION['seller_id'];

// Handle photo deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['photo_id'])) {
    $photoId = intval($_POST['photo_id']);
    
    try {
        // Verify photo belongs to seller's item
        $checkStmt = $pdo->prepare("
            SELECT p.*, i.sale_id 
            FROM yfc_item_photos p
            JOIN yfc_items i ON p.item_id = i.id
            JOIN yfc_sales s ON i.sale_id = s.id
            WHERE p.id = ? AND s.seller_id = ?
        ");
        $checkStmt->execute([$photoId, $sellerId]);
        $photo = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($photo) {
            // Delete the file from disk
            $filePath = dirname(dirname(__DIR__)) . $photo['photo_url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete from database
            $deleteStmt = $pdo->prepare("DELETE FROM yfc_item_photos WHERE id = ?");
            $deleteStmt->execute([$photoId]);
            
            // If this was the primary photo, make another one primary
            if ($photo['is_primary']) {
                $updateStmt = $pdo->prepare("
                    UPDATE yfc_item_photos 
                    SET is_primary = 1 
                    WHERE item_id = ? 
                    ORDER BY id 
                    LIMIT 1
                ");
                $updateStmt->execute([$photo['item_id']]);
            }
            
            echo json_encode(['success' => true]);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Photo not found or access denied']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
?>