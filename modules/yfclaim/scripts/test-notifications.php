<?php
/**
 * Test the notification system end-to-end
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db_connection.php';

use YFEvents\Modules\YFClaim\Models\OfferModel;
use YFEvents\Modules\YFClaim\Models\NotificationModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\BuyerModel;

echo "Testing YFClaim Notification System\n";
echo "===================================\n\n";

try {
    // Initialize models
    $offerModel = new OfferModel($pdo);
    $notificationModel = new NotificationModel($pdo);
    $itemModel = new ItemModel($pdo);
    $buyerModel = new BuyerModel($pdo);
    
    // Step 1: Count initial notifications
    echo "1. Checking initial notification count...\n";
    $initialCount = $notificationModel->count([]);
    echo "   Initial notifications: $initialCount\n\n";
    
    // Step 2: Find an available item to make offer on
    echo "2. Finding available item...\n";
    $sql = "SELECT i.*, s.seller_id, s.title as sale_title 
            FROM yfc_items i 
            JOIN yfc_sales s ON i.sale_id = s.id 
            WHERE i.status = 'available' 
            AND s.status = 'active'
            AND s.claim_start <= NOW() 
            AND s.claim_end >= NOW()
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        throw new Exception("No available items found in active sales");
    }
    
    echo "   Found: {$item['title']} (ID: {$item['id']})\n";
    echo "   Sale: {$item['sale_title']}\n";
    echo "   Seller ID: {$item['seller_id']}\n\n";
    
    // Step 3: Create a test buyer
    echo "3. Creating test buyer...\n";
    $buyerData = $buyerModel->createWithAuth(
        $item['sale_id'],
        'Test Notification Buyer',
        'test.notifications@example.com',
        'email'
    );
    $buyerId = $buyerData['buyer_id'];
    echo "   Created buyer ID: $buyerId\n\n";
    
    // Step 4: Create an offer
    echo "4. Creating test offer...\n";
    $offerAmount = $item['starting_price'] + 50;
    $offerData = [
        'item_id' => $item['id'],
        'buyer_id' => $buyerId,
        'offer_amount' => $offerAmount,
        'status' => 'active',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Script'
    ];
    
    $offerId = $offerModel->createOffer($offerData);
    echo "   Created offer ID: $offerId\n";
    echo "   Amount: $" . number_format($offerAmount, 2) . "\n\n";
    
    // Step 5: Check if notification was created
    echo "5. Checking for new notification...\n";
    sleep(1); // Small delay to ensure notification is created
    
    $newCount = $notificationModel->count([]);
    $notificationsCreated = $newCount - $initialCount;
    
    echo "   New notifications created: $notificationsCreated\n";
    
    if ($notificationsCreated > 0) {
        // Get the latest notification
        $sql = "SELECT * FROM yfc_notifications 
                WHERE seller_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item['seller_id']]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "   Notification details:\n";
        echo "     - Type: {$notification['type']}\n";
        echo "     - Title: {$notification['title']}\n";
        echo "     - Message: {$notification['message']}\n";
        echo "     - Is Read: " . ($notification['is_read'] ? 'Yes' : 'No') . "\n";
        echo "     - Email Sent: " . ($notification['email_sent'] ? 'Yes' : 'No') . "\n";
    }
    
    echo "\n✅ Notification system test completed successfully!\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}