<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../src/Models/ShopModel.php';
require_once __DIR__ . '/../../../../src/Utils/GeocodeService.php';

use YFEvents\Models\ShopModel;
use YFEvents\Utils\GeocodeService;

try {
    // Validate request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $format = $_POST['format'] ?? 'csv';
    $file = $_FILES['file'];

    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File size must be less than 10MB');
    }

    // Validate file type
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['csv', 'json'])) {
        throw new Exception('Only CSV and JSON files are allowed');
    }

    if ($format !== $extension) {
        throw new Exception("File format mismatch. Selected $format but uploaded $extension file");
    }

    // Read file content
    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        throw new Exception('Failed to read file content');
    }

    // Initialize services
    $shopModel = new ShopModel($db);
    $geocodeService = new GeocodeService();

    $importedCount = 0;
    $errors = [];

    if ($format === 'csv') {
        $importedCount = importFromCSV($content, $shopModel, $geocodeService, $errors);
    } else {
        $importedCount = importFromJSON($content, $shopModel, $geocodeService, $errors);
    }

    $response = [
        'success' => true,
        'imported' => $importedCount,
        'errors' => $errors
    ];

    if (!empty($errors)) {
        $response['message'] = "Imported $importedCount shops with " . count($errors) . " errors/warnings";
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function importFromCSV($content, $shopModel, $geocodeService, &$errors)
{
    $lines = explode("\n", $content);
    if (empty($lines)) {
        throw new Exception('CSV file is empty');
    }

    // Parse header
    $header = str_getcsv(trim($lines[0]));
    $header = array_map('trim', $header);
    
    // Map CSV columns to database fields
    $fieldMapping = getFieldMapping($header);
    
    if (empty($fieldMapping['name'])) {
        throw new Exception('CSV must contain a "name" column');
    }

    $importedCount = 0;
    
    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;

        try {
            $values = str_getcsv($line);
            $shopData = [];

            // Map CSV values to shop fields
            foreach ($fieldMapping as $dbField => $csvIndex) {
                if ($csvIndex !== null && isset($values[$csvIndex])) {
                    $value = trim($values[$csvIndex]);
                    if (!empty($value)) {
                        $shopData[$dbField] = $value;
                    }
                }
            }

            // Validate required fields
            if (empty($shopData['name'])) {
                $errors[] = "Row $i: Missing shop name";
                continue;
            }

            // Process the shop data
            $processedData = processShopData($shopData, $geocodeService);
            
            // Check for duplicates
            if (shopExists($shopModel, $processedData['name'], $processedData['address'] ?? null)) {
                $errors[] = "Row $i: Shop '{$processedData['name']}' already exists";
                continue;
            }

            // Insert shop
            $shopId = $shopModel->create($processedData);
            if ($shopId) {
                $importedCount++;
            } else {
                $errors[] = "Row $i: Failed to create shop '{$processedData['name']}'";
            }

        } catch (Exception $e) {
            $errors[] = "Row $i: " . $e->getMessage();
        }
    }

    return $importedCount;
}

function importFromJSON($content, $shopModel, $geocodeService, &$errors)
{
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format: ' . json_last_error_msg());
    }

    // Handle both single object and array of objects
    if (!is_array($data)) {
        throw new Exception('JSON must contain an array of shops or a single shop object');
    }

    // If it's a single object (not an array of objects), wrap it
    if (!isset($data[0])) {
        $data = [$data];
    }

    $importedCount = 0;

    foreach ($data as $index => $shopData) {
        try {
            // Validate required fields
            if (empty($shopData['name'])) {
                $errors[] = "Item $index: Missing shop name";
                continue;
            }

            // Process the shop data
            $processedData = processShopData($shopData, $geocodeService);
            
            // Check for duplicates
            if (shopExists($shopModel, $processedData['name'], $processedData['address'] ?? null)) {
                $errors[] = "Item $index: Shop '{$processedData['name']}' already exists";
                continue;
            }

            // Insert shop
            $shopId = $shopModel->create($processedData);
            if ($shopId) {
                $importedCount++;
            } else {
                $errors[] = "Item $index: Failed to create shop '{$processedData['name']}'";
            }

        } catch (Exception $e) {
            $errors[] = "Item $index: " . $e->getMessage();
        }
    }

    return $importedCount;
}

function getFieldMapping($header)
{
    $mapping = [
        'name' => null,
        'description' => null,
        'address' => null,
        'phone' => null,
        'email' => null,
        'website' => null,
        'category_id' => null,
        'operating_hours' => null,
        'payment_methods' => null,
        'amenities' => null,
        'featured' => null,
        'status' => null
    ];

    // Common column name variations
    $columnMap = [
        'name' => ['name', 'shop_name', 'business_name', 'title'],
        'description' => ['description', 'desc', 'about', 'summary'],
        'address' => ['address', 'location', 'street_address', 'full_address'],
        'phone' => ['phone', 'telephone', 'phone_number', 'contact_phone'],
        'email' => ['email', 'email_address', 'contact_email'],
        'website' => ['website', 'url', 'web_address', 'homepage'],
        'category_id' => ['category_id', 'category', 'type', 'business_type'],
        'operating_hours' => ['operating_hours', 'hours', 'business_hours', 'schedule'],
        'payment_methods' => ['payment_methods', 'payments', 'accepted_payments'],
        'amenities' => ['amenities', 'features', 'services'],
        'featured' => ['featured', 'highlight', 'promoted'],
        'status' => ['status', 'active', 'state']
    ];

    foreach ($header as $index => $column) {
        $column = strtolower(trim($column));
        foreach ($columnMap as $dbField => $variations) {
            if (in_array($column, $variations)) {
                $mapping[$dbField] = $index;
                break;
            }
        }
    }

    return $mapping;
}

function processShopData($data, $geocodeService)
{
    $processed = [];

    // Required fields
    $processed['name'] = $data['name'];
    
    // Optional fields with defaults
    $processed['description'] = $data['description'] ?? null;
    $processed['address'] = $data['address'] ?? null;
    $processed['phone'] = $data['phone'] ?? null;
    $processed['email'] = $data['email'] ?? null;
    $processed['website'] = $data['website'] ?? null;
    
    // Handle category - convert name to ID if needed
    if (isset($data['category_id'])) {
        if (is_numeric($data['category_id'])) {
            $processed['category_id'] = (int)$data['category_id'];
        } else {
            // Try to find category by name
            $processed['category_id'] = findCategoryByName($data['category_id']);
        }
    }

    // Handle JSON fields
    if (isset($data['operating_hours'])) {
        $processed['operating_hours'] = is_string($data['operating_hours']) 
            ? json_encode(['description' => $data['operating_hours']])
            : json_encode($data['operating_hours']);
    }

    if (isset($data['payment_methods'])) {
        $processed['payment_methods'] = is_array($data['payment_methods'])
            ? json_encode($data['payment_methods'])
            : json_encode(explode(',', $data['payment_methods']));
    }

    if (isset($data['amenities'])) {
        $processed['amenities'] = is_array($data['amenities'])
            ? json_encode($data['amenities'])
            : json_encode(explode(',', $data['amenities']));
    }

    // Handle boolean fields
    $processed['featured'] = isset($data['featured']) ? (bool)$data['featured'] : false;
    
    // Handle status
    $validStatuses = ['active', 'pending', 'inactive'];
    $processed['status'] = in_array($data['status'] ?? 'pending', $validStatuses) 
        ? $data['status'] : 'pending';

    // Geocode address if provided
    if (!empty($processed['address'])) {
        try {
            $coordinates = $geocodeService->geocodeAddress($processed['address']);
            if ($coordinates) {
                $processed['latitude'] = $coordinates['lat'];
                $processed['longitude'] = $coordinates['lng'];
            }
        } catch (Exception $e) {
            // Geocoding failed, continue without coordinates
        }
    }

    return $processed;
}

function findCategoryByName($categoryName)
{
    global $db;
    
    $stmt = $db->prepare("SELECT id FROM shop_categories WHERE name = ? OR slug = ?");
    $stmt->execute([$categoryName, strtolower(str_replace(' ', '-', $categoryName))]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['id'] : null;
}

function shopExists($shopModel, $name, $address = null)
{
    global $db;
    
    if ($address) {
        $stmt = $db->prepare("SELECT id FROM local_shops WHERE name = ? AND address = ?");
        $stmt->execute([$name, $address]);
    } else {
        $stmt = $db->prepare("SELECT id FROM local_shops WHERE name = ?");
        $stmt->execute([$name]);
    }
    
    return $stmt->fetch() !== false;
}