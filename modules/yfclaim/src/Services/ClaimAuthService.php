<?php
namespace YFEvents\Modules\YFClaim\Services;

use PDO;
use YFEvents\Modules\YFAuth\Services\AuthService;
use YFEvents\Modules\YFClaim\Models\SellerModel;

class ClaimAuthService {
    private $db;
    private $authService;
    private $sellerModel;
    
    public function __construct(PDO $db) {
        $this->db = $db;
        $this->authService = new AuthService($db);
        $this->sellerModel = new SellerModel($db);
    }
    
    /**
     * Authenticate seller using YFAuth
     */
    public function authenticateSeller($username, $password) {
        // First try YFAuth authentication
        $authResult = $this->authService->authenticate($username, $password);
        
        if (!$authResult['success']) {
            return $authResult;
        }
        
        // Check if user has seller role (was claim_seller)
        $hasClaimRole = false;
        foreach ($authResult['user']['roles'] as $role) {
            if ($role['name'] === 'seller' || $role['name'] === 'claim_seller') {
                $hasClaimRole = true;
                break;
            }
        }
        
        if (!$hasClaimRole) {
            return [
                'success' => false,
                'error' => 'You do not have permission to access claim sales. Please contact an administrator.'
            ];
        }
        
        // Check if seller profile exists in YFClaim
        $seller = $this->sellerModel->findByEmail($authResult['user']['email']);
        
        if (!$seller) {
            // Create seller profile automatically
            $sellerId = $this->createSellerProfile($authResult['user']);
            $seller = $this->sellerModel->find($sellerId);
        }
        
        // Update seller's last login
        $this->sellerModel->update($seller['id'], ['last_login' => date('Y-m-d H:i:s')]);
        
        return [
            'success' => true,
            'auth_user' => $authResult['user'],
            'seller' => $seller,
            'session_id' => $authResult['session_id']
        ];
    }
    
    /**
     * Create seller profile from YFAuth user
     */
    private function createSellerProfile($authUser) {
        $sellerData = [
            'email' => $authUser['email'],
            'contact_name' => trim(($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? '')),
            'company_name' => $authUser['username'], // Default to username, can be updated later
            'status' => 'active'
        ];
        
        return $this->sellerModel->create($sellerData);
    }
    
    /**
     * Validate seller session
     */
    public function validateSellerSession($sessionId) {
        $user = $this->authService->validateSession($sessionId);
        
        if (!$user) {
            return null;
        }
        
        // Check seller role (was claim_seller)
        $hasClaimRole = false;
        foreach ($user['roles'] as $role) {
            if ($role['name'] === 'seller' || $role['name'] === 'claim_seller') {
                $hasClaimRole = true;
                break;
            }
        }
        
        if (!$hasClaimRole) {
            return null;
        }
        
        // Get seller profile
        $seller = $this->sellerModel->findByEmail($user['email']);
        
        return [
            'auth_user' => $user,
            'seller' => $seller
        ];
    }
    
    /**
     * Check if user can access seller dashboard
     */
    public function canAccessSellerDashboard($userId) {
        return $this->authService->hasRole($userId, 'claim_seller') || 
               $this->authService->hasPermission($userId, 'claims.edit_all');
    }
    
    /**
     * Check if user can manage specific sale
     */
    public function canManageSale($userId, $saleId) {
        // Super admins can manage all sales
        if ($this->authService->hasPermission($userId, 'claims.edit_all')) {
            return true;
        }
        
        // Sellers can manage their own sales
        $user = $this->authService->validateSession($_SESSION['auth_session_id'] ?? '');
        if ($user) {
            $seller = $this->sellerModel->findByEmail($user['email']);
            if ($seller) {
                $saleModel = new \YFEvents\Modules\YFClaim\Models\SaleModel($this->db);
                $sale = $saleModel->find($saleId);
                return $sale && $sale['seller_id'] == $seller['id'];
            }
        }
        
        return false;
    }
    
    /**
     * Register new seller (creates both YFAuth user and seller profile)
     */
    public function registerSeller($data) {
        $this->db->beginTransaction();
        
        try {
            // Create YFAuth user
            $authUser = $this->authService->createUser([
                'email' => $data['email'],
                'username' => $data['username'] ?? $data['email'],
                'password' => $data['password'],
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'default_role' => 'claim_seller',
                'auto_activate' => false // Require approval
            ]);
            
            // Create seller profile
            $sellerData = [
                'email' => $data['email'],
                'contact_name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
                'company_name' => $data['company_name'],
                'phone' => $data['phone'] ?? '',
                'address' => $data['address'] ?? '',
                'city' => $data['city'] ?? '',
                'state' => $data['state'] ?? 'WA',
                'zip' => $data['zip'] ?? '',
                'status' => 'pending' // Require approval
            ];
            
            $sellerId = $this->sellerModel->create($sellerData);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'auth_user_id' => $authUser['id'],
                'seller_id' => $sellerId,
                'message' => 'Registration successful. Your account is pending approval.'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Approve seller account
     */
    public function approveSeller($sellerId) {
        $seller = $this->sellerModel->find($sellerId);
        if (!$seller) {
            return false;
        }
        
        $this->db->beginTransaction();
        
        try {
            // Update seller status
            $this->sellerModel->update($sellerId, ['status' => 'active']);
            
            // Find and activate YFAuth user
            $stmt = $this->db->prepare("SELECT id FROM yfa_auth_users WHERE email = ?");
            $stmt->execute([$seller['email']]);
            $authUserId = $stmt->fetchColumn();
            
            if ($authUserId) {
                $stmt = $this->db->prepare("UPDATE yfa_auth_users SET status = 'active' WHERE id = ?");
                $stmt->execute([$authUserId]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get pending seller registrations
     */
    public function getPendingSellers() {
        return $this->sellerModel->all(['status' => 'pending'], 'created_at ASC');
    }
}