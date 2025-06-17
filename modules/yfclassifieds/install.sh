#!/bin/bash

# YFClassifieds Module Installation Script
# This script sets up the database, directories, and permissions for the YFClassifieds module

echo "================================================"
echo "YFClassifieds Module Installation"
echo "================================================"
echo ""

# Color codes for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MODULE_DIR="$SCRIPT_DIR"
YFEVENTS_ROOT="$SCRIPT_DIR/../../../.."

# Database credentials - update these if needed
DB_HOST="localhost"
DB_USER="yfevents"
DB_PASS="yfevents_pass"
DB_NAME="yakima_finds"

echo "🔧 Starting YFClassifieds installation..."
echo ""

# Step 1: Check if running from correct location
echo "1️⃣ Checking installation location..."
if [ ! -f "$MODULE_DIR/module.json" ]; then
    echo -e "${RED}❌ Error: module.json not found. Please run this script from the yfclassifieds directory.${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Installation location verified${NC}"
echo ""

# Step 2: Create required directories
echo "2️⃣ Creating required directories..."
mkdir -p "$MODULE_DIR/www/assets/uploads/$(date +%Y/%m)"
mkdir -p "$MODULE_DIR/www/assets/css"
mkdir -p "$MODULE_DIR/www/assets/js"
mkdir -p "$MODULE_DIR/logs"
mkdir -p "$MODULE_DIR/cache"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Directories created successfully${NC}"
else
    echo -e "${RED}❌ Error creating directories${NC}"
    exit 1
fi
echo ""

# Step 3: Set permissions
echo "3️⃣ Setting directory permissions..."
chmod -R 755 "$MODULE_DIR/www/assets"
chmod -R 777 "$MODULE_DIR/www/assets/uploads"
chmod -R 777 "$MODULE_DIR/logs"
chmod -R 777 "$MODULE_DIR/cache"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Permissions set successfully${NC}"
else
    echo -e "${YELLOW}⚠️  Warning: Some permissions may not have been set correctly${NC}"
fi
echo ""

# Step 4: Install database schema
echo "4️⃣ Installing database schema..."
echo "   Please enter MySQL password for user '$DB_USER' when prompted:"

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MODULE_DIR/database/schema.sql" 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Database schema installed successfully${NC}"
else
    echo -e "${YELLOW}⚠️  Database installation may have encountered issues.${NC}"
    echo "   This could be normal if tables already exist."
    echo "   Attempting to verify installation..."
    
    # Check if tables exist
    TABLE_CHECK=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE 'yfc_item_photos';" 2>/dev/null | grep -c "yfc_item_photos")
    
    if [ "$TABLE_CHECK" -gt 0 ]; then
        echo -e "${GREEN}✅ YFClassifieds tables verified in database${NC}"
    else
        echo -e "${RED}❌ Could not verify database tables. Please check manually.${NC}"
    fi
fi
echo ""

# Step 5: Create placeholder images
echo "5️⃣ Creating placeholder images..."
cat > "$MODULE_DIR/www/assets/no-photo.svg" << 'EOF'
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
  <rect width="400" height="300" fill="#f0f0f0"/>
  <g fill="#999" font-family="Arial, sans-serif" font-size="24" text-anchor="middle">
    <text x="200" y="140">No Photo</text>
    <text x="200" y="170">Available</text>
  </g>
</svg>
EOF

cat > "$MODULE_DIR/www/assets/og-image.jpg" << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <rect width="1200" height="630" fill="#2c3e50"/>
  <text x="600" y="300" font-family="Arial, sans-serif" font-size="72" fill="white" text-anchor="middle">YF Classifieds</text>
  <text x="600" y="380" font-family="Arial, sans-serif" font-size="36" fill="#3498db" text-anchor="middle">Local Items for Sale</text>
</svg>
EOF

echo -e "${GREEN}✅ Placeholder images created${NC}"
echo ""

# Step 6: Create .htaccess for uploads directory
echo "6️⃣ Creating .htaccess for security..."
cat > "$MODULE_DIR/www/assets/uploads/.htaccess" << 'EOF'
# Prevent PHP execution in uploads directory
<Files *.php>
    deny from all
</Files>

# Allow image files only
<FilesMatch "\.(jpg|jpeg|png|gif|webp)$">
    Allow from all
</FilesMatch>

# Prevent directory listing
Options -Indexes
EOF

echo -e "${GREEN}✅ Security configuration created${NC}"
echo ""

# Step 7: Create API endpoints
echo "7️⃣ Creating API endpoints..."

# Create track share API
cat > "$MODULE_DIR/www/api/track-share.php" << 'EOF'
<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../config/database.php';

$itemId = intval($_GET['item_id'] ?? 0);
$platform = $_GET['platform'] ?? 'other';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($itemId > 0) {
    try {
        $stmt = $pdo->prepare("CALL sp_track_item_share(?, ?, ?, ?)");
        $stmt->execute([$itemId, $platform, $ipAddress, $userAgent]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to track share']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid item ID']);
}
EOF

# Create delete item API
cat > "$MODULE_DIR/www/admin/api/delete-item.php" << 'EOF'
<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../../www/html/refactor/admin/auth_check.php';
require_once __DIR__ . '/../../../../../config/database.php';

$itemId = intval($_GET['id'] ?? 0);

if ($itemId > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM yfc_items WHERE id = ? AND listing_type = 'classified'");
        $stmt->execute([$itemId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete item']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
}
EOF

echo -e "${GREEN}✅ API endpoints created${NC}"
echo ""

# Step 8: Create item detail page
echo "8️⃣ Creating item detail page..."
cat > "$MODULE_DIR/www/item.php" << 'EOF'
<?php
require_once __DIR__ . '/../../../config/database.php';

$itemId = intval($_GET['id'] ?? 0);

if ($itemId <= 0) {
    header('Location: index.php');
    exit;
}

// Track view
$pdo->exec("CALL sp_track_item_view($itemId)");

// Get item details
$stmt = $pdo->prepare("
    SELECT i.*, s.business_name as seller_name, s.phone as seller_phone,
           GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as categories
    FROM yfc_items i
    LEFT JOIN yfc_sellers s ON i.seller_id = s.id
    LEFT JOIN yfc_item_categories ic ON i.id = ic.item_id
    LEFT JOIN yfc_categories c ON ic.category_id = c.id
    WHERE i.id = ? AND i.listing_type = 'classified'
    GROUP BY i.id
");
$stmt->execute([$itemId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: index.php');
    exit;
}

// Get photos
$photoStmt = $pdo->prepare("SELECT * FROM yfc_item_photos WHERE item_id = ? ORDER BY photo_order");
$photoStmt->execute([$itemId]);
$photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);

// Get location
$locationStmt = $pdo->prepare("SELECT * FROM yfc_item_locations WHERE item_id = ? AND is_primary = TRUE LIMIT 1");
$locationStmt->execute([$itemId]);
$location = $locationStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['title']) ?> - YF Classifieds</title>
    <meta property="og:title" content="<?= htmlspecialchars($item['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars(substr($item['description'], 0, 150)) ?>">
    <meta property="og:image" content="<?= !empty($photos) ? 'https://' . $_SERVER['HTTP_HOST'] . $photos[0]['photo_url'] : '' ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">All Items</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($item['title']) ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-lg-8">
                <?php if (!empty($photos)): ?>
                    <div id="photoCarousel" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php foreach ($photos as $index => $photo): ?>
                                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                    <img src="<?= htmlspecialchars($photo['photo_url']) ?>" 
                                         class="d-block w-100" alt="<?= htmlspecialchars($item['title']) ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($photos) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#photoCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#photoCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-light p-5 text-center">
                        <p class="text-muted">No photos available</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <h1><?= htmlspecialchars($item['title']) ?></h1>
                <h2 class="text-success">$<?= number_format($item['price'], 2) ?></h2>
                
                <?php if ($item['categories']): ?>
                    <p class="text-muted"><?= htmlspecialchars($item['categories']) ?></p>
                <?php endif; ?>
                
                <hr>
                
                <h5>Description</h5>
                <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                
                <?php if ($item['store_location'] || $location): ?>
                    <hr>
                    <h5>Pickup Location</h5>
                    <p><?= htmlspecialchars($item['store_location'] ?: $location['location_name']) ?></p>
                <?php endif; ?>
                
                <hr>
                
                <div class="d-grid gap-2">
                    <a href="tel:<?= htmlspecialchars($item['seller_phone']) ?>" class="btn btn-primary btn-lg">
                        Call Store
                    </a>
                </div>
                
                <div class="mt-3">
                    <small class="text-muted">Views: <?= number_format($item['views']) ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
EOF

echo -e "${GREEN}✅ Item detail page created${NC}"
echo ""

# Step 9: Test module access
echo "9️⃣ Testing module access..."
if [ -f "$YFEVENTS_ROOT/www/html/refactor/src/Infrastructure/Config/modules.php" ]; then
    echo -e "${GREEN}✅ Module configuration found${NC}"
else
    echo -e "${YELLOW}⚠️  Module configuration not found at expected location${NC}"
fi
echo ""

# Step 10: Summary
echo "================================================"
echo "Installation Summary"
echo "================================================"
echo ""
echo -e "${GREEN}✅ YFClassifieds module installation complete!${NC}"
echo ""
echo "📍 Access Points:"
echo "   Public Gallery: https://backoffice.yakimafinds.com/refactor/classifieds"
echo "   Admin Panel: https://backoffice.yakimafinds.com/modules/yfclassifieds/www/admin/"
echo "   Module Manager: https://backoffice.yakimafinds.com/refactor/admin/modules.php"
echo ""
echo "📝 Next Steps:"
echo "   1. Enable the module in Module Manager if not already enabled"
echo "   2. Add some test items via the admin panel"
echo "   3. Upload photos for the items"
echo "   4. View the public gallery to test functionality"
echo ""
echo "🔧 Troubleshooting:"
echo "   - If database errors occur, check MySQL credentials in this script"
echo "   - If uploads fail, ensure Apache has write permissions to uploads directory"
echo "   - Check Apache error logs if pages don't load"
echo ""
echo "Installation log saved to: $MODULE_DIR/logs/install-$(date +%Y%m%d-%H%M%S).log"
echo ""

# Save installation log
{
    echo "YFClassifieds Installation Log"
    echo "Date: $(date)"
    echo "Module Directory: $MODULE_DIR"
    echo "Database: $DB_NAME"
    echo "User: $(whoami)"
    echo ""
    echo "Directories created:"
    ls -la "$MODULE_DIR/www/assets/"
    echo ""
    echo "Database tables:"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE 'yfc_%';" 2>/dev/null
} > "$MODULE_DIR/logs/install-$(date +%Y%m%d-%H%M%S).log" 2>&1

echo "✨ Installation complete!"