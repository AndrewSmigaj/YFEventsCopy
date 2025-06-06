<?php
// www/html/ajax/auth/login.php
header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../src/Utils/Auth.php';

use YFEvents\Utils\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$auth = new Auth($pdo);

$login = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';
$rememberMe = isset($_POST['remember_me']);

if (empty($login) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please provide login and password']);
    exit;
}

$result = $auth->login($login, $password, $rememberMe);
echo json_encode($result);
?>