# BÁO CÁO CHỨC NĂNG DASHBOARD – ZenaManage (Super Admin)

## Mục lục
- [0) Metadata](#0-metadata)
- [1) Mục tiêu màn hình](#1-mục-tiêu-màn-hình)
- [2) Thành phần UI & Chức năng](#2-thành-phần-ui--chức-năng)
- [3) Data Contract (View Model)](#3-data-contract-view-model)
- [4) API Endpoints Liên quan](#4-api-endpoints-liên-quan)
- [5) Checklist tuân thủ Nguyên lý ZenaManage](#5-checklist-tuân-thủ-nguyên-lý-zenamanage)
- [6) Rủi ro/Nợ kỹ thuật hiện tại](#6-rủi-ronợ-kỹ-thuật-hiện-tại)
- [7) Đề xuất ngắn](#7-đề-xuất-ngắn)

---

## 0) Metadata

**Route:** `/admin/dashboard`

**Blade file:** 
```php
resources/views/admin/dashboard/index.blade.php
resources/views/admin/dashboard/_kpis.blade.php
resources/views/admin/dashboard/_charts.blade.php
resources/views/admin/dashboard/_activity.blade.php
```

**Controller/Method:** `Route::view('/dashboard', 'admin.dashboard.index')` (View-only route)

**RBAC:** `super-admin` (quyền truy cập Super Admin Dashboard)

---

## 1) Mục tiêu màn hình

Dashboard giúp Super Admin **hiểu tình trạng hệ thống trong 3 giây** thông qua KPI strip với sparkline charts và **hành động ngay trong 1 click** để điều hướng đến các module quản lý cụ thể (Tenants, Users, Alerts, Maintenance).

---

## 2) Thành phần UI & Chức năng

### **KPI Strip**
- **Hiện có:** 
  - 5 KPI cards: Total Tenants (89), Total Users (1,247), Errors 24h (12), Queue Jobs (156), Storage Used (2.1TB)
  - Sparkline charts (30-day data) cho mỗi KPI
  - Delta indicators (+5%, +12%, +3 từ hôm qua)
  - Primary action buttons (View Tenants, Manage Users, View Errors, Monitor Queue, Manage Storage)
  - Click-to-navigate functionality
- **Thiếu:** 
  - Real-time data updates
  - Period switching (7d/30d/90d) cho sparklines
  - Drill-down filters từ KPI
- **Action 1-click:** ✅ Navigate đến `/admin/tenants`, `/admin/users`, `/admin/alerts`, `/admin/maintenance`

### **Search & Filters (Global)**
- **Search debounce 250ms:** ✅ Có (trong topbar)
- **Filters:** ❌ Chưa có local filters cho dashboard
- **Presets:** ✅ Có (Critical/Active/Recent) - nhưng chỉ console.log, chưa implement thực tế

### **Charts Section**
- **Hiện có:**
  - New Signups chart (line, 30 days)
  - Error Rate chart (bar, 7 days)
  - Period switching (30d/90d/365d cho signups, 7d/30d/90d cho errors)
  - Export functionality (CSV/JSON)
- **Thiếu:**
  - Real-time data
  - More chart types (pie, area)
  - Chart interactions (zoom, drill-down)
- **Action 1-click:** ✅ Export charts với modal selection

### **Recent Activity**
- **Hiện có:**
  - 4 mock activities với icons và timestamps
  - Empty state
  - "View All" link
- **Thiếu:**
  - Real activity data
  - Activity filtering
  - Pagination
- **Action 1-click:** ✅ Navigate đến `/admin/activity`

### **Accessibility**
- **Label/aria:** ✅ aria-label cho KPI cards, aria-hidden cho icons
- **Focus ring:** ✅ focus:ring-2 cho buttons
- **Contrast:** ✅ Đạt WCAG 2.1 AA

### **Responsive**
- **Sidebar collapse:** ✅ Hoạt động với localStorage
- **Mobile:** ✅ KPI strip 2 cards/row trên mobile
- **Bottom tabs:** ✅ Mobile navigation

---

## 3) Data Contract (View Model)

```typescript
DashboardViewModel {
  kpis: {
    totalTenants: number,        // 89
    totalUsers: number,         // 1247
    errors24h: number,          // 12
    queueJobs: number,         // 156
    storageUsed: string        // "2.1TB"
  },
  
  chartData: {
    signups: {
      labels: string[],         // ['Jan', 'Feb', 'Mar', ...]
      data: number[]           // [45, 52, 48, ...]
    },
    errors: {
      labels: string[],         // ['Jan', 'Feb', 'Mar', ...]
      data: number[]           // [2.1, 1.8, 2.3, ...]
    }
  },
  
  recentActivity: ActivityItem[] // Mock data hiện tại
}

ActivityItem {
  id: number,
  type: string,                // 'tenant_created', 'user_registered', etc.
  message: string,             // 'New tenant "TechCorp" registered'
  time: string,                // '2 minutes ago'
  icon: string,                // 'fas fa-building'
  color: string                // 'text-green-600'
}
```

---

## 4) API Endpoints Liên quan

**Hiện tại:** ❌ Chưa có API endpoints thực tế

**Cần thiết:**
```
GET /api/admin/dashboard/kpis
- Response: DashboardKPIResponse
- Status: 200 OK

GET /api/admin/dashboard/charts/signups?period=30d
- Response: ChartDataResponse
- Status: 200 OK

GET /api/admin/dashboard/charts/errors?period=7d
- Response: ChartDataResponse
- Status: 200 OK

GET /api/admin/dashboard/activity?limit=10
- Response: ActivityResponse
- Status: 200 OK

POST /api/admin/dashboard/export/chart
- Body: { type: 'signups'|'errors', format: 'csv'|'json', period: string }
- Response: File download
- Status: 200 OK

GET /api/admin/dashboard/refresh
- Response: UpdatedDashboardData
- Status: 200 OK
```

---

## 5) Checklist tuân thủ Nguyên lý ZenaManage

| Nguyên lý | Trạng thái | Ghi chú |
|-----------|------------|---------|
| **3s hiểu – 1 click hành động** | ✅ **CÓ** | KPI strip rõ ràng, action buttons |
| **KPI-first (+ sparkline, delta)** | ✅ **CÓ** | 5 KPIs với sparklines và deltas |
| **Quick presets (Critical/Active/Recent)** | ⚠️ **CHƯA** | UI có nhưng chưa implement logic |
| **Search debounce 250ms (server-side)** | ✅ **CÓ** | Global search trong topbar |
| **Dense table, sort + filter + pagination** | ❌ **KHÔNG ÁP DỤNG** | Dashboard không có table |
| **Export-first** | ✅ **CÓ** | Export charts CSV/JSON |
| **WCAG 2.1 AA** | ✅ **CÓ** | aria-labels, focus rings, contrast |
| **Performance: p95 < 500ms** | ❌ **CHƯA TEST** | Cần đo performance thực tế |

---

## 6) Rủi ro/Nợ kỹ thuật hiện tại

- **Mock data:** Tất cả data đều hardcoded, chưa có API integration
- **Quick presets:** UI có nhưng logic chỉ console.log
- **Real-time updates:** Chưa có WebSocket hoặc polling
- **Error handling:** Chưa có error states cho API failures
- **Loading states:** Chưa có loading indicators
- **Performance:** Chưa optimize Chart.js rendering cho large datasets

---

## 7) Đề xuất ngắn

### **Quick Wins (Ưu tiên cao)**
- **Implement API endpoints** cho dashboard data với mock responses
- **Add loading states** cho KPI cards và charts
- **Implement quick presets logic** thay vì chỉ console.log

### **Medium Impact (Ưu tiên trung bình)**
- **Add error handling** với retry mechanisms
- **Implement real-time updates** với polling hoặc WebSocket
- **Add more chart types** (pie chart cho tenant distribution)

### **Hard (Ưu tiên thấp)**
- **Performance optimization** cho Chart.js với large datasets
- **Add drill-down functionality** từ KPI cards
- **Implement advanced filtering** cho activity feed

---

**Tổng kết:** Dashboard có foundation tốt với UI/UX đạt chuẩn ZenaManage, nhưng cần implement backend integration và real-time features để hoàn thiện.
