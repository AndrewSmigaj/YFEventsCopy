<?php
// YFTheme - Public Theme Browser
require_once __DIR__ . '/../../../config/database.php';

// Get available themes
$themesDir = __DIR__ . '/../../../www/html/css/themes/';
$themes = [];

if (is_dir($themesDir)) {
    $files = scandir($themesDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'css') {
            $themeName = pathinfo($file, PATHINFO_FILENAME);
            $themes[] = [
                'name' => $themeName,
                'file' => $file,
                'display_name' => ucwords(str_replace('-', ' ', $themeName))
            ];
        }
    }
}

// Get current theme
$currentTheme = $_SESSION['theme'] ?? 'default';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFTheme - Theme Browser</title>
    <link rel="stylesheet" href="/css/calendar.css">
    <style>
        .theme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .theme-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .theme-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .theme-card.active {
            border-color: var(--primary-color);
        }
        .theme-preview {
            height: 100px;
            border-radius: 4px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>YFTheme - Browse Themes</h1>
        <p>Select a theme for your YFEvents experience</p>

        <div class="theme-grid">
            <?php if (empty($themes)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <h3>No themes available</h3>
                    <p>Contact an administrator to install themes.</p>
                </div>
            <?php else: ?>
                <?php foreach ($themes as $theme): ?>
                    <div class="theme-card <?= $theme['name'] === $currentTheme ? 'active' : '' ?>">
                        <div class="theme-preview" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                            <?= htmlspecialchars($theme['display_name']) ?>
                        </div>
                        <h3><?= htmlspecialchars($theme['display_name']) ?></h3>
                        <p>Theme file: <?= htmlspecialchars($theme['file']) ?></p>
                        
                        <div style="margin-top: 15px;">
                            <?php if ($theme['name'] === $currentTheme): ?>
                                <span class="btn" style="background: #27ae60;">Current Theme</span>
                            <?php else: ?>
                                <a href="/admin/theme-config.php?preview=<?= urlencode($theme['name']) ?>" class="btn">
                                    Preview Theme
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="margin-top: 40px; text-align: center;">
            <a href="/" class="btn">← Back to Main Site</a>
            <a href="#" title="Theme config has been removed" class="btn">Theme Admin</a>
        </div>
    </div>
</body>
</html>