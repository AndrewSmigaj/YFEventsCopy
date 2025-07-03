<?php
/**
 * Send email notifications for YFClaim
 * This script can be run via cron or manually
 * 
 * Usage: php send-notification-emails.php [--dry-run]
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/db_connection.php';

use YFEvents\Modules\YFClaim\Models\NotificationModel;
use YFEvents\Modules\YFClaim\Models\SellerModel;

// Check for dry-run mode
$dryRun = in_array('--dry-run', $argv);

// Initialize models
$notificationModel = new NotificationModel($pdo);
$sellerModel = new SellerModel($pdo);

// Configuration
$fromEmail = 'noreply@yakimafinds.com';
$fromName = 'YFClaim Estate Sales';
$batchSize = 50; // Process 50 notifications at a time

echo "YFClaim Email Notification Sender\n";
echo "=================================\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no emails will be sent)" : "LIVE") . "\n\n";

try {
    // Get unsent notifications
    $sql = "SELECT n.*, s.email as seller_email, s.contact_name, s.company_name
            FROM yfc_notifications n
            JOIN yfc_sellers s ON n.seller_id = s.id
            WHERE n.email_sent = 0
            AND s.email IS NOT NULL
            AND s.email != ''
            ORDER BY n.created_at ASC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        echo "No unsent notifications found.\n";
        exit(0);
    }
    
    echo "Found " . count($notifications) . " notifications to send.\n\n";
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($notifications as $notification) {
        echo "Processing notification #{$notification['id']} for {$notification['seller_email']}... ";
        
        // Prepare email content based on notification type
        $subject = match($notification['type']) {
            'new_offer' => "New Offer - {$notification['title']}",
            'sale_ending' => "Sale Ending Soon - {$notification['title']}",
            'item_claimed' => "Item Claimed - {$notification['title']}",
            default => $notification['title']
        };
        
        // Build email body
        $body = "Hello {$notification['contact_name']},\n\n";
        $body .= "{$notification['message']}\n\n";
        
        // Add action link based on type
        if ($notification['type'] === 'new_offer' && $notification['sale_id']) {
            $body .= "View offers for this sale:\n";
            $body .= "http://137.184.245.149/modules/yfclaim/www/dashboard/view-offers.php?sale_id={$notification['sale_id']}\n\n";
        } elseif ($notification['type'] === 'sale_ending' && $notification['sale_id']) {
            $body .= "Review and accept offers:\n";
            $body .= "http://137.184.245.149/modules/yfclaim/www/dashboard/view-offers.php?sale_id={$notification['sale_id']}\n\n";
        }
        
        $body .= "View all notifications:\n";
        $body .= "http://137.184.245.149/modules/yfclaim/www/dashboard/notifications.php\n\n";
        
        $body .= "Best regards,\n";
        $body .= "YFClaim Estate Sales Team\n\n";
        $body .= "---\n";
        $body .= "This is an automated notification from YFClaim Estate Sales.\n";
        $body .= "To manage your notification preferences, please log in to your dashboard.";
        
        if ($dryRun) {
            echo "DRY RUN - Would send to {$notification['seller_email']}\n";
            echo "  Subject: $subject\n";
            echo "  Body preview: " . substr($notification['message'], 0, 50) . "...\n";
            $successCount++;
        } else {
            // Prepare email headers
            $headers = [
                'From' => "$fromName <$fromEmail>",
                'Reply-To' => $fromEmail,
                'X-Mailer' => 'PHP/' . phpversion(),
                'Content-Type' => 'text/plain; charset=UTF-8'
            ];
            
            $headerString = '';
            foreach ($headers as $key => $value) {
                $headerString .= "$key: $value\r\n";
            }
            
            // Send email
            $sent = mail(
                $notification['seller_email'],
                $subject,
                $body,
                $headerString
            );
            
            if ($sent) {
                echo "SENT\n";
                
                // Mark as sent
                $updateStmt = $pdo->prepare("UPDATE yfc_notifications SET email_sent = 1 WHERE id = ?");
                $updateStmt->execute([$notification['id']]);
                
                $successCount++;
            } else {
                echo "FAILED\n";
                error_log("Failed to send notification email #{$notification['id']} to {$notification['seller_email']}");
                $failCount++;
            }
        }
        
        // Small delay to avoid overwhelming mail server
        usleep(100000); // 0.1 second
    }
    
    echo "\n";
    echo "Summary:\n";
    echo "--------\n";
    echo "Successfully sent: $successCount\n";
    echo "Failed: $failCount\n";
    echo "Total processed: " . count($notifications) . "\n";
    
    if (count($notifications) == $batchSize) {
        echo "\nNote: There may be more notifications to send. Run this script again.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone.\n";