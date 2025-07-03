<?php
/**
 * Create comprehensive test data for YFClaim module
 * This script creates a realistic dataset with multiple sellers, sales, items, buyers, and offers
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db_connection.php';

use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\BuyerModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;

// Initialize models
$sellerModel = new SellerModel($pdo);
$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);
$buyerModel = new BuyerModel($pdo);
$offerModel = new OfferModel($pdo);

// Helper function to create realistic addresses in Yakima area
function getRandomYakimaAddress() {
    $streets = [
        '123 Main Street', '456 Oak Avenue', '789 Elm Drive', '321 Pine Road',
        '654 Maple Court', '987 Cedar Lane', '159 Birch Way', '753 Spruce Circle',
        '852 Willow Terrace', '951 Cherry Boulevard'
    ];
    $cities = ['Yakima', 'Union Gap', 'Selah', 'Naches', 'Tieton'];
    $zips = ['98901', '98902', '98903', '98908', '98942'];
    
    return [
        'address' => $streets[array_rand($streets)],
        'city' => $cities[array_rand($cities)],
        'zip' => $zips[array_rand($zips)]
    ];
}

try {
    echo "Creating comprehensive test data for YFClaim module...\n\n";
    
    // Create multiple sellers
    $sellers = [
        [
            'company_name' => 'Premium Estate Sales LLC',
            'contact_name' => 'Sarah Thompson',
            'email' => 'sarah@premiumestatesales.com',
            'phone' => '(509) 555-0101',
            'password' => 'test123',
            'status' => 'active'
        ],
        [
            'company_name' => 'Valley Treasures Estate Sales',
            'contact_name' => 'Mike Johnson',
            'email' => 'mike@valleytreasures.com',
            'phone' => '(509) 555-0102',
            'password' => 'test123',
            'status' => 'active'
        ],
        [
            'company_name' => 'Cascade Estate Liquidators',
            'contact_name' => 'Jennifer Davis',
            'email' => 'jennifer@cascadeliquidators.com',
            'phone' => '(509) 555-0103',
            'password' => 'test123',
            'status' => 'active'
        ]
    ];
    
    $sellerIds = [];
    foreach ($sellers as $sellerData) {
        $location = getRandomYakimaAddress();
        $sellerData = array_merge($sellerData, $location, ['state' => 'WA']);
        
        // Check if seller already exists
        $existing = $sellerModel->findByEmail($sellerData['email']);
        if ($existing) {
            $sellerIds[] = $existing['id'];
            echo "Seller '{$sellerData['company_name']}' already exists (ID: {$existing['id']})\n";
        } else {
            $sellerId = $sellerModel->createSeller($sellerData);
            $sellerIds[] = $sellerId;
            echo "Created seller '{$sellerData['company_name']}' (ID: $sellerId)\n";
        }
    }
    
    echo "\n";
    
    // Create multiple sales for each seller
    $saleTemplates = [
        [
            'title' => 'High-End Estate Sale - Antiques & Collectibles',
            'description' => 'Exceptional estate sale featuring rare antiques, fine art, vintage jewelry, and designer furniture. 50+ years of collecting!'
        ],
        [
            'title' => 'Complete Home Liquidation Sale',
            'description' => 'Everything must go! Furniture, appliances, tools, household items, and more. Downsizing after 30 years.'
        ],
        [
            'title' => 'Collector\'s Paradise Estate Sale',
            'description' => 'Amazing collection of vintage toys, comic books, sports memorabilia, and pop culture items from the 60s-90s.'
        ]
    ];
    
    $allSales = [];
    foreach ($sellerIds as $index => $sellerId) {
        // Create 2 sales per seller
        for ($i = 0; $i < 2; $i++) {
            $template = $saleTemplates[($index + $i) % count($saleTemplates)];
            $location = getRandomYakimaAddress();
            
            // Vary the timing - some current, some upcoming
            $daysOffset = ($i == 0) ? -1 : 3; // First sale started yesterday, second starts in 3 days
            
            $saleData = array_merge($template, $location, [
                'seller_id' => $sellerId,
                'state' => 'WA',
                'claim_start' => date('Y-m-d H:i:s', strtotime("+{$daysOffset} days 8:00")),
                'claim_end' => date('Y-m-d H:i:s', strtotime("+" . ($daysOffset + 5) . " days 20:00")),
                'pickup_start' => date('Y-m-d H:i:s', strtotime("+" . ($daysOffset + 6) . " days 9:00")),
                'pickup_end' => date('Y-m-d H:i:s', strtotime("+" . ($daysOffset + 7) . " days 17:00")),
                'status' => 'active'
            ]);
            
            $saleId = $saleModel->createSale($saleData);
            $allSales[] = ['id' => $saleId, 'seller_id' => $sellerId, 'title' => $saleData['title']];
            echo "Created sale '{$saleData['title']}' (ID: $saleId)\n";
        }
    }
    
    echo "\n";
    
    // Item templates organized by category
    $itemTemplates = [
        'furniture' => [
            ['title' => 'Victorian Mahogany Dining Set', 'price' => 800, 'desc' => '8-piece set including table, 6 chairs, and china cabinet'],
            ['title' => 'Mid-Century Modern Sofa', 'price' => 450, 'desc' => 'Original 1960s design, excellent condition'],
            ['title' => 'Antique Roll-Top Desk', 'price' => 600, 'desc' => 'Oak construction, fully functional, circa 1920s'],
            ['title' => 'Queen Anne Style Armchair', 'price' => 200, 'desc' => 'Upholstered in burgundy velvet'],
            ['title' => 'Vintage Cedar Chest', 'price' => 150, 'desc' => 'Lane cedar chest with original hardware']
        ],
        'antiques' => [
            ['title' => 'Grandfather Clock (1890s)', 'price' => 1200, 'desc' => 'Fully working with Westminster chimes'],
            ['title' => 'Tiffany-Style Table Lamp', 'price' => 350, 'desc' => 'Stained glass shade with bronze base'],
            ['title' => 'Victorian Tea Service Set', 'price' => 400, 'desc' => 'Silver-plated, complete 6-piece set'],
            ['title' => 'Antique Persian Rug', 'price' => 800, 'desc' => 'Hand-woven, 8x10 feet, some wear'],
            ['title' => 'Art Deco Mirror', 'price' => 250, 'desc' => 'Etched glass with geometric design']
        ],
        'collectibles' => [
            ['title' => 'Vintage Baseball Card Collection', 'price' => 500, 'desc' => '200+ cards from 1950s-1970s'],
            ['title' => 'First Edition Book Set', 'price' => 300, 'desc' => 'Classic American literature, 10 volumes'],
            ['title' => 'Coin Collection', 'price' => 400, 'desc' => 'US coins including silver dollars'],
            ['title' => 'Vintage Toy Train Set', 'price' => 250, 'desc' => 'Lionel O-gauge with tracks and accessories'],
            ['title' => 'Comic Book Collection', 'price' => 600, 'desc' => '100+ comics, Marvel and DC, 1970s-1980s']
        ],
        'jewelry' => [
            ['title' => 'Diamond Engagement Ring', 'price' => 2500, 'desc' => '1.5 carat center stone, platinum setting'],
            ['title' => 'Vintage Gold Watch', 'price' => 800, 'desc' => 'Rolex Datejust, needs servicing'],
            ['title' => 'Pearl Necklace', 'price' => 400, 'desc' => 'Cultured pearls with 14k gold clasp'],
            ['title' => 'Estate Jewelry Lot', 'price' => 300, 'desc' => 'Various pieces including rings, bracelets, earrings'],
            ['title' => 'Antique Cameo Brooch', 'price' => 150, 'desc' => 'Hand-carved shell cameo in gold setting']
        ],
        'household' => [
            ['title' => 'Kitchen Aid Stand Mixer', 'price' => 150, 'desc' => 'Professional series with attachments'],
            ['title' => 'China Dinnerware Set', 'price' => 200, 'desc' => 'Service for 12, Noritake pattern'],
            ['title' => 'Crystal Glassware Collection', 'price' => 300, 'desc' => 'Waterford crystal, various pieces'],
            ['title' => 'Vintage Kitchen Canisters', 'price' => 50, 'desc' => 'Set of 4, ceramic with lids'],
            ['title' => 'Silver Flatware Set', 'price' => 400, 'desc' => 'Sterling silver, service for 8']
        ],
        'tools' => [
            ['title' => 'Craftsman Tool Chest', 'price' => 300, 'desc' => 'Rolling chest with complete tool set'],
            ['title' => 'DeWalt Power Tool Set', 'price' => 400, 'desc' => 'Drill, saw, sander with batteries'],
            ['title' => 'Vintage Hand Tools', 'price' => 150, 'desc' => 'Collection of woodworking tools'],
            ['title' => 'Shop Vac System', 'price' => 100, 'desc' => 'Wet/dry vacuum with attachments'],
            ['title' => 'Workbench with Vise', 'price' => 200, 'desc' => 'Heavy-duty construction']
        ]
    ];
    
    // Create items for each sale
    $allItems = [];
    foreach ($allSales as $sale) {
        $itemCount = rand(15, 25); // Each sale has 15-25 items
        $usedCategories = [];
        
        for ($i = 0; $i < $itemCount; $i++) {
            // Rotate through categories
            $categories = array_keys($itemTemplates);
            $category = $categories[$i % count($categories)];
            
            // Get a random item from the category
            $template = $itemTemplates[$category][array_rand($itemTemplates[$category])];
            
            // Add some variation to the price
            $priceVariation = rand(-20, 20) / 100; // ±20% variation
            $startingPrice = round($template['price'] * (1 + $priceVariation), 2);
            
            $itemData = [
                'sale_id' => $sale['id'],
                'title' => $template['title'],
                'description' => $template['desc'],
                'starting_price' => $startingPrice,
                'category' => ucfirst($category),
                'item_number' => sprintf('%s-%03d', substr($sale['id'], -2), $i + 1),
                'status' => 'available'
            ];
            
            $itemId = $itemModel->createItem($itemData);
            $allItems[] = [
                'id' => $itemId,
                'sale_id' => $sale['id'],
                'title' => $itemData['title'],
                'starting_price' => $startingPrice
            ];
        }
        
        echo "Created $itemCount items for sale ID {$sale['id']}\n";
    }
    
    echo "\n";
    
    // Create buyers (no pre-registration needed in this system)
    $buyerNames = [
        'John Miller', 'Mary Johnson', 'Robert Smith', 'Jennifer Davis',
        'Michael Brown', 'Lisa Wilson', 'David Martinez', 'Sarah Anderson',
        'James Taylor', 'Patricia Thomas', 'William Jackson', 'Barbara White'
    ];
    
    // Create offers for current sales (those that started yesterday)
    $currentSales = array_filter($allSales, function($sale) use ($saleModel) {
        $saleData = $saleModel->find($sale['id']);
        return strtotime($saleData['claim_start']) <= time() && strtotime($saleData['claim_end']) >= time();
    });
    
    $offerCount = 0;
    foreach ($currentSales as $sale) {
        // Get items for this sale
        $saleItems = array_filter($allItems, function($item) use ($sale) {
            return $item['sale_id'] == $sale['id'];
        });
        
        // Create offers for 60-80% of items
        $itemsToOffer = array_slice($saleItems, 0, intval(count($saleItems) * rand(60, 80) / 100));
        
        foreach ($itemsToOffer as $item) {
            // Each item gets 1-4 offers
            $numOffers = rand(1, 4);
            $usedBuyers = [];
            
            for ($o = 0; $o < $numOffers; $o++) {
                // Pick a unique buyer for this item
                do {
                    $buyerName = $buyerNames[array_rand($buyerNames)];
                } while (in_array($buyerName, $usedBuyers));
                $usedBuyers[] = $buyerName;
                
                // Create buyer with email
                $buyerEmail = strtolower(str_replace(' ', '.', $buyerName)) . '@example.com';
                
                // Check if buyer already exists for this sale
                $existingBuyer = $buyerModel->findByContact($sale['id'], $buyerEmail, 'email');
                
                if (!$existingBuyer) {
                    // Create new buyer
                    $buyerAuth = $buyerModel->createWithAuth($sale['id'], $buyerName, $buyerEmail, 'email');
                    $buyerId = $buyerAuth['buyer_id'];
                    
                    // Auto-verify for test data
                    $buyerModel->update($buyerId, ['auth_verified' => 1]);
                } else {
                    $buyerId = $existingBuyer['id'];
                }
                
                // Create offer amount (starting price + increments)
                $increments = rand(0, 5); // 0-5 increments of $5-$20
                $incrementAmount = rand(5, 20);
                $offerAmount = $item['starting_price'] + ($increments * $incrementAmount);
                
                // Create the offer
                $offerData = [
                    'item_id' => $item['id'],
                    'buyer_id' => $buyerId,
                    'offer_amount' => $offerAmount,
                    'max_offer' => $offerAmount + rand(0, 100), // Some buyers set max offers
                    'status' => 'active',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Test Script'
                ];
                
                $offerId = $offerModel->createOffer($offerData);
                $offerCount++;
            }
        }
    }
    
    echo "Created $offerCount offers from " . count($buyerNames) . " potential buyers\n\n";
    
    // Mark some items as claimed (accept highest offers)
    $claimedCount = 0;
    foreach ($currentSales as $sale) {
        $saleItems = array_filter($allItems, function($item) use ($sale) {
            return $item['sale_id'] == $sale['id'];
        });
        
        // Claim 30-50% of items with offers
        foreach ($saleItems as $item) {
            if (rand(1, 100) <= 40) { // 40% chance
                $highestOffer = $offerModel->getHighest($item['id']);
                if ($highestOffer) {
                    $offerModel->acceptOffer($highestOffer['id'], 'Winner selected based on offer amount');
                    $claimedCount++;
                }
            }
        }
    }
    
    echo "Marked $claimedCount items as claimed with winning offers\n\n";
    
    // Create some notifications for sellers
    $notificationStmt = $pdo->prepare("
        INSERT INTO yfc_notifications (seller_id, sale_id, type, title, message)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($sellerIds as $sellerId) {
        // Get seller's sales
        $sellerSales = array_filter($allSales, function($sale) use ($sellerId) {
            return $sale['seller_id'] == $sellerId;
        });
        
        foreach ($sellerSales as $sale) {
            // New offer notification
            $notificationStmt->execute([
                $sellerId,
                $sale['id'],
                'new_offer',
                'New offers on your sale',
                "You have received new offers on items in '{$sale['title']}'"
            ]);
            
            // Sale ending notification (for current sales)
            $saleData = $saleModel->find($sale['id']);
            if (strtotime($saleData['claim_end']) - time() < 86400) { // Less than 24 hours
                $notificationStmt->execute([
                    $sellerId,
                    $sale['id'],
                    'sale_ending',
                    'Sale ending soon',
                    "Your sale '{$sale['title']}' ends in less than 24 hours. Review and accept offers now!"
                ]);
            }
        }
    }
    
    echo "Created notifications for sellers\n\n";
    
    // Summary
    echo "========================================\n";
    echo "Test Data Creation Complete!\n";
    echo "========================================\n";
    echo "Created:\n";
    echo "- " . count($sellerIds) . " sellers\n";
    echo "- " . count($allSales) . " sales\n";
    echo "- " . count($allItems) . " items\n";
    echo "- $offerCount offers\n";
    echo "- $claimedCount claimed items\n\n";
    
    echo "Access Points:\n";
    echo "- Public browsing: http://137.184.245.149/modules/yfclaim/www/\n";
    echo "- Seller dashboard: http://137.184.245.149/modules/yfclaim/www/dashboard/\n";
    echo "- Admin panel: http://137.184.245.149/modules/yfclaim/www/admin/\n\n";
    
    echo "Test Logins:\n";
    foreach ($sellers as $seller) {
        echo "- {$seller['company_name']}: {$seller['email']} / test123\n";
    }
    
} catch (Exception $e) {
    echo "Error creating test data: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}