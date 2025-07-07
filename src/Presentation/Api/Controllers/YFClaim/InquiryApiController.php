<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Api\Controllers\YFClaim;

use YFEvents\Presentation\Http\Controllers\BaseController;
use YFEvents\Application\Services\YFClaim\InquiryService;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * API controller for buyer inquiries
 */
class InquiryApiController extends BaseController
{
    private InquiryService $inquiryService;
    
    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->inquiryService = $container->resolve(InquiryService::class);
        
        // Set CORS headers for API
        $this->setCorsHeaders();
    }
    
    /**
     * Create a new inquiry (public endpoint)
     */
    public function create(): void
    {
        try {
            $input = $this->getInput();
            
            // Add request metadata
            $input['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
            $input['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $inquiry = $this->inquiryService->createInquiry($input);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Your inquiry has been sent successfully!',
                'data' => [
                    'inquiry_id' => $inquiry->getId(),
                    'reference_number' => 'INQ-' . str_pad((string)$inquiry->getId(), 6, '0', STR_PAD_LEFT)
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->errorResponse($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->errorResponse('Failed to send inquiry. Please try again later.', 500);
        }
    }
    
    /**
     * Get seller's inquiries (authenticated)
     */
    public function index(): void
    {
        try {
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            $sellerId = $this->getSellerUserId();
            if (!$sellerId) {
                $this->errorResponse('Seller access required', 403);
                return;
            }
            
            $input = $this->getInput();
            
            // Build filters
            $filters = [];
            if (!empty($input['status'])) {
                $filters['status'] = $input['status'];
            }
            if (!empty($input['item_id'])) {
                $filters['item_id'] = (int)$input['item_id'];
            }
            if (!empty($input['date_from'])) {
                $filters['date_from'] = $input['date_from'];
            }
            if (!empty($input['date_to'])) {
                $filters['date_to'] = $input['date_to'];
            }
            
            // Pagination
            $page = (int) ($input['page'] ?? 1);
            $limit = min(100, max(1, (int) ($input['limit'] ?? 20)));
            $filters['limit'] = $limit;
            $filters['offset'] = ($page - 1) * $limit;
            
            $inquiries = $this->inquiryService->getSellerInquiries($sellerId, $filters);
            
            // Format response
            $data = [];
            foreach ($inquiries as $inquiry) {
                $data[] = $this->formatInquiry($inquiry);
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($data) // Note: This is simplified, ideally get total count
                ]
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Get unread inquiry count (authenticated)
     */
    public function unreadCount(): void
    {
        try {
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            $sellerId = $this->getSellerUserId();
            if (!$sellerId) {
                $this->errorResponse('Seller access required', 403);
                return;
            }
            
            $count = $this->inquiryService->getUnreadCount($sellerId);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'unread_count' => $count
                ]
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Mark inquiry as read (authenticated)
     */
    public function markRead(): void
    {
        try {
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            $sellerId = $this->getSellerUserId();
            if (!$sellerId) {
                $this->errorResponse('Seller access required', 403);
                return;
            }
            
            // Get inquiry ID from URL parameter
            $inquiryId = (int) ($_GET['id'] ?? 0);
            if (!$inquiryId) {
                $this->errorResponse('Inquiry ID is required', 400);
                return;
            }
            
            $this->inquiryService->markAsRead($inquiryId, $sellerId);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Inquiry marked as read'
            ]);
        } catch (\UnauthorizedAccessException $e) {
            $this->errorResponse($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            $this->errorResponse($e->getMessage(), 404);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Update admin notes for inquiry (authenticated)
     */
    public function updateNotes(): void
    {
        try {
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            
            $sellerId = $this->getSellerUserId();
            if (!$sellerId) {
                $this->errorResponse('Seller access required', 403);
                return;
            }
            
            // Get inquiry ID from URL parameter
            $inquiryId = (int) ($_GET['id'] ?? 0);
            if (!$inquiryId) {
                $this->errorResponse('Inquiry ID is required', 400);
                return;
            }
            
            $input = $this->getInput();
            if (!isset($input['notes'])) {
                $this->errorResponse('Notes field is required', 400);
                return;
            }
            
            $this->inquiryService->addAdminNotes($inquiryId, $sellerId, $input['notes']);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Notes updated successfully'
            ]);
        } catch (\UnauthorizedAccessException $e) {
            $this->errorResponse($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            $this->errorResponse($e->getMessage(), 404);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Get seller user ID from session
     */
    private function getSellerUserId(): ?int
    {
        // Check YFAuth session
        if (isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['roles'])) {
            $userRoles = $_SESSION['auth']['roles'] ?? [];
            if (in_array('seller', $userRoles) || in_array('claim_seller', $userRoles) || in_array('admin', $userRoles)) {
                return (int) $_SESSION['auth']['user_id'];
            }
        }
        
        return null;
    }
    
    /**
     * Format inquiry for API response
     */
    private function formatInquiry($inquiry): array
    {
        $data = $inquiry->toArray();
        
        // Add formatted dates
        $data['created_at_formatted'] = $inquiry->getCreatedAt()->format('M j, Y g:i A');
        $data['is_new'] = $inquiry->isNew();
        
        // Add reference number
        $data['reference_number'] = 'INQ-' . str_pad((string)$inquiry->getId(), 6, '0', STR_PAD_LEFT);
        
        return $data;
    }
    
    /**
     * Set CORS headers for API endpoints
     */
    private function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}