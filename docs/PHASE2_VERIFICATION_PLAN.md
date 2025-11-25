# Phase 2: Verification Plan - Chia Nhỏ Test Execution

**Date:** 2025-11-09  
**Purpose:** Chia nhỏ việc chạy full test suite thành các phần nhỏ, dễ kiểm soát

---

## Tổng Quan

Thay vì chạy toàn bộ test suite một lần (có thể mất nhiều thời gian và khó kiểm soát), chúng ta sẽ chia thành **18 test suites nhỏ** (6 domains × 3 types) và chạy từng phần một.

---

## Cấu Trúc Test Suites

### 6 Domains × 3 Test Types = 18 Test Suites

| Domain | Unit Suite | Feature Suite | Integration Suite |
|--------|------------|---------------|-------------------|
| **Auth** | `auth-unit` | `auth-feature` | `auth-integration` |
| **Projects** | `projects-unit` | `projects-feature` | `projects-integration` |
| **Tasks** | `tasks-unit` | `tasks-feature` | `tasks-integration` |
| **Documents** | `documents-unit` | `documents-feature` | `documents-integration` |
| **Users** | `users-unit` | `users-feature` | `users-integration` |
| **Dashboard** | `dashboard-unit` | `dashboard-feature` | `dashboard-integration` |

---

## Kế Hoạch Chạy Tests

### Phase 1: Unit Tests (6 suites) - Nhanh nhất
Chạy unit tests cho tất cả domains (thường nhanh nhất):

```bash
# 1. Auth Unit Tests
php artisan test --testsuite=auth-unit

# 2. Projects Unit Tests
php artisan test --testsuite=projects-unit

# 3. Tasks Unit Tests
php artisan test --testsuite=tasks-unit

# 4. Documents Unit Tests
php artisan test --testsuite=documents-unit

# 5. Users Unit Tests
php artisan test --testsuite=users-unit

# 6. Dashboard Unit Tests
php artisan test --testsuite=dashboard-unit
```

**Ước tính thời gian:** ~5-10 phút mỗi suite

---

### Phase 2: Feature Tests (6 suites) - Trung bình
Chạy feature tests cho tất cả domains:

```bash
# 1. Auth Feature Tests
php artisan test --testsuite=auth-feature

# 2. Projects Feature Tests
php artisan test --testsuite=projects-feature

# 3. Tasks Feature Tests
php artisan test --testsuite=tasks-feature

# 4. Documents Feature Tests
php artisan test --testsuite=documents-feature

# 5. Users Feature Tests
php artisan test --testsuite=users-feature

# 6. Dashboard Feature Tests
php artisan test --testsuite=dashboard-feature
```

**Ước tính thời gian:** ~10-20 phút mỗi suite

---

### Phase 3: Integration Tests (6 suites) - Chậm nhất
Chạy integration tests cho tất cả domains:

```bash
# 1. Auth Integration Tests
php artisan test --testsuite=auth-integration

# 2. Projects Integration Tests
php artisan test --testsuite=projects-integration

# 3. Tasks Integration Tests
php artisan test --testsuite=tasks-integration

# 4. Documents Integration Tests
php artisan test --testsuite=documents-integration

# 5. Users Integration Tests
php artisan test --testsuite=users-integration

# 6. Dashboard Integration Tests
php artisan test --testsuite=dashboard-integration
```

**Ước tính thời gian:** ~15-30 phút mỗi suite

---

## Cách Chạy Từng Domain (Alternative Approach)

Nếu muốn chạy tất cả test types của một domain cùng lúc:

```bash
# Chạy tất cả Auth tests (unit + feature + integration)
php artisan test --group=auth

# Chạy tất cả Projects tests
php artisan test --group=projects

# Chạy tất cả Tasks tests
php artisan test --group=tasks

# Chạy tất cả Documents tests
php artisan test --group=documents

# Chạy tất cả Users tests
php artisan test --group=users

# Chạy tất cả Dashboard tests
php artisan test --group=dashboard
```

**Ước tính thời gian:** ~30-60 phút mỗi domain

---

## Sử Dụng Script Helper

### Script 1: Chạy một test suite cụ thể

```bash
# Cấp quyền thực thi
chmod +x scripts/verify-phase2.sh

# Chạy một test suite
./scripts/verify-phase2.sh auth unit
./scripts/verify-phase2.sh projects feature
./scripts/verify-phase2.sh dashboard integration
```

**Lợi ích:**
- Tự động lưu kết quả vào file
- Hiển thị thống kê (passed/failed/skipped)
- Hiển thị thời gian chạy
- Màu sắc dễ đọc

### Script 2: Chạy một phase (6 suites)

```bash
# Cấp quyền thực thi
chmod +x scripts/verify-phase2-all.sh

# Chạy Phase 1 (tất cả unit tests)
./scripts/verify-phase2-all.sh 1

# Chạy Phase 2 (tất cả feature tests)
./scripts/verify-phase2-all.sh 2

# Chạy Phase 3 (tất cả integration tests)
./scripts/verify-phase2-all.sh 3
```

**Lợi ích:**
- Chạy tự động tất cả 6 suites trong một phase
- Tổng hợp kết quả cuối cùng
- Dễ theo dõi tiến độ

---

## Checklist Verification

### Unit Tests (6 suites)
- [ ] `auth-unit` - ⏳
- [ ] `projects-unit` - ⏳
- [ ] `tasks-unit` - ⏳
- [ ] `documents-unit` - ⏳
- [ ] `users-unit` - ⏳
- [ ] `dashboard-unit` - ⏳

### Feature Tests (6 suites)
- [ ] `auth-feature` - ⏳
- [ ] `projects-feature` - ⏳
- [ ] `tasks-feature` - ⏳
- [ ] `documents-feature` - ⏳
- [ ] `users-feature` - ⏳
- [ ] `dashboard-feature` - ⏳

### Integration Tests (6 suites)
- [ ] `auth-integration` - ⏳
- [ ] `projects-integration` - ⏳
- [ ] `tasks-integration` - ⏳
- [ ] `documents-integration` - ⏳
- [ ] `users-integration` - ⏳
- [ ] `dashboard-integration` - ⏳

---

## Lưu Kết Quả

Kết quả sẽ được lưu tự động trong:
```
storage/app/test-results/
├── auth-unit.txt
├── auth-feature.txt
├── projects-unit.txt
├── ...
```

Xem kết quả:
```bash
# Xem kết quả của một suite
cat storage/app/test-results/auth-unit.txt | tail -30

# Xem thống kê
grep -E "(passed|failed|skipped)" storage/app/test-results/auth-unit.txt
```

---

## Quick Commands Reference

### Chạy một test suite cụ thể
```bash
php artisan test --testsuite=<domain>-<type>
```

### Chạy tất cả tests của một domain
```bash
php artisan test --group=<domain>
```

### Chạy với output chi tiết
```bash
php artisan test --testsuite=auth-unit --verbose
```

### Chạy và dừng khi có lỗi đầu tiên
```bash
php artisan test --testsuite=auth-unit --stop-on-failure
```

### Chạy và lưu kết quả
```bash
php artisan test --testsuite=auth-unit 2>&1 | tee results/auth-unit.txt
```

---

## Recommended Order

1. **Start with Unit Tests** (fastest, most isolated)
   - Chạy từng domain một: auth → projects → tasks → documents → users → dashboard
   
2. **Then Feature Tests** (medium speed)
   - Chạy từng domain một theo cùng thứ tự
   
3. **Finally Integration Tests** (slowest, most complex)
   - Chạy từng domain một theo cùng thứ tự

---

## Progress Tracking

Sau mỗi test suite, cập nhật checklist trong `docs/PHASE2_VERIFICATION_CHECKLIST.md`:
- Số tests passed
- Số tests failed
- Số tests skipped
- Thời gian chạy
- Các lỗi quan trọng (nếu có)

---

**Last Updated:** 2025-11-09  
**Status:** Ready to Execute

