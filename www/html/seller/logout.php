<?php
/**
 * Seller Logout
 */

session_start();
session_destroy();

header('Location: /seller/login.php');
exit;