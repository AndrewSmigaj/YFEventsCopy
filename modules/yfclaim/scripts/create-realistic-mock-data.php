<?php
/**
 * Create realistic mock data for YFClaim with images
 * This creates a fully functional test environment
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db_connection.php';

use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\BuyerModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;

echo "Creating Realistic Mock Data for YFClaim\n";
echo "=======================================\n\n";

// Initialize models
$sellerModel = new SellerModel($pdo);
$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);
$buyerModel = new BuyerModel($pdo);
$offerModel = new OfferModel($pdo);

// Clear existing test data (optional - comment out to keep existing data)
echo "Clearing existing data...\n";
$pdo->exec("DELETE FROM yfc_item_images");
$pdo->exec("DELETE FROM yfc_offers");
$pdo->exec("DELETE FROM yfc_buyers");
$pdo->exec("DELETE FROM yfc_items");
$pdo->exec("DELETE FROM yfc_sales");
$pdo->exec("DELETE FROM yfc_sellers");
$pdo->exec("DELETE FROM yfc_notifications");

// Reset auto-increment
$pdo->exec("ALTER TABLE yfc_sellers AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE yfc_sales AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE yfc_items AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE yfc_buyers AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE yfc_offers AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE yfc_item_images AUTO_INCREMENT = 1");

// Create estate sale companies
$sellers = [
    [
        'company_name' => 'Premium Estate Sales',
        'contact_name' => 'Sarah Johnson',
        'email' => 'sarah@premiumestatesales.com',
        'phone' => '(509) 555-0101',
        'password' => 'password123',
        'address' => '123 Main Street',
        'city' => 'Yakima',
        'state' => 'WA',
        'zip' => '98901',
        'latitude' => 46.6021,
        'longitude' => -120.5059,
        'status' => 'active'
    ],
    [
        'company_name' => 'Valley Treasures Estate Sales',
        'contact_name' => 'Michael Chen',
        'email' => 'mike@valleytreasures.com',
        'phone' => '(509) 555-0102',
        'password' => 'password123',
        'address' => '456 Oak Avenue',
        'city' => 'Selah',
        'state' => 'WA',
        'zip' => '98942',
        'latitude' => 46.6540,
        'longitude' => -120.5301,
        'status' => 'active'
    ],
    [
        'company_name' => 'Cascade Liquidators',
        'contact_name' => 'Jennifer Martinez',
        'email' => 'jennifer@cascadeliquidators.com',
        'phone' => '(509) 555-0103',
        'password' => 'password123',
        'address' => '789 Pine Street',
        'city' => 'Union Gap',
        'state' => 'WA',
        'zip' => '98903',
        'latitude' => 46.5607,
        'longitude' => -120.4989,
        'status' => 'active'
    ]
];

$sellerIds = [];
foreach ($sellers as $seller) {
    $passwordHash = password_hash($seller['password'], PASSWORD_DEFAULT);
    unset($seller['password']);
    $seller['password_hash'] = $passwordHash;
    
    $sellerId = $sellerModel->create($seller);
    $sellerIds[] = $sellerId;
    echo "Created seller: {$seller['company_name']} (ID: $sellerId)\n";
}

// Create sales for each seller
$salesData = [
    // Active sales
    [
        'seller_id' => $sellerIds[0],
        'title' => 'Luxury Downtown Estate Sale',
        'description' => 'Complete liquidation of a beautiful downtown estate featuring high-end furniture, original artwork, designer clothing, and premium collectibles.',
        'address' => '1234 Heritage Lane',
        'city' => 'Yakima',
        'state' => 'WA',
        'zip' => '98902',
        'latitude' => 46.5950,
        'longitude' => -120.5270,
        'preview_start' => date('Y-m-d 10:00:00', strtotime('-1 day')),
        'preview_end' => date('Y-m-d 18:00:00', strtotime('-1 day')),
        'claim_start' => date('Y-m-d 08:00:00'),
        'claim_end' => date('Y-m-d 20:00:00', strtotime('+2 days')),
        'pickup_start' => date('Y-m-d 09:00:00', strtotime('+3 days')),
        'pickup_end' => date('Y-m-d 17:00:00', strtotime('+4 days')),
        'status' => 'active'
    ],
    [
        'seller_id' => $sellerIds[1],
        'title' => 'Collector\'s Paradise - Tools & Antiques',
        'description' => 'Lifetime collection of professional tools, vintage motorcycles, antique furniture, and rare collectibles.',
        'address' => '5678 Orchard Road',
        'city' => 'Selah',
        'state' => 'WA',
        'zip' => '98942',
        'latitude' => 46.6600,
        'longitude' => -120.5400,
        'claim_start' => date('Y-m-d 09:00:00', strtotime('-1 day')),
        'claim_end' => date('Y-m-d 18:00:00', strtotime('+1 day')),
        'pickup_start' => date('Y-m-d 10:00:00', strtotime('+2 days')),
        'pickup_end' => date('Y-m-d 16:00:00', strtotime('+3 days')),
        'status' => 'active'
    ],
    // Upcoming sale
    [
        'seller_id' => $sellerIds[2],
        'title' => 'Mid-Century Modern Home Sale',
        'description' => 'Stunning collection of authentic mid-century modern furniture, lighting, and decor from the 1950s-1970s.',
        'address' => '910 Vintage Drive',
        'city' => 'Union Gap',
        'state' => 'WA',
        'zip' => '98903',
        'latitude' => 46.5650,
        'longitude' => -120.4950,
        'preview_start' => date('Y-m-d 12:00:00', strtotime('+5 days')),
        'preview_end' => date('Y-m-d 18:00:00', strtotime('+5 days')),
        'claim_start' => date('Y-m-d 08:00:00', strtotime('+6 days')),
        'claim_end' => date('Y-m-d 20:00:00', strtotime('+8 days')),
        'pickup_start' => date('Y-m-d 09:00:00', strtotime('+9 days')),
        'pickup_end' => date('Y-m-d 17:00:00', strtotime('+10 days')),
        'status' => 'active'
    ],
    // Recently closed sale
    [
        'seller_id' => $sellerIds[0],
        'title' => 'Designer Fashion & Jewelry Sale',
        'description' => 'High-end designer clothing, handbags, shoes, and fine jewelry collection.',
        'address' => '2468 Fashion Boulevard',
        'city' => 'Yakima',
        'state' => 'WA',
        'zip' => '98901',
        'latitude' => 46.6100,
        'longitude' => -120.5200,
        'claim_start' => date('Y-m-d 08:00:00', strtotime('-5 days')),
        'claim_end' => date('Y-m-d 20:00:00', strtotime('-3 days')),
        'pickup_start' => date('Y-m-d 09:00:00', strtotime('-2 days')),
        'pickup_end' => date('Y-m-d 17:00:00', strtotime('-1 day')),
        'status' => 'closed'
    ]
];

$saleIds = [];
foreach ($salesData as $sale) {
    // Generate access codes
    $sale['qr_code'] = 'QR' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    $sale['access_code'] = 'AC' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $saleId = $saleModel->create($sale);
    $saleIds[] = $saleId;
    echo "Created sale: {$sale['title']} (ID: $saleId)\n";
}

// Define realistic items for each sale
$itemTemplates = [
    // Furniture
    ['title' => 'Mid-Century Modern Walnut Dresser', 'category' => 'Furniture', 'starting_price' => 300, 'condition' => 'excellent', 'dimensions' => '60"W x 18"D x 32"H', 'weight' => '150 lbs'],
    ['title' => 'Leather Reclining Sofa', 'category' => 'Furniture', 'starting_price' => 200, 'condition' => 'good', 'dimensions' => '84"W x 38"D x 36"H', 'weight' => '200 lbs'],
    ['title' => 'Antique Oak Dining Table with 6 Chairs', 'category' => 'Furniture', 'starting_price' => 500, 'condition' => 'excellent', 'dimensions' => '72"L x 42"W x 30"H', 'weight' => '250 lbs'],
    ['title' => 'Queen Size Bedroom Set (5 pieces)', 'category' => 'Furniture', 'starting_price' => 800, 'condition' => 'like-new', 'dimensions' => 'Various', 'weight' => '400 lbs total'],
    
    // Electronics
    ['title' => 'Samsung 65" 4K Smart TV', 'category' => 'Electronics', 'starting_price' => 400, 'condition' => 'excellent', 'dimensions' => '57"W x 3"D x 33"H', 'weight' => '45 lbs'],
    ['title' => 'Bose Home Theater System', 'category' => 'Electronics', 'starting_price' => 250, 'condition' => 'excellent', 'dimensions' => '20"W x 15"D x 8"H', 'weight' => '30 lbs'],
    ['title' => 'MacBook Pro 16" (2021)', 'category' => 'Electronics', 'starting_price' => 1200, 'condition' => 'like-new', 'dimensions' => '14"W x 10"D x 1"H', 'weight' => '4.5 lbs'],
    
    // Jewelry
    ['title' => '14K Gold Diamond Ring (1.2ct)', 'category' => 'Jewelry', 'starting_price' => 2000, 'condition' => 'excellent', 'dimensions' => 'Size 7', 'weight' => '4.5g'],
    ['title' => 'Tiffany & Co. Silver Necklace', 'category' => 'Jewelry', 'starting_price' => 300, 'condition' => 'excellent', 'dimensions' => '18" chain', 'weight' => '15g'],
    ['title' => 'Rolex Submariner Watch', 'category' => 'Jewelry', 'starting_price' => 8000, 'condition' => 'excellent', 'dimensions' => '40mm case', 'weight' => '155g'],
    
    // Art
    ['title' => 'Original Oil Painting - Mountain Landscape', 'category' => 'Art', 'starting_price' => 500, 'condition' => 'excellent', 'dimensions' => '36"W x 24"H', 'weight' => '15 lbs'],
    ['title' => 'Bronze Sculpture - Dancing Figure', 'category' => 'Art', 'starting_price' => 800, 'condition' => 'excellent', 'dimensions' => '12"W x 8"D x 18"H', 'weight' => '25 lbs'],
    ['title' => 'Limited Edition Print - Ansel Adams', 'category' => 'Art', 'starting_price' => 300, 'condition' => 'excellent', 'dimensions' => '20"W x 16"H', 'weight' => '5 lbs'],
    
    // Collectibles
    ['title' => 'Complete Pokemon Card Collection (1st Edition)', 'category' => 'Collectibles', 'starting_price' => 1500, 'condition' => 'excellent', 'dimensions' => '12"W x 10"D x 4"H', 'weight' => '5 lbs'],
    ['title' => 'Vintage Star Wars Action Figures (Set of 20)', 'category' => 'Collectibles', 'starting_price' => 400, 'condition' => 'good', 'dimensions' => '24"W x 12"D x 6"H', 'weight' => '3 lbs'],
    ['title' => 'Antique Coin Collection - Silver Dollars', 'category' => 'Collectibles', 'starting_price' => 1000, 'condition' => 'excellent', 'dimensions' => '8"W x 6"D x 2"H', 'weight' => '2 lbs'],
    
    // Tools
    ['title' => 'Snap-on Master Tool Set (300 pieces)', 'category' => 'Tools', 'starting_price' => 2000, 'condition' => 'excellent', 'dimensions' => '48"W x 24"D x 36"H', 'weight' => '500 lbs'],
    ['title' => 'DeWalt Cordless Power Tool Set', 'category' => 'Tools', 'starting_price' => 500, 'condition' => 'like-new', 'dimensions' => '24"W x 18"D x 12"H', 'weight' => '40 lbs'],
    ['title' => 'Craftsman Table Saw', 'category' => 'Tools', 'starting_price' => 300, 'condition' => 'good', 'dimensions' => '36"W x 30"D x 34"H', 'weight' => '250 lbs']
];

// Create items for each sale
$itemCounter = 1;
foreach ($saleIds as $index => $saleId) {
    echo "\nCreating items for sale ID: $saleId\n";
    
    // Select 10-15 random items for each sale
    $numItems = rand(10, 15);
    $selectedItems = array_rand($itemTemplates, $numItems);
    if (!is_array($selectedItems)) {
        $selectedItems = [$selectedItems];
    }
    
    foreach ($selectedItems as $sortOrder => $itemIndex) {
        $template = $itemTemplates[$itemIndex];
        
        $itemData = [
            'sale_id' => $saleId,
            'title' => $template['title'],
            'description' => "Professional estate sale offering: {$template['title']}. " . 
                           "Condition: {$template['condition']}. Dimensions: {$template['dimensions']}. " .
                           "This item has been professionally evaluated and priced competitively.",
            'starting_price' => $template['starting_price'],
            'offer_increment' => $template['starting_price'] > 1000 ? 50 : ($template['starting_price'] > 500 ? 25 : 10),
            'buy_now_price' => $template['starting_price'] * 2.5,
            'category' => $template['category'],
            'condition_rating' => $template['condition'],
            'dimensions' => $template['dimensions'],
            'weight' => $template['weight'],
            'item_number' => sprintf('%02d-%03d', $saleId, $sortOrder + 1),
            'sort_order' => $sortOrder,
            'status' => 'available'
        ];
        
        // For closed sales, mark some items as claimed
        if ($index == 3 && $sortOrder < 5) { // Last sale, first 5 items
            $itemData['status'] = 'claimed';
        }
        
        $itemId = $itemModel->create($itemData);
        echo "  Created item: {$itemData['title']} (ID: $itemId)\n";
        
        // Create 1-3 images for each item
        $numImages = rand(1, 3);
        for ($i = 0; $i < $numImages; $i++) {
            $filename = sprintf('item_%d_%d.jpg', $itemId, $i + 1);
            $originalFilename = str_replace(' ', '_', strtolower($template['title'])) . '_' . ($i + 1) . '.jpg';
            
            $imageData = [
                'item_id' => $itemId,
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'file_size' => rand(100000, 500000), // 100KB to 500KB
                'mime_type' => 'image/jpeg',
                'is_primary' => $i === 0 ? 1 : 0,
                'sort_order' => $i
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO yfc_item_images 
                (item_id, filename, original_filename, file_size, mime_type, is_primary, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $imageData['item_id'],
                $imageData['filename'],
                $imageData['original_filename'],
                $imageData['file_size'],
                $imageData['mime_type'],
                $imageData['is_primary'],
                $imageData['sort_order']
            ]);
        }
        
        $itemCounter++;
    }
}

// Create some buyers and offers for active sales
echo "\nCreating buyers and offers...\n";

$buyerNames = [
    'John Smith', 'Mary Johnson', 'Robert Davis', 'Patricia Brown', 
    'Michael Wilson', 'Linda Garcia', 'David Martinez', 'Susan Anderson'
];

// Create offers for first two active sales
for ($saleIndex = 0; $saleIndex < 2; $saleIndex++) {
    $saleId = $saleIds[$saleIndex];
    
    // Get items for this sale
    $items = $itemModel->getBySale($saleId);
    
    // Create 5-10 buyers for this sale
    $numBuyers = rand(5, 10);
    $buyerIds = [];
    
    for ($i = 0; $i < $numBuyers; $i++) {
        $buyerData = $buyerModel->createWithAuth(
            $saleId,
            $buyerNames[array_rand($buyerNames)],
            sprintf('buyer%d@example.com', $i + 1),
            'email'
        );
        $buyerIds[] = $buyerData['buyer_id'];
    }
    
    // Create offers on random items
    foreach ($items as $item) {
        // 70% chance of having offers
        if (rand(1, 10) <= 7) {
            $numOffers = rand(1, 4);
            $currentPrice = $item['starting_price'];
            
            for ($i = 0; $i < $numOffers; $i++) {
                $buyerId = $buyerIds[array_rand($buyerIds)];
                $currentPrice += $item['offer_increment'] * rand(1, 3);
                
                $offerData = [
                    'item_id' => $item['id'],
                    'buyer_id' => $buyerId,
                    'offer_amount' => $currentPrice,
                    'status' => 'active',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 Test Browser'
                ];
                
                $offerId = $offerModel->createOffer($offerData);
                echo "  Created offer of $" . number_format($currentPrice, 2) . " on {$item['title']}\n";
            }
        }
    }
}

// For the closed sale, create claimed items with winning offers
$closedSaleId = $saleIds[3];
$closedItems = $itemModel->getBySale($closedSaleId);
$closedBuyerIds = [];

// Create buyers for closed sale
for ($i = 0; $i < 5; $i++) {
    $buyerData = $buyerModel->createWithAuth(
        $closedSaleId,
        $buyerNames[array_rand($buyerNames)],
        sprintf('closedbuyer%d@example.com', $i + 1),
        'email'
    );
    $closedBuyerIds[] = $buyerData['buyer_id'];
}

// Create winning offers for claimed items
foreach ($closedItems as $item) {
    if ($item['status'] === 'claimed') {
        $buyerId = $closedBuyerIds[array_rand($closedBuyerIds)];
        $winningPrice = $item['starting_price'] + ($item['offer_increment'] * rand(5, 15));
        
        $offerData = [
            'item_id' => $item['id'],
            'buyer_id' => $buyerId,
            'offer_amount' => $winningPrice,
            'status' => 'winning',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test Browser'
        ];
        
        $offerId = $offerModel->createOffer($offerData);
        
        // Update item with winning offer
        $stmt = $pdo->prepare("UPDATE yfc_items SET winning_offer_id = ? WHERE id = ?");
        $stmt->execute([$offerId, $item['id']]);
        
        echo "  Created winning offer of $" . number_format($winningPrice, 2) . " on {$item['title']}\n";
    }
}

// Create placeholder images
echo "\nCreating placeholder images...\n";
$categories = ['Furniture', 'Electronics', 'Jewelry', 'Art', 'Collectibles', 'Tools'];
$colors = ['#8B4513', '#4169E1', '#FFD700', '#8B008B', '#228B22', '#696969'];

foreach ($categories as $index => $category) {
    $svg = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="800" height="600" viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg">
  <rect width="800" height="600" fill="' . $colors[$index] . '" opacity="0.1"/>
  <rect x="200" y="150" width="400" height="300" fill="' . $colors[$index] . '" opacity="0.3" rx="8"/>
  <text x="400" y="290" text-anchor="middle" font-family="Arial, sans-serif" font-size="48" font-weight="bold" fill="' . $colors[$index] . '">' . $category . '</text>
  <text x="400" y="340" text-anchor="middle" font-family="Arial, sans-serif" font-size="24" fill="' . $colors[$index] . '" opacity="0.7">Estate Sale Item</text>
</svg>';
    
    $filename = '/mnt/d/YFEventsCopy/modules/yfclaim/www/assets/images/' . strtolower($category) . '-placeholder.svg';
    file_put_contents($filename, $svg);
    echo "  Created placeholder for $category\n";
}

// Summary
echo "\n========== Summary ==========\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM yfc_sellers");
echo "Sellers created: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM yfc_sales");
echo "Sales created: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM yfc_items");
echo "Items created: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM yfc_item_images");
echo "Item images created: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM yfc_buyers");
echo "Buyers created: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query("SELECT COUNT(*) FROM yfc_offers");
echo "Offers created: " . $stmt->fetchColumn() . "\n";

echo "\n✅ Mock data creation complete!\n";
echo "\nYou can now:\n";
echo "1. Browse sales at: http://localhost:8000/modules/yfclaim/www/\n";
echo "2. Login as sellers with email and password 'password123'\n";
echo "3. Test the offer system with realistic data\n";