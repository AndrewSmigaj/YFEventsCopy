<?php
session_start();

echo "Testing Seller Session\n";
echo "=====================\n\n";

echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n\n";

echo "Session Contents:\n";
print_r($_SESSION);

echo "\n\nAuth-related sessions:\n";
echo "auth['user_id']: " . ($_SESSION['auth']['user_id'] ?? 'not set') . "\n";
echo "auth['role']: " . ($_SESSION['auth']['role'] ?? 'not set') . "\n";
echo "claim_seller_id: " . ($_SESSION['claim_seller_id'] ?? 'not set') . "\n";
echo "yfclaim_seller_id: " . ($_SESSION['yfclaim_seller_id'] ?? 'not set') . "\n";
echo "yfclaim_seller_user_id: " . ($_SESSION['yfclaim_seller_user_id'] ?? 'not set') . "\n";
echo "claim_seller_user_id: " . ($_SESSION['claim_seller_user_id'] ?? 'not set') . "\n";