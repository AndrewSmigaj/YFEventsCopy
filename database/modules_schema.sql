-- YFEvents Modules Support Schema
-- This schema adds support for optional modules in YFEvents

-- Module registry table
CREATE TABLE IF NOT EXISTS modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    version VARCHAR(20) NOT NULL,
    status ENUM('active', 'inactive', 'error') DEFAULT 'active',
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    config JSON,
    INDEX idx_status (status)
);

-- Module hooks registry
CREATE TABLE IF NOT EXISTS module_hooks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT NOT NULL,
    hook_name VARCHAR(50) NOT NULL,
    callback_function VARCHAR(100) NOT NULL,
    priority INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    INDEX idx_hook_name (hook_name),
    UNIQUE KEY unique_module_hook (module_id, hook_name)
);

-- Module migrations tracking
CREATE TABLE IF NOT EXISTS module_migrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT NOT NULL,
    migration_file VARCHAR(255) NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_module_migration (module_id, migration_file)
);

-- Module permissions (for admin access control)
CREATE TABLE IF NOT EXISTS module_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT NOT NULL,
    permission_name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_module_permission (module_id, permission_name)
);

-- Module settings (for configurable options)
CREATE TABLE IF NOT EXISTS module_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    module_id INT NOT NULL,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_module_setting (module_id, setting_key)
);