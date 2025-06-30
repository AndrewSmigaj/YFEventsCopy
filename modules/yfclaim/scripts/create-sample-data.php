<?php
// Create Sample Data for YFClaim Testing
require_once '../../../config/database.php';
require_once '../../../vendor/autoload.php';

use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;

$sellerModel = new SellerModel($pdo);
$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);

try {
    // Create a sample seller
    $sellerId = $sellerModel->createSeller([
        'company_name' => 'Smith Family Estate Sales',
        'contact_name' => 'John Smith',
        'email' => 'john.smith' . time() . '@smithestatesales.com',
        'phone' => '(555) 123-4567',
        'password' => 'password123',
        'address' => '123 Main Street',
        'city' => 'Yakima',
        'state' => 'WA',
        'zip' => '98901',
        'status' => 'active'
    ]);
    
    echo "Created seller with ID: $sellerId\n";
    
    // Create a sample sale
    $saleId = $saleModel->createSale([
        'seller_id' => $sellerId,
        'title' => 'Vintage Furniture & Collectibles Estate Sale',
        'description' => 'Beautiful estate sale featuring vintage furniture, antique collectibles, jewelry, and household items. Everything must go!',
        'address' => '456 Oak Avenue',
        'city' => 'Yakima',
        'state' => 'WA',
        'zip' => '98902',
        'start_date' => date('Y-m-d', strtotime('+1 day')),
        'end_date' => date('Y-m-d', strtotime('+7 days')),
        'claim_start' => date('Y-m-d H:i:s', strtotime('+2 days 8:00')),
        'claim_end' => date('Y-m-d H:i:s', strtotime('+5 days 20:00')),
        'pickup_start' => date('Y-m-d H:i:s', strtotime('+6 days 9:00')),
        'pickup_end' => date('Y-m-d H:i:s', strtotime('+7 days 17:00')),
        'status' => 'active'
    ]);
    
    echo "Created sale with ID: $saleId\n";
    
    // Create sample items
    $sampleItems = [
        [
            'title' => 'Antique Oak Dining Table',
            'description' => 'Beautiful solid oak dining table from the 1920s. Seats 8 people comfortably. Some minor wear but structurally sound.',
            'starting_price' => 150.00,
            'category_id' => 1,
            'condition_notes' => 'Minor wear but structurally sound',
            'measurements' => '72" L x 42" W x 30" H, 85 lbs'
        ],
        [
            'title' => 'Vintage Jewelry Box',
            'description' => 'Ornate wooden jewelry box with velvet interior. Perfect for storing precious items.',
            'starting_price' => 25.00,
            'category_id' => 2,
            'condition_notes' => 'Excellent condition',
            'measurements' => '8" L x 6" W x 4" H'
        ],
        [
            'title' => 'Set of China Dishes (12 place settings)',
            'description' => 'Complete set of fine china including plates, bowls, cups, and saucers. Perfect for special occasions.',
            'starting_price' => 75.00,
            'category_id' => 3,
            'condition_notes' => 'Very good condition'
        ],
        [
            'title' => 'Antique Persian Rug',
            'description' => 'Hand-woven Persian rug with intricate patterns. Some fading but still beautiful.',
            'starting_price' => 200.00,
            'category_id' => 4,
            'condition_notes' => 'Some fading but still beautiful',
            'measurements' => '8\' x 10\''
        ],
        [
            'title' => 'Vintage Record Collection (50+ albums)',
            'description' => 'Collection of classic rock and jazz vinyl records from the 60s-80s. Various conditions.',
            'starting_price' => 100.00,
            'category_id' => 5,
            'condition_notes' => 'Various conditions'
        ],
        [
            'title' => 'Antique Clock',
            'description' => 'Working grandfather clock with chimes. Needs minor adjustment but keeps good time.',
            'starting_price' => 300.00,
            'category_id' => 4,
            'condition_notes' => 'Needs minor adjustment but keeps good time',
            'measurements' => '78" H x 18" W x 12" D, 120 lbs'
        ],
        [
            'title' => 'Kitchen Appliance Bundle',
            'description' => 'Includes blender, mixer, toaster, and coffee maker. All in working condition.',
            'starting_price' => 50.00,
            'category_id' => 3,
            'condition_notes' => 'All in working condition'
        ],
        [
            'title' => 'Bookshelf with Books',
            'description' => 'Solid wood bookshelf filled with classic literature and reference books.',
            'starting_price' => 80.00,
            'category_id' => 6,
            'condition_notes' => 'Good condition',
            'measurements' => '72" H x 36" W x 12" D'
        ]
    ];
    
    foreach ($sampleItems as $itemData) {
        $itemData['sale_id'] = $saleId;
        $itemId = $itemModel->createItem($itemData);
        echo "Created item '{$itemData['title']}' with ID: $itemId\n";
    }
    
    echo "\nSample data created successfully!\n";
    echo "Access the admin at: http://137.184.245.149/modules/yfclaim/www/admin/\n";
    echo "Access the public site at: http://137.184.245.149/modules/yfclaim/www/\n";
    echo "Seller login: john@smithestatesales.com / password123\n";
    
} catch (Exception $e) {
    echo "Error creating sample data: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}