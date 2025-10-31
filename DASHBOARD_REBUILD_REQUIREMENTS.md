# 🎯 DASHBOARD REBUILD REQUIREMENTS

## 📋 TIÊU CHUẨN CẦN TUÂN THỦ

### 🏗️ 1. UNIFIED PAGE FRAME STRUCTURE (BẮT BUỘC)

**Structure chuẩn (theo thứ tự từ trên xuống):**
```
1. Header (x-shared.header-wrapper - React) ← Tự động từ layout
2. Primary Navigator (x-shared.navigation.primary-navigator) ← Tự động từ layout
3. KPI Strip (@section('kpi-strip'))
4. Alert Bar (@section('alert-bar'))
5. Main Content (@section('content'))
6. Activity Section (@section('activity')) ← Optional
```

**KHÔNG được tạo:**
- ❌ Duplicate header (đã có trong layout)
- ❌ Duplicate alert banner code
- ❌ Sidebar (đã replaced bằng navigator phía dưới header)
- ❌ Page layout khác (chỉ dùng `layouts.app`)

---

### 📱 2. LAYOUT INHERITANCE

**Extends layout:**
```blade
@extends('layouts.app')
```

**Layout đã có sẵn:**
- ✅ Header (React HeaderShell)
- ✅ Primary Navigator
- ✅ Spacing với `pt-20`
- ✅ Container: `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`

---

### 🎨 3. BLADE SECTIONS (Cần define)

#### Section 1: KPI Strip
```blade
@section('kpi-strip')
@include('app.dashboard._kpis')
@endsection
```

**Features:**
- 4-8 KPI cards hiển thị metrics quan trọng
- Real data từ database
- Growth indicators
- Clickable để drill down

#### Section 2: Alert Bar
```blade
@section('alert-bar')
<div x-data="{ show: true }" x-show="show" class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
    <!-- Alert content -->
</div>
@endsection
```

**Features:**
- Dismissible (x-show)
- Warning/Info/Error states
- Clear call-to-action

#### Section 3: Activity Section
```blade
@section('activity')
<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <!-- Activity feed -->
</div>
@endsection
```

---

### 🎯 4. COMPONENTS CÓ SẴN

**Header Components:**
- ✅ `shared/header-wrapper.blade.php` - Single header cho app
- ✅ React HeaderShell mounted tự động
- ✅ Variant: `app` hoặc `admin`

**Navigation:**
- ✅ `shared/navigation/primary-navigator.blade.php` - Horizontal nav

**KPI Components:**
- ✅ `kpi/strip.blade.php` - Global KPI component

**Action Components:**
- ✅ `smart-filters.blade.php` - Smart filtering
- ✅ `_quick-actions.blade.php` - Quick action buttons

---

### 🔧 5. CÔNG NGHỆ SỬ DỤNG

#### Frontend Stack:
- **Blade Templates** (server-side rendering)
- **Alpine.js** (client-side reactivity)
- **Tailwind CSS** (styling)
- **Font Awesome** (icons)

#### Backend Stack:
- **Laravel 10.x** (PHP 8.2+)
- **MySQL** (database)
- **Eloquent ORM** (data access)

#### JavaScript Framework:
- **React** (only for HeaderShell)
- **Alpine.js** (for page interactions)

**Không dùng:**
- ❌ Vue.js
- ❌ jQuery
- ❌ Bootstrap (chỉ Tailwind)

---

### 📐 6. RESPONSIVE DESIGN

**Breakpoints:**
- Mobile: `< 640px` (1 column)
- Tablet: `640px - 1024px` (2 columns)
- Desktop: `> 1024px` (3+ columns)

**Features:**
- Mobile-first approach
- Touch-friendly (min 44px touch targets)
- Hamburger menu on mobile
- Sticky headers
- Card layouts on mobile

---

### ♿ 7. ACCESSIBILITY (WCAG 2.1 AA)

**Required:**
- ✅ `data-testid` attributes
- ✅ ARIA labels
- ✅ Keyboard navigation
- ✅ Focus indicators
- ✅ Screen reader support
- ✅ Color contrast (4.5:1 minimum)

**Example:**
```blade
<button 
    @click="action()" 
    type="button"
    data-testid="action-button"
    aria-label="Perform action"
    class="focus:outline-none focus:ring-2"
>
    Action
</button>
```

---

### 🎨 8. STYLING GUIDELINES

**Colors:**
- Primary: Blue (`bg-blue-600`, `text-blue-600`)
- Success: Green (`bg-green-100`, `text-green-800`)
- Warning: Yellow (`bg-yellow-100`, `text-yellow-800`)
- Error: Red (`bg-red-100`, `text-red-800`)
- Gray scales for text (`text-gray-900`, `text-gray-500`, etc.)

**Spacing:**
- Container: `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`
- Gap between sections: `mb-8`
- Card padding: `p-6`

**Shadows:**
- Small: `shadow-sm`
- Medium: `shadow`
- Large: `shadow-md`

---

### 🔍 9. DATA HANDLING

**Controller Pattern:**
```php
// In Controller
public function index()
{
    return view('app.dashboard.index', [
        'recentProjects' => $this->getRecentProjects(),
        'recentActivity' => $this->getRecentActivity(),
        'kpis' => $this->getKPIs(),
    ]);
}
```

**Blade Pattern:**
```blade
@forelse($recentProjects as $project)
    <div>{{ $project->name }}</div>
@empty
    <div>No data</div>
@endforelse
```

---

### ✅ 10. TESTING REQUIREMENTS

**Required Tests:**
- ✅ Page loads without errors
- ✅ KPIs display real data
- ✅ All sections render correctly
- ✅ Mobile responsive
- ✅ Keyboard navigation works
- ✅ Screen reader compatible

**Test Commands:**
```bash
# E2E Tests
npx playwright test tests/E2E/dashboard/Dashboard.spec.ts

# Manual Checklist
# See: DASHBOARD_TESTING_CHECKLIST.md
```

---

## 📝 DASHBOARD FILE STRUCTURE

```
resources/views/app/dashboard/
├── index.blade.php          # Main Dashboard page (extends layouts.app)
├── _kpis.blade.php          # KPI cards component
├── _projects.blade.php      # Recent projects widget
├── _activity.blade.php      # Activity feed widget
└── _quick-actions.blade.php # Quick action buttons
```

---

## 🎯 SUMMARY

**Must Have:**
1. ✅ Extends `layouts.app`
2. ✅ Uses `@section('kpi-strip')`, `@section('alert-bar')`, `@section('activity')`
3. ✅ No duplicate header/navigator
4. ✅ Mobile responsive
5. ✅ Accessibility compliant
6. ✅ Real data from database
7. ✅ Alpine.js for interactivity

**Technology Stack:**
- Blade Templates (server-side)
- Alpine.js (reactive interactions)
- Tailwind CSS (styling)
- React (HeaderShell only)
- Laravel 10.x (backend)
- MySQL (database)

**Status**: ✅ Dashboard đã rebuild theo đúng tiêu chuẩn này

