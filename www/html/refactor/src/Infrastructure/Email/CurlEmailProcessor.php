<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Email;

use Exception;

/**
 * Alternative email processor that doesn't require IMAP extension
 * Uses file-based email processing instead
 */
class CurlEmailProcessor
{
    private array $config;
    private string $emailDir;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->emailDir = dirname(__DIR__, 3) . '/storage/emails';
        
        // Create email storage directory
        if (!is_dir($this->emailDir)) {
            mkdir($this->emailDir, 0755, true);
        }
    }

    /**
     * Process emails from file uploads or forwarded emails
     */
    public function processEmails(): array
    {
        $results = [
            'processed' => 0,
            'errors' => 0,
            'messages' => []
        ];

        try {
            // Check for uploaded email files
            $emailFiles = glob($this->emailDir . '/*.eml');
            
            foreach ($emailFiles as $file) {
                try {
                    $this->processSingleEmailFile($file);
                    $results['processed']++;
                    $results['messages'][] = "Processed: " . basename($file);
                    
                    // Move processed file to archive
                    $archiveDir = $this->emailDir . '/processed';
                    if (!is_dir($archiveDir)) {
                        mkdir($archiveDir, 0755, true);
                    }
                    rename($file, $archiveDir . '/' . basename($file));
                    
                } catch (Exception $e) {
                    $results['errors']++;
                    $results['messages'][] = "Error processing " . basename($file) . ": " . $e->getMessage();
                }
            }

        } catch (Exception $e) {
            $results['messages'][] = "Email processing error: " . $e->getMessage();
        }

        return $results;
    }

    /**
     * Process a single email file
     */
    private function processSingleEmailFile(string $filePath): void
    {
        $content = file_get_contents($filePath);
        
        // Parse email headers and body
        $email = $this->parseEmailContent($content);
        
        // Check if it's a Facebook event invitation
        if ($this->isFacebookEvent($email)) {
            $this->processFacebookEvent($email);
        }
    }

    /**
     * Parse email content into structured data
     */
    private function parseEmailContent(string $content): array
    {
        $lines = explode("\n", $content);
        $headers = [];
        $body = '';
        $inHeaders = true;

        foreach ($lines as $line) {
            if ($inHeaders) {
                if (trim($line) === '') {
                    $inHeaders = false;
                    continue;
                }
                
                if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                    $headers[strtolower($matches[1])] = trim($matches[2]);
                }
            } else {
                $body .= $line . "\n";
            }
        }

        return [
            'headers' => $headers,
            'body' => $body,
            'subject' => $headers['subject'] ?? '',
            'from' => $headers['from'] ?? '',
            'date' => $headers['date'] ?? ''
        ];
    }

    /**
     * Check if email is a Facebook event
     */
    private function isFacebookEvent(array $email): bool
    {
        $indicators = [
            'facebook.com',
            'facebookmail.com',
            'event invitation',
            'invited you to',
            'facebook event'
        ];

        $searchText = strtolower($email['from'] . ' ' . $email['subject'] . ' ' . $email['body']);

        foreach ($indicators as $indicator) {
            if (strpos($searchText, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process Facebook event and extract details
     */
    private function processFacebookEvent(array $email): void
    {
        // Extract event details from email body
        $eventData = $this->extractEventDetails($email['body']);
        
        if ($eventData) {
            // Save to database or file for manual review
            $this->saveExtractedEvent($eventData, $email);
        }
    }

    /**
     * Extract event details from email content
     */
    private function extractEventDetails(string $body): ?array
    {
        $eventData = [];

        // Extract event title
        if (preg_match('/Event:\s*(.+?)(?:\n|$)/i', $body, $matches)) {
            $eventData['title'] = trim($matches[1]);
        }

        // Extract date/time
        if (preg_match('/Date:\s*(.+?)(?:\n|$)/i', $body, $matches)) {
            $eventData['date'] = trim($matches[1]);
        }

        // Extract location
        if (preg_match('/Location:\s*(.+?)(?:\n|$)/i', $body, $matches)) {
            $eventData['location'] = trim($matches[1]);
        }

        // Extract Facebook event URL
        if (preg_match('/(https:\/\/(?:www\.)?facebook\.com\/events\/[0-9]+)/i', $body, $matches)) {
            $eventData['facebook_url'] = $matches[1];
        }

        return !empty($eventData) ? $eventData : null;
    }

    /**
     * Save extracted event data
     */
    private function saveExtractedEvent(array $eventData, array $email): void
    {
        $pendingDir = $this->emailDir . '/pending_events';
        if (!is_dir($pendingDir)) {
            mkdir($pendingDir, 0755, true);
        }

        $eventFile = $pendingDir . '/event_' . time() . '_' . uniqid() . '.json';
        
        $data = [
            'extracted_at' => date('Y-m-d H:i:s'),
            'email_from' => $email['from'],
            'email_subject' => $email['subject'],
            'email_date' => $email['date'],
            'event_data' => $eventData,
            'status' => 'pending_review'
        ];

        file_put_contents($eventFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Get connection status without requiring IMAP
     */
    public function testConnection(): array
    {
        return [
            'success' => true,
            'message' => 'File-based email processing is ready. Upload .eml files to: ' . $this->emailDir,
            'upload_directory' => $this->emailDir,
            'instructions' => [
                'Forward emails as attachments (.eml format)',
                'Upload email files to the storage/emails directory',
                'Run the processor to extract event data'
            ]
        ];
    }
}