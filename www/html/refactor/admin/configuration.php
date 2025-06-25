<?php
session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /refactor/admin/login');
    exit;
}

// Static admin configuration page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .config-card {
            border: none;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .config-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .config-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            margin-right: 15px;
        }
        .status-badge {
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="./dashboard.php">
                <i class="fas fa-calendar-alt"></i> YFEvents Admin
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['admin_username'] ?? 'Admin'; ?>
                </span>
                <a class="btn btn-outline-light btn-sm" href="../">
                    <i class="fas fa-home"></i> View Site
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3">
                    <i class="fas fa-cogs"></i> System Configuration
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Configuration</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Configuration Sections -->
        <div class="row">
            <div class="col-md-6">
                <!-- General Settings -->
                <div class="card config-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="config-icon bg-primary">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">General Settings</h5>
                                <p class="text-muted mb-0">Site name, timezone, locale</p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Site Name</label>
                            <input type="text" class="form-control" value="YakimaFinds Events" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Timezone</label>
                            <select class="form-select" disabled>
                                <option>America/Los_Angeles</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Format</label>
                            <select class="form-select" disabled>
                                <option>MM/DD/YYYY</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" disabled>
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </div>

                <!-- Database Configuration -->
                <div class="card config-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="config-icon bg-info">
                                <i class="fas fa-database"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Database</h5>
                                <p class="text-muted mb-0">Connection status and info</p>
                            </div>
                        </div>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Database connected
                        </div>
                        <ul class="list-unstyled mb-0">
                            <li><strong>Host:</strong> localhost</li>
                            <li><strong>Database:</strong> yakima_finds</li>
                            <li><strong>Tables:</strong> 25</li>
                            <li><strong>Size:</strong> 12.5 MB</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <!-- API Configuration -->
                <div class="card config-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="config-icon bg-success">
                                <i class="fas fa-key"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">API Keys</h5>
                                <p class="text-muted mb-0">External service integrations</p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Google Maps API</label>
                            <div class="input-group">
                                <input type="password" class="form-control" value="••••••••••••" readonly>
                                <button class="btn btn-outline-secondary" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-success">
                                <i class="fas fa-check-circle"></i> Active
                            </small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Facebook API</label>
                            <div class="input-group">
                                <input type="password" class="form-control" value="Not configured" readonly>
                                <button class="btn btn-outline-secondary" type="button">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> Optional
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Email Configuration -->
                <div class="card config-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="config-icon bg-warning">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Email Settings</h5>
                                <p class="text-muted mb-0">SMTP and notifications</p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" value="smtp.gmail.com" readonly>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Port</label>
                                <input type="text" class="form-control" value="587" readonly>
                            </div>
                            <div class="col">
                                <label class="form-label">Encryption</label>
                                <select class="form-select" disabled>
                                    <option>TLS</option>
                                </select>
                            </div>
                        </div>
                        <button class="btn btn-warning" disabled>
                            <i class="fas fa-paper-plane"></i> Send Test Email
                        </button>
                    </div>
                </div>

                <!-- Caching Configuration -->
                <div class="card config-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="config-icon bg-secondary">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Cache Management</h5>
                                <p class="text-muted mb-0">Performance optimization</p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <p class="mb-2"><strong>Cache Status:</strong> <span class="badge bg-success">Enabled</span></p>
                            <p class="mb-2"><strong>Size:</strong> 2.1 MB</p>
                            <p class="mb-2"><strong>Files:</strong> 142</p>
                        </div>
                        <button class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i> Clear Cache
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Server Environment</h6>
                        <ul class="list-unstyled">
                            <li><strong>PHP Version:</strong> <?php echo phpversion(); ?></li>
                            <li><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></li>
                            <li><strong>Document Root:</strong> /home/robug/YFEvents/www/html/refactor</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Application Status</h6>
                        <ul class="list-unstyled">
                            <li><strong>Version:</strong> 2.0.0-refactor</li>
                            <li><strong>Environment:</strong> Production</li>
                            <li><strong>Debug Mode:</strong> <span class="badge bg-danger">Disabled</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>