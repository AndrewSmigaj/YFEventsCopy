<?php
namespace YFEvents\Modules\YFClaim\Utils;

class QRCodeGenerator {
    /**
     * Generate QR code URL using Google Charts API
     * This is a simple implementation using Google's service
     * For production, consider using a PHP library like endroid/qr-code
     */
    public static function generateQRCodeURL($data, $size = 200) {
        $encodedData = urlencode($data);
        return "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$encodedData}";
    }
    
    /**
     * Generate QR code for an item
     */
    public static function generateItemQR($itemId, $qrCode, $size = 200) {
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
        $url = $baseUrl . "/modules/yfclaim/www/item-quick.php?qr=" . $qrCode;
        return self::generateQRCodeURL($url, $size);
    }
    
    /**
     * Generate QR code for a sale
     */
    public static function generateSaleQR($saleId, $qrCode, $size = 200) {
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
        $url = $baseUrl . "/modules/yfclaim/www/sale-quick.php?qr=" . $qrCode;
        return self::generateQRCodeURL($url, $size);
    }
    
    /**
     * Generate access code QR for a sale
     */
    public static function generateAccessCodeQR($accessCode, $size = 200) {
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
        $url = $baseUrl . "/modules/yfclaim/www/sale-quick.php?access=" . $accessCode;
        return self::generateQRCodeURL($url, $size);
    }
    
    /**
     * Generate unique QR code string
     */
    public static function generateUniqueCode($prefix = 'QR') {
        return $prefix . strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
    }
}