<?php
// Local Shops Management Interface
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once '../../../config/database.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO local_shops (name, description, address, phone, website, 
                                               operating_hours, latitude, longitude) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    // Handle operating hours JSON
                    $hours = !empty($_POST['hours']) ? json_encode(['text' => $_POST['hours']]) : null;
                    
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['address'],
                        $_POST['phone'],
                        $_POST['website'],
                        $hours,
                        $_POST['latitude'] ?: null,
                        $_POST['longitude'] ?: null
                    ]);
                    
                    $message = "Shop added successfully!";
                } catch (Exception $e) {
                    $error = "Error adding shop: " . $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE local_shops SET 
                            name = ?,
                            description = ?,
                            address = ?,
                            phone = ?,
                            website = ?,
                            operating_hours = ?,
                            latitude = ?,
                            longitude = ?
                        WHERE id = ?
                    ");
                    
                    // Handle operating hours JSON
                    $hours = !empty($_POST['hours']) ? json_encode(['text' => $_POST['hours']]) : null;
                    
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['description'],
                        $_POST['address'],
                        $_POST['phone'],
                        $_POST['website'],
                        $hours,
                        $_POST['latitude'] ?: null,
                        $_POST['longitude'] ?: null,
                        $_POST['shop_id']
                    ]);
                    
                    $message = "Shop updated successfully!";
                } catch (Exception $e) {
                    $error = "Error updating shop: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM local_shops WHERE id = ?");
                $stmt->execute([$_POST['shop_id']]);
                $message = "Shop deleted!";
                break;
                
            case 'bulk_action':
                if (!empty($_POST['selected_shops']) && $_POST['bulk_action'] === 'delete') {
                    $shopIds = $_POST['selected_shops'];
                    $placeholders = str_repeat('?,', count($shopIds) - 1) . '?';
                    
                    $stmt = $pdo->prepare("DELETE FROM local_shops WHERE id IN ($placeholders)");
                    $stmt->execute($shopIds);
                    $message = count($shopIds) . " shops deleted!";
                }
                break;
                
            case 'geocode':
                // Geocode address
                $address = $_POST['geocode_address'];
                $apiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
                
                if ($apiKey) {
                    $url = "https://maps.googleapis.com/maps/api/geocode/json?" . http_build_query([
                        'address' => $address,
                        'key' => $apiKey
                    ]);
                    
                    $response = json_decode(file_get_contents($url), true);
                    
                    if ($response['status'] === 'OK' && !empty($response['results'])) {
                        $location = $response['results'][0]['geometry']['location'];
                        echo json_encode([
                            'success' => true,
                            'lat' => $location['lat'],
                            'lng' => $location['lng']
                        ]);
                        exit;
                    }
                }
                
                echo json_encode(['success' => false, 'error' => 'Geocoding failed']);
                exit;
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$shopType = $_GET['type'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = '(name LIKE ? OR description LIKE ? OR address LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Shop type filter disabled - column not available

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countQuery = "SELECT COUNT(*) FROM local_shops $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalShops = $stmt->fetchColumn();
$totalPages = ceil($totalShops / $perPage);

// Get shops
$query = "SELECT * FROM local_shops $whereClause ORDER BY name ASC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$shops = $stmt->fetchAll();

// Get shop types for dropdown - not available in current schema
$shopTypes = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local Shops Management - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .shop-row:hover {
            background-color: #f8f9fa;
        }
        .shop-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        .shop-description {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .btn-group-xs .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .edit-form {
            display: none;
            background: #f8f9fa;
            padding: 1rem;
            margin-top: 0.5rem;
            border-radius: 0.25rem;
        }
        .add-form {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-store"></i> Local Shops Management</h1>
                    <div>
                        <button class="btn btn-success" onclick="toggleAddForm()">
                            <i class="fas fa-plus"></i> Add New Shop
                        </button>
                        <a href="./" class="btn btn-secondary">← Back to Dashboard</a>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Add New Shop Form -->
                <div class="card mb-4 add-form" id="addForm">
                    <div class="card-header">
                        <h5 class="mb-0">Add New Shop</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="add">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Shop Name *</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" class="form-control" 
                                           placeholder="(509) 555-1234">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Address *</label>
                                    <div class="input-group">
                                        <input type="text" name="address" class="form-control" required>
                                        <button type="button" class="btn btn-outline-secondary" onclick="geocodeAddress('add')">
                                            <i class="fas fa-map-marker-alt"></i> Geocode
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="number" name="latitude" class="form-control" 
                                           step="0.000001" id="add-latitude">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="number" name="longitude" class="form-control" 
                                           step="0.000001" id="add-longitude">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Website</label>
                                    <input type="url" name="website" class="form-control" 
                                           placeholder="https://example.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Hours</label>
                                    <input type="text" name="hours" class="form-control" 
                                           placeholder="Mon-Fri 9am-5pm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Image URL</label>
                                    <input type="url" name="image_url" class="form-control" 
                                           placeholder="https://example.com/image.jpg">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Add Shop
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Shop Type</label>
                                <select name="type" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Types</option>
                                    <?php foreach ($shopTypes as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>" 
                                                <?= $shopType === $type ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search shops..." value="<?= htmlspecialchars($search) ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <a href="shops.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Shops List -->
                <form method="post" id="bulkForm">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Local Shops (<?= $totalShops ?> total)</h5>
                                <div class="d-flex gap-2">
                                    <select name="bulk_action" class="form-select form-select-sm" style="width: auto;">
                                        <option value="">Bulk Actions</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                    <button type="submit" name="action" value="bulk_action" class="btn btn-sm btn-primary">
                                        Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th width="30">
                                                <input type="checkbox" class="form-check-input" id="selectAll">
                                            </th>
                                            <th width="70">Image</th>
                                            <th>Name</th>
                                            <th>Address</th>
                                            <th>Contact</th>
                                            <th>Hours</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($shops as $shop): ?>
                                        <tr class="shop-row">
                                            <td>
                                                <input type="checkbox" class="form-check-input shop-checkbox" 
                                                       name="selected_shops[]" value="<?= $shop['id'] ?>">
                                            </td>
                                            <td>
                                                <?php if ($shop['image_url']): ?>
                                                    <img src="<?= htmlspecialchars($shop['image_url']) ?>" 
                                                         alt="<?= htmlspecialchars($shop['name']) ?>" 
                                                         class="shop-image">
                                                <?php else: ?>
                                                    <div class="shop-image bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-store text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($shop['name']) ?></strong>
                                                <?php if ($shop['description']): ?>
                                                    <div class="shop-description text-muted small">
                                                        <?= htmlspecialchars($shop['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($shop['address']) ?>
                                                <?php if ($shop['latitude'] && $shop['longitude']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt"></i> 
                                                        <?= number_format($shop['latitude'], 6) ?>, 
                                                        <?= number_format($shop['longitude'], 6) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($shop['phone']): ?>
                                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($shop['phone']) ?><br>
                                                <?php endif; ?>
                                                <?php if ($shop['website']): ?>
                                                    <a href="<?= htmlspecialchars($shop['website']) ?>" target="_blank" class="text-decoration-none">
                                                        <i class="fas fa-globe"></i> Website
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $hours = json_decode($shop['operating_hours'] ?? '{}', true);
                                                $hoursText = $hours['text'] ?? $hours['regular'] ?? '';
                                                if (!empty($hoursText)) {
                                                    echo '<small>' . htmlspecialchars($hoursText) . '</small>';
                                                } else {
                                                    echo '<small>-</small>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-xs">
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            onclick="toggleEditForm(<?= $shop['id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="submit" name="action" value="delete" 
                                                            class="btn btn-sm btn-danger"
                                                            form="quick-action-<?= $shop['id'] ?>"
                                                            onclick="return confirm('Delete this shop?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="8" class="p-0">
                                                <div id="edit-form-<?= $shop['id'] ?>" class="edit-form">
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                                        
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Shop Name *</label>
                                                                <input type="text" name="name" class="form-control" 
                                                                       value="<?= htmlspecialchars($shop['name']) ?>" required>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label">Phone</label>
                                                                <input type="tel" name="phone" class="form-control" 
                                                                       value="<?= htmlspecialchars($shop['phone']) ?>"
                                                                       placeholder="(509) 555-1234">
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label class="form-label">Description</label>
                                                                <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($shop['description']) ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Address *</label>
                                                                <div class="input-group">
                                                                    <input type="text" name="address" class="form-control" 
                                                                           value="<?= htmlspecialchars($shop['address']) ?>" required>
                                                                    <button type="button" class="btn btn-outline-secondary" 
                                                                            onclick="geocodeAddress('edit-<?= $shop['id'] ?>')">
                                                                        <i class="fas fa-map-marker-alt"></i> Geocode
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label">Latitude</label>
                                                                <input type="number" name="latitude" class="form-control" 
                                                                       step="0.000001" value="<?= $shop['latitude'] ?>"
                                                                       id="edit-<?= $shop['id'] ?>-latitude">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label">Longitude</label>
                                                                <input type="number" name="longitude" class="form-control" 
                                                                       step="0.000001" value="<?= $shop['longitude'] ?>"
                                                                       id="edit-<?= $shop['id'] ?>-longitude">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Website</label>
                                                                <input type="url" name="website" class="form-control" 
                                                                       value="<?= htmlspecialchars($shop['website']) ?>"
                                                                       placeholder="https://example.com">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Hours</label>
                                                                <?php 
                                                                $hoursData = json_decode($shop['operating_hours'] ?? '{}', true);
                                                                $hoursString = $hoursData['text'] ?? $hoursData['regular'] ?? '';
                                                                ?>
                                                                <input type="text" name="hours" class="form-control" 
                                                                       value="<?= htmlspecialchars($hoursString) ?>"
                                                                       placeholder="Mon-Fri 9am-5pm">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Image URL</label>
                                                                <input type="url" name="image_url" class="form-control" 
                                                                       value="<?= htmlspecialchars($shop['image_url']) ?>"
                                                                       placeholder="https://example.com/image.jpg">
                                                            </div>
                                                            <div class="col-12">
                                                                <button type="submit" class="btn btn-primary">
                                                                    <i class="fas fa-save"></i> Save Changes
                                                                </button>
                                                                <button type="button" class="btn btn-secondary" 
                                                                        onclick="toggleEditForm(<?= $shop['id'] ?>)">
                                                                    Cancel
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <form id="quick-action-<?= $shop['id'] ?>" method="post" style="display: none;">
                                            <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                        </form>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <div class="card-footer">
                            <nav>
                                <ul class="pagination mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&type=<?= $shopType ?>&search=<?= urlencode($search) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.shop-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        
        // Toggle edit form
        function toggleEditForm(shopId) {
            const form = document.getElementById('edit-form-' + shopId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        // Toggle add form
        function toggleAddForm() {
            const form = document.getElementById('addForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        // Geocode address
        async function geocodeAddress(formType) {
            let addressInput, latInput, lngInput;
            
            if (formType === 'add') {
                addressInput = document.querySelector('#addForm input[name="address"]');
                latInput = document.getElementById('add-latitude');
                lngInput = document.getElementById('add-longitude');
            } else {
                const formId = formType;
                addressInput = document.querySelector(`#${formId} input[name="address"]`);
                latInput = document.getElementById(`${formId}-latitude`);
                lngInput = document.getElementById(`${formId}-longitude`);
            }
            
            const address = addressInput.value;
            if (!address) {
                alert('Please enter an address first');
                return;
            }
            
            try {
                const response = await fetch('shops.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=geocode&geocode_address=${encodeURIComponent(address)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    latInput.value = data.lat;
                    lngInput.value = data.lng;
                    alert('Geocoding successful! Coordinates updated.');
                } else {
                    alert('Geocoding failed: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error geocoding address: ' + error.message);
            }
        }
    </script>
</body>
</html>