<?php

/**
 * YFTheme API Endpoints
 * Handles theme editor operations
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

use YFEvents\Modules\YFTheme\Models\ThemeModel;
use YFEvents\Modules\YFTheme\Services\ThemeService;
use YFEvents\Modules\YFAuth\Middleware\AuthMiddleware;

global $pdo;

// Initialize services
$themeModel = new ThemeModel($pdo);
$themeService = new ThemeService($pdo);
$authMiddleware = new AuthMiddleware($pdo);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remove base path parts (modules/yftheme/api)
$action = $pathParts[3] ?? '';
$id = $pathParts[4] ?? null;

try {
    switch ($action) {
        case 'theme':
            handleThemeRequest($method, $id);
            break;
            
        case 'variables':
            handleVariablesRequest($method, $id);
            break;
            
        case 'presets':
            handlePresetsRequest($method, $id);
            break;
            
        case 'preview':
            handlePreviewRequest($method);
            break;
            
        case 'export':
            handleExportRequest($method, $id);
            break;
            
        case 'import':
            handleImportRequest($method);
            break;
            
        case 'history':
            handleHistoryRequest($method, $id);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    error_log("YFTheme API Error: " . $e->getMessage());
}

/**
 * Handle theme CSS requests
 */
function handleThemeRequest($method, $id)
{
    global $themeService;
    
    if ($method === 'GET') {
        header('Content-Type: text/css');
        
        // Get scope from query parameters
        $scope = [];
        if (isset($_GET['page'])) $scope['page'] = $_GET['page'];
        if (isset($_GET['module'])) $scope['module'] = $_GET['module'];
        if (isset($_GET['user_group'])) $scope['user_group'] = $_GET['user_group'];
        
        echo $themeService->getThemeCSS($scope);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle variables requests
 */
function handleVariablesRequest($method, $id)
{
    global $themeModel, $themeService, $authMiddleware;
    
    switch ($method) {
        case 'GET':
            $authMiddleware->apiAuth(function($user) use ($themeModel, $id) {
                if ($id) {
                    // Get single variable
                    $variable = $themeModel->getVariableByName($id);
                    if ($variable) {
                        echo json_encode($variable);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Variable not found']);
                    }
                } else {
                    // Get all variables
                    $includeAdvanced = isset($_GET['advanced']) && $_GET['advanced'] === 'true';
                    $variables = $themeModel->getVariablesByCategory($includeAdvanced);
                    echo json_encode($variables);
                }
            }, ['theme.view']);
            break;
            
        case 'PUT':
            $authMiddleware->apiAuth(function($user) use ($themeModel, $themeService, $id) {
                $input = json_decode(file_get_contents('php://input'), true);
                
                if ($id && is_numeric($id)) {
                    // Update single variable
                    $value = $input['value'] ?? '';
                    $result = $themeModel->updateVariable($id, $value, $user->id);
                    
                    if ($result) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'Failed to update variable']);
                    }
                } else {
                    // Bulk update
                    $variables = $input['variables'] ?? [];
                    $result = $themeService->applyThemeUpdates($variables, $user->id);
                    
                    if ($result) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'Failed to update variables']);
                    }
                }
            }, ['theme.edit']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

/**
 * Handle presets requests
 */
function handlePresetsRequest($method, $id)
{
    global $themeModel, $authMiddleware;
    
    switch ($method) {
        case 'GET':
            $authMiddleware->apiAuth(function($user) use ($themeModel, $id) {
                if ($id) {
                    // Get single preset
                    $preset = $themeModel->getPreset($id);
                    if ($preset) {
                        echo json_encode($preset);
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'Preset not found']);
                    }
                } else {
                    // Get all presets
                    $presets = $themeModel->getPresets();
                    echo json_encode($presets);
                }
            }, ['theme.view']);
            break;
            
        case 'POST':
            $authMiddleware->apiAuth(function($user) use ($themeModel) {
                $input = json_decode(file_get_contents('php://input'), true);
                
                $presetId = $themeModel->createPreset($input, $user->id);
                
                if ($presetId) {
                    echo json_encode(['id' => $presetId, 'success' => true]);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Failed to create preset']);
                }
            }, ['theme.create']);
            break;
            
        case 'PUT':
            $authMiddleware->apiAuth(function($user) use ($themeModel, $id) {
                if (!$id || !is_numeric($id)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid preset ID']);
                    return;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (isset($input['apply']) && $input['apply']) {
                    // Apply preset
                    $result = $themeModel->applyPreset($id, $user->id);
                    
                    if ($result) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'Failed to apply preset']);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid operation']);
                }
            }, ['theme.apply']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

/**
 * Handle preview requests
 */
function handlePreviewRequest($method)
{
    global $themeService, $authMiddleware;
    
    if ($method === 'POST') {
        $authMiddleware->apiAuth(function($user) use ($themeService) {
            $input = json_decode(file_get_contents('php://input'), true);
            $changes = $input['changes'] ?? [];
            
            $preview = $themeService->generateLivePreview($changes);
            echo json_encode($preview);
        }, ['theme.view']);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle export requests
 */
function handleExportRequest($method, $id)
{
    global $themeModel, $authMiddleware;
    
    if ($method === 'GET') {
        $authMiddleware->apiAuth(function($user) use ($themeModel, $id) {
            $presetId = $id && is_numeric($id) ? $id : null;
            $theme = $themeModel->exportTheme($presetId);
            
            // Set download headers
            $filename = $presetId ? "theme_preset_{$presetId}.json" : "current_theme.json";
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            echo json_encode($theme, JSON_PRETTY_PRINT);
        }, ['theme.export']);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle import requests
 */
function handleImportRequest($method)
{
    global $themeModel, $authMiddleware;
    
    if ($method === 'POST') {
        $authMiddleware->apiAuth(function($user) use ($themeModel) {
            if (isset($_FILES['theme_file'])) {
                // File upload
                $file = $_FILES['theme_file'];
                $content = file_get_contents($file['tmp_name']);
                $data = json_decode($content, true);
                
                if (!$data) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid JSON file']);
                    return;
                }
            } else {
                // JSON in request body
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!$data) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid JSON data']);
                    return;
                }
            }
            
            $result = $themeModel->importTheme($data, $user->id);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Failed to import theme']);
            }
        }, ['theme.create']);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle history requests
 */
function handleHistoryRequest($method, $id)
{
    global $themeModel, $authMiddleware;
    
    switch ($method) {
        case 'GET':
            $authMiddleware->apiAuth(function($user) use ($themeModel) {
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
                $history = $themeModel->getHistory($limit);
                echo json_encode($history);
            }, ['theme.view']);
            break;
            
        case 'POST':
            $authMiddleware->apiAuth(function($user) use ($themeModel, $id) {
                if (!$id || !is_numeric($id)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid history ID']);
                    return;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (isset($input['rollback']) && $input['rollback']) {
                    $result = $themeModel->rollbackToHistory($id, $user->id);
                    
                    if ($result) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'Failed to rollback']);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid operation']);
                }
            }, ['theme.edit']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

// Special endpoint for serving theme CSS to public
if ($action === '' && $method === 'GET') {
    // This serves the main theme.css file
    header('Content-Type: text/css');
    header('Cache-Control: public, max-age=3600');
    
    $scope = $themeService->getCurrentScope();
    echo $themeService->getThemeCSS($scope);
}