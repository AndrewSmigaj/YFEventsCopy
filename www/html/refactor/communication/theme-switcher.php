<?php
// Handle theme switching
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';

use YakimaFinds\Infrastructure\Utils\MobileDetector;

$mobileDetector = new MobileDetector();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = $_POST['theme'] ?? 'auto';
    
    if (in_array($theme, ['mobile', 'desktop', 'auto'])) {
        if ($theme === 'auto') {
            // Clear preference cookie
            setcookie('theme_preference', '', time() - 3600, '/');
        } else {
            // Set preference cookie
            $mobileDetector->setThemePreference($theme);
        }
        
        echo json_encode(['success' => true, 'theme' => $theme]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid theme']);
    }
    exit;
}

// Get current theme info for display
$currentTheme = $mobileDetector->determineTheme();
$preference = $mobileDetector->getThemePreference();
$autoDetected = $mobileDetector->isMobile() ? 'mobile' : 'desktop';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Theme Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Theme Settings</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Choose how YFEvents Communication appears on your device.</p>
                
                <div class="mb-3">
                    <label class="form-label">Current Theme</label>
                    <p class="form-control-plaintext">
                        <strong><?php echo ucfirst($currentTheme); ?></strong>
                        <?php if ($preference): ?>
                            <span class="badge bg-secondary">Manual</span>
                        <?php else: ?>
                            <span class="badge bg-info">Auto-detected</span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Theme Selection</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="theme" id="theme-auto" 
                               value="auto" <?php echo !$preference ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="theme-auto">
                            Automatic <small class="text-muted">(Currently: <?php echo $autoDetected; ?>)</small>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="theme" id="theme-mobile" 
                               value="mobile" <?php echo $preference === 'mobile' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="theme-mobile">
                            Always use mobile theme
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="theme" id="theme-desktop" 
                               value="desktop" <?php echo $preference === 'desktop' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="theme-desktop">
                            Always use desktop theme
                        </label>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Device Info:</strong><br>
                    Type: <?php echo $mobileDetector->getDeviceType(); ?><br>
                    OS: <?php echo $mobileDetector->getOS(); ?><br>
                    PWA: <?php echo $mobileDetector->isPWA() ? 'Yes' : 'No'; ?>
                </div>
                
                <button class="btn btn-primary" onclick="saveTheme()">Save Changes</button>
                <a href="/communication/" class="btn btn-secondary">Back to Communication</a>
            </div>
        </div>
    </div>
    
    <script>
        function saveTheme() {
            const selectedTheme = document.querySelector('input[name="theme"]:checked').value;
            
            fetch('theme-switcher.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'theme=' + selectedTheme
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Theme preference saved! Redirecting...');
                    window.location.href = '/communication/';
                } else {
                    alert('Error saving theme preference');
                }
            });
        }
    </script>
</body>
</html>