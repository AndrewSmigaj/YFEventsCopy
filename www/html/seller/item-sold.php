<?php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

// Check authentication
if (!isset($_SESSION['seller_id'])) {
    header('Location: /seller/login.php');
    exit;
}

$sellerId = $_SESSION['seller_id'];
$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify item belongs to seller and mark as sold
try {
    $updateStmt = $pdo->prepare("
        UPDATE yfc_items i
        JOIN yfc_sales s ON i.sale_id = s.id
        SET i.status = 'sold'
        WHERE i.id = ? AND s.seller_id = ? AND i.status = 'active'
    ");
    $updateStmt->execute([$itemId, $sellerId]);
    
    if ($updateStmt->rowCount() > 0) {
        $_SESSION['success'] = 'Item marked as sold!';
    } else {
        $_SESSION['error'] = 'Unable to update item. It may not exist or already be sold.';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error updating item status.';
}

// Redirect back to items page
header('Location: /seller/items.php');
exit;