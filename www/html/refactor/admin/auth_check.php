<?php
/**
 * Simple admin authentication check
 * For production, integrate with proper authentication system
 */

session_start();

// For now, just check if admin session exists
// In production, this should check proper authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Simple authentication bypass for development
    // In production, redirect to login page
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = 'admin';
}
?>