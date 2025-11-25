# AuthHelper Migration Progress

## ✅ Completed Migrations

### 1. ✅ `tests/Feature/Api/Tasks/TasksContractTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** 11 test methods

### 2. ✅ `tests/Feature/Api/Projects/ProjectsContractTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** 10 test methods

### 3. ✅ `tests/Feature/Api/Documents/DocumentsContractTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** 12 test methods

### 4. ✅ `tests/Feature/Api/TaskCommentApiTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** 10 test methods

### 5. ✅ `tests/Feature/Dashboard/DashboardApiTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~40+ test methods
- **Changes:**
  - Removed `use Laravel\Sanctum\Sanctum;`
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory: `'password' => Hash::make('password')`
  - Added auth headers setup in setUp: `$this->authHeaders = AuthHelper::getAuthHeaders($this, $this->user->email, 'password');`
  - Updated all API calls to use `withHeaders($this->authHeaders)`
  - Special cases: Created separate auth headers for different users in permission tests (`$qcAuthHeaders`, `$unauthorizedAuthHeaders`)

### 6. ✅ `tests/Feature/Dashboard/AppDashboardApiTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** 12 test methods

### 7. ✅ `tests/Feature/TenantIsolationTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** 8 test methods

### 8. ✅ `tests/Feature/AuthorizationTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** 6 test methods

### 9. ✅ `tests/Feature/ApiEndpointsTest.php`
- **Status:** ✅ Completed (Tests are skipped but migrated for future use)
- **Methods migrated:** 4 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory: `'password' => Hash::make('password')`
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user)` with `$this->withHeaders($this->authHeaders)`

### 10. ✅ `tests/Feature/Api/ProjectManagerApiIntegrationTest.php`
- **Status:** ✅ Completed (Tests are skipped but migrated for future use)
- **Methods migrated:** 7 test methods

### 11. ✅ `tests/Feature/ClientsApiIntegrationTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~16 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];` and `$otherAuthHeaders = [];`
  - Set password in user factories
  - Added auth headers setup in setUp for both users
  - Replaced all `$this->actingAs()` with `$this->withHeaders($authHeaders)`
  - Created separate auth headers for member users in test methods

### 12. ✅ `tests/Feature/TasksApiIntegrationTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~13 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];` and `$otherAuthHeaders = [];`
  - Set password in user factories
  - Added auth headers setup in setUp for both users
  - Replaced all `$this->actingAs()` with `$this->withHeaders($authHeaders)`

### 13. ✅ `tests/Feature/ProjectsApiIntegrationTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~12 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];` and `$otherAuthHeaders = [];`
  - Set password in user factories
  - Added auth headers setup in setUp for both users
  - Replaced all `$this->actingAs()` with `$this->withHeaders($authHeaders)`
  - Created separate auth headers for member users in test methods

### 14. ✅ `tests/Feature/NotificationsTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~10 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user)` with `$this->withHeaders($this->authHeaders)`

### 15. ✅ `tests/Feature/TaskAssignmentTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~5 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Removed `$this->actingAs($this->user);` from setUp
  - Added auth headers setup in setUp
  - Updated all API calls to use `withHeaders($this->authHeaders)`

### 16. ✅ `tests/Feature/TemplateApiTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~15 test methods

---

## 📋 Batch 2 Migrations (In Progress)

### 17. ✅ `tests/Feature/Integration/SecurityIntegrationTest.php`
- **Status:** ✅ Completed (API routes only, web routes kept as-is)
- **Methods migrated:** ~6 API test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory: `'password' => Hash::make('password123')`
  - Added auth headers setup in setUp
  - Migrated all API calls (`/api/...`) to use `withHeaders($this->authHeaders)`
  - Web routes (`/app/...`, `/logout`) kept using `actingAs()` for session auth

### 18. ✅ `tests/Feature/Auth/PasswordChangeTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~12 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory: `'password' => Hash::make($this->currentPassword)`
  - Added auth headers setup in setUp with current password
  - Replaced all `$this->actingAs($this->user, 'sanctum')` with `$this->withHeaders($this->authHeaders)`

### 19. ✅ `tests/Feature/Users/ProfileManagementTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~9 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user, 'sanctum')` with `$this->withHeaders($this->authHeaders)`
  - Created separate auth headers for otherUser in test method

### 20. ✅ `tests/Feature/Users/AccountManagementTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~6 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user, 'sanctum')` with `$this->withHeaders($this->authHeaders)`

### 21. ✅ `tests/Feature/Auth/EmailVerificationTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~2 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Created auth headers in test methods for authenticated tests
  - Replaced `$this->actingAs($user, 'sanctum')` with `$this->withHeaders($authHeaders)`

### 22. ✅ `tests/Feature/Users/AvatarManagementTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~10 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory in `createUser()` method
  - Added auth headers setup in `createUser()` method
  - Replaced all `$this->actingAs($this->user, 'sanctum')` with `$this->withHeaders($this->authHeaders)`

### 23. ✅ `tests/Feature/Api/Admin/AdminExportSecurityTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~5 test methods
- **Changes:**
  - Removed `use Laravel\Sanctum\Sanctum;`
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $adminAuthHeaders = [];` and `$regularAuthHeaders = [];`
  - Set password in user factories
  - Added auth headers setup in setUp for both users
  - Replaced all `Sanctum::actingAs()` with `$this->withHeaders($authHeaders)`
  - Updated all `$this->json()` calls to use `withHeaders()`
  - Created separate auth headers for inactiveAdmin in test method

### 24. ✅ `tests/Feature/Performance/PerformanceFeatureTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~27 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user, 'sanctum')` with `$this->withHeaders($this->authHeaders)`

### 25. ✅ `tests/Feature/QualityAssuranceTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~17 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $userAuthHeaders = [];` and `$adminAuthHeaders = [];`
  - Set password in user factories
  - Added auth headers setup in setUp for both users
  - Replaced all `$this->actingAs()` with `$this->withHeaders($authHeaders)`
  - Updated all API calls (`/api/...`) to use `withHeaders()`

### 26. ✅ `tests/Feature/MonitoringTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~8 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user)` with `$this->withHeaders($this->authHeaders)`

### 27. ✅ `tests/Feature/ClientsQuotesTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~13 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user)` with `$this->withHeaders($this->authHeaders)`

### 28. ✅ `tests/Feature/TenantsApiTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~15 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user)` with `$this->withHeaders($this->authHeaders)`
  - Created separate auth headers for regularUser in test method

### 29. ✅ `tests/Feature/RewardsTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~13 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user)` with `$this->withHeaders($this->authHeaders)`

### 30. ✅ `tests/Feature/FocusModeTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~13 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user)` with `$this->withHeaders($this->authHeaders)`

### 31. ✅ `tests/Feature/PerformanceTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~15 test methods (API calls only)
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $userAuthHeaders = [];` and `$adminAuthHeaders = [];`
  - Set password in user factories
  - Added auth headers setup in setUp for both users
  - Migrated API calls (`/api/...`) to use `withHeaders()`
  - Tests without API calls kept comments for consistency

### 32. ✅ `tests/Feature/SidebarConfigTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~8 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $userAuthHeaders = [];` and `$adminAuthHeaders = [];`
  - Set password in user factories
  - Added auth headers setup in setUp for both users
  - Replaced all `$this->actingAs()` with `$this->withHeaders($authHeaders)`

### 33. ✅ `tests/Feature/Api/SimpleApiTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~1 test method
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Set password in user factory
  - Created auth headers in test method
  - Replaced `$this->actingAs($user, 'sanctum')` with `$this->withHeaders($authHeaders)`

### 34. ✅ `tests/Feature/TenantsPerformanceTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~10 test methods (all API calls)
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory
  - Added auth headers setup in setUp
  - Replaced all `$this->actingAs($this->user, 'sanctum')` with `$this->withHeaders($this->authHeaders)`
  - Migrated all API calls (`/api/admin/tenants`, `/api/admin/tenants-kpis`, etc.) to use `withHeaders()`

### 35. ✅ `tests/Feature/ProjectManagementTest.php`
- **Status:** ✅ Completed
- **Methods migrated:** ~1 test method
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Set password in user factory
  - Created auth headers in test method
  - Replaced `$this->actingAs($user)` with `$this->withHeaders($authHeaders)`

### 36. ✅ `tests/Feature/BulkOperationsSimpleTest.php`
- **Status:** ✅ Completed (prepared for migration, no API calls in tests)
- **Methods migrated:** ~0 test methods (no API calls, only service tests)
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Changed `bcrypt('password')` to `Hash::make('password')`
  - Removed `$this->actingAs($this->user, 'api')` from setUp (not needed for service tests)
  - Prepared auth headers for future API calls if needed
  - Added `protected array $authHeaders = [];`
  - Added auth headers setup in setUp
  - Removed `$this->actingAs($this->user, 'sanctum');`
  - Updated all API calls (21 calls) to use `withHeaders($this->authHeaders)`
  - Added `use Illuminate\Support\Facades\Hash;`
  - Added `protected array $authHeaders = [];`
  - Set password in user factory: `'password' => Hash::make('password')`
  - Added auth headers setup in setUp
  - Replaced all `Sanctum::actingAs()` with `$this->withHeaders($this->authHeaders)`
  - Created separate auth headers for member users in test methods
- **Status:** ✅ Completed
- **Methods migrated:** 6 test methods
- **Changes:**
  - Added `use Tests\Helpers\AuthHelper;`
  - Added `protected array $adminAuthHeaders = [];`, `$regularAuthHeaders = [];`, `$otherTenantAuthHeaders = [];`
  - Set password in user factories: `'password' => Hash::make('password')`
  - Added auth headers setup in setUp for all three users
  - Replaced all `$this->actingAs($user, 'sanctum')` with `$this->withHeaders($authHeaders)`

---

## ⏸️ Pending Migrations (Batch 1 - 10-15 files)

### Priority Files:
1. `tests/Feature/Api/Projects/ProjectsContractTest.php` - Similar structure to TasksContractTest
2. `tests/Feature/Api/Documents/DocumentsContractTest.php` - Similar structure
3. `tests/Feature/Api/TaskCommentApiTest.php` - API test
4. `tests/Feature/Dashboard/DashboardApiTest.php` - API test
5. `tests/Feature/Dashboard/AppDashboardApiTest.php` - API test
6. `tests/Feature/TenantIsolationTest.php` - Feature test
7. `tests/Feature/AuthorizationTest.php` - Feature test
8. `tests/Feature/ApiEndpointsTest.php` - API test
9. `tests/Feature/Api/ProjectManagerApiIntegrationTest.php` - Integration test
10. `tests/Feature/ClientsApiIntegrationTest.php` - Integration test
11. `tests/Feature/TasksApiIntegrationTest.php` - Integration test
12. `tests/Feature/ProjectsApiIntegrationTest.php` - Integration test
13. `tests/Feature/NotificationsTest.php` - Feature test
14. `tests/Feature/TaskAssignmentTest.php` - Feature test
15. `tests/Feature/TemplateApiTest.php` - API test

---

## 📋 Migration Pattern

### Before:
```php
use Laravel\Sanctum\Sanctum;

// In test method:
Sanctum::actingAs($this->user);
$response = $this->json('GET', '/api/endpoint');
```

### After:
```php
use Tests\Helpers\AuthHelper;
use Illuminate\Support\Facades\Hash;

// In setUp:
protected array $authHeaders = [];

protected function setUp(): void
{
    // ... existing setup ...
    
    $this->user = User::factory()->create([
        // ... other attributes ...
        'password' => Hash::make('password'), // Required for AuthHelper
    ]);
    
    // Get auth headers for API requests
    $this->authHeaders = AuthHelper::getAuthHeaders($this, $this->user->email, 'password');
}

// In test method:
$response = $this->withHeaders($this->authHeaders)
    ->json('GET', '/api/endpoint');
```

---

## ✅ Verification Checklist

For each migrated file:
- [x] Removed `use Laravel\Sanctum\Sanctum;`
- [x] Added `use Tests\Helpers\AuthHelper;`
- [x] Added `use Illuminate\Support\Facades\Hash;` (if not already present)
- [x] Added `protected array $authHeaders = [];`
- [x] Set password in user factory creation
- [x] Added auth headers setup in setUp()
- [x] Replaced all `Sanctum::actingAs()` calls
- [x] Updated all API requests to use `withHeaders($this->authHeaders)`
- [x] No linter errors
- [ ] Tests pass (to be verified)

---

## 📊 Statistics

- **Batch 1 Files Migrated:** 15/15 (100%) ✅ COMPLETE!
- **Batch 2 Files Migrated:** 20/20 (100%) ✅ COMPLETE!
- **Total Files Migrated:** 36/36 (100%) ✅ ALL API-FOCUSED FILES COMPLETE!
- **Total Methods Migrated:** ~400+ methods
- **Remaining:** Web routes files (intentionally kept as `actingAs()` for session auth)

---

## 🎯 Next Steps

1. Continue migrating remaining files in Batch 1
2. Verify all migrated tests pass
3. Document any issues or edge cases encountered
4. Move to Batch 2 (15-20 more files)

