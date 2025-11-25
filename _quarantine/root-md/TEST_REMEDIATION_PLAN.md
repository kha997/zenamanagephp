# TEST REMEDIATION PLAN - ZENAMANAGE

## 📊 INVENTORY SKIP/WARN MATRIX

| File | Test Name | Lý do Skip/Warn | Hành động Fix | Priority |
|------|-----------|-----------------|---------------|----------|
| **A. MISSING METHODS/CONTROLLERS** |
| `ProjectManagerControllerTest.php` | `test_get_project_timeline_with_valid_project_manager` | Method getProjectTimeline does not exist in controller | Implement getProjectTimeline method + route | HIGH |
| `ProjectManagerControllerTest.php` | `test_get_project_timeline_without_project_manager_role` | Method getProjectTimeline does not exist in controller | Implement getProjectTimeline method + route | HIGH |
| `ProjectManagerControllerTest.php` | `test_get_project_timeline_with_database_error` | Method getProjectTimeline does not exist in controller | Implement getProjectTimeline method + route | HIGH |
| `ProjectManagerControllerTest.php` | `test_get_project_timeline_without_authentication` | Method getProjectTimeline does not exist in controller | Implement getProjectTimeline method + route | HIGH |
| `ProjectManagerControllerTest.php` | `test_get_project_timeline_without_project_manager_role_duplicate` | Method getProjectTimeline does not exist in controller | Implement getProjectTimeline method + route | HIGH |
| `ProjectManagerControllerTest.php` | `test_get_project_stats_with_database_error` | Skipping database error test for now | Implement error handling + DB error simulation | MEDIUM |
| `ProjectManagerControllerTest.php` | `test_get_stats_error_handling` | Skipping error handling test for now | Implement error handling + DB error simulation | MEDIUM |
| **B. MISSING MIGRATIONS/TABLES/FIELDS** |
| `DashboardRoleBasedServiceTest.php` | `it_can_get_role_based_dashboard` | Missing dashboard_metrics table migration | ✅ FIXED - Table created | COMPLETED |
| `DashboardServiceTest.php` | Multiple tests | Missing code field in projects table | ✅ FIXED - Field added | COMPLETED |
| `ModelsTest.php` | `team_model_belongs_to_many_members` | Missing team_members pivot table migration | Create team_members pivot table | HIGH |
| `ModelsTest.php` | `rfi_model_belongs_to_project` | Missing RfiFactory | Create RfiFactory | MEDIUM |
| `ModelsTest.php` | `rfi_model_belongs_to_creator` | Missing RfiFactory | Create RfiFactory | MEDIUM |
| `ModelsTest.php` | `qc_plan_model_belongs_to_project` | Missing QcPlanFactory | Create QcPlanFactory | MEDIUM |
| `ModelsTest.php` | `qc_plan_model_belongs_to_creator` | Missing QcPlanFactory | Create QcPlanFactory | MEDIUM |
| `ModelsTest.php` | `qc_inspection_model_belongs_to_project` | Missing QcInspectionFactory and no direct project relationship | Create QcInspectionFactory + fix relationship | MEDIUM |
| `ModelsTest.php` | `qc_inspection_model_belongs_to_inspector` | Missing QcInspectionFactory | Create QcInspectionFactory | MEDIUM |
| `TemplateTest.php` | All tests | Missing name field in templates table | Add name field to templates table | MEDIUM |
| **C. FOREIGN KEY CONSTRAINT ISSUES** |
| `ProjectRepositoryTest.php` | All 25+ tests | Foreign key constraint issues with tenant creation | Fix tenant creation + FK constraints | HIGH |
| **D. DELIBERATELY SKIPPED TESTS** |
| `SidebarServiceTest.php` | Multiple tests | Auth setup issue in test environment | Fix auth setup in tests | LOW |
| `SidebarServiceTest.php` | Multiple tests | Protected method test - using public method instead | Refactor to test public methods | LOW |
| `SecurityTest.php` | Multiple tests | Database credentials test skipped - not critical | Implement security tests | LOW |
| `SecurityTest.php` | Multiple tests | File upload security test skipped - route not implemented | Implement file upload routes + security | LOW |
| `SecurityTest.php` | Multiple tests | SQL injection prevention test skipped - route not implemented | Implement SQL injection prevention | LOW |
| `AuthServiceTest.php` | `register_fails_with_existing_email` | Skipping due to transaction conflicts in AuthService | Fix transaction handling | MEDIUM |

## 🎯 REMEDIATION PLAN BY CATEGORY

### **A. MISSING METHODS/CONTROLLERS** (Priority: HIGH)

#### **A1. ProjectManagerController@getProjectTimeline**
- **Files to create/modify:**
  - `app/Http/Controllers/Unified/ProjectManagementController.php` - Add getProjectTimeline method
  - `routes/api.php` - Add route for getProjectTimeline
  - `app/Services/ProjectManagementService.php` - Add getProjectTimeline method
- **Implementation requirements:**
  - Return JSON response with project timeline data
  - Include project_id, timeline items, status code
  - Check role PM, handle unauthenticated case
  - Handle DB error cases (real errors, not mocked)
- **Test contract:**
  ```php
  // Expected response structure
  {
    "success": true,
    "data": {
      "project_id": "string",
      "timeline": [
        {
          "id": "string",
          "title": "string", 
          "date": "datetime",
          "type": "milestone|task|event",
          "status": "completed|pending|overdue"
        }
      ]
    }
  }
  ```

#### **A2. Error Handling Implementation**
- **Files to modify:**
  - `app/Http/Controllers/Unified/ProjectManagementController.php` - Add error handling
  - `app/Services/ProjectManagementService.php` - Add error simulation methods
- **Implementation requirements:**
  - Real database error simulation (connection timeout, constraint violations)
  - Proper error response format with error.id
  - Transaction rollback handling
  - Retry logic for transient errors

### **B. MISSING MIGRATIONS/TABLES/FIELDS** (Priority: HIGH-MEDIUM)

#### **B1. Team Members Pivot Table** (Priority: HIGH)
- **Migration:** `create_team_members_pivot_table.php`
- **Schema:**
  ```php
  Schema::create('team_members', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('team_id');
      $table->string('user_id');
      $table->string('role')->default('member');
      $table->boolean('is_active')->default(true);
      $table->timestamps();
      
      $table->foreign('team_id')->references('id')->on('teams');
      $table->foreign('user_id')->references('id')->on('users');
      $table->unique(['team_id', 'user_id']);
  });
  ```

#### **B2. Missing Factories** (Priority: MEDIUM)
- **Files to create:**
  - `database/factories/RfiFactory.php`
  - `database/factories/QcPlanFactory.php` 
  - `database/factories/QcInspectionFactory.php`
- **Requirements:**
  - Generate realistic test data
  - Include all required relationships
  - Support different states (active, completed, etc.)

#### **B3. Templates Table Name Field** (Priority: MEDIUM)
- **Migration:** `add_name_field_to_templates_table.php`
- **Schema:**
  ```php
  Schema::table('templates', function (Blueprint $table) {
      if (!Schema::hasColumn('templates', 'name')) {
          $table->string('name')->after('id');
      }
  });
  ```

### **C. FOREIGN KEY CONSTRAINT ISSUES** (Priority: HIGH)

#### **C1. ProjectRepositoryTest Fix**
- **Root cause:** Tenant creation failing due to FK constraints
- **Files to modify:**
  - `tests/Unit/Repositories/ProjectRepositoryTest.php` - Fix setUp method
  - `database/factories/TenantFactory.php` - Ensure proper tenant creation
  - `database/factories/UserFactory.php` - Fix tenant_id assignment
- **Solution:**
  - Use RefreshDatabase trait properly
  - Create tenants before users
  - Ensure FK constraints are satisfied
  - Add proper error handling for constraint violations

### **D. DELIBERATELY SKIPPED TESTS** (Priority: LOW-MEDIUM)

#### **D1. Auth Setup Issues**
- **Files to modify:**
  - `tests/Unit/SidebarServiceTest.php` - Fix auth setup
  - `tests/TestCase.php` - Improve auth mocking
- **Solution:**
  - Proper auth facade mocking
  - Session handling in tests
  - User authentication state management

#### **D2. Security Tests Implementation**
- **Files to create/modify:**
  - `tests/Unit/SecurityTest.php` - Implement security tests
  - `app/Http/Middleware/SecurityMiddleware.php` - Add security middleware
- **Requirements:**
  - SQL injection prevention tests
  - File upload security tests
  - XSS prevention tests
  - CSRF protection tests

## 📋 DEFINITION OF DONE (DoD)

### **General DoD:**
- [ ] All tests run without skip/warn
- [ ] 0 skipped tests in target files
- [ ] 0 warnings in test output
- [ ] All migrations are idempotent and SQLite-compatible
- [ ] All factories generate realistic data
- [ ] All controllers return proper JSON responses
- [ ] All error handling includes error.id correlation
- [ ] All tests pass in both SQLite (testing) and MySQL (production)

### **DoD for Missing Methods/Controllers:**
- [ ] Method implemented with proper error handling
- [ ] Route registered in api.php
- [ ] Service layer method implemented
- [ ] All test cases pass (authenticated, unauthenticated, error)
- [ ] Response format matches test expectations
- [ ] Role-based access control implemented

### **DoD for Missing Migrations/Tables:**
- [ ] Migration created and tested
- [ ] Table/field exists in both SQLite and MySQL
- [ ] Foreign key constraints properly defined
- [ ] Indexes created for performance
- [ ] Factory updated to include new fields
- [ ] Model updated with fillable attributes

### **DoD for Foreign Key Issues:**
- [ ] All FK constraints satisfied
- [ ] Test data creation order fixed
- [ ] RefreshDatabase trait working properly
- [ ] No constraint violation errors
- [ ] All repository tests pass

### **DoD for Deliberately Skipped Tests:**
- [ ] Skip statements removed
- [ ] Test logic implemented
- [ ] Auth setup working
- [ ] Security tests functional
- [ ] All assertions passing

## ⚠️ RISKS & ROLLBACK

### **Risks:**
1. **Migration conflicts:** Multiple migrations modifying same table
2. **FK constraint violations:** Circular dependencies between tables
3. **Test environment differences:** SQLite vs MySQL behavior
4. **Performance impact:** New indexes and constraints
5. **Data integrity:** Existing data compatibility

### **Rollback Strategy:**
1. **Migration rollback:** `php artisan migrate:rollback --step=N`
2. **Test environment reset:** `php artisan migrate:fresh --env=testing`
3. **Factory rollback:** Revert factory changes
4. **Controller rollback:** Remove new methods, restore original
5. **Route rollback:** Remove new routes from api.php

### **Environment Flags:**
- `TESTING_ENV=true` - Use SQLite for testing
- `PRODUCTION_ENV=true` - Use MySQL for production
- `SKIP_MIGRATIONS=false` - Enable all migrations
- `ENABLE_SECURITY_TESTS=true` - Enable security test suite

## 🚀 EXECUTION PLAN

### **Phase 1: Critical Infrastructure (HIGH Priority)**
1. ✅ **COMPLETED:** Dashboard metrics table + projects code field
2. **NEXT:** Implement ProjectManagerController@getProjectTimeline
3. **NEXT:** Fix ProjectRepositoryTest FK constraints
4. **NEXT:** Create team_members pivot table

### **Phase 2: Missing Factories & Models (MEDIUM Priority)**
1. Create RfiFactory, QcPlanFactory, QcInspectionFactory
2. Add name field to templates table
3. Fix QcInspection project relationship

### **Phase 3: Error Handling & Security (MEDIUM Priority)**
1. Implement error handling in ProjectManagerController
2. Add database error simulation
3. Implement security tests

### **Phase 4: Auth & Sidebar Tests (LOW Priority)**
1. Fix auth setup in SidebarServiceTest
2. Refactor protected method tests
3. Implement remaining security tests

## 📊 SUCCESS METRICS

### **Before Remediation:**
- ❌ 183 tests skipped
- ❌ 13 tests failed
- ❌ Multiple WARN messages
- ❌ Missing methods/controllers
- ❌ FK constraint issues

### **After Remediation (Target):**
- ✅ 0 tests skipped
- ✅ 0 tests failed  
- ✅ 0 WARN messages
- ✅ All methods implemented
- ✅ All FK constraints working
- ✅ All tests passing

## 🔧 VERIFICATION COMMANDS

```bash
# Full test suite verification
php artisan migrate:fresh --env=testing
php artisan test --testdox

# Specific test verification
php artisan test --testsuite=Unit --filter="ProjectManagerController"
php artisan test --testsuite=Unit --filter="Dashboard"
php artisan test --testsuite=Unit --filter="ProjectRepository"

# Migration verification
php artisan migrate:status
php artisan migrate:fresh --seed

# Performance verification
php artisan test --testsuite=Unit --stop-on-failure
```

---

**Status:** Ready for execution
**Next Action:** Begin Phase 1 - Implement ProjectManagerController@getProjectTimeline
**Estimated Time:** 2-3 hours for Phase 1 completion
