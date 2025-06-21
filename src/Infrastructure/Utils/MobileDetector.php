<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Utils;

/**
 * Mobile device detection utility
 */
class MobileDetector
{
    private array $mobileUserAgents = [
        'Android',
        'webOS',
        'iPhone',
        'iPad',
        'iPod',
        'BlackBerry',
        'IEMobile',
        'Opera Mini',
        'Mobile',
        'mobile',
        'CriOS',
        'FxiOS',
    ];
    
    private array $mobileHeaders = [
        'HTTP_X_WAP_PROFILE',
        'HTTP_X_OPERAMINI_PHONE_UA',
        'HTTP_X_NOKIA_GATEWAY_ID',
        'HTTP_X_ORANGE_ID',
        'HTTP_X_VODAFONE_3GPDPCONTEXT',
        'HTTP_X_HUAWEI_USERID',
        'HTTP_UA_OS',
        'HTTP_X_MOBILE_GATEWAY',
        'HTTP_X_ATT_DEVICEID',
        'HTTP_UA_CPU',
    ];
    
    /**
     * Check if the current request is from a mobile device
     */
    public function isMobile(): bool
    {
        // Check user agent
        if ($this->checkUserAgent()) {
            return true;
        }
        
        // Check mobile-specific headers
        if ($this->checkMobileHeaders()) {
            return true;
        }
        
        // Check accept header
        if ($this->checkAcceptHeader()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if the device is a tablet
     */
    public function isTablet(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // iPad
        if (preg_match('/iPad/', $userAgent)) {
            return true;
        }
        
        // Android tablets (not phones)
        if (preg_match('/Android/', $userAgent) && !preg_match('/Mobile/', $userAgent)) {
            return true;
        }
        
        // Windows tablets
        if (preg_match('/Tablet PC/', $userAgent)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if the device is a phone (not tablet)
     */
    public function isPhone(): bool
    {
        return $this->isMobile() && !$this->isTablet();
    }
    
    /**
     * Get the device type
     */
    public function getDeviceType(): string
    {
        if ($this->isTablet()) {
            return 'tablet';
        }
        
        if ($this->isPhone()) {
            return 'phone';
        }
        
        return 'desktop';
    }
    
    /**
     * Check if the app is running as a PWA
     */
    public function isPWA(): bool
    {
        // Check for display mode
        if (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'document') {
            if (isset($_SERVER['HTTP_SEC_FETCH_MODE']) && $_SERVER['HTTP_SEC_FETCH_MODE'] === 'navigate') {
                return true;
            }
        }
        
        // Check for PWA user agent additions
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/\(display-mode: standalone\)/', $userAgent)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get the operating system
     */
    public function getOS(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/Android/', $userAgent)) {
            return 'android';
        }
        
        if (preg_match('/iPhone|iPad|iPod/', $userAgent)) {
            return 'ios';
        }
        
        if (preg_match('/Windows Phone/', $userAgent)) {
            return 'windows_phone';
        }
        
        if (preg_match('/Windows/', $userAgent)) {
            return 'windows';
        }
        
        if (preg_match('/Mac/', $userAgent)) {
            return 'macos';
        }
        
        if (preg_match('/Linux/', $userAgent)) {
            return 'linux';
        }
        
        return 'unknown';
    }
    
    /**
     * Check user agent for mobile patterns
     */
    private function checkUserAgent(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        foreach ($this->mobileUserAgents as $mobileAgent) {
            if (stripos($userAgent, $mobileAgent) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for mobile-specific headers
     */
    private function checkMobileHeaders(): bool
    {
        foreach ($this->mobileHeaders as $header) {
            if (isset($_SERVER[$header])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check accept header for mobile content types
     */
    private function checkAcceptHeader(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        
        if (preg_match('/application\/x-obml2d|application\/vnd\.rim\.html|text\/vnd\.wap\.wml|application\/vnd\.wap\.xhtml\+xml/', $accept)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Set a cookie to remember the user's theme preference
     */
    public function setThemePreference(string $theme): void
    {
        setcookie('theme_preference', $theme, [
            'expires' => time() + (365 * 24 * 60 * 60), // 1 year
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    /**
     * Get the user's theme preference
     */
    public function getThemePreference(): ?string
    {
        return $_COOKIE['theme_preference'] ?? null;
    }
    
    /**
     * Determine which theme to use
     */
    public function determineTheme(): string
    {
        // Check for user preference first
        $preference = $this->getThemePreference();
        if ($preference) {
            return $preference;
        }
        
        // Auto-detect based on device
        return $this->isMobile() ? 'mobile' : 'desktop';
    }
}