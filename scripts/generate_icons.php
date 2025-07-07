#!/usr/bin/env php
<?php

// Generate placeholder icons for PWA
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$iconDir = __DIR__ . '/../public/assets/communication/icons/';

if (!is_dir($iconDir)) {
    mkdir($iconDir, 0755, true);
}

foreach ($sizes as $size) {
    // Create a simple colored square as placeholder
    $image = imagecreatetruecolor($size, $size);
    
    // Background color (Bootstrap primary blue)
    $bgColor = imagecolorallocate($image, 0, 123, 255);
    imagefill($image, 0, 0, $bgColor);
    
    // Add text
    $textColor = imagecolorallocate($image, 255, 255, 255);
    $fontSize = $size / 8;
    $text = 'YF';
    
    // Center the text
    $bbox = imagettfbbox($fontSize, 0, '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', $text);
    $x = ceil(($size - $bbox[2]) / 2);
    $y = ceil(($size - $bbox[5]) / 2);
    
    // Try to use a font, fallback to imagestring if not available
    if (file_exists('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf')) {
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', $text);
    } else {
        // Fallback to built-in font
        $font = 5; // Largest built-in font
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        $x = ($size - $textWidth) / 2;
        $y = ($size - $textHeight) / 2;
        imagestring($image, $font, $x, $y, $text, $textColor);
    }
    
    // Save the image
    imagepng($image, $iconDir . "icon-{$size}x{$size}.png");
    imagedestroy($image);
    
    echo "Created icon-{$size}x{$size}.png\n";
}

// Create additional icons
// Badge icon (smaller)
$badge = imagecreatetruecolor(72, 72);
$bgColor = imagecolorallocate($badge, 0, 123, 255);
imagefill($badge, 0, 0, $bgColor);
imagepng($badge, $iconDir . "badge-72x72.png");
echo "Created badge-72x72.png\n";

// Channel icons
$channelIcons = ['general' => [0, 123, 255], 'announce' => [255, 193, 7]];
foreach ($channelIcons as $name => $rgb) {
    $icon = imagecreatetruecolor(96, 96);
    $bgColor = imagecolorallocate($icon, $rgb[0], $rgb[1], $rgb[2]);
    imagefill($icon, 0, 0, $bgColor);
    imagepng($icon, $iconDir . "{$name}-96x96.png");
    echo "Created {$name}-96x96.png\n";
}

echo "Icon generation complete!\n";