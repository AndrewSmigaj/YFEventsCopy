# YFClaim Development Guide

## Getting Started

### 1. Install Database Tables
```bash
cd /home/robug/YFEvents
mysql -u yfevents -p yakima_finds < modules/yfclaim/database/schema.sql
```

### 2. Verify Installation
- Visit: `http://137.184.245.149/modules/yfclaim/www/admin/`
- Should show dashboard with 0 stats (empty database)
- No errors should appear

### 3. Test Model Classes
```bash
cd /home/robug/YFEvents
php -r "
require 'vendor/autoload.php';
require 'config/database.php';
use YFEvents\Modules\YFClaim\Models\SellerModel;
\$model = new SellerModel(\$db);
echo 'SellerModel loaded successfully\n';
"
```

## Development Workflow

### Step 1: Complete Model Implementation
Start with `SellerModel.php`:

```php
<?php
namespace YFEvents\Modules\YFClaim\Models;

class SellerModel extends BaseModel 
{
    protected $table = 'yfc_sellers';
    
    public function createSeller($data) {
        // Validate required fields
        $required = ['company_name', 'contact_name', 'email', 'phone'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Insert seller
        $sql = "INSERT INTO yfc_sellers (company_name, contact_name, email, phone, address, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['company_name'],
            $data['contact_name'], 
            $data['email'],
            $data['phone'],
            $data['address'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // Add other methods...
}
```

### Step 2: Connect Admin Interface
Update `sellers.php` to use the model:

```php
// Replace direct SQL with model calls
$sellerModel = new SellerModel($db);

if ($_POST['action'] === 'add') {
    $sellerId = $sellerModel->createSeller($_POST);
    $message = "Seller created successfully!";
}
```

### Step 3: Create Test Data
```sql
-- Test seller
INSERT INTO yfc_sellers (company_name, contact_name, email, phone, status) 
VALUES ('Test Estate Sales', 'John Doe', 'john@test.com', '509-555-1234', 'approved');

-- Test sale
INSERT INTO yfc_sales (seller_id, title, description, start_date, end_date, status) 
VALUES (1, 'Test Estate Sale', 'Sample sale for testing', '2025-06-10', '2025-06-12', 'active');
```

## File Structure
```
modules/yfclaim/
├── database/
│   └── schema.sql ✅
├── src/
│   ├── Models/
│   │   ├── BaseModel.php ✅
│   │   ├── SellerModel.php ⚠️  (needs implementation)
│   │   ├── SaleModel.php ⚠️   (needs implementation)
│   │   ├── ItemModel.php ⚠️   (needs implementation)
│   │   └── OfferModel.php ⚠️  (needs implementation)
│   └── Services/
│       └── ClaimAuthService.php ⚠️ (needs implementation)
├── www/
│   ├── admin/ ✅ (templates ready)
│   ├── api/ ⚠️ (needs implementation)
│   └── dashboard/ ⚠️ (needs implementation)
└── docs/ ✅
```

## Testing Strategy

### 1. Unit Testing
```bash
# Test model methods individually
php test_seller_model.php
```

### 2. Integration Testing
- Test admin interface CRUD operations
- Verify database constraints
- Test foreign key relationships

### 3. User Acceptance Testing
- Admin workflow: Create seller → Create sale → Add items
- Buyer workflow: Browse sale → Make offer → Check status

## Next Steps

1. **Install database** (5 minutes)
2. **Implement SellerModel** (30 minutes)
3. **Connect sellers.php to model** (15 minutes)
4. **Test seller CRUD operations** (15 minutes)
5. **Repeat for other models**

Total estimated time for basic functionality: **4-6 hours**

## Common Issues

1. **Namespace errors**: Ensure autoloader is working
2. **Database connection**: Verify $db variable is available
3. **Foreign key constraints**: Create in correct order (sellers → sales → items → offers)
4. **JSON fields**: Handle empty values properly (use NULL, not empty strings)

## Resources

- Database schema: `modules/yfclaim/database/schema.sql`
- Admin templates: `modules/yfclaim/www/admin/`
- Model examples: `src/Models/EventModel.php` (for reference)