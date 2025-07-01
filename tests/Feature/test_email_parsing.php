<?php

declare(strict_types=1);

/**
 * Test script for Facebook email parsing
 * Simulates Facebook event emails to test parsing logic
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use YFEvents\Infrastructure\Services\EmailEventProcessor;

echo "🧪 Facebook Email Parsing Test\n";
echo "===============================\n\n";

// Sample Facebook event invitation email
$sampleInvitationEmail = "
John Doe invited you to Summer Music Festival

Saturday, July 15, 2024 at 6:00 PM

Location: Yakima Valley Park, 123 Main St, Yakima, WA

Description:
Join us for an amazing evening of live music featuring local bands. Food trucks, drinks, and family-friendly activities available. Bring your lawn chairs and blankets!

Going: 45 people
Maybe: 23 people

View Event: https://www.facebook.com/events/123456789
";

// Sample Facebook event reminder email
$sampleReminderEmail = "
Summer Concert Series
Saturday, July 22 at 7:00 PM

Hosted by Yakima Music Society
Downtown Yakima Amphitheater

View Event: https://www.facebook.com/events/987654321
";

// Create mock header objects
$invitationHeader = (object) [
    'subject' => 'John Doe invited you to Summer Music Festival',
    'from' => [(object) ['mailbox' => 'notification', 'host' => 'facebook.com']]
];

$reminderHeader = (object) [
    'subject' => 'Reminder: Summer Concert Series is tomorrow',
    'from' => [(object) ['mailbox' => 'events', 'host' => 'facebookmail.com']]
];

// Setup database (for testing, we'll use minimal config)
try {
    $config = require dirname(__DIR__) . '/config/database.php';
    $dbConfig = $config['database'];
    
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Database connection successful\n\n";
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Load email config
$emailConfig = require dirname(__DIR__) . '/config/email.php';

// Create processor
$processor = new EmailEventProcessor($pdo, $emailConfig);

// Test email detection using reflection
echo "🔍 Testing Email Detection\n";
echo "---------------------------\n";

try {
    $reflection = new ReflectionClass($processor);
    $detectMethod = $reflection->getMethod('isFacebookEventEmail');
    $detectMethod->setAccessible(true);
    
    $isInvitationDetected = $detectMethod->invoke($processor, $invitationHeader, $sampleInvitationEmail);
    $isReminderDetected = $detectMethod->invoke($processor, $reminderHeader, $sampleReminderEmail);
    
    echo "Invitation Email Detection: " . ($isInvitationDetected ? "✅ DETECTED" : "❌ NOT DETECTED") . "\n";
    echo "Reminder Email Detection: " . ($isReminderDetected ? "✅ DETECTED" : "❌ NOT DETECTED") . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Detection test error: " . $e->getMessage() . "\n\n";
}

// Test email parsing
echo "📝 Testing Email Parsing\n";
echo "-------------------------\n";

try {
    // Use reflection to access private methods for testing
    $reflection = new ReflectionClass($processor);
    $parseMethod = $reflection->getMethod('parseEventFromEmail');
    $parseMethod->setAccessible(true);
    
    echo "Parsing invitation email...\n";
    $invitationData = $parseMethod->invoke($processor, $sampleInvitationEmail, $invitationHeader);
    
    if ($invitationData) {
        echo "✅ Invitation parsed successfully:\n";
        echo "  Title: " . ($invitationData['title'] ?? 'N/A') . "\n";
        echo "  Date: " . ($invitationData['start_date'] ?? 'N/A') . "\n";
        echo "  Location: " . ($invitationData['location'] ?? 'N/A') . "\n";
        echo "  Description: " . substr($invitationData['description'] ?? '', 0, 50) . "...\n";
        echo "  Going: " . ($invitationData['attendees_going'] ?? 0) . " people\n";
        echo "  URL: " . ($invitationData['event_url'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Failed to parse invitation email\n";
    }
    
    echo "\nParsing reminder email...\n";
    $reminderData = $parseMethod->invoke($processor, $sampleReminderEmail, $reminderHeader);
    
    if ($reminderData) {
        echo "✅ Reminder parsed successfully:\n";
        echo "  Title: " . ($reminderData['title'] ?? 'N/A') . "\n";
        echo "  Date: " . ($reminderData['start_date'] ?? 'N/A') . "\n";
        echo "  Location: " . ($reminderData['location'] ?? 'N/A') . "\n";
        echo "  URL: " . ($reminderData['event_url'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Failed to parse reminder email\n";
    }
    
} catch (Exception $e) {
    echo "❌ Parsing error: " . $e->getMessage() . "\n";
}

echo "\n📊 Current Statistics\n";
echo "--------------------\n";
$stats = $processor->getProcessingStats();
echo "Total Events (30 days): " . ($stats['total_events'] ?? 0) . "\n";
echo "Email Events: " . ($stats['email_events'] ?? 0) . "\n";
echo "Pending Email Events: " . ($stats['pending_email_events'] ?? 0) . "\n";

echo "\n✨ Test Complete!\n";
echo "================\n";
echo "Email parsing system is ready for Facebook event processing.\n";
echo "Next step: Set up actual email account and test with real Facebook invitations.\n\n";