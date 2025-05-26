<?php
session_start();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: /admin/login.php');
exit;
?>