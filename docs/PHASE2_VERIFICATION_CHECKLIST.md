# Phase 2: Verification Checklist

**Date:** 2025-11-09  
**Purpose:** Track progress of Phase 2 verification tests

---

## Quick Start

### Chạy một test suite cụ thể:
```bash
php artisan test --testsuite=auth-unit
```

### Hoặc sử dụng script helper:
```bash
chmod +x scripts/verify-phase2.sh
./scripts/verify-phase2.sh auth unit
```

### Chạy một phase (6 suites):
```bash
chmod +x scripts/verify-phase2-all.sh
./scripts/verify-phase2-all.sh 1  # Unit tests
./scripts/verify-phase2-all.sh 2  # Feature tests
./scripts/verify-phase2-all.sh 3  # Integration tests
```

---

## Verification Progress

### ⏳ Phase 1: Unit Tests (6 suites)

| Suite | Status | Passed | Failed | Skipped | Duration | Notes |
|-------|--------|--------|--------|---------|----------|-------|
| `auth-unit` | ⏳ | - | - | - | - | - |
| `projects-unit` | ⏳ | - | - | - | - | - |
| `tasks-unit` | ⏳ | - | - | - | - | - |
| `documents-unit` | ⏳ | - | - | - | - | - |
| `users-unit` | ⏳ | - | - | - | - | - |
| `dashboard-unit` | ⏳ | - | - | - | - | - |

**Commands:**
```bash
php artisan test --testsuite=auth-unit
php artisan test --testsuite=projects-unit
php artisan test --testsuite=tasks-unit
php artisan test --testsuite=documents-unit
php artisan test --testsuite=users-unit
php artisan test --testsuite=dashboard-unit
```

**Or use script:**
```bash
./scripts/verify-phase2-all.sh 1
```

---

### ⏳ Phase 2: Feature Tests (6 suites)

| Suite | Status | Passed | Failed | Skipped | Duration | Notes |
|-------|--------|--------|--------|---------|----------|-------|
| `auth-feature` | ⏳ | - | - | - | - | - |
| `projects-feature` | ⏳ | - | - | - | - | - |
| `tasks-feature` | ⏳ | - | - | - | - | - |
| `documents-feature` | ⏳ | - | - | - | - | - |
| `users-feature` | ⏳ | - | - | - | - | - |
| `dashboard-feature` | ⏳ | - | - | - | - | - |

**Commands:**
```bash
php artisan test --testsuite=auth-feature
php artisan test --testsuite=projects-feature
php artisan test --testsuite=tasks-feature
php artisan test --testsuite=documents-feature
php artisan test --testsuite=users-feature
php artisan test --testsuite=dashboard-feature
```

**Or use script:**
```bash
./scripts/verify-phase2-all.sh 2
```

---

### ⏳ Phase 3: Integration Tests (6 suites)

| Suite | Status | Passed | Failed | Skipped | Duration | Notes |
|-------|--------|--------|--------|---------|----------|-------|
| `auth-integration` | ⏳ | - | - | - | - | - |
| `projects-integration` | ⏳ | - | - | - | - | - |
| `tasks-integration` | ⏳ | - | - | - | - | - |
| `documents-integration` | ⏳ | - | - | - | - | - |
| `users-integration` | ⏳ | - | - | - | - | - |
| `dashboard-integration` | ⏳ | - | - | - | - | - |

**Commands:**
```bash
php artisan test --testsuite=auth-integration
php artisan test --testsuite=projects-integration
php artisan test --testsuite=tasks-integration
php artisan test --testsuite=documents-integration
php artisan test --testsuite=users-integration
php artisan test --testsuite=dashboard-integration
```

**Or use script:**
```bash
./scripts/verify-phase2-all.sh 3
```

---

## Status Legend

- ⏳ **Pending** - Chưa chạy
- 🔄 **Running** - Đang chạy
- ✅ **Passed** - Tất cả tests pass
- ⚠️ **Partial** - Một số tests pass, một số fail/skip
- ❌ **Failed** - Có tests fail
- ⏭️ **Skipped** - Bỏ qua (có lý do)

---

## Notes

Sau mỗi test suite, cập nhật:
1. Status
2. Test counts (passed/failed/skipped)
3. Duration
4. Notes (nếu có lỗi quan trọng)

Kết quả chi tiết được lưu trong: `storage/app/test-results/<suite>.txt`

---

## Quick Reference

### Chạy một test suite
```bash
./scripts/verify-phase2.sh <domain> <type>
```

### Chạy một phase
```bash
./scripts/verify-phase2-all.sh <phase>
# phase: 1 (unit), 2 (feature), 3 (integration)
```

### Xem kết quả
```bash
cat storage/app/test-results/<suite>.txt | tail -30
```

---

**Last Updated:** 2025-11-09

