<?php
/**
 * Authentication check specifically for admin pages
 * Requires admin or super_admin role
 * 
 * Usage:
 *   require_once __DIR__ . '/../includes/admin_auth_required.php';
 */

$required_role = ['admin', 'super_admin'];
require_once __DIR__ . '/auth_required.php';