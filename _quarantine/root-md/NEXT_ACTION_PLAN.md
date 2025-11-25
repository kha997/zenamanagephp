# 🎯 KẾ HOẠCH HÀNH ĐỘNG TIẾP THEO - ZENAMANAGE

**Ngày tạo:** 2025-01-19  
**Mục đích:** Kế hoạch chi tiết và actionable cho công việc tiếp theo dựa trên tổng hợp các plan hiện tại  
**Status:** 📋 Ready to Execute

---

## 📊 TỔNG QUAN TÌNH HÌNH HIỆN TẠI

### ✅ Đã Hoàn Thành
- ✅ **Phase 1:** Authentication Pages + Dashboard rebuild
- ✅ **UI/UX Foundation:** P0-P6 hoàn thành (Tokens, Theming, HeaderShell, Primitives, Container, Tests)
- ✅ **Core Infrastructure:** Test organization, E2E tests passing (4/4 smoke tests)
- ✅ **Documentation:** API contracts và component breakdowns cho Projects module

### ✅ Đã Có Sẵn (Không Cần Tạo Mới)
- ✅ **Universal Page Frame Components:** KpiStrip.tsx, AlertBar.tsx, ActivityFeed.tsx đã tồn tại
- ✅ **KPI APIs:** Tất cả KPI APIs đã có trong routes/api_v1.php và controllers

### 📋 Đang Chờ Thực Hiện
- 🔴 **CRITICAL:** Tích hợp Universal Page Frame Components vào Projects pages
- 🔴 **CRITICAL:** Kết nối KPI APIs với KPI Strip component trong Projects pages
- 🔴 **CRITICAL:** Hoàn thiện Projects Module pages với Apple-style UI
- 🟡 **HIGH:** Tasks Module rebuild (4 pages)
- 🟡 **HIGH:** UI/UX P7 Adoption
- 🟡 **HIGH:** Missing Routes
- 🟡 **HIGH:** Task Detail Page completion

---

## 🎯 PHÂN TÍCH DEPENDENCIES & BLOCKERS

### Dependency Graph (Updated)
```
✅ Universal Page Frame Components (ĐÃ CÓ)
✅ KPI APIs for Pages (ĐÃ CÓ)
    ↓
🔴 Tích hợp Components + APIs vào Projects Pages
    ↓
🔴 Hoàn thiện Projects Pages với Apple-style UI
    ↓
🟡 Tasks Module Pages (depends on Projects)
    ↓
🟡 UI/UX P7 Adoption (apply to new pages)
```

### Blockers Hiện Tại
- ✅ **Không có blocker nghiêm trọng** - Tất cả dependencies đã sẵn sàng
- ⚠️ **Test failures** (~269+ tests) - Không blocking nhưng cần fix sớm
- ✅ **Components & APIs đã có** - Chỉ cần tích hợp và hoàn thiện

---

## 📅 KẾ HOẠCH THEO TUẦN (3 TUẦN - Updated)

### 🔴 TUẦN 1: Integration & Verification (CRITICAL)

**Mục tiêu:** Verify và tích hợp Universal Page Frame Components + KPI APIs vào Projects pages

#### Day 1-2: Verify Existing Components & APIs (4-6 hours)
**Priority:** 🔴 CRITICAL - Verify trước khi tích hợp

**Tasks:**
1. **Verify Universal Page Frame Components**
   - [ ] Review `frontend/src/components/shared/KpiStrip.tsx` - Verify props interface, loading/error states
   - [ ] Review `frontend/src/components/shared/AlertBar.tsx` - Verify alert types, dismiss functionality
   - [ ] Review `frontend/src/components/shared/ActivityFeed.tsx` - Verify pagination, filtering
   - [ ] Test components với sample data
   - [ ] Verify Apple-style tokens usage
   - **Estimated:** 2-3 hours

2. **Verify KPI APIs**
   - [ ] Test `/api/v1/app/projects/kpis` endpoint
   - [ ] Test `/api/v1/app/tasks/kpis` endpoint
   - [ ] Test `/api/v1/app/clients/kpis` endpoint
   - [ ] Test `/api/v1/app/quotes/kpis` endpoint
   - [ ] Test `/api/v1/app/templates/kpis` endpoint
   - [ ] Verify response format matches component expectations
   - [ ] Verify tenant isolation
   - [ ] Verify caching (60s)
   - [ ] Performance check (< 300ms p95)
   - **Estimated:** 2-3 hours

**Deliverables:**
- ✅ Verification report cho components
- ✅ Verification report cho APIs
- ✅ Integration readiness checklist

#### Day 3-4: Integrate Components vào Projects Index Page (6-8 hours)
**Priority:** 🔴 CRITICAL - First integration

**Tasks:**
1. **Integrate KPI Strip vào Projects Index Page**
   - [ ] Update `resources/views/app/projects/index.blade.php`
   - [ ] Replace placeholder `@section('kpi-strip')` với actual KPI Strip component
   - [ ] Connect to `/api/v1/app/projects/kpis` endpoint
   - [ ] Transform API response to match KpiStrip props format
   - [ ] Handle loading state
   - [ ] Handle error state
   - [ ] Test integration
   - **Estimated:** 3-4 hours

2. **Integrate Activity Feed vào Projects Index Page**
   - [ ] Add `@section('activity')` với Activity Feed component
   - [ ] Connect to `/api/v1/app/projects/activity` endpoint (if exists)
   - [ ] Transform API response to match ActivityFeed props format
   - [ ] Handle loading/error states
   - [ ] Test integration
   - **Estimated:** 2-3 hours

3. **Verify Integration**
   - [ ] Test page load với real data
   - [ ] Test error scenarios
   - [ ] Test loading states
   - [ ] Verify mobile responsive
   - [ ] Performance check (< 500ms p95)
   - **Estimated:** 1 hour

**Deliverables:**
- ✅ Projects Index Page với KPI Strip và Activity Feed integrated
- ✅ Integration tests passing
- ✅ Performance benchmarks met

---

### 🔴 TUẦN 2-3: Projects Module Rebuild (CRITICAL)

**Mục tiêu:** Rebuild 4 Projects pages với Universal Page Frame và Apple-style UI

#### Week 2: Projects List & Create Pages

**Day 1-2: Projects Index Page** (`projects/index.blade.php`)
**Priority:** 🔴 CRITICAL
**Estimated:** 8-10 hours

**Tasks:**
- [ ] Review `PROJECTS_API_CONTRACT.md` và `PROJECTS_COMPONENT_BREAKDOWN.md`
- [ ] Create page với Universal Page Frame structure:
  - [ ] Header (auto from layout)
  - [ ] Primary Navigator (auto from layout)
  - [ ] KPI Strip (use Projects KPIs API)
  - [ ] Alert Bar (if needed)
  - [ ] Page Content:
    - [ ] Smart Filters component ✅ (already exists)
    - [ ] Quick Actions component ✅ (already exists)
    - [ ] Projects table/list với pagination
    - [ ] Search với debounce
  - [ ] Activity Section (use Activity Feed component)
- [ ] Apply Apple-style UI:
  - [ ] Wrap with Container
  - [ ] Use Card components
  - [ ] Use Button/Input primitives
  - [ ] Use tokens (no hardcoded colors)
- [ ] Mobile responsive
- [ ] Accessibility (ARIA labels, keyboard navigation)
- [ ] Error handling & loading states
- [ ] E2E test

**Deliverables:**
- ✅ `projects/index.blade.php` hoàn chỉnh
- ✅ E2E test passing
- ✅ Performance < 500ms p95

**Day 3-4: Projects Create Page** (`projects/create.blade.php`)
**Priority:** 🔴 CRITICAL
**Estimated:** 6-8 hours

**Tasks:**
- [ ] Create page với Universal Page Frame structure
- [ ] Form với validation:
  - [ ] Project name, description
  - [ ] Client selection
  - [ ] Start/end dates
  - [ ] Budget
  - [ ] Status
- [ ] Apply Apple-style UI (Container, Card, Button/Input)
- [ ] Form validation (client-side + server-side)
- [ ] Error handling
- [ ] Success redirect
- [ ] Mobile responsive
- [ ] E2E test

**Deliverables:**
- ✅ `projects/create.blade.php` hoàn chỉnh
- ✅ E2E test passing

#### Week 3: Projects Detail & Edit Pages

**Day 1-2: Projects Show Page** (`projects/show.blade.php`)
**Priority:** 🔴 CRITICAL
**Estimated:** 8-10 hours

**Tasks:**
- [ ] Create page với Universal Page Frame structure
- [ ] Detail view với tabs:
  - [ ] Overview tab
  - [ ] Tasks tab
  - [ ] Documents tab
  - [ ] Team tab
  - [ ] Activity tab
- [ ] Quick Actions (Edit, Delete, Archive)
- [ ] Apply Apple-style UI
- [ ] Mobile responsive
- [ ] E2E test

**Deliverables:**
- ✅ `projects/show.blade.php` hoàn chỉnh
- ✅ E2E test passing

**Day 3-4: Projects Edit Page** (`projects/edit.blade.php`)
**Priority:** 🔴 CRITICAL
**Estimated:** 6-8 hours

**Tasks:**
- [ ] Create page với Universal Page Frame structure
- [ ] Form pre-filled với existing data
- [ ] Same validation as Create page
- [ ] Apply Apple-style UI
- [ ] Error handling
- [ ] Success redirect
- [ ] Mobile responsive
- [ ] E2E test

**Deliverables:**
- ✅ `projects/edit.blade.php` hoàn chỉnh
- ✅ E2E test passing

**Verification Checklist:**
- [ ] All 4 pages follow Universal Page Frame structure
- [ ] All pages use Apple-style UI components
- [ ] All pages have KPI Strip (index page)
- [ ] All pages have Activity Feed (index & show pages)
- [ ] All pages are mobile responsive
- [ ] All pages have E2E tests passing
- [ ] All pages meet performance budgets (< 500ms p95)
- [ ] All pages have proper error handling
- [ ] All pages filter by tenant_id correctly

---

### 🟡 TUẦN 4: Tasks Module & Adoption

#### Day 1-3: Tasks Module Rebuild (Start)
**Priority:** 🟡 HIGH
**Estimated:** 12-15 hours (partial)

**Tasks:**
- [ ] `tasks/index.blade.php` - Task list (similar to Projects index)
- [ ] `tasks/create.blade.php` - Create task
- [ ] Apply Universal Page Frame structure
- [ ] Apply Apple-style UI
- [ ] E2E tests

#### Day 4-5: UI/UX P7 Adoption
**Priority:** 🟡 HIGH
**Estimated:** 8-10 hours

**Tasks:**
- [ ] Apply Apple-style UI to Projects pages (if not already done)
- [ ] Apply Apple-style UI to Tasks pages
- [ ] Ensure active nav uses `aria-current="page"`
- [ ] Verify UI smoke tests pass
- [ ] Update documentation

**Deliverables:**
- ✅ All new pages use Apple-style UI
- ✅ UI smoke tests passing
- ✅ Documentation updated

---

## 📋 ACTIONABLE TASKS CHECKLIST

### 🔴 CRITICAL (Week 1)

#### Verify & Integration
- [ ] **Task 1.1:** Verify Universal Page Frame Components (2-3h)
  - [ ] Review KpiStrip.tsx props interface và functionality
  - [ ] Review AlertBar.tsx props interface và functionality
  - [ ] Review ActivityFeed.tsx props interface và functionality
  - [ ] Test với sample data
  - [ ] Verify Apple-style tokens usage
  - [ ] Document component usage patterns

- [ ] **Task 1.2:** Verify KPI APIs (2-3h)
  - [ ] Test all 5 KPI endpoints
  - [ ] Verify response format
  - [ ] Verify tenant isolation
  - [ ] Verify caching
  - [ ] Performance check
  - [ ] Document API response format

- [ ] **Task 1.3:** Integrate KPI Strip vào Projects Index (3-4h)
  - [ ] Update projects/index.blade.php
  - [ ] Connect to Projects KPIs API
  - [ ] Transform API response
  - [ ] Handle loading/error states
  - [ ] Test integration

- [ ] **Task 1.4:** Integrate Activity Feed vào Projects Index (2-3h)
  - [ ] Add activity section
  - [ ] Connect to Projects Activity API
  - [ ] Transform API response
  - [ ] Handle loading/error states
  - [ ] Test integration

### 🔴 CRITICAL (Week 2-3)

#### Projects Module Rebuild
- [ ] **Task 2.1:** Projects Index Page (8-10h)
  - [ ] Page structure với Universal Page Frame
  - [ ] KPI Strip integration
  - [ ] Smart Filters integration
  - [ ] Quick Actions integration
  - [ ] Projects table/list
  - [ ] Search functionality
  - [ ] Pagination
  - [ ] Activity Feed integration
  - [ ] Apple-style UI
  - [ ] Mobile responsive
  - [ ] E2E test

- [ ] **Task 2.2:** Projects Create Page (6-8h)
  - [ ] Page structure với Universal Page Frame
  - [ ] Form với validation
  - [ ] Apple-style UI
  - [ ] Error handling
  - [ ] Success redirect
  - [ ] Mobile responsive
  - [ ] E2E test

- [ ] **Task 2.3:** Projects Show Page (8-10h)
  - [ ] Page structure với Universal Page Frame
  - [ ] Detail view với tabs
  - [ ] Quick Actions
  - [ ] Activity Feed integration
  - [ ] Apple-style UI
  - [ ] Mobile responsive
  - [ ] E2E test

- [ ] **Task 2.4:** Projects Edit Page (6-8h)
  - [ ] Page structure với Universal Page Frame
  - [ ] Form pre-filled
  - [ ] Validation
  - [ ] Apple-style UI
  - [ ] Error handling
  - [ ] Success redirect
  - [ ] Mobile responsive
  - [ ] E2E test

### 🟡 HIGH PRIORITY (Week 4+)

#### Tasks Module Rebuild
- [ ] **Task 3.1:** Tasks Index Page (6-8h)
- [ ] **Task 3.2:** Tasks Create Page (4-6h)
- [ ] **Task 3.3:** Tasks Show Page (6-8h)
- [ ] **Task 3.4:** Tasks Kanban Page (8-10h)

#### UI/UX Adoption
- [ ] **Task 4.1:** Apply Apple-style UI to Projects pages (4-6h)
- [ ] **Task 4.2:** Apply Apple-style UI to Tasks pages (4-6h)
- [ ] **Task 4.3:** Verify UI smoke tests (1h)
- [ ] **Task 4.4:** Update documentation (1h)

#### Missing Routes
- [ ] **Task 5.1:** Add Change Requests route (1h)
- [ ] **Task 5.2:** Add Templates route (1h)
- [ ] **Task 5.3:** Add Quotes route (1h)
- [ ] **Task 5.4:** Add Clients route (1h)
- [ ] **Task 5.5:** Add Gantt Chart route (1h)
- [ ] **Task 5.6:** Add QC Module route (1h)

#### Task Detail Page Completion
- [ ] **Task 6.1:** Task Comments UI (2-3h)
- [ ] **Task 6.2:** Task Attachments UI (2-3h)
- [ ] **Task 6.3:** Task Time Tracking UI (2-3h)

---

## 🔍 VERIFICATION CHECKLIST

### Before Starting Any Task
- [ ] Read `BUILD_ROADMAP.md` for page requirements
- [ ] Read `INCOMPLETE_WORK_ITEMS.md` for related items
- [ ] Read `UIUX_ROLLOUT_EXEC_PLAN.md` for UI/UX requirements
- [ ] Read `PROJECTS_API_CONTRACT.md` for API contracts
- [ ] Read `PROJECTS_COMPONENT_BREAKDOWN.md` for component structure
- [ ] Verify no conflicts with other work
- [ ] Check `DOCUMENTATION_INDEX.md` for relevant docs

### During Implementation
- [ ] Follow architecture principles (UI renders only, API for business logic)
- [ ] Ensure tenant isolation (all queries filter by tenant_id)
- [ ] Include proper error handling with error.id
- [ ] Add logging with correlation IDs (X-Request-Id)
- [ ] Use Apple-style tokens (no hardcoded colors)
- [ ] Write tests for new functionality
- [ ] Update documentation

### After Completion
- [ ] All tests pass (unit, integration, E2E)
- [ ] Performance budgets met (< 500ms p95 for pages, < 300ms p95 for APIs)
- [ ] Security review completed
- [ ] Documentation updated
- [ ] Architecture compliance verified
- [ ] UI smoke tests pass (for UI changes)
- [ ] Mobile responsive verified
- [ ] Accessibility verified (keyboard navigation, ARIA labels)
- [ ] Update relevant plan files with completion status

---

## 📊 SUCCESS METRICS

### Week 1 Goals
- ✅ Universal Page Frame Components verified và documented
- ✅ KPI APIs verified và documented
- ✅ Components integrated vào Projects Index Page
- ✅ Integration tests passing

### Week 2-3 Goals
- ✅ 4 Projects pages rebuilt và tested
- ✅ All pages use Universal Page Frame structure
- ✅ All pages use Apple-style UI
- ✅ All pages meet performance budgets
- ✅ All E2E tests passing

### Week 4 Goals
- ✅ Tasks Module started (at least 2 pages)
- ✅ UI/UX P7 Adoption completed
- ✅ Missing routes added

---

## 🚨 RISK MITIGATION

### Identified Risks
1. **Risk:** KPI APIs performance issues
   - **Mitigation:** Implement caching (60s), optimize queries, monitor performance

2. **Risk:** Universal Components không tương thích với existing pages
   - **Mitigation:** Test integration với Dashboard trước, iterate based on feedback

3. **Risk:** Projects pages complexity cao hơn expected
   - **Mitigation:** Break down into smaller tasks, prioritize core features first

4. **Risk:** Test failures blocking progress
   - **Mitigation:** Fix critical test failures first, skip non-blocking failures temporarily

---

## 📝 NOTES

### Completed Recently
- ✅ Dashboard rebuild complete
- ✅ Authentication pages complete
- ✅ UI/UX Foundation (P0-P6) complete
- ✅ E2E tests passing (4/4 smoke tests)
- ✅ Core Infrastructure complete

### Current Focus
1. **Week 1:** Verify & Integrate Components + APIs vào Projects pages
2. **Week 2-3:** Hoàn thiện Projects Module với Apple-style UI
3. **Week 3:** Tasks Module start + UI/UX Adoption

### Blockers
- ✅ None currently identified - Tất cả dependencies đã sẵn sàng
- ⚠️ Test failures exist but not blocking current work
- ✅ Components & APIs đã có - Chỉ cần tích hợp

---

## 🎯 RECOMMENDED IMMEDIATE ACTIONS

### Today (Day 1)
1. **Verify:** Universal Page Frame Components
   - Review KpiStrip.tsx, AlertBar.tsx, ActivityFeed.tsx
   - Test với sample data
   - Document component usage

2. **Verify:** KPI APIs
   - Test all 5 KPI endpoints
   - Verify response format matches component expectations
   - Document API response format

### This Week
3. **Integrate:** KPI Strip vào Projects Index Page
4. **Integrate:** Activity Feed vào Projects Index Page
5. **Verify:** Integration tests passing
6. **Prepare:** Review Projects pages structure cho Apple-style UI updates

### Next Week
7. **Start:** Projects Module rebuild
8. **Begin:** Projects Index Page
9. **Apply:** Universal Page Frame structure
10. **Apply:** Apple-style UI

---

**Last Updated:** 2025-01-19 (Updated - Removed duplicate tasks, focused on integration)  
**Next Review:** Weekly or after major milestones  
**Status:** 📋 Ready to Execute - Updated Plan (No Duplicates)

