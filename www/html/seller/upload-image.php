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

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $uploadDir = dirname(dirname(__DIR__)) . '/uploads/items/';
    $webPath = '/uploads/items/';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $file = $_FILES['image'];
    $itemId = isset($_POST['item_id']) ? intval($_POST['item_id']) : null;
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.']);
        exit;
    }
    
    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'File too large. Maximum size is 5MB.']);
        exit;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('item_') . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    $webUrl = $webPath . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // If item_id is provided, save to database
        if ($itemId) {
            try {
                // Verify item belongs to seller
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM yfc_items i
                    JOIN yfc_sales s ON i.sale_id = s.id
                    WHERE i.id = ? AND s.seller_id = ?
                ");
                $checkStmt->execute([$itemId, $sellerId]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    // Check if this is the first photo
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_item_photos WHERE item_id = ?");
                    $countStmt->execute([$itemId]);
                    $isFirst = $countStmt->fetchColumn() == 0;
                    
                    // Insert photo record
                    $insertStmt = $pdo->prepare("
                        INSERT INTO yfc_item_photos (item_id, photo_url, is_primary, uploaded_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $insertStmt->execute([$itemId, $webUrl, $isFirst ? 1 : 0]);
                    
                    $photoId = $pdo->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'url' => $webUrl,
                        'photo_id' => $photoId,
                        'is_primary' => $isFirst
                    ]);
                } else {
                    http_response_code(403);
                    echo json_encode(['error' => 'Item not found or access denied']);
                }
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        } else {
            // Return URL for temporary storage (will be associated when item is created)
            echo json_encode([
                'success' => true,
                'url' => $webUrl,
                'temp' => true
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to upload file']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
}
?>