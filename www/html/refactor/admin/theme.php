<?php
// Admin Theme Editor Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /refactor/admin/login');
    exit;
}

// Set correct base path for refactor admin
$basePath = '/refactor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Editor - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/admin-styles.css">
    <link rel="stylesheet" href="<?= $basePath ?>/css/theme-custom.css">
    <style>
        /* Theme editor specific styles */
        .theme-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .theme-tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--gray-600);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .theme-tab:hover {
            color: var(--primary-color);
        }
        
        .theme-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .theme-section {
            display: none;
        }
        
        .theme-section.active {
            display: block;
        }
        
        .theme-group {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .theme-group-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            text-transform: capitalize;
        }
        
        .theme-controls {
            display: grid;
            gap: 1rem;
        }
        
        .theme-control {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1rem;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .theme-control:last-child {
            border-bottom: none;
        }
        
        .theme-label {
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .theme-description {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        .color-input-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        input[type="color"] {
            width: 50px;
            height: 40px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            cursor: pointer;
        }
        
        .color-value {
            font-family: monospace;
            font-size: 0.9rem;
            color: var(--gray-600);
        }
        
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .preset-card {
            background: white;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .preset-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .preset-card.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        
        .preset-preview {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
            margin-bottom: 0.75rem;
        }
        
        .preset-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .preset-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .preset-description {
            font-size: 0.85rem;
            color: var(--gray-600);
        }
        
        .preset-card.active .preset-description {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .seo-form-group {
            margin-bottom: 1.5rem;
        }
        
        .seo-form-group label {
            display: block;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .seo-form-group input,
        .seo-form-group textarea,
        .seo-form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 1rem;
        }
        
        .seo-form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .char-counter {
            font-size: 0.85rem;
            color: var(--gray-500);
            text-align: right;
            margin-top: 0.25rem;
        }
        
        .social-platform {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .social-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .social-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .social-icon {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            font-size: 1rem;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--gray-300);
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--success-color);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .preview-frame {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .preview-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
        }
        
        .action-buttons {
            position: sticky;
            bottom: 0;
            background: white;
            border-top: 1px solid var(--gray-200);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            margin: 0 -2rem;
        }
        
        @media (max-width: 768px) {
            .theme-control {
                grid-template-columns: 1fr;
            }
            
            .preset-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/admin-navigation.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div class="container-fluid">
                    <h1><i class="bi bi-palette"></i> Theme</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Theme</li>
                        </ol>
                    </nav>
                </div>
            </div>
            
            <div class="main-content">
        <div class="page-header">
            <h2 class="page-title">Theme & Appearance Editor</h2>
            <div>
                <button class="btn btn-secondary" onclick="exportTheme()">
                    <span>📥</span> Export
                </button>
                <button class="btn btn-primary" onclick="saveAllSettings()">
                    <span>💾</span> Save All Changes
                </button>
            </div>
        </div>
        
        <!-- Tab Navigation -->
        <div class="theme-tabs">
            <button class="theme-tab active" onclick="switchTab('appearance')">
                🎨 Appearance
            </button>
            <button class="theme-tab" onclick="switchTab('seo')">
                🔍 SEO Settings
            </button>
            <button class="theme-tab" onclick="switchTab('social')">
                📱 Social Media
            </button>
            <button class="theme-tab" onclick="switchTab('presets')">
                🎭 Presets
            </button>
        </div>
        
        <!-- Appearance Section -->
        <div id="appearance-section" class="theme-section active">
            <div id="theme-settings-container">
                <div class="loading">Loading theme settings...</div>
            </div>
        </div>
        
        <!-- SEO Section -->
        <div id="seo-section" class="theme-section">
            <div class="theme-group">
                <h3 class="theme-group-title">Global SEO Settings</h3>
                
                <div class="seo-form-group">
                    <label for="page-type">Page Type</label>
                    <select id="page-type" onchange="loadSEOSettings()">
                        <option value="home">Home Page</option>
                        <option value="events">Events Page</option>
                        <option value="shops">Shops Directory</option>
                        <option value="claims">Estate Sales</option>
                        <option value="event">Individual Event</option>
                        <option value="shop">Individual Shop</option>
                        <option value="sale">Individual Sale</option>
                    </select>
                </div>
                
                <div id="seo-settings-container">
                    <div class="loading">Loading SEO settings...</div>
                </div>
            </div>
        </div>
        
        <!-- Social Media Section -->
        <div id="social-section" class="theme-section">
            <div id="social-settings-container">
                <div class="loading">Loading social media settings...</div>
            </div>
        </div>
        
        <!-- Presets Section -->
        <div id="presets-section" class="theme-section">
            <div class="theme-group">
                <h3 class="theme-group-title">Theme Presets</h3>
                <p class="theme-description">Choose a preset theme or create your own</p>
                
                <div id="presets-container">
                    <div class="loading">Loading presets...</div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <button class="btn btn-primary" onclick="saveAsPreset()">
                        <span>💾</span> Save Current as Preset
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <div>
                <span id="save-status" style="color: var(--success-color); margin-right: 1rem;"></span>
            </div>
            <div>
                <button class="btn btn-secondary" onclick="resetToDefaults()">Reset to Defaults</button>
                <button class="btn btn-primary" onclick="saveAllSettings()">Save All Changes</button>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        const apiBasePath = '<?= $basePath ?>';
        let currentTab = 'appearance';
        let themeSettings = {};
        let seoSettings = {};
        let socialSettings = [];
        let hasUnsavedChanges = false;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadThemeSettings();
            loadSEOSettings();
            loadSocialSettings();
            loadPresets();
            
            // Track unsaved changes
            document.addEventListener('input', () => {
                hasUnsavedChanges = true;
                document.getElementById('save-status').textContent = '● Unsaved changes';
            });
        });
        
        // Tab switching
        function switchTab(tab) {
            currentTab = tab;
            
            // Update tab buttons
            document.querySelectorAll('.theme-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update sections
            document.querySelectorAll('.theme-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(`${tab}-section`).classList.add('active');
        }
        
        // Load theme settings
        async function loadThemeSettings() {
            try {
                const response = await fetch(`${apiBasePath}/api/theme/settings`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    themeSettings = data.data;
                    renderThemeSettings();
                } else {
                    showToast(data.message || 'Failed to load theme settings', 'error');
                }
            } catch (error) {
                console.error('Error loading theme settings:', error);
                showToast('Error loading theme settings', 'error');
            }
        }
        
        // Render theme settings
        function renderThemeSettings() {
            const container = document.getElementById('theme-settings-container');
            let html = '';
            
            for (const [category, settings] of Object.entries(themeSettings)) {
                html += `
                    <div class="theme-group">
                        <h3 class="theme-group-title">${category}</h3>
                        <div class="theme-controls">
                `;
                
                settings.forEach(setting => {
                    html += renderThemeControl(setting);
                });
                
                html += '</div></div>';
            }
            
            container.innerHTML = html;
        }
        
        // Render individual theme control
        function renderThemeControl(setting) {
            let control = '';
            
            switch (setting.setting_type) {
                case 'color':
                    control = `
                        <div class="color-input-wrapper">
                            <input type="color" 
                                   id="${setting.setting_key}" 
                                   value="${setting.setting_value}"
                                   onchange="updateThemeSetting('${setting.setting_key}', this.value)">
                            <span class="color-value">${setting.setting_value}</span>
                        </div>
                    `;
                    break;
                    
                case 'text':
                    control = `
                        <input type="text" 
                               id="${setting.setting_key}"
                               value="${escapeHtml(setting.setting_value || '')}"
                               onchange="updateThemeSetting('${setting.setting_key}', this.value)"
                               class="form-control">
                    `;
                    break;
                    
                case 'number':
                    const options = setting.options ? JSON.parse(setting.options) : {};
                    control = `
                        <input type="number"
                               id="${setting.setting_key}"
                               value="${setting.setting_value}"
                               min="${options.min || 0}"
                               max="${options.max || 100}"
                               step="${options.step || 1}"
                               onchange="updateThemeSetting('${setting.setting_key}', this.value)"
                               class="form-control">
                    `;
                    break;
                    
                case 'toggle':
                    control = `
                        <label class="toggle-switch">
                            <input type="checkbox"
                                   id="${setting.setting_key}"
                                   ${setting.setting_value === 'true' ? 'checked' : ''}
                                   onchange="updateThemeSetting('${setting.setting_key}', this.checked ? 'true' : 'false')">
                            <span class="toggle-slider"></span>
                        </label>
                    `;
                    break;
                    
                case 'select':
                    const selectOptions = setting.options ? JSON.parse(setting.options) : [];
                    control = `
                        <select id="${setting.setting_key}"
                                onchange="updateThemeSetting('${setting.setting_key}', this.value)"
                                class="form-control">
                    `;
                    selectOptions.forEach(opt => {
                        control += `<option value="${opt.value}" ${setting.setting_value === opt.value ? 'selected' : ''}>${opt.label}</option>`;
                    });
                    control += '</select>';
                    break;
            }
            
            return `
                <div class="theme-control">
                    <div>
                        <div class="theme-label">${setting.label}</div>
                        ${setting.description ? `<div class="theme-description">${setting.description}</div>` : ''}
                    </div>
                    <div>
                        ${control}
                    </div>
                </div>
            `;
        }
        
        // Update theme setting
        function updateThemeSetting(key, value) {
            // Update color value display
            if (document.getElementById(key).type === 'color') {
                const colorDisplay = document.getElementById(key).nextElementSibling;
                if (colorDisplay) {
                    colorDisplay.textContent = value;
                }
            }
            
            // Update CSS variable in real-time
            if (key.includes('_color')) {
                const cssVar = '--' + key.replace(/_/g, '-');
                document.documentElement.style.setProperty(cssVar, value);
            }
            
            hasUnsavedChanges = true;
        }
        
        // Load SEO settings
        async function loadSEOSettings() {
            try {
                const pageType = document.getElementById('page-type').value;
                const response = await fetch(`${apiBasePath}/api/seo/settings?page_type=${pageType}`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    seoSettings = data.data[0] || {};
                    renderSEOSettings();
                } else {
                    showToast(data.message || 'Failed to load SEO settings', 'error');
                }
            } catch (error) {
                console.error('Error loading SEO settings:', error);
                showToast('Error loading SEO settings', 'error');
            }
        }
        
        // Render SEO settings
        function renderSEOSettings() {
            const container = document.getElementById('seo-settings-container');
            
            container.innerHTML = `
                <div class="seo-form-group">
                    <label for="meta-title">Page Title</label>
                    <input type="text" id="meta-title" value="${escapeHtml(seoSettings.meta_title || '')}" 
                           placeholder="Page title for search engines" maxlength="60">
                    <div class="char-counter">${(seoSettings.meta_title || '').length}/60 characters</div>
                </div>
                
                <div class="seo-form-group">
                    <label for="meta-description">Meta Description</label>
                    <textarea id="meta-description" placeholder="Brief description for search results" maxlength="160">${escapeHtml(seoSettings.meta_description || '')}</textarea>
                    <div class="char-counter">${(seoSettings.meta_description || '').length}/160 characters</div>
                </div>
                
                <div class="seo-form-group">
                    <label for="meta-keywords">Keywords</label>
                    <input type="text" id="meta-keywords" value="${escapeHtml(seoSettings.meta_keywords || '')}" 
                           placeholder="Comma-separated keywords">
                </div>
                
                <h4 style="margin-top: 2rem; margin-bottom: 1rem;">Social Media Preview</h4>
                
                <div class="seo-form-group">
                    <label for="og-title">Open Graph Title</label>
                    <input type="text" id="og-title" value="${escapeHtml(seoSettings.og_title || '')}" 
                           placeholder="Title for social media sharing">
                </div>
                
                <div class="seo-form-group">
                    <label for="og-description">Open Graph Description</label>
                    <textarea id="og-description" placeholder="Description for social media sharing">${escapeHtml(seoSettings.og_description || '')}</textarea>
                </div>
                
                <div class="seo-form-group">
                    <label for="og-image">Social Media Image URL</label>
                    <input type="url" id="og-image" value="${escapeHtml(seoSettings.og_image || '')}" 
                           placeholder="https://example.com/image.jpg">
                </div>
                
                <div class="seo-form-group">
                    <label for="twitter-card">Twitter Card Type</label>
                    <select id="twitter-card">
                        <option value="summary" ${seoSettings.twitter_card === 'summary' ? 'selected' : ''}>Summary</option>
                        <option value="summary_large_image" ${seoSettings.twitter_card === 'summary_large_image' ? 'selected' : ''}>Summary Large Image</option>
                    </select>
                </div>
                
                <div class="seo-form-group">
                    <label for="robots">Search Engine Directives</label>
                    <select id="robots">
                        <option value="index, follow" ${seoSettings.robots === 'index, follow' ? 'selected' : ''}>Index & Follow</option>
                        <option value="noindex, follow" ${seoSettings.robots === 'noindex, follow' ? 'selected' : ''}>No Index, Follow</option>
                        <option value="index, nofollow" ${seoSettings.robots === 'index, nofollow' ? 'selected' : ''}>Index, No Follow</option>
                        <option value="noindex, nofollow" ${seoSettings.robots === 'noindex, nofollow' ? 'selected' : ''}>No Index, No Follow</option>
                    </select>
                </div>
                
                <div class="preview-frame">
                    <div class="preview-title">Search Result Preview</div>
                    <div id="search-preview" style="font-family: Arial, sans-serif;">
                        <div style="color: #1a0dab; font-size: 18px; margin-bottom: 3px;" id="preview-title">
                            ${escapeHtml(seoSettings.meta_title || 'Page Title')}
                        </div>
                        <div style="color: #006621; font-size: 14px; margin-bottom: 3px;">
                            ${window.location.hostname} › ${document.getElementById('page-type').value}
                        </div>
                        <div style="color: #545454; font-size: 13px; line-height: 1.4;" id="preview-description">
                            ${escapeHtml(seoSettings.meta_description || 'Page description will appear here...')}
                        </div>
                    </div>
                </div>
            `;
            
            // Add event listeners for live preview
            document.getElementById('meta-title').addEventListener('input', function() {
                document.getElementById('preview-title').textContent = this.value || 'Page Title';
                this.nextElementSibling.textContent = `${this.value.length}/60 characters`;
            });
            
            document.getElementById('meta-description').addEventListener('input', function() {
                document.getElementById('preview-description').textContent = this.value || 'Page description will appear here...';
                this.nextElementSibling.textContent = `${this.value.length}/160 characters`;
            });
        }
        
        // Load social media settings
        async function loadSocialSettings() {
            try {
                const response = await fetch(`${apiBasePath}/api/social/settings`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    socialSettings = data.data;
                    renderSocialSettings();
                } else {
                    showToast(data.message || 'Failed to load social media settings', 'error');
                }
            } catch (error) {
                console.error('Error loading social media settings:', error);
                showToast('Error loading social media settings', 'error');
            }
        }
        
        // Render social media settings
        function renderSocialSettings() {
            const container = document.getElementById('social-settings-container');
            let html = '';
            
            socialSettings.forEach(platform => {
                html += `
                    <div class="social-platform">
                        <div class="social-header">
                            <div class="social-title">
                                <div class="social-icon" style="background-color: ${platform.color};">
                                    <i class="${platform.icon_class}"></i>
                                </div>
                                ${platform.platform.charAt(0).toUpperCase() + platform.platform.slice(1)}
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       id="social-${platform.platform}-enabled"
                                       ${platform.enabled ? 'checked' : ''}
                                       onchange="updateSocialSetting('${platform.platform}', 'enabled', this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="theme-controls" ${!platform.enabled ? 'style="opacity: 0.5;"' : ''}>
                            <div class="theme-control">
                                <div>
                                    <div class="theme-label">Username/Handle</div>
                                    <div class="theme-description">Your ${platform.platform} username</div>
                                </div>
                                <input type="text" 
                                       id="social-${platform.platform}-username"
                                       value="${escapeHtml(platform.username || '')}"
                                       placeholder="@username"
                                       onchange="updateSocialSetting('${platform.platform}', 'username', this.value)"
                                       class="form-control">
                            </div>
                            
                            <div class="theme-control">
                                <div>
                                    <div class="theme-label">Profile URL</div>
                                    <div class="theme-description">Full URL to your ${platform.platform} profile</div>
                                </div>
                                <input type="url" 
                                       id="social-${platform.platform}-url"
                                       value="${escapeHtml(platform.url || '')}"
                                       placeholder="https://${platform.platform}.com/yourprofile"
                                       onchange="updateSocialSetting('${platform.platform}', 'url', this.value)"
                                       class="form-control">
                            </div>
                            
                            ${platform.share_template ? `
                            <div class="theme-control">
                                <div>
                                    <div class="theme-label">Share Template</div>
                                    <div class="theme-description">Template for sharing content. Use {title} and {url} as placeholders</div>
                                </div>
                                <textarea id="social-${platform.platform}-template"
                                          onchange="updateSocialSetting('${platform.platform}', 'share_template', this.value)"
                                          class="form-control"
                                          rows="2">${escapeHtml(platform.share_template || '')}</textarea>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Update social setting
        function updateSocialSetting(platform, field, value) {
            const platformData = socialSettings.find(p => p.platform === platform);
            if (platformData) {
                platformData[field] = value;
                hasUnsavedChanges = true;
            }
        }
        
        // Load presets
        async function loadPresets() {
            try {
                const response = await fetch(`${apiBasePath}/api/theme/presets`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    renderPresets(data.data);
                } else {
                    showToast(data.message || 'Failed to load presets', 'error');
                }
            } catch (error) {
                console.error('Error loading presets:', error);
                showToast('Error loading presets', 'error');
            }
        }
        
        // Render presets
        function renderPresets(presets) {
            const container = document.getElementById('presets-container');
            let html = '<div class="preset-grid">';
            
            presets.forEach(preset => {
                const colors = preset.settings;
                html += `
                    <div class="preset-card" onclick="applyPreset(${preset.id})">
                        <div class="preset-preview">
                            ${colors.primary_color ? `<div class="preset-color" style="background-color: ${colors.primary_color}"></div>` : ''}
                            ${colors.secondary_color ? `<div class="preset-color" style="background-color: ${colors.secondary_color}"></div>` : ''}
                            ${colors.background_color ? `<div class="preset-color" style="background-color: ${colors.background_color}"></div>` : ''}
                            ${colors.text_color ? `<div class="preset-color" style="background-color: ${colors.text_color}"></div>` : ''}
                        </div>
                        <div class="preset-name">${escapeHtml(preset.name)}</div>
                        <div class="preset-description">${escapeHtml(preset.description || '')}</div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        // Apply preset
        async function applyPreset(presetId) {
            if (!confirm('Apply this preset? Current unsaved changes will be lost.')) {
                return;
            }
            
            try {
                const response = await fetch(`${apiBasePath}/api/theme/presets/apply`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({ preset_id: presetId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Preset applied successfully', 'success');
                    hasUnsavedChanges = false;
                    loadThemeSettings();
                    location.reload(); // Reload to apply new styles
                } else {
                    showToast(data.message || 'Failed to apply preset', 'error');
                }
            } catch (error) {
                console.error('Error applying preset:', error);
                showToast('Error applying preset', 'error');
            }
        }
        
        // Save all settings
        async function saveAllSettings() {
            try {
                // Save theme settings
                if (currentTab === 'appearance') {
                    await saveThemeSettings();
                }
                
                // Save SEO settings
                if (currentTab === 'seo') {
                    await saveSEOSettings();
                }
                
                // Save social settings
                if (currentTab === 'social') {
                    await saveSocialSettings();
                }
                
                hasUnsavedChanges = false;
                document.getElementById('save-status').textContent = '';
                showToast('All settings saved successfully', 'success');
                
            } catch (error) {
                console.error('Error saving settings:', error);
                showToast('Error saving settings', 'error');
            }
        }
        
        // Save theme settings
        async function saveThemeSettings() {
            const settings = {};
            
            // Collect all settings
            document.querySelectorAll('#theme-settings-container input, #theme-settings-container select').forEach(input => {
                if (input.type === 'checkbox') {
                    settings[input.id] = input.checked ? 'true' : 'false';
                } else {
                    settings[input.id] = input.value;
                }
            });
            
            const response = await fetch(`${apiBasePath}/api/theme/settings`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({ settings })
            });
            
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Failed to save theme settings');
            }
        }
        
        // Save SEO settings
        async function saveSEOSettings() {
            const settings = {
                page_type: document.getElementById('page-type').value,
                meta_title: document.getElementById('meta-title').value,
                meta_description: document.getElementById('meta-description').value,
                meta_keywords: document.getElementById('meta-keywords').value,
                og_title: document.getElementById('og-title').value,
                og_description: document.getElementById('og-description').value,
                og_image: document.getElementById('og-image').value,
                twitter_card: document.getElementById('twitter-card').value,
                robots: document.getElementById('robots').value
            };
            
            const response = await fetch(`${apiBasePath}/api/seo/settings`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify(settings)
            });
            
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Failed to save SEO settings');
            }
        }
        
        // Save social settings
        async function saveSocialSettings() {
            const promises = socialSettings.map(platform => {
                return fetch(`${apiBasePath}/api/social/settings`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify(platform)
                });
            });
            
            await Promise.all(promises);
        }
        
        // Save as preset
        async function saveAsPreset() {
            const name = prompt('Enter a name for this preset:');
            if (!name) return;
            
            const description = prompt('Enter a description (optional):');
            
            try {
                const response = await fetch(`${apiBasePath}/api/theme/presets`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({ name, description })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Preset saved successfully', 'success');
                    loadPresets();
                } else {
                    showToast(data.message || 'Failed to save preset', 'error');
                }
            } catch (error) {
                console.error('Error saving preset:', error);
                showToast('Error saving preset', 'error');
            }
        }
        
        // Export theme
        async function exportTheme() {
            try {
                const response = await fetch(`${apiBasePath}/api/theme/export`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `theme-export-${data.timestamp.replace(/[: ]/g, '-')}.json`;
                    a.click();
                    URL.revokeObjectURL(url);
                    showToast('Theme exported successfully', 'success');
                } else {
                    showToast(data.message || 'Failed to export theme', 'error');
                }
            } catch (error) {
                console.error('Error exporting theme:', error);
                showToast('Error exporting theme', 'error');
            }
        }
        
        // Reset to defaults
        function resetToDefaults() {
            if (!confirm('Reset all theme settings to defaults? This cannot be undone.')) {
                return;
            }
            
            // Apply default preset (ID: 1)
            applyPreset(1);
        }
        
        // Utility functions
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        async function logout() {
            try {
                const response = await fetch(`${basePath}/admin/logout`, { method: 'POST' });
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = `${basePath}/admin/login`;
                }
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = `${basePath}/admin/login`;
            }
        }
        
        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
    
    <!-- Font Awesome for social icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>