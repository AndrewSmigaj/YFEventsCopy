<?php

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Application\Services\YFClaim\InquiryService;

echo "Testing Inquiry System\n";
echo "=====================\n\n";

try {
    // Get container
    $container = require __DIR__ . '/../bootstrap/container.php';
    
    // Get InquiryService
    $inquiryService = $container->resolve(InquiryService::class);
    echo "✓ InquiryService resolved successfully\n";
    
    // Test creating an inquiry
    echo "\nTesting inquiry creation...\n";
    $testData = [
        'item_id' => 1, // Assuming item ID 1 exists
        'seller_id' => 1, // Assuming seller user ID 1 exists  
        'buyer_name' => 'Test Buyer',
        'buyer_email' => 'test@example.com',
        'buyer_phone' => '555-1234',
        'message' => 'I am interested in this item. Is it still available?',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Script'
    ];
    
    try {
        $inquiry = $inquiryService->createInquiry($testData);
        echo "✓ Inquiry created successfully with ID: " . $inquiry->getId() . "\n";
        
        // Test fetching inquiries
        echo "\nTesting inquiry retrieval...\n";
        $inquiries = $inquiryService->getSellerInquiries(1, ['limit' => 5]);
        echo "✓ Found " . count($inquiries) . " inquiries for seller\n";
        
        // Test unread count
        $unreadCount = $inquiryService->getUnreadCount(1);
        echo "✓ Unread inquiry count: " . $unreadCount . "\n";
        
        // Test marking as read
        if (!empty($inquiries)) {
            $firstInquiry = $inquiries[0];
            if ($firstInquiry->isNew()) {
                $inquiryService->markAsRead($firstInquiry->getId(), 1);
                echo "✓ Marked inquiry as read\n";
            }
        }
        
    } catch (Exception $e) {
        echo "✗ Error creating inquiry: " . $e->getMessage() . "\n";
    }
    
    // Test API endpoints
    echo "\nTesting API endpoints...\n";
    
    // Test public create endpoint
    $ch = curl_init('http://localhost/api/yfclaim/inquiries');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result['success'] ?? false) {
            echo "✓ API create endpoint working\n";
        } else {
            echo "✗ API create failed: " . ($result['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "✗ API returned HTTP $httpCode\n";
    }
    
    echo "\n✅ Inquiry system test completed\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}