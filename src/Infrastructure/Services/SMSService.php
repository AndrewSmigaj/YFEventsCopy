<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Services;

use YakimaFinds\Infrastructure\Config\ConfigurationInterface;

class SMSService
{
    private bool $enabled;
    private string $provider;
    
    public function __construct(
        private readonly ConfigurationInterface $config
    ) {
        $this->enabled = $config->get('sms.enabled', false);
        $this->provider = $config->get('sms.provider', 'twilio');
    }

    /**
     * Send buyer verification SMS
     */
    public function sendBuyerVerification(string $phone, string $code): bool
    {
        $message = "Your YakimaFinds verification code is: {$code}. This code expires in 10 minutes.";
        
        return $this->send($phone, $message);
    }

    /**
     * Send offer accepted SMS
     */
    public function sendOfferAccepted(string $phone, array $offerDetails): bool
    {
        $message = "Your offer for \"{$offerDetails['item_title']}\" was accepted! ";
        $message .= "Amount: \${$offerDetails['amount']}. ";
        $message .= "Pickup: {$offerDetails['pickup_date']} at {$offerDetails['pickup_location']}.";
        
        return $this->send($phone, $message);
    }

    /**
     * Send sale reminder SMS
     */
    public function sendSaleReminder(string $phone, array $saleDetails): bool
    {
        $message = "Reminder: \"{$saleDetails['title']}\" claiming starts in 1 hour! ";
        $message .= "Location: {$saleDetails['location']}";
        
        return $this->send($phone, $message);
    }

    /**
     * Send SMS (placeholder implementation)
     */
    private function send(string $to, string $message): bool
    {
        if (!$this->enabled) {
            // Log the message instead
            error_log("SMS to {$to}: {$message}");
            return true;
        }
        
        // In production, this would integrate with Twilio, AWS SNS, etc.
        switch ($this->provider) {
            case 'twilio':
                return $this->sendViaTwilio($to, $message);
            case 'aws':
                return $this->sendViaAWS($to, $message);
            default:
                error_log("SMS to {$to}: {$message}");
                return true;
        }
    }

    /**
     * Send via Twilio (placeholder)
     */
    private function sendViaTwilio(string $to, string $message): bool
    {
        // Twilio integration would go here
        error_log("Twilio SMS to {$to}: {$message}");
        return true;
    }

    /**
     * Send via AWS SNS (placeholder)
     */
    private function sendViaAWS(string $to, string $message): bool
    {
        // AWS SNS integration would go here
        error_log("AWS SMS to {$to}: {$message}");
        return true;
    }

    /**
     * Validate phone number
     */
    public function validatePhoneNumber(string $phone): bool
    {
        // Remove all non-digits
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if it's a valid US phone number
        return strlen($digits) === 10 || (strlen($digits) === 11 && $digits[0] === '1');
    }

    /**
     * Format phone number
     */
    public function formatPhoneNumber(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6)
            );
        } elseif (strlen($digits) === 11 && $digits[0] === '1') {
            return sprintf('+1 (%s) %s-%s',
                substr($digits, 1, 3),
                substr($digits, 4, 3),
                substr($digits, 7)
            );
        }
        
        return $phone;
    }
}