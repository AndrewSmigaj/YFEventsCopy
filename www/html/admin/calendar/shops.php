<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect to parent admin login with proper path
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';

// Get shops with categories directly from database
$query = "SELECT s.*, c.name as category_name,
          0 as image_count
          FROM local_shops s
          LEFT JOIN shop_categories c ON s.category_id = c.id
          ORDER BY s.created_at DESC";

$stmt = $db->query($query);
$shops = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Management - Advanced Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px;
        }
        .admin-content {
            flex: 1;
            padding: 20px;
            background: #f5f5f5;
        }
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .shop-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .shop-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        .shop-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .shop-category {
            color: #666;
            font-size: 14px;
        }
        .shop-details {
            padding: 20px;
        }
        .detail-row {
            margin-bottom: 10px;
            display: flex;
            align-items: start;
        }
        .detail-icon {
            width: 20px;
            color: #666;
            margin-right: 10px;
        }
        .shop-stats {
            display: flex;
            justify-content: space-around;
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        .shop-actions {
            display: flex;
            padding: 15px;
            gap: 10px;
            border-top: 1px solid #eee;
        }
        .shop-actions button {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-edit { background: #007bff; color: white; }
        .btn-images { background: #28a745; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .add-shop-btn {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .status-active { color: #28a745; }
        .status-inactive { color: #dc3545; }
        .no-data { color: #999; font-style: italic; }
        
        /* Import Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            position: relative;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 10px;
        }
        .close:hover { color: black; }
        .import-format-selector {
            margin: 20px 0;
        }
        .format-radio {
            margin: 10px 0;
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .format-radio:hover { border-color: #007bff; }
        .format-radio.selected { border-color: #007bff; background-color: #f8f9fa; }
        .format-radio input[type="radio"] { margin-right: 10px; }
        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            transition: all 0.3s;
        }
        .file-upload-area:hover { border-color: #007bff; }
        .file-upload-area.dragover { border-color: #007bff; background-color: #f8f9fa; }
        .import-preview {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            background: #f8f9fa;
        }
        .import-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-sidebar">
            <h2>Advanced Admin</h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 10px;">
                        <a href="./" style="color: white; text-decoration: none;">
                            <i class="fas fa-dashboard"></i> Dashboard
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="events.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-calendar"></i> Events
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="sources.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-rss"></i> Sources
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="shops.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-store"></i> Shops
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="../" style="color: white; text-decoration: none;">
                            <i class="fas fa-arrow-left"></i> Back to Main
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <div class="admin-content">
            <h1>Shop Management</h1>
            
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <button class="add-shop-btn" onclick="window.location.href='../shops.php?action=add'">
                    <i class="fas fa-plus"></i> Add New Shop
                </button>
                <button class="add-shop-btn" onclick="openImportModal()" style="background: #007bff;">
                    <i class="fas fa-upload"></i> Import Shops
                </button>
            </div>
            
            <div class="shop-grid">
                <?php foreach ($shops as $shop): ?>
                <div class="shop-card" data-shop-id="<?= $shop['id'] ?>">
                    <div class="shop-header">
                        <div class="shop-name"><?= htmlspecialchars($shop['name']) ?></div>
                        <div class="shop-category">
                            <?= htmlspecialchars($shop['category_name'] ?? 'Uncategorized') ?>
                            <i class="fas fa-circle status-<?= $shop['status'] === 'active' ? 'active' : 'inactive' ?>" 
                               style="font-size: 10px; margin-left: 10px;"
                               title="<?= ucfirst($shop['status']) ?>"></i>
                        </div>
                    </div>
                    
                    <div class="shop-details">
                        <?php if ($shop['address']): ?>
                        <div class="detail-row">
                            <i class="fas fa-map-marker-alt detail-icon"></i>
                            <div><?= htmlspecialchars($shop['address']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($shop['phone']): ?>
                        <div class="detail-row">
                            <i class="fas fa-phone detail-icon"></i>
                            <div><?= htmlspecialchars($shop['phone']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($shop['website']): ?>
                        <div class="detail-row">
                            <i class="fas fa-globe detail-icon"></i>
                            <div>
                                <a href="<?= htmlspecialchars($shop['website']) ?>" target="_blank">
                                    <?= parse_url($shop['website'], PHP_URL_HOST) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($shop['operating_hours']): ?>
                        <div class="detail-row">
                            <i class="fas fa-clock detail-icon"></i>
                            <div>
                                <?php 
                                $hours = $shop['operating_hours'];
                                if (is_string($hours)) {
                                    $hoursData = json_decode($hours, true);
                                    if ($hoursData && isset($hoursData['description'])) {
                                        echo nl2br(htmlspecialchars(substr($hoursData['description'], 0, 50))) . '...';
                                    } else {
                                        echo nl2br(htmlspecialchars(substr($hours, 0, 50))) . '...';
                                    }
                                } else {
                                    echo 'See details';
                                }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="shop-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $shop['image_count'] ?></div>
                            <div class="stat-label">Images</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">
                                <?= $shop['latitude'] && $shop['longitude'] ? 
                                    '<i class="fas fa-check" style="color: #28a745;"></i>' : 
                                    '<i class="fas fa-times" style="color: #dc3545;"></i>' ?>
                            </div>
                            <div class="stat-label">Geocoded</div>
                        </div>
                    </div>
                    
                    <div class="shop-actions">
                        <button class="btn-edit" onclick="editShop(<?= $shop['id'] ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-images" onclick="manageImages(<?= $shop['id'] ?>)">
                            <i class="fas fa-images"></i> Images
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($shops)): ?>
            <p class="no-data" style="text-align: center; margin-top: 50px;">
                No shops found. Click "Add New Shop" to create one.
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeImportModal()">&times;</span>
            <h2>Import Shops</h2>
            
            <div class="import-format-selector">
                <h3>Select Import Format:</h3>
                <div class="format-radio selected" onclick="selectFormat('csv')">
                    <input type="radio" name="import_format" value="csv" checked>
                    <strong>CSV (Comma Separated Values)</strong><br>
                    <small>Standard spreadsheet format with headers</small>
                </div>
                <div class="format-radio" onclick="selectFormat('json')">
                    <input type="radio" name="import_format" value="json">
                    <strong>JSON (JavaScript Object Notation)</strong><br>
                    <small>Structured data format for technical users</small>
                </div>
            </div>

            <div class="file-upload-area" onclick="document.getElementById('importFile').click()">
                <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                <p><strong>Click to select file</strong> or drag and drop</p>
                <p style="font-size: 12px; color: #666;">Supported formats: .csv, .json (max 10MB)</p>
                <input type="file" id="importFile" style="display: none;" accept=".csv,.json" onchange="handleFileSelect(event)">
            </div>

            <div id="importPreview" class="import-preview" style="display: none;">
                <h4>Preview (first 5 rows):</h4>
                <div id="previewContent"></div>
            </div>

            <div class="import-actions">
                <button class="btn btn-secondary" onclick="closeImportModal()">Cancel</button>
                <button class="btn btn-primary" id="importBtn" onclick="performImport()" disabled>
                    <i class="fas fa-upload"></i> Import Shops
                </button>
            </div>
        </div>
    </div>
    
    <script>
        let selectedFile = null;
        let importFormat = 'csv';
        
        function editShop(shopId) {
            window.location.href = `/admin/shops.php?action=edit&id=${shopId}`;
        }
        
        function manageImages(shopId) {
            window.location.href = `/admin/shops.php?action=images&id=${shopId}`;
        }
        
        function openImportModal() {
            document.getElementById('importModal').style.display = 'block';
        }
        
        function closeImportModal() {
            document.getElementById('importModal').style.display = 'none';
            selectedFile = null;
            document.getElementById('importFile').value = '';
            document.getElementById('importPreview').style.display = 'none';
            document.getElementById('importBtn').disabled = true;
        }
        
        function selectFormat(format) {
            importFormat = format;
            document.querySelectorAll('.format-radio').forEach(el => el.classList.remove('selected'));
            event.target.closest('.format-radio').classList.add('selected');
            document.querySelector(`input[value="${format}"]`).checked = true;
            
            // Update file input accept attribute
            const fileInput = document.getElementById('importFile');
            fileInput.accept = format === 'csv' ? '.csv' : '.json';
        }
        
        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            selectedFile = file;
            
            // Validate file size (max 10MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be less than 10MB');
                return;
            }
            
            // Validate file type
            const extension = file.name.split('.').pop().toLowerCase();
            if ((importFormat === 'csv' && extension !== 'csv') || 
                (importFormat === 'json' && extension !== 'json')) {
                alert(`Please select a ${importFormat.toUpperCase()} file`);
                return;
            }
            
            // Read and preview file
            const reader = new FileReader();
            reader.onload = function(e) {
                previewFile(e.target.result);
            };
            reader.readAsText(file);
        }
        
        function previewFile(content) {
            const previewDiv = document.getElementById('importPreview');
            const previewContent = document.getElementById('previewContent');
            
            try {
                let preview = '';
                
                if (importFormat === 'csv') {
                    const lines = content.split('\n').slice(0, 6); // Header + 5 rows
                    preview = '<table style="width: 100%; border-collapse: collapse;">';
                    
                    lines.forEach((line, index) => {
                        if (line.trim()) {
                            const cells = line.split(',').map(cell => cell.trim().replace(/"/g, ''));
                            const tag = index === 0 ? 'th' : 'td';
                            const style = index === 0 ? 'font-weight: bold; background: #f0f0f0;' : '';
                            
                            preview += '<tr>';
                            cells.forEach(cell => {
                                preview += `<${tag} style="border: 1px solid #ddd; padding: 8px; ${style}">${cell || '(empty)'}</${tag}>`;
                            });
                            preview += '</tr>';
                        }
                    });
                    preview += '</table>';
                } else {
                    // JSON preview
                    const data = JSON.parse(content);
                    const items = Array.isArray(data) ? data.slice(0, 5) : [data];
                    preview = '<pre style="font-size: 12px; max-height: 200px; overflow: auto;">' + 
                             JSON.stringify(items, null, 2) + '</pre>';
                }
                
                previewContent.innerHTML = preview;
                previewDiv.style.display = 'block';
                document.getElementById('importBtn').disabled = false;
                
            } catch (error) {
                alert('Error reading file: ' + error.message);
                previewDiv.style.display = 'none';
            }
        }
        
        function performImport() {
            if (!selectedFile) {
                alert('Please select a file first');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', selectedFile);
            formData.append('format', importFormat);
            
            document.getElementById('importBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
            document.getElementById('importBtn').disabled = true;
            
            fetch('../ajax/import_shops.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Successfully imported ${data.imported} shops!`);
                    closeImportModal();
                    location.reload(); // Refresh the page to show new shops
                } else {
                    alert('Import failed: ' + data.message);
                    document.getElementById('importBtn').innerHTML = '<i class="fas fa-upload"></i> Import Shops';
                    document.getElementById('importBtn').disabled = false;
                }
            })
            .catch(error => {
                alert('Import failed: ' + error.message);
                document.getElementById('importBtn').innerHTML = '<i class="fas fa-upload"></i> Import Shops';
                document.getElementById('importBtn').disabled = false;
            });
        }
        
        // Drag and drop functionality
        const uploadArea = document.querySelector('.file-upload-area');
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('importFile').files = files;
                handleFileSelect({target: {files: files}});
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('importModal');
            if (event.target === modal) {
                closeImportModal();
            }
        }
    </script>
</body>
</html>