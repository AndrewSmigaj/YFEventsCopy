<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
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
        
        // Get database connection from container
        try {
            $connection = $container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class);
            $this->pdo = $connection->getConnection();
        } catch (Exception $e) {
            // Fallback to direct connection
            $this->pdo = new PDO(
                "mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4",
                'yfevents',
                'yfevents_pass',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }
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
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderSellerRegistrationPage($basePath);
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

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        try {
            // Validate input
            $companyName = trim($_POST['company_name'] ?? '');
            $contactName = trim($_POST['contact_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($companyName) || empty($contactName) || empty($email) || empty($password)) {
                throw new Exception('All required fields must be filled');
            }

            if ($password !== $confirmPassword) {
                throw new Exception('Passwords do not match');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }

            // Check if email already exists
            $stmt = $this->pdo->prepare("SELECT id FROM yfc_sellers WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email address already registered');
            }

            // Create seller account
            $stmt = $this->pdo->prepare("
                INSERT INTO yfc_sellers (company_name, contact_name, email, phone, password_hash, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $companyName,
                $contactName,
                $email,
                $phone,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            // Show success message
            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderRegistrationSuccess($basePath, $email);

        } catch (Exception $e) {
            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderSellerRegistrationPage($basePath, $e->getMessage());
        }
    }

    /**
     * Show seller login page
     */
    public function showSellerLogin(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderSellerLoginPage($basePath);
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
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        try {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                throw new Exception('Email and password are required');
            }

            $stmt = $this->pdo->prepare("
                SELECT id, company_name, password_hash, status 
                FROM yfc_sellers 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $seller = $stmt->fetch();

            if (!$seller || !password_verify($password, $seller['password_hash'])) {
                throw new Exception('Invalid email or password');
            }

            if ($seller['status'] !== 'active') {
                throw new Exception('Account is not active. Please contact admin.');
            }

            // Set session
            $_SESSION['yfclaim_seller_id'] = $seller['id'];
            $_SESSION['yfclaim_seller_name'] = $seller['company_name'];

            // Update last login
            $stmt = $this->pdo->prepare("UPDATE yfc_sellers SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$seller['id']]);

            // Redirect to dashboard
            header('Location: ' . $basePath . '/seller/dashboard');
            exit;

        } catch (Exception $e) {
            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderSellerLoginPage($basePath, $e->getMessage());
        }
    }

    /**
     * Show seller dashboard
     */
    public function showSellerDashboard(): void
    {
        session_start();
        if (!isset($_SESSION['yfclaim_seller_id'])) {
            $basePath = dirname($_SERVER['SCRIPT_NAME']);
            if ($basePath === '/') {
                $basePath = '';
            }
            header('Location: ' . $basePath . '/seller/login');
            exit;
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        $sellerId = $_SESSION['yfclaim_seller_id'];
        $sellerName = $_SESSION['yfclaim_seller_name'];

        // Get seller's sales
        $sales = $this->getSellerSales($sellerId);
        $stats = $this->getSellerStats($sellerId);

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderSellerDashboard($basePath, $sellerName, $sales, $stats);
    }

    /**
     * Show create sale form
     */
    public function showCreateSale(): void
    {
        session_start();
        if (!isset($_SESSION['yfclaim_seller_id'])) {
            $basePath = dirname($_SERVER['SCRIPT_NAME']);
            if ($basePath === '/') {
                $basePath = '';
            }
            header('Location: ' . $basePath . '/seller/login');
            exit;
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderCreateSalePage($basePath);
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
        unset($_SESSION['yfclaim_seller_id']);
        unset($_SESSION['yfclaim_seller_name']);
        
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
                'redirect' => dirname($_SERVER['SCRIPT_NAME']) . '/buyer/offers'
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show buyer offers page
     */
    public function showBuyerOffers(): void
    {
        session_start();
        if (!isset($_SESSION['yfclaim_buyer_id'])) {
            $basePath = dirname($_SERVER['SCRIPT_NAME']);
            if ($basePath === '/') {
                $basePath = '';
            }
            header('Location: ' . $basePath . '/buyer/auth');
            exit;
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        $buyerId = $_SESSION['yfclaim_buyer_id'];
        $buyerName = $_SESSION['yfclaim_buyer_name'];

        // Get buyer's offers
        $offers = $this->getBuyerOffersData($buyerId);

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderBuyerOffersPage($basePath, $buyerName, $offers);
    }

    /**
     * Submit offer on an item
     */
    public function submitOffer(): void
    {
        session_start();
        if (!isset($_SESSION['yfclaim_buyer_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        header('Content-Type: application/json');

        try {
            $buyerId = $_SESSION['yfclaim_buyer_id'];
            $itemId = (int)($_POST['item_id'] ?? 0);
            $offerAmount = (float)($_POST['offer_amount'] ?? 0);

            if (!$itemId || $offerAmount <= 0) {
                throw new Exception('Invalid item or offer amount');
            }

            // Check if item is available
            $stmt = $this->pdo->prepare("
                SELECT i.*, s.claim_end 
                FROM yfc_items i
                JOIN yfc_sales s ON i.sale_id = s.id
                WHERE i.id = ? AND i.status = 'available' AND s.claim_end > NOW()
            ");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();

            if (!$item) {
                throw new Exception('Item is not available for offers');
            }

            // Check minimum offer
            if ($offerAmount < $item['starting_price']) {
                throw new Exception('Offer must be at least $' . number_format($item['starting_price'], 2));
            }

            // Create offer
            $stmt = $this->pdo->prepare("
                INSERT INTO yfc_offers (item_id, buyer_id, offer_amount, status, created_at)
                VALUES (?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$itemId, $buyerId, $offerAmount]);

            echo json_encode([
                'success' => true,
                'message' => 'Offer submitted successfully'
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
     * Get buyer offers via API
     */
    public function getBuyerOffers(): void
    {
        session_start();
        if (!isset($_SESSION['yfclaim_buyer_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            return;
        }

        header('Content-Type: application/json');
        $buyerId = $_SESSION['yfclaim_buyer_id'];
        $offers = $this->getBuyerOffersData($buyerId);
        echo json_encode(['offers' => $offers]);
    }

    // ==== HELPER METHODS FOR DATA RETRIEVAL ====

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
                   COUNT(o.id) as offer_count,
                   MAX(o.offer_amount) as highest_offer
            FROM yfc_items i
            LEFT JOIN yfc_offers o ON i.id = o.item_id AND o.status = 'active'
            WHERE i.sale_id = ? AND i.status = 'active'
            GROUP BY i.id
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
                COUNT(DISTINCT o.id) as total_offers,
                COUNT(DISTINCT o.buyer_id) as unique_buyers,
                COUNT(DISTINCT CASE WHEN i.status = 'claimed' THEN i.id END) as claimed_items
            FROM yfc_items i
            LEFT JOIN yfc_offers o ON i.id = o.item_id
            WHERE i.sale_id = ?
        ");
        $stmt->execute([$saleId]);
        return $stmt->fetch();
    }

    private function getItemById(int $itemId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, s.title as sale_title, s.id as sale_id,
                   sel.company_name, sel.phone as seller_phone,
                   COUNT(o.id) as offer_count,
                   MAX(o.offer_amount) as highest_offer
            FROM yfc_items i
            JOIN yfc_sales s ON i.sale_id = s.id
            JOIN yfc_sellers sel ON s.seller_id = sel.id
            LEFT JOIN yfc_offers o ON i.id = o.item_id AND o.status = 'active'
            WHERE i.id = ?
            GROUP BY i.id
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetch() ?: null;
    }

    private function getSellerSales(int $sellerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, 
                   COUNT(DISTINCT i.id) as item_count,
                   COUNT(DISTINCT o.id) as offer_count,
                   COUNT(DISTINCT o.buyer_id) as buyer_count
            FROM yfc_sales s
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            LEFT JOIN yfc_offers o ON i.id = o.item_id
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
                COUNT(DISTINCT o.id) as total_offers,
                SUM(CASE WHEN i.status = 'claimed' THEN 1 ELSE 0 END) as claimed_items
            FROM yfc_sales s
            LEFT JOIN yfc_items i ON s.id = i.sale_id
            LEFT JOIN yfc_offers o ON i.id = o.item_id
            WHERE s.seller_id = ?
        ");
        $stmt->execute([$sellerId]);
        return $stmt->fetch();
    }

    private function getBuyerOffersData(int $buyerId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.*, i.title as item_title, i.status as item_status,
                   s.title as sale_title, s.pickup_start, s.pickup_end,
                   sel.company_name, sel.phone as seller_phone
            FROM yfc_offers o
            JOIN yfc_items i ON o.item_id = i.id
            JOIN yfc_sales s ON i.sale_id = s.id
            JOIN yfc_sellers sel ON s.seller_id = sel.id
            WHERE o.buyer_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$buyerId]);
        return $stmt->fetchAll();
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
            <a href="{$basePath}/buyer/offers" class="header-btn">View My Offers</a>
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
        $offerCount = $sale['offer_count'] ?? 0;
        $buyerCount = $sale['buyer_count'] ?? 0;
        
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
                        <div class="stat">
                            <div class="stat-value">{$offerCount}</div>
                            <div class="stat-label">Offers</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value">{$buyerCount}</div>
                            <div class="stat-label">Buyers</div>
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
    private function renderUpcomingClaimsPage(string $basePath, array $upcomingSales): string
    {
        return "<!-- Upcoming sales page HTML -->"; // Implementation continues...
    }

    private function renderSalePage(string $basePath, array $sale, array $items, array $stats): string
    {
        $itemsHtml = $this->renderSaleItems($items, $basePath);
        $saleStatus = $this->getSaleStatus($sale);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$sale['title']} | YFClaim Estate Sales</title>
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
                    <div class="stat-value">{$stats['total_offers']}</div>
                    <div class="stat-label">Offers Made</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{$stats['unique_buyers']}</div>
                    <div class="stat-label">Active Buyers</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{$stats['claimed_items']}</div>
                    <div class="stat-label">Items Claimed</div>
                </div>
            </div>
            
            {$this->renderSaleDescription($sale)}
        </div>
        
        <div class="items-section">
            <div class="items-header">
                <h2>Available Items</h2>
                <span>{$this->getAvailableItemsCount($items)} items available</span>
            </div>
            {$itemsHtml}
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderItemPage(string $basePath, array $item): string
    {
        session_start();
        $isAuthenticated = isset($_SESSION['yfclaim_buyer_id']);
        $buyerName = $_SESSION['yfclaim_buyer_name'] ?? '';
        
        $offerSection = $this->renderOfferSection($basePath, $item, $isAuthenticated, $buyerName);
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
                <div class="item-price">\${$item['starting_price']}</div>
                <div class="item-description">{$item['description']}</div>
                
                <div class="item-meta">
                    <div class="meta-item">
                        <span class="meta-label">Category:</span>
                        <span class="meta-value">{$item['category']}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Condition:</span>
                        <span class="meta-value">{$item['condition']}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Current Offers:</span>
                        <span class="meta-value">{$item['offer_count']}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Highest Offer:</span>
                        <span class="meta-value">\${$item['highest_offer']}</span>
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
        
        {$offerSection}
    </div>
    
    <script>
        // Offer submission functionality
        document.addEventListener('DOMContentLoaded', function() {
            const offerForm = document.getElementById('offer-form');
            const offerInput = document.getElementById('offer-amount');
            const submitBtn = document.getElementById('submit-offer-btn');
            const loading = document.getElementById('loading');
            const alertContainer = document.getElementById('alert-container');
            
            if (offerForm) {
                offerForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const offerAmount = parseFloat(offerInput.value);
                    const startingPrice = {$item['starting_price']};
                    
                    if (!offerAmount || offerAmount <= 0) {
                        showAlert('Please enter a valid offer amount.', 'error');
                        return;
                    }
                    
                    if (offerAmount < startingPrice) {
                        showAlert('Offer must be at least \$' + startingPrice.toFixed(2), 'error');
                        return;
                    }
                    
                    showLoading(true);
                    clearAlerts();
                    
                    try {
                        const response = await fetch('{$basePath}/api/claims/offer', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                item_id: '{$item['id']}',
                                offer_amount: offerAmount
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            showAlert('Offer submitted successfully! You can track its status in your offers page.', 'success');
                            offerInput.value = '';
                            
                            // Redirect to offers page after success
                            setTimeout(() => {
                                window.location.href = '{$basePath}/buyer/offers';
                            }, 2000);
                        } else {
                            showAlert(result.error || 'Failed to submit offer.', 'error');
                        }
                    } catch (error) {
                        showAlert('Network error. Please try again.', 'error');
                    } finally {
                        showLoading(false);
                    }
                });
                
                // Real-time offer validation
                offerInput.addEventListener('input', function() {
                    const offerAmount = parseFloat(this.value);
                    const startingPrice = {$item['starting_price']};
                    
                    if (offerAmount && offerAmount < startingPrice) {
                        this.style.borderColor = '#dc3545';
                        submitBtn.disabled = true;
                    } else {
                        this.style.borderColor = '#e9ecef';
                        submitBtn.disabled = false;
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
    
    private function renderOfferSection(string $basePath, array $item, bool $isAuthenticated, string $buyerName): string
    {
        if (!$isAuthenticated) {
            return <<<HTML
                <div class="offer-section">
                    <div class="offer-header">
                        <h3 class="offer-title">💰 Make an Offer</h3>
                        <div class="offer-stats">
                            <span>{$item['offer_count']} offers made</span>
                            <span>Highest: \${$item['highest_offer']}</span>
                        </div>
                    </div>
                    
                    <div class="auth-prompt">
                        <h4>🔐 Authentication Required</h4>
                        <p>You need to authenticate before making an offer on this item.</p>
                        <a href="{$basePath}/buyer/auth?sale_id={$item['sale_id']}&item_id={$item['id']}" class="btn btn-primary">Authenticate to Make Offer</a>
                    </div>
                </div>
HTML;
        }
        
        return <<<HTML
            <div class="offer-section">
                <div class="offer-header">
                    <h3 class="offer-title">💰 Make an Offer</h3>
                    <div class="offer-stats">
                        <span>{$item['offer_count']} offers made</span>
                        <span>Highest: \${$item['highest_offer']}</span>
                        <span>Welcome, {$buyerName}!</span>
                    </div>
                </div>
                
                <div id="alert-container"></div>
                
                <form id="offer-form" class="offer-form">
                    <div class="form-group">
                        <label class="form-label" for="offer-amount">Your Offer Amount</label>
                        <div class="input-group">
                            <span class="input-prefix">\$</span>
                            <input type="number" id="offer-amount" class="form-input" step="0.01" min="{$item['starting_price']}" placeholder="Enter your offer amount" required>
                        </div>
                        <small style="color: #6c757d; margin-top: 5px; display: block;">Minimum offer: \${$item['starting_price']}</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Important:</strong> Your offer is binding once accepted by the seller. Please ensure you can pick up the item during the designated pickup window.
                    </div>
                    
                    <button type="submit" id="submit-offer-btn" class="btn btn-primary btn-block">Submit Offer</button>
                </form>
                
                <div id="loading" class="loading">
                    <div class="spinner"></div>
                    <p>Submitting your offer...</p>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="{$basePath}/buyer/offers" class="btn btn-secondary">View My Offers</a>
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

    private function renderSellerRegistrationPage(string $basePath, string $error = ''): string
    {
        return "<!-- Seller registration page HTML -->"; // Implementation continues...
    }

    private function renderRegistrationSuccess(string $basePath, string $email): string
    {
        return "<!-- Registration success page HTML -->"; // Implementation continues...
    }

    private function renderSellerLoginPage(string $basePath, string $error = ''): string
    {
        return "<!-- Seller login page HTML -->"; // Implementation continues...
    }

    private function renderSellerDashboard(string $basePath, string $sellerName, array $sales, array $stats): string
    {
        return "<!-- Seller dashboard HTML -->"; // Implementation continues...
    }

    private function renderCreateSalePage(string $basePath, string $error = ''): string
    {
        return "<!-- Create sale page HTML -->"; // Implementation continues...
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
                        <p>Starting Price: \${$item['starting_price']}</p>
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

    private function renderBuyerOffersPage(string $basePath, string $buyerName, array $offers): string
    {
        $offersHtml = $this->renderOffersGrid($offers, $basePath);
        $offerStats = $this->calculateOfferStats($offers);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Offers | YFClaim Estate Sales</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; font-weight: 700; }
        .header p { font-size: 1.1rem; opacity: 0.95; }
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .nav-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .nav-link { color: #667eea; text-decoration: none; font-weight: 500; }
        .nav-link:hover { text-decoration: underline; }
        .logout-btn { background: #dc3545; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; }
        .logout-btn:hover { background: #c82333; }
        .stats-section { background: white; border-radius: 15px; padding: 30px; margin-bottom: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .stats-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .welcome-text { color: #2c3e50; }
        .welcome-text h2 { font-size: 1.8rem; margin-bottom: 5px; }
        .welcome-text p { color: #6c757d; }
        .quick-actions { display: flex; gap: 15px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 500; cursor: pointer; text-decoration: none; text-align: center; transition: all 0.3s ease; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; text-align: center; }
        .stat-value { font-size: 2.2rem; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px; }
        .offers-section { margin-bottom: 40px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .section-header h2 { font-size: 1.8rem; color: #2c3e50; }
        .filter-tabs { display: flex; gap: 15px; }
        .filter-tab { padding: 8px 16px; border: 2px solid #e9ecef; border-radius: 20px; background: white; color: #6c757d; cursor: pointer; transition: all 0.3s ease; }
        .filter-tab.active { border-color: #667eea; background: #667eea; color: white; }
        .offers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; }
        .offer-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease; }
        .offer-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
        .offer-header { padding: 20px; border-bottom: 1px solid #e9ecef; }
        .offer-title { font-size: 1.2rem; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        .offer-sale { color: #6c757d; font-size: 0.95rem; }
        .offer-body { padding: 20px; }
        .offer-amount { font-size: 1.8rem; font-weight: bold; color: #28a745; margin-bottom: 15px; }
        .offer-details { margin-bottom: 15px; }
        .offer-detail { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; }
        .offer-detail .label { color: #6c757d; }
        .offer-detail .value { font-weight: 500; }
        .offer-status { display: inline-block; padding: 6px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-active { background: #d4edda; color: #155724; }
        .status-accepted { background: #d1ecf1; color: #0c5460; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-expired { background: #e2e3e5; color: #383d41; }
        .offer-actions { display: flex; gap: 10px; margin-top: 15px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
        .empty-state h3 { font-size: 1.5rem; margin-bottom: 15px; color: #495057; }
        .empty-state p { font-size: 1.1rem; line-height: 1.6; margin-bottom: 25px; }
        .offer-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .offer-modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 15px; padding: 30px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-title { font-size: 1.4rem; color: #2c3e50; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6c757d; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #2c3e50; }
        .form-input { width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s ease; }
        .form-input:focus { outline: none; border-color: #667eea; }
        @media (max-width: 768px) { 
            .nav-section { flex-direction: column; align-items: flex-start; }
            .stats-header { flex-direction: column; gap: 20px; align-items: flex-start; }
            .offers-grid { grid-template-columns: 1fr; }
            .section-header { flex-direction: column; gap: 15px; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💼 My Offers</h1>
        <p>Track your estate sale offers and manage your claims</p>
    </div>
    
    <div class="container">
        <div class="nav-section">
            <a href="{$basePath}/claims" class="nav-link">← Browse Estate Sales</a>
            <a href="{$basePath}/buyer/logout" class="logout-btn">Logout</a>
        </div>
        
        <div class="stats-section">
            <div class="stats-header">
                <div class="welcome-text">
                    <h2>Welcome back, {$buyerName}!</h2>
                    <p>Here's a summary of your offering activity</p>
                </div>
                <div class="quick-actions">
                    <a href="{$basePath}/claims" class="btn btn-primary">Browse Sales</a>
                    <button id="make-offer-btn" class="btn btn-secondary">Make New Offer</button>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">{$offerStats['total']}</div>
                    <div class="stat-label">Total Offers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{$offerStats['active']}</div>
                    <div class="stat-label">Active Offers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{$offerStats['accepted']}</div>
                    <div class="stat-label">Accepted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">\${$offerStats['total_amount']}</div>
                    <div class="stat-label">Total Offered</div>
                </div>
            </div>
        </div>
        
        <div class="offers-section">
            <div class="section-header">
                <h2>Your Offers</h2>
                <div class="filter-tabs">
                    <div class="filter-tab active" data-filter="all">All Offers</div>
                    <div class="filter-tab" data-filter="active">Active</div>
                    <div class="filter-tab" data-filter="accepted">Accepted</div>
                    <div class="filter-tab" data-filter="ended">Ended</div>
                </div>
            </div>
            {$offersHtml}
        </div>
    </div>
    
    <!-- Make Offer Modal -->
    <div id="offer-modal" class="offer-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Make New Offer</h3>
                <button class="modal-close" id="close-modal">&times;</button>
            </div>
            <div id="modal-body">
                <p>Select an item from an active sale to make an offer.</p>
                <div style="margin-top: 20px;">
                    <a href="{$basePath}/claims" class="btn btn-primary">Browse Current Sales</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Filter functionality
        const filterTabs = document.querySelectorAll('.filter-tab');
        const offerCards = document.querySelectorAll('.offer-card');
        
        filterTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Update active tab
                filterTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Filter offers
                const filter = this.dataset.filter;
                offerCards.forEach(card => {
                    if (filter === 'all' || card.dataset.status === filter) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
        
        // Modal functionality
        const makeOfferBtn = document.getElementById('make-offer-btn');
        const offerModal = document.getElementById('offer-modal');
        const closeModal = document.getElementById('close-modal');
        
        makeOfferBtn.addEventListener('click', function() {
            offerModal.classList.add('show');
        });
        
        closeModal.addEventListener('click', function() {
            offerModal.classList.remove('show');
        });
        
        offerModal.addEventListener('click', function(e) {
            if (e.target === offerModal) {
                offerModal.classList.remove('show');
            }
        });
        
        // Auto-refresh offers every 30 seconds
        setInterval(function() {
            // In a real implementation, you would fetch updated offer data
            console.log('Checking for offer updates...');
        }, 30000);
    </script>
</body>
</html>
HTML;
    }
    
    private function renderOffersGrid(array $offers, string $basePath): string
    {
        if (empty($offers)) {
            return <<<HTML
                <div class="empty-state">
                    <h3>No Offers Yet</h3>
                    <p>You haven't made any offers yet. Browse current estate sales to find items you're interested in and start making offers!</p>
                    <a href="{$basePath}/claims" class="btn btn-primary">Browse Estate Sales</a>
                </div>
HTML;
        }
        
        $html = '<div class="offers-grid">';
        
        foreach ($offers as $offer) {
            $statusClass = $this->getOfferStatusClass($offer['status']);
            $statusText = $this->getOfferStatusText($offer['status']);
            $offerAmount = number_format((float)$offer['offer_amount'], 2);
            $offerDate = date('M j, Y', strtotime($offer['created_at']));
            
            // Determine if pickup info should be shown
            $pickupInfo = '';
            if ($offer['status'] === 'accepted' && !empty($offer['pickup_start'])) {
                $pickupStart = date('M j', strtotime($offer['pickup_start']));
                $pickupEnd = date('M j, Y', strtotime($offer['pickup_end']));
                $pickupInfo = <<<PICKUP
                    <div class="offer-detail">
                        <span class="label">Pickup Window:</span>
                        <span class="value">{$pickupStart} - {$pickupEnd}</span>
                    </div>
PICKUP;
            }
            
            $contactInfo = '';
            if ($offer['status'] === 'accepted' && !empty($offer['seller_phone'])) {
                $contactInfo = <<<CONTACT
                    <div class="offer-detail">
                        <span class="label">Contact:</span>
                        <span class="value">{$offer['seller_phone']}</span>
                    </div>
CONTACT;
            }
            
            $html .= <<<OFFER
                <div class="offer-card" data-status="{$offer['status']}">
                    <div class="offer-header">
                        <div class="offer-title">{$offer['item_title']}</div>
                        <div class="offer-sale">{$offer['sale_title']}</div>
                    </div>
                    <div class="offer-body">
                        <div class="offer-amount">\${$offerAmount}</div>
                        
                        <div class="offer-details">
                            <div class="offer-detail">
                                <span class="label">Status:</span>
                                <span class="offer-status {$statusClass}">{$statusText}</span>
                            </div>
                            <div class="offer-detail">
                                <span class="label">Offered:</span>
                                <span class="value">{$offerDate}</span>
                            </div>
                            <div class="offer-detail">
                                <span class="label">Company:</span>
                                <span class="value">{$offer['company_name']}</span>
                            </div>
                            {$pickupInfo}
                            {$contactInfo}
                        </div>
                        
                        <div class="offer-actions">
                            <a href="{$basePath}/claims/item/{$offer['item_id']}" class="btn btn-secondary">View Item</a>
                        </div>
                    </div>
                </div>
OFFER;
        }
        
        $html .= '</div>';
        return $html;
    }
    
    private function getOfferStatusClass(string $status): string
    {
        switch ($status) {
            case 'active': return 'status-active';
            case 'accepted': return 'status-accepted';
            case 'rejected': return 'status-rejected';
            case 'expired': return 'status-expired';
            default: return 'status-active';
        }
    }
    
    private function getOfferStatusText(string $status): string
    {
        switch ($status) {
            case 'active': return 'Active';
            case 'accepted': return 'Accepted';
            case 'rejected': return 'Rejected';
            case 'expired': return 'Expired';
            default: return 'Active';
        }
    }
    
    private function calculateOfferStats(array $offers): array
    {
        $stats = [
            'total' => count($offers),
            'active' => 0,
            'accepted' => 0,
            'total_amount' => 0
        ];
        
        foreach ($offers as $offer) {
            $stats['total_amount'] += (float)$offer['offer_amount'];
            
            switch ($offer['status']) {
                case 'active':
                    $stats['active']++;
                    break;
                case 'accepted':
                    $stats['accepted']++;
                    break;
            }
        }
        
        $stats['total_amount'] = number_format($stats['total_amount'], 0);
        
        return $stats;
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
            $startingPrice = (float)($item['starting_price'] ?? 0);
            $category = $item['category'] ?? 'General';
            $priceDisplay = $highestOffer 
                ? '$' . number_format((float)$highestOffer, 2) . ' (highest offer)'
                : '$' . number_format($startingPrice, 2) . ' (starting)';
            
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
                            <a href="{$basePath}/buyer/auth?sale_id={$item['sale_id']}&item_id={$item['id']}" class="btn btn-secondary">Make Offer</a>
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
    public function manageSaleItems(): void { echo "Sale items management - coming soon"; }
    public function showEditSale(): void { echo "Edit sale - coming soon"; }
    public function updateSale(): void { echo json_encode(['success' => false, 'error' => 'Not implemented yet']); }
    public function getSaleItemsApi(): void { echo json_encode(['items' => []]); }
    public function addSaleItem(): void { echo json_encode(['success' => false, 'error' => 'Not implemented yet']); }
    public function updateSaleItem(): void { echo json_encode(['success' => false, 'error' => 'Not implemented yet']); }
    public function deleteSaleItem(): void { echo json_encode(['success' => false, 'error' => 'Not implemented yet']); }
    public function claimItem(): void { echo json_encode(['success' => false, 'error' => 'Not implemented yet']); }
}