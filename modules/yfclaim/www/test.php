<?php
echo "YFClaim test page working!<br>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Config path: " . __DIR__ . '/../../../config/database.php' . "<br>";
echo "Config exists: " . (file_exists(__DIR__ . '/../../../config/database.php') ? 'YES' : 'NO') . "<br>";
echo "Vendor path: " . __DIR__ . '/../../../vendor/autoload.php' . "<br>";
echo "Vendor exists: " . (file_exists(__DIR__ . '/../../../vendor/autoload.php') ? 'YES' : 'NO') . "<br>";
?>