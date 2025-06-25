<?php
require_once __DIR__ . '/../vendor/autoload.php';
use YFEvents\Helpers\PathHelper;

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: ' . PathHelper::adminUrl('login'));
    exit;
}

// Simple static admin claims page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claims Management - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card {
            border: none;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
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
                    <i class="fas fa-gavel"></i> Estate Sales Management
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Claims</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-primary me-3">
                                <i class="fas fa-store"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Total Sales</h6>
                                <h3 class="mb-0">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-success me-3">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Active Sales</h6>
                                <h3 class="mb-0">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-info me-3">
                                <i class="fas fa-box"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Total Items</h6>
                                <h3 class="mb-0">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-warning me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-1">Sellers</h6>
                                <h3 class="mb-0">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Estate Sales</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    The YFClaim estate sales module is ready for use. Sellers can register and create sales, buyers can browse and make offers.
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Seller</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th>Items</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No estate sales found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <a href="../claims" class="btn btn-outline-primary">
                                <i class="fas fa-eye"></i> View Public Claims Page
                            </a>
                            <a href="../seller/register" class="btn btn-outline-success">
                                <i class="fas fa-user-plus"></i> Seller Registration
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Module Info</h5>
                        <p class="mb-2"><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                        <p class="mb-2"><strong>Database:</strong> <span class="badge bg-success">Connected</span></p>
                        <p class="mb-0"><strong>Features:</strong></p>
                        <ul class="mb-0">
                            <li>Estate sale management</li>
                            <li>Item cataloging with QR codes</li>
                            <li>Buyer offer system</li>
                            <li>SMS verification</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>