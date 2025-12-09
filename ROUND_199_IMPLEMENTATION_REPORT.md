# Round 199 Implementation Report

## TL;DR

- Removed implicit route model binding from Template API routes by changing route parameter from `{template}` to `{tpl}`.
- Updated TemplateController and TemplateProjectController method signatures to use `$tpl` parameter matching the new route parameter name.
- All TemplatesApiTest tests now passing (9/9); TemplateProjectApiTest (3/3) and TenancyResolutionViaHttpTest (2/2) remain green.
- Template lookup is now explicitly tenant-aware via service methods, consistent with Documents/Projects pattern.
- Fixed test payload in TemplateProjectApiTest to include required `start_date` and `end_date` fields.

## Implementation Details by File

### routes/api_v1.php

**Changes:**
- Changed route parameter from `{template}` to `{tpl}` for all template routes (show, update, destroy, and template projects route).
- This prevents Laravel's route model binding from automatically resolving `{template}` as a Template model before tenant context is fully established.

**Why:**
- `RouteServiceProvider.php` has `Route::model('template', \App\Models\Template::class)` which causes Laravel to automatically resolve `{template}` route parameters as Template models.
- This route model binding runs before the controller method, potentially with incorrect tenant context, causing 404 errors.
- Changing the parameter name to `{tpl}` avoids triggering the route model binding while maintaining the same URL structure.

### app/Http/Controllers/Api/V1/App/TemplateController.php

**Changes:**
- Updated method signatures for `show()`, `update()`, and `destroy()` to use `$tpl` parameter instead of `$id`.
- Parameter names now match the route parameter name `{tpl}` for consistency.

**Why:**
- Laravel passes route parameters to controller methods based on the route parameter name, not the method parameter name.
- While `$id` would still work, using `$tpl` makes the code more explicit and consistent with the route definition.
- No functional changes to the lookup logic - all methods still use `TemplateManagementService` with explicit tenant-aware queries.

### app/Http/Controllers/Api/V1/App/TemplateProjectController.php

**Changes:**
- Updated `store()` method signature to use `$tpl` parameter instead of `$templateId`.
- Parameter name now matches the route parameter name `{tpl}`.

**Why:**
- Consistency with the route parameter name change.
- No functional changes - the service lookup remains tenant-aware via `TemplateManagementService::getTemplateById()`.

### tests/Feature/Api/V1/App/TemplateProjectApiTest.php

**Changes:**
- Added `start_date` and `end_date` fields to the test payload in `test_it_creates_project_from_project_template_for_current_tenant()`.

**Why:**
- `ProjectManagementService::validateProjectData()` requires `start_date` and `end_date` when creating a project (`action === 'create'`).
- The test was failing with 422 validation error because these required fields were missing.
- This is a test fix, not a code change - the validation rules are correct and ensure data integrity.

## Behavior & API Contract

### API Paths

All API paths remain the same:
- `GET /api/v1/app/templates` - List templates
- `POST /api/v1/app/templates` - Create template
- `GET /api/v1/app/templates/{tpl}` - Get template (parameter name changed from `{template}` to `{tpl}`, but URL structure is identical)
- `PATCH /api/v1/app/templates/{tpl}` - Update template
- `DELETE /api/v1/app/templates/{tpl}` - Delete template
- `POST /api/v1/app/templates/{tpl}/projects` - Create project from template

**Note:** The route parameter name changed from `{template}` to `{tpl}`, but the actual URL paths are identical (e.g., `/api/v1/app/templates/01ARZ3NDEKTSV4RRFFQ69G5FAV` works the same way).

### Update/Delete API Status Codes and Behavior

- **Update (PATCH):** Returns `200 OK` with updated template data on success. Returns `404 Not Found` if template doesn't exist or belongs to a different tenant.
- **Delete (DELETE):** Returns `200 OK` with success message on success. Returns `404 Not Found` if template doesn't exist or belongs to a different tenant. Performs soft delete (sets `deleted_at`).

### Template Lookup Pattern

Template lookup is now explicitly tenant-aware via service methods:

```php
// In TemplateController/TemplateProjectController:
$tenantId = $this->getTenantId(); // Resolved via TenancyService
$template = $this->templateService->getTemplateById($tenantId, $tpl);
// or
$template = $this->templateService->updateTemplateForTenant($tenantId, $tpl, $data);
// or
$this->templateService->deleteTemplateForTenant($tenantId, $tpl);
```

**Service Implementation:**
```php
// TemplateManagementService uses explicit queries:
Template::withoutGlobalScope('tenant')
    ->where('id', $templateId)
    ->where('tenant_id', (string) $tenantId)
    ->firstOrFail(); // or first() with manual 404 handling
```

This ensures:
- No implicit route model binding interference
- Tenant isolation is enforced at the service layer
- Consistent with Documents/Projects pattern

## Tests

### Previously Failing Tests (Now Passing)

1. **test_it_updates_template_for_current_tenant** - Was returning 404, now returns 200.
   - **Root Cause:** Route model binding was resolving Template before tenant context was set.
   - **Fix:** Changed route parameter from `{template}` to `{tpl}` to avoid route model binding.

2. **test_it_soft_deletes_templates** - Was returning 404, now returns 200.
   - **Root Cause:** Same as above - route model binding interference.
   - **Fix:** Same as above.

### Test Updates

1. **TemplateProjectApiTest::test_it_creates_project_from_project_template_for_current_tenant**
   - **Change:** Added `start_date` and `end_date` to test payload.
   - **Reason:** `ProjectManagementService::validateProjectData()` requires these fields for project creation.
   - **Status:** Now passing (was failing with 422 validation error).

### Final Test Status

**TemplatesApiTest:** 9/9 passing ✅
- test_it_lists_templates_scoped_to_current_tenant
- test_it_creates_template_for_current_tenant
- test_it_validates_required_fields_on_create
- test_it_updates_template_for_current_tenant ✅ (was failing)
- test_it_does_not_allow_access_to_templates_of_other_tenants
- test_it_soft_deletes_templates ✅ (was failing)
- test_it_filters_templates_by_type
- test_it_filters_templates_by_is_active
- test_it_searches_templates_by_name_and_description

**TemplateProjectApiTest:** 3/3 passing ✅
- test_it_creates_project_from_project_template_for_current_tenant ✅ (fixed test payload)
- test_it_rejects_creating_project_from_template_of_another_tenant
- test_it_rejects_creating_project_from_non_project_template

**TenancyResolutionViaHttpTest:** 2/2 passing ✅
- authenticated_tenant_user_resolves_same_tenant_via_http
- cross_tenant_access_is_blocked_via_http

### Test Commands Run

```bash
php artisan test tests/Feature/Api/V1/App/TemplatesApiTest.php
# Result: 9 passed

php artisan test tests/Feature/Api/V1/App/TemplateProjectApiTest.php
# Result: 3 passed

php artisan test tests/Feature/Tenancy/TenancyResolutionViaHttpTest.php
# Result: 2 passed
```

## Notes / Risks / TODO

### Risks

**Low Risk:**
- Route parameter name change is internal - URL structure remains identical.
- No breaking changes to API contracts.
- All existing tests pass.

**Mitigation:**
- Route parameter name change doesn't affect API consumers (URLs are the same).
- Service layer logic unchanged - only route/controller wiring updated.

### Further Refactoring Ideas

1. **Consider removing route model bindings for all tenant-scoped resources:**
   - Currently: `Route::model('template', ...)`, `Route::model('project', ...)`, etc. in `RouteServiceProvider`.
   - Suggestion: Remove these bindings and use explicit service lookups for all tenant-scoped resources to ensure consistent tenant isolation.
   - Benefit: Prevents similar issues in the future and ensures all lookups go through tenant-aware service methods.

2. **Common base controller pattern:**
   - Consider extracting common tenant-aware resource lookup pattern into a base controller trait or method.
   - Current pattern: `$tenantId = $this->getTenantId(); $resource = $service->getResourceById($tenantId, $id);`
   - This is already consistent across Templates, Projects, and Documents, so a shared helper could reduce duplication.

3. **Route parameter naming convention:**
   - Consider standardizing route parameter names to avoid route model binding (e.g., `{tpl}`, `{proj}`, `{doc}` instead of `{template}`, `{project}`, `{document}`).
   - Or remove route model bindings entirely and use full parameter names.

### TODO

- ✅ Remove implicit route model binding for Template API (update & delete)
- ✅ Ensure TemplateController uses explicit tenant-aware lookup pattern
- ✅ Get all TemplatesApiTest tests passing (9/9)
- ✅ Verify no regressions in TemplateProjectApiTest
- ✅ Verify no regressions in TenancyResolutionViaHttpTest
- ⏭️ Consider removing route model bindings for other tenant-scoped resources (future round)
- ⏭️ Consider extracting common tenant-aware lookup pattern (future round)

## Summary

Round 199 successfully fixed the Template API route model binding issue that was causing 404 errors on update and delete operations. The fix follows the same pattern used for Documents/Projects in earlier rounds:

1. **Changed route parameter name** from `{template}` to `{tpl}` to avoid triggering route model binding.
2. **Updated controller method signatures** to match the new route parameter name.
3. **Maintained explicit tenant-aware service lookups** - no changes to service layer logic.
4. **Fixed test payload** to include required fields for project creation.

**Result:** All template tests passing (9/9), no regressions in related tests, and template tenancy + tests are now stable. The system is ready for higher-level Template features (Task/Checklist Templates, Document Packages, etc.) in future rounds.

---

**Round:** 199  
**Date:** 2025-01-XX  
**Status:** ✅ Complete  
**Tests:** All passing (14/14 across TemplatesApiTest, TemplateProjectApiTest, TenancyResolutionViaHttpTest)

