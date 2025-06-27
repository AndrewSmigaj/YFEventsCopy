<?php
session_start();
$_SESSION['seller_id'] = 15;
$_SESSION['seller_name'] = 'Yakima Finds';
$_SESSION['seller_email'] = 'yakimafinds@gmail.com';

echo "Session set. You can now access the dashboard.";
echo "<br><a href='/seller/dashboard.php'>Go to Dashboard</a>";
?>