# ZenaManage DevOps Pipeline Documentation

## Overview

ZenaManage sử dụng một CI/CD pipeline toàn diện với GitHub Actions để đảm bảo chất lượng code, bảo mật và deployment tự động.

## Pipeline Components

### 1. Security Audit (Daily)
- **Trigger**: Cron job hàng ngày lúc 2:00 AM UTC
- **Purpose**: Kiểm tra bảo mật tự động
- **Actions**:
  - Chạy `npm audit` và `composer audit`
  - Tạo security report JSON
  - Gửi alert nếu có vulnerability critical
  - Upload artifact với security report

### 2. Code Quality & Security
- **Trigger**: Push/PR vào main/develop branches
- **Purpose**: Kiểm tra chất lượng code và bảo mật
- **Actions**:
  - NPM audit với audit-level=moderate
  - Composer audit
  - PHP CS Fixer (code style)
  - PHPStan (static analysis)

### 3. Backend Tests
- **Trigger**: Push/PR vào main/develop branches
- **Purpose**: Chạy test suite backend
- **Services**: MySQL 8.0
- **Actions**:
  - Setup PHP 8.2 với extensions cần thiết
  - Install dependencies
  - Run migrations và seeders
  - Run Feature tests với memory_limit=512M
  - Run Unit tests
  - Upload test results

### 4. Frontend Build & Tests
- **Trigger**: Push/PR vào main/develop branches
- **Purpose**: Build và test frontend
- **Actions**:
  - Setup Node.js 18
  - Install dependencies
  - Run ESLint
  - Run Prettier check
  - Build assets với Vite
  - Upload build artifacts

### 5. E2E Tests
- **Trigger**: Push/PR vào main/develop branches
- **Purpose**: End-to-end testing với Playwright
- **Services**: MySQL 8.0
- **Actions**:
  - Setup PHP và Node.js
  - Install Playwright browsers
  - Setup database
  - Build assets
  - Start Laravel server
  - Run E2E tests
  - Upload test results

### 6. Performance Tests
- **Trigger**: Push/PR vào main/develop branches
- **Purpose**: Kiểm tra performance
- **Services**: MySQL 8.0
- **Actions**:
  - Run performance test suite
  - Upload performance results

### 7. Deploy Production
- **Trigger**: Push vào main branch
- **Purpose**: Deploy tự động lên production
- **Requirements**: Tất cả tests phải pass
- **Actions**:
  - Install dependencies
  - Build assets
  - Run production deployment script
  - Run health checks
  - Send Slack notification

### 8. Rollback
- **Trigger**: Khi deployment fails
- **Purpose**: Rollback tự động về version trước
- **Actions**:
  - Rollback code và database
  - Send notification

## Environment Variables

### Required Secrets
```yaml
SLACK_SECURITY_WEBHOOK: # Webhook cho security alerts
SLACK_DEPLOYMENT_WEBHOOK: # Webhook cho deployment notifications
```

### Database Configuration
```yaml
DB_USERNAME: root
DB_PASSWORD: password
DB_HOST: localhost
DB_DATABASE: zenamanage_test
```

## Deployment Scripts

### deploy-production.sh
Script deployment production với các tính năng:
- Pre-deployment checks
- Backup tự động
- Install dependencies
- Build assets
- Laravel optimization
- Database migrations
- File permissions
- Health checks
- Cleanup

### rollback-production.sh
Script rollback với các tính năng:
- Pre-rollback checks
- Emergency backup
- Restore files và database
- Reinstall dependencies
- Rebuild assets
- Health checks
- Service restart

## Security Features

### Automated Security Audit
- **Script**: `scripts/security-audit.sh`
- **Frequency**: Daily via cron
- **Checks**:
  - NPM vulnerabilities
  - Composer vulnerabilities
  - Outdated packages
  - Known security issues
- **Reporting**: JSON reports với recommendations
- **Alerts**: Email + Slack cho critical issues

### Dependabot Integration
- **Config**: `.github/dependabot.yml`
- **Frequency**: Weekly updates
- **Packages**: npm, composer, GitHub Actions
- **Features**:
  - Security updates priority
  - Automatic PR creation
  - Grouped updates
  - Custom labels và reviewers

## Monitoring & Alerting

### Slack Integration
- **Security Alerts**: Critical vulnerabilities
- **Deployment Notifications**: Success/failure
- **Rollback Alerts**: Emergency rollbacks

### Health Checks
- **Application**: HTTP status check
- **Database**: Connection verification
- **Performance**: Load average, memory usage
- **Disk Space**: Usage monitoring

## Best Practices

### Code Quality
- All code phải pass PHP CS Fixer
- Static analysis với PHPStan level 5
- ESLint và Prettier cho frontend
- Test coverage requirements

### Security
- Dependencies audit hàng ngày
- Security headers middleware
- RBAC implementation
- Input validation
- SQL injection prevention

### Performance
- Memory limits cho tests (512M)
- Asset optimization
- Database query optimization
- Caching strategies

### Deployment
- Blue-green deployment ready
- Automated rollback
- Health checks
- Monitoring integration

## Troubleshooting

### Common Issues

#### Memory Exhaustion
```bash
# Increase memory limit
php -d memory_limit=512M artisan test
```

#### Database Connection Issues
```bash
# Check database service
docker-compose ps mysql
# Check connection
php artisan tinker --execute="DB::connection()->getPdo();"
```

#### Build Failures
```bash
# Clear npm cache
npm cache clean --force
# Reinstall dependencies
rm -rf node_modules package-lock.json
npm install
```

#### Test Failures
```bash
# Run specific test
php artisan test tests/Feature/Dashboard/DashboardApiTest.php
# Run with verbose output
php artisan test --verbose
```

### Logs Location
- **Laravel Logs**: `storage/logs/laravel.log`
- **Security Reports**: `storage/logs/security-report-*.json`
- **Test Results**: GitHub Actions artifacts
- **Deployment Logs**: Console output

## Maintenance

### Weekly Tasks
- Review security audit reports
- Update dependencies via Dependabot PRs
- Check performance metrics
- Review deployment logs

### Monthly Tasks
- Security review
- Performance optimization
- Dependency audit
- Backup verification

### Quarterly Tasks
- Infrastructure review
- Security penetration testing
- Performance benchmarking
- Disaster recovery testing

## Support

### Team Contacts
- **DevOps**: devops@zenamanage.com
- **Security**: security@zenamanage.com
- **Development**: dev@zenamanage.com

### Documentation
- **Pipeline Config**: `.github/workflows/ci-cd.yml`
- **Security Script**: `scripts/security-audit.sh`
- **Deployment**: `deploy-production.sh`
- **Rollback**: `rollback-production.sh`

### Monitoring
- **GitHub Actions**: Repository Actions tab
- **Security Reports**: `storage/logs/security-report-*.json`
- **Performance**: Grafana dashboard
- **Alerts**: Slack channels
