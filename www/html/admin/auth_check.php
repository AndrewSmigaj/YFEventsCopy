<?php
// Admin authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: /admin/login.php');
    exit;
}

// Optional: Check for admin role specifically
if (isset($requireAdmin) && $requireAdmin === true) {
    if ($_SESSION['user_role'] !== 'admin') {
        header('HTTP/1.1 403 Forbidden');
        echo "Access denied. Admin privileges required.";
        exit;
    }
}