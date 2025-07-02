<?php
/**
 * YF Classifieds - Photo Upload Interface
 * 
 * Allows bulk uploading of product photos with drag-and-drop support
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../admin/auth_check.php';
require_once __DIR__ . '/../../../../config/database.php';

// Get existing items for selection
$itemsSql = "SELECT i.id, i.title, i.price, 
             (SELECT COUNT(*) FROM yfc_item_photos WHERE item_id = i.id) as photo_count
             FROM yfc_items i
             WHERE i.listing_type = 'classified'
             AND i.status = 'available'
             ORDER BY i.created_at DESC";
$itemsStmt = $pdo->query($itemsSql);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    $itemId = intval($_POST['item_id'] ?? 0);
    $uploadedFiles = [];
    $errors = [];
    
    if ($itemId > 0) {
        $uploadDir = __DIR__ . '/../assets/uploads/' . date('Y/m/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        foreach ($_FILES['photos']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['photos']['name'][$key];
                $fileType = $_FILES['photos']['type'][$key];
                $fileSize = $_FILES['photos']['size'][$key];
                
                if (!in_array($fileType, $allowedTypes)) {
                    $errors[] = "$fileName: Invalid file type";
                    continue;
                }
                
                if ($fileSize > $maxSize) {
                    $errors[] = "$fileName: File too large (max 5MB)";
                    continue;
                }
                
                // Generate unique filename
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = uniqid('item_' . $itemId . '_') . '.' . $ext;
                $destination = $uploadDir . $newFileName;
                
                if (move_uploaded_file($tmpName, $destination)) {
                    // Get current max photo order
                    $orderStmt = $pdo->prepare("SELECT MAX(photo_order) FROM yfc_item_photos WHERE item_id = ?");
                    $orderStmt->execute([$itemId]);
                    $maxOrder = $orderStmt->fetchColumn() ?: 0;
                    
                    // Insert photo record
                    $photoUrl = '/modules/yfclassifieds/www/assets/uploads/' . date('Y/m/') . $newFileName;
                    $insertStmt = $pdo->prepare("INSERT INTO yfc_item_photos (item_id, photo_url, photo_order, is_primary) 
                                                VALUES (?, ?, ?, ?)");
                    
                    // First photo becomes primary if none exists
                    $isPrimary = $maxOrder === 0 ? true : false;
                    $insertStmt->execute([$itemId, $photoUrl, $maxOrder + 1, $isPrimary]);
                    
                    $uploadedFiles[] = $fileName;
                } else {
                    $errors[] = "$fileName: Upload failed";
                }
            }
        }
        
        if (!empty($uploadedFiles)) {
            $success = true;
            $message = count($uploadedFiles) . " photos uploaded successfully";
        }
    } else {
        $errors[] = "Please select an item";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Photos - YF Classifieds Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .upload-zone {
            border: 3px dashed #dee2e6;
            border-radius: 10px;
            padding: 3rem;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        
        .upload-icon {
            font-size: 4rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .file-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .preview-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        
        .preview-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .preview-item .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .preview-item .file-name {
            padding: 5px;
            font-size: 0.75rem;
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }
        
        .item-selector {
            max-width: 600px;
            margin: 0 auto 2rem;
        }
        
        .progress-container {
            display: none;
            margin-top: 2rem;
        }
        
        .item-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .photo-count {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex justify-content-between align-items-center">
                    <h1>📸 Upload Photos</h1>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (isset($success) && $success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h6 class="alert-heading">Upload Errors:</h6>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form id="uploadForm" method="post" enctype="multipart/form-data">
            <!-- Item Selector -->
            <div class="item-selector">
                <label for="item_id" class="form-label">Select Item</label>
                <select name="item_id" id="item_id" class="form-select form-select-lg" required>
                    <option value="">Choose an item to add photos...</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= $item['id'] ?>">
                            <div class="item-option">
                                <span><?= htmlspecialchars($item['title']) ?> - $<?= number_format($item['price'], 2) ?></span>
                                <span class="photo-count"><?= $item['photo_count'] ?> photos</span>
                            </div>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    Each item can have up to 6 photos. The first photo will be the primary image.
                </div>
            </div>
            
            <!-- Upload Zone -->
            <div class="upload-zone" id="uploadZone">
                <input type="file" name="photos[]" id="fileInput" multiple accept="image/jpeg,image/png,image/webp" style="display: none;">
                <div class="upload-icon">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <h4>Drag & Drop Photos Here</h4>
                <p class="text-muted mb-3">or click to browse</p>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                    <i class="bi bi-folder-open"></i> Select Photos
                </button>
                <p class="text-muted mt-3 mb-0">
                    <small>Accepted formats: JPEG, PNG, WebP • Max size: 5MB per photo</small>
                </p>
            </div>
            
            <!-- File Preview -->
            <div id="filePreview" class="file-preview"></div>
            
            <!-- Progress Bar -->
            <div class="progress-container" id="progressContainer">
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%"></div>
                </div>
                <p class="text-center mt-2">Uploading photos...</p>
            </div>
            
            <!-- Submit Button -->
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-success btn-lg" id="submitBtn" style="display: none;">
                    <i class="bi bi-upload"></i> Upload Photos
                </button>
            </div>
        </form>
        
        <!-- Tips -->
        <div class="card mt-5">
            <div class="card-body">
                <h5 class="card-title">📌 Photo Upload Tips</h5>
                <ul class="mb-0">
                    <li>Take photos in good lighting for best results</li>
                    <li>Show the item from multiple angles</li>
                    <li>Include close-ups of important details or features</li>
                    <li>Avoid blurry or dark photos</li>
                    <li>Square photos (1:1 ratio) work best for the gallery</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        const submitBtn = document.getElementById('submitBtn');
        const itemSelect = document.getElementById('item_id');
        
        let selectedFiles = [];
        
        // Drag and drop events
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        
        uploadZone.addEventListener('click', () => {
            fileInput.click();
        });
        
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        function handleFiles(files) {
            const maxFiles = 6;
            const currentPhotos = parseInt(itemSelect.selectedOptions[0]?.textContent.match(/(\d+) photos/)?.[1] || 0);
            const remainingSlots = maxFiles - currentPhotos;
            
            if (!itemSelect.value) {
                alert('Please select an item first');
                return;
            }
            
            if (files.length > remainingSlots) {
                alert(`This item can only have ${remainingSlots} more photo(s)`);
                return;
            }
            
            selectedFiles = Array.from(files);
            displayPreview();
            submitBtn.style.display = selectedFiles.length > 0 ? 'inline-block' : 'none';
        }
        
        function displayPreview() {
            filePreview.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}">
                        <button type="button" class="remove-btn" onclick="removeFile(${index})">
                            <i class="bi bi-x"></i>
                        </button>
                        <div class="file-name">${file.name}</div>
                    `;
                    filePreview.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }
        
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            displayPreview();
            submitBtn.style.display = selectedFiles.length > 0 ? 'inline-block' : 'none';
        }
        
        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', (e) => {
            if (selectedFiles.length === 0) {
                e.preventDefault();
                alert('Please select photos to upload');
                return;
            }
            
            document.getElementById('progressContainer').style.display = 'block';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>