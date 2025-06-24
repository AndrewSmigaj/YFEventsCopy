<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Application\Services\ClaimService;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use Exception;
use PDO;

/**
 * Admin controller for estate sales claims management
 */
class AdminClaimsController extends BaseController
{
    private ClaimService $claimService;
    private PDO $pdo;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->claimService = $container->resolve(ClaimService::class);
        $connection = $container->resolve(ConnectionInterface::class);
        $this->pdo = $connection->getConnection();
    }

    /**
     * Show claims admin dashboard
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
        echo $this->renderClaimsPage($basePath);
    }

    /**
     * Get claims statistics
     */
    public function getStatistics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $stats = [];

            // Total sales
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM yfc_sales
            ");
            $stats['sales'] = $stmt->fetch();

            // Total items
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN claim_status = 'available' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN claim_status = 'claimed' THEN 1 ELSE 0 END) as claimed,
                    SUM(CASE WHEN claim_status = 'sold' THEN 1 ELSE 0 END) as sold
                FROM yfc_items
            ");
            $stats['items'] = $stmt->fetch();

            // Total offers
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(amount) as total_value
                FROM yfc_offers
            ");
            $stats['offers'] = $stmt->fetch();

            // Active sellers
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM yfc_sellers WHERE is_active = 1");
            $stats['sellers'] = $stmt->fetch();

            // Active buyers
            $stmt = $this->pdo->query("SELECT COUNT(DISTINCT buyer_id) as total FROM yfc_offers");
            $stats['buyers'] = $stmt->fetch();

            // Recent activity
            $stmt = $this->pdo->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as offers_count,
                    SUM(amount) as offers_value
                FROM yfc_offers
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            $stats['recent_activity'] = $stmt->fetchAll();

            $this->successResponse([
                'statistics' => $stats
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all sales with pagination
     */
    public function getSales(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $page = max(1, (int)($input['page'] ?? 1));
            $perPage = min(100, max(10, (int)($input['per_page'] ?? 20)));
            $status = $input['status'] ?? '';
            $search = $input['search'] ?? '';

            $offset = ($page - 1) * $perPage;

            // Build query
            $where = ['1=1'];
            $params = [];

            if ($status) {
                $where[] = 'status = :status';
                $params['status'] = $status;
            }

            if ($search) {
                $where[] = '(title LIKE :search OR company_name LIKE :search)';
                $params['search'] = "%$search%";
            }

            $whereClause = implode(' AND ', $where);

            // Get total count
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM yfc_sales s
                JOIN yfc_sellers se ON s.seller_id = se.id
                WHERE $whereClause
            ");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();

            // Get sales
            $stmt = $this->pdo->prepare("
                SELECT 
                    s.*,
                    se.company_name,
                    se.contact_name,
                    COUNT(DISTINCT i.id) as item_count,
                    COUNT(DISTINCT o.id) as offer_count,
                    SUM(o.amount) as total_offers
                FROM yfc_sales s
                JOIN yfc_sellers se ON s.seller_id = se.id
                LEFT JOIN yfc_items i ON i.sale_id = s.id
                LEFT JOIN yfc_offers o ON o.item_id = i.id
                WHERE $whereClause
                GROUP BY s.id
                ORDER BY s.start_date DESC
                LIMIT :limit OFFSET :offset
            ");
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $sales = $stmt->fetchAll();

            $this->successResponse([
                'sales' => $sales,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => ceil($total / $perPage)
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load sales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get pending offers
     */
    public function getPendingOffers(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $stmt = $this->pdo->query("
                SELECT 
                    o.*,
                    i.title as item_title,
                    i.description as item_description,
                    b.name as buyer_name,
                    b.email as buyer_email,
                    b.phone as buyer_phone,
                    s.title as sale_title
                FROM yfc_offers o
                JOIN yfc_items i ON o.item_id = i.id
                JOIN yfc_buyers b ON o.buyer_id = b.id
                JOIN yfc_sales s ON i.sale_id = s.id
                WHERE o.status = 'pending'
                ORDER BY o.created_at DESC
                LIMIT 100
            ");

            $offers = $stmt->fetchAll();

            $this->successResponse([
                'offers' => $offers
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load offers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get sellers list
     */
    public function getSellers(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $status = $input['status'] ?? '';

            $where = [];
            $params = [];

            if ($status === 'active') {
                $where[] = 'se.is_active = 1';
            } elseif ($status === 'inactive') {
                $where[] = 'se.is_active = 0';
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $this->pdo->prepare("
                SELECT 
                    se.*,
                    COUNT(DISTINCT s.id) as total_sales,
                    COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_sales,
                    MAX(s.created_at) as last_sale_date
                FROM yfc_sellers se
                LEFT JOIN yfc_sales s ON s.seller_id = se.id
                $whereClause
                GROUP BY se.id
                ORDER BY se.created_at DESC
            ");
            $stmt->execute($params);
            
            $sellers = $stmt->fetchAll();

            $this->successResponse([
                'sellers' => $sellers
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load sellers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve/activate seller
     */
    public function approveSeller(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $this->errorResponse('Invalid seller ID');
                return;
            }

            $stmt = $this->pdo->prepare("
                UPDATE yfc_sellers 
                SET is_active = 1, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);

            $this->successResponse([
                'message' => 'Seller approved successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to approve seller: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Deactivate seller
     */
    public function deactivateSeller(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $this->errorResponse('Invalid seller ID');
                return;
            }

            $stmt = $this->pdo->prepare("
                UPDATE yfc_sellers 
                SET is_active = 0, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);

            $this->successResponse([
                'message' => 'Seller deactivated successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to deactivate seller: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update sale status
     */
    public function updateSaleStatus(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $id = (int)($_GET['id'] ?? 0);
            $input = $this->getInput();
            $status = $input['status'] ?? '';

            if ($id <= 0 || !in_array($status, ['upcoming', 'active', 'completed', 'cancelled'])) {
                $this->errorResponse('Invalid sale ID or status');
                return;
            }

            $stmt = $this->pdo->prepare("
                UPDATE yfc_sales 
                SET status = :status, updated_at = NOW() 
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id, 'status' => $status]);

            $this->successResponse([
                'message' => 'Sale status updated successfully'
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to update sale status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get sale details with items and offers
     */
    public function getSaleDetails(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $id = (int)($_GET['id'] ?? 0);
            
            if ($id <= 0) {
                $this->errorResponse('Invalid sale ID');
                return;
            }

            // Get sale
            $stmt = $this->pdo->prepare("
                SELECT s.*, se.company_name, se.contact_name 
                FROM yfc_sales s
                JOIN yfc_sellers se ON s.seller_id = se.id
                WHERE s.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $sale = $stmt->fetch();

            if (!$sale) {
                $this->errorResponse('Sale not found', 404);
                return;
            }

            // Get items
            $stmt = $this->pdo->prepare("
                SELECT 
                    i.*,
                    COUNT(o.id) as offer_count,
                    MAX(o.amount) as highest_offer
                FROM yfc_items i
                LEFT JOIN yfc_offers o ON o.item_id = i.id
                WHERE i.sale_id = :sale_id
                GROUP BY i.id
                ORDER BY i.category_id, i.title
            ");
            $stmt->execute(['sale_id' => $id]);
            $items = $stmt->fetchAll();

            // Get offers
            $stmt = $this->pdo->prepare("
                SELECT 
                    o.*,
                    i.title as item_title,
                    b.name as buyer_name,
                    b.email as buyer_email
                FROM yfc_offers o
                JOIN yfc_items i ON o.item_id = i.id
                JOIN yfc_buyers b ON o.buyer_id = b.id
                WHERE i.sale_id = :sale_id
                ORDER BY o.created_at DESC
            ");
            $stmt->execute(['sale_id' => $id]);
            $offers = $stmt->fetchAll();

            $this->successResponse([
                'sale' => $sale,
                'items' => $items,
                'offers' => $offers
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load sale details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Render claims admin page
     */
    private function renderClaimsPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claims Management - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .sale-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-upcoming { background: #e3f2fd; color: #1976d2; }
        .status-active { background: #e8f5e9; color: #388e3c; }
        .status-completed { background: #f3e5f5; color: #7b1fa2; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        .offer-card {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }
        .nav-tabs .nav-link {
            color: #495057;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Estate Sales Claims Management</h1>
                <a href="{$basePath}/admin/dashboard" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <div class="stat-number" id="total-sales">-</div>
                    <div class="text-muted">Total Sales</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <div class="stat-number text-success" id="active-sales">-</div>
                    <div class="text-muted">Active</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <div class="stat-number" id="total-items">-</div>
                    <div class="text-muted">Total Items</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <div class="stat-number" id="pending-offers">-</div>
                    <div class="text-muted">Pending Offers</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <div class="stat-number" id="active-sellers">-</div>
                    <div class="text-muted">Sellers</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <div class="stat-number" id="total-buyers">-</div>
                    <div class="text-muted">Buyers</div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#sales">
                    <i class="bi bi-calendar-event"></i> Sales
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#offers">
                    <i class="bi bi-cash-stack"></i> Pending Offers
                    <span class="badge bg-danger ms-1" id="pending-count">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#sellers">
                    <i class="bi bi-shop"></i> Sellers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#analytics">
                    <i class="bi bi-graph-up"></i> Analytics
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Sales Tab -->
            <div class="tab-pane fade show active" id="sales">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="search-sales" 
                                       placeholder="Search sales...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="status-filter">
                                    <option value="">All Status</option>
                                    <option value="upcoming">Upcoming</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-5 text-end">
                                <a href="{$basePath}/seller/sale/new" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Create Sale
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="sales-container">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>

                <nav id="sales-pagination"></nav>
            </div>

            <!-- Offers Tab -->
            <div class="tab-pane fade" id="offers">
                <div id="offers-container">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sellers Tab -->
            <div class="tab-pane fade" id="sellers">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-0">Registered Sellers</h5>
                            </div>
                            <div class="col-md-6 text-end">
                                <select class="form-select w-auto d-inline-block" id="seller-status-filter">
                                    <option value="">All Sellers</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="sellers-container">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Tab -->
            <div class="tab-pane fade" id="analytics">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="activity-chart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Top Categories</h5>
                            </div>
                            <div class="card-body" id="top-categories">
                                <div class="text-center p-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sale Details Modal -->
    <div class="modal fade" id="saleDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="saleDetailsTitle">Sale Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="saleDetailsContent">
                    <!-- Content loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        const basePath = '{$basePath}';
        let currentPage = 1;
        let activityChart = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadSales();
            
            // Bind filters
            document.getElementById('search-sales').addEventListener('input', debounce(loadSales, 300));
            document.getElementById('status-filter').addEventListener('change', loadSales);
            document.getElementById('seller-status-filter').addEventListener('change', loadSellers);
            
            // Tab change handlers
            document.querySelector('a[data-bs-toggle="tab"][href="#offers"]').addEventListener('shown.bs.tab', loadPendingOffers);
            document.querySelector('a[data-bs-toggle="tab"][href="#sellers"]').addEventListener('shown.bs.tab', loadSellers);
            document.querySelector('a[data-bs-toggle="tab"][href="#analytics"]').addEventListener('shown.bs.tab', loadAnalytics);
        });

        async function loadStatistics() {
            try {
                const response = await fetch(`\${basePath}/admin/claims/statistics`);
                const data = await response.json();

                if (data.success) {
                    const stats = data.statistics;
                    document.getElementById('total-sales').textContent = stats.sales.total || 0;
                    document.getElementById('active-sales').textContent = stats.sales.active || 0;
                    document.getElementById('total-items').textContent = stats.items.total || 0;
                    document.getElementById('pending-offers').textContent = stats.offers.pending || 0;
                    document.getElementById('pending-count').textContent = stats.offers.pending || 0;
                    document.getElementById('active-sellers').textContent = stats.sellers.total || 0;
                    document.getElementById('total-buyers').textContent = stats.buyers.total || 0;
                }
            } catch (error) {
                console.error('Failed to load statistics:', error);
            }
        }

        async function loadSales(page = 1) {
            currentPage = page;
            const search = document.getElementById('search-sales').value;
            const status = document.getElementById('status-filter').value;

            const params = new URLSearchParams({
                page: page,
                per_page: 20,
                ...(search && { search }),
                ...(status && { status })
            });

            try {
                const response = await fetch(`\${basePath}/admin/claims/sales?\${params}`);
                const data = await response.json();

                if (data.success) {
                    renderSales(data.sales);
                    renderPagination(data.pagination);
                } else {
                    showError(data.error || 'Failed to load sales');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        function renderSales(sales) {
            const container = document.getElementById('sales-container');
            
            if (sales.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No sales found</div>';
                return;
            }

            container.innerHTML = sales.map(sale => `
                <div class="sale-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h5 class="mb-2">
                                \${escapeHtml(sale.title)}
                                <span class="status-badge status-\${sale.status}">\${sale.status.toUpperCase()}</span>
                            </h5>
                            <p class="mb-1 text-muted">
                                <i class="bi bi-shop"></i> \${escapeHtml(sale.company_name)}
                            </p>
                            <p class="mb-2">
                                <i class="bi bi-calendar"></i> \${formatDate(sale.start_date)} - \${formatDate(sale.end_date)}
                                <span class="ms-3"><i class="bi bi-geo-alt"></i> \${escapeHtml(sale.location)}</span>
                            </p>
                            <div class="text-muted">
                                <span class="me-3">\${sale.item_count || 0} items</span>
                                <span class="me-3">\${sale.offer_count || 0} offers</span>
                                <span>$\${formatMoney(sale.total_offers || 0)} total value</span>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewSaleDetails(\${sale.id})">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateSaleStatus(\${sale.id}, '\${sale.status}')">
                                <i class="bi bi-gear"></i> Status
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function renderPagination(pagination) {
            const container = document.getElementById('sales-pagination');
            const totalPages = pagination.total_pages;
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '<ul class="pagination justify-content-center">';
            
            // Previous button
            html += `<li class="page-item \${pagination.page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadSales(\${pagination.page - 1}); return false;">Previous</a>
            </li>`;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= pagination.page - 2 && i <= pagination.page + 2)) {
                    html += `<li class="page-item \${i === pagination.page ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="loadSales(\${i}); return false;">\${i}</a>
                    </li>`;
                } else if (i === pagination.page - 3 || i === pagination.page + 3) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            // Next button
            html += `<li class="page-item \${pagination.page === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadSales(\${pagination.page + 1}); return false;">Next</a>
            </li>`;
            
            html += '</ul>';
            container.innerHTML = html;
        }

        async function loadPendingOffers() {
            try {
                const response = await fetch(`\${basePath}/admin/claims/offers/pending`);
                const data = await response.json();

                if (data.success) {
                    renderPendingOffers(data.offers);
                } else {
                    showError('Failed to load offers');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        function renderPendingOffers(offers) {
            const container = document.getElementById('offers-container');
            
            if (offers.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No pending offers</div>';
                return;
            }

            container.innerHTML = offers.map(offer => `
                <div class="offer-card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h6 class="mb-1">\${escapeHtml(offer.item_title)}</h6>
                            <p class="mb-0 text-muted">
                                Sale: \${escapeHtml(offer.sale_title)}
                            </p>
                        </div>
                        <div class="col-md-3">
                            <strong>$\${formatMoney(offer.amount)}</strong><br>
                            <small class="text-muted">
                                By: \${escapeHtml(offer.buyer_name)}<br>
                                \${escapeHtml(offer.buyer_email)}
                            </small>
                        </div>
                        <div class="col-md-3 text-end">
                            <small class="text-muted d-block mb-2">
                                \${new Date(offer.created_at).toLocaleString()}
                            </small>
                            <button class="btn btn-sm btn-success" onclick="handleOffer(\${offer.id}, 'accept')">
                                Accept
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="handleOffer(\${offer.id}, 'reject')">
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        async function loadSellers() {
            const status = document.getElementById('seller-status-filter').value;
            const params = new URLSearchParams(status ? { status } : {});

            try {
                const response = await fetch(`\${basePath}/admin/claims/sellers?\${params}`);
                const data = await response.json();

                if (data.success) {
                    renderSellers(data.sellers);
                } else {
                    showError('Failed to load sellers');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        function renderSellers(sellers) {
            const container = document.getElementById('sellers-container');
            
            if (sellers.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No sellers found</div>';
                return;
            }

            container.innerHTML = `
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Sales</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            \${sellers.map(seller => `
                                <tr>
                                    <td>\${escapeHtml(seller.company_name)}</td>
                                    <td>\${escapeHtml(seller.contact_name)}</td>
                                    <td>\${escapeHtml(seller.email)}</td>
                                    <td>\${escapeHtml(seller.phone)}</td>
                                    <td>
                                        \${seller.total_sales || 0} total<br>
                                        <small class="text-muted">\${seller.active_sales || 0} active</small>
                                    </td>
                                    <td>
                                        \${seller.is_active == '1' ? 
                                            '<span class="badge bg-success">Active</span>' : 
                                            '<span class="badge bg-danger">Inactive</span>'
                                        }
                                    </td>
                                    <td>
                                        \${seller.is_active == '1' ? 
                                            `<button class="btn btn-sm btn-outline-danger" onclick="deactivateSeller(\${seller.id})">
                                                Deactivate
                                            </button>` :
                                            `<button class="btn btn-sm btn-outline-success" onclick="approveSeller(\${seller.id})">
                                                Activate
                                            </button>`
                                        }
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        async function loadAnalytics() {
            // Load analytics data
            try {
                const response = await fetch(`\${basePath}/admin/claims/statistics`);
                const data = await response.json();

                if (data.success) {
                    const activity = data.statistics.recent_activity;
                    
                    // Render activity chart
                    const ctx = document.getElementById('activity-chart').getContext('2d');
                    
                    if (activityChart) {
                        activityChart.destroy();
                    }
                    
                    activityChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: activity.map(a => a.date),
                            datasets: [{
                                label: 'Offers',
                                data: activity.map(a => a.offers_count),
                                borderColor: 'rgb(75, 192, 192)',
                                tension: 0.1
                            }, {
                                label: 'Value ($)',
                                data: activity.map(a => a.offers_value),
                                borderColor: 'rgb(255, 99, 132)',
                                tension: 0.1,
                                yAxisID: 'y1'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left'
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: {
                                        drawOnChartArea: false
                                    }
                                }
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Failed to load analytics:', error);
            }
        }

        async function viewSaleDetails(saleId) {
            try {
                const response = await fetch(`\${basePath}/admin/claims/sales/\${saleId}/details`);
                const data = await response.json();

                if (data.success) {
                    const modal = new bootstrap.Modal(document.getElementById('saleDetailsModal'));
                    document.getElementById('saleDetailsTitle').textContent = data.sale.title;
                    document.getElementById('saleDetailsContent').innerHTML = renderSaleDetails(data);
                    modal.show();
                } else {
                    showError('Failed to load sale details');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        function renderSaleDetails(data) {
            const { sale, items, offers } = data;
            
            return `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Sale Information</h6>
                        <table class="table table-sm">
                            <tr><th>Company:</th><td>\${escapeHtml(sale.company_name)}</td></tr>
                            <tr><th>Status:</th><td><span class="status-badge status-\${sale.status}">\${sale.status}</span></td></tr>
                            <tr><th>Dates:</th><td>\${formatDate(sale.start_date)} - \${formatDate(sale.end_date)}</td></tr>
                            <tr><th>Location:</th><td>\${escapeHtml(sale.location)}</td></tr>
                            <tr><th>Access Code:</th><td>\${sale.access_code}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Statistics</h6>
                        <table class="table table-sm">
                            <tr><th>Total Items:</th><td>\${items.length}</td></tr>
                            <tr><th>Total Offers:</th><td>\${offers.length}</td></tr>
                            <tr><th>Total Value:</th><td>$\${formatMoney(offers.reduce((sum, o) => sum + parseFloat(o.amount), 0))}</td></tr>
                        </table>
                    </div>
                </div>
                
                <h6 class="mt-4">Items (\${items.length})</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Offers</th>
                            </tr>
                        </thead>
                        <tbody>
                            \${items.map(item => `
                                <tr>
                                    <td>\${escapeHtml(item.title)}</td>
                                    <td>\${item.category_id}</td>
                                    <td>$\${formatMoney(item.price || 0)}</td>
                                    <td>\${item.claim_status}</td>
                                    <td>\${item.offer_count || 0} ($\${formatMoney(item.highest_offer || 0)})</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                
                <h6 class="mt-4">Recent Offers (\${offers.length})</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Buyer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            \${offers.slice(0, 10).map(offer => `
                                <tr>
                                    <td>\${escapeHtml(offer.item_title)}</td>
                                    <td>\${escapeHtml(offer.buyer_name)}<br><small>\${escapeHtml(offer.buyer_email)}</small></td>
                                    <td>$\${formatMoney(offer.amount)}</td>
                                    <td>\${offer.status}</td>
                                    <td>\${new Date(offer.created_at).toLocaleDateString()}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        async function updateSaleStatus(saleId, currentStatus) {
            const newStatus = prompt(`Update status (current: \${currentStatus})\\n\\nOptions: upcoming, active, completed, cancelled`);
            
            if (!newStatus || newStatus === currentStatus) return;
            
            try {
                const response = await fetch(`\${basePath}/admin/claims/sales/\${saleId}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ status: newStatus })
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('Status updated successfully');
                    loadSales(currentPage);
                } else {
                    showError(data.error || 'Failed to update status');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        async function approveSeller(sellerId) {
            if (!confirm('Activate this seller?')) return;

            try {
                const response = await fetch(`\${basePath}/admin/claims/sellers/\${sellerId}/approve`, {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('Seller activated successfully');
                    loadSellers();
                } else {
                    showError(data.error || 'Failed to activate seller');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        async function deactivateSeller(sellerId) {
            if (!confirm('Deactivate this seller?')) return;

            try {
                const response = await fetch(`\${basePath}/admin/claims/sellers/\${sellerId}/deactivate`, {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('Seller deactivated successfully');
                    loadSellers();
                } else {
                    showError(data.error || 'Failed to deactivate seller');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        // Utility functions
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            return new Date(dateStr).toLocaleDateString();
        }

        function formatMoney(amount) {
            return parseFloat(amount).toFixed(2);
        }

        function showSuccess(message) {
            alert(message);
        }

        function showError(message) {
            alert('Error: ' + message);
        }
    </script>
</body>
</html>
HTML;
    }
}