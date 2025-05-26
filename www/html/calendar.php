<?php
// Calendar entry point
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Get Google Maps API key from environment
$googleMapsApiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';

// Set script tag to be added
$additionalScripts = '<script src="/calendar-init.js"></script>';

// Include the calendar template
include __DIR__ . '/templates/calendar/calendar.php';
?>