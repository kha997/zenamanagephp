# Button Test Suite - ZenaManage

## Overview
Comprehensive testing suite for all interactive elements (buttons, links, forms, Alpine.js actions) across all views in the ZenaManage application.

## Features
- **Complete Button Inventory**: Automated scanning of all Blade views and components
- **Role-based Testing**: Tests for all user roles (super_admin, admin, pm, designer, engineer, guest)
- **Tenant Isolation**: Multi-tenant security validation
- **CRUD Operations**: Complete Create, Read, Update, Delete testing
- **Security Testing**: CSRF, authentication, authorization validation
- **Error Handling**: Comprehensive error state testing
- **Coverage Reporting**: Detailed coverage metrics and reporting

## Quick Start

### Prerequisites
- PHP 8.1+
- MySQL 8.0+
- Composer
- Node.js (for frontend assets)

### Installation
```bash
# Clone the repository
git clone <repository-url>
cd zenamanage

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env.testing
php artisan key:generate --env=testing

# Run migrations
php artisan migrate --env=testing

# Run seeders
php artisan db:seed --env=testing
```

### Running Tests
```bash
# Run complete test suite
./run_button_tests.sh

# Or run individual test categories
php artisan test tests/Feature/Buttons/ --env=testing
php artisan dusk tests/Browser/Buttons/ --env=testing
php artisan test tests/Feature/SecurityFeaturesSimpleTest.php --env=testing
```

## Test Structure

### Feature Tests
- `ButtonAuthenticationTest`: Authentication flows
- `ButtonAuthorizationTest`: Role-based access control
- `ButtonCRUDTest`: CRUD operations
- `ButtonBulkOperationsTest`: Bulk actions
- `ButtonSecurityTest`: Security validations
- `ButtonErrorHandlingTest`: Error states

### Browser Tests
- `ButtonNavigationTest`: Navigation flows
- `ButtonFormSubmissionTest`: Form interactions
- `ButtonModalTest`: Modal interactions
- `ButtonDropdownTest`: Dropdown menus
- `ButtonAlpineActionsTest`: Alpine.js actions

## Test Data

### Roles
- **super_admin**: System administrator with full access
- **admin**: Tenant administrator with tenant-wide access
- **pm**: Project Manager with project-level access
- **designer**: Design Lead with design-related access
- **engineer**: Site Engineer with construction access
- **guest**: Limited read-only access

### Test Data Setup
- 2 test tenants for isolation testing
- 3 users per role per tenant
- 5 projects per tenant
- 10 tasks per project
- 5 documents per project

## Coverage Matrix

| View Category | Coverage | Status |
|---------------|----------|--------|
| Dashboard Views | 95% | ✅ |
| CRUD Views | 90% | ✅ |
| Admin Views | 100% | ✅ |
| Team Views | 85% | ✅ |
| Template Views | 80% | ✅ |

## Quality Gates

### Must Pass
- [ ] No orphaned buttons (buttons without routes/policies)
- [ ] All authentication flows working
- [ ] All authorization policies enforced
- [ ] All CRUD operations functional
- [ ] All error states handled gracefully

### Should Pass
- [ ] 95%+ button coverage
- [ ] All navigation flows working
- [ ] All form validations working
- [ ] All bulk operations functional

## CI/CD Integration

### GitHub Actions
The test suite includes GitHub Actions workflow (`.github/workflows/button-tests.yml`) that:
- Generates button inventory
- Runs feature tests
- Runs browser tests
- Runs security tests
- Generates coverage reports
- Enforces quality gates

### Pre-commit Hooks
```bash
# Install pre-commit hook
cp .git/hooks/pre-commit.example .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

## Reports

### Generated Reports
- `docs/testing/button-inventory.csv`: Complete button inventory
- `docs/testing/button-test-plan.md`: Test plan documentation
- `docs/testing/button-coverage-matrix.md`: Coverage matrix
- `docs/testing/button-gaps.md`: Gap analysis
- `storage/test-reports/coverage-report.md`: Coverage report
- `storage/test-reports/test-summary.md`: Test summary

### Coverage Metrics
- **Total Buttons**: 306
- **Covered Buttons**: 285 (93.1%)
- **Not Covered**: 21 (6.9%)
- **N/A Combinations**: 45

## Troubleshooting

### Common Issues

#### Orphaned Buttons
```bash
# Check for orphaned buttons
grep ',,.*button' docs/testing/button-inventory.csv
```

#### Test Failures
```bash
# Run specific test with verbose output
php artisan test tests/Feature/Buttons/ButtonAuthenticationTest.php --env=testing -v
```

#### Database Issues
```bash
# Reset test database
php artisan migrate:fresh --env=testing
php artisan db:seed --env=testing
```

### Debug Mode
```bash
# Enable debug mode
export APP_DEBUG=true
export LOG_LEVEL=debug
```

## Contributing

### Adding New Tests
1. Create test file in appropriate directory
2. Follow naming convention: `Button[Category]Test.php`
3. Include proper setup and teardown
4. Add to coverage matrix
5. Update documentation

### Test Guidelines
- Use descriptive test names
- Include proper assertions
- Test both success and failure cases
- Include edge cases
- Document any special requirements

## Maintenance

### Regular Updates
- **Weekly**: Review new buttons added
- **Monthly**: Update test data
- **Quarterly**: Review test coverage
- **Annually**: Full test plan review

### Change Management
- **New Features**: Add corresponding tests
- **Bug Fixes**: Add regression tests
- **Security Updates**: Update security tests
- **Performance Changes**: Update performance tests

## Support

### Documentation
- [Laravel Testing](https://laravel.com/docs/testing)
- [PHPUnit](https://phpunit.de/)
- [Laravel Dusk](https://laravel.com/docs/dusk)

### Issues
Report issues in the project repository with:
- Test file name
- Error message
- Steps to reproduce
- Expected vs actual behavior

---

*This Button Test Suite ensures comprehensive coverage of all interactive elements in the ZenaManage application, providing confidence in the system's functionality, security, and user experience.*
