#!/usr/bin/env php
<?php

// Generate SVG placeholder icons for PWA
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$iconDir = __DIR__ . '/../public/assets/communication/icons/';

if (!is_dir($iconDir)) {
    mkdir($iconDir, 0755, true);
}

// SVG template
$svgTemplate = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="%d" height="%d" viewBox="0 0 %d %d" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%%" height="100%%" fill="#007bff"/>
    <text x="50%%" y="50%%" font-family="Arial, sans-serif" font-size="%d" font-weight="bold" 
          fill="white" text-anchor="middle" dominant-baseline="middle">YF</text>
</svg>';

foreach ($sizes as $size) {
    $fontSize = $size * 0.3;
    $svg = sprintf($svgTemplate, $size, $size, $size, $size, $fontSize);
    
    // Save as SVG (browsers can use SVG for icons)
    file_put_contents($iconDir . "icon-{$size}x{$size}.svg", $svg);
    echo "Created icon-{$size}x{$size}.svg\n";
    
    // Also create a simple HTML file that renders as PNG alternative
    $html = "<!DOCTYPE html>
<html>
<head>
<style>
body { margin: 0; padding: 0; }
.icon { width: {$size}px; height: {$size}px; background: #007bff; color: white; 
        display: flex; align-items: center; justify-content: center; 
        font-family: Arial; font-size: {$fontSize}px; font-weight: bold; }
</style>
</head>
<body><div class='icon'>YF</div></body>
</html>";
    
    file_put_contents($iconDir . "icon-{$size}x{$size}.html", $html);
}

// Create PNG placeholders (1x1 transparent pixel)
$pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
foreach ($sizes as $size) {
    file_put_contents($iconDir . "icon-{$size}x{$size}.png", $pngData);
    echo "Created placeholder icon-{$size}x{$size}.png\n";
}

// Additional icons
file_put_contents($iconDir . "badge-72x72.png", $pngData);
file_put_contents($iconDir . "general-96x96.png", $pngData);
file_put_contents($iconDir . "announce-96x96.png", $pngData);

echo "Icon generation complete!\n";