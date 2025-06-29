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
    $uploadDir = dirname(__DIR__) . '/uploads/items/';
    $webPath = '/uploads/items/';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create upload directory']);
            exit;
        }
    }
    
    $file = $_FILES['image'];
    $itemId = isset($_POST['item_id']) ? intval($_POST['item_id']) : null;
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        $error = isset($errorMessages[$file['error']]) ? $errorMessages[$file['error']] : 'Unknown upload error';
        http_response_code(400);
        echo json_encode(['error' => $error, 'error_code' => $file['error']]);
        exit;
    }
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.', 'type' => $file['type']]);
        exit;
    }
    
    // Check file size (2MB max due to PHP upload_max_filesize)
    if ($file['size'] > 2 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'File too large. Maximum size is 2MB.', 'size' => $file['size']]);
        exit;
    }
    
    // Generate unique filename with seller ID prefix for security
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'seller' . $sellerId . '_' . uniqid() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    $webUrl = $webPath . $filename;
    
    // Debug info
    error_log("Upload attempt - Seller: $sellerId, Dir: $uploadDir, File: $filename, Path: $uploadPath");
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // If item_id is provided, save to database
        if ($itemId) {
            try {
                // Verify item belongs to seller through sale_id
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM yfc_items i
                    LEFT JOIN yfc_sales s ON i.sale_id = s.id
                    WHERE i.id = ? AND s.seller_id = ?
                ");
                $checkStmt->execute([$itemId, $sellerId]);
                
                $hasAccess = $checkStmt->fetchColumn() > 0;
                
                if ($hasAccess) {
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
                    echo json_encode([
                        'error' => 'Item not found or access denied'
                    ]);
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
        // Get more details about the failure
        $uploadError = error_get_last();
        error_log("Upload failed - Error: " . json_encode($uploadError));
        
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to upload file',
            'debug' => [
                'upload_dir' => $uploadDir,
                'upload_path' => $uploadPath,
                'is_writable' => is_writable($uploadDir),
                'tmp_name' => $file['tmp_name'],
                'tmp_exists' => file_exists($file['tmp_name']),
                'last_error' => $uploadError
            ]
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        'error' => 'No file uploaded',
        'method' => $_SERVER['REQUEST_METHOD'],
        'files' => isset($_FILES) ? array_keys($_FILES) : [],
        'post' => array_keys($_POST)
    ]);
}
?>