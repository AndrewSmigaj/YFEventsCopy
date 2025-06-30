-- YFTheme Module Database Schema
-- Centralized theme management system

-- Drop existing tables if they exist
-- Important: Drop child tables first (those with foreign keys), then parent tables

-- Disable foreign key checks temporarily for clean drops
SET foreign_key_checks = 0;

-- Drop child tables first (those that reference other tables)
DROP TABLE IF EXISTS theme_history;
DROP TABLE IF EXISTS theme_variable_overrides;
DROP TABLE IF EXISTS theme_preset_variables;

-- Drop parent tables
DROP TABLE IF EXISTS theme_variables;
DROP TABLE IF EXISTS theme_presets;
DROP TABLE IF EXISTS theme_categories;

-- Re-enable foreign key checks
SET foreign_key_checks = 1;

-- Theme categories for organizing variables
CREATE TABLE theme_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_sort (sort_order)
);

-- Theme variables (CSS custom properties)
CREATE TABLE theme_variables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) UNIQUE NOT NULL, -- e.g., 'primary-color', 'font-size-base'
    css_variable VARCHAR(100) UNIQUE NOT NULL, -- e.g., '--primary-color', '--font-size-base'
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('color', 'size', 'spacing', 'font', 'number', 'select', 'gradient', 'shadow', 'border') NOT NULL,
    default_value VARCHAR(500) NOT NULL,
    current_value VARCHAR(500),
    options JSON, -- For select type: ["option1", "option2"]
    constraints JSON, -- {"min": 0, "max": 100, "unit": "px", "format": "hex"}
    preview_element VARCHAR(255), -- CSS selector for live preview
    sort_order INT DEFAULT 0,
    is_advanced BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES theme_categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_type (type),
    INDEX idx_sort (sort_order)
);

-- Theme presets (predefined theme combinations)
CREATE TABLE theme_presets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail_url VARCHAR(500),
    is_active BOOLEAN DEFAULT FALSE,
    is_default BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active),
    INDEX idx_default (is_default)
);

-- Theme preset variables (values for each preset)
CREATE TABLE theme_preset_variables (
    preset_id INT NOT NULL,
    variable_id INT NOT NULL,
    value VARCHAR(500) NOT NULL,
    
    PRIMARY KEY (preset_id, variable_id),
    FOREIGN KEY (preset_id) REFERENCES theme_presets(id) ON DELETE CASCADE,
    FOREIGN KEY (variable_id) REFERENCES theme_variables(id) ON DELETE CASCADE
);

-- Theme variable overrides (per-page or per-section overrides)
CREATE TABLE theme_variable_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    variable_id INT NOT NULL,
    scope_type ENUM('page', 'section', 'module', 'user_group') NOT NULL,
    scope_value VARCHAR(255) NOT NULL, -- e.g., '/admin/*', 'module:yfclaim', 'group:premium'
    value VARCHAR(500) NOT NULL,
    priority INT DEFAULT 0, -- Higher priority overrides lower
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (variable_id) REFERENCES theme_variables(id) ON DELETE CASCADE,
    INDEX idx_scope (scope_type, scope_value),
    INDEX idx_variable (variable_id),
    INDEX idx_active (is_active)
);

-- Theme change history for rollback
CREATE TABLE theme_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    preset_id INT,
    variable_id INT,
    old_value VARCHAR(500),
    new_value VARCHAR(500),
    change_type ENUM('variable_update', 'preset_change', 'preset_create', 'bulk_update') NOT NULL,
    changed_by INT NOT NULL,
    change_reason TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (preset_id) REFERENCES theme_presets(id) ON DELETE SET NULL,
    FOREIGN KEY (variable_id) REFERENCES theme_variables(id) ON DELETE SET NULL,
    INDEX idx_created (created_at),
    INDEX idx_changed_by (changed_by),
    INDEX idx_type (change_type)
);

-- Insert default categories
INSERT INTO theme_categories (name, display_name, description, sort_order, icon) VALUES
('colors', 'Colors', 'Primary, secondary, and accent colors', 1, 'palette'),
('typography', 'Typography', 'Fonts, sizes, and text styles', 2, 'text_fields'),
('layout', 'Layout', 'Spacing, padding, and margins', 3, 'dashboard'),
('components', 'Components', 'Buttons, cards, and form elements', 4, 'widgets'),
('effects', 'Effects', 'Shadows, borders, and animations', 5, 'auto_awesome'),
('advanced', 'Advanced', 'Advanced customization options', 6, 'settings');

-- Insert default theme variables
INSERT INTO theme_variables (category_id, name, css_variable, display_name, description, type, default_value, sort_order) VALUES
-- Colors
(1, 'primary-color', '--primary-color', 'Primary Color', 'Main brand color used throughout the site', 'color', '#e91e63', 1),
(1, 'primary-dark', '--primary-dark', 'Primary Dark', 'Darker variant of primary color', 'color', '#c2185b', 2),
(1, 'primary-light', '--primary-light', 'Primary Light', 'Lighter variant of primary color', 'color', '#f8bbd0', 3),
(1, 'secondary-color', '--secondary-color', 'Secondary Color', 'Secondary brand color', 'color', '#9c27b0', 4),
(1, 'accent-color', '--accent-color', 'Accent Color', 'Color for highlights and CTAs', 'color', '#ff4081', 5),
(1, 'background-color', '--background-color', 'Background Color', 'Main background color', 'color', '#ffffff', 6),
(1, 'surface-color', '--surface-color', 'Surface Color', 'Color for cards and elevated surfaces', 'color', '#f5f5f5', 7),
(1, 'text-primary', '--text-primary', 'Primary Text', 'Main text color', 'color', '#212121', 8),
(1, 'text-secondary', '--text-secondary', 'Secondary Text', 'Secondary text color', 'color', '#757575', 9),
(1, 'border-color', '--border-color', 'Border Color', 'Default border color', 'color', '#e0e0e0', 10),
(1, 'success-color', '--success-color', 'Success Color', 'Success state color', 'color', '#4caf50', 11),
(1, 'warning-color', '--warning-color', 'Warning Color', 'Warning state color', 'color', '#ff9800', 12),
(1, 'error-color', '--error-color', 'Error Color', 'Error state color', 'color', '#f44336', 13),
(1, 'info-color', '--info-color', 'Info Color', 'Info state color', 'color', '#2196f3', 14),

-- Typography
(2, 'font-family-base', '--font-family-base', 'Base Font Family', 'Main font family', 'font', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif', 1),
(2, 'font-family-heading', '--font-family-heading', 'Heading Font Family', 'Font for headings', 'font', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif', 2),
(2, 'font-size-base', '--font-size-base', 'Base Font Size', 'Base font size in pixels', 'size', '16px', 3),
(2, 'font-size-small', '--font-size-small', 'Small Font Size', 'Small text size', 'size', '14px', 4),
(2, 'font-size-large', '--font-size-large', 'Large Font Size', 'Large text size', 'size', '18px', 5),
(2, 'font-size-h1', '--font-size-h1', 'H1 Font Size', 'Heading 1 size', 'size', '36px', 6),
(2, 'font-size-h2', '--font-size-h2', 'H2 Font Size', 'Heading 2 size', 'size', '30px', 7),
(2, 'font-size-h3', '--font-size-h3', 'H3 Font Size', 'Heading 3 size', 'size', '24px', 8),
(2, 'font-weight-normal', '--font-weight-normal', 'Normal Font Weight', 'Normal text weight', 'number', '400', 9),
(2, 'font-weight-bold', '--font-weight-bold', 'Bold Font Weight', 'Bold text weight', 'number', '700', 10),
(2, 'line-height-base', '--line-height-base', 'Base Line Height', 'Default line height', 'number', '1.5', 11),

-- Layout
(3, 'spacing-unit', '--spacing-unit', 'Spacing Unit', 'Base spacing unit', 'size', '8px', 1),
(3, 'spacing-small', '--spacing-small', 'Small Spacing', 'Small spacing value', 'spacing', '8px', 2),
(3, 'spacing-medium', '--spacing-medium', 'Medium Spacing', 'Medium spacing value', 'spacing', '16px', 3),
(3, 'spacing-large', '--spacing-large', 'Large Spacing', 'Large spacing value', 'spacing', '24px', 4),
(3, 'spacing-xlarge', '--spacing-xlarge', 'Extra Large Spacing', 'Extra large spacing value', 'spacing', '32px', 5),
(3, 'container-max-width', '--container-max-width', 'Container Max Width', 'Maximum container width', 'size', '1200px', 6),
(3, 'sidebar-width', '--sidebar-width', 'Sidebar Width', 'Width of sidebars', 'size', '250px', 7),
(3, 'header-height', '--header-height', 'Header Height', 'Height of header', 'size', '60px', 8),

-- Components
(4, 'button-padding', '--button-padding', 'Button Padding', 'Padding inside buttons', 'spacing', '12px 24px', 1),
(4, 'button-border-radius', '--button-border-radius', 'Button Border Radius', 'Button corner radius', 'size', '4px', 2),
(4, 'card-padding', '--card-padding', 'Card Padding', 'Padding inside cards', 'spacing', '16px', 3),
(4, 'card-border-radius', '--card-border-radius', 'Card Border Radius', 'Card corner radius', 'size', '8px', 4),
(4, 'input-padding', '--input-padding', 'Input Padding', 'Padding inside form inputs', 'spacing', '8px 12px', 5),
(4, 'input-border-radius', '--input-border-radius', 'Input Border Radius', 'Input corner radius', 'size', '4px', 6),
(4, 'input-border-width', '--input-border-width', 'Input Border Width', 'Width of input borders', 'size', '1px', 7),

-- Effects
(5, 'shadow-small', '--shadow-small', 'Small Shadow', 'Small drop shadow', 'shadow', '0 1px 3px rgba(0,0,0,0.12)', 1),
(5, 'shadow-medium', '--shadow-medium', 'Medium Shadow', 'Medium drop shadow', 'shadow', '0 4px 6px rgba(0,0,0,0.1)', 2),
(5, 'shadow-large', '--shadow-large', 'Large Shadow', 'Large drop shadow', 'shadow', '0 10px 20px rgba(0,0,0,0.15)', 3),
(5, 'transition-speed', '--transition-speed', 'Transition Speed', 'Animation transition speed', 'select', '200ms', 4),
(5, 'hover-opacity', '--hover-opacity', 'Hover Opacity', 'Opacity on hover', 'number', '0.8', 5);

-- Update constraints for select fields
UPDATE theme_variables SET options = '["100ms", "200ms", "300ms", "400ms", "500ms"]' WHERE name = 'transition-speed';
UPDATE theme_variables SET constraints = '{"min": 0, "max": 1, "step": 0.1}' WHERE name = 'hover-opacity';
UPDATE theme_variables SET constraints = '{"min": 100, "max": 900, "step": 100}' WHERE type = 'number' AND name LIKE 'font-weight%';

-- Insert default preset
INSERT INTO theme_presets (name, display_name, description, is_active, is_default) VALUES
('default', 'Default Theme', 'Clean and modern default theme', TRUE, TRUE),
('dark', 'Dark Mode', 'Dark theme for reduced eye strain', FALSE, FALSE),
('high-contrast', 'High Contrast', 'Improved accessibility with high contrast', FALSE, FALSE);

-- Copy default values to the default preset
INSERT INTO theme_preset_variables (preset_id, variable_id, value)
SELECT 1, id, default_value FROM theme_variables;

-- Set current values to default values
UPDATE theme_variables SET current_value = default_value;

-- Create dark mode preset values
INSERT INTO theme_preset_variables (preset_id, variable_id, value)
SELECT 2, id, 
    CASE 
        WHEN name = 'background-color' THEN '#121212'
        WHEN name = 'surface-color' THEN '#1e1e1e'
        WHEN name = 'text-primary' THEN '#ffffff'
        WHEN name = 'text-secondary' THEN '#b0b0b0'
        WHEN name = 'border-color' THEN '#333333'
        WHEN name = 'primary-color' THEN '#bb86fc'
        WHEN name = 'primary-dark' THEN '#985eff'
        WHEN name = 'primary-light' THEN '#e1bee7'
        WHEN name = 'secondary-color' THEN '#03dac6'
        WHEN name = 'accent-color' THEN '#cf6679'
        ELSE default_value
    END
FROM theme_variables;

-- Create high contrast preset values
INSERT INTO theme_preset_variables (preset_id, variable_id, value)
SELECT 3, id, 
    CASE 
        WHEN name = 'background-color' THEN '#ffffff'
        WHEN name = 'text-primary' THEN '#000000'
        WHEN name = 'text-secondary' THEN '#000000'
        WHEN name = 'border-color' THEN '#000000'
        WHEN name = 'primary-color' THEN '#0000ff'
        WHEN name = 'secondary-color' THEN '#008000'
        WHEN name = 'accent-color' THEN '#ff0000'
        WHEN name = 'input-border-width' THEN '2px'
        ELSE default_value
    END
FROM theme_variables;