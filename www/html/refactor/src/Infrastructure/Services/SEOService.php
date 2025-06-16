<?php

declare(strict_types=1);

namespace YakimaFinds\Infrastructure\Services;

use PDO;
use Exception;

/**
 * SEO service for generating meta tags and social sharing
 */
class SEOService
{
    private PDO $pdo;
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get SEO settings for a specific page
     */
    public function getPageSEO(string $pageType, string $pageIdentifier = null): array
    {
        $cacheKey = $pageType . ':' . ($pageIdentifier ?? 'default');
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $sql = "
                SELECT * FROM seo_settings 
                WHERE page_type = ? AND (page_identifier = ? OR page_identifier IS NULL)
                ORDER BY page_identifier IS NULL ASC
                LIMIT 1
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$pageType, $pageIdentifier]);
            $seo = $stmt->fetch();
            
            if (!$seo) {
                // Return default SEO
                $seo = $this->getDefaultSEO($pageType);
            }
            
            // Decode JSON fields
            $seo['schema_markup'] = $seo['schema_markup'] ? json_decode($seo['schema_markup'], true) : null;
            $seo['custom_meta'] = $seo['custom_meta'] ? json_decode($seo['custom_meta'], true) : null;
            
            $this->cache[$cacheKey] = $seo;
            return $seo;
            
        } catch (Exception $e) {
            error_log("Error loading SEO settings: " . $e->getMessage());
            return $this->getDefaultSEO($pageType);
        }
    }

    /**
     * Generate HTML meta tags for a page
     */
    public function generateMetaTags(string $pageType, string $pageIdentifier = null, array $dynamicData = []): string
    {
        $seo = $this->getPageSEO($pageType, $pageIdentifier);
        
        // Replace dynamic placeholders
        $seo = $this->replaceDynamicContent($seo, $dynamicData);
        
        $html = '';
        
        // Basic meta tags
        if ($seo['meta_title']) {
            $html .= '<title>' . htmlspecialchars($seo['meta_title']) . '</title>' . "\n";
        }
        
        if ($seo['meta_description']) {
            $html .= '<meta name="description" content="' . htmlspecialchars($seo['meta_description']) . '">' . "\n";
        }
        
        if ($seo['meta_keywords']) {
            $html .= '<meta name="keywords" content="' . htmlspecialchars($seo['meta_keywords']) . '">' . "\n";
        }
        
        if ($seo['canonical_url']) {
            $html .= '<link rel="canonical" href="' . htmlspecialchars($seo['canonical_url']) . '">' . "\n";
        }
        
        if ($seo['robots']) {
            $html .= '<meta name="robots" content="' . htmlspecialchars($seo['robots']) . '">' . "\n";
        }
        
        // Open Graph tags
        $html .= '<meta property="og:type" content="' . htmlspecialchars($seo['og_type'] ?? 'website') . '">' . "\n";
        
        if ($seo['og_title'] ?? $seo['meta_title']) {
            $html .= '<meta property="og:title" content="' . htmlspecialchars($seo['og_title'] ?? $seo['meta_title']) . '">' . "\n";
        }
        
        if ($seo['og_description'] ?? $seo['meta_description']) {
            $html .= '<meta property="og:description" content="' . htmlspecialchars($seo['og_description'] ?? $seo['meta_description']) . '">' . "\n";
        }
        
        if ($seo['og_image']) {
            $html .= '<meta property="og:image" content="' . htmlspecialchars($seo['og_image']) . '">' . "\n";
        }
        
        // Twitter Card tags
        $html .= '<meta name="twitter:card" content="' . htmlspecialchars($seo['twitter_card'] ?? 'summary') . '">' . "\n";
        
        if ($seo['twitter_title'] ?? $seo['og_title'] ?? $seo['meta_title']) {
            $html .= '<meta name="twitter:title" content="' . htmlspecialchars($seo['twitter_title'] ?? $seo['og_title'] ?? $seo['meta_title']) . '">' . "\n";
        }
        
        if ($seo['twitter_description'] ?? $seo['og_description'] ?? $seo['meta_description']) {
            $html .= '<meta name="twitter:description" content="' . htmlspecialchars($seo['twitter_description'] ?? $seo['og_description'] ?? $seo['meta_description']) . '">' . "\n";
        }
        
        if ($seo['twitter_image'] ?? $seo['og_image']) {
            $html .= '<meta name="twitter:image" content="' . htmlspecialchars($seo['twitter_image'] ?? $seo['og_image']) . '">' . "\n";
        }
        
        // Custom meta tags
        if ($seo['custom_meta']) {
            foreach ($seo['custom_meta'] as $meta) {
                if (isset($meta['name']) && isset($meta['content'])) {
                    $html .= '<meta name="' . htmlspecialchars($meta['name']) . '" content="' . htmlspecialchars($meta['content']) . '">' . "\n";
                } elseif (isset($meta['property']) && isset($meta['content'])) {
                    $html .= '<meta property="' . htmlspecialchars($meta['property']) . '" content="' . htmlspecialchars($meta['content']) . '">' . "\n";
                }
            }
        }
        
        // Schema markup
        if ($seo['schema_markup']) {
            $html .= '<script type="application/ld+json">' . "\n";
            $html .= json_encode($seo['schema_markup'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $html .= "\n" . '</script>' . "\n";
        }
        
        return $html;
    }

    /**
     * Get social media sharing URLs
     */
    public function getSharingUrls(string $url, string $title, string $description = ''): array
    {
        $urls = [];
        
        // Get social media settings
        try {
            $sql = "SELECT platform, enabled, share_template FROM social_media_settings WHERE enabled = 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $platforms = $stmt->fetchAll();
            
            foreach ($platforms as $platform) {
                $shareUrl = $this->generateSharingUrl($platform['platform'], $url, $title, $description, $platform['share_template']);
                if ($shareUrl) {
                    $urls[$platform['platform']] = $shareUrl;
                }
            }
            
        } catch (Exception $e) {
            error_log("Error generating sharing URLs: " . $e->getMessage());
        }
        
        return $urls;
    }

    /**
     * Generate sharing buttons HTML
     */
    public function generateSharingButtons(string $url, string $title, string $description = '', array $options = []): string
    {
        $sharingUrls = $this->getSharingUrls($url, $title, $description);
        
        if (empty($sharingUrls)) {
            return '';
        }
        
        $buttonClass = $options['button_class'] ?? 'btn btn-sm btn-outline-secondary';
        $showText = $options['show_text'] ?? true;
        $openInPopup = $options['popup'] ?? true;
        
        $html = '<div class="social-sharing">';
        
        if ($showText) {
            $html .= '<span class="sharing-label">Share:</span> ';
        }
        
        foreach ($sharingUrls as $platform => $shareUrl) {
            $icon = $this->getPlatformIcon($platform);
            $name = ucfirst($platform);
            $onclick = $openInPopup ? "onclick=\"window.open('{$shareUrl}', '_blank', 'width=600,height=400'); return false;\"" : '';
            
            $html .= '<a href="' . htmlspecialchars($shareUrl) . '" ' . $onclick . ' class="' . $buttonClass . '" title="Share on ' . $name . '">';
            $html .= '<i class="' . $icon . '"></i>';
            
            if ($showText) {
                $html .= ' ' . $name;
            }
            
            $html .= '</a> ';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate structured data for events
     */
    public function generateEventSchema(array $event): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event['title'],
            'description' => $event['description'] ?? '',
            'startDate' => $event['start_date'],
            'endDate' => $event['end_date'] ?? $event['start_date'],
            'location' => [
                '@type' => 'Place',
                'name' => $event['location'] ?? '',
                'address' => $event['address'] ?? ''
            ],
            'organizer' => [
                '@type' => 'Organization',
                'name' => $event['organizer'] ?? 'YakimaFinds'
            ],
            'url' => $event['url'] ?? '',
            'image' => $event['image'] ?? ''
        ];
    }

    /**
     * Generate structured data for local businesses
     */
    public function generateBusinessSchema(array $business): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $business['name'],
            'description' => $business['description'] ?? '',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $business['address'] ?? '',
                'addressLocality' => $business['city'] ?? 'Yakima',
                'addressRegion' => $business['state'] ?? 'WA',
                'postalCode' => $business['zip'] ?? '',
                'addressCountry' => 'US'
            ],
            'telephone' => $business['phone'] ?? '',
            'url' => $business['website'] ?? '',
            'image' => $business['image'] ?? '',
            'priceRange' => $business['price_range'] ?? '',
            'openingHours' => $business['hours'] ?? []
        ];
    }

    /**
     * Private helper methods
     */
    private function getDefaultSEO(string $pageType): array
    {
        $defaults = [
            'home' => [
                'meta_title' => 'YakimaFinds - Local Events & Business Directory',
                'meta_description' => 'Discover local events, businesses, and estate sales in Yakima Valley.',
                'og_type' => 'website',
                'twitter_card' => 'summary_large_image',
                'robots' => 'index, follow'
            ],
            'events' => [
                'meta_title' => 'Events Calendar - YakimaFinds',
                'meta_description' => 'Browse upcoming events in Yakima Valley.',
                'og_type' => 'website',
                'twitter_card' => 'summary_large_image',
                'robots' => 'index, follow'
            ],
            'shops' => [
                'meta_title' => 'Local Business Directory - YakimaFinds',
                'meta_description' => 'Explore local businesses in Yakima Valley.',
                'og_type' => 'website',
                'twitter_card' => 'summary_large_image',
                'robots' => 'index, follow'
            ],
            'claims' => [
                'meta_title' => 'Estate Sales - YakimaFinds',
                'meta_description' => 'Browse current estate sales and make claims on items.',
                'og_type' => 'website',
                'twitter_card' => 'summary_large_image',
                'robots' => 'index, follow'
            ]
        ];
        
        return $defaults[$pageType] ?? $defaults['home'];
    }

    private function replaceDynamicContent(array $seo, array $data): array
    {
        $fields = ['meta_title', 'meta_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'];
        
        foreach ($fields as $field) {
            if (isset($seo[$field])) {
                $seo[$field] = $this->replacePlaceholders($seo[$field], $data);
            }
        }
        
        return $seo;
    }

    private function replacePlaceholders(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        
        return $text;
    }

    private function generateSharingUrl(string $platform, string $url, string $title, string $description, string $template = null): string
    {
        $encodedUrl = urlencode($url);
        $encodedTitle = urlencode($title);
        $encodedDescription = urlencode($description);
        
        switch ($platform) {
            case 'facebook':
                return "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}";
                
            case 'twitter':
                $text = $template ? str_replace(['{title}', '{url}'], [$title, $url], $template) : "{$title} {$url}";
                return "https://twitter.com/intent/tweet?text=" . urlencode($text);
                
            case 'linkedin':
                return "https://www.linkedin.com/sharing/share-offsite/?url={$encodedUrl}";
                
            case 'pinterest':
                return "https://pinterest.com/pin/create/button/?url={$encodedUrl}&description={$encodedTitle}";
                
            case 'whatsapp':
                $text = $template ? str_replace(['{title}', '{url}'], [$title, $url], $template) : "{$title} {$url}";
                return "https://wa.me/?text=" . urlencode($text);
                
            case 'email':
                $subject = urlencode("Check out: {$title}");
                $body = urlencode("I thought you might be interested in this: {$title}\n\n{$description}\n\n{$url}");
                return "mailto:?subject={$subject}&body={$body}";
                
            default:
                return '';
        }
    }

    private function getPlatformIcon(string $platform): string
    {
        $icons = [
            'facebook' => 'fab fa-facebook-f',
            'twitter' => 'fab fa-twitter',
            'linkedin' => 'fab fa-linkedin-in',
            'pinterest' => 'fab fa-pinterest-p',
            'instagram' => 'fab fa-instagram',
            'whatsapp' => 'fab fa-whatsapp',
            'email' => 'fas fa-envelope'
        ];
        
        return $icons[$platform] ?? 'fas fa-share';
    }
}