# YFEvents - Community Events Platform

A comprehensive event calendar system with local business directory, estate sales management, and automated event scraping capabilities.

## 🚀 Overview

YFEvents is a modern PHP application built with Clean Architecture principles, providing:

- **Event Calendar** - Multi-view calendar with Google Maps integration
- **Shop Directory** - Local business listings with geocoding
- **Estate Sales** - Complete estate sale management system (YFClaim module)
- **Event Scraping** - Automated collection from multiple sources
- **Unified Authentication** - Centralized auth system for all modules
- **Real-time Chat** - Integrated communication system for sellers and admins

**Version**: 2.3.0  
**Status**: Production Ready  
**License**: MIT

## 🎯 Key Features

### Public Features
- Responsive calendar views (month, week, list, map)
- Interactive Google Maps with event/shop pins
- Event search and filtering by category/location
- Mobile-optimized interface with GPS support
- Community event submission
- Estate sale item gallery with advanced filtering
- Dynamic homepage with real-time stats

### Administrative Features
- Advanced admin dashboard
- Event approval and management
- Automated event scraping from multiple sources
- Shop/business directory management
- Estate sale system for sellers
- Geocoding verification tools

### Technical Features
- Clean Architecture (Hexagonal/DDD)
- Modular system architecture
- RESTful API design
- Unified authentication via YFAuth
- No framework dependencies

## 📋 Requirements

- PHP 8.1+ with extensions: pdo_mysql, curl, mbstring, json, xml
- MySQL 8.0+ or MariaDB 10.3+
- Apache 2.4+ with mod_rewrite or Nginx
- Composer 2.x
- Google Maps API key

## 🚀 Quick Start

### Option 1: Automated Deployment (Recommended)

Deploy to a fresh Ubuntu 22.04 server (e.g., Digital Ocean droplet):

```bash
# SSH into your server
ssh root@your-server-ip

# Download and run setup script
wget https://raw.githubusercontent.com/AndrewSmigaj/YFEventsCopy/main/scripts/deploy/setup-server.sh
chmod +x setup-server.sh
sudo ./setup-server.sh

# Download and run deployment script
wget https://raw.githubusercontent.com/AndrewSmigaj/YFEventsCopy/main/scripts/deploy/deploy.sh
chmod +x deploy.sh
sudo ./deploy.sh https://github.com/AndrewSmigaj/YFEventsCopy.git
```

The deployment script will:
- Clone the repository to `/var/www/yfevents`
- Install all dependencies
- Configure the database
- Set up Apache with SSL
- Create your first admin user
- Configure automated event scraping

### Option 2: Manual Installation

1. **Clone the repository**
```bash
git clone https://github.com/AndrewSmigaj/YFEventsCopy.git
cd YFEventsCopy
```

2. **Install dependencies**
```bash
composer install
```

3. **Configure environment**
```bash
cp .env.example .env
# Edit .env with your settings
```

4. **Import database**
```bash
# Run in order (see database/INSTALL_ORDER.md)
mysql -u root -p your_database < database/calendar_schema.sql
mysql -u root -p your_database < database/shop_claim_system.sql
mysql -u root -p your_database < database/communication_schema_fixed.sql
mysql -u root -p your_database < database/seed_communication_channels.sql
mysql -u root -p your_database < modules/yfauth/database/schema.sql
mysql -u root -p your_database < modules/yfclaim/database/schema.sql
```

5. **Configure web server**
```apache
<VirtualHost *:80>
    DocumentRoot /path/to/yfevents/public
    <Directory /path/to/yfevents/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

6. **Access the application**
- Main site: http://your-domain.com
- Admin panel: http://your-domain.com/admin
- API endpoints: http://your-domain.com/api/

## 🏗️ Architecture

YFEvents follows Clean Architecture principles:

```
src/
├── Domain/          # Business logic and entities
├── Application/     # Use cases and services
├── Infrastructure/  # External implementations
└── Presentation/    # Controllers and views

modules/
├── yfauth/         # Authentication system
├── yfclaim/        # Estate sales system
├── yftheme/        # Theme customization
└── Communication/  # Chat system (in Domain layer)
```

For detailed architecture documentation, see [architecture.yaml](architecture.yaml).

## 🔧 Configuration

### Environment Variables

Key configuration in `.env`:

```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_HOST=localhost
DB_NAME=yakima_finds
DB_USER=your_user
DB_PASS=your_password

# API Keys
GOOGLE_MAPS_API_KEY=your_api_key
SEGMIND_API_KEY=your_api_key  # For AI scraping

# Email
MAIL_HOST=smtp.example.com
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password
```

### Module Configuration

Modules can be configured in their respective directories:
- Authentication: `modules/yfauth/config/`
- Estate Sales: `modules/yfclaim/config/`

## 📚 Documentation

- [CLAUDE.md](CLAUDE.md) - AI assistant guidelines
- [SECURITY.md](SECURITY.md) - Security best practices
- [architecture.yaml](architecture.yaml) - System architecture
- [database/INSTALL_ORDER.md](database/INSTALL_ORDER.md) - Database setup
- [modules/README.md](modules/README.md) - Module development
- [docs/CHAT_SYSTEM_DOCUMENTATION.md](docs/CHAT_SYSTEM_DOCUMENTATION.md) - Chat system implementation

## 🔌 API Reference

### Public Endpoints

```
GET  /api/events              # List events
GET  /api/events/{id}         # Get event details
GET  /api/shops               # List shops
GET  /api/shops/{id}          # Get shop details
POST /api/events/submit       # Submit new event
```

### Authenticated Endpoints

```
POST /api/auth/login          # User login
POST /api/auth/logout         # User logout
GET  /api/admin/events        # Admin: list events
POST /api/admin/events/{id}   # Admin: update event
```

### Communication Endpoints (Authenticated)

```
GET  /api/communication/unread-count      # Get unread message count
GET  /communication/?embedded=true        # Embedded chat interface
```

For full chat API documentation, see [Chat System Documentation](docs/CHAT_SYSTEM_DOCUMENTATION.md).

## 🛠️ Development

### Code Style
- PSR-12 coding standards
- Type declarations required
- Clean Architecture principles

### Testing
```bash
# Run all tests
php tests/run_all_tests.php

# Test specific components
php tests/test_core_functionality.php
php tests/test_web_interfaces.php
php tests/test_chat_system_updated.php
```

### Adding Features
1. Follow the module structure in `modules/`
2. Use dependency injection via the container
3. Add routes in `routes/web.php`
4. Document API changes

## 🚀 Deployment

YFEvents includes automated deployment scripts for production servers.

### Prerequisites
- Ubuntu 22.04 server (e.g., Digital Ocean droplet with 2GB+ RAM)
- Root access to the server
- Domain name pointed to server IP

### Automated Deployment

See the [Quick Start](#-quick-start) section above for the automated deployment process.

### Post-Deployment

1. Configure Google Maps API key in `.env`
2. Set up email credentials for notifications
3. Monitor logs at `/var/www/yfevents/storage/logs/`
4. Access admin panel to manage events and users

### Maintenance

- Event scraping runs automatically via cron
- Logs rotate daily
- Run health check: `php scripts/deploy/health-check.php`

## 📝 Recent Updates

### Version 2.3.0 (July 2025)
- **Homepage Overhaul**: Dynamic content with real-time stats
- **Item Gallery**: Browse all estate sale items across sales
  - Advanced filtering by category, price range, search
  - Sort by newest, price, ending soon
  - Responsive grid layout with pagination
- **Improved UX**: Better navigation and discovery features
- **Bug Fixes**: Database column mappings, repository registration

### Version 2.2.0
- **Chat System**: Real-time communication for sellers and admins
- **Authentication**: Unified auth system via YFAuth module
- **YFClaim**: Refactored to remove bidding, focus on contact forms

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Follow coding standards
4. Add tests for new features
5. Submit a pull request

## 📝 License

This project is licensed under the MIT License. See [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Built for Yakima Valley community
- Google Maps API for mapping features
- Segmind API for intelligent scraping

---

For more information or support, please refer to the documentation or submit an issue on GitHub.