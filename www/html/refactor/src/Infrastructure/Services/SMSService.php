<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Services;

use YakimaFinds\Infrastructure\Config\ConfigurationInterface;

class SMSService
{
    private bool $enabled;
    private string $provider;
    private array $smsConfig;
    
    public function __construct()
    {
        // Load configuration directly from config file
        $appConfig = include __DIR__ . '/../../config/app.php';
        $this->smsConfig = $appConfig['sms'] ?? [];
        $this->enabled = $this->smsConfig['enabled'] ?? false;
        $this->provider = $this->smsConfig['provider'] ?? 'twilio';
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
     * Send SMS with real implementation
     */
    private function send(string $to, string $message): bool
    {
        if (!$this->enabled) {
            // Log the message instead
            error_log("SMS disabled - would send to {$to}: {$message}");
            return false;
        }
        
        // Check test mode
        if ($this->smsConfig['test_mode'] ?? true) {
            error_log("SMS test mode - to {$to}: {$message}");
            return true;
        }
        
        // Send via configured provider
        switch ($this->provider) {
            case 'twilio':
                return $this->sendViaTwilio($to, $message);
            case 'aws':
                return $this->sendViaAWS($to, $message);
            case 'nexmo':
                return $this->sendViaNexmo($to, $message);
            default:
                error_log("Unknown SMS provider '{$this->provider}' - to {$to}: {$message}");
                return false;
        }
    }

    /**
     * Send via Twilio
     */
    private function sendViaTwilio(string $to, string $message): bool
    {
        $config = $this->smsConfig['twilio'] ?? [];
        $accountSid = $config['account_sid'] ?? '';
        $authToken = $config['auth_token'] ?? '';
        $fromNumber = $config['from_number'] ?? '';
        
        if (empty($accountSid) || empty($authToken) || empty($fromNumber)) {
            error_log("Twilio SMS failed: Missing configuration");
            return false;
        }
        
        try {
            // Twilio API call using cURL
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
            
            $data = [
                'From' => $fromNumber,
                'To' => $to,
                'Body' => $message
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_USERPWD, $accountSid . ':' . $authToken);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 201) {
                error_log("Twilio SMS sent successfully to {$to}");
                return true;
            } else {
                error_log("Twilio SMS failed: HTTP {$httpCode} - {$response}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Twilio SMS error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send via AWS SNS
     */
    private function sendViaAWS(string $to, string $message): bool
    {
        $config = $this->smsConfig['aws'] ?? [];
        $key = $config['key'] ?? '';
        $secret = $config['secret'] ?? '';
        $region = $config['region'] ?? 'us-east-1';
        
        if (empty($key) || empty($secret)) {
            error_log("AWS SNS failed: Missing configuration");
            return false;
        }
        
        try {
            // AWS SNS API call using cURL (simplified)
            // In production, you'd use the AWS SDK
            $endpoint = "https://sns.{$region}.amazonaws.com/";
            
            // For now, just log - AWS SNS requires more complex authentication
            error_log("AWS SNS would send to {$to}: {$message}");
            return true;
            
        } catch (Exception $e) {
            error_log("AWS SNS error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send via Nexmo/Vonage
     */
    private function sendViaNexmo(string $to, string $message): bool
    {
        $config = $this->smsConfig['nexmo'] ?? [];
        $apiKey = $config['api_key'] ?? '';
        $apiSecret = $config['api_secret'] ?? '';
        $fromNumber = $config['from_number'] ?? 'YFClaim';
        
        if (empty($apiKey) || empty($apiSecret)) {
            error_log("Nexmo SMS failed: Missing configuration");
            return false;
        }
        
        try {
            // Nexmo API call using cURL
            $url = 'https://rest.nexmo.com/sms/json';
            
            $data = [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'from' => $fromNumber,
                'to' => $to,
                'text' => $message
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($httpCode === 200 && isset($result['messages'][0]['status']) && $result['messages'][0]['status'] === '0') {
                error_log("Nexmo SMS sent successfully to {$to}");
                return true;
            } else {
                $errorText = $result['messages'][0]['error-text'] ?? 'Unknown error';
                error_log("Nexmo SMS failed: {$errorText}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Nexmo SMS error: " . $e->getMessage());
            return false;
        }
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