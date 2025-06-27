<?php
session_start();
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';

// Check authentication
if (!isset($_SESSION['seller_id'])) {
    header('Location: /seller/login.php');
    exit;
}

$sellerId = $_SESSION['seller_id'];
$error = '';
$success = '';

// Get categories
$categoriesStmt = $pdo->query("SELECT * FROM yfc_categories ORDER BY name");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get the classifieds sale for this seller
$salesStmt = $pdo->prepare("SELECT id FROM yfc_sales WHERE seller_id = ? AND title = 'General Classifieds' LIMIT 1");
$salesStmt->execute([$sellerId]);
$sale = $salesStmt->fetch(PDO::FETCH_ASSOC);
$saleId = $sale ? $sale['id'] : 3; // Default to sale ID 3 if not found

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $categoryId = intval($_POST['category_id'] ?? 0);
    $condition = $_POST['condition'] ?? '';
    
    if (empty($title)) {
        $error = 'Title is required';
    } elseif ($price <= 0) {
        $error = 'Please enter a valid price';
    } else {
        try {
            // Insert the item
            $insertStmt = $pdo->prepare("
                INSERT INTO yfc_items (
                    sale_id, title, description, category_id, price, 
                    listing_type, status, created_at, available_until
                ) VALUES (
                    ?, ?, ?, ?, ?, 'classified', 'active', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)
                )
            ");
            
            $insertStmt->execute([
                $saleId,
                $title,
                $description,
                $categoryId ?: null,
                $price
            ]);
            
            $itemId = $pdo->lastInsertId();
            
            // Handle uploaded images
            if (!empty($_POST['uploaded_images'])) {
                $uploadedImages = json_decode($_POST['uploaded_images'], true);
                if (is_array($uploadedImages)) {
                    $isFirst = true;
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
            
            $success = 'Classified ad created successfully!';
            
            // Redirect to the item page or dashboard
            header('Location: /seller/dashboard.php?success=1');
            exit;
            
        } catch (PDOException $e) {
            $error = 'Error creating listing: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Classified Ad - YF Marketplace</title>
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
        .create-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 0 auto;
        }
        .btn-primary {
            background: #667eea;
            border: none;
        }
        .btn-primary:hover {
            background: #764ba2;
        }
        .preview-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
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
                        <a class="nav-link" href="/seller/sales">Estate Sales</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/seller/classifieds">Classifieds</a>
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
            <div class="col-12">
                <h1><i class="bi bi-megaphone"></i> Create Classified Ad</h1>
                <p class="text-muted">List an item for direct sale to buyers</p>
            </div>
        </div>

        <div class="create-form">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg" id="title" name="title" 
                           placeholder="e.g., Vintage Record Player" required 
                           value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                    <small class="form-text text-muted">Make it descriptive and include key details</small>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="price" name="price" 
                                   step="0.01" min="0.01" required
                                   value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" 
                                        <?= ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="6" 
                              placeholder="Describe your item in detail. Include condition, features, dimensions, etc."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    <small class="form-text text-muted">The more details you provide, the better!</small>
                </div>

                <div class="mb-4">
                    <label class="form-label">Photos</label>
                    <div id="image-upload-container"></div>
                    <input type="hidden" name="uploaded_images" id="uploaded_images">
                </div>

                <div class="d-flex justify-content-between">
                    <a href="/seller/dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> Create Listing
                    </button>
                </div>
            </form>
        </div>

        <div class="preview-section">
            <h5><i class="bi bi-eye"></i> Listing Tips</h5>
            <ul>
                <li>Use clear, descriptive titles that include brand names and key features</li>
                <li>Set competitive prices by researching similar items</li>
                <li>Include measurements and condition details in the description</li>
                <li>Respond to inquiries promptly to increase sales</li>
                <li>Your listing will be active for 30 days and can be renewed</li>
            </ul>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/image-upload.js"></script>
    <script>
        // Initialize image upload component
        let imageUploader;
        document.addEventListener('DOMContentLoaded', function() {
            imageUploader = new ImageUploadComponent({
                container: '#image-upload-container',
                uploadUrl: '/seller/upload-image.php',
                maxImages: 5,
                onUploadComplete: function(data) {
                    // Update hidden field with uploaded images
                    const images = imageUploader.getImages();
                    document.getElementById('uploaded_images').value = JSON.stringify(images.map(img => img.url));
                },
                onError: function(message) {
                    alert('Upload Error: ' + message);
                }
            });
        });
    </script>
</body>
</html>