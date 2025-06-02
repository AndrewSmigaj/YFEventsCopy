# YFClaim - Facebook-Style Claim Sale Platform

## Overview

YFClaim is a Facebook-style claim sale module for YFEvents that enables estate sale companies to run online claim sales. Unlike traditional auctions, this platform uses a claim sale format where:

- Sellers post items with starting prices
- Buyers make offers (claims) on items
- Offers are shown as price ranges to other buyers
- Sellers choose winning offers from top claims
- Similar to Facebook Marketplace auction groups

## Features

### For Sellers (Estate Sale Companies)
- **Full Authentication System**: Secure login and account management
- **Sale Management**: Create and manage multiple claim sales
- **Item Listings**: Upload items with multiple photos and detailed descriptions
- **Offer Management**: View and select winning offers from buyers
- **QR Code Access**: Generate QR codes for easy buyer access at physical locations
- **Analytics Dashboard**: Track sale performance and buyer engagement

### For Buyers
- **Temporary Authentication**: Access sales via SMS/email verification
- **Easy Access**: Join sales via QR code, access code, or direct link
- **Claim System**: Make offers on multiple items
- **Price Range Display**: See offer ranges without exact amounts
- **Mobile Optimized**: Designed for on-the-go browsing at estate sales

## Technical Architecture

### Module Structure
```
modules/yfclaim/
├── module.json          # Module configuration
├── database/            # SQL schemas
│   └── schema.sql      # Complete database structure
├── src/                 # PHP source code
│   ├── Models/         # Data models
│   ├── Controllers/    # Business logic
│   └── Services/       # Utility services
├── www/                 # Public web files
│   ├── admin/          # Seller admin interface
│   ├── api/            # API endpoints
│   ├── assets/         # CSS, JS, images
│   └── templates/      # View templates
└── README.md           # This file
```

### Database Design
- `yfc_sellers`: Estate sale companies
- `yfc_sales`: Individual claim sales
- `yfc_items`: Items in each sale
- `yfc_offers`: Buyer offers/claims
- `yfc_buyers`: Temporary buyer accounts
- `yfc_categories`: Item categorization

### API Endpoints

#### Public API
- `GET /api/modules/yfclaim/sales`: Active sales list
- `GET /api/modules/yfclaim/sales/{id}`: Sale details and items
- `POST /api/modules/yfclaim/auth`: Buyer authentication
- `POST /api/modules/yfclaim/offers`: Submit offer

#### Seller API
- `POST /api/modules/yfclaim/sellers/login`: Seller authentication
- `GET /api/modules/yfclaim/sellers/sales`: Manage sales
- `POST /api/modules/yfclaim/items`: Add/edit items
- `PUT /api/modules/yfclaim/offers/{id}/accept`: Accept offer

## Installation

1. **Install the module**:
   ```bash
   php modules/install.php yfclaim
   ```

2. **Configure settings**:
   - Set up SMS/Email services for buyer authentication
   - Configure image upload limits
   - Set offer increment amounts

3. **Access admin panel**:
   - Navigate to `/admin/modules/yfclaim/`
   - Create seller accounts
   - Start creating claim sales

## Configuration

### Module Settings
- `enable_sms_auth`: Enable SMS authentication (default: true)
- `max_images_per_item`: Maximum images per item (default: 5)
- `offer_increment`: Minimum offer increment (default: $5)
- `show_price_ranges`: Show ranges instead of exact offers (default: true)

### Environment Variables
Add to your `.env` file:
```env
# SMS Service (Twilio)
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_PHONE_NUMBER=+1234567890

# Email Service
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=your_email
SMTP_PASS=your_password
```

## Usage Guide

### Creating a Claim Sale
1. Log in to seller dashboard
2. Click "Create New Sale"
3. Fill in sale details and location
4. Set claim period and pickup times
5. Add items with photos
6. Publish and share QR code

### Buyer Flow
1. Scan QR code or visit sale link
2. Enter phone/email for verification
3. Browse items and make offers
4. Receive notifications on winning claims
5. Arrange pickup during scheduled times

## Development

### Adding New Features
Follow YFEvents coding standards:
- Use PSR-4 autoloading
- Extend BaseModel for data models
- Use prepared statements for database queries
- Follow existing template patterns

### Testing
```bash
# Run module tests
php vendor/bin/phpunit modules/yfclaim/tests/
```

## Support

For issues or questions:
- Check module logs in `logs/yfclaim/`
- Review error messages in admin dashboard
- Contact support at admin@yakimafinds.com

## License

This module is part of YFEvents and follows the same license terms.