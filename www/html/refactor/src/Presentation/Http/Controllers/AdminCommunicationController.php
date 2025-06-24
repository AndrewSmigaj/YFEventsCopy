<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Application\Services\Communication\CommunicationService;
use YFEvents\Domain\Communication\Services\ChannelService;
use YFEvents\Domain\Communication\Services\MessageService;
use YFEvents\Domain\Communication\Services\AnnouncementService;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use Exception;
use PDO;

/**
 * Admin controller for communication module management
 */
class AdminCommunicationController extends BaseController
{
    private CommunicationService $communicationService;
    private ChannelService $channelService;
    private MessageService $messageService;
    private AnnouncementService $announcementService;
    private PDO $pdo;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->communicationService = $container->resolve(CommunicationService::class);
        $this->channelService = $container->resolve(ChannelService::class);
        $this->messageService = $container->resolve(MessageService::class);
        $this->announcementService = $container->resolve(AnnouncementService::class);
        $connection = $container->resolve(ConnectionInterface::class);
        $this->pdo = $connection->getConnection();
    }

    /**
     * Show communication admin dashboard
     */
    public function index(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderCommunicationPage($basePath);
    }

    /**
     * Get communication statistics
     */
    public function getStatistics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $stats = [
                'overview' => $this->getOverviewStats(),
                'activity' => $this->getActivityStats(),
                'channels' => $this->getChannelStats(),
                'users' => $this->getUserStats(),
                'engagement' => $this->getEngagementStats()
            ];

            $this->successResponse([
                'statistics' => $stats
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all channels with details
     */
    public function getChannels(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $sql = "
                SELECT 
                    c.*,
                    COUNT(DISTINCT cp.user_id) as participant_count,
                    COUNT(DISTINCT m.id) as message_count,
                    MAX(m.created_at) as last_activity
                FROM comm_channels c
                LEFT JOIN comm_channel_participants cp ON c.id = cp.channel_id
                LEFT JOIN comm_messages m ON c.id = m.channel_id
                GROUP BY c.id
                ORDER BY c.sort_order, c.name
            ";
            
            $stmt = $this->pdo->query($sql);
            $channels = $stmt->fetchAll();

            $this->successResponse([
                'channels' => $channels
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load channels: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create new channel
     */
    public function createChannel(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            
            $channel = $this->channelService->createChannel(
                $input['name'] ?? '',
                $input['description'] ?? '',
                $input['type'] ?? 'public',
                $_SESSION['user_id'] ?? 1
            );

            $this->successResponse([
                'message' => 'Channel created successfully',
                'channel' => $channel->toArray()
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to create channel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update channel
     */
    public function updateChannel(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $channelId = (int)($_GET['id'] ?? 0);
            $input = $this->getInput();

            if ($channelId <= 0) {
                $this->errorResponse('Invalid channel ID');
                return;
            }

            $stmt = $this->pdo->prepare("
                UPDATE comm_channels 
                SET 
                    name = :name,
                    description = :description,
                    type = :type,
                    is_active = :is_active,
                    allows_reactions = :allows_reactions,
                    allows_threads = :allows_threads,
                    allows_files = :allows_files,
                    max_participants = :max_participants,
                    sort_order = :sort_order,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $channelId,
                'name' => $input['name'] ?? '',
                'description' => $input['description'] ?? '',
                'type' => $input['type'] ?? 'public',
                'is_active' => $input['is_active'] ?? true,
                'allows_reactions' => $input['allows_reactions'] ?? true,
                'allows_threads' => $input['allows_threads'] ?? true,
                'allows_files' => $input['allows_files'] ?? true,
                'max_participants' => $input['max_participants'] ?? null,
                'sort_order' => $input['sort_order'] ?? 0
            ]);

            $this->successResponse([
                'message' => 'Channel updated successfully',
                'channel_id' => $channelId
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to update channel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete channel
     */
    public function deleteChannel(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $channelId = (int)($_GET['id'] ?? 0);

            if ($channelId <= 0) {
                $this->errorResponse('Invalid channel ID');
                return;
            }

            // Check if channel can be deleted
            $stmt = $this->pdo->prepare("SELECT type, name FROM comm_channels WHERE id = :id");
            $stmt->execute(['id' => $channelId]);
            $channel = $stmt->fetch();

            if (!$channel) {
                $this->errorResponse('Channel not found');
                return;
            }

            if ($channel['type'] === 'system') {
                $this->errorResponse('System channels cannot be deleted');
                return;
            }

            // Delete channel (cascades to related tables)
            $stmt = $this->pdo->prepare("DELETE FROM comm_channels WHERE id = :id");
            $stmt->execute(['id' => $channelId]);

            $this->successResponse([
                'message' => "Channel '{$channel['name']}' deleted successfully"
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to delete channel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get channel participants
     */
    public function getParticipants(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $channelId = (int)($_GET['channel_id'] ?? 0);

            if ($channelId <= 0) {
                $this->errorResponse('Invalid channel ID');
                return;
            }

            $sql = "
                SELECT 
                    cp.*,
                    u.username,
                    u.email,
                    COUNT(DISTINCT m.id) as message_count,
                    MAX(m.created_at) as last_message
                FROM comm_channel_participants cp
                JOIN users u ON cp.user_id = u.id
                LEFT JOIN comm_messages m ON m.channel_id = cp.channel_id AND m.user_id = cp.user_id
                WHERE cp.channel_id = :channel_id
                GROUP BY cp.id
                ORDER BY cp.joined_at DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['channel_id' => $channelId]);
            $participants = $stmt->fetchAll();

            $this->successResponse([
                'participants' => $participants
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load participants: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add participant to channel
     */
    public function addParticipant(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $channelId = (int)($input['channel_id'] ?? 0);
            $userId = (int)($input['user_id'] ?? 0);
            $role = $input['role'] ?? 'member';

            if ($channelId <= 0 || $userId <= 0) {
                $this->errorResponse('Invalid channel or user ID');
                return;
            }

            $this->channelService->addParticipant($channelId, $userId, $role);

            $this->successResponse([
                'message' => 'Participant added successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to add participant: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove participant from channel
     */
    public function removeParticipant(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $channelId = (int)($input['channel_id'] ?? 0);
            $userId = (int)($input['user_id'] ?? 0);

            if ($channelId <= 0 || $userId <= 0) {
                $this->errorResponse('Invalid channel or user ID');
                return;
            }

            $this->channelService->removeParticipant($channelId, $userId);

            $this->successResponse([
                'message' => 'Participant removed successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to remove participant: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get recent messages for moderation
     */
    public function getMessages(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
            $channelId = $_GET['channel_id'] ?? null;
            $userId = $_GET['user_id'] ?? null;
            $flagged = isset($_GET['flagged']) && $_GET['flagged'] === 'true';

            $sql = "
                SELECT 
                    m.*,
                    u.username,
                    c.name as channel_name,
                    COUNT(DISTINCT a.id) as attachment_count
                FROM comm_messages m
                JOIN users u ON m.user_id = u.id
                JOIN comm_channels c ON m.channel_id = c.id
                LEFT JOIN comm_message_attachments a ON m.id = a.message_id
                WHERE 1=1
            ";

            $params = [];

            if ($channelId) {
                $sql .= " AND m.channel_id = :channel_id";
                $params['channel_id'] = $channelId;
            }

            if ($userId) {
                $sql .= " AND m.user_id = :user_id";
                $params['user_id'] = $userId;
            }

            if ($flagged) {
                $sql .= " AND m.is_flagged = 1";
            }

            $sql .= " GROUP BY m.id ORDER BY m.created_at DESC LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $messages = $stmt->fetchAll();

            $this->successResponse([
                'messages' => $messages,
                'count' => count($messages)
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load messages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete message
     */
    public function deleteMessage(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $messageId = (int)($_GET['id'] ?? 0);

            if ($messageId <= 0) {
                $this->errorResponse('Invalid message ID');
                return;
            }

            $this->messageService->deleteMessage($messageId, $_SESSION['user_id'] ?? 1);

            $this->successResponse([
                'message' => 'Message deleted successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to delete message: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Flag/unflag message
     */
    public function toggleMessageFlag(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $messageId = (int)($_GET['id'] ?? 0);

            if ($messageId <= 0) {
                $this->errorResponse('Invalid message ID');
                return;
            }

            $stmt = $this->pdo->prepare("
                UPDATE comm_messages 
                SET is_flagged = NOT is_flagged 
                WHERE id = :id
            ");
            $stmt->execute(['id' => $messageId]);

            $this->successResponse([
                'message' => 'Message flag toggled successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to toggle message flag: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get announcements
     */
    public function getAnnouncements(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $sql = "
                SELECT 
                    a.*,
                    u.username as author,
                    COUNT(DISTINCT ad.user_id) as dismissal_count
                FROM comm_announcements a
                JOIN users u ON a.created_by = u.id
                LEFT JOIN comm_announcement_dismissals ad ON a.id = ad.announcement_id
                GROUP BY a.id
                ORDER BY a.created_at DESC
            ";

            $stmt = $this->pdo->query($sql);
            $announcements = $stmt->fetchAll();

            $this->successResponse([
                'announcements' => $announcements
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load announcements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create announcement
     */
    public function createAnnouncement(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();

            $announcement = $this->announcementService->createAnnouncement(
                $input['title'] ?? '',
                $input['content'] ?? '',
                $input['type'] ?? 'info',
                $_SESSION['user_id'] ?? 1,
                $input['expires_at'] ?? null,
                $input['is_dismissible'] ?? true,
                $input['priority'] ?? 0
            );

            $this->successResponse([
                'message' => 'Announcement created successfully',
                'announcement' => $announcement
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to create announcement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update announcement
     */
    public function updateAnnouncement(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $announcementId = (int)($_GET['id'] ?? 0);
            $input = $this->getInput();

            if ($announcementId <= 0) {
                $this->errorResponse('Invalid announcement ID');
                return;
            }

            $announcement = $this->announcementService->updateAnnouncement(
                $announcementId,
                $input['title'] ?? null,
                $input['content'] ?? null,
                $input['type'] ?? null,
                $input['expires_at'] ?? null,
                $input['is_dismissible'] ?? null,
                $input['priority'] ?? null
            );

            $this->successResponse([
                'message' => 'Announcement updated successfully',
                'announcement' => $announcement
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to update announcement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete announcement
     */
    public function deleteAnnouncement(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $announcementId = (int)($_GET['id'] ?? 0);

            if ($announcementId <= 0) {
                $this->errorResponse('Invalid announcement ID');
                return;
            }

            $this->announcementService->deleteAnnouncement($announcementId);

            $this->successResponse([
                'message' => 'Announcement deleted successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to delete announcement: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get notification settings
     */
    public function getNotificationSettings(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $sql = "
                SELECT 
                    COUNT(DISTINCT user_id) as total_users,
                    SUM(email_enabled) as email_enabled_count,
                    SUM(push_enabled) as push_enabled_count,
                    SUM(email_digest_enabled) as digest_enabled_count,
                    AVG(CASE 
                        WHEN email_digest_frequency = 'daily' THEN 1
                        WHEN email_digest_frequency = 'weekly' THEN 7
                        WHEN email_digest_frequency = 'monthly' THEN 30
                        ELSE 0
                    END) as avg_digest_frequency_days
                FROM comm_notification_preferences
            ";

            $stmt = $this->pdo->query($sql);
            $stats = $stmt->fetch();

            $this->successResponse([
                'notification_settings' => $stats
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load notification settings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats(): array
    {
        $stats = [];

        // Total channels
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM comm_channels WHERE is_active = 1");
        $stats['active_channels'] = (int)$stmt->fetchColumn();

        // Total users
        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT user_id) FROM comm_channel_participants");
        $stats['total_users'] = (int)$stmt->fetchColumn();

        // Messages today
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM comm_messages 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stats['messages_today'] = (int)$stmt->fetchColumn();

        // Active announcements
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM comm_announcements 
            WHERE (expires_at IS NULL OR expires_at > NOW())
        ");
        $stats['active_announcements'] = (int)$stmt->fetchColumn();

        // Total messages
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM comm_messages");
        $stats['total_messages'] = (int)$stmt->fetchColumn();

        // Total attachments
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM comm_message_attachments");
        $stats['total_attachments'] = (int)$stmt->fetchColumn();

        return $stats;
    }

    /**
     * Get activity statistics
     */
    private function getActivityStats(): array
    {
        $stats = [];

        // Messages per day (last 7 days)
        $stmt = $this->pdo->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM comm_messages
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stats['messages_per_day'] = $stmt->fetchAll();

        // Active users per day (last 7 days)
        $stmt = $this->pdo->query("
            SELECT 
                DATE(created_at) as date,
                COUNT(DISTINCT user_id) as count
            FROM comm_messages
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stats['active_users_per_day'] = $stmt->fetchAll();

        // Peak hours
        $stmt = $this->pdo->query("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as count
            FROM comm_messages
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY HOUR(created_at)
            ORDER BY count DESC
            LIMIT 5
        ");
        $stats['peak_hours'] = $stmt->fetchAll();

        return $stats;
    }

    /**
     * Get channel statistics
     */
    private function getChannelStats(): array
    {
        $stats = [];

        // Most active channels
        $stmt = $this->pdo->query("
            SELECT 
                c.name,
                c.type,
                COUNT(m.id) as message_count,
                COUNT(DISTINCT m.user_id) as active_users
            FROM comm_channels c
            LEFT JOIN comm_messages m ON c.id = m.channel_id 
                AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY message_count DESC
            LIMIT 10
        ");
        $stats['most_active'] = $stmt->fetchAll();

        // Channel types
        $stmt = $this->pdo->query("
            SELECT 
                type,
                COUNT(*) as count
            FROM comm_channels
            GROUP BY type
        ");
        $stats['by_type'] = $stmt->fetchAll();

        return $stats;
    }

    /**
     * Get user statistics
     */
    private function getUserStats(): array
    {
        $stats = [];

        // Most active users
        $stmt = $this->pdo->query("
            SELECT 
                u.username,
                COUNT(m.id) as message_count,
                COUNT(DISTINCT m.channel_id) as channels_active
            FROM users u
            JOIN comm_messages m ON u.id = m.user_id
            WHERE m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY u.id
            ORDER BY message_count DESC
            LIMIT 10
        ");
        $stats['most_active'] = $stmt->fetchAll();

        // User roles distribution
        $stmt = $this->pdo->query("
            SELECT 
                role,
                COUNT(*) as count
            FROM comm_channel_participants
            GROUP BY role
        ");
        $stats['by_role'] = $stmt->fetchAll();

        return $stats;
    }

    /**
     * Get engagement statistics
     */
    private function getEngagementStats(): array
    {
        $stats = [];

        // Average messages per user
        $stmt = $this->pdo->query("
            SELECT 
                AVG(message_count) as avg_messages
            FROM (
                SELECT COUNT(*) as message_count
                FROM comm_messages
                GROUP BY user_id
            ) as user_messages
        ");
        $stats['avg_messages_per_user'] = round((float)$stmt->fetchColumn(), 2);

        // Engagement rate (users who sent at least 1 message in last 30 days)
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(DISTINCT m.user_id) * 100.0 / COUNT(DISTINCT cp.user_id) as engagement_rate
            FROM comm_channel_participants cp
            LEFT JOIN comm_messages m ON cp.user_id = m.user_id 
                AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['engagement_rate'] = round((float)$stmt->fetchColumn(), 2);

        // Notification preferences
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(CASE WHEN email_enabled = 1 THEN 1 END) * 100.0 / COUNT(*) as email_opt_in,
                COUNT(CASE WHEN push_enabled = 1 THEN 1 END) * 100.0 / COUNT(*) as push_opt_in
            FROM comm_notification_preferences
        ");
        $prefs = $stmt->fetch();
        $stats['email_opt_in_rate'] = round((float)($prefs['email_opt_in'] ?? 0), 2);
        $stats['push_opt_in_rate'] = round((float)($prefs['push_opt_in'] ?? 0), 2);

        return $stats;
    }

    private function renderCommunicationPage(string $basePath): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $username = $_SESSION['admin_username'] ?? 'admin';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Hub Admin - YFEvents</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            display: flex;
            height: calc(100vh - 70px);
        }
        
        .sidebar {
            width: 250px;
            background: white;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .nav-item {
            display: block;
            padding: 12px 16px;
            margin-bottom: 5px;
            color: #495057;
            text-decoration: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .nav-item:hover {
            background: #f1f3f5;
            color: #17a2b8;
        }
        
        .nav-item.active {
            background: #17a2b8;
            color: white;
        }
        
        .content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
        
        .section {
            display: none;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .section.active {
            display: block;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #17a2b8;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-public {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-private {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-system {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #17a2b8;
            color: white;
        }
        
        .btn-primary:hover {
            background: #138496;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #17a2b8;
            box-shadow: 0 0 0 2px rgba(23, 162, 184, 0.25);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .chart-container {
            margin-top: 30px;
            height: 300px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #343a40;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        .close:hover {
            color: #343a40;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💬 Communication Hub Admin</h1>
        <div class="user-info">
            <span>Welcome, {$username}</span>
            <a href="{$basePath}/admin/dashboard" class="back-btn">← Back to Dashboard</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a class="nav-item active" data-section="overview" onclick="showSection('overview')">📊 Overview</a>
            <a class="nav-item" data-section="channels" onclick="showSection('channels')">📢 Channels</a>
            <a class="nav-item" data-section="messages" onclick="showSection('messages')">💬 Messages</a>
            <a class="nav-item" data-section="users" onclick="showSection('users')">👥 Users</a>
            <a class="nav-item" data-section="announcements" onclick="showSection('announcements')">📣 Announcements</a>
            <a class="nav-item" data-section="statistics" onclick="showSection('statistics')">📈 Statistics</a>
            <a class="nav-item" data-section="settings" onclick="showSection('settings')">⚙️ Settings</a>
        </div>
        
        <div class="content">
            <div id="alert" class="alert"></div>
            
            <!-- Overview Section -->
            <div id="overview-section" class="section active">
                <h2 class="section-title">Communication Hub Overview</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="active-channels">-</div>
                        <div class="stat-label">Active Channels</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="total-users">-</div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="messages-today">-</div>
                        <div class="stat-label">Messages Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="active-announcements">-</div>
                        <div class="stat-label">Active Announcements</div>
                    </div>
                </div>
                
                <h3 style="margin-top: 30px; margin-bottom: 15px;">Recent Activity</h3>
                <div id="activity-chart" class="chart-container">
                    <div class="loading">Loading activity data...</div>
                </div>
                
                <h3 style="margin-top: 30px; margin-bottom: 15px;">Most Active Channels</h3>
                <table class="data-table" id="active-channels-table">
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th>Type</th>
                            <th>Messages (30d)</th>
                            <th>Active Users</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" class="loading">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Channels Section -->
            <div id="channels-section" class="section">
                <h2 class="section-title">Channel Management</h2>
                
                <div style="margin-bottom: 20px;">
                    <button class="btn btn-primary" onclick="showCreateChannelModal()">+ Create Channel</button>
                </div>
                
                <table class="data-table" id="channels-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Participants</th>
                            <th>Messages</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="6" class="loading">Loading channels...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Messages Section -->
            <div id="messages-section" class="section">
                <h2 class="section-title">Message Moderation</h2>
                
                <div style="margin-bottom: 20px;">
                    <label>
                        <input type="checkbox" id="flagged-only" onchange="loadMessages()">
                        Show flagged messages only
                    </label>
                </div>
                
                <table class="data-table" id="messages-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Channel</th>
                            <th>Message</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="5" class="loading">Loading messages...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Users Section -->
            <div id="users-section" class="section">
                <h2 class="section-title">User Activity</h2>
                
                <h3>Most Active Users (Last 30 Days)</h3>
                <table class="data-table" id="active-users-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Messages</th>
                            <th>Channels Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" class="loading">Loading users...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Announcements Section -->
            <div id="announcements-section" class="section">
                <h2 class="section-title">Announcement Management</h2>
                
                <div style="margin-bottom: 20px;">
                    <button class="btn btn-primary" onclick="showCreateAnnouncementModal()">+ Create Announcement</button>
                </div>
                
                <table class="data-table" id="announcements-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Author</th>
                            <th>Created</th>
                            <th>Expires</th>
                            <th>Dismissals</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="7" class="loading">Loading announcements...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Statistics Section -->
            <div id="statistics-section" class="section">
                <h2 class="section-title">Detailed Statistics</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="total-messages">-</div>
                        <div class="stat-label">Total Messages</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="total-attachments">-</div>
                        <div class="stat-label">Total Attachments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="engagement-rate">-</div>
                        <div class="stat-label">Engagement Rate</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="avg-messages-user">-</div>
                        <div class="stat-label">Avg Messages/User</div>
                    </div>
                </div>
                
                <h3 style="margin-top: 30px;">Peak Activity Hours</h3>
                <div id="peak-hours-chart" class="chart-container">
                    <div class="loading">Loading peak hours data...</div>
                </div>
                
                <h3 style="margin-top: 30px;">Channel Distribution</h3>
                <div id="channel-distribution" class="chart-container">
                    <div class="loading">Loading channel data...</div>
                </div>
            </div>
            
            <!-- Settings Section -->
            <div id="settings-section" class="section">
                <h2 class="section-title">Communication Settings</h2>
                
                <div class="form-group">
                    <label class="form-label">Max Message Length</label>
                    <input type="number" class="form-control" id="max-message-length" value="5000">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Max File Size (MB)</label>
                    <input type="number" class="form-control" id="max-file-size" value="10">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Allowed File Types</label>
                    <input type="text" class="form-control" id="allowed-file-types" value="jpg,png,gif,pdf,doc,docx">
                    <small style="color: #6c757d;">Comma-separated list of extensions</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="enable-notifications" checked>
                        Enable email notifications
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="enable-digest" checked>
                        Enable email digests
                    </label>
                </div>
                
                <button class="btn btn-primary" onclick="saveSettings()">Save Settings</button>
            </div>
        </div>
    </div>
    
    <!-- Create Channel Modal -->
    <div id="channel-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create Channel</h3>
                <span class="close" onclick="closeModal('channel-modal')">&times;</span>
            </div>
            <form id="channel-form">
                <div class="form-group">
                    <label class="form-label">Channel Name</label>
                    <input type="text" class="form-control" id="channel-name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="channel-description"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-control" id="channel-type">
                        <option value="public">Public</option>
                        <option value="private">Private</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create Channel</button>
            </form>
        </div>
    </div>
    
    <!-- Create Announcement Modal -->
    <div id="announcement-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create Announcement</h3>
                <span class="close" onclick="closeModal('announcement-modal')">&times;</span>
            </div>
            <form id="announcement-form">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control" id="announcement-title" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea class="form-control" id="announcement-content" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-control" id="announcement-type">
                        <option value="info">Information</option>
                        <option value="warning">Warning</option>
                        <option value="success">Success</option>
                        <option value="error">Error</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <input type="number" class="form-control" id="announcement-priority" value="0">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="announcement-dismissible" checked>
                        Allow users to dismiss
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Create Announcement</button>
            </form>
        </div>
    </div>

    <script>
        let currentSection = 'overview';
        let statistics = null;
        
        // Show section
        function showSection(section) {
            currentSection = section;
            
            // Update nav
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-section="\${section}"]`).classList.add('active');
            
            // Update content
            document.querySelectorAll('.section').forEach(s => {
                s.classList.remove('active');
            });
            document.getElementById(`\${section}-section`).classList.add('active');
            
            // Load section data
            switch(section) {
                case 'overview':
                    loadStatistics();
                    break;
                case 'channels':
                    loadChannels();
                    break;
                case 'messages':
                    loadMessages();
                    break;
                case 'users':
                    loadUsers();
                    break;
                case 'announcements':
                    loadAnnouncements();
                    break;
                case 'statistics':
                    loadDetailedStatistics();
                    break;
            }
        }
        
        // Load statistics
        async function loadStatistics() {
            try {
                const response = await fetch('{$basePath}/api/communication/admin/statistics');
                const data = await response.json();
                
                if (data.success) {
                    statistics = data.data;
                    
                    // Update overview stats
                    document.getElementById('active-channels').textContent = statistics.channels || 0;
                    document.getElementById('total-users').textContent = statistics.users || 0;
                    document.getElementById('messages-today').textContent = statistics.messages_today || 0;
                    document.getElementById('active-announcements').textContent = statistics.announcements || 0;
                    
                    // Load most active channels
                    if (statistics.statistics?.channels?.most_active) {
                        displayActiveChannels(statistics.statistics.channels.most_active);
                    }
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }
        
        // Display active channels
        function displayActiveChannels(channels) {
            const tbody = document.querySelector('#active-channels-table tbody');
            
            if (channels.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">No active channels</td></tr>';
                return;
            }
            
            tbody.innerHTML = channels.map(channel => `
                <tr>
                    <td>\${channel.name}</td>
                    <td><span class="badge badge-\${channel.type}">\${channel.type}</span></td>
                    <td>\${channel.message_count || 0}</td>
                    <td>\${channel.active_users || 0}</td>
                </tr>
            `).join('');
        }
        
        // Load channels
        async function loadChannels() {
            try {
                const response = await fetch('{$basePath}/admin/communication/channels');
                const data = await response.json();
                
                if (data.success) {
                    displayChannels(data.data.channels);
                }
            } catch (error) {
                showAlert('Error loading channels: ' + error.message, 'error');
            }
        }
        
        // Display channels
        function displayChannels(channels) {
            const tbody = document.querySelector('#channels-table tbody');
            
            if (channels.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6">No channels found</td></tr>';
                return;
            }
            
            tbody.innerHTML = channels.map(channel => `
                <tr>
                    <td>\${channel.name}</td>
                    <td><span class="badge badge-\${channel.type}">\${channel.type}</span></td>
                    <td>\${channel.participant_count || 0}</td>
                    <td>\${channel.message_count || 0}</td>
                    <td><span class="badge badge-\${channel.is_active ? 'active' : 'inactive'}">\${channel.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td class="actions">
                        <button class="btn btn-sm btn-primary" onclick="editChannel(\${channel.id})">Edit</button>
                        \${channel.type !== 'system' ? `<button class="btn btn-sm btn-danger" onclick="deleteChannel(\${channel.id})">Delete</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }
        
        // Load messages
        async function loadMessages() {
            try {
                const flaggedOnly = document.getElementById('flagged-only').checked;
                const url = '{$basePath}/admin/communication/messages' + (flaggedOnly ? '?flagged=true' : '');
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    displayMessages(data.data.messages);
                }
            } catch (error) {
                showAlert('Error loading messages: ' + error.message, 'error');
            }
        }
        
        // Display messages
        function displayMessages(messages) {
            const tbody = document.querySelector('#messages-table tbody');
            
            if (messages.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5">No messages found</td></tr>';
                return;
            }
            
            tbody.innerHTML = messages.map(message => `
                <tr>
                    <td>\${message.username}</td>
                    <td>\${message.channel_name}</td>
                    <td>\${message.content.substring(0, 100)}\${message.content.length > 100 ? '...' : ''}</td>
                    <td>\${new Date(message.created_at).toLocaleString()}</td>
                    <td class="actions">
                        <button class="btn btn-sm btn-danger" onclick="deleteMessage(\${message.id})">Delete</button>
                        <button class="btn btn-sm btn-primary" onclick="toggleFlag(\${message.id})">\${message.is_flagged ? 'Unflag' : 'Flag'}</button>
                    </td>
                </tr>
            `).join('');
        }
        
        // Load users
        async function loadUsers() {
            if (statistics && statistics.statistics?.users?.most_active) {
                displayUsers(statistics.statistics.users.most_active);
            } else {
                // Reload statistics
                await loadStatistics();
                if (statistics && statistics.statistics?.users?.most_active) {
                    displayUsers(statistics.statistics.users.most_active);
                }
            }
        }
        
        // Display users
        function displayUsers(users) {
            const tbody = document.querySelector('#active-users-table tbody');
            
            if (users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">No active users</td></tr>';
                return;
            }
            
            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>\${user.username}</td>
                    <td>\${user.message_count}</td>
                    <td>\${user.channels_active}</td>
                    <td class="actions">
                        <button class="btn btn-sm btn-primary" onclick="viewUserMessages('\${user.username}')">View Messages</button>
                    </td>
                </tr>
            `).join('');
        }
        
        // Load announcements
        async function loadAnnouncements() {
            try {
                const response = await fetch('{$basePath}/admin/communication/announcements');
                const data = await response.json();
                
                if (data.success) {
                    displayAnnouncements(data.data.announcements);
                }
            } catch (error) {
                showAlert('Error loading announcements: ' + error.message, 'error');
            }
        }
        
        // Display announcements
        function displayAnnouncements(announcements) {
            const tbody = document.querySelector('#announcements-table tbody');
            
            if (announcements.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7">No announcements found</td></tr>';
                return;
            }
            
            tbody.innerHTML = announcements.map(announcement => `
                <tr>
                    <td>\${announcement.title}</td>
                    <td><span class="badge badge-\${announcement.type}">\${announcement.type}</span></td>
                    <td>\${announcement.author}</td>
                    <td>\${new Date(announcement.created_at).toLocaleDateString()}</td>
                    <td>\${announcement.expires_at ? new Date(announcement.expires_at).toLocaleDateString() : 'Never'}</td>
                    <td>\${announcement.dismissal_count || 0}</td>
                    <td class="actions">
                        <button class="btn btn-sm btn-danger" onclick="deleteAnnouncement(\${announcement.id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }
        
        // Load detailed statistics
        async function loadDetailedStatistics() {
            if (!statistics) {
                await loadStatistics();
            }
            
            if (statistics) {
                // Update detailed stats
                document.getElementById('total-messages').textContent = statistics.statistics?.overview?.total_messages || 0;
                document.getElementById('total-attachments').textContent = statistics.statistics?.overview?.total_attachments || 0;
                document.getElementById('engagement-rate').textContent = (statistics.statistics?.engagement?.engagement_rate || 0) + '%';
                document.getElementById('avg-messages-user').textContent = statistics.statistics?.engagement?.avg_messages_per_user || 0;
            }
        }
        
        // Delete channel
        async function deleteChannel(id) {
            if (!confirm('Are you sure you want to delete this channel?')) {
                return;
            }
            
            try {
                const response = await fetch(`{$basePath}/admin/communication/channels/\${id}`, {
                    method: 'DELETE'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Channel deleted successfully', 'success');
                    loadChannels();
                } else {
                    showAlert(data.error || 'Failed to delete channel', 'error');
                }
            } catch (error) {
                showAlert('Error deleting channel: ' + error.message, 'error');
            }
        }
        
        // Delete message
        async function deleteMessage(id) {
            if (!confirm('Are you sure you want to delete this message?')) {
                return;
            }
            
            try {
                const response = await fetch(`{$basePath}/admin/communication/messages/\${id}`, {
                    method: 'DELETE'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Message deleted successfully', 'success');
                    loadMessages();
                } else {
                    showAlert(data.error || 'Failed to delete message', 'error');
                }
            } catch (error) {
                showAlert('Error deleting message: ' + error.message, 'error');
            }
        }
        
        // Toggle message flag
        async function toggleFlag(id) {
            try {
                const response = await fetch(`{$basePath}/admin/communication/messages/\${id}/flag`, {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    loadMessages();
                } else {
                    showAlert(data.error || 'Failed to toggle flag', 'error');
                }
            } catch (error) {
                showAlert('Error toggling flag: ' + error.message, 'error');
            }
        }
        
        // Delete announcement
        async function deleteAnnouncement(id) {
            if (!confirm('Are you sure you want to delete this announcement?')) {
                return;
            }
            
            try {
                const response = await fetch(`{$basePath}/admin/communication/announcements/\${id}`, {
                    method: 'DELETE'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Announcement deleted successfully', 'success');
                    loadAnnouncements();
                } else {
                    showAlert(data.error || 'Failed to delete announcement', 'error');
                }
            } catch (error) {
                showAlert('Error deleting announcement: ' + error.message, 'error');
            }
        }
        
        // Show create channel modal
        function showCreateChannelModal() {
            document.getElementById('channel-modal').style.display = 'block';
        }
        
        // Show create announcement modal
        function showCreateAnnouncementModal() {
            document.getElementById('announcement-modal').style.display = 'block';
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Save settings
        async function saveSettings() {
            const settings = {
                max_message_length: document.getElementById('max-message-length').value,
                max_file_size: document.getElementById('max-file-size').value * 1048576, // Convert to bytes
                allowed_file_types: document.getElementById('allowed-file-types').value.split(',').map(t => t.trim()),
                enable_notifications: document.getElementById('enable-notifications').checked,
                enable_email_digest: document.getElementById('enable-digest').checked
            };
            
            try {
                const response = await fetch('{$basePath}/admin/settings/bulk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        settings: {
                            communication: settings
                        }
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Settings saved successfully', 'success');
                } else {
                    showAlert(data.error || 'Failed to save settings', 'error');
                }
            } catch (error) {
                showAlert('Error saving settings: ' + error.message, 'error');
            }
        }
        
        // Show alert
        function showAlert(message, type = 'info') {
            const alert = document.getElementById('alert');
            alert.className = `alert alert-\${type}`;
            alert.textContent = message;
            alert.style.display = 'block';
            
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }
        
        // Handle channel form submission
        document.getElementById('channel-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const channelData = {
                name: document.getElementById('channel-name').value,
                description: document.getElementById('channel-description').value,
                type: document.getElementById('channel-type').value
            };
            
            try {
                const response = await fetch('{$basePath}/admin/communication/channels', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(channelData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Channel created successfully', 'success');
                    closeModal('channel-modal');
                    loadChannels();
                    document.getElementById('channel-form').reset();
                } else {
                    showAlert(data.error || 'Failed to create channel', 'error');
                }
            } catch (error) {
                showAlert('Error creating channel: ' + error.message, 'error');
            }
        });
        
        // Handle announcement form submission
        document.getElementById('announcement-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const announcementData = {
                title: document.getElementById('announcement-title').value,
                content: document.getElementById('announcement-content').value,
                type: document.getElementById('announcement-type').value,
                priority: parseInt(document.getElementById('announcement-priority').value),
                is_dismissible: document.getElementById('announcement-dismissible').checked
            };
            
            try {
                const response = await fetch('{$basePath}/admin/communication/announcements', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(announcementData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Announcement created successfully', 'success');
                    closeModal('announcement-modal');
                    loadAnnouncements();
                    document.getElementById('announcement-form').reset();
                } else {
                    showAlert(data.error || 'Failed to create announcement', 'error');
                }
            } catch (error) {
                showAlert('Error creating announcement: ' + error.message, 'error');
            }
        });
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
        });
    </script>
</body>
</html>
HTML;
    }
}