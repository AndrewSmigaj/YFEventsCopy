<?php
// YFClaim Buyer Logout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear buyer session data
unset($_SESSION['buyer_id']);
unset($_SESSION['buyer_name']);
unset($_SESSION['buyer_email']);
unset($_SESSION['buyer_phone']);

// Destroy the session
session_destroy();

// Redirect to buyer auth page
header('Location: /refactor/buyer/auth');
exit;