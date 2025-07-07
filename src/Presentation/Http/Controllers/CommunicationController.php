<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;

/**
 * Communication controller for chat/messaging interface
 */
class CommunicationController extends BaseController
{
    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
    }

    /**
     * Display standalone communication hub
     */
    public function index(): void
    {
        // Check authentication
        if (!$this->requireAuth()) {
            header('Location: /login.php');
            exit;
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get user info from session (YFAuth takes precedence)
        $userId = (int) ($_SESSION['auth']['user_id'] ?? $_SESSION['user_id'] ?? 0);
        $userName = $_SESSION['auth']['username'] ?? $_SESSION['user_name'] ?? 'User';
        $userRole = $_SESSION['auth']['role'] ?? $_SESSION['user_role'] ?? 'user';
        
        if (!$userId) {
            header('Location: /login.php');
            exit;
        }
        
        // Render the communication page
        echo $this->renderCommunicationPage($userId, $userName, $userRole, false);
    }
    
    /**
     * Display embedded communication interface for seller dashboard
     */
    public function embedded(): void
    {
        // Get seller ID from query parameter
        $sellerId = isset($_GET['seller_id']) ? (int) $_GET['seller_id'] : null;
        
        if (!$sellerId) {
            http_response_code(400);
            echo 'Invalid seller ID';
            return;
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is authenticated as seller
        $isAuthenticated = false;
        $userId = 0;
        $userName = 'User';
        
        // Check standardized auth session
        if (isset($_SESSION['auth']['user_id']) && isset($_SESSION['auth']['roles'])) {
            $userRoles = $_SESSION['auth']['roles'] ?? [];
            
            // Check if user has seller role
            if (in_array('seller', $userRoles) || in_array('claim_seller', $userRoles) || in_array('admin', $userRoles)) {
                // Verify this is the correct seller
                if (isset($_SESSION['seller']['seller_id']) && $_SESSION['seller']['seller_id'] == $sellerId) {
                    $isAuthenticated = true;
                    $userId = (int) $_SESSION['auth']['user_id'];
                    $userName = $_SESSION['auth']['username'] ?? 'Seller';
                }
            }
        }
        
        if (!$isAuthenticated) {
            http_response_code(401);
            echo 'Unauthorized access';
            return;
        }
        
        // Render embedded version
        echo $this->renderCommunicationPage($userId, $userName, 'seller', true, $sellerId);
    }
    
    /**
     * Handle theme switching
     */
    public function themeSwitcher(): void
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $theme = $_POST['theme'] ?? 'auto';
            
            if (in_array($theme, ['mobile', 'desktop', 'auto'])) {
                if ($theme === 'auto') {
                    // Clear preference cookie
                    setcookie('theme_preference', '', time() - 3600, '/');
                } else {
                    // Set preference cookie for 30 days
                    setcookie('theme_preference', $theme, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                }
                
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'theme' => $theme]);
                return;
            } else {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid theme']);
                return;
            }
        }
        
        // Display theme switcher page
        $currentTheme = $_COOKIE['theme_preference'] ?? 'auto';
        ?>
<!DOCTYPE html>
<html>
<head>
    <title>Theme Settings - Communication Hub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/communication/css/communication.css">
    <style>
        .theme-settings {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .theme-option {
            padding: 1rem;
            margin: 0.5rem 0;
            border: 2px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .theme-option.selected {
            border-color: #007bff;
            background: #f0f8ff;
        }
        .theme-option:hover {
            border-color: #007bff;
        }
        .theme-actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="theme-settings">
        <h1>Theme Settings</h1>
        <p>Choose how the Communication Hub appears on your device.</p>
        
        <div class="theme-options">
            <div class="theme-option <?= $currentTheme === 'auto' ? 'selected' : '' ?>" onclick="selectTheme('auto')">
                <h3>Automatic</h3>
                <p>Automatically detect and use the best theme for your device</p>
            </div>
            
            <div class="theme-option <?= $currentTheme === 'mobile' ? 'selected' : '' ?>" onclick="selectTheme('mobile')">
                <h3>Mobile Theme</h3>
                <p>Always use the mobile-optimized interface</p>
            </div>
            
            <div class="theme-option <?= $currentTheme === 'desktop' ? 'selected' : '' ?>" onclick="selectTheme('desktop')">
                <h3>Desktop Theme</h3>
                <p>Always use the full desktop interface</p>
            </div>
        </div>
        
        <div class="theme-actions">
            <button class="btn btn-primary" onclick="saveTheme()">Save Changes</button>
            <button class="btn btn-secondary" onclick="window.location.href='/communication'">Cancel</button>
        </div>
    </div>
    
    <script>
        let selectedTheme = '<?= $currentTheme ?>';
        
        function selectTheme(theme) {
            selectedTheme = theme;
            document.querySelectorAll('.theme-option').forEach(el => {
                el.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }
        
        function saveTheme() {
            fetch('/communication/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'theme=' + selectedTheme
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Theme preference saved!');
                    window.location.href = '/communication';
                } else {
                    alert('Error saving theme preference');
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
    </script>
</body>
</html>
        <?php
    }
    
    /**
     * Render the communication page HTML
     */
    private function renderCommunicationPage(int $userId, string $userName, string $userRole, bool $isEmbedded, ?int $sellerId = null): string
    {
        $basePath = $this->config->get('app.base_path', '');
        $embedClass = $isEmbedded ? 'embedded-mode' : '';
        $embedParam = $isEmbedded ? '?embedded=true' : '';
        
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#007bff">
    <title>Communication Hub - YFEvents</title>
    
    <?php if (!$isEmbedded): ?>
    <!-- PWA Manifest -->
    <link rel="manifest" href="/assets/communication/manifest.json">
    
    <!-- Icons -->
    <link rel="icon" type="image/png" href="/assets/communication/icons/icon-96x96.png">
    <link rel="apple-touch-icon" href="/assets/communication/icons/icon-152x152.png">
    <?php endif; ?>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="/assets/communication/css/communication.css">
    <link rel="stylesheet" href="/assets/communication/css/mobile.css">
    
    <?php if ($isEmbedded): ?>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: transparent;
            height: 100vh;
            overflow: hidden;
        }
        .app-header {
            display: none;
        }
        .container-fluid {
            height: 100vh;
            max-width: none;
            margin: 0;
            border-radius: 0;
        }
        .content-wrapper {
            height: 100vh;
        }
    </style>
    <?php endif; ?>
</head>
<body class="<?= $embedClass ?>">
    <div class="container-fluid p-0 h-100">
        <?php if (!$isEmbedded): ?>
        <header class="app-header">
            <h1>Communication Hub</h1>
            <div class="header-actions">
                <button class="btn-icon" onclick="CommunicationApp.toggleNotifications()" title="Notifications">
                    <span class="notification-badge" id="notification-count" style="display: none;">0</span>
                    🔔
                </button>
                <button class="btn-icon" onclick="CommunicationApp.showSettings()" title="Settings">⚙️</button>
            </div>
        </header>
        <?php endif; ?>
        
        <div class="content-wrapper">
            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <h2>Channels</h2>
                    <button class="btn-icon" onclick="CommunicationApp.showCreateChannel()" title="Create Channel">+</button>
                </div>
                
                <div class="channel-list">
                    <!-- Public Channels -->
                    <div class="channel-section">
                        <h3>Public Channels</h3>
                        <div id="public-channels" class="channel-group">
                            <!-- Channels will be loaded here -->
                        </div>
                    </div>
                    
                    <!-- Event Channels -->
                    <div class="channel-section">
                        <h3>Event Channels</h3>
                        <div id="event-channels" class="channel-group">
                            <!-- Event channels will be loaded here -->
                        </div>
                    </div>
                    
                    <?php if ($userRole === 'vendor' || $userRole === 'admin'): ?>
                    <!-- Vendor Channels -->
                    <div class="channel-section">
                        <h3>Vendor Channels</h3>
                        <div id="vendor-channels" class="channel-group">
                            <!-- Vendor channels will be loaded here -->
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Announcements -->
                    <div class="channel-section">
                        <h3>Announcements</h3>
                        <div id="announcement-channels" class="channel-group">
                            <!-- Announcement channels will be loaded here -->
                        </div>
                    </div>
                </div>
            </aside>
            
            <!-- Main Content -->
            <main class="main-content">
                <!-- Channel Header -->
                <div class="channel-header" id="channel-header" style="display: none;">
                    <div class="channel-info">
                        <h2 id="channel-name">Select a channel</h2>
                        <span id="channel-description" class="channel-description"></span>
                    </div>
                    <div class="channel-actions">
                        <button class="btn-icon" onclick="CommunicationApp.toggleChannelInfo()" title="Channel Info">ℹ️</button>
                        <button class="btn-icon mobile-only" onclick="CommunicationApp.toggleSidebar()" title="Toggle Sidebar">☰</button>
                    </div>
                </div>
                
                <!-- Messages Area -->
                <div class="messages-area" id="messages-area">
                    <div class="welcome-message">
                        <h2>Welcome to Communication Hub</h2>
                        <p>Select a channel from the sidebar to start chatting.</p>
                    </div>
                </div>
                
                <!-- Message Input -->
                <div class="message-input-wrapper" id="message-input-wrapper" style="display: none;">
                    <form id="message-form" class="message-form">
                        <div class="input-group">
                            <textarea 
                                id="message-input" 
                                class="message-input" 
                                placeholder="Type a message..." 
                                rows="1"
                                maxlength="2000"
                                required
                            ></textarea>
                            <button type="submit" class="btn btn-primary" title="Send">
                                <span class="desktop-only">Send</span>
                                <span class="mobile-only">➤</span>
                            </button>
                        </div>
                        <div class="input-actions">
                            <button type="button" class="btn-icon" onclick="CommunicationApp.showEmojiPicker()" title="Emoji">😊</button>
                            <button type="button" class="btn-icon" onclick="CommunicationApp.attachFile()" title="Attach File">📎</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modals -->
    <div id="modal-container"></div>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" onclick="CommunicationApp.toggleSidebar()"></div>
    
    <!-- Scripts -->
    <script>
        // Global configuration
        window.basePath = '<?= $basePath ?>';
        window.currentUserId = <?= $userId ?>;
        window.currentUserName = '<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>';
        window.isEmbedded = <?= $isEmbedded ? 'true' : 'false' ?>;
        <?php if ($sellerId): ?>
        window.sellerId = <?= $sellerId ?>;
        <?php endif; ?>
    </script>
    <script src="/assets/communication/js/communication.js"></script>
    
    <?php if (!$isEmbedded): ?>
    <script>
        // Register service worker for PWA
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/assets/communication/service-worker.js')
                .then(reg => console.log('Service Worker registered'))
                .catch(err => console.error('Service Worker registration failed:', err));
        }
    </script>
    <?php endif; ?>
    
    <script>
        // Initialize the app
        document.addEventListener('DOMContentLoaded', () => {
            CommunicationApp.init();
        });
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}