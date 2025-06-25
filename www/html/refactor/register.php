<?php
require_once __DIR__ . '/../vendor/autoload.php';
use YFEvents\Helpers\PathHelper;

session_start();

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $accountType = $_POST['account_type'] ?? 'user';
    
    // Additional fields for sellers/vendors
    $companyName = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($firstName)) $errors[] = 'First name is required';
    if (empty($lastName)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (empty($password)) $errors[] = 'Password is required';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';
    
    if ($accountType === 'seller' && empty($companyName)) {
        $errors[] = 'Company name is required for seller accounts';
    }
    
    if (empty($errors)) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=yakima_finds;charset=utf8mb4",
                'yfevents',
                'yfevents_pass',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already registered';
            } else {
                // Begin transaction
                $pdo->beginTransaction();
                
                try {
                    // Create user account
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $username = strtolower($firstName . '.' . $lastName);
                    
                    // Ensure unique username
                    $baseUsername = $username;
                    $counter = 1;
                    while (true) {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
                        $stmt->execute(['username' => $username]);
                        if (!$stmt->fetch()) break;
                        $username = $baseUsername . $counter++;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, first_name, last_name, role, status, created_at)
                        VALUES (:username, :email, :password_hash, :first_name, :last_name, :role, 'active', NOW())
                    ");
                    
                    $stmt->execute([
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => $passwordHash,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'role' => 'user' // All users start with basic role
                    ]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // If seller/vendor account, create seller profile
                    if ($accountType === 'seller') {
                        $stmt = $pdo->prepare("
                            INSERT INTO yfc_sellers (user_id, company_name, contact_email, contact_phone, status, created_at)
                            VALUES (:user_id, :company_name, :email, :phone, 'pending', NOW())
                        ");
                        
                        $stmt->execute([
                            'user_id' => $userId,
                            'company_name' => $companyName,
                            'email' => $email,
                            'phone' => $phone
                        ]);
                    }
                    
                    $pdo->commit();
                    
                    // Auto-login the user
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'user';
                    
                    if ($accountType === 'seller') {
                        $_SESSION['is_vendor'] = true;
                        $_SESSION['company_name'] = $companyName;
                        // Redirect to seller onboarding or dashboard
                        header('Location: /refactor/seller/onboarding');
                    } else {
                        // Redirect to communication hub or where they came from
                        $redirect = $_GET['redirect'] ?? '/refactor/communication/';
                        header('Location: ' . $redirect);
                    }
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - YFEvents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .account-type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .account-type {
            flex: 1;
            padding: 1.5rem;
            border: 2px solid #dee2e6;
            border-radius: 0.5rem;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        .account-type:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .account-type.active {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .account-type h5 {
            margin-bottom: 0.5rem;
            color: #212529;
        }
        .account-type p {
            margin: 0;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .seller-fields {
            display: none;
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Create Your YFEvents Account</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="registrationForm">
                            <!-- Account Type Selection -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Choose Account Type</label>
                                <div class="account-type-selector">
                                    <div class="account-type active" data-type="user">
                                        <h5>🙋 Individual User</h5>
                                        <p>Browse events, join discussions, make offers on estate sales</p>
                                    </div>
                                    <div class="account-type" data-type="seller">
                                        <h5>🏢 Estate Sale Company</h5>
                                        <p>List and manage estate sales, track offers and buyers</p>
                                    </div>
                                </div>
                                <input type="hidden" name="account_type" id="account_type" value="user">
                            </div>
                            
                            <!-- Basic Information -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                <div class="form-text">This will be your login email</div>
                            </div>
                            
                            <!-- Seller-specific fields -->
                            <div class="seller-fields" id="sellerFields">
                                <h5 class="mb-3">Company Information</h5>
                                <div class="mb-3">
                                    <label for="company_name" class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" 
                                           value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Business Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                                <div class="alert alert-info">
                                    <small>Your seller account will be reviewed by our team before activation. You'll receive an email once approved.</small>
                                </div>
                            </div>
                            
                            <!-- Password fields -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="password-requirements">Minimum 8 characters</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                                <a href="<?= PathHelper::url('login.php') ?>" class="btn btn-outline-secondary">Already have an account? Login</a>
                                <a href="<?= PathHelper::url() ?>" class="btn btn-link">Back to Home</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Account type selector
        document.querySelectorAll('.account-type').forEach(type => {
            type.addEventListener('click', function() {
                // Remove active class from all
                document.querySelectorAll('.account-type').forEach(t => t.classList.remove('active'));
                
                // Add active to clicked
                this.classList.add('active');
                
                // Update hidden field
                const accountType = this.dataset.type;
                document.getElementById('account_type').value = accountType;
                
                // Show/hide seller fields
                const sellerFields = document.getElementById('sellerFields');
                if (accountType === 'seller') {
                    sellerFields.style.display = 'block';
                    document.getElementById('company_name').required = true;
                } else {
                    sellerFields.style.display = 'none';
                    document.getElementById('company_name').required = false;
                }
            });
        });
        
        // Form validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>