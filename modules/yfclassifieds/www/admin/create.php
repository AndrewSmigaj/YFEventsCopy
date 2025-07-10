<?php
/**
 * YF Classifieds - Create New Item
 * 
 * Form for adding new classified items
 */

require_once __DIR__ . '/../../../../www/html/refactor/vendor/autoload.php';
require_once __DIR__ . '/../../../../www/html/refactor/admin/auth_check.php';
require_once __DIR__ . '/../../../../config/database.php';

// Get categories for dropdown
$categorySql = "SELECT * FROM yfc_categories ORDER BY name";
$categoryStmt = $pdo->query($categorySql);
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Get sellers for dropdown (simplified - in real app would be from session)
$sellerSql = "SELECT * FROM yfc_sellers ORDER BY business_name";
$sellerStmt = $pdo->query($sellerSql);
$sellers = $sellerStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $sellerId = intval($_POST['seller_id'] ?? 1); // Default to first seller
    $storeLocation = $_POST['store_location'] ?? '';
    $availableDays = intval($_POST['available_days'] ?? 30);
    $categoryIds = $_POST['categories'] ?? [];
    
    if ($title && $description && $price > 0) {
        try {
            // Insert the item
            $sql = "INSERT INTO yfc_items (seller_id, title, description, price, listing_type, store_location, available_until, status) 
                    VALUES (?, ?, ?, ?, 'classified', ?, DATE_ADD(CURDATE(), INTERVAL ? DAY), 'available')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sellerId, $title, $description, $price, $storeLocation, $availableDays]);
            
            $itemId = $pdo->lastInsertId();
            
            // Insert categories
            if (!empty($categoryIds)) {
                $categorySql = "INSERT INTO yfc_item_categories (item_id, category_id) VALUES (?, ?)";
                $categoryStmt = $pdo->prepare($categorySql);
                foreach ($categoryIds as $categoryId) {
                    $categoryStmt->execute([$itemId, intval($categoryId)]);
                }
            }
            
            $success = true;
            $message = "Item created successfully! ID: $itemId";
            
        } catch (Exception $e) {
            $error = "Error creating item: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Item - YF Classifieds Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .preview-area {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            min-height: 200px;
        }
        
        .price-input {
            position: relative;
        }
        
        .price-input::before {
            content: '$';
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: bold;
            color: #28a745;
        }
        
        .price-input input {
            padding-left: 25px;
        }
        
        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center">
                    <h1>➕ Add New Item</h1>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (isset($success) && $success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
                <div class="mt-2">
                    <a href="upload.php" class="btn btn-sm btn-primary">📸 Add Photos</a>
                    <a href="../item.php?id=<?= $itemId ?>" class="btn btn-sm btn-outline-primary" target="_blank">👀 View Item</a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="post" id="createItemForm">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h5 class="mb-4">📝 Item Information</h5>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="required">*</span></label>
                            <input type="text" class="form-control form-control-lg" id="title" name="title" 
                                   required maxlength="255" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                            <div class="form-text">Make it descriptive and appealing to buyers</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="required">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="6" 
                                      required maxlength="2000"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <div class="form-text">
                                <span id="charCount">0</span>/2000 characters. Include condition, features, dimensions, etc.
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="price" class="form-label">Price <span class="required">*</span></label>
                                <div class="price-input">
                                    <input type="number" class="form-control form-control-lg" id="price" name="price" 
                                           required min="0" step="0.01" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="available_days" class="form-label">Available For</label>
                                <select class="form-select" id="available_days" name="available_days">
                                    <option value="7">1 Week</option>
                                    <option value="14">2 Weeks</option>
                                    <option value="30" selected>1 Month</option>
                                    <option value="60">2 Months</option>
                                    <option value="90">3 Months</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Categories -->
                    <div class="form-section">
                        <h5 class="mb-4">📂 Categories</h5>
                        <div class="row">
                            <?php foreach ($categories as $category): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="categories[]" value="<?= $category['id'] ?>" 
                                               id="cat_<?= $category['id'] ?>">
                                        <label class="form-check-label" for="cat_<?= $category['id'] ?>">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Location -->
                    <div class="form-section">
                        <h5 class="mb-4">📍 Pickup Location</h5>
                        
                        <div class="mb-3">
                            <label for="store_location" class="form-label">Store Location</label>
                            <input type="text" class="form-control" id="store_location" name="store_location" 
                                   value="<?= htmlspecialchars($_POST['store_location'] ?? 'Main Store - Downtown Yakima') ?>">
                            <div class="form-text">Where customers can pick up this item</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="seller_id" class="form-label">Seller</label>
                            <select class="form-select" id="seller_id" name="seller_id">
                                <?php foreach ($sellers as $seller): ?>
                                    <option value="<?= $seller['id'] ?>" <?= ($seller['id'] == 1) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($seller['business_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Preview -->
                    <div class="form-section">
                        <h5 class="mb-4">👀 Preview</h5>
                        <div class="preview-area">
                            <div id="previewContent">
                                <i class="bi bi-eye-slash" style="font-size: 3rem; color: #dee2e6;"></i>
                                <p class="text-muted mt-3">Fill in the form to see a preview</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="form-section">
                        <h5 class="mb-4">🚀 Actions</h5>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-plus-circle"></i> Create Item
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                💡 After creating, you can add up to 6 photos via the upload interface
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counter
        const description = document.getElementById('description');
        const charCount = document.getElementById('charCount');
        
        function updateCharCount() {
            charCount.textContent = description.value.length;
        }
        
        description.addEventListener('input', updateCharCount);
        updateCharCount();
        
        // Live preview
        function updatePreview() {
            const title = document.getElementById('title').value;
            const price = document.getElementById('price').value;
            const previewContent = document.getElementById('previewContent');
            
            if (title || price) {
                previewContent.innerHTML = `
                    <div class="text-start">
                        <h6 class="mb-2">${title || 'Item Title'}</h6>
                        <div class="text-success fw-bold mb-2">$${price || '0.00'}</div>
                        <small class="text-muted">Preview - Add photos after creation</small>
                    </div>
                `;
            } else {
                previewContent.innerHTML = `
                    <i class="bi bi-eye-slash" style="font-size: 3rem; color: #dee2e6;"></i>
                    <p class="text-muted mt-3">Fill in the form to see a preview</p>
                `;
            }
        }
        
        document.getElementById('title').addEventListener('input', updatePreview);
        document.getElementById('price').addEventListener('input', updatePreview);
    </script>
</body>
</html>