<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Modules\YFClaim\Services\ClaimAuthService;
use PDO;
use Exception;

/**
 * Claims controller for YFClaim estate sales - COMPLETE IMPLEMENTATION
 */
class ClaimsController extends BaseController
{
    private PDO $pdo;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        
        // Get database connection
        $dbConfig = $config->get('database');
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    // ==== PUBLIC PAGES ====

    /**
     * Show estate sales browse page
     */
    public function showClaimsPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        // Get current and upcoming sales
        $currentSales = $this->getCurrentSales();
        $upcomingSales = $this->getUpcomingSales();

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderClaimsPage($basePath, $currentSales, $upcomingSales);
    }

    /**
     * Show upcoming estate sales page
     */
    public function showUpcomingClaimsPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        $upcomingSales = $this->getUpcomingSales();

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderUpcomingClaimsPage($basePath, $upcomingSales);
    }

    /**
     * Show individual sale page
     */
    public function showSale(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        $saleId = (int)($_GET['id'] ?? 0);
        if (!$saleId) {
            http_response_code(404);
            echo "Sale not found";
            return;
        }

        $sale = $this->getSaleById($saleId);
        if (!$sale) {
            http_response_code(404);
            echo "Sale not found";
            return;
        }

        $items = $this->getSaleItems($saleId);
        $stats = $this->getSaleStats($saleId);

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderSalePage($basePath, $sale, $items, $stats);
    }

    /**
     * Show individual item details
     */
    public function showItem(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        $itemId = (int)($_GET['id'] ?? 0);
        if (!$itemId) {
            http_response_code(404);
            echo "Item not found";
            return;
        }

        $item = $this->getItemById($itemId);
        if (!$item) {
            http_response_code(404);
            echo "Item not found";
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderItemPage($basePath, $item);
    }

    // ==== SELLER FUNCTIONALITY ====

    /**
     * Show seller registration page
     */
    public function showSellerRegistration(): void
    {
        session_start();
        
        // Check if already logged in
        if (isset($_SESSION['claim_seller_logged_in']) && $_SESSION['claim_seller_logged_in'] === true) {
            header('Location: /seller/dashboard');
            exit;
        }
        
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderSellerRegister();
    }

    /**
     * Process seller registration
     */
    public function processSellerRegistration(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        header('Content-Type: application/json');

        try {
            // Gather registration data
            $data = [
                'company_name' => trim($_POST['company_name'] ?? ''),
                'contact_name' => trim($_POST['contact_name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'username' => trim($_POST['username'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'phone' => trim($_POST['phone'] ?? ''),
                'address' => trim($_POST['address'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? 'WA'),
                'zip' => trim($_POST['zip'] ?? ''),
                'website' => trim($_POST['website'] ?? '')
            ];
            
            // Extract names if provided as full name
            if (!empty($data['contact_name']) && strpos($data['contact_name'], ' ') !== false) {
                $nameParts = explode(' ', $data['contact_name'], 2);
                $data['first_name'] = $nameParts[0];
                $data['last_name'] = $nameParts[1] ?? '';
            } else {
                $data['first_name'] = $data['contact_name'];
                $data['last_name'] = '';
            }
            
            // Use email as username if not provided
            if (empty($data['username'])) {
                $data['username'] = $data['email'];
            }

            // Validate required fields
            if (empty($data['company_name']) || empty($data['contact_name']) || empty($data['email']) || empty($data['password'])) {
                throw new Exception('All required fields must be filled');
            }

            // Validate passwords match
            $confirmPassword = $_POST['confirm_password'] ?? '';
            if ($data['password'] !== $confirmPassword) {
                throw new Exception('Passwords do not match');
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }

            // Initialize auth service
            $authService = new ClaimAuthService($this->pdo);
            
            // Register seller through the service
            $result = $authService->registerSeller($data);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'redirect' => '/seller/login'
                ]);
            } else {
                throw new Exception($result['error'] ?? 'Registration failed');
            }
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show seller login page
     */
    public function showSellerLogin(): void
    {
        session_start();
        
        // Check if already logged in
        if (isset($_SESSION['claim_seller_logged_in']) && $_SESSION['claim_seller_logged_in'] === true) {
            header('Location: /seller/dashboard');
            exit;
        }
        
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderSellerLogin();
    }

    /**
     * Process seller login
     */
    public function processSellerLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        session_start();
        header('Content-Type: application/json');

        try {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                throw new Exception('Username and password are required');
            }

            // Initialize auth service
            $authService = new ClaimAuthService($this->pdo);
            
            // Authenticate using the service
            $result = $authService->authenticateSeller($username, $password);
            
            if ($result['success']) {
                // Set session variables (ClaimAuthService already sets most of these)
                $_SESSION['claim_seller_logged_in'] = true;
                $_SESSION['claim_seller_id'] = $result['seller']['id'];
                $_SESSION['claim_auth_user_id'] = $result['auth_user']['id'];
                $_SESSION['claim_session_id'] = $result['session_id'];
                $_SESSION['seller_email'] = $result['seller']['email'];
                $_SESSION['seller_name'] = $result['seller']['contact_name'];
                $_SESSION['company_name'] = $result['seller']['company_name'];
                
                // Also set legacy session variables for compatibility
                $_SESSION['yfclaim_seller_id'] = $result['seller']['id'];
                $_SESSION['yfclaim_seller_name'] = $result['seller']['company_name'];
                
                // Ensure seller is in global chat channels
                try {
                    $container = require __DIR__ . '/../../../../config/container.php';
                    $adminSellerChat = $container->resolve(\YFEvents\Application\Services\Communication\AdminSellerChatService::class);
                    $adminSellerChat->ensureUserInGlobalChannels($result['auth_user']['id'], 'seller');
                } catch (Exception $chatEx) {
                    // Log but don't fail login if chat setup fails
                    error_log('Failed to add seller to chat channels: ' . $chatEx->getMessage());
                }
                
                echo json_encode([
                    'success' => true,
                    'redirect' => '/modules/yfclaim/www/dashboard/'
                ]);
            } else {
                echo json_encode($result);
            }
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show seller dashboard
     */
    public function showSellerDashboard(): void
    {
        session_start();
        
        // Ensure session compatibility between systems
        if (isset($_SESSION['yfclaim_seller_id']) && !isset($_SESSION['claim_seller_id'])) {
            $_SESSION['claim_seller_logged_in'] = true;
            $_SESSION['claim_seller_id'] = $_SESSION['yfclaim_seller_id'];
            $_SESSION['seller_name'] = $_SESSION['yfclaim_seller_name'];
            $_SESSION['company_name'] = $_SESSION['yfclaim_seller_name'];
        }
        
        // Use the module dashboard
        require BASE_PATH . '/modules/yfclaim/www/dashboard/index.php';
    }

    /**
     * Show seller sales list
     */
    public function showSellerSales(): void
    {
        session_start();
        $this->ensureSessionCompatibility();
        require BASE_PATH . '/modules/yfclaim/www/dashboard/sales.php';
    }

    /**
     * Show create sale form
     */
    public function showCreateSale(): void
    {
        session_start();
        $this->ensureSessionCompatibility();
        require BASE_PATH . '/modules/yfclaim/www/dashboard/create-sale.php';
    }

    /**
     * Create new sale
     */
    public function createSale(): void
    {
        session_start();
        if (!isset($_SESSION['yfclaim_seller_id'])) {
            http_response_code(401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        try {
            $sellerId = $_SESSION['yfclaim_seller_id'];
            
            // Validate input
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = $_POST['state'] ?? 'WA';
            $zip = trim($_POST['zip'] ?? '');
            
            if (empty($title) || empty($address) || empty($city)) {
                throw new Exception('Title, address, and city are required');
            }

            // Generate unique codes
            $qrCode = $this->generateUniqueCode('qr');
            $accessCode = $this->generateUniqueCode('access');

            // Create sale
            $stmt = $this->pdo->prepare("
                INSERT INTO yfc_sales (
                    seller_id, title, description, address, city, state, zip,
                    claim_start, claim_end, pickup_start, pickup_end,
                    qr_code, access_code, status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, 'draft', NOW()
                )
            ");

            $stmt->execute([
                $sellerId,
                $title,
                $description,
                $address,
                $city,
                $state,
                $zip,
                $_POST['claim_start'] ?? null,
                $_POST['claim_end'] ?? null,
                $_POST['pickup_start'] ?? null,
                $_POST['pickup_end'] ?? null,
                $qrCode,
                $accessCode
            ]);

            $saleId = $this->pdo->lastInsertId();

            // Redirect to item management
            header('Location: ' . $basePath . '/seller/sale/' . $saleId . '/items');
            exit;

        } catch (Exception $e) {
            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderCreateSalePage($basePath, $e->getMessage());
        }
    }

    /**
     * Seller logout
     */
    public function sellerLogout(): void
    {
        session_start();
        
        // Clear all seller session variables
        unset($_SESSION['yfclaim_seller_id']);
        unset($_SESSION['yfclaim_seller_name']);
        unset($_SESSION['claim_seller_logged_in']);
        unset($_SESSION['claim_seller_id']);
        unset($_SESSION['seller_email']);
        unset($_SESSION['seller_name']);
        unset($_SESSION['company_name']);
        
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }
        
        header('Location: ' . $basePath . '/seller/login');
        exit;
    }

    // ==== BUYER FUNCTIONALITY ====

    /**
     * Show buyer authentication page
     */
    public function showBuyerAuth(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        $saleId = (int)($_GET['sale_id'] ?? 0);
        $itemId = (int)($_GET['item_id'] ?? 0);

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderBuyerAuthPage($basePath, $saleId, $itemId);
    }

    /**
     * Send buyer authentication code
     */
    public function sendBuyerAuthCode(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        header('Content-Type: application/json');

        try {
            $saleId = (int)($_POST['sale_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $authMethod = $_POST['auth_method'] ?? '';
            $authValue = trim($_POST['auth_value'] ?? '');

            if (!$saleId || empty($name) || empty($authMethod) || empty($authValue)) {
                throw new Exception('All fields are required');
            }

            // Generate auth code
            $authCode = sprintf('%06d', mt_rand(0, 999999));
            $sessionToken = bin2hex(random_bytes(32));

            // Create or update buyer
            $stmt = $this->pdo->prepare("
                INSERT INTO yfc_buyers (sale_id, name, email, phone, auth_method, verification_code, session_token, session_expires, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())
                ON DUPLICATE KEY UPDATE
                    verification_code = VALUES(verification_code),
                    session_token = VALUES(session_token),
                    session_expires = VALUES(session_expires)
            ");

            $email = $authMethod === 'email' ? $authValue : '';
            $phone = $authMethod === 'sms' ? $authValue : '';

            $stmt->execute([$saleId, $name, $email, $phone, $authMethod, $authCode, $sessionToken]);

            // Log the verification code for debugging
            error_log("YFClaim verification code for {$authMethod} {$authValue}: {$authCode}");
            
            // Attempt to send the code
            $sent = false;
            $errorMessage = '';
            
            if ($authMethod === 'sms') {
                // Use SMS service
                try {
                    $smsService = new \YFEvents\Infrastructure\Services\SMSService();
                    $sent = $smsService->sendBuyerVerification($authValue, $authCode);
                } catch (Exception $smsError) {
                    $errorMessage = $smsError->getMessage();
                    error_log("SMS service error: " . $errorMessage);
                }
            } else {
                // Try to send email using PHP's mail function
                $subject = 'YFClaim Verification Code';
                $message = "Your verification code is: {$authCode}\n\nThis code expires in 15 minutes.";
                $headers = "From: YFClaim <noreply@yakimafinds.com>\r\n";
                $headers .= "Reply-To: noreply@yakimafinds.com\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                $sent = mail($authValue, $subject, $message, $headers);
            }
            
            // Return response
            if ($authMethod === 'sms') {
                if ($sent) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'SMS verification code sent successfully!',
                        'debug_code' => $authCode // For testing - remove in production
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'SMS could not be sent. Here is your code:',
                        'debug_code' => $authCode,
                        'instructions' => 'SMS failed' . ($errorMessage ? ": {$errorMessage}" : '') . '. The verification code is: ' . $authCode
                    ]);
                }
            } else {
                if ($sent) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Verification code sent to your email.',
                        'debug_code' => $authCode // For testing - remove in production
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Email sending failed. Here is your code:',
                        'debug_code' => $authCode,
                        'instructions' => 'Email could not be sent. The verification code is: ' . $authCode
                    ]);
                }
            }

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verify buyer authentication code
     */
    public function verifyBuyerAuthCode(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        session_start();
        header('Content-Type: application/json');

        try {
            $authValue = trim($_POST['auth_value'] ?? '');
            $authCode = trim($_POST['auth_code'] ?? '');

            if (empty($authValue) || empty($authCode)) {
                throw new Exception('Authentication code is required');
            }

            // Find buyer with valid code
            $stmt = $this->pdo->prepare("
                SELECT id, sale_id, name, session_token
                FROM yfc_buyers
                WHERE (email = ? OR phone = ?)
                AND verification_code = ?
                AND session_expires > NOW()
                AND auth_verified = 0
                LIMIT 1
            ");
            $stmt->execute([$authValue, $authValue, $authCode]);
            $buyer = $stmt->fetch();

            if (!$buyer) {
                throw new Exception('Invalid or expired authentication code');
            }

            // Mark as verified
            $stmt = $this->pdo->prepare("
                UPDATE yfc_buyers 
                SET auth_verified = 1, session_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR)
                WHERE id = ?
            ");
            $stmt->execute([$buyer['id']]);

            // Set session
            $_SESSION['yfclaim_buyer_id'] = $buyer['id'];
            $_SESSION['yfclaim_buyer_name'] = $buyer['name'];
            $_SESSION['yfclaim_buyer_token'] = $buyer['session_token'];

            echo json_encode([
                'success' => true,
                'redirect' => dirname($_SERVER['SCRIPT_NAME']) . '/claims'
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }


    /**
     * Buyer logout
     */
    public function buyerLogout(): void
    {
        session_start();
        unset($_SESSION['yfclaim_buyer_id']);
        unset($_SESSION['yfclaim_buyer_name']);
        unset($_SESSION['yfclaim_buyer_token']);
        
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }
        
        header('Location: ' . $basePath . '/claims');
        exit;
    }

    // ==== API ENDPOINTS ====

    /**
     * Handle contact seller form submission
     */
    public function contactSeller(): void
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        try {
            // Get form data
            $itemId = (int)($_POST['item_id'] ?? 0);
            $buyerName = trim($_POST['buyer_name'] ?? '');
            $buyerEmail = trim($_POST['buyer_email'] ?? '');
            $buyerPhone = trim($_POST['buyer_phone'] ?? '');
            $message = trim($_POST['message'] ?? '');
            
            // Validate required fields
            if (!$itemId || !$buyerName || !$buyerEmail || !$message) {
                throw new Exception('Please fill in all required fields');
            }
            
            // Validate email
            if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please provide a valid email address');
            }
            
            // Rate limiting check (1 per minute per IP)
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $cacheKey = 'contact_' . md5($ip . $itemId);
            $cacheFile = sys_get_temp_dir() . '/' . $cacheKey;
            
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 60)) {
                throw new Exception('Please wait a minute before sending another message');
            }
            
            // Get item and seller info
            $stmt = $this->pdo->prepare("
                SELECT i.*, s.id as sale_id, s.title as sale_title,
                       sel.id as seller_id, sel.email as seller_email, 
                       sel.company_name, sel.contact_name
                FROM yfc_items i
                JOIN yfc_sales s ON i.sale_id = s.id
                JOIN yfc_sellers sel ON s.seller_id = sel.id
                WHERE i.id = ?
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                throw new Exception('Item not found');
            }
            
            // Send email to seller
            $subject = "Inquiry about {$item['title']} - {$item['sale_title']}";
            $emailBody = "You have received a new inquiry about an item in your sale.\n\n";
            $emailBody .= "Item: {$item['title']}\n";
            $emailBody .= "Sale: {$item['sale_title']}\n";
            $emailBody .= "Price: $" . number_format($item['price'], 2) . "\n\n";
            $emailBody .= "From: {$buyerName}\n";
            $emailBody .= "Email: {$buyerEmail}\n";
            if ($buyerPhone) {
                $emailBody .= "Phone: {$buyerPhone}\n";
            }
            $emailBody .= "\nMessage:\n{$message}\n\n";
            $emailBody .= "Please respond directly to the buyer's email address.";
            
            $headers = "From: noreply@yakimafinds.com\r\n";
            $headers .= "Reply-To: {$buyerEmail}\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            if (!mail($item['seller_email'], $subject, $emailBody, $headers)) {
                throw new Exception('Failed to send message. Please try again later.');
            }
            
            // Update rate limit cache
            file_put_contents($cacheFile, time());
            
            // Log notification
            if (class_exists('YFEvents\Modules\YFClaim\Models\NotificationModel')) {
                $notificationModel = new \YFEvents\Modules\YFClaim\Models\NotificationModel($this->pdo);
                $notificationModel->create([
                    'seller_id' => $item['seller_id'],
                    'sale_id' => $item['sale_id'],
                    'type' => 'item_inquiry',
                    'title' => 'New inquiry for ' . $item['title'],
                    'message' => "From: {$buyerName} ({$buyerEmail})"
                ]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Your message has been sent to the seller!'
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    // ==== HELPER METHODS FOR DATA RETRIEVAL ====

    /**
     * Ensure session variables are compatible between both systems
     */
    private function ensureSessionCompatibility(): void
    {
        // Map from controller naming to module naming
        if (isset($_SESSION['yfclaim_seller_id']) && !isset($_SESSION['claim_seller_id'])) {
            $_SESSION['claim_seller_logged_in'] = true;
            $_SESSION['claim_seller_id'] = $_SESSION['yfclaim_seller_id'];
            $_SESSION['seller_name'] = $_SESSION['yfclaim_seller_name'] ?? '';
            $_SESSION['company_name'] = $_SESSION['yfclaim_seller_name'] ?? '';
        }
        
        // Map from module naming to controller naming
        if (isset($_SESSION['claim_seller_id']) && !isset($_SESSION['yfclaim_seller_id'])) {
            $_SESSION['yfclaim_seller_id'] = $_SESSION['claim_seller_id'];
            $_SESSION['yfclaim_seller_name'] = $_SESSION['company_name'] ?? $_SESSION['seller_name'] ?? '';
        }
    }

    private function getCurrentSales(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, sel.company_name, 
                   COUNT(i.id) as item_count,
                   COUNT(DISTINCT o.buyer_id) as buyer_count,
                   COUNT(o.id) as offer_count
            FROM yfc_sales s
            LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            LEFT JOIN yfc_offers o ON i.id = o.item_id
            WHERE s.status = 'active' 
            AND s.claim_start <= NOW() 
            AND s.claim_end >= NOW()
            GROUP BY s.id
            ORDER BY s.claim_end ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getUpcomingSales(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, sel.company_name, 
                   COUNT(i.id) as item_count
            FROM yfc_sales s
            LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            WHERE s.status = 'active' 
            AND s.claim_start > NOW()
            GROUP BY s.id
            ORDER BY s.claim_start ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getSaleById(int $saleId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, sel.company_name, sel.contact_name, sel.phone
            FROM yfc_sales s
            LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
            WHERE s.id = ? AND s.status = 'active'
        ");
        $stmt->execute([$saleId]);
        return $stmt->fetch() ?: null;
    }

    private function getSaleItems(int $saleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, 
                   (SELECT filename FROM yfc_item_images WHERE item_id = i.id AND is_primary = 1 LIMIT 1) as primary_image
            FROM yfc_items i
            WHERE i.sale_id = ? AND i.status = 'available'
            ORDER BY i.sort_order ASC, i.id ASC
        ");
        $stmt->execute([$saleId]);
        return $stmt->fetchAll();
    }

    private function getSaleStats(int $saleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT i.id) as total_items,
                COUNT(DISTINCT CASE WHEN i.status = 'claimed' THEN i.id END) as claimed_items
            FROM yfc_items i
            WHERE i.sale_id = ?
        ");
        $stmt->execute([$saleId]);
        return $stmt->fetch();
    }

    private function getItemById(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, s.title as sale_title, s.id as sale_id,
                   sel.company_name, sel.phone as seller_phone
            FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            JOIN yfc_sellers sel ON s.seller_id = sel.id
            WHERE i.id = ?
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetch() ?: null;
    }

    private function getSellerSales(int $sellerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, 
                   COUNT(DISTINCT i.id) as item_count
            FROM yfc_sales s
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            WHERE s.seller_id = ?
            GROUP BY s.id
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetchAll();
    }

    private function getSellerStats(int $sellerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT s.id) as total_sales,
                COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_sales,
                COUNT(DISTINCT i.id) as total_items,
                SUM(CASE WHEN i.status = 'claimed' THEN 1 ELSE 0 END) as claimed_items
            FROM yfc_sales s
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            WHERE s.seller_id = ?
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetch();
    }


    private function generateUniqueCode(string $type): string
    {
        $length = $type === 'qr' ? 32 : 6;
        do {
            $code = $type === 'qr' 
                ? bin2hex(random_bytes(16))
                : sprintf('%06d', mt_rand(0, 999999));
            
            $column = $type === 'qr' ? 'qr_code' : 'access_code';
            $stmt = $this->pdo->prepare("SELECT id FROM yfc_sales WHERE $column = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetch());
        
        return $code;
    }

    // ==== RENDER METHODS ====

    private function renderClaimsPage(string $basePath, array $currentSales, array $upcomingSales): string
    {
        $currentSalesHtml = $this->renderSalesGrid($currentSales, $basePath, 'current');
        $upcomingSalesHtml = $this->renderSalesGrid($upcomingSales, $basePath, 'upcoming');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFClaim - Estate Sales | YFEvents</title>
    <meta name="description" content="Browse and claim items from estate sales across the Yakima Valley.">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 60px 20px; text-align: center; }
        .header h1 { font-size: 3rem; margin-bottom: 15px; font-weight: 700; }
        .header p { font-size: 1.3rem; opacity: 0.95; max-width: 600px; margin: 0 auto; }
        .header-actions { margin-top: 30px; display: flex; gap: 20px; justify-content: center; }
        .header-btn { display: inline-block; padding: 12px 24px; background: rgba(255, 255, 255, 0.2); color: white; text-decoration: none; border-radius: 8px; transition: background 0.3s ease; backdrop-filter: blur(10px); }
        .header-btn:hover { background: rgba(255, 255, 255, 0.3); }
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .nav-link { display: inline-block; margin-bottom: 30px; color: #667eea; text-decoration: none; font-weight: 500; font-size: 1.1rem; }
        .nav-link:hover { text-decoration: underline; }
        .section { margin-bottom: 50px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 3px solid #667eea; }
        .section h2 { font-size: 2rem; color: #2c3e50; font-weight: 600; }
        .status-badge { padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-active { background: #d4edda; color: #155724; }
        .status-upcoming { background: #cce5ff; color: #004085; }
        .sales-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 30px; }
        .sale-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease; border: 1px solid #e9ecef; }
        .sale-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .sale-header { background: #2c3e50; color: white; padding: 25px; }
        .sale-title { font-size: 1.4rem; font-weight: 600; margin-bottom: 8px; }
        .sale-company { opacity: 0.9; font-size: 1rem; }
        .sale-body { padding: 25px; }
        .sale-info { margin-bottom: 20px; }
        .sale-info-item { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; font-size: 0.95rem; }
        .sale-info-item .icon { width: 20px; text-align: center; color: #667eea; }
        .sale-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; padding: 20px; background: #f8f9fa; border-radius: 10px; margin-bottom: 20px; }
        .stat { text-align: center; }
        .stat-value { font-size: 1.5rem; font-weight: bold; color: #2c3e50; margin-bottom: 5px; }
        .stat-label { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
        .sale-actions { display: flex; gap: 15px; }
        .btn { flex: 1; padding: 12px 20px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; text-decoration: none; text-align: center; transition: all 0.3s ease; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; transform: translateY(-2px); }
        .btn-secondary { background: #e9ecef; color: #495057; }
        .btn-secondary:hover { background: #dee2e6; }
        .empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
        .empty-state h3 { font-size: 1.5rem; margin-bottom: 15px; color: #495057; }
        .empty-state p { font-size: 1.1rem; line-height: 1.6; }
        @media (max-width: 768px) { .header h1 { font-size: 2.5rem; } .header-actions { flex-direction: column; align-items: center; } .sales-grid { grid-template-columns: 1fr; } .section-header { flex-direction: column; gap: 15px; align-items: flex-start; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏛️ YFClaim Estate Sales</h1>
        <p>Browse and claim items from estate sales across the Yakima Valley</p>
        <div class="header-actions">
            <a href="{$basePath}/seller/login" class="header-btn">Estate Sale Company? Login</a>
        </div>
    </div>
    
    <div class="container">
        <a href="{$basePath}/" class="nav-link">← Back to YFEvents</a>
        
        <section class="section">
            <div class="section-header">
                <h2>Current Sales</h2>
                <span class="status-badge status-active">Claiming Open</span>
            </div>
            {$currentSalesHtml}
        </section>
        
        <section class="section">
            <div class="section-header">
                <h2>Upcoming Sales</h2>
                <span class="status-badge status-upcoming">Preview Available</span>
            </div>
            {$upcomingSalesHtml}
        </section>
    </div>
</body>
</html>
HTML;
    }

    private function renderSalesGrid(array $sales, string $basePath, string $type): string
    {
        if (empty($sales)) {
            $message = $type === 'current' 
                ? 'No current sales available. Check back soon for new estate sales!'
                : 'No upcoming sales scheduled yet.';
            
            return <<<HTML
                <div class="empty-state">
                    <h3>No Sales Available</h3>
                    <p>{$message}</p>
                </div>
HTML;
        }

        $salesHtml = '<div class="sales-grid">';
        
        foreach ($sales as $sale) {
            $timeInfo = $this->getSaleTimeInfo($sale, $type);
            $salesHtml .= $this->renderSaleCard($sale, $basePath, $timeInfo, $type);
        }
        
        $salesHtml .= '</div>';
        return $salesHtml;
    }

    private function renderSaleCard(array $sale, string $basePath, array $timeInfo, string $type): string
    {
        $itemCount = $sale['item_count'] ?? 0;
        
        $actionButton = $type === 'current' 
            ? '<a href="' . $basePath . '/claims/sale?id=' . $sale['id'] . '" class="btn btn-primary">Browse Items</a>'
            : '<a href="' . $basePath . '/claims/sale?id=' . $sale['id'] . '&preview=1" class="btn btn-secondary">Preview Items</a>';

        return <<<HTML
            <div class="sale-card">
                <div class="sale-header">
                    <div class="sale-title">{$sale['title']}</div>
                    <div class="sale-company">by {$sale['company_name']}</div>
                </div>
                <div class="sale-body">
                    <div class="sale-info">
                        <div class="sale-info-item">
                            <span class="icon">📍</span>
                            <span>{$sale['city']}, {$sale['state']}</span>
                        </div>
                        <div class="sale-info-item">
                            <span class="icon">{$timeInfo['icon']}</span>
                            <span>{$timeInfo['text']}</span>
                        </div>
                        <div class="sale-info-item">
                            <span class="icon">📅</span>
                            <span>Pickup: {$timeInfo['pickup']}</span>
                        </div>
                    </div>
                    
                    <div class="sale-stats">
                        <div class="stat">
                            <div class="stat-value">{$itemCount}</div>
                            <div class="stat-label">Items</div>
                        </div>
                    </div>
                    
                    <div class="sale-actions">
                        {$actionButton}
                    </div>
                </div>
            </div>
HTML;
    }

    private function getSaleTimeInfo(array $sale, string $type): array
    {
        $now = time();
        
        if ($type === 'current') {
            $claimEnd = strtotime($sale['claim_end']);
            $hoursLeft = round(($claimEnd - $now) / 3600);
            $timeText = $hoursLeft > 24 
                ? round($hoursLeft / 24) . ' days left'
                : $hoursLeft . ' hours left';
            
            return [
                'icon' => '⏰',
                'text' => $timeText,
                'pickup' => date('M j', strtotime($sale['pickup_start'])) . ' - ' . date('M j', strtotime($sale['pickup_end']))
            ];
        } else {
            $claimStart = strtotime($sale['claim_start']);
            $daysUntil = round(($claimStart - $now) / 86400);
            
            return [
                'icon' => '🔓',
                'text' => 'Claims open in ' . $daysUntil . ' day' . ($daysUntil != 1 ? 's' : ''),
                'pickup' => date('M j', strtotime($sale['pickup_start'])) . ' - ' . date('M j', strtotime($sale['pickup_end']))
            ];
        }
    }

    // Additional render methods follow - simplified for space

    private function renderSalePage(string $basePath, array $sale, array $items, array $stats): string
    {
        $itemsHtml = $this->renderSaleItems($items, $basePath);
        $saleStatus = $this->getSaleStatus($sale);
        
        // Get first item image for Open Graph
        $ogImage = '';
        if (!empty($items)) {
            foreach ($items as $item) {
                if (!empty($item['primary_image'])) {
                    $ogImage = "http://{$_SERVER['HTTP_HOST']}/uploads/yfclaim/items/{$item['primary_image']}";
                    break;
                }
            }
        }
        // Fallback to default image if no item images
        if (empty($ogImage)) {
            $ogImage = "http://{$_SERVER['HTTP_HOST']}/assets/images/estate-sale-default.jpg";
        }
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$sale['title']} | YFClaim Estate Sales</title>
    
    <!-- Open Graph Meta Tags for Facebook -->
    <meta property="og:title" content="{$sale['title']} - Estate Sale">
    <meta property="og:description" content="{$sale['description']}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}">
    <meta property="og:image" content="{$ogImage}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="YFClaim Estate Sales">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{$sale['title']} - Estate Sale">
    <meta name="twitter:description" content="{$sale['description']}">
    <meta name="twitter:image" content="{$ogImage}">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; font-weight: 700; }
        .header p { font-size: 1.1rem; opacity: 0.95; }
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .nav-link { display: inline-block; margin-bottom: 30px; color: #667eea; text-decoration: none; font-weight: 500; }
        .nav-link:hover { text-decoration: underline; }
        .sale-info { background: white; border-radius: 15px; padding: 30px; margin-bottom: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .sale-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 30px; flex-wrap: wrap; gap: 20px; }
        .sale-title-section { flex: 1; }
        .sale-title { font-size: 2rem; color: #2c3e50; margin-bottom: 10px; }
        .sale-company { color: #6c757d; font-size: 1.1rem; }
        .sale-status { padding: 10px 20px; border-radius: 25px; font-weight: 600; text-transform: uppercase; font-size: 0.9rem; }
        .status-active { background: #d4edda; color: #155724; }
        .status-upcoming { background: #cce5ff; color: #004085; }
        .status-ended { background: #f8d7da; color: #721c24; }
        .sale-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 30px; }
        .detail-section { background: #f8f9fa; padding: 20px; border-radius: 10px; }
        .detail-section h3 { color: #2c3e50; margin-bottom: 15px; font-size: 1.2rem; }
        .detail-item { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .detail-item .icon { color: #667eea; font-size: 1.2rem; }
        .sale-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; color: white; }
        .stat { text-align: center; }
        .stat-value { font-size: 2.5rem; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; text-transform: uppercase; }
        .items-section { margin-top: 40px; }
        .items-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .items-header h2 { font-size: 1.8rem; color: #2c3e50; }
        .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; }
        .item-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease; }
        .item-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .item-image { width: 100%; height: 200px; background: #e9ecef; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 3rem; }
        .item-body { padding: 20px; }
        .item-title { font-size: 1.2rem; font-weight: 600; color: #2c3e50; margin-bottom: 10px; }
        .item-description { color: #6c757d; margin-bottom: 15px; font-size: 0.95rem; line-height: 1.5; }
        .item-price { font-size: 1.4rem; font-weight: bold; color: #28a745; margin-bottom: 15px; }
        .item-stats { display: flex; gap: 20px; margin-bottom: 15px; font-size: 0.9rem; color: #6c757d; }
        .item-actions { display: flex; gap: 10px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; text-decoration: none; text-align: center; transition: all 0.3s ease; flex: 1; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-secondary { background: #e9ecef; color: #495057; }
        .btn-secondary:hover { background: #dee2e6; }
        @media (max-width: 768px) { 
            .sale-header { flex-direction: column; }
            .items-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$sale['title']}</h1>
        <p>by {$sale['company_name']}</p>
    </div>
    
    <div class="container">
        <a href="{$basePath}/claims" class="nav-link">← Back to All Sales</a>
        
        <div class="sale-info">
            <div class="sale-header">
                <div class="sale-title-section">
                    <h2 class="sale-title">{$sale['title']}</h2>
                    <p class="sale-company">{$sale['company_name']}</p>
                </div>
                <span class="sale-status {$saleStatus['class']}">{$saleStatus['text']}</span>
            </div>
            
            <div class="sale-details">
                <div class="detail-section">
                    <h3>📍 Location</h3>
                    <div class="detail-item">
                        <span>{$sale['address']}</span>
                    </div>
                    <div class="detail-item">
                        <span>{$sale['city']}, {$sale['state']} {$sale['zip']}</span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>📅 Schedule</h3>
                    <div class="detail-item">
                        <span class="icon">🔓</span>
                        <span>Claims: {$this->formatDateRange($sale['claim_start'], $sale['claim_end'])}</span>
                    </div>
                    <div class="detail-item">
                        <span class="icon">📦</span>
                        <span>Pickup: {$this->formatDateRange($sale['pickup_start'], $sale['pickup_end'])}</span>
                    </div>
                </div>
                
                <div class="detail-section">
                    <h3>📞 Contact</h3>
                    <div class="detail-item">
                        <span>{$sale['contact_name']}</span>
                    </div>
                    <div class="detail-item">
                        <span>{$sale['phone']}</span>
                    </div>
                </div>
            </div>
            
            <div class="sale-stats">
                <div class="stat">
                    <div class="stat-value">{$stats['total_items']}</div>
                    <div class="stat-label">Total Items</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{$stats['claimed_items']}</div>
                    <div class="stat-label">Items Claimed</div>
                </div>
            </div>
            
            {$this->renderSaleDescription($sale)}
            
            <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3 style="margin-bottom: 10px;">📢 Share This Sale</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button onclick="shareSaleFacebook()" class="btn btn-primary" style="background: #1877f2;">📘 Facebook</button>
                    <button onclick="shareSaleTwitter()" class="btn btn-primary" style="background: #1da1f2;">🐦 Twitter</button>
                    <button onclick="shareSaleEmail()" class="btn btn-primary" style="background: #6c757d;">✉️ Email</button>
                    <button onclick="copySaleLink()" class="btn btn-primary" style="background: #28a745;">📋 Copy Link</button>
                </div>
            </div>
        </div>
        
        <div class="items-section">
            <div class="items-header">
                <h2>Available Items</h2>
                <span>{$this->getAvailableItemsCount($items)} items available</span>
            </div>
            {$itemsHtml}
        </div>
    </div>
    
    <script>
        // Share functionality for items
        function shareItem(itemId, title, description) {
            const baseUrl = window.location.origin;
            const itemUrl = baseUrl + '{$basePath}/claims/item/' + itemId;
            const shareText = title + ' - ' + description.substring(0, 100) + '...';
            
            // Simple share menu
            const shareOptions = [
                {
                    name: 'Facebook',
                    url: 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(itemUrl),
                    icon: '📘'
                },
                {
                    name: 'Twitter',
                    url: 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareText) + '&url=' + encodeURIComponent(itemUrl),
                    icon: '🐦'
                },
                {
                    name: 'Pinterest',
                    url: 'https://pinterest.com/pin/create/button/?url=' + encodeURIComponent(itemUrl) + '&description=' + encodeURIComponent(shareText),
                    icon: '📌'
                },
                {
                    name: 'Copy Link',
                    action: function() {
                        navigator.clipboard.writeText(itemUrl).then(() => {
                            alert('Link copied to clipboard!');
                        }).catch(() => {
                            prompt('Copy this link:', itemUrl);
                        });
                    },
                    icon: '📋'
                }
            ];
            
            // Create share menu
            let menu = '<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,0.2);z-index:9999;min-width:200px;">';
            menu += '<h3 style="margin-bottom:15px;">Share Item</h3>';
            shareOptions.forEach(option => {
                if (option.url) {
                    menu += '<a href="' + option.url + '" target="_blank" style="display:block;padding:10px;text-decoration:none;color:#333;border-radius:5px;margin-bottom:5px;" onmouseover="this.style.background=\'#f0f0f0\'" onmouseout="this.style.background=\'none\'">' + option.icon + ' ' + option.name + '</a>';
                } else {
                    menu += '<button onclick="(' + option.action.toString() + ')();document.getElementById(\'shareMenu\').remove();" style="display:block;width:100%;padding:10px;border:none;background:none;text-align:left;cursor:pointer;border-radius:5px;" onmouseover="this.style.background=\'#f0f0f0\'" onmouseout="this.style.background=\'none\'">' + option.icon + ' ' + option.name + '</button>';
                }
            });
            menu += '<button onclick="document.getElementById(\'shareMenu\').remove();" style="margin-top:10px;padding:5px 15px;background:#6c757d;color:white;border:none;border-radius:5px;cursor:pointer;">Close</button>';
            menu += '</div>';
            menu += '<div onclick="document.getElementById(\'shareMenu\').remove();" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9998;"></div>';
            
            // Remove any existing menu
            const existingMenu = document.getElementById('shareMenu');
            if (existingMenu) {
                existingMenu.remove();
            }
            
            const menuElement = document.createElement('div');
            menuElement.id = 'shareMenu';
            menuElement.innerHTML = menu;
            document.body.appendChild(menuElement);
        }
        
        // Share functions for the sale
        function shareSaleFacebook() {
            const saleUrl = window.location.href;
            const saleTitle = document.querySelector('.sale-title').textContent;
            window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(saleUrl), 'facebook-share', 'width=626,height=436');
        }
        
        function shareSaleTwitter() {
            const saleUrl = window.location.href;
            const saleTitle = document.querySelector('.sale-title').textContent;
            const text = saleTitle + ' - Check out this estate sale!';
            window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(saleUrl), 'twitter-share', 'width=550,height=420');
        }
        
        function shareSaleEmail() {
            const saleUrl = window.location.href;
            const saleTitle = document.querySelector('.sale-title').textContent;
            const subject = encodeURIComponent('Check out this estate sale: ' + saleTitle);
            const body = encodeURIComponent('I found this estate sale that might interest you:\\n\\n' + saleTitle + '\\n\\n' + saleUrl);
            window.location.href = 'mailto:?subject=' + subject + '&body=' + body;
        }
        
        function copySaleLink() {
            const saleUrl = window.location.href;
            navigator.clipboard.writeText(saleUrl).then(() => {
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '✅ Copied!';
                const originalBg = btn.style.background;
                btn.style.background = '#28a745';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.background = originalBg;
                }, 2000);
            }).catch(() => {
                // Fallback for browsers that don't support clipboard API
                prompt('Copy this link:', saleUrl);
            });
        }
    </script>
</body>
</html>
HTML;
    }

    private function renderItemPage(string $basePath, array $item): string
    {
        session_start();
        $isAuthenticated = isset($_SESSION['yfclaim_buyer_id']);
        $buyerName = $_SESSION['yfclaim_buyer_name'] ?? '';
        $price = $item['price'] ?? 0;
        $conditionRating = $item['condition_rating'] ?? 'Not specified';
        
        $contactSection = $this->renderContactSection($basePath, $item);
        $itemImages = $this->renderItemImages($item);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$item['title']} | YFClaim Estate Sales</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; font-weight: 700; }
        .header p { font-size: 1.1rem; opacity: 0.95; }
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .nav-link { display: inline-block; margin-bottom: 30px; color: #667eea; text-decoration: none; font-weight: 500; }
        .nav-link:hover { text-decoration: underline; }
        .item-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
        .item-images { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .item-details { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .item-title { font-size: 2rem; color: #2c3e50; margin-bottom: 15px; font-weight: 600; }
        .item-price { font-size: 2.5rem; font-weight: bold; color: #28a745; margin-bottom: 20px; }
        .item-description { color: #6c757d; font-size: 1.1rem; line-height: 1.6; margin-bottom: 30px; }
        .item-meta { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .meta-item { display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e9ecef; }
        .meta-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .meta-label { color: #6c757d; font-weight: 500; }
        .meta-value { font-weight: 600; color: #2c3e50; }
        .sale-info { background: #e8f4fd; padding: 20px; border-radius: 10px; border-left: 4px solid #667eea; margin-bottom: 30px; }
        .sale-info h3 { color: #2c3e50; margin-bottom: 10px; }
        .sale-info p { color: #6c757d; margin-bottom: 5px; }
        .offer-section { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .offer-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .offer-title { font-size: 1.5rem; color: #2c3e50; font-weight: 600; }
        .offer-stats { display: flex; gap: 30px; font-size: 0.95rem; color: #6c757d; }
        .offer-form { margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #2c3e50; }
        .form-input { width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s ease; }
        .form-input:focus { outline: none; border-color: #667eea; }
        .input-group { display: flex; align-items: center; }
        .input-prefix { background: #e9ecef; padding: 12px 15px; border: 2px solid #e9ecef; border-right: none; border-radius: 8px 0 0 8px; font-weight: 500; }
        .input-group .form-input { border-radius: 0 8px 8px 0; border-left: none; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; text-decoration: none; text-align: center; transition: all 0.3s ease; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-primary:disabled { background: #ccc; cursor: not-allowed; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-block { width: 100%; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .auth-prompt { background: #cce5ff; padding: 20px; border-radius: 10px; text-align: center; border: 1px solid #99d3ff; }
        .auth-prompt h4 { color: #004085; margin-bottom: 10px; }
        .auth-prompt p { color: #004085; margin-bottom: 15px; }
        .loading { display: none; text-align: center; padding: 20px; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .item-image-placeholder { width: 100%; height: 300px; background: #e9ecef; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 3rem; }
        @media (max-width: 768px) { 
            .item-layout { grid-template-columns: 1fr; }
            .offer-header { flex-direction: column; gap: 15px; align-items: flex-start; }
            .offer-stats { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📦 {$item['title']}</h1>
        <p>Estate Sale Item Details</p>
    </div>
    
    <div class="container">
        <a href="{$basePath}/claims/sale?id={$item['sale_id']}" class="nav-link">← Back to Sale</a>
        
        <div class="item-layout">
            <div class="item-images">
                {$itemImages}
            </div>
            
            <div class="item-details">
                <h2 class="item-title">{$item['title']}</h2>
                <div class="item-price">\${$price}</div>
                <div class="item-description">{$item['description']}</div>
                
                <div class="item-meta">
                    <div class="meta-item">
                        <span class="meta-label">Category:</span>
                        <span class="meta-value">{$item['category']}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Condition:</span>
                        <span class="meta-value">{$conditionRating}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Price:</span>
                        <span class="meta-value">\${$price}</span>
                    </div>
                </div>
                
                <div class="sale-info">
                    <h3>📍 Sale Information</h3>
                    <p><strong>Sale:</strong> {$item['sale_title']}</p>
                    <p><strong>Company:</strong> {$item['company_name']}</p>
                    <p><strong>Contact:</strong> {$item['seller_phone']}</p>
                </div>
            </div>
        </div>
        
        {$contactSection}
    </div>
    
    <script>
        // Contact form functionality
        document.addEventListener('DOMContentLoaded', function() {
            const contactForm = document.getElementById('contact-form');
            const submitBtn = document.getElementById('submit-contact-btn');
            const loading = document.getElementById('loading');
            const alertContainer = document.getElementById('alert-container');
            
            if (contactForm) {
                contactForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const buyerName = document.getElementById('buyer-name').value.trim();
                    const buyerEmail = document.getElementById('buyer-email').value.trim();
                    const buyerPhone = document.getElementById('buyer-phone').value.trim();
                    const message = document.getElementById('message').value.trim();
                    const itemId = document.getElementById('item-id').value;
                    
                    if (!buyerName || !buyerEmail || !message) {
                        showAlert('Please fill in all required fields.', 'error');
                        return;
                    }
                    
                    showLoading(true);
                    clearAlerts();
                    
                    try {
                        const response = await fetch('{$basePath}/api/claims/contact', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                item_id: itemId,
                                buyer_name: buyerName,
                                buyer_email: buyerEmail,
                                buyer_phone: buyerPhone,
                                message: message
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            showAlert('Message sent successfully! The seller will contact you soon.', 'success');
                            contactForm.reset();
                        } else {
                            showAlert(result.error || 'Failed to send message.', 'error');
                        }
                    } catch (error) {
                        showAlert('Network error. Please try again.', 'error');
                    } finally {
                        showLoading(false);
                    }
                });
            }
            
            function showAlert(message, type) {
                const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
                alertContainer.innerHTML = '<div class="alert ' + alertClass + '">' + message + '</div>';
            }
            
            function clearAlerts() {
                alertContainer.innerHTML = '';
            }
            
            function showLoading(show) {
                if (loading) {
                    loading.style.display = show ? 'block' : 'none';
                }
                if (submitBtn) {
                    submitBtn.disabled = show;
                }
            }
        });
    </script>
</body>
</html>
HTML;
    }
    
    private function renderContactSection(string $basePath, array $item): string
    {
        $price = $item['price'] ?? 0;
        $isAvailable = $item['status'] === 'available';
        
        if (!$isAvailable) {
            return <<<HTML
                <div class="contact-section">
                    <div class="contact-header">
                        <h3 class="contact-title">📧 Contact Seller</h3>
                    </div>
                    <div class="alert alert-info">
                        This item is no longer available.
                    </div>
                </div>
HTML;
        }
        
        return <<<HTML
            <div class="contact-section">
                <div class="contact-header">
                    <h3 class="contact-title">📧 Contact Seller</h3>
                    <div class="item-price">
                        <span>Price: \${$price}</span>
                    </div>
                </div>
                
                <div id="alert-container"></div>
                
                <form id="contact-form" class="contact-form">
                    <input type="hidden" id="item-id" value="{$item['id']}">
                    
                    <div class="form-group">
                        <label class="form-label" for="buyer-name">Your Name</label>
                        <input type="text" id="buyer-name" class="form-input" placeholder="Enter your name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="buyer-email">Your Email</label>
                        <input type="email" id="buyer-email" class="form-input" placeholder="your@email.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="buyer-phone">Phone (optional)</label>
                        <input type="tel" id="buyer-phone" class="form-input" placeholder="(555) 123-4567">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="message">Message</label>
                        <textarea id="message" class="form-input" rows="4" placeholder="I'm interested in this item..." required></textarea>
                    </div>
                    
                    <button type="submit" id="submit-contact-btn" class="btn btn-primary btn-block">Send Message</button>
                </form>
                
                <div id="loading" class="loading">
                    <div class="spinner"></div>
                    <p>Sending your message...</p>
                </div>
            </div>
HTML;
    }
    
    private function renderItemImages(array $item): string
    {
        // In a real implementation, this would handle actual item images
        return <<<HTML
            <div class="item-image-placeholder">
                📦
            </div>
            <div style="margin-top: 15px; color: #6c757d; text-align: center;">
                <small>Item images will be displayed here</small>
            </div>
HTML;
    }


    private function renderBuyerAuthPage(string $basePath, int $saleId, int $itemId): string
    {
        $itemInfo = '';
        if ($itemId > 0) {
            $item = $this->getItemById($itemId);
            if ($item) {
                $itemInfo = "
                <div class='item-context'>
                    <h3>🎯 You're making an offer on:</h3>
                    <div class='item-preview'>
                        <h4>{$item['title']}</h4>
                        <p>Starting Price: \${$price}</p>
                        <p>Sale: {$item['sale_title']}</p>
                    </div>
                </div>";
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Authentication | YFClaim Estate Sales</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; font-weight: 700; }
        .header p { font-size: 1.1rem; opacity: 0.95; }
        .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
        .nav-link { display: inline-block; margin-bottom: 30px; color: #667eea; text-decoration: none; font-weight: 500; }
        .nav-link:hover { text-decoration: underline; }
        .auth-card { background: white; border-radius: 15px; padding: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .item-context { background: #e8f4fd; padding: 20px; border-radius: 10px; margin-bottom: 30px; border-left: 4px solid #667eea; }
        .item-context h3 { color: #2c3e50; margin-bottom: 15px; }
        .item-preview { background: white; padding: 15px; border-radius: 8px; }
        .item-preview h4 { color: #667eea; margin-bottom: 8px; }
        .item-preview p { color: #6c757d; margin-bottom: 5px; }
        .auth-steps { margin-bottom: 30px; }
        .step { display: flex; align-items: center; margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .step-number { background: #667eea; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
        .step-text { flex: 1; }
        .auth-form { margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #2c3e50; }
        .form-input { width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s ease; }
        .form-input:focus { outline: none; border-color: #667eea; }
        .form-radio { margin-right: 10px; }
        .radio-group { display: flex; gap: 20px; margin-top: 10px; }
        .radio-option { display: flex; align-items: center; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease; width: 100%; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-primary:disabled { background: #ccc; cursor: not-allowed; }
        .error-message { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
        .success-message { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .auth-code-section { display: none; }
        .info-box { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .loading { display: none; text-align: center; padding: 20px; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @media (max-width: 768px) { .container { padding: 20px 15px; } .auth-card { padding: 25px; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 Buyer Authentication</h1>
        <p>Secure authentication required to make offers</p>
    </div>
    
    <div class="container">
        <a href="{$basePath}/claims" class="nav-link">← Back to Sales</a>
        
        <div class="auth-card">
            {$itemInfo}
            
            <div class="auth-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-text">
                        <strong>Enter Your Information</strong><br>
                        Provide your name and contact method
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-text">
                        <strong>Receive Verification Code</strong><br>
                        We'll send a secure code via SMS or email
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-text">
                        <strong>Make Your Offer</strong><br>
                        Submit offers on items you're interested in
                    </div>
                </div>
            </div>
            
            <div id="error-container"></div>
            <div id="success-container"></div>
            
            <!-- Step 1: Contact Information -->
            <div id="contact-form" class="auth-form">
                <h3 style="margin-bottom: 20px; color: #2c3e50;">Step 1: Your Information</h3>
                
                <div class="form-group">
                    <label class="form-label" for="buyer-name">Full Name *</label>
                    <input type="text" id="buyer-name" class="form-input" placeholder="Enter your full name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">How would you like to receive your verification code? *</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="auth-email" name="auth-method" value="email" class="form-radio" checked>
                            <label for="auth-email">📧 Email</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="auth-sms" name="auth-method" value="sms" class="form-radio">
                            <label for="auth-sms">📱 SMS</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="contact-value" id="contact-label">Email Address *</label>
                    <input type="text" id="contact-value" class="form-input" placeholder="Enter your email address" required>
                </div>
                
                <div class="info-box">
                    <strong>Privacy Notice:</strong> Your contact information is only used for authentication and communication about your offers. We never share your information with third parties.
                </div>
                
                <button type="button" id="send-code-btn" class="btn btn-primary">Send Verification Code</button>
            </div>
            
            <!-- Step 2: Verification Code -->
            <div id="verification-form" class="auth-form auth-code-section">
                <h3 style="margin-bottom: 20px; color: #2c3e50;">Step 2: Enter Verification Code</h3>
                
                <div class="info-box">
                    <span id="code-sent-message">We've sent a 6-digit verification code to your contact method.</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="auth-code">Verification Code *</label>
                    <input type="text" id="auth-code" class="form-input" placeholder="Enter 6-digit code" maxlength="6" required>
                </div>
                
                <button type="button" id="verify-code-btn" class="btn btn-primary">Verify & Continue</button>
                <br><br>
                <button type="button" id="resend-code-btn" class="btn" style="background: #6c757d; color: white;">Resend Code</button>
            </div>
            
            <!-- Loading State -->
            <div id="loading" class="loading">
                <div class="spinner"></div>
                <p>Processing your request...</p>
            </div>
        </div>
    </div>
    
    <script>
        // Form elements
        const contactForm = document.getElementById('contact-form');
        const verificationForm = document.getElementById('verification-form');
        const loading = document.getElementById('loading');
        const errorContainer = document.getElementById('error-container');
        const successContainer = document.getElementById('success-container');
        
        // Input elements
        const buyerNameInput = document.getElementById('buyer-name');
        const authMethodRadios = document.querySelectorAll('input[name="auth-method"]');
        const contactValueInput = document.getElementById('contact-value');
        const contactLabel = document.getElementById('contact-label');
        const authCodeInput = document.getElementById('auth-code');
        
        // Button elements
        const sendCodeBtn = document.getElementById('send-code-btn');
        const verifyCodeBtn = document.getElementById('verify-code-btn');
        const resendCodeBtn = document.getElementById('resend-code-btn');
        
        // Update contact field based on selected method
        authMethodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'email') {
                    contactLabel.textContent = 'Email Address *';
                    contactValueInput.placeholder = 'Enter your email address';
                    contactValueInput.type = 'email';
                } else {
                    contactLabel.textContent = 'Phone Number *';
                    contactValueInput.placeholder = 'Enter your phone number';
                    contactValueInput.type = 'tel';
                }
                contactValueInput.value = '';
            });
        });
        
        // Send verification code
        sendCodeBtn.addEventListener('click', async function() {
            const name = buyerNameInput.value.trim();
            const authMethod = document.querySelector('input[name="auth-method"]:checked').value;
            const contactValue = contactValueInput.value.trim();
            
            if (!name || !contactValue) {
                showError('Please fill in all required fields.');
                return;
            }
            
            // Basic validation
            if (authMethod === 'email' && !isValidEmail(contactValue)) {
                showError('Please enter a valid email address.');
                return;
            }
            
            if (authMethod === 'sms' && !isValidPhone(contactValue)) {
                showError('Please enter a valid phone number.');
                return;
            }
            
            showLoading(true);
            clearMessages();
            
            try {
                const response = await fetch('{$basePath}/buyer/auth/send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        sale_id: '{$saleId}',
                        name: name,
                        auth_method: authMethod,
                        auth_value: contactValue
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    contactForm.style.display = 'none';
                    verificationForm.style.display = 'block';
                    
                    // Show instructions or debug code
                    if (result.instructions) {
                        showSuccess(result.instructions);
                    } else if (result.debug_code) {
                        showSuccess('Verification code sent! For testing: ' + result.debug_code);
                    } else {
                        showSuccess('Verification code sent successfully!');
                    }
                } else {
                    showError(result.error || 'Failed to send verification code.');
                }
            } catch (error) {
                showError('Network error. Please try again.');
            } finally {
                showLoading(false);
            }
        });
        
        // Verify authentication code
        verifyCodeBtn.addEventListener('click', async function() {
            const authCode = authCodeInput.value.trim();
            const contactValue = contactValueInput.value.trim();
            
            if (!authCode) {
                showError('Please enter the verification code.');
                return;
            }
            
            if (authCode.length !== 6) {
                showError('Verification code must be 6 digits.');
                return;
            }
            
            showLoading(true);
            clearMessages();
            
            try {
                const response = await fetch('{$basePath}/buyer/auth/verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        auth_value: contactValue,
                        auth_code: authCode
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess('Authentication successful! Redirecting...');
                    setTimeout(() => {
                        if (result.redirect) {
                            window.location.href = result.redirect;
                        } else {
                            window.location.href = '{$basePath}/buyer/offers';
                        }
                    }, 1500);
                } else {
                    showError(result.error || 'Invalid or expired verification code.');
                }
            } catch (error) {
                showError('Network error. Please try again.');
            } finally {
                showLoading(false);
            }
        });
        
        // Resend code
        resendCodeBtn.addEventListener('click', function() {
            verificationForm.style.display = 'none';
            contactForm.style.display = 'block';
            authCodeInput.value = '';
            clearMessages();
        });
        
        // Utility functions
        function showError(message) {
            errorContainer.innerHTML = '<div class="error-message">' + message + '</div>';
            successContainer.innerHTML = '';
        }
        
        function showSuccess(message) {
            successContainer.innerHTML = '<div class="success-message">' + message + '</div>';
            errorContainer.innerHTML = '';
        }
        
        function clearMessages() {
            errorContainer.innerHTML = '';
            successContainer.innerHTML = '';
        }
        
        function showLoading(show) {
            loading.style.display = show ? 'block' : 'none';
            sendCodeBtn.disabled = show;
            verifyCodeBtn.disabled = show;
        }
        
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
        
        function isValidPhone(phone) {
            return /^[\+]?[1-9][\d]{0,15}$/.test(phone.replace(/\s/g, ''));
        }
    </script>
</body>
</html>
HTML;
    }

    private function formatDateRange(string $start, string $end): string
    {
        return date('M j', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end));
    }
    
    private function renderSaleItems(array $items, string $basePath): string
    {
        if (empty($items)) {
            return '<div class="empty-state" style="text-align: center; padding: 60px 20px; color: #6c757d;">
                <h3 style="font-size: 1.5rem; margin-bottom: 15px; color: #495057;">No Items Available</h3>
                <p>Items will be added soon. Please check back later.</p>
            </div>';
        }
        
        $html = '<div class="items-grid">';
        foreach ($items as $item) {
            $offerCount = $item['offer_count'] ?? 0;
            $highestOffer = $item['highest_offer'] ?? null;
            $startingPrice = (float)($item['price'] ?? 0);
            $category = $item['category'] ?? 'General';
            $priceDisplay = $highestOffer 
                ? '$' . number_format((float)$highestOffer, 2) . ' (highest offer)'
                : '$' . number_format($startingPrice, 2) . ' (starting)';
            
            // Properly escape values for JavaScript in HTML attributes
            // Use JSON encoding which handles all special characters correctly
            $escapedTitle = json_encode($item['title'], JSON_HEX_QUOT | JSON_HEX_APOS);
            $escapedDescription = json_encode($item['description'], JSON_HEX_QUOT | JSON_HEX_APOS);
            
            // Remove the outer quotes that json_encode adds
            $escapedTitle = substr($escapedTitle, 1, -1);
            $escapedDescription = substr($escapedDescription, 1, -1);
            
            $html .= <<<ITEM
                <div class="item-card">
                    <div class="item-image">📦</div>
                    <div class="item-body">
                        <h3 class="item-title">{$item['title']}</h3>
                        <p class="item-description">{$item['description']}</p>
                        <div class="item-price">{$priceDisplay}</div>
                        <div class="item-stats">
                            <span>{$offerCount} offer(s)</span>
                            <span>Category: {$category}</span>
                        </div>
                        <div class="item-actions">
                            <a href="{$basePath}/claims/item/{$item['id']}" class="btn btn-primary">View Details</a>
                            <button onclick="shareItem({$item['id']}, '{$escapedTitle}', '{$escapedDescription}')" class="btn btn-secondary">Share Item</button>
                        </div>
                    </div>
                </div>
ITEM;
        }
        $html .= '</div>';
        
        return $html;
    }
    
    private function getSaleStatus(array $sale): array
    {
        $now = time();
        $claimStart = strtotime($sale['claim_start']);
        $claimEnd = strtotime($sale['claim_end']);
        
        if ($now < $claimStart) {
            return [
                'text' => 'Upcoming',
                'class' => 'status-upcoming'
            ];
        } elseif ($now >= $claimStart && $now <= $claimEnd) {
            return [
                'text' => 'Claims Open',
                'class' => 'status-active'
            ];
        } else {
            return [
                'text' => 'Claims Ended',
                'class' => 'status-ended'
            ];
        }
    }
    
    private function renderSaleDescription(array $sale): string
    {
        if (empty($sale['description'])) {
            return '';
        }
        
        return '<div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #e9ecef;">
            <h3 style="color: #2c3e50; margin-bottom: 15px;">About This Sale</h3>
            <p style="color: #6c757d; line-height: 1.6;">' . nl2br(htmlspecialchars($sale['description'])) . '</p>
        </div>';
    }
    
    private function getAvailableItemsCount(array $items): int
    {
        return count(array_filter($items, function($item) {
            return $item['status'] === 'active';
        }));
    }

    // Additional stub methods for completeness
    public function manageSaleItems(): void 
    {
        session_start();
        $this->ensureSessionCompatibility();
        
        // Map route parameter to expected GET parameter
        if (isset($_GET['id'])) {
            $_GET['sale_id'] = $_GET['id'];
        }
        
        require BASE_PATH . '/modules/yfclaim/www/dashboard/manage-items.php';
    }
    public function showEditSale(): void { echo "Edit sale - coming soon"; }
    public function updateSale(): void { echo json_encode(['success' => false, 'error' => 'Not implemented yet']); }
    public function getSaleItemsApi(): void { echo json_encode(['items' => []]); }
    public function addSaleItem(): void { echo json_encode(['success' => false, 'error' => 'Not implemented yet']); }
    public function updateSaleItem(): void { echo json_encode(['success' => false, 'error' => 'Not implemented yet']); }
    public function deleteSaleItem(): void { echo json_encode(['success' => false, 'error' => 'Not implemented yet']); }
    public function claimItem(): void { echo json_encode(['success' => false, 'error' => 'Not implemented yet']); }

    /**
     * Render seller login page HTML
     */
    private function renderSellerLogin(): string
    {
        $css = $this->getSellerAuthStyles();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login - YFClaim</title>
    <style>{$css}</style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>YFClaim</h1>
            <p>Seller Portal</p>
        </div>
        
        <div class="welcome-text">
            <h3>Welcome back!</h3>
            <p>Sign in to manage your estate sales, upload items, and connect with buyers.</p>
        </div>
        
        <div id="alerts"></div>
        
        <form id="loginForm">
            <div class="form-group">
                <label for="username">Email or Username</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" id="loginBtn">Sign In</button>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Signing you in...</p>
            </div>
        </form>
        
        <div class="register-link">
            <p>New seller? <a href="/seller/register">Register for an account</a></p>
        </div>
        
        <div class="links">
            <a href="/">← Back to Home</a>
            <a href="#" onclick="alert('Password reset feature coming soon!')">Forgot Password?</a>
        </div>
    </div>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const loginBtn = document.getElementById('loginBtn');
            const loading = document.getElementById('loading');
            const alerts = document.getElementById('alerts');
            
            // Clear previous alerts
            alerts.innerHTML = '';
            
            // Show loading
            loginBtn.disabled = true;
            loading.style.display = 'block';
            
            try {
                const formData = new FormData();
                formData.append('username', username);
                formData.append('password', password);
                
                const response = await fetch('/seller/login', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alerts.innerHTML = '<div class="alert alert-success">Login successful! Redirecting to dashboard...</div>';
                    setTimeout(() => {
                        window.location.href = '/seller/dashboard';
                    }, 1500);
                } else {
                    alerts.innerHTML = `<div class="alert alert-error">\${data.error || 'Login failed'}</div>`;
                    loginBtn.disabled = false;
                }
            } catch (error) {
                alerts.innerHTML = '<div class="alert alert-error">An error occurred. Please try again.</div>';
                loginBtn.disabled = false;
            } finally {
                loading.style.display = 'none';
            }
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Render seller registration page HTML
     */
    private function renderSellerRegister(): string
    {
        $css = $this->getSellerAuthStyles();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Registration - YFClaim</title>
    <style>{$css}</style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>YFClaim</h1>
            <p>Seller Registration</p>
        </div>
        
        <div class="welcome-text">
            <h3>Join YFClaim</h3>
            <p>Create your seller account to start managing estate sales, uploading items, and connecting with buyers.</p>
        </div>
        
        <div id="alerts"></div>
        
        <form id="registerForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="contact_name">Contact Name</label>
                    <input type="text" id="contact_name" name="contact_name" required>
                </div>
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="website">Website (Optional)</label>
                    <input type="url" id="website" name="website">
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">Business Address</label>
                <textarea id="address" name="address" placeholder="Street address, city, state, zip"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required minlength="3">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" id="registerBtn">Create Account</button>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Creating your account...</p>
            </div>
        </form>
        
        <div class="login-link">
            <p>Already have an account? <a href="/seller/login">Sign in here</a></p>
        </div>
        
        <div class="links">
            <a href="/">← Back to Home</a>
            <a href="/claims">Browse Sales</a>
        </div>
    </div>
    
    <script>
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');
            
            const registerBtn = document.getElementById('registerBtn');
            const loading = document.getElementById('loading');
            const alerts = document.getElementById('alerts');
            
            // Clear previous alerts
            alerts.innerHTML = '';
            
            // Validate passwords match
            if (password !== confirmPassword) {
                alerts.innerHTML = '<div class="alert alert-error">Passwords do not match</div>';
                return;
            }
            
            // Show loading
            registerBtn.disabled = true;
            loading.style.display = 'block';
            
            try {
                const response = await fetch('/seller/register', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alerts.innerHTML = '<div class="alert alert-success">Registration successful! Redirecting to login...</div>';
                    setTimeout(() => {
                        window.location.href = '/seller/login';
                    }, 2000);
                } else {
                    alerts.innerHTML = `<div class="alert alert-error">\${data.error || 'Registration failed'}</div>`;
                    registerBtn.disabled = false;
                }
            } catch (error) {
                alerts.innerHTML = '<div class="alert alert-error">An error occurred. Please try again.</div>';
                registerBtn.disabled = false;
            } finally {
                loading.style.display = 'none';
            }
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Get CSS styles for seller auth pages
     */
    private function getSellerAuthStyles(): string
    {
        return '
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container, .register-container {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        
        .register-container {
            max-width: 500px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo h1 {
            color: #333;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .logo p {
            color: #666;
            font-size: 1rem;
        }
        
        .welcome-text {
            background: #f8f9ff;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }
        
        .welcome-text h3 {
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .welcome-text p {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
        }
        
        input, textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid transparent;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border-color: #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border-color: #cfc;
        }
        
        .links {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e1e8ed;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            margin: 0 1rem;
            font-weight: 500;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 1rem;
        }
        
        .spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .register-link, .login-link {
            background: #f8f9ff;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin-top: 1rem;
        }
        
        .register-link a, .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .login-container, .register-container {
                padding: 2rem;
            }
        }
        ';
    }
}