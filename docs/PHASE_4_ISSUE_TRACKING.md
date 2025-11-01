# Phase 4 Issue Tracking & Assignment

## 📊 Tổng quan Issues
- **Total Issues**: 38
- **Security-Critical**: 12 issues (AUTH-SECURITY-001 to 006, RBAC-ISSUE-001 to 006)
- **Feature Gaps**: 26 issues (CSV, Queue, i18n, Performance)

## 🚨 Security-Critical Issues (12) - Tag "security", "must fix trước UAT"

### Authentication Security (6 issues)
| Issue ID | Priority | Description | Owner | Due Date | Status | Test Command |
|----------|----------|-------------|-------|----------|--------|--------------|
| AUTH-SECURITY-001 | CRITICAL | Brute force protection not working | Backend Lead | 2025-01-20 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="brute force"` |
| AUTH-SECURITY-002 | HIGH | Session expiry not properly handled | Backend Lead | 2025-01-20 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="session expiry"` |
| AUTH-SECURITY-003 | MEDIUM | Password reset flow not implemented | Backend Lead | 2025-01-22 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="password reset"` |
| AUTH-SECURITY-004 | MEDIUM | Multi-device session management issues | Backend Lead | 2025-01-22 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="multi-device"` |
| AUTH-SECURITY-005 | LOW | CSRF protection test fails | Backend Lead | 2025-01-25 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="CSRF"` |
| AUTH-SECURITY-006 | HIGH | Input validation not working | Backend Lead | 2025-01-20 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/auth/auth-security.spec.ts --grep="input validation"` |

### RBAC Security (6 issues)
| Issue ID | Priority | Description | Owner | Due Date | Status | Test Command |
|----------|----------|-------------|-------|----------|--------|--------------|
| RBAC-ISSUE-001 | HIGH | Test data structure issues | QA Lead | 2025-01-18 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="test data"` |
| RBAC-ISSUE-002 | MEDIUM | Strict mode violations in test locators | QA Lead | 2025-01-18 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="strict mode"` |
| RBAC-ISSUE-003 | HIGH | API endpoints return HTML instead of JSON | Backend Lead | 2025-01-20 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="API endpoints"` |
| RBAC-ISSUE-004 | HIGH | Missing API endpoints | Backend Lead | 2025-01-20 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="missing endpoints"` |
| RBAC-ISSUE-005 | CRITICAL | Insufficient permission restrictions | Backend Lead | 2025-01-18 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="permission restrictions"` |
| RBAC-ISSUE-006 | CRITICAL | Cross-tenant resource access | Backend Lead | 2025-01-18 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/rbac/ --grep="cross-tenant"` |

## 🔧 Feature Gaps (26) - Nhóm theo domain

### CSV Import/Export (2 issues) → Front/Back Team
| Issue ID | Priority | Description | Owner | Due Date | Status | Test Command |
|----------|----------|-------------|-------|----------|--------|--------------|
| CSV-IMPORT-EXPORT-001 | HIGH | CSV export functionality not implemented | Frontend Lead | 2025-01-25 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/csv/csv-import-export.spec.ts --grep="export"` |
| CSV-IMPORT-EXPORT-002 | HIGH | CSV import functionality not implemented | Frontend Lead | 2025-01-25 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/csv/csv-import-export.spec.ts --grep="import"` |

### Queue & Background Jobs (5 issues) → Backend + DevOps
| Issue ID | Priority | Description | Owner | Due Date | Status | Test Command |
|----------|----------|-------------|-------|----------|--------|--------------|
| QUEUE-MONITORING-001 | HIGH | Queue metrics not implemented | DevOps Lead | 2025-01-22 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/queue/offline-queue.spec.ts --grep="monitoring"` |
| QUEUE-RETRY-001 | HIGH | Automatic retry mechanism not implemented | Backend Lead | 2025-01-22 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/queue/offline-queue.spec.ts --grep="retry"` |
| QUEUE-LIMITS-001 | MEDIUM | Retry limits not implemented | Backend Lead | 2025-01-25 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/queue/offline-queue.spec.ts --grep="limits"` |
| PERFORMANCE-MONITORING-001 | MEDIUM | Performance metrics not implemented | DevOps Lead | 2025-01-25 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/queue/offline-queue.spec.ts --grep="performance"` |
| BACKGROUND-JOBS-001 | MEDIUM | Background job processing not implemented | Backend Lead | 2025-01-25 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/queue/offline-queue.spec.ts --grep="background"` |

### Internationalization & Timezone (4 issues) → Frontend
| Issue ID | Priority | Description | Owner | Due Date | Status | Test Command |
|----------|----------|-------------|-------|----------|--------|--------------|
| I18N-LANGUAGE-001 | HIGH | Language switching not implemented | Frontend Lead | 2025-01-28 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/i18n/ --grep="language switching"` |
| I18N-TIMEZONE-001 | HIGH | Timezone switching not implemented | Frontend Lead | 2025-01-28 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/i18n/ --grep="timezone"` |
| I18N-TRANSLATION-001 | MEDIUM | Vietnamese and other language translations not implemented | Frontend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/i18n/ --grep="translation"` |
| I18N-FORMATTING-001 | MEDIUM | Locale-specific formatting not implemented | Frontend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/i18n/ --grep="formatting"` |

### Performance Suite (13 issues) → Product/UX + FE/BE
| Issue ID | Priority | Description | Owner | Due Date | Status | Test Command |
|----------|----------|-------------|-------|----------|--------|--------------|
| PERF-LOAD-001 | MEDIUM | Performance indicators not implemented | Frontend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="performance indicators"` |
| PERF-LOAD-002 | MEDIUM | Loading time display not implemented | Frontend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="loading time"` |
| PERF-API-001 | MEDIUM | API timing display not implemented | Frontend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="API timing"` |
| PERF-API-002 | MEDIUM | Refresh/action buttons not implemented | Frontend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="refresh buttons"` |
| PERF-API-003 | MEDIUM | Pagination buttons not implemented | Frontend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="pagination"` |
| PERF-API-004 | MEDIUM | Bulk operation buttons not implemented | Frontend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="bulk operations"` |
| PERF-MONITOR-001 | MEDIUM | Memory usage monitoring not implemented | DevOps Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="memory usage"` |
| PERF-MONITOR-002 | MEDIUM | Network performance monitoring not implemented | DevOps Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="network performance"` |
| PERF-MONITOR-003 | MEDIUM | Performance thresholds not implemented | DevOps Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="thresholds"` |
| PERF-MONITOR-004 | MEDIUM | Performance recommendations not implemented | DevOps Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="recommendations"` |
| PERF-RETRY-001 | MEDIUM | Retry UI feedback not implemented | Frontend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="retry feedback"` |
| PERF-RETRY-002 | MEDIUM | Retry limit handling not implemented | Backend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="retry limits"` |
| PERF-RETRY-003 | MEDIUM | Exponential backoff indicators not implemented | Frontend Lead | 2025-01-30 | Open | `npx playwright test --project=regression-chromium tests/e2e/regression/performance/ --grep="exponential backoff"` |

## 📋 Team Assignment Summary

### Backend Lead (12 issues)
- **Security-Critical**: 8 issues (AUTH-SECURITY-001 to 006, RBAC-ISSUE-003 to 006)
- **Queue**: 3 issues (QUEUE-RETRY-001, QUEUE-LIMITS-001, BACKGROUND-JOBS-001)
- **Performance**: 1 issue (PERF-RETRY-002)

### Frontend Lead (15 issues)
- **CSV**: 2 issues (CSV-IMPORT-EXPORT-001, CSV-IMPORT-EXPORT-002)
- **i18n**: 4 issues (I18N-LANGUAGE-001 to I18N-FORMATTING-001)
- **Performance**: 9 issues (PERF-LOAD-001, PERF-LOAD-002, PERF-API-001 to PERF-API-004, PERF-RETRY-001, PERF-RETRY-003)

### DevOps Lead (4 issues)
- **Queue**: 2 issues (QUEUE-MONITORING-001, PERFORMANCE-MONITORING-001)
- **Performance**: 2 issues (PERF-MONITOR-001, PERF-MONITOR-002, PERF-MONITOR-003, PERF-MONITOR-004)

### QA Lead (2 issues)
- **RBAC**: 2 issues (RBAC-ISSUE-001, RBAC-ISSUE-002)

## 🎯 Priority Timeline

### Week 1 (Jan 15-18, 2025)
- **CRITICAL**: RBAC-ISSUE-005, RBAC-ISSUE-006 (Cross-tenant access)
- **HIGH**: AUTH-SECURITY-001, AUTH-SECURITY-002, AUTH-SECURITY-006
- **QA**: RBAC-ISSUE-001, RBAC-ISSUE-002 (Test data fixes)

### Week 2 (Jan 19-25, 2025)
- **HIGH**: AUTH-SECURITY-003, AUTH-SECURITY-004, RBAC-ISSUE-003, RBAC-ISSUE-004
- **Queue**: QUEUE-MONITORING-001, QUEUE-RETRY-001
- **CSV**: CSV-IMPORT-EXPORT-001, CSV-IMPORT-EXPORT-002

### Week 3 (Jan 26-30, 2025)
- **MEDIUM**: All remaining AUTH-SECURITY, QUEUE, i18n, Performance issues
- **Final**: Performance monitoring and recommendations

## 📁 Artifacts & References

### Test Artifacts
- **Screenshots**: `test-results/regression-*/test-failed-*.png`
- **Videos**: `test-results/regression-*/video.webm`
- **Console Logs**: Available in test output
- **Playwright Reports**: `playwright-report/index.html`

### Documentation References
- **Phase 4 Results**: `docs/TASK_LOG_PHASE_4.md`
- **Detailed Analysis**: `docs/RBAC_ISSUES_DETAILED_ANALYSIS.md`
- **CSV Retest Plan**: `docs/CSV_FEATURES_RETEST_PLAN.md`
- **Changelog**: `CHANGELOG.md` (Known Issues section)

### Test Commands Reference
```bash
# Full regression suite
npx playwright test --config=playwright.config.ts --project=regression-chromium tests/e2e/regression/ --reporter=list

# Individual modules
npx playwright test --config=playwright.config.ts --project=regression-chromium tests/e2e/regression/auth/
npx playwright test --config=playwright.config.ts --project=regression-chromium tests/e2e/regression/rbac/
npx playwright test --config=playwright.config.ts --project=regression-chromium tests/e2e/regression/csv/
npx playwright test --config=playwright.config.ts --project=regression-chromium tests/e2e/regression/queue/
npx playwright test --config=playwright.config.ts --project=regression-chromium tests/e2e/regression/i18n/
npx playwright test --config=playwright.config.ts --project=regression-chromium tests/e2e/regression/performance/
```

---

**Last Updated**: 2025-01-15  
**Next Review**: Weekly during fix phase  
**Status**: Phase 4 Completed → Issue Resolution Phase
