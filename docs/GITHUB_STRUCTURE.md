# GitHub Repository Structure for YFEvents with Modules

## Repository Organization

### Single Repository Approach (Recommended)
Keep everything in the main YFEvents repository with modules as subdirectories:

```
YFEvents/
├── .github/
│   ├── workflows/
│   │   ├── core-tests.yml
│   │   └── module-tests.yml
│   └── CODEOWNERS
├── database/
├── src/
├── www/
├── modules/              # All modules live here
│   ├── README.md        # Module development guide
│   ├── install.php      # Module installer
│   └── yfauction/       # First module
│       ├── module.json
│       ├── database/
│       ├── src/
│       └── README.md
├── .gitignore
├── README.md
└── CLAUDE.md
```

### Advantages:
- Single repository to manage
- Easier dependency management
- Simpler CI/CD setup
- Better for small team development
- Modules can reference core code easily

## .gitignore Updates

Add these entries for module development:

```gitignore
# Module-specific ignores
modules/*/vendor/
modules/*/node_modules/
modules/*/cache/
modules/*/logs/
modules/*/config/local.php

# Module uploads (but keep directory structure)
www/html/modules/*/uploads/*
!www/html/modules/*/uploads/.gitkeep

# Module build artifacts
modules/*/dist/
modules/*/build/
```

## Branch Strategy

### Main Branches
- `main` - Production-ready code
- `develop` - Integration branch for features
- `module/[name]` - Development branches for each module

### Feature Branches
- `feature/core-[feature-name]` - Core YFEvents features
- `feature/module-[module-name]-[feature]` - Module-specific features

### Example Workflow
```bash
# Working on YFAuction module
git checkout -b feature/module-yfauction-seller-dashboard
# Make changes
git add modules/yfauction/
git commit -m "feat(yfauction): Add seller dashboard"
git push origin feature/module-yfauction-seller-dashboard
# Create PR to develop branch
```

## Module Release Process

### Version Tags
Use semantic versioning with module prefixes:
- `v1.0.0` - Core YFEvents releases
- `module-yfauction-v1.0.0` - Module-specific releases

### Release Notes Template
```markdown
## YFEvents v1.1.0

### Core Changes
- Feature: Added module support system
- Fix: Improved geocoding performance

### Module Updates
- **YFAuction v1.0.0** (NEW)
  - Initial release of estate sale auction module
  - Seller authentication system
  - Basic auction functionality
```

## CI/CD Considerations

### GitHub Actions for Modules
Create workflows that:
1. Test core functionality
2. Test each module independently
3. Check module compatibility

Example workflow structure:
```yaml
name: Module Tests
on:
  push:
    paths:
      - 'modules/**'
  pull_request:
    paths:
      - 'modules/**'

jobs:
  test-modules:
    strategy:
      matrix:
        module: [yfauction, future-module]
    steps:
      - uses: actions/checkout@v3
      - name: Test ${{ matrix.module }}
        run: |
          php modules/install.php ${{ matrix.module }}
          # Run module-specific tests
```

## Documentation Structure

```
docs/
├── README.md              # Main documentation
├── CONTRIBUTING.md        # How to contribute
├── MODULES.md            # Module development guide
├── modules/              # Module-specific docs
│   └── yfauction/
│       ├── API.md        # API documentation
│       ├── INSTALL.md    # Installation guide
│       └── USER_GUIDE.md # End-user documentation
└── GITHUB_STRUCTURE.md   # This file
```

## Module Submission Guidelines

For external module developers:
1. Fork the repository
2. Create module in `modules/` directory
3. Follow module structure guidelines
4. Include comprehensive tests
5. Submit PR with:
   - Module manifest
   - Documentation
   - Example configuration
   - Screenshots (if UI changes)

## Security Considerations

### Code Review Requirements
- All module code must be reviewed
- Security scan for SQL injection
- Check for XSS vulnerabilities
- Validate file upload handling
- Review authentication/authorization

### Module Permissions
Document required permissions in module.json:
```json
{
  "permissions": [
    "database_write",
    "file_upload",
    "send_email"
  ]
}
```

## Future Considerations

### Module Marketplace
Consider creating a module registry:
- Official modules in main repo
- Community modules as separate repos
- Module discovery/search functionality
- Compatibility matrix

### Module Dependencies
Plan for modules that depend on other modules:
```json
{
  "requires": {
    "yfevents": ">=1.0.0",
    "modules": {
      "yfpayments": ">=1.0.0"
    }
  }
}
```