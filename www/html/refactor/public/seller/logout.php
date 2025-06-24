<?php
// YFClaim Seller Logout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear seller session data
unset($_SESSION['yfclaim_seller_id']);
unset($_SESSION['yfclaim_seller_name']);

// Destroy the session
session_destroy();

// Redirect to seller login page
header('Location: /refactor/seller/login');
exit;