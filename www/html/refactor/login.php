<?php
require_once __DIR__ . '/../vendor/autoload.php';
use YFEvents\Helpers\PathHelper;

session_start();

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4",
            'yfevents',
            'yfevents_pass',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash, first_name, last_name, role FROM users WHERE email = :email AND status = 'active'");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            // Check if user is a vendor/seller (for YFClaim/estate sales)
            // Vendors are identified by having an entry in yfc_sellers table
            try {
                $stmt = $pdo->prepare("SELECT id, company_name FROM yfc_sellers WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $user['id']]);
                $seller = $stmt->fetch();
                
                if ($seller) {
                    $_SESSION['is_vendor'] = true;
                    $_SESSION['seller_id'] = $seller['id'];
                    $_SESSION['company_name'] = $seller['company_name'];
                }
            } catch (PDOException $e) {
                // Table might not exist, ignore
            }
            
            // Check if user owns a local shop
            $stmt = $pdo->prepare("SELECT id, name FROM local_shops WHERE owner_id = :user_id");
            $stmt->execute(['user_id' => $user['id']]);
            $shop = $stmt->fetch();
            
            if ($shop) {
                $_SESSION['shop_id'] = $shop['id'];
                $_SESSION['shop_name'] = $shop['name'];
                $_SESSION['is_shop_owner'] = true;
            }
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                // Admin goes to admin dashboard
                header('Location: /refactor/admin/dashboard');
            } elseif (isset($_SESSION['is_vendor']) && $_SESSION['is_vendor']) {
                // Sellers/vendors go to seller dashboard
                header('Location: /refactor/seller/dashboard');
            } elseif (isset($_SESSION['is_shop_owner']) && $_SESSION['is_shop_owner']) {
                // Shop owners go to shop management
                header('Location: /refactor/shops/manage');
            } else {
                // Regular users go to communication hub
                $redirect = $_GET['redirect'] ?? '/refactor/communication/';
                header('Location: ' . $redirect);
            }
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    } catch (Exception $e) {
        $error = 'Login failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - YFEvents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>Login to YFEvents</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Login</button>
                            <a href="<?= PathHelper::url() ?>" class="btn btn-link">Back to Home</a>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <small>
                                Don't have an account? <a href="<?= PathHelper::url('register') ?>">Register here</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>