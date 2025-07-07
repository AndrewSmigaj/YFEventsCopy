<?php
/**
 * Script to add mock messages to the Support and Tips & Tricks channels
 * This helps with testing the chat UI by providing realistic content
 */

require_once __DIR__ . '/../vendor/autoload.php';

use YFEvents\Infrastructure\Database\Connection;

// Load environment variables
$envLoader = new \YFEvents\Infrastructure\Utils\EnvLoader(__DIR__ . '/../.env');
$envLoader->load();

// Create database connection
$connection = new Connection(
    $_ENV['DB_HOST'] ?? 'localhost',
    $_ENV['DB_NAME'] ?? 'yakima_finds',
    $_ENV['DB_USER'] ?? 'yfevents',
    $_ENV['DB_PASS'] ?? 'yfevents_pass'
);
$pdo = $connection->getConnection();

echo "Adding mock messages to communication channels...\n\n";

// Get the Support and Tips & Tricks channel IDs
$stmt = $pdo->prepare("SELECT id, name, slug FROM communication_channels WHERE slug IN ('support', 'tips')");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "Error: Support and Tips & Tricks channels not found!\n";
    exit(1);
}

$supportChannelId = null;
$tipsChannelId = null;

foreach ($channels as $channel) {
    if ($channel['slug'] === 'support') {
        $supportChannelId = $channel['id'];
        echo "Found Support channel: ID {$channel['id']}\n";
    } elseif ($channel['slug'] === 'tips') {
        $tipsChannelId = $channel['id'];
        echo "Found Tips & Tricks channel: ID {$channel['id']}\n";
    }
}

// User IDs: 1 = admin, 2-5 = sellers
$adminId = 1;
$sellerIds = [2, 3, 4, 5];

// Helper function to insert a message
function insertMessage($pdo, $channelId, $userId, $content, $contentType = 'text', $hoursAgo = 0) {
    $createdAt = date('Y-m-d H:i:s', strtotime("-{$hoursAgo} hours"));
    
    $stmt = $pdo->prepare("
        INSERT INTO communication_messages 
        (channel_id, user_id, parent_message_id, content, content_type, is_pinned, is_edited, 
         is_deleted, yfclaim_item_id, metadata, email_message_id, reply_count, reaction_count, 
         created_at, updated_at, deleted_at)
        VALUES 
        (?, ?, NULL, ?, ?, FALSE, FALSE, FALSE, NULL, '{}', NULL, 0, 0, ?, ?, NULL)
    ");
    
    $stmt->execute([
        $channelId,
        $userId,
        $content,
        $contentType,
        $createdAt,
        $createdAt
    ]);
    
    return $pdo->lastInsertId();
}

// Add messages to Support channel
if ($supportChannelId) {
    echo "\nAdding messages to Support channel...\n";
    
    // Welcome message (72 hours ago)
    insertMessage($pdo, $supportChannelId, $adminId, 
        "Welcome to the YFEvents Support Channel! 👋\n\nThis is your place to ask questions, get help, and connect with our community. Our team and experienced sellers are here to help you succeed!", 
        'system', 72);
    
    // Seller question (48 hours ago)
    insertMessage($pdo, $supportChannelId, $sellerIds[0], 
        "Hi everyone! I'm new to YFEvents and setting up my first estate sale. Where do I start?", 
        'text', 48);
    
    // Admin response (47 hours ago)
    insertMessage($pdo, $supportChannelId, $adminId, 
        "Welcome! Here's a quick guide to get you started:\n\n1. Click 'Create New Sale' in your dashboard\n2. Fill in your sale details (dates, address, description)\n3. Add photos of your items\n4. Set your prices\n5. Publish when ready!\n\nLet me know if you need help with any step!", 
        'text', 47);
    
    // Seller follow-up (46 hours ago)
    insertMessage($pdo, $supportChannelId, $sellerIds[0], 
        "Thank you! How many photos should I add for each item?", 
        'text', 46);
    
    // Another seller helping (45 hours ago)
    insertMessage($pdo, $supportChannelId, $sellerIds[1], 
        "I usually add 3-5 photos per item. Make sure to show any defects or special features. Good lighting makes a huge difference!", 
        'text', 45);
    
    // Recent question (6 hours ago)
    insertMessage($pdo, $supportChannelId, $sellerIds[2], 
        "Does anyone know how to handle inquiries from buyers? I'm getting a lot of questions about my upcoming sale.", 
        'text', 6);
    
    // Admin response (5 hours ago)
    insertMessage($pdo, $supportChannelId, $adminId, 
        "Great question! We're actually implementing a new inquiry system. For now, you can direct buyers to visit during your sale hours. Make sure your sale description includes all the important details to minimize repetitive questions.", 
        'text', 5);
    
    // Recent activity (2 hours ago)
    insertMessage($pdo, $supportChannelId, $sellerIds[3], 
        "Quick tip: I always post my sale hours clearly at the top of my description. Saves so much time!", 
        'text', 2);
    
    echo "Added 8 messages to Support channel\n";
}

// Add messages to Tips & Tricks channel
if ($tipsChannelId) {
    echo "\nAdding messages to Tips & Tricks channel...\n";
    
    // Welcome message (72 hours ago)
    insertMessage($pdo, $tipsChannelId, $adminId, 
        "Welcome to Tips & Tricks! 🎯\n\nShare your best practices, learn from experienced sellers, and help each other succeed. This is our community knowledge base!", 
        'system', 72);
    
    // Pricing tip (36 hours ago)
    insertMessage($pdo, $tipsChannelId, $sellerIds[1], 
        "💡 Pricing Tip: Research similar items on eBay and price at 30-40% of online prices. Estate sale shoppers expect good deals but will pay fair prices for quality items.", 
        'text', 36);
    
    // Organization tip (24 hours ago)
    insertMessage($pdo, $tipsChannelId, $sellerIds[2], 
        "Here's how I organize my sales:\n\n• Group similar items together\n• Use tables to display small items\n• Keep jewelry and valuables near checkout\n• Clear pathways for safety\n• Have boxes/bags ready for buyers", 
        'text', 24);
    
    // Photo tips (18 hours ago)
    insertMessage($pdo, $tipsChannelId, $sellerIds[3], 
        "📸 Photo Tips That Increased My Sales:\n\n1. Natural lighting is best (near windows)\n2. Plain backgrounds (white sheet works great)\n3. Show size reference (ruler or common object)\n4. Capture maker's marks and labels\n5. Be honest about condition", 
        'text', 18);
    
    // Customer service (12 hours ago)
    insertMessage($pdo, $tipsChannelId, $adminId, 
        "Remember: Great customer service leads to repeat buyers and referrals! Always be friendly, honest about item conditions, and willing to negotiate reasonably.", 
        'text', 12);
    
    // Recent tip (4 hours ago)
    insertMessage($pdo, $tipsChannelId, $sellerIds[0], 
        "I started offering 'early bird' preview hours for serious collectors. They pay full price and often buy the best items. Game changer for my sales! 🌟", 
        'text', 4);
    
    // Discussion (3 hours ago)
    insertMessage($pdo, $tipsChannelId, $sellerIds[1], 
        "That's brilliant! How do you advertise the early bird hours?", 
        'text', 3);
    
    // Response (2 hours ago)
    insertMessage($pdo, $tipsChannelId, $sellerIds[0], 
        "I mention it in my sale description and send a special email to my subscriber list. Usually charge $5-10 entry fee that goes toward purchases.", 
        'text', 2);
    
    echo "Added 8 messages to Tips & Tricks channel\n";
}

// Update channel statistics
echo "\nUpdating channel statistics...\n";

$updateStmt = $pdo->prepare("
    UPDATE communication_channels c
    SET 
        message_count = (
            SELECT COUNT(*) 
            FROM communication_messages m 
            WHERE m.channel_id = c.id AND m.is_deleted = FALSE
        ),
        last_activity_at = NOW()
    WHERE id IN (?, ?)
");

$updateStmt->execute([$supportChannelId, $tipsChannelId]);

// Add some participants to the channels
echo "\nAdding participants to channels...\n";

$participantStmt = $pdo->prepare("
    INSERT IGNORE INTO communication_participants 
    (channel_id, user_id, role, joined_at, last_read_at, notification_preference, is_muted)
    VALUES 
    (?, ?, 'member', NOW(), NOW(), 'all', FALSE)
");

// Add all users to both channels
$allUsers = array_merge([$adminId], $sellerIds);
foreach ([$supportChannelId, $tipsChannelId] as $channelId) {
    if ($channelId) {
        foreach ($allUsers as $userId) {
            $participantStmt->execute([$channelId, $userId]);
        }
    }
}

// Update participant counts
$updateParticipantStmt = $pdo->prepare("
    UPDATE communication_channels c
    SET participant_count = (
        SELECT COUNT(*) 
        FROM communication_participants p 
        WHERE p.channel_id = c.id
    )
    WHERE id IN (?, ?)
");

$updateParticipantStmt->execute([$supportChannelId, $tipsChannelId]);

// Show final statistics
echo "\nFinal channel statistics:\n";
$finalStmt = $pdo->prepare("
    SELECT name, message_count, participant_count, last_activity_at 
    FROM communication_channels 
    WHERE id IN (?, ?)
");
$finalStmt->execute([$supportChannelId, $tipsChannelId]);
$results = $finalStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $channel) {
    echo "\n{$channel['name']}:\n";
    echo "  Messages: {$channel['message_count']}\n";
    echo "  Participants: {$channel['participant_count']}\n";
    echo "  Last Activity: {$channel['last_activity_at']}\n";
}

echo "\nMock messages added successfully! 🎉\n";
echo "You should now see messages when you visit the chat interface.\n";