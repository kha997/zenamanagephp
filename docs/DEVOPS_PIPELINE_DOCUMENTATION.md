# DevOps Pipeline Documentation

**Date**: 2025-01-18  
**Version**: 1.0  
**Status**: Active  

---

## 📋 Overview

This document describes the CI/CD pipeline for ZenaManage, including automated testing, deployment, and monitoring workflows. The pipeline is designed to ensure code quality, system stability, and production readiness.

---

## 🚀 Phase 5 CI Integration Improvements

### **Completed Enhancements (2025-01-15)**

#### **1. Unified CI Pipeline**
- **PHP Unit Tests**: Added to core workflow as prerequisite job
- **Dependency Chain**: Playwright tests now depend on PHP unit test success
- **Coverage Integration**: Codecov reporting for PHP tests

#### **2. Enhanced Triggers**
- **Develop Branch**: Regression tests now run on develop branch
- **Manual Dispatch**: Added workflow_dispatch to both core and regression workflows
- **Consistent Scheduling**: Daily core tests (2 AM UTC), nightly regression tests (2 AM UTC)

#### **3. Performance Optimizations**
- **Composer Caching**: Added vendor directory caching to reduce build time
- **NPM Caching**: Enhanced Node.js dependency caching
- **Parallel Execution**: Optimized job dependencies for faster execution

#### **4. CI Pipeline Matrix**
```
Push to main → PHP Unit Tests → Playwright Core + Playwright Regression (parallel)
Push to develop → PHP Unit Tests → Playwright Core
PR → PHP Unit Tests → Playwright Core
Schedule → PHP Unit Tests → Playwright Core (daily) + PHP Unit Tests → Playwright Regression (nightly)
Manual → All suites available via workflow_dispatch
```

---

## 🔄 Pipeline Architecture

### Pipeline Stages
1. **Code Quality**: Linting, formatting, and static analysis
2. **Unit Testing**: PHPUnit tests for backend components
3. **Integration Testing**: API and database integration tests
4. **E2E Testing**: Playwright tests for user workflows
5. **Security Testing**: Vulnerability scanning and security tests
6. **Performance Testing**: Load and performance validation
7. **Deployment**: Automated deployment to staging and production
8. **Monitoring**: Health checks and performance monitoring

### Pipeline Triggers
- **Push to main**: Full pipeline execution (PHP unit tests → Playwright core + regression in parallel)
- **Push to develop**: Core tests and smoke tests (PHP unit tests → Playwright core)
- **Pull Request**: Core tests and code quality checks (PHP unit tests → Playwright core)
- **Scheduled**: Daily core tests (2 AM UTC), nightly regression tests (2 AM UTC)
- **Manual**: On-demand execution for specific test suites via workflow_dispatch

---

## 🧪 Testing Pipeline

### 1. PHP Unit Tests
**Workflow**: `.github/workflows/playwright-core.yml` (php-unit-tests job)  
**Trigger**: Push to main/develop, PR, daily schedule  
**Duration**: ~30 minutes  
**Dependencies**: None (runs first)

#### Test Suites:
- **Unit Tests**: `--testsuite=Unit` for isolated component testing
- **Feature Tests**: `--testsuite=Feature` for integration testing
- **Coverage**: Xdebug coverage reporting with Codecov integration

### 2. Playwright Core Tests
**Workflow**: `.github/workflows/playwright-core.yml`  
**Trigger**: Push to main/develop, PR, daily schedule  
**Duration**: ~60 minutes  
**Dependencies**: php-unit-tests (must pass first)

#### Test Suites:
- **Core Tests**: `@core` tagged tests for core CRUD operations
- **Smoke Tests**: `@smoke` tagged tests for basic functionality
- **Mobile Tests**: `@mobile` tagged tests for mobile responsiveness

#### Test Execution:
```yaml
# Core tests
npx playwright test --project=core-chromium --grep "@core"

# Smoke tests
npx playwright test --project=smoke-chromium --grep "@smoke"

# Mobile tests
npx playwright test --project=mobile-chromium --grep "@mobile"
```

#### Artifacts:
- Playwright HTML reports
- Test screenshots and videos
- Test results and logs
- Performance metrics

### 3. Playwright Regression Tests
**Workflow**: `.github/workflows/playwright-regression.yml`  
**Trigger**: Push to main/develop, PR to main/develop, nightly schedule (2 AM UTC), manual dispatch  
**Duration**: ~120 minutes  

#### Test Suites:
- **Regression Tests**: `@regression` tagged tests for comprehensive testing
- **Security Tests**: `@security` tagged tests for security validation
- **Performance Tests**: `@performance` tagged tests for performance validation
- **Cross-browser Tests**: `@cross-browser` tagged tests for browser compatibility

#### Test Execution:
```yaml
# Regression tests
npx playwright test --project=regression-chromium --grep "@regression"

# Security tests
npx playwright test --project=security-chromium --grep "@security"

# Performance tests
npx playwright test --project=performance-chromium --grep "@performance"

# Cross-browser tests
npx playwright test --project=chromium --grep "@cross-browser"
npx playwright test --project=firefox --grep "@cross-browser"
npx playwright test --project=webkit --grep "@cross-browser"
```

#### Artifacts:
- Comprehensive test reports
- Security scan results
- Performance benchmarks
- Cross-browser compatibility reports

---

## 🏗️ Build Pipeline

### 1. PHP Backend Build
**Steps**:
1. **Dependency Installation**: `composer install --no-progress --prefer-dist --optimize-autoloader`
2. **Environment Setup**: Copy `.env.example` to `.env`
3. **Key Generation**: `php artisan key:generate`
4. **Database Setup**: Create test database and run migrations
5. **Data Seeding**: Seed database with test data
6. **Server Start**: Start Laravel development server

### 2. Node.js Frontend Build
**Steps**:
1. **Dependency Installation**: `npm ci`
2. **Playwright Installation**: `npx playwright install --with-deps`
3. **Test Execution**: Run Playwright tests
4. **Report Generation**: Generate HTML test reports

### 3. Database Setup
**Steps**:
1. **Database Creation**: Create test database
2. **Migration Execution**: Run database migrations
3. **Data Seeding**: Seed with appropriate test data
4. **Data Validation**: Verify seeded data integrity

---

## 📊 Test Data Management

### 1. Core Test Data
**Seeder**: `E2EDatabaseSeeder`  
**Purpose**: Basic functionality testing  
**Data Volume**: 
- 2 tenants (ZENA, TTF)
- 10+ users per tenant
- 20+ projects per tenant
- 50+ tasks per tenant
- 20+ documents per tenant

### 2. Regression Test Data
**Seeder**: `Phase4E2EDatabaseSeeder`  
**Purpose**: Comprehensive testing  
**Data Volume**:
- Extended user roles and permissions
- Large datasets for performance testing
- Multi-language content
- Timezone-aware data
- Complex data relationships

### 3. Security Test Data
**Seeder**: `SecurityTestDatabaseSeeder`  
**Purpose**: Security testing  
**Data Volume**:
- Malicious content for injection testing
- Invalid data for validation testing
- Edge cases and boundary values
- Permission combinations for RBAC testing

### 4. Performance Test Data
**Seeder**: `PerformanceTestDatabaseSeeder`  
**Purpose**: Performance testing  
**Data Volume**:
- Large files for upload testing
- Complex queries for database testing
- Bulk operations data
- Concurrent user simulation data

---

## 🔒 Security Pipeline

### 1. Vulnerability Scanning
**Tools**: 
- PHP security scanners
- Node.js dependency vulnerability checks
- Database security validation
- API endpoint security testing

### 2. Authentication Testing
**Tests**:
- Brute-force protection
- Session management
- Password security
- Multi-factor authentication
- Account lockout mechanisms

### 3. Authorization Testing
**Tests**:
- Role-based access control
- Permission inheritance
- Cross-tenant isolation
- API endpoint authorization
- UI element visibility

### 4. Data Security Testing
**Tests**:
- SQL injection prevention
- XSS protection
- CSRF protection
- Data encryption
- Secure data transmission

---

## ⚡ Performance Pipeline

### 1. Load Testing
**Metrics**:
- Page load times (target: <500ms p95)
- API response times (target: <300ms p95)
- Database query performance
- Memory usage and leaks
- CPU utilization

### 2. Stress Testing
**Scenarios**:
- Concurrent user simulation
- Large dataset operations
- Bulk operation performance
- File upload/download performance
- Real-time feature performance

### 3. Performance Monitoring
**Tools**:
- Playwright performance metrics
- Laravel performance monitoring
- Database query analysis
- Memory usage tracking
- Error rate monitoring

---

## 🚀 Deployment Pipeline

### 1. Staging Deployment
**Trigger**: Push to develop branch  
**Steps**:
1. **Build**: Compile and optimize code
2. **Test**: Run core and smoke tests
3. **Deploy**: Deploy to staging environment
4. **Verify**: Run health checks and basic tests
5. **Notify**: Send deployment notifications

### 2. Production Deployment
**Trigger**: Push to main branch  
**Steps**:
1. **Build**: Compile and optimize code
2. **Test**: Run full test suite
3. **Security**: Run security scans
4. **Deploy**: Deploy to production environment
5. **Verify**: Run comprehensive health checks
6. **Monitor**: Start performance monitoring
7. **Notify**: Send deployment notifications

### 3. Rollback Strategy
**Triggers**:
- Health check failures
- Performance degradation
- Security vulnerabilities
- User-reported issues

**Steps**:
1. **Detect**: Automated monitoring detects issues
2. **Alert**: Send alerts to development team
3. **Rollback**: Automatically rollback to previous version
4. **Verify**: Verify rollback success
5. **Investigate**: Investigate root cause
6. **Fix**: Implement fixes and redeploy

---

## 📈 Monitoring & Observability

### 1. Health Checks
**Endpoints**:
- `/health`: Basic application health
- `/health/database`: Database connectivity
- `/health/redis`: Cache connectivity
- `/health/queue`: Queue system health
- `/health/storage`: File storage health

### 2. Performance Monitoring
**Metrics**:
- Response times and throughput
- Error rates and success rates
- Resource utilization (CPU, memory, disk)
- Database performance metrics
- Queue processing metrics

### 3. Error Tracking
**Tools**:
- Laravel error logging
- Playwright test failure tracking
- Performance degradation alerts
- Security incident monitoring
- User experience metrics

### 4. Alerting
**Channels**:
- Email notifications
- Slack integration
- PagerDuty integration
- SMS alerts for critical issues
- Dashboard notifications

---

## 🔧 Pipeline Configuration

### 1. Environment Variables
**Required**:
- `DB_CONNECTION`: Database connection type
- `DB_HOST`: Database host
- `DB_PORT`: Database port
- `DB_DATABASE`: Database name
- `DB_USERNAME`: Database username
- `DB_PASSWORD`: Database password
- `APP_KEY`: Application encryption key
- `APP_ENV`: Application environment

**Optional**:
- `REDIS_HOST`: Redis host for caching
- `QUEUE_CONNECTION`: Queue connection type
- `MAIL_MAILER`: Email service configuration
- `LOG_CHANNEL`: Logging configuration

### 2. Test Configuration
**Playwright Config**: `playwright.config.ts`
**PHPUnit Config**: `phpunit.xml`
**Laravel Config**: `config/testing.php`

### 3. Database Configuration
**Test Database**: `zenamanage_test`
**Migrations**: `database/migrations/`
**Seeders**: `database/seeders/`
**Factories**: `database/factories/`

---

## 📋 Pipeline Maintenance

### 1. Regular Updates
**Schedule**: Monthly  
**Tasks**:
- Update dependencies and packages
- Review and update test cases
- Optimize pipeline performance
- Update documentation
- Review security configurations

### 2. Performance Optimization
**Monitoring**:
- Pipeline execution times
- Resource utilization
- Test failure rates
- Deployment success rates
- User experience metrics

### 3. Security Updates
**Schedule**: As needed  
**Tasks**:
- Update security dependencies
- Review and update security tests
- Update vulnerability scanners
- Review access controls
- Update security documentation

---

## 🚨 Troubleshooting

### 1. Common Issues
**Database Connection**:
- Check database credentials
- Verify database server status
- Check network connectivity
- Review migration status

**Test Failures**:
- Review test logs and screenshots
- Check test data integrity
- Verify application state
- Review environment configuration

**Performance Issues**:
- Check resource utilization
- Review database query performance
- Analyze test execution times
- Review application performance

### 2. Debugging Steps
1. **Check Logs**: Review application and test logs
2. **Verify Environment**: Check environment configuration
3. **Test Locally**: Reproduce issues locally
4. **Review Changes**: Check recent code changes
5. **Escalate**: Escalate to development team if needed

### 3. Recovery Procedures
**Pipeline Failure**:
1. **Identify**: Identify the failing component
2. **Isolate**: Isolate the issue
3. **Fix**: Implement fix
4. **Test**: Verify fix works
5. **Deploy**: Deploy fix to pipeline

**Deployment Failure**:
1. **Rollback**: Rollback to previous version
2. **Investigate**: Investigate root cause
3. **Fix**: Implement fix
4. **Test**: Test fix thoroughly
5. **Redeploy**: Deploy fixed version

---

## 📚 Resources

### 1. Documentation
- [Playwright Documentation](https://playwright.dev/)
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)

### 2. Tools
- **Playwright**: E2E testing framework
- **PHPUnit**: PHP unit testing framework
- **GitHub Actions**: CI/CD platform
- **Laravel**: PHP web framework
- **MySQL**: Database management system

### 3. Support
- **Development Team**: Internal support
- **DevOps Team**: Pipeline support
- **QA Team**: Testing support
- **Security Team**: Security support

---

## Phase 4 Regression Testing Status

### ✅ Completed Test Suites (7/7)
- ✅ **E2E-REGRESSION-010**: Authentication Security Testing (9 tests, 3 passed, 6 failed) - 6 issues identified
- ✅ **E2E-REGRESSION-020**: Documents Conflict Testing (5 tests, 5 passed, 0 failed) ✅ **FIXED**
- ✅ **E2E-REGRESSION-030**: CSV Import/Export Testing (7 tests, 7 passed, 0 failed) ✅ **ALL PASSED**
- ✅ **E2E-REGRESSION-040**: Offline Queue Testing (6 tests, 6 passed, 0 failed) ✅ **ALL PASSED**
- ✅ **E2E-REGRESSION-050**: RBAC Comprehensive Testing (15 tests, 9 passed, 6 failed) - 6 critical issues identified
- ✅ **E2E-REGRESSION-060**: Internationalization Testing (20 tests, 20 passed, 0 failed) ✅ **ALL PASSED**
- ✅ **E2E-REGRESSION-070**: Performance & Load Testing (18 tests, 18 passed, 0 failed) ✅ **ALL PASSED**

### 📊 Total Issues Identified: 38
- **Security Issues**: 12 (AUTH-SECURITY-001 to 006, RBAC-ISSUE-001 to 006)
- **Feature Gaps**: 26 (CSV, Queue, i18n, Performance functionality)

### 🔧 CI Pipeline Status
- **playwright-core.yml**: ✅ Active (daily schedule)
- **playwright-regression.yml**: ✅ **ACTIVE** (nightly schedule + manual dispatch)
- **Regression Workflow**: ✅ **ACTIVATED** from 2025-01-15

### 🎯 Regression Workflow Configuration
- **Schedule**: Nightly at 2 AM UTC
- **Manual Dispatch**: Available with test suite selection
- **Test Suites**: regression, security, performance, cross-browser
- **Environments**: testing, staging
- **Artifacts**: playwright-report, test-results (30-day retention)

### 📋 Issue Breakdown by Team
- **Backend Lead**: 12 issues (Security + Queue + Performance)
- **Frontend Lead**: 15 issues (CSV + i18n + Performance UI)
- **DevOps Lead**: 4 issues (Queue monitoring + Performance monitoring)
- **QA Lead**: 2 issues (RBAC test data fixes)

### 🚀 Next Steps
1. **Issue Resolution**: Address 38 identified issues with team assignments
2. **CI Monitoring**: Track nightly regression runs and performance
3. **Phase 5**: CI Integration and automation completion
4. **Phase 6**: Handoff cards preparation
5. **Phase 7**: UAT and production readiness

---

**Last Updated**: 2025-01-18  
**Next Review**: Monthly  
**Maintainer**: DevOps Team