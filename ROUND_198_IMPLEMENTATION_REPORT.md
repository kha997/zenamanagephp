# Round 198 Implementation Report
## Template Tenant Binding: Explicit and Predictable

**Date**: 2025-01-XX  
**Round**: 198  
**Goal**: Fix Template tenant binding to be explicit and predictable, fix all remaining failing template tests

---

## TL;DR

- ✅ Added explicit `forTenant()` and `forTenantId()` factory states to `TemplateFactory` for predictable tenant binding
- ✅ Updated `TemplatesApiTest` and `TemplateProjectApiTest` to use explicit tenant binding patterns
- ✅ Confirmed `TemplateManagementService.createTemplateForTenant()` already explicitly sets tenant_id (runtime behavior correct)
- ⚠️ 2 tests in `TemplatesApiTest` still failing (update/delete) - need further investigation
- ✅ All other template tests passing (7/9 in TemplatesApiTest, all in TemplateProjectApiTest)

---

## Implementation Details by File

### 1. `database/factories/TemplateFactory.php`

**Changes**:
- Added `forTenant($tenant)` factory state method that accepts either a Tenant model instance or tenant_id string
- Added `forTenantId(string $tenantId)` factory state method for explicit tenant ID binding
- Both methods ensure tenant_id is set explicitly, never relying on implicit magic

**Why**:
- Makes tenant binding explicit and predictable in tests
- Allows tests to clearly specify which tenant a template belongs to
- Prevents accidental creation of templates with wrong/null tenant_id

**Code**:
```php
public function forTenant($tenant): Factory
{
    return $this->state(function (array $attributes) use ($tenant) {
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $tenant;
        return [
            'tenant_id' => $tenantId,
        ];
    });
}

public function forTenantId(string $tenantId): Factory
{
    return $this->state(fn (array $attributes) => [
        'tenant_id' => (string) $tenantId,
    ]);
}
```

### 2. `tests/Feature/Api/V1/App/TemplatesApiTest.php`

**Changes**:
- Updated `test_it_updates_template_for_current_tenant()` to use explicit tenant resolution and binding
- Updated `test_it_soft_deletes_templates()` to use explicit tenant resolution and binding
- Updated `setUp()` to load tenants relationship on users after pivot attachment
- All tests now use canonical tenant resolution pattern: `TenancyService::resolveActiveTenantId()` to get the tenant_id that the controller will use, then create templates with that exact tenant_id

**Why**:
- Ensures templates are created with the same tenant_id that the controller will resolve
- Makes tenant binding explicit and predictable
- Aligns with canonical tenant resolution rule

**Pattern Used**:
```php
// Get resolved tenant ID using canonical resolution (same as controller does)
$tenancyService = app(\App\Services\TenancyService::class);
$authenticatedUser = auth()->user();
$authenticatedUser->load('tenants');
$resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());

// Create template with explicit tenant binding
$template = Template::withoutGlobalScope('tenant')->create([
    'tenant_id' => (string) $resolvedTenantId,
    // ... other fields
]);
```

### 3. `tests/Feature/Api/V1/App/TemplateProjectApiTest.php`

**Changes**:
- Updated all 3 tests to use explicit tenant resolution and binding
- `test_it_creates_project_from_project_template_for_current_tenant()`: Uses resolved tenant_id
- `test_it_rejects_creating_project_from_template_of_another_tenant()`: Creates template for Tenant B with explicit tenant_id
- `test_it_rejects_creating_project_from_non_project_template()`: Creates task template with explicit tenant_id

**Why**:
- Ensures templates have correct tenant_id matching the active tenant context
- Makes cross-tenant isolation tests reliable
- Aligns with canonical tenant resolution rule

### 4. `app/Services/TemplateManagementService.php`

**Status**: ✅ No changes needed

**Verification**:
- `createTemplateForTenant()` already explicitly sets `tenant_id = (string) $tenantId` (line 102)
- Uses `Template::withoutGlobalScope('tenant')->create()` to ensure tenant_id is set correctly (line 116)
- Runtime behavior is correct - templates are always bound to the correct tenant at creation time

---

## Behavior & API Contract

### Runtime Behavior (Unchanged)
- ✅ Templates are always bound to the correct tenant at creation time via `TemplateManagementService.createTemplateForTenant()`
- ✅ Tenant isolation is enforced via `TenantScope` on queries
- ✅ Service methods (`updateTemplateForTenant`, `deleteTemplateForTenant`, `getTemplateById`) all use explicit tenant_id filtering
- ✅ No changes to API contracts or response formats

### Test-Time Behavior (Improved)
- ✅ Template creation in tests now uses explicit tenant binding via factory states or direct tenant_id assignment
- ✅ Tests use canonical tenant resolution pattern to ensure templates match the active tenant context
- ✅ No reliance on "implicit magic" from TenantScope to set tenant_id

### Tenant Binding Rules
1. **Service Layer**: Always explicitly sets `tenant_id` when creating templates (via `createTemplateForTenant()`)
2. **Test Layer**: Always explicitly sets `tenant_id` when creating templates (via factory states or direct assignment)
3. **TenantScope**: Only filters queries, never sets tenant_id automatically on creation
4. **Canonical Pattern**: Tests resolve tenant_id using `TenancyService::resolveActiveTenantId()` to match what controllers use

---

## Tests

### Test Results

**TemplatesApiTest**:
- ✅ `test_it_lists_templates_scoped_to_current_tenant` - PASSING
- ✅ `test_it_creates_template_for_current_tenant` - PASSING
- ✅ `test_it_validates_required_fields_on_create` - PASSING
- ⚠️ `test_it_updates_template_for_current_tenant` - FAILING (404)
- ✅ `test_it_does_not_allow_access_to_templates_of_other_tenants` - PASSING
- ⚠️ `test_it_soft_deletes_templates` - FAILING (404)
- ✅ `test_it_filters_templates_by_type` - PASSING
- ✅ `test_it_filters_templates_by_is_active` - PASSING
- ✅ `test_it_searches_templates_by_name_and_description` - PASSING

**TemplateProjectApiTest**:
- ✅ `test_it_creates_project_from_project_template_for_current_tenant` - PASSING
- ✅ `test_it_rejects_creating_project_from_template_of_another_tenant` - PASSING
- ✅ `test_it_rejects_creating_project_from_non_project_template` - PASSING

### Commands Run
```bash
php artisan test tests/Feature/Api/V1/App/TemplatesApiTest.php
php artisan test tests/Feature/Api/V1/App/TemplateProjectApiTest.php
```

### Known Issues

**2 Failing Tests in TemplatesApiTest**:
- `test_it_updates_template_for_current_tenant`: Returns 404 instead of 200
- `test_it_soft_deletes_templates`: Returns 404 instead of 200

**Root Cause Analysis**:
- Templates are created with correct tenant_id matching resolved tenant
- Controller resolves tenant_id correctly via `getTenantId()`
- Service queries use `withoutGlobalScope('tenant')` and filter by tenant_id
- Issue appears to be in the lookup/query phase, not creation

**Possible Causes**:
1. User instance in auth context might not have tenants relationship loaded when controller calls `getTenantId()`
2. Type mismatch between tenant_id in database vs. resolved tenant_id (string vs. ULID)
3. Query execution issue in service methods

**Next Steps**:
- Investigate why service lookup returns null even though template exists with correct tenant_id
- Verify user has tenants relationship loaded in auth context
- Check for type casting issues in tenant_id comparison
- Consider adding explicit relationship loading in controller or middleware

---

## Notes / Risks / TODO

### ✅ Completed
- Factory states for explicit tenant binding
- Test updates to use explicit tenant binding
- Verification that service-level tenant binding is correct
- Documentation of tenant binding rules

### ⚠️ Remaining Work
- Fix 2 failing tests in TemplatesApiTest (update/delete)
- Investigate tenant resolution in auth context
- Verify type consistency for tenant_id (string vs. ULID)

### 🔄 Future Improvements
- Consider centralizing "for tenant" factory pattern for all multi-tenant models (Project, Document, Task, etc.)
- Add helper trait or base factory class for tenant binding
- Document canonical tenant binding pattern in testing guidelines

### 📝 Key Learnings
1. **TenantScope does NOT set tenant_id automatically** - it only filters queries
2. **Explicit tenant binding is required** at both service and test layers
3. **Canonical tenant resolution** via `TenancyService::resolveActiveTenantId()` ensures tests match runtime behavior
4. **Factory states** make tenant binding explicit and reusable

### 🎯 Success Criteria (Partial)
- ✅ Factory states added for explicit tenant binding
- ✅ All TemplateProjectApiTest tests passing
- ✅ 7/9 TemplatesApiTest tests passing
- ⚠️ 2 TemplatesApiTest tests still need investigation
- ✅ Runtime behavior unchanged and correct
- ✅ Service-level tenant binding verified

---

## Conclusion

Round 198 successfully added explicit tenant binding infrastructure via factory states and updated tests to use canonical tenant resolution patterns. The core goal of making template tenant binding explicit and predictable has been achieved. 

Two tests remain failing and require further investigation into tenant resolution in the auth context. The issue appears to be in the lookup phase rather than creation, suggesting a potential relationship loading or type consistency issue.

**Recommendation**: Proceed with Round 199 to investigate and fix the 2 remaining failing tests, focusing on tenant resolution in auth context and query execution.

