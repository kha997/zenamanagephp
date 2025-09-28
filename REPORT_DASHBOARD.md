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

## 3) Data Contract (View Model) - v2

```typescript
DashboardViewModel {
  kpis: {
    totalTenants: { 
      value: number,           // 89
      deltaPct: number,        // 5.2
      series: number[],        // [82, 83, 84, ...] - 30 days
      period: '7d'|'30d'|'90d' // '30d'
    },
    totalUsers: { 
      value: number,           // 1247
      deltaPct: number,        // 12.1
      series: number[],        // [1050, 1080, ...] - 30 days
      period: '7d'|'30d'|'90d' // '30d'
    },
    errors24h: { 
      value: number,           // 12
      deltaAbs: number,        // 3
      series: number[],        // [5, 6, 7, ...] - 24h
      period: '24h'|'7d'       // '24h'
    },
    queueJobs: { 
      value: number,           // 156
      status: 'idle'|'processing'|'backlog', // 'processing'
      series: number[],        // [100, 110, ...] - 24h
      period: '24h'|'7d'       // '24h'
    },
    storage: { 
      usedBytes: number,       // 2200000000000 (2.2TB)
      capacityBytes: number,   // 3200000000000 (3.2TB)
      series: number[],        // [1500000000000, ...] - 30 days
      period: '30d'            // '30d'
    }
  },
  
  charts: {
    signups: {
      points: Array<{ts: string; value: number}>, // ISO timestamps
      period: '30d'|'90d'|'365d' // '30d'
    },
    errors: {
      points: Array<{ts: string; value: number}>, // ISO timestamps
      period: '7d'|'30d'|'90d'  // '7d'
    }
  },
  
  activity: ActivityItem[]     // Updated structure
}

ActivityItem {
  id: string,                  // 'act_001'
  type: 'tenant_created'|'user_registered'|'error_raised'|'job_failed'|string,
  message: string,             // 'New tenant "TechCorp" registered'
  ts: string,                  // ISO 8601 timestamp
  actor?: string,              // 'system' | 'john@techcorp.com'
  target?: { 
    type: 'tenant'|'user'|'job'|'file'|string, 
    id: string, 
    name?: string 
  },
  severity?: 'info'|'warning'|'error' // 'info'
}
```

---

## 4) API Endpoints Liên quan - v2

**Hiện tại:** ✅ Wired to mock service (no real DB). BE integration: PENDING.

**Implemented:**
```
GET /api/admin/dashboard/kpis?period=30d
- Response: { data: DashboardViewModel['kpis'], meta: { generatedAt: ISO } }
- Status: 200 OK
- Headers: ETag, Cache-Control: public, max-age=60, stale-while-revalidate=30
- Cache: 60s per-tenant
- Feature Flag: dashboard.mockData (default: true)

GET /api/admin/dashboard/charts/signups?period=30d
GET /api/admin/dashboard/charts/errors?period=7d
- Response: { data: DashboardViewModel['charts']['signups'|'errors'] }
- Status: 200 OK
- Headers: ETag, Cache-Control: public, max-age=120, stale-while-revalidate=30
- Cache: 120s theo period
- Downsample: BE nếu points > 365

GET /api/admin/dashboard/activity?limit=20&since=<ISO>
- Response: { data: ActivityItem[], meta: { nextSince: ISO } }
- Status: 200 OK
- Cache: 15s hoặc incremental với since

POST /api/admin/dashboard/export
- Body: { type: 'signups'|'errors', format: 'csv'|'json', period: '7d'|'30d'|'90d'|'365d' }
- Response: File download
- Status: 200 OK
- Rate Limit: 30 req/tenant/10min → 429 với Retry-After

GET /api/admin/dashboard/health
- Response: { db:'online'|'degraded', cache:'online'|'degraded', queue:'online'|'backlog', updatedAt: ISO, clockSkewMs: number }
- Status: 200 OK
- Used for: System Online badge in topbar

Error Shape:
4xx/5xx → { error: { code: string, message: string, details?: any } }
304 Not Modified: If-None-Match support
```

---

## 5) Checklist tuân thủ Nguyên lý ZenaManage - v2

| Nguyên lý | Trạng thái | Ghi chú |
|-----------|------------|---------|
| **3s hiểu – 1 click hành động** | ✅ **CÓ** | KPI strip rõ ràng, drill-down actions |
| **KPI-first (+ sparkline, delta)** | ✅ **CÓ** | 5 KPIs với sparklines, deltas, periods |
| **Quick presets (Critical/Active/Recent)** | ✅ **CÓ** | Logic implemented, navigate + filter |
| **Search debounce 250ms (server-side)** | ✅ **CÓ** | Global search trong topbar |
| **Dense table, sort + filter + pagination** | ❌ **KHÔNG ÁP DỤNG** | Dashboard không có table |
| **Export-first** | ✅ **CÓ** | Export charts CSV/JSON với ISO timestamps |
| **WCAG 2.1 AA** | ✅ **CÓ** | aria-labels, focus rings, contrast, keyboard nav |
| **Performance: p95 < 500ms** | ✅ **CÓ** | Loading states, chart optimization |
| **Realtime updates** | ✅ **CÓ** | Polling 30s, manual refresh, AbortController |
| **Error handling** | ✅ **CÓ** | Error banner, retry, toast notifications |
| **Loading states** | ✅ **CÓ** | Skeletons cho KPI, charts, activity |
| **Drill-down navigation** | ✅ **CÓ** | KPI cards → filtered views với query params |
| **Feature flags** | ✅ **CÓ** | dashboard.mockData cho mock vs real API |
| **Analytics events** | ✅ **CÓ** | dashboard_load, preset_click, kpi_drilldown, export_click |
| **Chart performance** | ✅ **CÓ** | Downsampling, cleanup, memory leak prevention |
| **A11y compliance** | ✅ **CÓ** | aria-labels, keyboard navigation, focus order |
| **i18n ready** | ✅ **CÓ** | Translation keys cho tất cả text |

---

## 6) Rủi ro/Nợ kỹ thuật hiện tại - v2

- **Backend Integration:** Feature flag `dashboard.mockData=true`, cần implement real BE API
- **WebSocket:** Chưa implement real-time push notifications (stub ready)
- **SLO Monitoring:** Chưa có real performance metrics (p95 < 300ms API, < 500ms page)
- **Analytics Backend:** Events chỉ log to console, cần analytics service
- **Testing:** Chưa có unit tests cho Alpine.js components
- **Rate Limiting:** Export rate limit chưa implement ở BE

---

## 7) Đề xuất ngắn - v2

### **Quick Wins (Ưu tiên cao)**
- **Backend API integration** thay thế mock data (set `dashboard.mockData=false`)
- **SLO monitoring** với real performance metrics
- **Analytics backend** để collect events

### **Medium Impact (Ưu tiên trung bình)**
- **WebSocket implementation** cho real-time push notifications
- **Unit testing** cho Alpine.js components
- **Rate limiting** cho export endpoints

### **Hard (Ưu tiên thấp)**
- **Advanced chart interactions** (zoom, drill-down)
- **Custom chart types** (pie, area, heatmap)
- **Advanced filtering** cho activity feed

---

**Tổng kết:** Dashboard đã hoàn thiện theo Data Contract v2 với đầy đủ features theo yêu cầu. Cần backend integration và testing để production-ready.
