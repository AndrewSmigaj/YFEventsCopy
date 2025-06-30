<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing All Required Methods</h1>";

// Auth bypass
if (!isset($_GET['admin_bypass']) || $_GET['admin_bypass'] !== 'YakFind2025') {
    echo "Add ?admin_bypass=YakFind2025 to URL";
    exit;
}

require_once dirname(__DIR__, 4) . '/config/database.php';
require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\BuyerModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;

try {
    $sellerModel = new SellerModel($pdo);
    $saleModel = new SaleModel($pdo);
    $itemModel = new ItemModel($pdo);
    $buyerModel = new BuyerModel($pdo);
    $offerModel = new OfferModel($pdo);
    
    echo "<h2>Testing SellerModel Methods</h2>";
    
    // Test getAllSellers
    echo "getAllSellers: ";
    $sellers = $sellerModel->getAllSellers(1, 0);
    echo "✓ Works (" . count($sellers) . " results)<br>";
    
    if (!empty($sellers)) {
        $sellerId = $sellers[0]['id'];
        
        // Test getStats
        echo "getStats($sellerId): ";
        $stats = $sellerModel->getStats($sellerId);
        echo "✓ Works<br>";
        
        // Test getSellerById
        echo "getSellerById($sellerId): ";
        $seller = $sellerModel->getSellerById($sellerId);
        echo "✓ Works<br>";
    }
    
    echo "<h2>Testing SaleModel Methods</h2>";
    
    // Test getAllSales
    echo "getAllSales: ";
    $sales = $saleModel->getAllSales(1, 0);
    echo "✓ Works (" . count($sales) . " results)<br>";
    
    if (!empty($sales)) {
        $saleId = $sales[0]['id'];
        
        // Test getSaleById
        echo "getSaleById($saleId): ";
        $sale = $saleModel->getSaleById($saleId);
        echo "✓ Works<br>";
        
        // Test getStats
        echo "getStats($saleId): ";
        $stats = $saleModel->getStats($saleId);
        echo "✓ Works<br>";
    }
    
    echo "<h2>Testing ItemModel Methods</h2>";
    
    if (!empty($sales)) {
        $saleId = $sales[0]['id'];
        
        // Test getItemsBySale
        echo "getItemsBySale($saleId): ";
        $items = $itemModel->getItemsBySale($saleId);
        echo "✓ Works (" . count($items) . " results)<br>";
        
        if (!empty($items)) {
            $itemId = $items[0]['id'];
            
            // Test getOffers
            echo "getOffers($itemId): ";
            $offers = $itemModel->getOffers($itemId);
            echo "✓ Works<br>";
            
            // Test getHighestOffer
            echo "getHighestOffer($itemId): ";
            $highestOffer = $itemModel->getHighestOffer($itemId);
            echo "✓ Works<br>";
            
            // Test getPriceRange
            echo "getPriceRange($itemId): ";
            $priceRange = $itemModel->getPriceRange($itemId);
            echo "✓ Works<br>";
        }
    }
    
    echo "<h2>Testing BuyerModel Methods</h2>";
    
    // Test count
    echo "count(): ";
    $count = $buyerModel->count();
    echo "✓ Works ($count buyers)<br>";
    
    if ($count > 0) {
        // Get a buyer to test
        $buyers = $buyerModel->all([], 'id ASC', 1);
        if (!empty($buyers)) {
            $buyerId = $buyers[0]['id'];
            
            // Test getBuyerById
            echo "getBuyerById($buyerId): ";
            $buyer = $buyerModel->getBuyerById($buyerId);
            echo "✓ Works<br>";
            
            // Test getStats
            echo "getStats($buyerId): ";
            $stats = $buyerModel->getStats($buyerId);
            echo "✓ Works<br>";
        }
    }
    
    echo "<br><strong>All method tests completed!</strong><br>";
    
} catch (Exception $e) {
    echo "<br>❌ Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
} catch (Error $e) {
    echo "<br>❌ Fatal Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
}
?>