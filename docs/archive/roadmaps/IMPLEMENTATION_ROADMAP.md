# IMPLEMENTATION ROADMAP

## 🎯 Mục Tiêu
Đưa test suite từ 60% pass rate lên 90%+ pass rate trong 8 tuần

## 📊 Current Status
- **Total Tests**: 2,385
- **Tests Passed**: ~926 (38.8%)
- **Tests Skipped**: 441 (18.5%)
- **Tests Remaining**: ~1,018 (42.7%)

## 🗓️ PHASE 1: CORE INFRASTRUCTURE (Week 1-2)

### Priority: HIGH 🔴

#### 1.1 Testing Infrastructure Setup
- [ ] **Redis Configuration**
  - Setup Redis for testing environment
  - Configure `QUEUE_CONNECTION=redis` in `.env.testing`
  - Mock Redis with array driver for tests
  - **Files**: `tests/Feature/Api/CachingTest.php`

- [ ] **JWT/Sanctum Authentication**
  - Fix JWT token validation issues
  - Configure Sanctum for testing
  - Setup proper authentication middleware
  - **Files**: Multiple auth-related tests

- [ ] **Rate Limiting Headers**
  - Configure rate limiting middleware
  - Add proper headers to responses
  - **Files**: `tests/Feature/Api/RateLimitingTest.php`

#### 1.2 Database Schema Fixes
- [ ] **Missing Migrations**
  - Create `dashboard_metrics` table migration
  - Create `team_members` pivot table migration
  - Add `file_type` column to documents table
  - **Files**: Various model tests

- [ ] **Foreign Key Constraints**
  - Fix User-Tenant relationship constraints
  - Ensure proper cascade deletes
  - **Files**: BackgroundJobsTest, UserRepositoryTest

#### 1.3 Job Property Access (URGENT)
- [ ] **Revert Public Properties**
  - Revert all Job classes from `public` to `protected`
  - Add proper getter methods
  - Update tests to use getters
  - **Files**: All Job classes + BackgroundJobsTest

**Expected Outcome**: Reduce skipped tests to <300, fix core infrastructure issues

## 🗓️ PHASE 2: MISSING DEPENDENCIES (Week 3-4)

### Priority: MEDIUM 🟡

#### 2.1 Missing Factories
- [ ] **InvitationFactory**
  - Create factory for Invitation model
  - **Files**: `tests/Feature/BackgroundJobsTest.php`

- [ ] **FileFactory**
  - Create factory for File model
  - **Files**: `tests/Feature/BackgroundJobsTest.php`

- [ ] **RfiFactory**
  - Create factory for Rfi model
  - **Files**: `tests/Unit/Models/ModelsTest.php`

- [ ] **QcPlanFactory**
  - Create factory for QcPlan model
  - **Files**: `tests/Unit/Models/ModelsTest.php`

- [ ] **QcInspectionFactory**
  - Create factory for QcInspection model
  - **Files**: `tests/Unit/Models/ModelsTest.php`

#### 2.2 Legacy Model Migration
- [ ] **ZenaProject → App\Models\Project**
  - Update all references from ZenaProject to Project
  - **Files**: Multiple API tests

- [ ] **ZenaTask → App\Models\Task**
  - Update all references from ZenaTask to Task
  - **Files**: Task-related tests

- [ ] **ZenaDocument → App\Models\Document**
  - Update all references from ZenaDocument to Document
  - **Files**: Document management tests

- [ ] **Other Legacy Models**
  - ZenaChangeRequest → App\Models\ChangeRequest
  - ZenaRfi → App\Models\Rfi
  - ZenaSubmittal → App\Models\Submittal

**Expected Outcome**: Enable 200+ tests that were skipped due to missing dependencies

## 🗓️ PHASE 3: ADVANCED FEATURES (Week 5-8)

### Priority: LOW 🟢

#### 3.1 Billing System Implementation
- [ ] **BillingController**
  - Implement BillingController with all required methods
  - Add proper authentication and authorization
  - **Files**: `tests/Feature/BillingTest.php`

- [ ] **Billing Routes**
  - Add all billing routes to `routes/web.php` and `routes/api.php`
  - Implement proper middleware
  - **Routes**: `/admin/billing/*`, `/api/admin/billing/*`

- [ ] **Billing Services**
  - Implement BillingService
  - Add subscription management
  - Add invoice generation
  - **Files**: Service classes

#### 3.2 WebSocket/Real-time Features
- [ ] **WebSocket Endpoints**
  - Implement WebSocket connection endpoints
  - Add real-time notification system
  - **Files**: `tests/Feature/Api/WebSocketTest.php`

- [ ] **Real-time Notifications**
  - Implement notification broadcasting
  - Add WebSocket event handling
  - **Files**: `tests/Feature/Api/RealTimeNotificationsTest.php`

#### 3.3 Advanced Security
- [ ] **AdvancedSecurityController**
  - Implement AdvancedSecurityController
  - Add security monitoring features
  - **Files**: `tests/Feature/AdvancedSecurityTest.php`

- [ ] **Security Features**
  - Add advanced security monitoring
  - Implement threat detection
  - **Files**: Security-related tests

**Expected Outcome**: Enable remaining 300+ tests, achieve 90%+ pass rate

## 📋 TASK ASSIGNMENT

### Week 1-2: Core Infrastructure
- **Developer 1**: Testing Infrastructure Setup (Redis, JWT, Rate Limiting)
- **Developer 2**: Database Schema Fixes (Migrations, FK Constraints)
- **Developer 3**: Job Property Access (Revert + Getters)

### Week 3-4: Missing Dependencies
- **Developer 1**: Missing Factories (Invitation, File, Rfi, QcPlan, QcInspection)
- **Developer 2**: Legacy Model Migration (ZenaProject, ZenaTask, ZenaDocument)
- **Developer 3**: Other Legacy Models (ZenaChangeRequest, ZenaRfi, ZenaSubmittal)

### Week 5-8: Advanced Features
- **Developer 1**: Billing System Implementation
- **Developer 2**: WebSocket/Real-time Features
- **Developer 3**: Advanced Security Features

## 🎯 SUCCESS METRICS

### Week 2 Target
- **Tests Passed**: 1,200+ (50%+)
- **Tests Skipped**: <300 (12.5%)
- **Infrastructure**: Redis, JWT, Rate Limiting working

### Week 4 Target
- **Tests Passed**: 1,800+ (75%+)
- **Tests Skipped**: <200 (8.5%)
- **Dependencies**: All factories and models available

### Week 8 Target
- **Tests Passed**: 2,100+ (90%+)
- **Tests Skipped**: <100 (4%)
- **Features**: Billing, WebSocket, Advanced Security implemented

## 🔄 TRACKING

### Daily Standups
- Review progress on current phase
- Identify blockers
- Adjust priorities if needed

### Weekly Reviews
- Measure success metrics
- Update roadmap based on progress
- Plan next week's tasks

### Monthly Assessments
- Full test suite run
- Performance analysis
- Architecture review

## 📝 NOTES

### Critical Issues to Address
1. **Job Property Access**: Must be fixed in Week 1 to avoid breaking encapsulation
2. **Authentication**: Core to many tests, must be working by Week 2
3. **Database Schema**: Blocks many model tests, priority in Week 1-2

### Risk Mitigation
1. **Parallel Development**: Multiple developers working on different phases
2. **Incremental Testing**: Test each phase before moving to next
3. **Rollback Plan**: Keep backups of working code at each phase

### Dependencies
1. **Phase 1** must be completed before **Phase 2**
2. **Phase 2** can be partially completed while **Phase 3** starts
3. **Phase 3** is independent and can be done in parallel

---

**Last Updated**: 2025-10-13
**Next Review**: 2025-10-20
**Owner**: Development Team
**Status**: Active