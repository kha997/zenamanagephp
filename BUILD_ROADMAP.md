# 🚀 ZENAMANAGE PAGE REBUILD ROADMAP

## 📊 PHASE 1: CRITICAL PAGES (Weeks 1-2)
**Priority**: P0 - Must have để app có thể chạy

### 1.1 Authentication Pages ✅
- [x] Login page (`/login`)
- [x] Register page (`/register`)
- Status: Hoàn thành

### 1.2 Dashboard - ✅ COMPLETED! 🎯
**Status**: ✅ ĐÃ HOÀN THÀNH REBUILD

**Files đã rebuild:**
- ✅ `resources/views/app/dashboard/index.blade.php` - Updated to standard structure
- Includes: `_kpis.blade.php`, `_projects.blade.php`, `_quick-actions.blade.php`

**What was changed:**
- ✅ Removed duplicate header (đã có trong layout)
- ✅ Removed duplicate alert banner code
- ✅ Added proper @section('kpi-strip')
- ✅ Added proper @section('alert-bar')
- ✅ Added proper @section('activity')
- ✅ Simplified main content structure
- ✅ Follows unified page frame structure:
  - Header (auto from layout)
  - Navigator (auto from layout)
  - KPI Strip
  - Alert Bar
  - Main Content
  - Activity Section

**Scope completed:**
- ✅ Unified Page Frame structure
- ✅ KPI Strip với real data
- ✅ Recent Projects widget
- ✅ Quick Actions
- ✅ Activity Feed
- ✅ Mobile responsive

---

## 📋 PHASE 2: CORE PAGES (Weeks 3-4)
**Priority**: P0 - Essential features

### 2.1 Projects Module 🏗️
**Status**: 📋 PLANNED - Ready for implementation

**Documentation Created:**
- ✅ `PROJECTS_API_CONTRACT.md` - API specification
- ✅ `PROJECTS_COMPONENT_BREAKDOWN.md` - Component structure

**Approach:**
- 🔹 Lock API contract FIRST
- 🔹 Build backend & frontend in parallel
- 🔹 Test as you go
- 🔹 No rework!

**Pages to rebuild:**
- [ ] `projects/index.blade.php` - List all projects
- [ ] `projects/create.blade.php` - Create project
- [ ] `projects/show.blade.php` - Project detail
- [ ] `projects/edit.blade.php` - Edit project

**Scope:**
- Smart Filters ✅ (component ready)
- Quick Actions ✅ (component ready)
- Table/list với filters
- Create/Edit forms
- Detail view với tabs
- Pagination
- Search with debounce

### 2.2 Tasks Module 📝
**Vị trí thứ 3** - Vì:
- Depends on Projects
- Critical workflow

**Pages:**
- [ ] `tasks/index.blade.php` - Task list
- [ ] `tasks/create.blade.php` - Create task
- [ ] `tasks/show.blade.php` - Task detail
- [ ] `tasks/kanban.blade.php` - Kanban board

---

## 👥 PHASE 3: SUPPORTING PAGES (Weeks 5-6)
**Priority**: P1 - Important

### 3.1 Team Management 👨‍💼
- [ ] `team/index.blade.php`
- [ ] `team/users.blade.php`

### 3.2 Clients Management 💼
- [ ] `clients/index.blade.php`
- [ ] `clients/create.blade.php`
- [ ] `clients/show.blade.php`

---

## 📄 PHASE 4: DOCUMENT & TEMPLATE PAGES (Week 7)
**Priority**: P2 - Nice to have

### 4.1 Documents
- [ ] Templates module
- [ ] Document center

### 4.2 Calendar & Quotes
- [ ] Calendar view
- [ ] Quotes management

---

## ⚙️ PHASE 5: SETTINGS & ADMIN (Week 8)
**Priority**: P2

### 5.1 User Settings
- [ ] Profile page
- [ ] Settings page
- [ ] Preferences

### 5.2 Admin Pages
- [ ] Admin dashboard
- [ ] Admin users
- [ ] Admin tenants

---

## 📊 SUMMARY BY PRIORITY

### 🔴 CRITICAL (Must Start Here)
1. **Dashboard** ← BẮT ĐẦU TỪ ĐÂY!
2. Projects (index, create, show, edit)
3. Tasks (index, create, show, kanban)

### 🟡 IMPORTANT
4. Team management
5. Clients management
6. Reports & Analytics

### 🟢 OPTIONAL
7. Templates
8. Calendar
9. Settings
10. Admin pages

---

## 🎯 RECOMMENDATION: START WITH DASHBOARD

**Why Dashboard First?**
1. ✅ First page user sees
2. ✅ Validates entire system
3. ✅ Contains widgets from other pages
4. ✅ Sets UX standards for all pages
5. ✅ Can test all integrations

**Next: Projects → Tasks → Team → Clients → Others**

---

## ✅ Build Checklist per Page

For EACH page rebuild, verify:

### 🏗️ Standard Page Structure (MUST HAVE)
```
Standard Structure:
├── Header (x-shared.header-wrapper)
├── Primary Navigator (x-shared.navigation.primary-navigator)
├── KPI Strip (if needed)
├── Alert Bar (if needed)
├── Page Content
└── Activity Section (if needed)
```

### Layout & Structure
- [ ] Uses standardized layout (`layouts.app` or `layouts.admin`)
- [ ] Has header-wrapper component ✅
- [ ] Has primary-navigator component ✅ (Horizontal nav below header)
- [ ] NO SIDEBAR ✅ (Removed, replaced with navigator)
- [ ] Has KPI strip (if needed)
- [ ] Has alert bar (if needed)
- [ ] Has breadcrumbs
- [ ] Has activity section (if needed)

### Components
- [ ] Uses shared components from `components/shared/`
- [ ] Uses standardized tables/cards
- [ ] Responsive design (mobile/desktop)
- [ ] Accessibility (ARIA labels)

### Integration
- [ ] API calls work correctly
- [ ] Data loading states
- [ ] Error handling
- [ ] Validation

### Testing
- [ ] E2E test passes
- [ ] Unit tests pass
- [ ] Manual testing done

