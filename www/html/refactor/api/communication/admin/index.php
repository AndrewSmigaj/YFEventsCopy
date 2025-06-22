<?php
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/bootstrap.php';

// Check admin privileges
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Set JSON headers
header('Content-Type: application/json');

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_SERVER['PATH_INFO'] ?? '', '/');
$segments = explode('/', $path);

// Get container
$container = \YFEvents\Infrastructure\Container\Container::getInstance();
$db = $container->resolve(\YFEvents\Infrastructure\Database\Connection::class);

// Route the request
try {
    switch ($segments[0]) {
        case 'statistics':
            handleStatistics($db);
            break;
            
        case 'activity':
            if (isset($segments[1]) && $segments[1] === 'recent') {
                handleRecentActivity($db);
            }
            break;
            
        case 'users':
            handleUsers($method, $segments, $db);
            break;
            
        case 'channels':
            handleChannels($method, $segments, $db);
            break;
            
        case 'messages':
            handleMessages($method, $segments, $db);
            break;
            
        case 'settings':
            handleSettings($method, $db);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// Handler functions
function handleStatistics($db) {
    $stats = [];
    
    // Total users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $stats['totalUsers'] = $stmt->fetch()['count'];
    
    // Active channels
    $stmt = $db->query("SELECT COUNT(*) as count FROM communication_channels WHERE is_archived = 0");
    $stats['activeChannels'] = $stmt->fetch()['count'];
    
    // Messages today
    $stmt = $db->query("SELECT COUNT(*) as count FROM communication_messages 
                        WHERE DATE(created_at) = CURDATE() AND is_deleted = 0");
    $stats['messagesToday'] = $stmt->fetch()['count'];
    
    // Active users (last 15 minutes)
    $stmt = $db->query("SELECT COUNT(DISTINCT user_id) as count 
                        FROM communication_messages 
                        WHERE created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stats['activeNow'] = $stmt->fetch()['count'];
    
    echo json_encode($stats);
}

function handleRecentActivity($db) {
    // For now, return recent messages as activity
    $stmt = $db->prepare("
        SELECT m.*, u.username as user_name, c.name as channel_name
        FROM communication_messages m
        JOIN users u ON m.user_id = u.id
        JOIN communication_channels c ON m.channel_id = c.id
        WHERE m.is_deleted = 0
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    
    $activities = [];
    while ($row = $stmt->fetch()) {
        $activities[] = [
            'description' => 'New message in #' . $row['channel_name'],
            'details' => substr($row['content'], 0, 100) . (strlen($row['content']) > 100 ? '...' : ''),
            'user_name' => $row['user_name'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode($activities);
}

function handleUsers($method, $segments, $db) {
    $userId = isset($segments[1]) ? (int)$segments[1] : null;
    
    switch ($method) {
        case 'GET':
            if ($userId) {
                // Get single user
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user) {
                    unset($user['password_hash']); // Never send password
                    echo json_encode($user);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                }
            } else {
                // Get all users with filters
                $sql = "SELECT id, username, email, first_name, last_name, role, 
                               created_at, last_login as last_active_at, status
                        FROM users WHERE 1=1";
                $params = [];
                
                if (isset($_GET['search'])) {
                    $sql .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
                    $search = '%' . $_GET['search'] . '%';
                    $params = array_merge($params, [$search, $search, $search, $search]);
                }
                
                if (isset($_GET['role'])) {
                    $sql .= " AND role = ?";
                    $params[] = $_GET['role'];
                }
                
                if (isset($_GET['status'])) {
                    $sql .= " AND status = ?";
                    $params[] = $_GET['status'];
                }
                
                $sql .= " ORDER BY created_at DESC";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $users = $stmt->fetchAll();
                echo json_encode($users);
            }
            break;
            
        case 'POST':
            // Create new user
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            // Check if user exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$data['username'], $data['email']]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Username or email already exists']);
                return;
            }
            
            // Create user
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, role, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                $data['role'] ?? 'user'
            ]);
            
            echo json_encode(['id' => $db->lastInsertId(), 'success' => true]);
            break;
            
        case 'PUT':
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
                return;
            }
            
            if (isset($segments[2]) && $segments[2] === 'status') {
                // Update user status
                $data = json_decode(file_get_contents('php://input'), true);
                $status = $data['status'];
                
                $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$status, $userId]);
                
                echo json_encode(['success' => true]);
            } else {
                // Update user details
                $data = json_decode(file_get_contents('php://input'), true);
                
                $fields = [];
                $params = [];
                
                foreach (['username', 'email', 'first_name', 'last_name', 'role'] as $field) {
                    if (isset($data[$field])) {
                        $fields[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
                
                if (isset($data['password']) && $data['password']) {
                    $fields[] = "password_hash = ?";
                    $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
                
                if (empty($fields)) {
                    echo json_encode(['success' => true]);
                    return;
                }
                
                $params[] = $userId;
                $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode(['success' => true]);
            }
            break;
            
        case 'DELETE':
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID required']);
                return;
            }
            
            // Don't actually delete, just deactivate
            $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$userId]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleChannels($method, $segments, $db) {
    switch ($method) {
        case 'GET':
            $stmt = $db->prepare("
                SELECT c.*, 
                       COUNT(DISTINCT p.user_id) as participant_count,
                       COUNT(DISTINCT m.id) as message_count
                FROM communication_channels c
                LEFT JOIN communication_participants p ON c.id = p.channel_id
                LEFT JOIN communication_messages m ON c.id = m.channel_id AND m.is_deleted = 0
                GROUP BY c.id
                ORDER BY c.last_activity_at DESC
            ");
            $stmt->execute();
            
            $channels = $stmt->fetchAll();
            echo json_encode($channels);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Generate slug
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));
            
            $stmt = $db->prepare("
                INSERT INTO communication_channels 
                (name, slug, description, type, created_by_user_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $data['name'],
                $slug,
                $data['description'] ?? null,
                $data['type'] ?? 'public',
                $_SESSION['user_id']
            ]);
            
            echo json_encode(['id' => $db->lastInsertId(), 'success' => true]);
            break;
            
        case 'DELETE':
            $channelId = isset($segments[1]) ? (int)$segments[1] : null;
            if (!$channelId) {
                http_response_code(400);
                echo json_encode(['error' => 'Channel ID required']);
                return;
            }
            
            // Delete channel and related data
            $db->beginTransaction();
            try {
                // Delete messages
                $stmt = $db->prepare("DELETE FROM communication_messages WHERE channel_id = ?");
                $stmt->execute([$channelId]);
                
                // Delete participants
                $stmt = $db->prepare("DELETE FROM communication_participants WHERE channel_id = ?");
                $stmt->execute([$channelId]);
                
                // Delete channel
                $stmt = $db->prepare("DELETE FROM communication_channels WHERE id = ?");
                $stmt->execute([$channelId]);
                
                $db->commit();
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleMessages($method, $segments, $db) {
    if (isset($segments[1]) && $segments[1] === 'statistics') {
        // Get message statistics for chart
        $stmt = $db->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM communication_messages
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                  AND is_deleted = 0
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute();
        
        $data = [];
        $labels = [];
        while ($row = $stmt->fetch()) {
            $labels[] = date('M j', strtotime($row['date']));
            $data[] = $row['count'];
        }
        
        // Get top channels
        $stmt = $db->prepare("
            SELECT c.id, c.name, c.type, COUNT(m.id) as message_count
            FROM communication_channels c
            LEFT JOIN communication_messages m ON c.id = m.channel_id 
                AND m.is_deleted = 0 
                AND m.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY c.id
            ORDER BY message_count DESC
            LIMIT 5
        ");
        $stmt->execute();
        
        $topChannels = $stmt->fetchAll();
        
        echo json_encode([
            'labels' => $labels,
            'data' => $data,
            'topChannels' => $topChannels
        ]);
        
    } elseif (isset($segments[1]) && $segments[1] === 'recent') {
        // Get recent messages
        $stmt = $db->prepare("
            SELECT m.*, u.username as user_name, c.name as channel_name
            FROM communication_messages m
            JOIN users u ON m.user_id = u.id
            JOIN communication_channels c ON m.channel_id = c.id
            WHERE m.is_deleted = 0
            ORDER BY m.created_at DESC
            LIMIT 20
        ");
        $stmt->execute();
        
        $messages = $stmt->fetchAll();
        echo json_encode($messages);
        
    } elseif ($method === 'DELETE' && isset($segments[1])) {
        // Delete message
        $messageId = (int)$segments[1];
        
        $stmt = $db->prepare("UPDATE communication_messages SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$messageId]);
        
        echo json_encode(['success' => true]);
        
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
}

function handleSettings($method, $db) {
    // For now, return mock settings
    // In a real implementation, these would be stored in a settings table
    $settings = [
        'allow_registration' => true,
        'default_role' => 'user',
        'message_retention' => 90,
        'max_file_size' => 10,
        'enable_email_notifications' => true,
        'enable_push_notifications' => false
    ];
    
    if ($method === 'GET') {
        echo json_encode($settings);
    } elseif ($method === 'PUT') {
        // In a real implementation, save settings to database
        echo json_encode(['success' => true]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}