<?php
echo "Testing database connection...<br>";

try {
    require_once __DIR__ . '/../../../config/database.php';
    echo "Database config loaded successfully<br>";
    echo "PDO connection: " . (isset($pdo) ? 'Connected' : 'NOT CONNECTED') . "<br>";
    
    if (isset($pdo)) {
        $result = $pdo->query("SELECT 1 as test")->fetch();
        echo "Test query result: " . $result['test'] . "<br>";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

echo "<br>Testing autoloader...<br>";
try {
    require_once __DIR__ . '/../../../vendor/autoload.php';
    echo "Autoloader loaded successfully<br>";
} catch (Exception $e) {
    echo "Autoloader error: " . $e->getMessage() . "<br>";
}

echo "<br>Testing YFClaim models...<br>";
try {
    use YFEvents\Modules\YFClaim\Models\SaleModel;
    echo "SaleModel class loaded successfully<br>";
    
    $saleModel = new SaleModel($pdo);
    echo "SaleModel instantiated successfully<br>";
    
} catch (Exception $e) {
    echo "Model error: " . $e->getMessage() . "<br>";
}
?>