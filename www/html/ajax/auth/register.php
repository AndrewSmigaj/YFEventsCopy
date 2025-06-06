<?php
// www/html/ajax/auth/register.php
header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../src/Utils/Auth.php';

use YFEvents\Utils\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$auth = new Auth($pdo);

$data = [
    'username' => $_POST['username'] ?? '',
    'email' => $_POST['email'] ?? '',
    'password' => $_POST['password'] ?? '',
    'first_name' => $_POST['first_name'] ?? '',
    'last_name' => $_POST['last_name'] ?? ''
];

$result = $auth->register($data);
echo json_encode($result);
?>