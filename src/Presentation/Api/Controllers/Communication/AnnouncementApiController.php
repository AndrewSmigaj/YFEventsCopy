<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Api\Controllers\Communication;

use YFEvents\Presentation\Http\Controllers\BaseController;
use YFEvents\Application\Services\Communication\CommunicationService;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Announcement API controller
 */
class AnnouncementApiController extends BaseController
{
    private CommunicationService $communicationService;
    
    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->communicationService = $container->resolve(CommunicationService::class);
        
        // Set CORS headers for API
        $this->setCorsHeaders();
    }
    
    /**
     * Get announcements for the current user
     */
    public function index(): void
    {
        try {
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            $input = $this->getInput();
            $page = (int) ($input['page'] ?? 1);
            $limit = min(50, max(1, (int) ($input['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $announcements = $this->communicationService->getUserAnnouncements($userId, $limit, $offset);
            
            $formattedAnnouncements = [];
            foreach ($announcements as $announcement) {
                $message = $announcement['message'];
                $channel = $announcement['channel'];
                
                $formattedAnnouncements[] = [
                    'id' => $message->getId(),
                    'title' => $this->extractTitle($message->getContent()),
                    'content' => $message->getContent(),
                    'channel' => [
                        'id' => $channel->getId(),
                        'name' => $channel->getName(),
                        'type' => $channel->getType()->getValue()
                    ],
                    'is_read' => $announcement['is_read'],
                    'created_at' => $message->getCreatedAt()->format('c')
                ];
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $formattedAnnouncements,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Create a new announcement
     */
    public function create(): void
    {
        try {
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            $data = $this->getInput();
            
            // Check if user has permission to create announcements
            if (!$this->userCanCreateAnnouncements($userId)) {
                $this->errorResponse('Insufficient permissions', 403);
                return;
            }
            
            $data['author_id'] = $userId;
            $type = $data['type'] ?? 'system';
            
            $results = $this->communicationService->createAnnouncement($type, $data);
            
            $summary = [
                'channels_posted' => count($results),
                'message_ids' => array_map(function($result) {
                    return $result['message']->getId();
                }, $results)
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => $summary,
                'message' => sprintf('Announcement posted to %d channels', count($results))
            ], 201);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Get announcement statistics
     */
    public function stats(): void
    {
        try {
            $input = $this->getInput();
            if (!isset($input['id'])) {
                $this->errorResponse('Announcement ID is required');
                return;
            }
            $id = (int) $input['id'];
            if (!$this->requireAuth()) {
                $this->errorResponse('Authentication required', 401);
                return;
            }
            $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
            if (!$userId) {
                $this->errorResponse('Invalid session', 401);
                return;
            }
            
            // Check if user has permission to view stats
            if (!$this->userCanViewAnnouncementStats($userId)) {
                $this->errorResponse('Insufficient permissions', 403);
                return;
            }
            
            // This method would need to be added to the service
            $stats = $this->communicationService->getAnnouncementStats($id);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $stats
            ]);
        } catch (Exception $e) {
            $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Extract title from announcement content
     */
    private function extractTitle(string $content): string
    {
        // Announcements are formatted as **Title**\n\nContent
        if (preg_match('/^\*\*(.+?)\*\*/', $content, $matches)) {
            return $matches[1];
        }
        
        // Fallback to first line
        $lines = explode("\n", $content);
        return substr($lines[0], 0, 100);
    }
    
    /**
     * Check if user can create announcements
     */
    private function userCanCreateAnnouncements(int $userId): bool
    {
        // In practice, this would check user roles/permissions
        // For now, we'll use a simple check
        return isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'editor']);
    }
    
    /**
     * Check if user can view announcement stats
     */
    private function userCanViewAnnouncementStats(int $userId): bool
    {
        return isset($_SESSION['auth']['role']) && in_array($_SESSION['auth']['role'], ['admin', 'editor']);
    }
    
    /**
     * Set CORS headers for API access
     */
    private function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}