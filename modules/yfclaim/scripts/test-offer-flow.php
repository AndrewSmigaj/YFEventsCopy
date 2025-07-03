<?php
/**
 * Test the public offer submission flow
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db_connection.php';

use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;
use YFEvents\Modules\YFClaim\Models\BuyerModel;

// Initialize models
$itemModel = new ItemModel($pdo);
$saleModel = new SaleModel($pdo);
$offerModel = new OfferModel($pdo);
$buyerModel = new BuyerModel($pdo);

echo "Testing YFClaim Offer Submission Flow\n";
echo "=====================================\n\n";

// Test parameters
$saleId = 13;
$itemId = 1;
$buyerName = "Test Buyer";
$buyerEmail = "testbuyer@example.com";

try {
    // Step 1: Verify sale is active
    echo "1. Checking if sale is active...\n";
    $sale = $saleModel->find($saleId);
    $isActive = $saleModel->isActive($saleId);
    
    if (!$isActive) {
        throw new Exception("Sale {$saleId} is not active");
    }
    echo "   ✓ Sale '{$sale['title']}' is active\n\n";
    
    // Step 2: Verify item is available
    echo "2. Checking if item is available...\n";
    $item = $itemModel->find($itemId);
    
    if (!$item || $item['status'] !== 'available') {
        throw new Exception("Item {$itemId} is not available");
    }
    echo "   ✓ Item '{$item['title']}' is available (Starting price: $" . number_format($item['starting_price'], 2) . ")\n\n";
    
    // Step 3: Create/authenticate buyer
    echo "3. Creating/authenticating buyer...\n";
    $existingBuyer = $buyerModel->findByContact($saleId, $buyerEmail, 'email');
    
    if ($existingBuyer) {
        echo "   - Buyer already exists\n";
        $buyerId = $existingBuyer['id'];
        
        // Generate new auth code
        $authInfo = $buyerModel->resendAuthCode($buyerId);
    } else {
        echo "   - Creating new buyer\n";
        $authInfo = $buyerModel->createWithAuth($saleId, $buyerName, $buyerEmail, 'email');
        $buyerId = $authInfo['buyer_id'];
    }
    
    echo "   - Auth code: {$authInfo['auth_code']}\n";
    
    // Step 4: Verify auth code
    echo "4. Verifying authentication code...\n";
    $verifyResult = $buyerModel->verifyAuthCode($buyerId, $authInfo['auth_code']);
    
    if (!$verifyResult) {
        throw new Exception("Failed to verify auth code");
    }
    echo "   ✓ Authentication successful\n";
    echo "   - Session token: " . substr($verifyResult['session_token'], 0, 16) . "...\n\n";
    
    // Step 5: Check for existing offers
    echo "5. Checking for existing offers...\n";
    $existingOffer = $offerModel->getBuyerOffer($itemId, $buyerId);
    
    if ($existingOffer) {
        echo "   - Buyer has existing offer: $" . number_format($existingOffer['offer_amount'], 2) . "\n";
    } else {
        echo "   - No existing offer from this buyer\n";
    }
    echo "\n";
    
    // Step 6: Submit an offer
    echo "6. Submitting offer...\n";
    $offerAmount = $item['starting_price'] + 25.00; // Starting price + $25
    
    if ($existingOffer && $offerAmount <= $existingOffer['offer_amount']) {
        $offerAmount = $existingOffer['offer_amount'] + 10.00; // Increase by $10
        echo "   - Increasing offer to: $" . number_format($offerAmount, 2) . "\n";
    } else {
        echo "   - Offer amount: $" . number_format($offerAmount, 2) . "\n";
    }
    
    if ($existingOffer) {
        // Update existing offer
        $offerModel->updateAmount($existingOffer['id'], $offerAmount);
        echo "   ✓ Offer updated successfully\n";
    } else {
        // Create new offer
        $offerData = [
            'item_id' => $itemId,
            'buyer_id' => $buyerId,
            'offer_amount' => $offerAmount,
            'max_offer' => $offerAmount + 50.00, // Allow auto-bidding up to +$50
            'status' => 'active',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Script'
        ];
        
        $offerId = $offerModel->createOffer($offerData);
        echo "   ✓ Offer created successfully (ID: {$offerId})\n";
    }
    echo "\n";
    
    // Step 7: Verify offer statistics
    echo "7. Checking offer statistics...\n";
    $itemStats = $offerModel->getItemStats($itemId);
    echo "   - Total offers: {$itemStats['total_offers']}\n";
    echo "   - Unique buyers: {$itemStats['unique_buyers']}\n";
    echo "   - Offer range: $" . number_format($itemStats['min_offer'], 2) . " - $" . number_format($itemStats['max_offer'], 2) . "\n";
    echo "   - Average offer: $" . number_format($itemStats['avg_offer'], 2) . "\n\n";
    
    echo "✅ Offer submission flow test completed successfully!\n\n";
    
    // Display URLs for manual testing
    echo "URLs for manual testing:\n";
    echo "- Browse sales: http://137.184.245.149/modules/yfclaim/www/\n";
    echo "- View this sale: http://137.184.245.149/modules/yfclaim/www/sale.php?id={$saleId}\n";
    echo "- View this item: http://137.184.245.149/modules/yfclaim/www/item.php?id={$itemId}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}