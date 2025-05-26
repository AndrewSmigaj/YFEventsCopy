<?php
// Event submission page
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config/database.php';

// Get Google Maps API key from environment
$googleMapsApiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';

// Include the event submission template
include dirname(dirname(__DIR__)) . '/templates/calendar/event-submit.php';
?>