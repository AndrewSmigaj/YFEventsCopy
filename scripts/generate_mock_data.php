<?php
/**
 * Mock Data Generator for YFEvents
 * Generates realistic test data including images
 */

// Define helper function if not already defined
if (!function_exists('info')) {
    function info($message) {
        echo "[INFO] $message\n";
    }
}

function generateMockData($pdo) {
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // 1. Create users
        info("Creating users...");
        createUsers($pdo);
        
        // 2. Create event categories
        info("Creating event categories...");
        createEventCategories($pdo);
        
        // 3. Create shop categories  
        info("Creating shop categories...");
        createShopCategories($pdo);
        
        // 4. Create events
        info("Creating events...");
        createEvents($pdo);
        
        // 5. Create shops
        info("Creating shops...");
        createShops($pdo);
        
        // 6. Create estate sale companies (sellers)
        info("Creating estate sale companies...");
        createSellers($pdo);
        
        // 7. Create estate sales
        info("Creating estate sales...");
        createSales($pdo);
        
        // 8. Create items with images
        info("Creating items with images...");
        createItems($pdo);
        
        // Commit transaction
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function createUsers($pdo) {
    // Create admin user
    $pdo->exec("
        INSERT INTO yfa_auth_users (username, email, password_hash, status, created_at)
        VALUES ('admin', 'admin@yakimafinds.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'active', NOW())
    ");
    $adminId = $pdo->lastInsertId();
    
    // Create admin role
    $pdo->exec("
        INSERT INTO yfa_auth_roles (name, display_name, description) 
        VALUES ('admin', 'Administrator', 'Full system access')
    ");
    $adminRoleId = $pdo->lastInsertId();
    
    // Assign role
    $pdo->exec("
        INSERT INTO yfa_auth_user_roles (user_id, role_id)
        VALUES ($adminId, $adminRoleId)
    ");
    
    // Create seller users
    $sellers = [
        ['username' => 'estate_pros', 'email' => 'contact@estatepros.com', 'company' => 'Estate Sale Professionals'],
        ['username' => 'valley_estates', 'email' => 'info@valleyestates.com', 'company' => 'Valley Estate Services'],
        ['username' => 'heritage_sales', 'email' => 'sales@heritagesales.com', 'company' => 'Heritage Estate Sales'],
        ['username' => 'yakima_liquidators', 'email' => 'info@yakimaliquidators.com', 'company' => 'Yakima Liquidators'],
        ['username' => 'treasure_hunters', 'email' => 'find@treasurehunters.com', 'company' => 'Treasure Hunters Estate Sales']
    ];
    
    // Create seller role
    $pdo->exec("
        INSERT INTO yfa_auth_roles (name, display_name, description) 
        VALUES ('seller', 'Estate Sale Seller', 'Can manage estate sales')
    ");
    $sellerRoleId = $pdo->lastInsertId();
    
    foreach ($sellers as $seller) {
        $pdo->exec("
            INSERT INTO yfa_auth_users (username, email, password_hash, status, created_at)
            VALUES ('{$seller['username']}', '{$seller['email']}', '" . password_hash('seller123', PASSWORD_DEFAULT) . "', 'active', NOW())
        ");
        $userId = $pdo->lastInsertId();
        
        // Assign seller role
        $pdo->exec("
            INSERT INTO yfa_auth_user_roles (user_id, role_id)
            VALUES ($userId, $sellerRoleId)
        ");
    }
}

function createEventCategories($pdo) {
    $categories = [
        ['name' => 'Farmers Markets', 'slug' => 'farmers-markets', 'color' => '#2ecc71', 'icon' => 'fa-carrot'],
        ['name' => 'Music & Concerts', 'slug' => 'music-concerts', 'color' => '#e74c3c', 'icon' => 'fa-music'],
        ['name' => 'Art & Culture', 'slug' => 'art-culture', 'color' => '#9b59b6', 'icon' => 'fa-palette'],
        ['name' => 'Food & Drink', 'slug' => 'food-drink', 'color' => '#f39c12', 'icon' => 'fa-utensils'],
        ['name' => 'Sports & Recreation', 'slug' => 'sports-recreation', 'color' => '#3498db', 'icon' => 'fa-running'],
        ['name' => 'Community', 'slug' => 'community', 'color' => '#1abc9c', 'icon' => 'fa-users']
    ];
    
    foreach ($categories as $cat) {
        $pdo->exec("
            INSERT INTO event_categories (name, slug, color, icon, active)
            VALUES ('{$cat['name']}', '{$cat['slug']}', '{$cat['color']}', '{$cat['icon']}', 1)
        ");
    }
}

function createShopCategories($pdo) {
    $categories = [
        ['name' => 'Restaurants', 'slug' => 'restaurants', 'icon' => 'fa-utensils'],
        ['name' => 'Coffee & Tea', 'slug' => 'coffee-tea', 'icon' => 'fa-coffee'],
        ['name' => 'Retail', 'slug' => 'retail', 'icon' => 'fa-shopping-bag'],
        ['name' => 'Services', 'slug' => 'services', 'icon' => 'fa-concierge-bell'],
        ['name' => 'Health & Beauty', 'slug' => 'health-beauty', 'icon' => 'fa-heart'],
        ['name' => 'Automotive', 'slug' => 'automotive', 'icon' => 'fa-car'],
        ['name' => 'Home & Garden', 'slug' => 'home-garden', 'icon' => 'fa-home']
    ];
    
    foreach ($categories as $cat) {
        $pdo->exec("
            INSERT INTO shop_categories (name, slug, icon, sort_order, active)
            VALUES ('{$cat['name']}', '{$cat['slug']}', '{$cat['icon']}', 0, 1)
        ");
    }
}

function createEvents($pdo) {
    $events = [
        // Upcoming events
        ['title' => 'Downtown Farmers Market', 'days' => 3, 'location' => 'Downtown Yakima', 'address' => '3rd Ave & Yakima Ave, Yakima, WA', 'category' => 'farmers-markets'],
        ['title' => 'Jazz in the Valley', 'days' => 7, 'location' => 'Franklin Park', 'address' => '1201 Tieton Dr, Yakima, WA', 'category' => 'music-concerts'],
        ['title' => 'Art Walk First Friday', 'days' => 10, 'location' => 'Downtown Arts District', 'address' => 'N 1st St, Yakima, WA', 'category' => 'art-culture'],
        ['title' => 'Wine Tasting Weekend', 'days' => 14, 'location' => 'Yakima Valley', 'address' => 'Various Locations, Yakima Valley, WA', 'category' => 'food-drink'],
        ['title' => 'Community Garage Sale', 'days' => 5, 'location' => 'West Valley', 'address' => 'Wide Hollow Rd, Yakima, WA', 'category' => 'community'],
        ['title' => '5K Fun Run', 'days' => 21, 'location' => 'Yakima Greenway', 'address' => 'Sherman Ave, Yakima, WA', 'category' => 'sports-recreation'],
        ['title' => 'Food Truck Festival', 'days' => 8, 'location' => 'State Fair Park', 'address' => '1301 S Fair Ave, Yakima, WA', 'category' => 'food-drink'],
        ['title' => 'Local Artist Showcase', 'days' => 12, 'location' => 'Larson Gallery', 'address' => '5000 W Lincoln Ave, Yakima, WA', 'category' => 'art-culture'],
        ['title' => 'Summer Concert Series', 'days' => 15, 'location' => 'Gilbert Cellars', 'address' => '5 N Front St, Yakima, WA', 'category' => 'music-concerts'],
        ['title' => 'Antique & Collectibles Fair', 'days' => 6, 'location' => 'Central Plaza', 'address' => '214 E Yakima Ave, Yakima, WA', 'category' => 'community'],
        
        // Past events (for variety)
        ['title' => 'Spring Festival', 'days' => -7, 'location' => 'Miller Park', 'address' => 'S 4th Ave, Yakima, WA', 'category' => 'community'],
        ['title' => 'Blues & BBQ', 'days' => -14, 'location' => 'Depot Restaurant', 'address' => '32 N Front St, Yakima, WA', 'category' => 'music-concerts']
    ];
    
    // Get category IDs
    $catStmt = $pdo->prepare("SELECT slug, id FROM event_categories");
    $catStmt->execute();
    $categories = $catStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($events as $event) {
        $startDate = date('Y-m-d H:i:s', strtotime("+{$event['days']} days"));
        $endDate = date('Y-m-d H:i:s', strtotime("+{$event['days']} days +3 hours"));
        
        $description = generateEventDescription($event['title']);
        $categoryId = $categories[$event['category']] ?? null;
        
        // Add some randomization to coordinates near Yakima
        $lat = 46.6021 + (mt_rand(-50, 50) / 1000);
        $lng = -120.5059 + (mt_rand(-50, 50) / 1000);
        
        $pdo->exec("
            INSERT INTO events (
                title, description, start_datetime, end_datetime,
                location, address, latitude, longitude,
                status, featured, created_at
            ) VALUES (
                '{$event['title']}', '$description', '$startDate', '$endDate',
                '{$event['location']}', '{$event['address']}', $lat, $lng,
                'approved', " . (mt_rand(0, 3) == 0 ? '1' : '0') . ", NOW()
            )
        ");
    }
}

function createShops($pdo) {
    $shops = [
        // Restaurants
        ['name' => 'Valley Cafe', 'category' => 'restaurants', 'address' => '105 W Yakima Ave, Yakima, WA 98902'],
        ['name' => 'El Porton', 'category' => 'restaurants', 'address' => '180 N Fair Ave, Yakima, WA 98901'],
        ['name' => 'Cowiche Canyon Kitchen', 'category' => 'restaurants', 'address' => '202 E Yakima Ave, Yakima, WA 98901'],
        
        // Coffee
        ['name' => 'Northtown Coffee', 'category' => 'coffee-tea', 'address' => '1725 Summitview Ave, Yakima, WA 98902'],
        ['name' => 'Brewed Awakening', 'category' => 'coffee-tea', 'address' => '5614 Summitview Ave, Yakima, WA 98908'],
        
        // Retail
        ['name' => 'Inklings Bookshop', 'category' => 'retail', 'address' => '5 N 3rd St, Yakima, WA 98901'],
        ['name' => 'Valley Vintage', 'category' => 'retail', 'address' => '21 S 2nd Ave, Yakima, WA 98902'],
        ['name' => 'The Seasons Performance Hall', 'category' => 'retail', 'address' => '101 N Naches Ave, Yakima, WA 98901'],
        
        // Services
        ['name' => 'Yakima Valley Visitors Center', 'category' => 'services', 'address' => '101 N Fair Ave, Yakima, WA 98901'],
        ['name' => 'Print Plus', 'category' => 'services', 'address' => '3101 W Nob Hill Blvd, Yakima, WA 98902'],
        
        // Health & Beauty
        ['name' => 'Harmony Spa', 'category' => 'health-beauty', 'address' => '203 E Chestnut Ave, Yakima, WA 98901'],
        ['name' => 'Valley Fitness', 'category' => 'health-beauty', 'address' => '1211 S 7th St, Yakima, WA 98901'],
        
        // Automotive
        ['name' => 'Valley Auto Care', 'category' => 'automotive', 'address' => '802 S 1st St, Yakima, WA 98901'],
        
        // Home & Garden
        ['name' => 'Yakima Garden Center', 'category' => 'home-garden', 'address' => '1606 W Lincoln Ave, Yakima, WA 98902'],
        ['name' => 'Valley Home Store', 'category' => 'home-garden', 'address' => '5607 W Nob Hill Blvd, Yakima, WA 98908']
    ];
    
    // Get category IDs
    $catStmt = $pdo->prepare("SELECT slug, id FROM shop_categories");
    $catStmt->execute();
    $categories = $catStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($shops as $shop) {
        $categoryId = $categories[$shop['category']] ?? null;
        $description = generateShopDescription($shop['name']);
        
        // Add coordinates near address
        $lat = 46.6021 + (mt_rand(-30, 30) / 1000);
        $lng = -120.5059 + (mt_rand(-30, 30) / 1000);
        
        // Generate hours
        $hours = json_encode([
            'monday' => '9:00 AM - 6:00 PM',
            'tuesday' => '9:00 AM - 6:00 PM',
            'wednesday' => '9:00 AM - 6:00 PM',
            'thursday' => '9:00 AM - 8:00 PM',
            'friday' => '9:00 AM - 8:00 PM',
            'saturday' => '10:00 AM - 5:00 PM',
            'sunday' => 'Closed'
        ]);
        
        $pdo->exec("
            INSERT INTO local_shops (
                name, description, address, latitude, longitude,
                phone, email, website, category_id, operating_hours,
                featured, verified, status, created_at
            ) VALUES (
                '{$shop['name']}', '$description', '{$shop['address']}', $lat, $lng,
                '509-" . mt_rand(100, 999) . "-" . mt_rand(1000, 9999) . "',
                '" . strtolower(str_replace(' ', '', $shop['name'])) . "@example.com',
                'https://example.com/" . strtolower(str_replace(' ', '-', $shop['name'])) . "',
                $categoryId, '$hours',
                " . (mt_rand(0, 4) == 0 ? '1' : '0') . ", 1, 'active', NOW()
            )
        ");
    }
}

function createSellers($pdo) {
    $sellers = [
        ['user' => 'estate_pros', 'company' => 'Estate Sale Professionals', 'phone' => '509-555-0101'],
        ['user' => 'valley_estates', 'company' => 'Valley Estate Services', 'phone' => '509-555-0102'],
        ['user' => 'heritage_sales', 'company' => 'Heritage Estate Sales', 'phone' => '509-555-0103'],
        ['user' => 'yakima_liquidators', 'company' => 'Yakima Liquidators', 'phone' => '509-555-0104'],
        ['user' => 'treasure_hunters', 'company' => 'Treasure Hunters Estate Sales', 'phone' => '509-555-0105']
    ];
    
    foreach ($sellers as $seller) {
        // Get user ID
        $stmt = $pdo->prepare("SELECT id, email FROM yfa_auth_users WHERE username = ?");
        $stmt->execute([$seller['user']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $description = "Professional estate sale company serving the Yakima Valley. " .
                          "We specialize in complete estate liquidation, appraisals, and clean-out services.";
            
            $pdo->exec("
                INSERT INTO yfc_sellers (
                    company_name, contact_name, email, phone,
                    password_hash, address, city, state, zip, 
                    status, created_at
                ) VALUES (
                    '{$seller['company']}', '{$seller['user']}', '{$user['email']}', '{$seller['phone']}',
                    '" . password_hash('seller123', PASSWORD_DEFAULT) . "',
                    '" . mt_rand(100, 999) . " Main St', 'Yakima', 'WA', '98902',
                    'active', NOW()
                )
            ");
        }
    }
}

function createSales($pdo) {
    // Get seller IDs
    $stmt = $pdo->query("SELECT id, company_name FROM yfc_sellers");
    $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $saleTypes = [
        'Complete Estate Liquidation',
        'Moving Sale', 
        'Downsizing Sale',
        'Collector Estate Sale',
        'Antique & Vintage Sale'
    ];
    
    $neighborhoods = [
        'West Valley', 'Downtown', 'Terrace Heights', 
        'Nob Hill', 'Fruitvale', 'Union Gap'
    ];
    
    foreach ($sellers as $seller) {
        // Create 2-3 sales per seller
        $numSales = mt_rand(2, 3);
        
        for ($i = 0; $i < $numSales; $i++) {
            $daysOffset = mt_rand(-5, 20);
            $startDate = date('Y-m-d', strtotime("+$daysOffset days"));
            $endDate = date('Y-m-d', strtotime("+$daysOffset days +2 days"));
            $previewDate = date('Y-m-d', strtotime("+$daysOffset days -1 day"));
            
            $title = $saleTypes[array_rand($saleTypes)] . ' - ' . $neighborhoods[array_rand($neighborhoods)];
            $description = generateSaleDescription($title);
            
            $address = mt_rand(100, 9999) . ' ' . ['Oak', 'Maple', 'Cedar', 'Pine', 'Elm'][array_rand(['Oak', 'Maple', 'Cedar', 'Pine', 'Elm'])] . ' St';
            
            $status = $daysOffset < 0 ? 'closed' : ($daysOffset < 7 ? 'active' : 'draft');
            
            $pdo->exec("
                INSERT INTO yfc_sales (
                    seller_id, title, description, address, city, state, zip,
                    preview_start, preview_end, claim_start, claim_end,
                    status, created_at
                ) VALUES (
                    {$seller['id']}, '$title', '$description', '$address', 'Yakima', 'WA', '" . (98900 + mt_rand(1, 9)) . "',
                    '$previewDate 10:00:00', '$previewDate 16:00:00', 
                    '$startDate 09:00:00', '$endDate 17:00:00',
                    '$status', NOW()
                )
            ");
        }
    }
}

function createItems($pdo) {
    // Get all sales
    $stmt = $pdo->query("SELECT id FROM yfc_sales WHERE status IN ('active', 'upcoming')");
    $sales = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Define item templates by category
    $itemsByCategory = [
        'Furniture' => [
            ['name' => 'Victorian Dining Table', 'price' => 850],
            ['name' => 'Antique Oak Dresser', 'price' => 450],
            ['name' => 'Leather Recliner', 'price' => 325],
            ['name' => 'Queen Size Bed Frame', 'price' => 275],
            ['name' => 'Mahogany Bookshelf', 'price' => 225],
            ['name' => 'Vintage Secretary Desk', 'price' => 550]
        ],
        'Art' => [
            ['name' => 'Oil Painting - Landscape', 'price' => 425],
            ['name' => 'Bronze Sculpture', 'price' => 750],
            ['name' => 'Watercolor Collection', 'price' => 195],
            ['name' => 'Framed Print Set', 'price' => 125]
        ],
        'Collectibles' => [
            ['name' => 'Vintage Coin Collection', 'price' => 950],
            ['name' => 'Baseball Card Set', 'price' => 325],
            ['name' => 'Antique China Set', 'price' => 275],
            ['name' => 'Crystal Glassware', 'price' => 195],
            ['name' => 'Stamp Collection', 'price' => 425]
        ],
        'Jewelry' => [
            ['name' => 'Diamond Ring', 'price' => 2850],
            ['name' => 'Gold Necklace', 'price' => 875],
            ['name' => 'Vintage Watch', 'price' => 625],
            ['name' => 'Pearl Earrings', 'price' => 325]
        ],
        'Electronics' => [
            ['name' => '55" Smart TV', 'price' => 425],
            ['name' => 'Stereo System', 'price' => 275],
            ['name' => 'Laptop Computer', 'price' => 525],
            ['name' => 'Digital Camera', 'price' => 325]
        ],
        'Tools' => [
            ['name' => 'Craftsman Tool Set', 'price' => 425],
            ['name' => 'Power Drill Collection', 'price' => 275],
            ['name' => 'Table Saw', 'price' => 625],
            ['name' => 'Air Compressor', 'price' => 325]
        ],
        'Books' => [
            ['name' => 'First Edition Collection', 'price' => 750],
            ['name' => 'Vintage Encyclopedia Set', 'price' => 125],
            ['name' => 'Leather Bound Classics', 'price' => 275]
        ],
        'Appliances' => [
            ['name' => 'KitchenAid Mixer', 'price' => 225],
            ['name' => 'Espresso Machine', 'price' => 425],
            ['name' => 'Vintage Refrigerator', 'price' => 625],
            ['name' => 'Washer/Dryer Set', 'price' => 750]
        ],
        'Other' => [
            ['name' => 'Garden Tools Collection', 'price' => 175],
            ['name' => 'Camping Equipment', 'price' => 325],
            ['name' => 'Exercise Equipment', 'price' => 425]
        ]
    ];
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../public/uploads/yfclaim/items';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $itemCount = 0;
    foreach ($sales as $saleId) {
        // Add 5-15 items per sale
        $numItems = mt_rand(5, 15);
        
        for ($i = 0; $i < $numItems; $i++) {
            // Pick random category
            $category = array_rand($itemsByCategory);
            $categoryItems = $itemsByCategory[$category];
            $itemTemplate = $categoryItems[array_rand($categoryItems)];
            
            // Vary the price slightly
            $price = $itemTemplate['price'] * (mt_rand(70, 130) / 100);
            $conditions = ['excellent', 'good', 'fair', 'poor'];
            $condition = $conditions[array_rand($conditions)];
            
            $title = $itemTemplate['name'] . ($i > 0 ? " #" . ($i + 1) : "");
            $description = generateItemDescription($title, $category, $condition);
            
            // Generate placeholder image
            $imageFilename = generateItemImage($uploadDir, $category, $title);
            
            $pdo->exec("
                INSERT INTO yfc_items (
                    sale_id, title, description, price, condition_rating,
                    category, status, created_at
                ) VALUES (
                    $saleId, '$title', '$description', $price, '$condition',
                    '$category', 'available', NOW()
                )
            ");
            $itemId = $pdo->lastInsertId();
            
            // Add image to item_images table
            if ($imageFilename) {
                $pdo->exec("
                    INSERT INTO yfc_item_images (
                        item_id, filename, is_primary, sort_order
                    ) VALUES (
                        $itemId, '$imageFilename', 1, 0
                    )
                ");
            }
            
            $itemCount++;
        }
    }
    
    info("Created $itemCount items with images");
}

// Helper functions
function generateEventDescription($title) {
    $templates = [
        "Join us for %s! A wonderful community event bringing together locals and visitors alike.",
        "Don't miss %s - a fantastic opportunity to experience the best of Yakima Valley.",
        "%s returns with even more excitement this year. Fun for the whole family!",
        "Experience %s in the heart of Yakima. Great food, entertainment, and community spirit."
    ];
    
    return addslashes(sprintf($templates[array_rand($templates)], $title));
}

function generateShopDescription($name) {
    $templates = [
        "Welcome to %s, proudly serving the Yakima Valley community with quality products and exceptional service.",
        "%s has been a local favorite for years, offering the best selection and prices in town.",
        "Visit %s for a unique shopping experience. Locally owned and operated since opening.",
        "At %s, we are committed to providing excellent customer service and quality goods."
    ];
    
    return addslashes(sprintf($templates[array_rand($templates)], $name));
}

function generateSaleDescription($title) {
    $features = [
        "Quality furniture, antiques, collectibles, and household items.",
        "Entire contents of home must go! Everything priced to sell.",
        "Beautiful estate with vintage and modern items throughout.",
        "Collectors paradise! Rare finds and unique treasures.",
        "Well-maintained household with quality furnishings and decor."
    ];
    
    return addslashes($features[array_rand($features)] . " Numbers given out one hour before sale start. " .
           "Cash and credit cards accepted. All sales final.");
}

function generateItemDescription($title, $category, $condition) {
    $descriptions = [
        'Furniture' => "Beautiful piece in $condition condition. Well-maintained and ready for your home.",
        'Art' => "Stunning artwork in $condition condition. A wonderful addition to any collection.",
        'Collectibles' => "Rare find in $condition condition. Perfect for collectors and enthusiasts.",
        'Jewelry' => "Exquisite piece in $condition condition. Professionally cleaned and inspected.",
        'Electronics' => "Fully functional in $condition condition. Tested and working perfectly.",
        'Tools' => "Professional quality in $condition condition. Ready for your next project.",
        'Books' => "Well-preserved in $condition condition. A treasure for book lovers.",
        'Appliances' => "Working appliance in $condition condition. Clean and ready to use.",
        'Other' => "Unique item in $condition condition. Don't miss this opportunity!"
    ];
    
    $desc = $descriptions[$category] ?? $descriptions['Other'];
    return addslashes($desc . " " . $title . " - must see to appreciate!");
}

function generateItemImage($uploadDir, $category, $title) {
    // Create a simple placeholder image using GD
    $width = 800;
    $height = 600;
    
    // Create image
    $image = imagecreatetruecolor($width, $height);
    
    // Category colors
    $colors = [
        'Furniture' => ['bg' => [139, 69, 19], 'text' => [255, 255, 255]],      // Brown
        'Art' => ['bg' => [147, 112, 219], 'text' => [255, 255, 255]],         // Purple
        'Collectibles' => ['bg' => [255, 215, 0], 'text' => [0, 0, 0]],        // Gold
        'Jewelry' => ['bg' => [192, 192, 192], 'text' => [0, 0, 0]],           // Silver
        'Electronics' => ['bg' => [70, 130, 180], 'text' => [255, 255, 255]],   // Blue
        'Tools' => ['bg' => [105, 105, 105], 'text' => [255, 255, 255]],       // Gray
        'Books' => ['bg' => [139, 90, 43], 'text' => [255, 255, 255]],         // Brown
        'Appliances' => ['bg' => [220, 220, 220], 'text' => [0, 0, 0]],        // Light gray
        'Other' => ['bg' => [128, 128, 128], 'text' => [255, 255, 255]]        // Gray
    ];
    
    $color = $colors[$category] ?? $colors['Other'];
    
    // Set background color
    $bgColor = imagecolorallocate($image, $color['bg'][0], $color['bg'][1], $color['bg'][2]);
    imagefill($image, 0, 0, $bgColor);
    
    // Add some texture - diagonal lines
    $lineColor = imagecolorallocatealpha($image, 255, 255, 255, 100);
    for ($i = -$height; $i < $width; $i += 40) {
        imageline($image, $i, 0, $i + $height, $height, $lineColor);
    }
    
    // Add text
    $textColor = imagecolorallocate($image, $color['text'][0], $color['text'][1], $color['text'][2]);
    
    // Category name (large)
    $fontSize = 5;
    $categoryWidth = imagefontwidth($fontSize) * strlen($category);
    imagestring($image, $fontSize, ($width - $categoryWidth) / 2, $height / 2 - 40, $category, $textColor);
    
    // Item title (smaller, may be truncated)
    $fontSize = 3;
    $maxTitleLength = 30;
    $displayTitle = strlen($title) > $maxTitleLength ? substr($title, 0, $maxTitleLength) . '...' : $title;
    $titleWidth = imagefontwidth($fontSize) * strlen($displayTitle);
    imagestring($image, $fontSize, ($width - $titleWidth) / 2, $height / 2 + 20, $displayTitle, $textColor);
    
    // Add border
    $borderColor = imagecolorallocate($image, 0, 0, 0);
    imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);
    
    // Generate unique filename
    $filename = 'item_' . uniqid() . '_' . time() . '.jpg';
    $filepath = $uploadDir . '/' . $filename;
    
    // Save image
    imagejpeg($image, $filepath, 85);
    imagedestroy($image);
    
    return $filename;
}