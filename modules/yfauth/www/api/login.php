<?php
session_start();

// Load dependencies
require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../config/database.php';
require_once __DIR__ . '/../../src/Services/AuthService.php';

use YFEvents\Modules\YFAuth\Services\AuthService;

// Initialize auth service
$auth = new AuthService($db);

// Handle login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username and password are required']);
        exit;
    }
    
    $result = $auth->authenticate($username, $password);
    
    if ($result['success']) {
        // Set session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['username'] = $result['user']['username'];
        $_SESSION['email'] = $result['user']['email'];
        $_SESSION['roles'] = $result['user']['roles'];
        $_SESSION['permissions'] = $result['user']['permissions'];
        $_SESSION['auth_session_id'] = $result['session_id'];
        $_SESSION['login_time'] = time();
        
        // Check if user has admin access
        $hasAdminAccess = false;
        foreach ($result['user']['roles'] as $role) {
            if (in_array($role['name'], ['super_admin', 'calendar_admin', 'calendar_moderator', 'shop_moderator'])) {
                $hasAdminAccess = true;
                break;
            }
        }
        
        echo json_encode([
            'success' => true,
            'user' => $result['user'],
            'has_admin_access' => $hasAdminAccess,
            'redirect' => $hasAdminAccess ? '/admin/' : '/'
        ]);
    } else {
        echo json_encode($result);
    }
    exit;
}

// Handle logout request
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
    if (isset($_SESSION['auth_session_id'])) {
        $auth->logout($_SESSION['auth_session_id']);
    }
    
    session_destroy();
    
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Location: /admin/login.php');
    }
    exit;
}