<?php

declare(strict_types=1);

namespace YakimaFinds\Application\Services;

use YakimaFinds\Domain\Claims\Buyer;
use YakimaFinds\Domain\Claims\Seller;
use YakimaFinds\Domain\Claims\BuyerRepositoryInterface;
use YakimaFinds\Domain\Claims\SellerRepositoryInterface;
use YakimaFinds\Infrastructure\Services\EmailService;
use YakimaFinds\Infrastructure\Services\SMSService;

class ClaimAuthService
{
    private const VERIFICATION_CODE_LENGTH = 6;
    private const MAX_VERIFICATION_ATTEMPTS = 5;
    
    public function __construct(
        private readonly BuyerRepositoryInterface $buyerRepository,
        private readonly SellerRepositoryInterface $sellerRepository,
        private readonly EmailService $emailService,
        private readonly SMSService $smsService,
        private readonly array $config = []
    ) {}

    /**
     * Authenticate buyer with email
     */
    public function authenticateBuyerWithEmail(string $email, ?string $name = null): array
    {
        // Check if buyer exists
        $buyer = $this->buyerRepository->findByEmail($email);
        
        if (!$buyer) {
            // Create new buyer
            $buyer = Buyer::fromArray([
                'email' => $email,
                'name' => $name,
                'auth_method' => 'email'
            ]);
            $buyer = $this->buyerRepository->save($buyer);
        } else {
            // Regenerate token if expired
            if (!$buyer->isTokenValid()) {
                $buyer = $buyer->regenerateToken();
                $buyer = $this->buyerRepository->save($buyer);
            }
        }
        
        // Generate verification code
        $verificationCode = $this->generateVerificationCode();
        $this->storeVerificationCode($buyer->getId(), $verificationCode);
        
        // Send verification email
        $this->emailService->sendBuyerVerification($email, $verificationCode, $buyer->getAuthToken());
        
        return [
            'buyer_id' => $buyer->getId(),
            'auth_token' => $buyer->getAuthToken(),
            'requires_verification' => !$buyer->isVerified()
        ];
    }

    /**
     * Authenticate buyer with phone
     */
    public function authenticateBuyerWithPhone(string $phone, ?string $name = null): array
    {
        // Normalize phone number
        $phone = $this->normalizePhoneNumber($phone);
        
        // Check if buyer exists
        $buyer = $this->buyerRepository->findByPhone($phone);
        
        if (!$buyer) {
            // Create new buyer
            $buyer = Buyer::fromArray([
                'phone' => $phone,
                'name' => $name,
                'auth_method' => 'sms'
            ]);
            $buyer = $this->buyerRepository->save($buyer);
        } else {
            // Regenerate token if expired
            if (!$buyer->isTokenValid()) {
                $buyer = $buyer->regenerateToken();
                $buyer = $this->buyerRepository->save($buyer);
            }
        }
        
        // Generate verification code
        $verificationCode = $this->generateVerificationCode();
        $this->storeVerificationCode($buyer->getId(), $verificationCode);
        
        // Send verification SMS
        $this->smsService->sendBuyerVerification($phone, $verificationCode);
        
        return [
            'buyer_id' => $buyer->getId(),
            'auth_token' => $buyer->getAuthToken(),
            'requires_verification' => !$buyer->isVerified()
        ];
    }

    /**
     * Verify buyer with code
     */
    public function verifyBuyer(int $buyerId, string $code): bool
    {
        $buyer = $this->buyerRepository->findById($buyerId);
        
        if (!$buyer) {
            throw new \RuntimeException("Buyer not found");
        }
        
        // Check verification attempts
        if ($this->hasExceededVerificationAttempts($buyerId)) {
            throw new \RuntimeException("Maximum verification attempts exceeded");
        }
        
        // Verify code
        if (!$this->verifyCode($buyerId, $code)) {
            $this->incrementVerificationAttempts($buyerId);
            return false;
        }
        
        // Mark buyer as verified
        $verifiedBuyer = $buyer->verify();
        $this->buyerRepository->save($verifiedBuyer);
        
        // Clear verification data
        $this->clearVerificationData($buyerId);
        
        return true;
    }

    /**
     * Get buyer by token
     */
    public function getBuyerByToken(string $token): ?Buyer
    {
        $buyer = $this->buyerRepository->findByAuthToken($token);
        
        if (!$buyer || !$buyer->isTokenValid()) {
            return null;
        }
        
        // Update last activity
        $buyer = $buyer->updateLastActivity();
        $this->buyerRepository->save($buyer);
        
        return $buyer;
    }

    /**
     * Authenticate seller
     */
    public function authenticateSeller(string $email, string $password): ?Seller
    {
        $seller = $this->sellerRepository->findByEmail($email);
        
        if (!$seller || !$seller->isActive()) {
            return null;
        }
        
        // Verify password (assuming it's stored on the associated user)
        if (!$this->verifySellerPassword($seller, $password)) {
            return null;
        }
        
        return $seller;
    }

    /**
     * Register new seller
     */
    public function registerSeller(array $data): Seller
    {
        // Check if email already exists
        if ($this->sellerRepository->findByEmail($data['email'])) {
            throw new \RuntimeException("Email already registered");
        }
        
        // Create seller
        $seller = Seller::fromArray($data);
        
        return $this->sellerRepository->save($seller);
    }

    /**
     * Update buyer contact info
     */
    public function updateBuyerContactInfo(int $buyerId, array $info): Buyer
    {
        $buyer = $this->buyerRepository->findById($buyerId);
        
        if (!$buyer) {
            throw new \RuntimeException("Buyer not found");
        }
        
        $updatedBuyer = $buyer->updateContactInfo($info);
        
        return $this->buyerRepository->save($updatedBuyer);
    }

    /**
     * Resend verification code
     */
    public function resendVerificationCode(int $buyerId): void
    {
        $buyer = $this->buyerRepository->findById($buyerId);
        
        if (!$buyer) {
            throw new \RuntimeException("Buyer not found");
        }
        
        if ($buyer->isVerified()) {
            throw new \RuntimeException("Buyer already verified");
        }
        
        // Generate new verification code
        $verificationCode = $this->generateVerificationCode();
        $this->storeVerificationCode($buyer->getId(), $verificationCode);
        
        // Send verification based on auth method
        if ($buyer->getAuthMethod() === 'email' && $buyer->getEmail()) {
            $this->emailService->sendBuyerVerification(
                $buyer->getEmail(),
                $verificationCode,
                $buyer->getAuthToken()
            );
        } elseif ($buyer->getAuthMethod() === 'sms' && $buyer->getPhone()) {
            $this->smsService->sendBuyerVerification(
                $buyer->getPhone(),
                $verificationCode
            );
        } else {
            throw new \RuntimeException("Cannot send verification - no contact method available");
        }
    }

    /**
     * Generate verification code
     */
    private function generateVerificationCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::VERIFICATION_CODE_LENGTH; $i++) {
            $code .= mt_rand(0, 9);
        }
        return $code;
    }

    /**
     * Store verification code
     */
    private function storeVerificationCode(int $buyerId, string $code): void
    {
        // Store in cache or session
        $_SESSION["verification_code_$buyerId"] = [
            'code' => hash('sha256', $code),
            'expires' => time() + 600, // 10 minutes
            'attempts' => 0
        ];
    }

    /**
     * Verify code
     */
    private function verifyCode(int $buyerId, string $code): bool
    {
        $sessionKey = "verification_code_$buyerId";
        
        if (!isset($_SESSION[$sessionKey])) {
            return false;
        }
        
        $data = $_SESSION[$sessionKey];
        
        // Check expiry
        if ($data['expires'] < time()) {
            unset($_SESSION[$sessionKey]);
            return false;
        }
        
        // Verify code
        return hash('sha256', $code) === $data['code'];
    }

    /**
     * Check if exceeded verification attempts
     */
    private function hasExceededVerificationAttempts(int $buyerId): bool
    {
        $sessionKey = "verification_code_$buyerId";
        
        if (!isset($_SESSION[$sessionKey])) {
            return false;
        }
        
        return $_SESSION[$sessionKey]['attempts'] >= self::MAX_VERIFICATION_ATTEMPTS;
    }

    /**
     * Increment verification attempts
     */
    private function incrementVerificationAttempts(int $buyerId): void
    {
        $sessionKey = "verification_code_$buyerId";
        
        if (isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey]['attempts']++;
        }
    }

    /**
     * Clear verification data
     */
    private function clearVerificationData(int $buyerId): void
    {
        $sessionKey = "verification_code_$buyerId";
        unset($_SESSION[$sessionKey]);
    }

    /**
     * Normalize phone number
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digits
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present
        if (strlen($phone) === 10) {
            $phone = '1' . $phone;
        }
        
        return $phone;
    }

    /**
     * Verify seller password
     */
    private function verifySellerPassword(Seller $seller, string $password): bool
    {
        // This would typically check against the user table
        // For now, we'll assume it's implemented elsewhere
        return true;
    }
}