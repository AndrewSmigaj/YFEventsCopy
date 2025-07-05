<?php
session_start();

// Check if embedded mode
$isEmbedded = isset($_GET['embedded']) && $_GET['embedded'] === 'true';
$sellerId = $_GET['seller_id'] ?? null;

// If embedded, ensure seller is authenticated
if ($isEmbedded && $sellerId) {
    // Check if user is logged in as a seller
    if (!isset($_SESSION['claim_seller_id']) || $_SESSION['claim_seller_id'] != $sellerId) {
        // Also check if they're logged in via YFAuth
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'seller') {
            die('Unauthorized access');
        }
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Include autoloader
require_once __DIR__ . '/../../../vendor/autoload.php';

// Mobile detection
use YFEvents\Infrastructure\Utils\MobileDetector;
$mobileDetector = new MobileDetector();
$isMobile = $mobileDetector->isMobile();
$deviceType = $mobileDetector->getDeviceType();
$theme = $mobileDetector->determineTheme();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'user';
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
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/communication/manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="/communication/icons/icon-192x192.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/communication.css" rel="stylesheet">
    
    <?php if ($isMobile): ?>
    <!-- Mobile CSS -->
    <link href="css/mobile.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="<?php echo $isMobile ? 'mobile-view' : 'desktop-view'; ?>" data-device="<?php echo $deviceType; ?>">
    <?php if ($isMobile && !$isEmbedded): ?>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="header-action" onclick="toggleMobileDrawer()">
            <i class="fas fa-bars"></i>
        </button>
        <div class="header-title" id="mobileHeaderTitle">Communication Hub</div>
        <button class="header-action" onclick="showCreateMessageModal()">
            <i class="fas fa-edit"></i>
        </button>
    </div>
    
    <!-- Mobile Channel Drawer -->
    <div class="mobile-channel-drawer" id="mobileChannelDrawer">
        <div class="p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Channels</h5>
                <button class="btn btn-sm btn-primary" onclick="showCreateChannelModal()">
                    <i class="fas fa-plus"></i> New
                </button>
            </div>
            <div id="mobileChannelList">
                <!-- Channels loaded here for mobile -->
            </div>
        </div>
    </div>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileDrawer()"></div>
    
    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav">
        <a href="#" class="nav-item active" onclick="switchMobileTab('messages')">
            <i class="fas fa-comments"></i>
            <span>Messages</span>
        </a>
        <a href="#" class="nav-item" onclick="switchMobileTab('channels')">
            <i class="fas fa-hashtag"></i>
            <span>Channels</span>
        </a>
        <a href="#" class="nav-item" onclick="switchMobileTab('notifications')">
            <i class="fas fa-bell"></i>
            <span>Alerts</span>
        </a>
        <a href="#" class="nav-item" onclick="switchMobileTab('profile')">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </div>
    <?php endif; ?>
    
    <div class="communication-hub">
        <div class="row g-0 h-100">
            <!-- Channel Sidebar (Desktop only) -->
            <?php if (!$isEmbedded): ?>
            <div class="col-md-3 col-lg-2 border-end" id="channelSidebar">
                <div class="sidebar h-100 d-flex flex-column">
                    <div class="sidebar-header p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Channels</h5>
                            <button class="btn btn-sm btn-primary" onclick="showCreateChannelModal()">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="channel-list flex-grow-1 overflow-auto p-3">
                        <!-- Public Channels -->
                        <div class="channel-group mb-3">
                            <h6 class="text-muted small">PUBLIC CHANNELS</h6>
                            <div id="public-channels" class="channel-items">
                                <!-- Channels loaded dynamically -->
                            </div>
                        </div>
                        
                        <!-- Event Channels -->
                        <div class="channel-group mb-3">
                            <h6 class="text-muted small">EVENT CHANNELS</h6>
                            <div id="event-channels" class="channel-items">
                                <!-- Channels loaded dynamically -->
                            </div>
                        </div>
                        
                        <!-- Vendor Channels -->
                        <?php if (!empty($_SESSION['shop_id'])): ?>
                        <div class="channel-group mb-3">
                            <h6 class="text-muted small">VENDOR CHANNELS</h6>
                            <div id="vendor-channels" class="channel-items">
                                <!-- Channels loaded dynamically -->
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Announcement Channels -->
                        <div class="channel-group mb-3">
                            <h6 class="text-muted small">ANNOUNCEMENTS</h6>
                            <div id="announcement-channels" class="channel-items">
                                <!-- Channels loaded dynamically -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Status -->
                    <div class="user-status p-3 border-top">
                        <div class="d-flex align-items-center">
                            <img src="/uploads/avatars/default.jpg" class="rounded-circle me-2" width="32" height="32" alt="Avatar">
                            <div class="flex-grow-1">
                                <div class="fw-bold small"><?php echo htmlspecialchars($userName); ?></div>
                                <div class="text-muted small"><?php echo ucfirst($userRole); ?></div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm" data-bs-toggle="dropdown">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" onclick="showPreferencesModal()">Preferences</a></li>
                                    <li><a class="dropdown-item" href="/logout.php">Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Main Chat Area -->
            <div class="<?php echo $isEmbedded ? 'col-12' : 'col-md-9 col-lg-10'; ?>">
                <div class="chat-area h-100 d-flex flex-column">
                    <!-- Channel Header -->
                    <div class="channel-header p-3 border-bottom" id="channel-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0" id="channel-name">Select a channel</h4>
                                <small class="text-muted" id="channel-description"></small>
                            </div>
                            <div class="channel-actions">
                                <button class="btn btn-sm btn-outline-secondary" id="btn-pinned" style="display:none;" onclick="showPinnedMessages()">
                                    <i class="fas fa-thumbtack"></i> Pinned
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" id="btn-search" style="display:none;" onclick="showSearchModal()">
                                    <i class="fas fa-search"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" id="btn-info" style="display:none;" onclick="showChannelInfo()">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Messages Container -->
                    <div class="messages-container flex-grow-1 overflow-auto p-3" id="messages-container">
                        <div class="text-center text-muted mt-5">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <p>Select a channel to start messaging</p>
                        </div>
                    </div>
                    
                    <!-- Message Input -->
                    <div class="message-input-area border-top p-3" id="message-input-area" style="display:none;">
                        <form id="message-form" class="d-flex gap-2">
                            <div class="flex-grow-1">
                                <textarea 
                                    class="form-control" 
                                    id="message-input" 
                                    rows="2" 
                                    placeholder="Type your message..."
                                    maxlength="5000"></textarea>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="showAttachmentModal()">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </div>
                        </form>
                        <div class="mt-2">
                            <small class="text-muted">
                                Press Enter to send, Shift+Enter for new line. 
                                Use @username to mention someone.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Channel Modal -->
    <div class="modal fade" id="createChannelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Channel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="create-channel-form">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="channel-name-input" class="form-label">Channel Name</label>
                            <input type="text" class="form-control" id="channel-name-input" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="channel-description-input" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="channel-description-input" rows="3" maxlength="500"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="channel-type-select" class="form-label">Channel Type</label>
                            <select class="form-select" id="channel-type-select">
                                <option value="public">Public</option>
                                <option value="private">Private</option>
                                <?php if ($userRole === 'admin' || $userRole === 'editor'): ?>
                                <option value="announcement">Announcement</option>
                                <?php endif; ?>
                                <?php if (!empty($_SESSION['shop_id'])): ?>
                                <option value="vendor">Vendor</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Channel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Search Modal -->
    <div class="modal fade" id="searchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Search Messages</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="search-form" class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="search-input" placeholder="Search messages..." minlength="3">
                            <button class="btn btn-primary" type="submit">Search</button>
                        </div>
                    </form>
                    <div id="search-results" class="search-results">
                        <!-- Search results appear here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Preferences Modal -->
    <div class="modal fade" id="preferencesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Notification Preferences</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="preferences-form">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Desktop Notifications</label>
                            <select class="form-select" id="notification-preference">
                                <option value="all">All messages</option>
                                <option value="mentions">Mentions only</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Digest</label>
                            <select class="form-select" id="email-digest-frequency">
                                <option value="real-time">Real-time</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Preferences</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Communication App JS -->
    <script>
        window.basePath = '';
        window.currentUserId = <?php echo $userId; ?>;
        window.currentUserName = '<?php echo htmlspecialchars($userName); ?>';
        window.isMobile = <?php echo $isMobile ? 'true' : 'false'; ?>;
        window.deviceType = '<?php echo $deviceType; ?>';
    </script>
    <script src="js/communication.js"></script>
    
    <?php if ($isMobile): ?>
    <script>
        // Mobile-specific functions
        function toggleMobileDrawer() {
            const drawer = document.getElementById('mobileChannelDrawer');
            const overlay = document.getElementById('mobileOverlay');
            drawer.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        
        function switchMobileTab(tab) {
            // Update active tab
            document.querySelectorAll('.mobile-bottom-nav .nav-item').forEach(item => {
                item.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Handle tab switching logic
            switch(tab) {
                case 'messages':
                    // Show messages view
                    break;
                case 'channels':
                    toggleMobileDrawer();
                    break;
                case 'notifications':
                    // Show notifications
                    break;
                case 'profile':
                    // Show profile
                    break;
            }
        }
        
        // Handle keyboard on mobile
        window.addEventListener('resize', function() {
            if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') {
                document.body.classList.add('keyboard-open');
            } else {
                document.body.classList.remove('keyboard-open');
            }
        });
        
        // Load channels in mobile drawer
        document.addEventListener('DOMContentLoaded', function() {
            // Clone channels to mobile drawer
            const updateMobileChannels = () => {
                const mobileList = document.getElementById('mobileChannelList');
                const publicChannels = document.getElementById('public-channels');
                if (publicChannels && mobileList) {
                    mobileList.innerHTML = publicChannels.innerHTML;
                }
            };
            
            // Update mobile channels when desktop channels are loaded
            const observer = new MutationObserver(updateMobileChannels);
            const publicChannels = document.getElementById('public-channels');
            if (publicChannels) {
                observer.observe(publicChannels, { childList: true });
            }
        });
    </script>
    <?php endif; ?>
    
    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/communication/service-worker.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
        
        // PWA Install Prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Show install button on mobile
            if (window.isMobile) {
                showInstallPrompt();
            }
        });
        
        function showInstallPrompt() {
            // Create install banner
            const banner = document.createElement('div');
            banner.className = 'install-prompt';
            banner.innerHTML = `
                <div class="p-3 bg-primary text-white d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Install YFComm</strong>
                        <div class="small">Add to your home screen for the best experience</div>
                    </div>
                    <button class="btn btn-light btn-sm" onclick="installPWA()">Install</button>
                </div>
            `;
            document.body.appendChild(banner);
        }
        
        async function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response to install prompt: ${outcome}`);
                deferredPrompt = null;
                document.querySelector('.install-prompt')?.remove();
            }
        }
        
        // Handle PWA display mode
        if (window.matchMedia('(display-mode: standalone)').matches) {
            console.log('App running in standalone mode');
            document.body.classList.add('pwa-mode');
        }
    </script>
</body>
</html>