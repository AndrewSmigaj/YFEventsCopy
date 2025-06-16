<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Services;

use PDO;
use Exception;
use YakimaFinds\Domain\Event\Event;

/**
 * Email-based event processing service for Facebook events
 * Parses Facebook event invitation and notification emails
 */
class EmailEventProcessor
{
    private PDO $pdo;
    private array $config;
    private $imapConnection = null;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Setup IMAP connection to email inbox
     */
    public function setupIMAPConnection(): bool
    {
        try {
            $mailbox = $this->config['email']['imap_server'] ?? '{localhost:993/imap/ssl}INBOX';
            $username = $this->config['email']['username'] ?? 'events@yakimafinds.com';
            $password = $this->config['email']['password'] ?? '';

            $this->imapConnection = imap_open($mailbox, $username, $password);
            
            if (!$this->imapConnection) {
                error_log("Failed to connect to email: " . imap_last_error());
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log("IMAP connection error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process all unread emails for Facebook events
     */
    public function processIncomingEmails(): array
    {
        if (!$this->setupIMAPConnection()) {
            return [];
        }

        $processedEvents = [];
        
        try {
            // Search for unread emails
            $emails = imap_search($this->imapConnection, 'UNSEEN');
            
            if (!$emails) {
                return [];
            }

            foreach ($emails as $emailId) {
                try {
                    $header = imap_headerinfo($this->imapConnection, $emailId);
                    $body = $this->getEmailBody($emailId);
                    
                    if ($this->isFacebookEventEmail($header, $body)) {
                        $event = $this->parseEventFromEmail($body, $header);
                        
                        if ($event && $this->validateEvent($event)) {
                            $savedEvent = $this->saveEvent($event, $header->from[0]->mailbox . '@' . $header->from[0]->host);
                            
                            if ($savedEvent) {
                                $processedEvents[] = $savedEvent;
                                $this->sendConfirmationEmail($header->from[0]->mailbox . '@' . $header->from[0]->host, $savedEvent);
                            }
                        }
                    }
                    
                    // Mark as read
                    imap_setflag_full($this->imapConnection, $emailId, "\\Seen");
                    
                } catch (Exception $e) {
                    error_log("Error processing email {$emailId}: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            error_log("Error processing emails: " . $e->getMessage());
        } finally {
            if ($this->imapConnection) {
                imap_close($this->imapConnection);
            }
        }

        return $processedEvents;
    }

    /**
     * Get email body content (handles multipart)
     */
    private function getEmailBody(int $emailId): string
    {
        $structure = imap_fetchstructure($this->imapConnection, $emailId);
        
        if (isset($structure->parts)) {
            // Multipart email
            foreach ($structure->parts as $partNum => $part) {
                if ($part->subtype === 'PLAIN' || $part->subtype === 'HTML') {
                    $body = imap_fetchbody($this->imapConnection, $emailId, $partNum + 1);
                    
                    if ($part->encoding === 3) {
                        $body = base64_decode($body);
                    } elseif ($part->encoding === 4) {
                        $body = quoted_printable_decode($body);
                    }
                    
                    return $body;
                }
            }
        } else {
            // Simple email
            $body = imap_body($this->imapConnection, $emailId);
            
            if ($structure->encoding === 3) {
                $body = base64_decode($body);
            } elseif ($structure->encoding === 4) {
                $body = quoted_printable_decode($body);
            }
            
            return $body;
        }
        
        return '';
    }

    /**
     * Check if email is a Facebook event email
     */
    private function isFacebookEventEmail(object $header, string $body): bool
    {
        $subject = $header->subject ?? '';
        $fromEmail = '';
        
        if (isset($header->from[0])) {
            $fromEmail = $header->from[0]->mailbox . '@' . $header->from[0]->host;
        }

        // Check if from Facebook
        $facebookDomains = ['facebook.com', 'facebookmail.com', 'notification.facebook.com'];
        $isFromFacebook = false;
        
        foreach ($facebookDomains as $domain) {
            if (strpos($fromEmail, $domain) !== false) {
                $isFromFacebook = true;
                break;
            }
        }

        // Check subject patterns
        $eventSubjectPatterns = [
            '/invited you to/',
            '/Reminder:.*is (today|tomorrow|in \d+ days?)/',
            '/You created an event:/',
            '/event has been updated/',
            '/Event starting soon:/',
            '/going to.*event/'
        ];

        $hasEventSubject = false;
        foreach ($eventSubjectPatterns as $pattern) {
            if (preg_match($pattern, $subject)) {
                $hasEventSubject = true;
                break;
            }
        }

        // Check body content for event indicators
        $eventBodyIndicators = [
            'facebook.com/events/',
            'View Event',
            'Going:',
            'Maybe:',
            'Interested:',
            'Location:',
            'Hosted by'
        ];

        $hasEventContent = false;
        foreach ($eventBodyIndicators as $indicator) {
            if (stripos($body, $indicator) !== false) {
                $hasEventContent = true;
                break;
            }
        }

        return $isFromFacebook && ($hasEventSubject || $hasEventContent);
    }

    /**
     * Parse event data from Facebook email
     */
    private function parseEventFromEmail(string $body, object $header): ?array
    {
        $subject = $header->subject ?? '';
        
        // Determine email type and parse accordingly
        if (preg_match('/invited you to (.+)/', $subject, $matches)) {
            return $this->parseEventInvitation($body, $matches[1]);
        }
        
        if (preg_match('/Reminder: (.+) is/', $subject, $matches)) {
            return $this->parseEventReminder($body, $matches[1]);
        }
        
        if (preg_match('/You created an event: (.+)/', $subject, $matches)) {
            return $this->parseEventCreation($body, $matches[1]);
        }

        // Try generic parsing
        return $this->parseGenericEventEmail($body);
    }

    /**
     * Parse Facebook event invitation email
     */
    private function parseEventInvitation(string $body, string $titleFromSubject): array
    {
        $eventData = [
            'title' => $titleFromSubject,
            'description' => '',
            'location' => '',
            'start_date' => '',
            'end_date' => '',
            'event_url' => '',
            'host' => '',
            'attendees_going' => 0,
            'attendees_maybe' => 0
        ];

        // Parse date and time
        if (preg_match('/([A-Z][a-z]+, [A-Z][a-z]+ \d+, \d{4} at \d+:\d+ [AP]M)/i', $body, $matches)) {
            // Remove "at" and parse the date
            $dateString = str_replace(' at ', ' ', $matches[1]);
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                $eventData['start_date'] = date('Y-m-d H:i:s', $timestamp);
            }
        }

        // Parse location
        if (preg_match('/Location:\s*(.+?)(?=\n|$)/i', $body, $matches)) {
            $eventData['location'] = trim($matches[1]);
        }

        // Parse description
        if (preg_match('/Description:\s*(.+?)(?=\nGoing|\nMaybe|\nView Event|Location:|$)/s', $body, $matches)) {
            $eventData['description'] = trim($matches[1]);
        }

        // Parse attendee counts
        if (preg_match('/Going:\s*(\d+)\s*people?/i', $body, $matches)) {
            $eventData['attendees_going'] = (int)$matches[1];
        }

        if (preg_match('/Maybe:\s*(\d+)\s*people?/i', $body, $matches)) {
            $eventData['attendees_maybe'] = (int)$matches[1];
        }

        // Parse event URL
        if (preg_match('/(https:\/\/(?:www\.)?facebook\.com\/events\/\d+)/', $body, $matches)) {
            $eventData['event_url'] = $matches[1];
        }

        // Parse host
        if (preg_match('/Hosted by\s+(.+?)(?=\n|$)/i', $body, $matches)) {
            $eventData['host'] = trim($matches[1]);
        }

        return $eventData;
    }

    /**
     * Parse Facebook event reminder email
     */
    private function parseEventReminder(string $body, string $titleFromSubject): array
    {
        $eventData = [
            'title' => $titleFromSubject,
            'description' => '',
            'location' => '',
            'start_date' => '',
            'event_url' => ''
        ];

        // Parse date/time from body
        if (preg_match('/([A-Z][a-z]+, [A-Z][a-z]+ \d+ at \d+:\d+ [AP]M)/i', $body, $matches)) {
            // Remove "at" and parse the date
            $dateString = str_replace(' at ', ' ', $matches[1]);
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                $eventData['start_date'] = date('Y-m-d H:i:s', $timestamp);
            }
        }

        // Parse location (usually comes after date)
        $lines = explode("\n", $body);
        foreach ($lines as $i => $line) {
            if (preg_match('/[A-Z][a-z]+, [A-Z][a-z]+ \d+ at \d+:\d+ [AP]M/', $line)) {
                // Location is usually the next non-empty line
                if (isset($lines[$i + 1]) && trim($lines[$i + 1])) {
                    $eventData['location'] = trim($lines[$i + 1]);
                }
                break;
            }
        }

        // Parse event URL
        if (preg_match('/(https:\/\/(?:www\.)?facebook\.com\/events\/\d+)/', $body, $matches)) {
            $eventData['event_url'] = $matches[1];
        }

        return $eventData;
    }

    /**
     * Parse event creation email
     */
    private function parseEventCreation(string $body, string $titleFromSubject): array
    {
        // Similar to invitation parsing but with different structure
        return $this->parseEventInvitation($body, $titleFromSubject);
    }

    /**
     * Generic email parsing for other Facebook event emails
     */
    private function parseGenericEventEmail(string $body): array
    {
        $eventData = [
            'title' => '',
            'description' => '',
            'location' => '',
            'start_date' => '',
            'event_url' => ''
        ];

        // Try to extract event URL first
        if (preg_match('/(https:\/\/(?:www\.)?facebook\.com\/events\/\d+)/', $body, $matches)) {
            $eventData['event_url'] = $matches[1];
        }

        // Try to find date patterns
        if (preg_match('/([A-Z][a-z]+, [A-Z][a-z]+ \d+(?:, \d{4})? at \d+:\d+ [AP]M)/i', $body, $matches)) {
            // Remove "at" and parse the date
            $dateString = str_replace(' at ', ' ', $matches[1]);
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                $eventData['start_date'] = date('Y-m-d H:i:s', $timestamp);
            }
        }

        // Try to find title (first meaningful line)
        $lines = array_filter(explode("\n", $body), function($line) {
            return trim($line) && strlen(trim($line)) > 5;
        });

        if (!empty($lines)) {
            $eventData['title'] = trim(array_values($lines)[0]);
        }

        return $eventData;
    }

    /**
     * Validate parsed event data
     */
    private function validateEvent(array $eventData): bool
    {
        // Must have title and either date or URL
        return !empty($eventData['title']) && 
               (!empty($eventData['start_date']) || !empty($eventData['event_url']));
    }

    /**
     * Save event to database
     */
    private function saveEvent(array $eventData, string $submitterEmail): ?array
    {
        try {
            // Check for duplicates
            if ($this->isDuplicateEvent($eventData)) {
                error_log("Duplicate event detected: {$eventData['title']}");
                return null;
            }

            $sql = "
                INSERT INTO events (
                    title, description, start_datetime, end_datetime, location, 
                    external_url, external_event_id, status, cms_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $eventData['title'],
                $eventData['description'],
                $eventData['start_date'] ?: null,
                $eventData['end_date'] ?: null,
                $eventData['location'],
                $eventData['event_url'],
                'facebook_email_' . time(), // Unique identifier
                'pending', // Requires admin approval
                null // No specific user ID for email submissions
            ]);

            $eventId = $this->pdo->lastInsertId();

            // Return the saved event
            $savedEvent = $eventData;
            $savedEvent['id'] = $eventId;
            $savedEvent['status'] = 'pending';

            error_log("Saved Facebook event: {$eventData['title']} (ID: {$eventId})");
            return $savedEvent;

        } catch (Exception $e) {
            error_log("Error saving event: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check for duplicate events
     */
    private function isDuplicateEvent(array $eventData): bool
    {
        try {
            $sql = "
                SELECT id FROM events 
                WHERE title = ? 
                AND (start_datetime = ? OR external_url = ?)
                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $eventData['title'],
                $eventData['start_date'] ?: '1970-01-01',
                $eventData['event_url'] ?: ''
            ]);

            return $stmt->rowCount() > 0;

        } catch (Exception $e) {
            error_log("Error checking duplicates: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send confirmation email to submitter
     */
    private function sendConfirmationEmail(string $toEmail, array $event): void
    {
        try {
            $subject = "✅ Event Added to Community Calendar";
            
            $message = "
Thank you for submitting an event to the YakimaFinds community calendar!

Event Details:
📅 Event: {$event['title']}
📍 Location: {$event['location']}
🕐 Date: " . ($event['start_date'] ? date('F j, Y \a\t g:i A', strtotime($event['start_date'])) : 'TBD') . "

Your event is now pending review and will appear on yakimafinds.com within 24 hours.

Thank you for helping keep our community informed!

---
YakimaFinds Community Calendar
https://yakimafinds.com/events
            ";

            // Use PHP's mail function (configure proper SMTP later)
            $headers = [
                'From: YakimaFinds <noreply@yakimafinds.com>',
                'Reply-To: events@yakimafinds.com',
                'Content-Type: text/plain; charset=UTF-8'
            ];

            mail($toEmail, $subject, $message, implode("\r\n", $headers));

        } catch (Exception $e) {
            error_log("Error sending confirmation email: " . $e->getMessage());
        }
    }

    /**
     * Get processing statistics
     */
    public function getProcessingStats(): array
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_events,
                    COUNT(CASE WHEN external_event_id LIKE 'facebook_email_%' THEN 1 END) as email_events,
                    COUNT(CASE WHEN status = 'pending' AND external_event_id LIKE 'facebook_email_%' THEN 1 END) as pending_email_events
                FROM events 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch() ?: [];

        } catch (Exception $e) {
            error_log("Error getting stats: " . $e->getMessage());
            return [];
        }
    }
}