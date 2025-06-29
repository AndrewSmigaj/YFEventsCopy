<?php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

// Check authentication
if (!isset($_SESSION['seller_id'])) {
    header('Location: /seller/login.php');
    exit;
}

$sellerId = $_SESSION['seller_id'];
$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check for recovered images
$recoveredImages = [];
if (isset($_SESSION['recovered_images'])) {
    $recoveredImages = $_SESSION['recovered_images'];
    unset($_SESSION['recovered_images']); // Use only once
}

// Verify item belongs to seller through sale_id
$checkStmt = $pdo->prepare("
    SELECT i.*, s.seller_id, c.name as category_name
    FROM yfc_items i
    LEFT JOIN yfc_sales s ON i.sale_id = s.id
    LEFT JOIN yfc_categories c ON i.category_id = c.id
    WHERE i.id = ? AND s.seller_id = ?
");
$checkStmt->execute([$itemId, $sellerId]);
$item = $checkStmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: /seller/items.php');
    exit;
}

// Get categories
$catStmt = $pdo->query("SELECT id, name FROM yfc_categories ORDER BY name");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing photos
$photoStmt = $pdo->prepare("SELECT * FROM yfc_item_photos WHERE item_id = ? ORDER BY is_primary DESC, id");
$photoStmt->execute([$itemId]);
$existingPhotos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $categoryId = $_POST['category_id'] ?: null;
    $status = $_POST['status'] ?? 'active';
    
    if (empty($title)) {
        $error = 'Title is required';
    } else {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE yfc_items 
                SET title = ?, description = ?, price = ?, category_id = ?, status = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$title, $description, $price, $categoryId, $status, $itemId]);
            
            // Handle deleted photos
            if (!empty($_POST['deleted_photos'])) {
                $deletedIds = json_decode($_POST['deleted_photos'], true);
                if (is_array($deletedIds)) {
                    foreach ($deletedIds as $photoId) {
                        $deleteStmt = $pdo->prepare("DELETE FROM yfc_item_photos WHERE id = ? AND item_id = ?");
                        $deleteStmt->execute([$photoId, $itemId]);
                    }
                }
            }
            
            // Handle new uploaded images
            if (!empty($_POST['uploaded_images'])) {
                $uploadedImages = json_decode($_POST['uploaded_images'], true);
                if (is_array($uploadedImages)) {
                    // Check if item has any photos left
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_item_photos WHERE item_id = ?");
                    $countStmt->execute([$itemId]);
                    $hasPhotos = $countStmt->fetchColumn() > 0;
                    
                    $isFirst = !$hasPhotos;
                    foreach ($uploadedImages as $imageUrl) {
                        $photoStmt = $pdo->prepare("
                            INSERT INTO yfc_item_photos (item_id, photo_url, is_primary, uploaded_at)
                            VALUES (?, ?, ?, NOW())
                        ");
                        $photoStmt->execute([$itemId, $imageUrl, $isFirst ? 1 : 0]);
                        $isFirst = false;
                    }
                }
            }
            
            $_SESSION['success'] = 'Item updated successfully!';
            header('Location: /seller/items.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Error updating item. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - YF Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 30px;
        }
        .btn-primary {
            background: #667eea;
            border: none;
        }
        .btn-primary:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="/seller/dashboard.php">
                <i class="bi bi-shop"></i> YF Marketplace - Seller Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/seller/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/seller/items.php">My Items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/seller/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="mb-4">
                    <h1>Edit Item</h1>
                    <p class="text-muted">Update your item details</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($recoveredImages)): ?>
                    <div class="alert alert-info alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?= count($recoveredImages) ?> recovered image(s) have been added below. Don't forget to save your changes!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="form-card">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" 
                                   value="<?= htmlspecialchars($item['title']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="5"><?= htmlspecialchars($item['description']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price ($)</label>
                                <input type="number" name="price" class="form-control" 
                                       step="0.01" min="0" 
                                       value="<?= $item['price'] ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select">
                                    <option value="">No Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" 
                                                <?= $cat['id'] == $item['category_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= $item['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="sold" <?= $item['status'] === 'sold' ? 'selected' : '' ?>>Sold</option>
                                <option value="inactive" <?= $item['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Photos</label>
                            <?php
                            // Check if there are any unattached uploads
                            $uploadDir = dirname(__DIR__) . '/uploads/items/';
                            $hasUnattachedUploads = false;
                            if (is_dir($uploadDir)) {
                                $files = scandir($uploadDir);
                                foreach ($files as $file) {
                                    // Only check files uploaded by this seller (new format)
                                    if (preg_match('/^seller' . $sellerId . '_.*\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
                                        $fileTime = filemtime($uploadDir . $file);
                                        if ($fileTime > time() - 86400) { // Last 24 hours
                                            $photoCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_item_photos WHERE photo_url = ?");
                                            $photoCheckStmt->execute(['/uploads/items/' . $file]);
                                            if ($photoCheckStmt->fetchColumn() == 0) {
                                                $hasUnattachedUploads = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            if ($hasUnattachedUploads && empty($recoveredImages)): ?>
                                <div class="mb-2">
                                    <a href="/seller/recover-uploads.php?return=edit&item=<?= $itemId ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-images"></i> Add Previously Uploaded Images
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($existingPhotos)): ?>
                                <div class="existing-photos mb-3">
                                    <div class="row g-2">
                                        <?php foreach ($existingPhotos as $photo): ?>
                                            <div class="col-md-3 photo-item" data-photo-id="<?= $photo['id'] ?>">
                                                <div class="position-relative">
                                                    <img src="<?= htmlspecialchars($photo['photo_url']) ?>" class="img-fluid rounded" style="height: 150px; width: 100%; object-fit: cover;">
                                                    <?php if ($photo['is_primary']): ?>
                                                        <span class="badge bg-primary position-absolute top-0 start-0 m-2">Primary</span>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" onclick="removeExistingPhoto(<?= $photo['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div id="image-upload-container"></div>
                            <input type="hidden" name="uploaded_images" id="uploaded_images">
                            <input type="hidden" name="deleted_photos" id="deleted_photos">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Save Changes
                            </button>
                            <a href="/seller/items.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/image-upload.js"></script>
    <script>
        let deletedPhotos = [];
        let imageUploader;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize image upload component
            imageUploader = new ImageUploadComponent({
                container: '#image-upload-container',
                uploadUrl: '/seller/upload-image.php',
                maxImages: 5 - document.querySelectorAll('.photo-item').length, // Account for existing photos
                itemId: <?= $itemId ?>,
                onUploadComplete: function(data) {
                    updateUploadedImages();
                },
                onError: function(message) {
                    alert('Upload Error: ' + message);
                }
            });
            
            // Pre-populate with recovered images if any
            <?php if (!empty($recoveredImages)): ?>
            const recoveredImages = <?= json_encode($recoveredImages) ?>;
            recoveredImages.forEach(function(imageUrl) {
                // Add to the uploader's image list
                imageUploader.addExistingImage(imageUrl);
            });
            // Update the hidden field
            updateUploadedImages();
            <?php endif; ?>
        });
        
        function removeExistingPhoto(photoId) {
            if (confirm('Remove this photo?')) {
                deletedPhotos.push(photoId);
                document.querySelector(`[data-photo-id="${photoId}"]`).remove();
                document.getElementById('deleted_photos').value = JSON.stringify(deletedPhotos);
                
                // Update max images for uploader
                if (imageUploader) {
                    imageUploader.config.maxImages = 5 - document.querySelectorAll('.photo-item').length;
                    if (document.getElementById('upload-drop-zone').style.display === 'none') {
                        document.getElementById('upload-drop-zone').style.display = 'block';
                    }
                }
            }
        }
        
        function updateUploadedImages() {
            const images = imageUploader.getImages();
            document.getElementById('uploaded_images').value = JSON.stringify(images.map(img => img.url));
        }
    </script>
</body>
</html>