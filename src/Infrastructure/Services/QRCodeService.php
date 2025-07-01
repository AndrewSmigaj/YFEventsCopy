<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Services;

use YFEvents\Infrastructure\Config\ConfigurationInterface;

class QRCodeService
{
    private string $baseUrl;
    private int $size;
    
    public function __construct(
        private readonly ConfigurationInterface $config
    ) {
        $this->baseUrl = $config->get('app.url', 'https://localhost');
        $this->size = $config->get('qr.size', 300);
    }

    /**
     * Generate QR code for sale
     */
    public function generateForSale(int $saleId, string $accessCode): string
    {
        $url = $this->baseUrl . '/claims/sale/' . $saleId . '?code=' . $accessCode;
        
        return $this->generateQRCode($url);
    }

    /**
     * Generate QR code for item
     */
    public function generateForItem(int $itemId): string
    {
        $url = $this->baseUrl . '/claims/item/' . $itemId;
        
        return $this->generateQRCode($url);
    }

    /**
     * Generate QR code using Google Charts API
     */
    private function generateQRCode(string $data): string
    {
        $encodedData = urlencode($data);
        
        return sprintf(
            'https://chart.googleapis.com/chart?chs=%dx%d&cht=qr&chl=%s&choe=UTF-8',
            $this->size,
            $this->size,
            $encodedData
        );
    }

    /**
     * Generate QR code and save to file
     */
    public function generateAndSave(string $data, string $filename): bool
    {
        $qrUrl = $this->generateQRCode($data);
        
        // Download and save the image
        $imageData = file_get_contents($qrUrl);
        
        if ($imageData === false) {
            return false;
        }
        
        return file_put_contents($filename, $imageData) !== false;
    }

    /**
     * Generate QR code as base64
     */
    public function generateAsBase64(string $data): string
    {
        $qrUrl = $this->generateQRCode($data);
        $imageData = file_get_contents($qrUrl);
        
        if ($imageData === false) {
            throw new \RuntimeException('Failed to generate QR code');
        }
        
        return 'data:image/png;base64,' . base64_encode($imageData);
    }
}