# Phase 2: Auth Unit Test Failures Analysis

**Date:** 2025-11-11  
**Test Suite:** `auth-unit`  
**Results:** 72 failed, 40 skipped, 577 passed  
**Duration:** 42m 46s

---

## Summary

Tổng cộng **689 tests** trong `auth-unit` suite:
- ✅ **577 passed** (83.7%)
- ❌ **72 failed** (10.4%)
- ⚠️ **40 skipped** (5.8%)

---

## Failure Categories

### 1. Database Schema Issues (High Priority)

#### Issue: Missing `tenants` table in test environment
**Affected Tests:**
- `Tests\Unit\Helpers\TestDataSeederVerificationTest > users domain seed reproducibility`
- `Tests\Unit\Helpers\TestDataSeederVerificationTest > dashboard domain seed reproducibility`

**Error:**
```
SQLSTATE[HY000]: General error: 1 no such table: tenants
```

**Root Cause:** Test environment không có migrations chạy đầy đủ trước khi seed data.

**Fix:** Đảm bảo `RefreshDatabase` hoặc migrations chạy trước khi seed.

---

#### Issue: `templates.name` NOT NULL constraint
**Affected Tests:**
- `Tests\Unit\TemplateBasicTest > it can get template data`
- `Tests\Unit\TemplateBasicTest > it can filter by tenant`
- `Tests\Unit\TemplateBasicTest > it can filter by category`
- `Tests\Unit\TemplateBasicTest > it can increment version`
- `Tests\Unit\TemplateBasicTest > it can be soft deleted`

**Error:**
```
SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: templates.name
```

**Root Cause:** Template factory hoặc model không set `name` field, nhưng database schema yêu cầu NOT NULL.

**Fix:** Update Template factory hoặc model để luôn set `name` field.

---

#### Issue: `zena_roles.name` UNIQUE constraint violation
**Affected Tests:**
- `Tests\Unit\Repositories\UserRepositoryTest > it can get user permissions`
- `Tests\Unit\Repositories\UserRepositoryTest > it can check user permission`
- `Tests\Unit\Repositories\UserRepositoryTest > it can get user roles`
- `Tests\Unit\Repositories\UserRepositoryTest > it can check user role`

**Error:**
```
SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: zena_roles.name
```

**Root Cause:** Test đang tạo role với tên "admin" nhưng role này đã tồn tại từ seed data.

**Fix:** Sử dụng role từ seed data thay vì tạo mới, hoặc dùng unique name.

---

### 2. Missing Properties (Medium Priority)

#### Issue: `$seedData` property not defined
**Affected Tests:**
- `Tests\Unit\Services\TaskDependencyServiceTest > it can add task dependency`
- `Tests\Unit\Services\TaskDependencyServiceTest > it prevents self dependency`
- `Tests\Unit\Services\TaskDependencyServiceTest > it prevents simple circular dependency`

**Error:**
```
Undefined property: Tests\Unit\Services\TaskDependencyServiceTest::$seedData
```

**Root Cause:** Test class chưa được update để sử dụng `DomainTestIsolation` và `TestDataSeeder`.

**Fix:** Update test class để:
1. Use `DomainTestIsolation` trait
2. Call `TestDataSeeder::seedTasksDomain()` in `setUp()`
3. Store result in `$this->seedData`

---

### 3. Model Attribute Mismatches (Medium Priority)

#### Issue: User fillable attributes
**Affected Test:**
- `Tests\Unit\Models\UserTest > it has fillable attributes`

**Error:**
```
Failed asserting that two arrays are equal.
Expected: [...]
Actual: [... + 'failed_login_attempts', + 'locked_until']
```

**Root Cause:** Model đã thêm `failed_login_attempts` và `locked_until` vào fillable, nhưng test chưa update.

**Fix:** Update test assertion để include 2 fields mới.

---

#### Issue: Template constants missing
**Affected Tests:**
- `Tests\Unit\TemplateTest > it can check if template can be used`
- `Tests\Unit\TemplateTest > it can publish template`
- `Tests\Unit\TemplateTest > it can archive template`
- `Tests\Unit\TemplateTest > it can filter published templates`

**Error:**
```
Undefined constant App\Models\Template::STATUS_PUBLISHED
```

**Root Cause:** Template model không có constants `STATUS_PUBLISHED`, `STATUS_DRAFT`, etc.

**Fix:** 
- Option 1: Add constants to Template model
- Option 2: Update tests để dùng string values thay vì constants

---

### 4. Test Logic Issues (Low Priority)

#### Issue: Project repository filter by manager_id
**Affected Tests:**
- `Tests\Unit\Repositories\ProjectRepositoryTest > it can filter projects by manager id`
- `Tests\Unit\Repositories\ProjectRepositoryTest > it can get projects by manager id`

**Error:**
```
Failed asserting that 0 matches expected 1.
```

**Root Cause:** Seed data có thể không có project với `manager_id` matching `$this->user->id`.

**Fix:** Đảm bảo seed data có project với `manager_id` set correctly, hoặc update test để dùng project từ seed data.

---

#### Issue: Task statistics count mismatch
**Affected Tests:**
- `Tests\Unit\Services\TaskManagementServiceTest > task statistics uses cloned queries`
- `Tests\Unit\TaskServiceTest > get tasks with filters`

**Error:**
```
Failed asserting that 10 matches expected 6.
Failed asserting that actual size 6 matches expected size 2.
```

**Root Cause:** Seed data có nhiều tasks hơn expected, hoặc test assertions cần update.

**Fix:** Update test assertions để match với seed data counts, hoặc adjust seed data.

---

### 5. Security Test Issues (Low Priority)

#### Issue: CSRF token route 404
**Affected Tests:**
- `Tests\Unit\SecurityTest > csrf token is generated`
- `Tests\Unit\SecurityTest > csrf protection is active`
- `Tests\Unit\SecurityTest > guest middleware redirects authenticated users`

**Error:**
```
Expected response status code [200] but received 404.
```

**Root Cause:** Route `/login` có thể không tồn tại hoặc được handle bởi frontend (React SPA).

**Fix:** Update tests để match với actual routing (có thể là frontend route).

---

#### Issue: Redirect URL mismatch
**Affected Test:**
- `Tests\Unit\SecurityTest > authentication middleware protects routes`

**Error:**
```
Expected: 'http://localhost/login'
Actual: 'http://localhost:5173/login'
```

**Root Cause:** Redirect URL includes port number (5173 - Vite dev server).

**Fix:** Update assertion để accept both URLs hoặc configure test environment.

---

#### Issue: XSS escaping
**Affected Test:**
- `Tests\Unit\SecurityTest > input sanitization prevents xss`

**Error:**
```
Failed asserting that '<!DOCTYPE html>...' contains "&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;".
```

**Root Cause:** XSS payload được escape trong JSON (correct behavior), nhưng test đang check HTML output.

**Fix:** Update test để check JSON escaping thay vì HTML, hoặc check trong HTML context nếu cần.

---

## Recommended Fix Priority

### Priority 1: Database Schema (Blocking)
1. ✅ Fix `tenants` table missing - Ensure migrations run
2. ✅ Fix `templates.name` NOT NULL - Update factory/model
3. ✅ Fix `zena_roles.name` UNIQUE - Use seed data or unique names

### Priority 2: Missing Properties (Blocking)
4. ✅ Fix `TaskDependencyServiceTest::$seedData` - Add DomainTestIsolation

### Priority 3: Model Attributes (Non-blocking)
5. ⚠️ Fix User fillable attributes test - Update assertion
6. ⚠️ Fix Template constants - Add constants or update tests

### Priority 4: Test Logic (Non-blocking)
7. ⚠️ Fix Project repository tests - Use seed data correctly
8. ⚠️ Fix Task statistics tests - Update assertions

### Priority 5: Security Tests (Non-blocking)
9. ⚠️ Fix Security tests - Update for frontend routing

---

## Next Steps

1. **Immediate:** Fix Priority 1 issues (database schema)
2. **Short-term:** Fix Priority 2 issues (missing properties)
3. **Medium-term:** Fix Priority 3-4 issues (model attributes, test logic)
4. **Long-term:** Review Priority 5 issues (security tests)

---

**Last Updated:** 2025-11-11

