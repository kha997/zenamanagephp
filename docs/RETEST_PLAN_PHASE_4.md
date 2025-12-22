# Retest Plan for Phase 4 Issues

## 🎯 Retest Strategy Overview

### Retest Triggers
- **Team reports "Ready for QA"** on specific tickets
- **Weekly regression runs** show improvements
- **Manual retest requests** from stakeholders

### Retest Process
1. **Verify Fix**: Check if issue is resolved
2. **Run Tests**: Execute specific test commands
3. **Collect Evidence**: Screenshots, logs, videos
4. **Update Ticket**: Mark status and attach artifacts
5. **Update Documentation**: Update progress tracking

## 🔧 Security Issues Retest Plan

### AUTH-SECURITY-001: Brute Force Protection
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="brute force"`
**Expected Result**: Error messages displayed after failed login attempts
**Success Criteria**: 
- [ ] Login form shows error after 3 failed attempts
- [ ] Account locked after 5 failed attempts
- [ ] Lockout message displayed
- [ ] Account unlocks after timeout period

### AUTH-SECURITY-002: Session Expiry
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="session expiry"`
**Expected Result**: Sessions expire after configured timeout
**Success Criteria**:
- [ ] Session expires after timeout period
- [ ] User redirected to login page
- [ ] Session data cleared
- [ ] API returns 401 for expired sessions

### AUTH-SECURITY-003: Password Reset Flow
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="password reset"`
**Expected Result**: Password reset functionality working
**Success Criteria**:
- [ ] "Forgot Password" link visible
- [ ] Password reset email sent
- [ ] Reset token validation working
- [ ] New password can be set

### AUTH-SECURITY-004: Multi-Device Session Management
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="multi-device"`
**Expected Result**: Session management across devices
**Success Criteria**:
- [ ] Active sessions displayed
- [ ] Sessions can be terminated
- [ ] New device login notifications
- [ ] Session limits enforced

### AUTH-SECURITY-005: CSRF Protection
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="CSRF"`
**Expected Result**: CSRF protection working
**Success Criteria**:
- [ ] CSRF tokens in forms
- [ ] API requests include CSRF headers
- [ ] Invalid CSRF tokens rejected
- [ ] CSRF errors handled gracefully

### AUTH-SECURITY-006: Input Validation
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="input validation"`
**Expected Result**: Input validation working
**Success Criteria**:
- [ ] Malicious input rejected
- [ ] Error messages displayed
- [ ] XSS protection working
- [ ] SQL injection protection

## 🔐 RBAC Issues Retest Plan

### RBAC-ISSUE-001: Test Data Structure
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="test data"`
**Expected Result**: All user roles have proper test data
**Success Criteria**:
- [ ] Developer user data available
- [ ] Client user data available
- [ ] Guest user data available
- [ ] All roles properly seeded

### RBAC-ISSUE-002: Strict Mode Violations
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="strict mode"`
**Expected Result**: Test locators resolve to single elements
**Success Criteria**:
- [ ] No strict mode violations
- [ ] Locators resolve to single elements
- [ ] Tests run without errors
- [ ] Screenshots captured correctly

### RBAC-ISSUE-003: API Endpoints Return JSON
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="API endpoints"`
**Expected Result**: API endpoints return proper JSON
**Success Criteria**:
- [ ] API responses are JSON format
- [ ] No HTML returned from API
- [ ] Proper error responses
- [ ] Content-Type headers correct

### RBAC-ISSUE-004: Missing API Endpoints
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="missing endpoints"`
**Expected Result**: All required API endpoints available
**Success Criteria**:
- [ ] Admin Tenants endpoint working
- [ ] Admin Dashboard endpoint working
- [ ] All endpoints return 200/403 appropriately
- [ ] No 404 errors for required endpoints

### RBAC-ISSUE-005: Permission Restrictions
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="permission restrictions"`
**Expected Result**: Non-admin roles cannot access admin functions
**Success Criteria**:
- [ ] Non-admin users blocked from admin areas
- [ ] Admin buttons hidden for non-admin users
- [ ] API returns 403 for unauthorized access
- [ ] UI elements properly restricted

### RBAC-ISSUE-006: Cross-Tenant Access
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="cross-tenant"`
**Expected Result**: Users cannot access other tenant resources
**Success Criteria**:
- [ ] Tenant isolation working
- [ ] Users only see own tenant data
- [ ] Cross-tenant requests blocked
- [ ] Data properly filtered by tenant_id

## 📊 Feature Gaps Retest Plan

### CSV Import/Export Issues

#### CSV-IMPORT-EXPORT-001: Export Functionality
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/csv/csv-import-export.spec.ts --grep="export"`
**Expected Result**: CSV export functionality working
**Success Criteria**:
- [ ] Export button visible on admin users page
- [ ] CSV file generated with correct data
- [ ] Headers match expected format
- [ ] Data exported correctly

#### CSV-IMPORT-EXPORT-002: Import Functionality
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/csv/csv-import-export.spec.ts --grep="import"`
**Expected Result**: CSV import functionality working
**Success Criteria**:
- [ ] Import button visible on admin users page
- [ ] File upload working
- [ ] Data validation working
- [ ] Import progress tracking

### Queue & Background Jobs Issues

#### QUEUE-MONITORING-001: Queue Metrics
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/queue/offline-queue.spec.ts --grep="monitoring"`
**Expected Result**: Queue monitoring dashboard functional
**Success Criteria**:
- [ ] Queue metrics displayed
- [ ] Job counts visible
- [ ] Processing times shown
- [ ] Failed job tracking

#### QUEUE-RETRY-001: Automatic Retry
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/queue/offline-queue.spec.ts --grep="retry"`
**Expected Result**: Automatic retry mechanism working
**Success Criteria**:
- [ ] Failed jobs automatically retried
- [ ] Exponential backoff working
- [ ] Retry count displayed
- [ ] Success after retry

#### QUEUE-LIMITS-001: Retry Limits
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/queue/offline-queue.spec.ts --grep="limits"`
**Expected Result**: Retry limits enforced
**Success Criteria**:
- [ ] Max retry attempts enforced
- [ ] Dead letter queue working
- [ ] Retry limit messages displayed
- [ ] Failed job handling

#### PERFORMANCE-MONITORING-001: Performance Metrics
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/queue/offline-queue.spec.ts --grep="performance"`
**Expected Result**: Performance monitoring working
**Success Criteria**:
- [ ] Performance metrics collected
- [ ] APM integration working
- [ ] Performance alerts configured
- [ ] Metrics dashboard functional

#### BACKGROUND-JOBS-001: Background Job Processing
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/queue/offline-queue.spec.ts --grep="background"`
**Expected Result**: Background job processing working
**Success Criteria**:
- [ ] Horizon dashboard functional
- [ ] Queue workers running
- [ ] Job processing successful
- [ ] Background tasks completed

### Internationalization Issues

#### I18N-LANGUAGE-001: Language Switching
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/i18n/ --grep="language switching"`
**Expected Result**: Language switching functionality working
**Success Criteria**:
- [ ] Language selector visible
- [ ] Language switching working
- [ ] UI text changes language
- [ ] Language preference persisted

#### I18N-TIMEZONE-001: Timezone Switching
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/i18n/ --grep="timezone"`
**Expected Result**: Timezone switching functionality working
**Success Criteria**:
- [ ] Timezone selector visible
- [ ] Timezone switching working
- [ ] Date/time display changes
- [ ] Timezone preference persisted

#### I18N-TRANSLATION-001: Translations
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/i18n/ --grep="translation"`
**Expected Result**: Vietnamese and other language translations available
**Success Criteria**:
- [ ] Vietnamese translations available
- [ ] All UI text translated
- [ ] Error messages translated
- [ ] Notifications translated

#### I18N-FORMATTING-001: Locale-Specific Formatting
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/i18n/ --grep="formatting"`
**Expected Result**: Locale-specific formatting working
**Success Criteria**:
- [ ] Date formatting localized
- [ ] Number formatting localized
- [ ] Currency formatting localized
- [ ] Input field formatting localized

### Performance Issues

#### PERF-LOAD-001: Performance Indicators
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="performance indicators"`
**Expected Result**: Performance indicators displayed in UI
**Success Criteria**:
- [ ] Performance indicators visible
- [ ] Load time displayed
- [ ] Performance metrics shown
- [ ] Performance warnings displayed

#### PERF-LOAD-002: Loading Time Display
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="loading time"`
**Expected Result**: Loading time displayed in UI
**Success Criteria**:
- [ ] Loading time visible
- [ ] Page load metrics shown
- [ ] Performance timing displayed
- [ ] Load time within acceptable limits

#### PERF-API-001: API Timing Display
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="API timing"`
**Expected Result**: API timing displayed in UI
**Success Criteria**:
- [ ] API timing visible
- [ ] Response time displayed
- [ ] API performance metrics shown
- [ ] API timing within acceptable limits

#### PERF-API-002: Refresh/Action Buttons
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="refresh buttons"`
**Expected Result**: Refresh/action buttons available
**Success Criteria**:
- [ ] Refresh button visible
- [ ] Action buttons functional
- [ ] Button interactions working
- [ ] UI feedback provided

#### PERF-API-003: Pagination Buttons
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="pagination"`
**Expected Result**: Pagination buttons available
**Success Criteria**:
- [ ] Pagination buttons visible
- [ ] Pagination working
- [ ] Page navigation functional
- [ ] Pagination performance acceptable

#### PERF-API-004: Bulk Operation Buttons
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="bulk operations"`
**Expected Result**: Bulk operation buttons available
**Success Criteria**:
- [ ] Bulk operation buttons visible
- [ ] Bulk operations functional
- [ ] Bulk operation performance acceptable
- [ ] Bulk operation feedback provided

#### PERF-MONITOR-001: Memory Usage Monitoring
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="memory usage"`
**Expected Result**: Memory usage monitoring working
**Success Criteria**:
- [ ] Memory usage indicators visible
- [ ] Memory metrics displayed
- [ ] Memory warnings shown
- [ ] Memory usage within limits

#### PERF-MONITOR-002: Network Performance Monitoring
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="network performance"`
**Expected Result**: Network performance monitoring working
**Success Criteria**:
- [ ] Network performance indicators visible
- [ ] Network metrics displayed
- [ ] Network warnings shown
- [ ] Network performance acceptable

#### PERF-MONITOR-003: Performance Thresholds
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="thresholds"`
**Expected Result**: Performance thresholds displayed
**Success Criteria**:
- [ ] Performance thresholds visible
- [ ] Threshold warnings displayed
- [ ] Performance alerts shown
- [ ] Thresholds properly configured

#### PERF-MONITOR-004: Performance Recommendations
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="recommendations"`
**Expected Result**: Performance recommendations displayed
**Success Criteria**:
- [ ] Performance recommendations visible
- [ ] Optimization suggestions shown
- [ ] Performance tips displayed
- [ ] Recommendations actionable

#### PERF-RETRY-001: Retry UI Feedback
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="retry feedback"`
**Expected Result**: Retry UI feedback working
**Success Criteria**:
- [ ] Retry feedback visible
- [ ] Retry status displayed
- [ ] Retry progress shown
- [ ] Retry success/failure feedback

#### PERF-RETRY-002: Retry Limit Handling
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="retry limits"`
**Expected Result**: Retry limit handling working
**Success Criteria**:
- [ ] Retry limits enforced
- [ ] Retry limit messages displayed
- [ ] Retry limit handling functional
- [ ] Retry limit recovery working

#### PERF-RETRY-003: Exponential Backoff Indicators
**Test Command**: `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="exponential backoff"`
**Expected Result**: Exponential backoff indicators working
**Success Criteria**:
- [ ] Exponential backoff indicators visible
- [ ] Backoff timing displayed
- [ ] Retry intervals shown
- [ ] Exponential backoff working correctly

## 📋 Retest Execution Process

### 1. Pre-Retest Checklist
- [ ] Issue marked as "Ready for QA" by development team
- [ ] Fix description provided
- [ ] Test environment prepared
- [ ] Test data available

### 2. Retest Execution
- [ ] Run specific test command
- [ ] Capture screenshots
- [ ] Record test results
- [ ] Document any issues found

### 3. Post-Retest Actions
- [ ] Update ticket status
- [ ] Attach test artifacts
- [ ] Update progress tracking
- [ ] Notify stakeholders

### 4. Success Criteria
- [ ] All test criteria met
- [ ] No regression issues
- [ ] Performance within limits
- [ ] Documentation updated

---

**Last Updated**: 2025-01-15  
**Next Review**: Weekly during retest phase  
**Status**: Ready for retest execution
