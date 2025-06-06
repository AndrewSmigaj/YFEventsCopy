<?php
// www/html/ajax/auth/logout.php
header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../src/Utils/Auth.php';

use YFEvents\Utils\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$auth = new Auth($pdo);
$result = $auth->logout();
echo json_encode($result);
?>