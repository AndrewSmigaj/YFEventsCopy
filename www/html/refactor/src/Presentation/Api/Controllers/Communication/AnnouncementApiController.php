<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Api\Controllers\Communication;

use YFEvents\Application\Services\Communication\CommunicationService;

/**
 * Announcement API controller
 */
class AnnouncementApiController
{
    private CommunicationService $communicationService;
    
    public function __construct(CommunicationService $communicationService)
    {
        $this->communicationService = $communicationService;
    }
    
    /**
     * Get announcements for the current user
     */
    public function index(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
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
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => $formattedAnnouncements,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit
                ]
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Create a new announcement
     */
    public function create(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            $data = $this->getJsonInput();
            
            // Check if user has permission to create announcements
            if (!$this->userCanCreateAnnouncements($userId)) {
                $this->sendErrorResponse('Insufficient permissions', 403);
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
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => $summary,
                'message' => sprintf('Announcement posted to %d channels', count($results))
            ], 201);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Get announcement statistics
     */
    public function stats(): void
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // Get ID from $_GET
            if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
                $this->sendErrorResponse('Announcement ID is required', 400);
                return;
            }
            
            $id = (int)$_GET['id'];
            
            // Check if user has permission to view stats
            if (!$this->userCanViewAnnouncementStats($userId)) {
                $this->sendErrorResponse('Insufficient permissions', 403);
                return;
            }
            
            // This method would need to be added to the service
            $stats = $this->communicationService->getAnnouncementStats($id);
            
            $this->sendJsonResponse([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
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
        session_start();
        return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'editor']);
    }
    
    /**
     * Check if user can view announcement stats
     */
    private function userCanViewAnnouncementStats(int $userId): bool
    {
        session_start();
        return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'editor']);
    }
    
    /**
     * Get current user ID from session
     */
    private function getCurrentUserId(): int
    {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            throw new \RuntimeException('Not authenticated');
        }
        
        return (int)$_SESSION['user_id'];
    }
    
    /**
     * Get JSON input from request
     */
    private function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON input');
        }
        
        return $data ?? [];
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Send error response
     */
    private function sendErrorResponse(string $message, int $statusCode = 400): void
    {
        $this->sendJsonResponse([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }
}