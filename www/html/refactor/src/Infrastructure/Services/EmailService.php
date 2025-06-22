<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Services;

use YFEvents\Infrastructure\Config\ConfigurationInterface;

class EmailService
{
    private string $fromEmail;
    private string $fromName;
    
    public function __construct(
        private readonly ConfigurationInterface $config
    ) {
        $this->fromEmail = $config->get('email.from_email', 'noreply@yakimafinds.com');
        $this->fromName = $config->get('email.from_name', 'YakimaFinds');
    }

    /**
     * Send buyer verification email
     */
    public function sendBuyerVerification(string $email, string $code, string $token): bool
    {
        $subject = 'Verify Your Email - YakimaFinds Claims';
        
        $body = "Your verification code is: {$code}\n\n";
        $body .= "This code will expire in 10 minutes.\n\n";
        $body .= "If you did not request this code, please ignore this email.";
        
        return $this->send($email, $subject, $body);
    }

    /**
     * Send offer accepted notification
     */
    public function sendOfferAccepted(string $email, array $offerDetails): bool
    {
        $subject = 'Your Offer Has Been Accepted!';
        
        $body = "Congratulations! Your offer for \"{$offerDetails['item_title']}\" has been accepted.\n\n";
        $body .= "Offer Amount: \${$offerDetails['amount']}\n";
        $body .= "Pickup Date: {$offerDetails['pickup_date']}\n";
        $body .= "Pickup Location: {$offerDetails['pickup_location']}\n\n";
        $body .= "Please bring cash for payment at pickup.";
        
        return $this->send($email, $subject, $body);
    }

    /**
     * Send offer rejected notification
     */
    public function sendOfferRejected(string $email, array $offerDetails): bool
    {
        $subject = 'Offer Update - YakimaFinds Claims';
        
        $body = "Unfortunately, your offer for \"{$offerDetails['item_title']}\" was not selected.\n\n";
        $body .= "The seller has chosen another offer. Thank you for your interest!";
        
        return $this->send($email, $subject, $body);
    }

    /**
     * Send sale reminder
     */
    public function sendSaleReminder(string $email, array $saleDetails): bool
    {
        $subject = "Reminder: {$saleDetails['title']} - Claiming Starts Soon!";
        
        $body = "The claiming period for \"{$saleDetails['title']}\" starts in 1 hour!\n\n";
        $body .= "Location: {$saleDetails['location']}\n";
        $body .= "Claiming Period: {$saleDetails['claim_start']} - {$saleDetails['claim_end']}\n\n";
        $body .= "Visit the sale at: {$saleDetails['url']}";
        
        return $this->send($email, $subject, $body);
    }

    /**
     * Send email (placeholder implementation)
     */
    private function send(string $to, string $subject, string $body, bool $isHtml = false): bool
    {
        $headers = [
            'From' => "{$this->fromName} <{$this->fromEmail}>",
            'Reply-To' => $this->fromEmail,
            'X-Mailer' => 'PHP/' . phpversion()
        ];
        
        if ($isHtml) {
            $headers['Content-Type'] = 'text/html; charset=UTF-8';
        }
        
        // In production, this would use a proper email service
        return mail(
            $to,
            $subject,
            $body,
            implode("\r\n", array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers))
        );
    }
}