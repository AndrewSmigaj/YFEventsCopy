<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "Starting debug...<br>";

try {
    echo "1. Loading config...<br>";
    require_once __DIR__ . '/../../../config/database.php';
    echo "2. Config loaded<br>";
} catch (Throwable $e) {
    echo "Config error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    exit;
}

try {
    echo "3. Loading autoloader...<br>";
    require_once __DIR__ . '/../../../vendor/autoload.php';
    echo "4. Autoloader loaded<br>";
} catch (Throwable $e) {
    echo "Autoloader error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    exit;
}

try {
    echo "5. Loading models...<br>";
    use YFEvents\Modules\YFClaim\Models\SaleModel;
    echo "6. SaleModel loaded<br>";
} catch (Throwable $e) {
    echo "Model error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    exit;
}

echo "7. All components loaded successfully!<br>";
?>