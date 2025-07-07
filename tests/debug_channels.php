<?php
// Debug channel loading

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use YFEvents\Application\Bootstrap;

$container = Bootstrap::boot();
$pdo = $container->resolve(\PDO::class);

echo "=== Debug Channel Loading ===\n\n";

// 1. Check database directly
echo "1. Channels in database:\n";
$stmt = $pdo->query("SELECT id, name, slug, type, is_archived FROM communication_channels");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($channels as $channel) {
    echo "   ID: {$channel['id']}, Name: {$channel['name']}, Slug: {$channel['slug']}, Type: {$channel['type']}, Archived: {$channel['is_archived']}\n";
}

// 2. Test repository
echo "\n2. Testing ChannelRepository::findPublicChannels():\n";
$channelRepo = $container->resolve(\YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface::class);
$publicChannels = $channelRepo->findPublicChannels();

echo "   Found " . count($publicChannels) . " channels\n";
foreach ($publicChannels as $channel) {
    echo "   - " . $channel->getName() . " (Type: " . $channel->getType()->getValue() . ")\n";
}

// 3. Test AdminSellerChatService
echo "\n3. Testing AdminSellerChatService::getGlobalChannels():\n";
try {
    $adminSellerChat = $container->resolve(\YFEvents\Application\Services\Communication\AdminSellerChatService::class);
    $globalChannels = $adminSellerChat->getGlobalChannels();
    echo "   Success! Found:\n";
    echo "   - Support: " . $globalChannels['support']->getName() . "\n";
    echo "   - Tips: " . $globalChannels['tips']->getName() . "\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== End Debug ===\n";