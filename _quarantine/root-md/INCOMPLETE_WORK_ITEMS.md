# 📋 DANH SÁCH CÁC HẠNG MỤC CÔNG VIỆC CHƯA HOÀN THÀNH

**Ngày cập nhật:** 2025-01-19  
**Mục đích:** Tổng hợp tất cả các công việc còn dang dở cần thực hiện tiếp

---

## 🎯 TỔNG QUAN

### Phân loại theo mức độ ưu tiên:
- 🔴 **HIGH PRIORITY** - Phải làm ngay (blocking)
- 🟡 **MEDIUM PRIORITY** - Nên làm sớm (important)
- 🟢 **LOW PRIORITY** - Có thể làm sau (nice to have)

### Phân loại theo domain:
- **Frontend** - React SPA features
- **Backend** - API & Services
- **Testing** - Test coverage & fixes
- **Infrastructure** - Core components & architecture
- **Documentation** - Docs updates

---

## 🔴 HIGH PRIORITY (Must Have)

### 1. Frontend Features - Missing Routes & Components

#### 1.1 Task Detail Page (Full Functionality)
**Status:** ⚠️ Partial - Route exists but incomplete  
**Location:** `frontend/src/pages/tasks/`  
**Missing:**
- [ ] Task comments UI integration (Backend API ready)
- [ ] Task attachments UI (Backend API pending)
- [ ] Task time tracking UI
- [ ] Task detail page component verification

**Files:**
- `frontend/src/pages/tasks/TaskDetailPage.tsx` (needs completion)
- `frontend/src/components/tasks/TaskComments.tsx` (needs creation)
- `frontend/src/components/tasks/TaskAttachments.tsx` (needs creation)

**Estimated Effort:** 6-8 hours

#### 1.2 Missing Routes for Existing Features
**Status:** ⚠️ Components exist but no routes  
**Missing Routes:**
- [ ] Change Requests (`/app/change-requests`) - Component exists
- [ ] Templates (`/app/templates`) - API exists
- [ ] Quotes (`/app/quotes`) - API exists
- [ ] Clients (`/app/clients`) - API exists
- [ ] Gantt Chart (`/app/gantt`) - Component exists
- [ ] QC Module (`/app/qc`) - Component exists

**Files to Update:**
- `frontend/src/router/index.tsx` (add routes)
- Create page components if missing

**Estimated Effort:** 4-6 hours

#### 1.3 Universal Page Frame Components
**Status:** ❌ Not Started  
**Missing Components:**
- [ ] **KPI Strip Component** (`frontend/src/components/shared/KpiStrip.tsx`)
  - Reusable KPI strip for all pages
  - Currently only Dashboard has KPI cards
  - Needed for: Projects, Tasks, Clients, Quotes, Templates pages
  
- [ ] **Global Alert Bar** (`frontend/src/components/shared/AlertBar.tsx`)
  - Global alert bar for all pages
  - Currently only Dashboard has alerts
  - Should display system-wide alerts
  
- [ ] **Activity Feed Component** (`frontend/src/components/shared/ActivityFeed.tsx`)
  - Reusable activity feed for all pages
  - Currently only Dashboard has activity feed
  - Needed for: Projects, Tasks, Documents pages

**Estimated Effort:** 12-18 hours total

#### 1.4 KPI APIs for Pages
**Status:** ❌ Not Started  
**Missing APIs:**
- [ ] Projects Page KPIs: Total, Active, Completed, Overdue
- [ ] Tasks Page KPIs: Total, Pending, In Progress, Completed
- [ ] Clients Page KPIs: Total, Active, New, Revenue
- [ ] Quotes Page KPIs: Total, Pending, Accepted, Rejected
- [ ] Templates Page KPIs: Total, Active, Usage Count

**Backend Files:**
- `app/Http/Controllers/Api/V1/App/ProjectsController.php` (add KPI method)
- `app/Http/Controllers/Api/V1/App/TasksController.php` (add KPI method)
- `app/Http/Controllers/Api/V1/App/ClientsController.php` (add KPI method)
- `app/Http/Controllers/Api/V1/App/QuotesController.php` (add KPI method)
- `app/Http/Controllers/Api/V1/App/TemplatesController.php` (add KPI method)

**Estimated Effort:** 8-10 hours

### 2. Backend Features - Phase 2 Completion

#### 2.1 File Attachments System
**Status:** ⏳ Pending  
**Progress:** 0%  
**Missing:**
- [ ] Database migration for file attachments table
- [ ] FileAttachment model
- [ ] FileAttachmentService
- [ ] FileAttachmentController (API endpoints)
- [ ] File storage integration
- [ ] File upload validation
- [ ] File download endpoints

**Files to Create:**
- `database/migrations/YYYY_MM_DD_create_file_attachments_table.php`
- `app/Models/FileAttachment.php`
- `app/Services/FileAttachmentService.php`
- `app/Http/Controllers/Unified/FileAttachmentController.php`

**Estimated Effort:** 8-10 hours

#### 2.2 Real-time Updates (WebSocket)
**Status:** ⏳ Pending  
**Progress:** 0%  
**Missing:**
- [ ] WebSocket server setup (Laravel Echo + Pusher/Socket.io)
- [ ] Real-time notification system
- [ ] Live dashboard updates
- [ ] Live task updates
- [ ] Live project updates
- [ ] Frontend WebSocket client integration

**Estimated Effort:** 12-16 hours

#### 2.3 Comments Frontend Integration
**Status:** ⚠️ Backend Complete, Frontend Pending  
**Progress:** Backend 100%, Frontend 0%  
**Missing:**
- [ ] Task comments UI component
- [ ] Comment form component
- [ ] Comment thread display
- [ ] Comment editing/deletion UI
- [ ] Real-time comment updates

**Files:**
- `frontend/src/components/tasks/TaskComments.tsx` (needs creation)
- `frontend/src/components/tasks/CommentForm.tsx` (needs creation)
- `frontend/src/components/tasks/CommentThread.tsx` (needs creation)

**Estimated Effort:** 6-8 hours

### 3. Testing - Test Failures & Coverage

#### 3.1 Test Failures Resolution
**Status:** ⚠️ Multiple failures across domains  
**Failure Summary:**
- **Tasks Domain:** 82 failed, 58 passed (58.6% failure rate)
- **Documents Domain:** 55 failed, 55 passed (50% failure rate)
- **Dashboard Domain:** 132 failed, 27 passed (83% failure rate)
- **Total:** ~269+ failed tests

**Common Issues:**
- [ ] Database schema issues (missing columns, NOT NULL constraints)
- [ ] Seed method issues (tests not using seed methods)
- [ ] Model relationship issues
- [ ] Authentication/authorization issues
- [ ] API response format issues

**Priority:** 🔴 CRITICAL - Blocking CI/CD pipeline

**Estimated Effort:** 20-30 hours

#### 3.2 Test Coverage Gaps
**Status:** ⚠️ Partial coverage  
**Missing:**
- [ ] Unit tests for new components
- [ ] Integration tests for API endpoints
- [ ] E2E tests for critical paths (Playwright)
- [ ] Component tests for React components

**Estimated Effort:** 15-20 hours

---

## 🟡 MEDIUM PRIORITY (Should Have)

### 4. Frontend Features - Enhancements

#### 4.1 Reports & Analytics Pages
**Status:** ⚠️ Routes exist but components incomplete  
**Missing:**
- [ ] Reports page component verification
- [ ] Report generation functionality
- [ ] Report export functionality
- [ ] Analytics page component verification
- [ ] Charts and visualizations
- [ ] Data export functionality

**Estimated Effort:** 10-12 hours

#### 4.2 User Customization Features
**Status:** ⚠️ Partial - Infrastructure exists  
**Missing:**
- [ ] Views preferences (grid/list/compact) with full persistence
- [ ] Density preferences with full persistence
- [ ] KPI selection per page with persistence
- [ ] Apply preferences globally and per-page

**Estimated Effort:** 6-8 hours

#### 4.3 Smart Tools
**Status:** ⚠️ Basic features exist  
**Missing:**
- [ ] Intelligent search with suggestions and recent searches
- [ ] Smart filters with presets and suggestions
- [ ] One-tap focus presets (filters + sort combinations)

**Estimated Effort:** 8-10 hours

#### 4.4 Mobile-First Enhancements
**Status:** ⚠️ Partial - Responsive design exists  
**Missing:**
- [ ] Floating Action Button (FAB) for mobile
- [ ] Hamburger menu for mobile header
- [ ] Mobile-optimized navigation
- [ ] Swipe gestures for mobile

**Estimated Effort:** 6-8 hours

#### 4.5 Error Handling & Loading States
**Status:** ⚠️ Partial  
**Missing:**
- [ ] Error boundaries for React components
- [ ] Error pages (404, 500, etc.)
- [ ] Error recovery mechanisms
- [ ] Skeleton loaders for all pages
- [ ] Loading spinners consistency
- [ ] Progressive loading

**Estimated Effort:** 6-8 hours

### 5. Backend Features - API Enhancements

#### 5.1 Activity APIs for Pages
**Status:** ❌ Not Started  
**Missing:**
- [ ] Activity API for Projects page
- [ ] Activity API for Tasks page
- [ ] Activity API for Documents page
- [ ] Activity API for Clients page
- [ ] Activity API for Quotes page

**Estimated Effort:** 6-8 hours

---

## 🟢 LOW PRIORITY (Nice to Have)

### 6. Accessibility Improvements
**Status:** ⚠️ Partial  
**Missing:**
- [ ] Complete keyboard navigation audit
- [ ] Focus management improvements (focus traps, focus indicators)
- [ ] Screen reader support improvements
- [ ] WCAG 2.1 AA compliance audit
- [ ] ARIA labels completeness

**Estimated Effort:** 4-6 hours

### 7. Performance Optimization
**Status:** ⚠️ Performance budgets met but can improve  
**Missing:**
- [ ] Code splitting optimization
- [ ] Lazy loading improvements
- [ ] Image optimization
- [ ] Bundle size optimization

**Estimated Effort:** 4-6 hours

### 8. Documentation Updates
**Status:** ⚠️ Most docs complete  
**Missing:**
- [ ] Component documentation updates
- [ ] API documentation updates (if new endpoints added)
- [ ] User guide updates

**Estimated Effort:** 2-4 hours

---

## 📊 SUMMARY BY CATEGORY

### Frontend (React SPA)
- **High Priority:** 4 items (28-38 hours)
- **Medium Priority:** 5 items (36-46 hours)
- **Low Priority:** 2 items (8-12 hours)
- **Total:** ~72-96 hours

### Backend (Laravel API)
- **High Priority:** 3 items (26-34 hours)
- **Medium Priority:** 1 item (6-8 hours)
- **Total:** ~32-42 hours

### Testing
- **High Priority:** 2 items (35-50 hours)
- **Total:** ~35-50 hours

### Infrastructure
- **High Priority:** 1 item (12-18 hours)
- **Total:** ~12-18 hours

---

## 🎯 RECOMMENDED IMPLEMENTATION ORDER

### Phase 1: Critical Fixes (Week 1-2)
1. ✅ Fix test failures (HIGH PRIORITY - Blocking CI/CD)
2. ✅ Complete Universal Page Frame components (KPI Strip, Alert Bar, Activity Feed)
3. ✅ Implement KPI APIs for all pages

### Phase 2: Core Features (Week 3-4)
4. ✅ Complete Task Detail Page (comments, attachments, time tracking)
5. ✅ Add missing routes (Change Requests, Templates, Quotes, Clients, Gantt, QC)
6. ✅ Implement File Attachments system

### Phase 3: Enhancements (Week 5-6)
7. ✅ Complete Reports & Analytics pages
8. ✅ Implement User Customization features
9. ✅ Add Smart Tools features

### Phase 4: Polish (Week 7-8)
10. ✅ Mobile-first enhancements
11. ✅ Error handling & loading states
12. ✅ Accessibility improvements
13. ✅ Performance optimization

### Phase 5: Advanced Features (Future)
14. ⏳ Real-time Updates (WebSocket)
15. ⏳ Advanced analytics
16. ⏳ Additional integrations

---

## 📝 NOTES

### Completed Recently
- ✅ Dashboard rebuild complete
- ✅ Core Infrastructure complete
- ✅ Auth Domain test organization complete
- ✅ Projects Domain test organization complete
- ✅ Tasks Domain test organization complete
- ✅ Documents Domain test organization complete
- ✅ Users Domain test organization complete
- ✅ Dashboard Domain test organization complete
- ✅ UI/UX Implementation (HeaderShell, tokens, components)

### In Progress
- 🟡 Frontend E2E Organization (Codex)
- 🟡 Core Infrastructure Review (Codex)

### Blockers
- ⚠️ Test failures blocking CI/CD pipeline
- ⚠️ Missing KPI/Activity APIs blocking page completion

---

## 🔄 NEXT ACTIONS

### Immediate (This Week)
1. [ ] Fix critical test failures (Tasks, Documents, Dashboard domains)
2. [ ] Create KPI Strip component
3. [ ] Create Global Alert Bar component
4. [ ] Create Activity Feed component

### Short-term (Next Week)
5. [ ] Implement KPI APIs for Projects, Tasks, Clients, Quotes, Templates
6. [ ] Complete Task Detail Page (comments UI)
7. [ ] Add missing routes for existing features

### Medium-term (Next 2 Weeks)
8. [ ] Implement File Attachments system
9. [ ] Complete Reports & Analytics pages
10. [ ] Implement User Customization features

---

**Last Updated:** 2025-01-19  
**Next Review:** Weekly

