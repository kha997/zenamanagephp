# BÁO CÁO CHỨC NĂNG TENANTS – ZenaManage (Super Admin)

## Mục lục
- [0) Metadata](#0-metadata)
- [1) Mục tiêu màn hình](#1-mục-tiêu-màn-hình)
- [2) Thành phần UI & Chức năng](#2-thành-phần-ui--chức-năng)
- [3) Data Contract (View Model)](#3-data-contract-view-model)
- [4) API Endpoints liên quan](#4-api-endpoints-liên-quan)
- [5) Checklist chuẩn ZenaManage](#5-checklist-chuẩn-zenamanage)
- [6) Rủi ro/Nợ kỹ thuật](#6-rủi-ronợ-kỹ-thuật)
- [7) Đề xuất ngắn](#7-đề-xuất-ngắn)

---

## 0) Metadata

**Route:** `/admin/tenants`

**Blade:**
```php
resources/views/admin/tenants/index.blade.php
resources/views/admin/tenants/_filters.blade.php
resources/views/admin/tenants/_table.blade.php
resources/views/admin/tenants/_pagination.blade.php
```

**Controller/Method:** `Route::view('/tenants', 'admin.tenants.index')` (View-only route)

**RBAC:** `super-admin` (quyền truy cập Super Admin Tenants)

**Feature flags:** Chưa có (cần thêm `tenants.mockData`, `tenants.importEnabled`)

---

## 1) Mục tiêu màn hình

Cho Super Admin xem, lọc, thao tác hàng loạt, và xuất danh sách tenants trong 1–2 click để quản lý hiệu quả các tổ chức thuê.

---

## 2) Thành phần UI & Chức năng

### **KPI Strip**
- **Hiện có:** ❌ **CHƯA CÓ** KPI strip
- **Thiếu:** Tổng tenants, active, disabled, new 30d, trial expiring
- **Primary action:** Cần thêm KPI cards với drill-down

### **Search & Filters (local)**
- **Search debounce 250ms (server):** ❌ **CHƯA** - đang dùng client-side filtering
- **Filters:** 
  - ✅ Status (all|active|suspended|pending)
  - ✅ Plan (all|Basic|Professional|Enterprise)
  - ❌ Created range (from/to dates)
  - ❌ Owner filter
- **Presets:** ❌ **CHƯA CÓ** (Active, Disabled, New 30d, Trial Expiring)

### **Bảng danh sách (bắt buộc)**
- **Cột thực tế:** 
  - ✅ name (với icon + ID)
  - ✅ domain
  - ✅ owner (name + email)
  - ✅ plan (badge)
  - ✅ status (badge)
  - ✅ users (count)
  - ❌ projects (count) - chưa có
  - ✅ lastActive (date)
  - ✅ actions (Edit, Delete)
- **Sort server-side:** ❌ **CHƯA** - đang dùng client-side sort
- **Pagination:** ❌ **CHƯA** - chỉ có UI placeholder, chưa implement
- **Row density:** ✅ **CÓ** - h-12 (48px)
- **Empty state:** ✅ **CÓ** - "No tenants found" với CTA
- **Loading state:** ❌ **CHƯA**
- **Error state:** ❌ **CHƯA**

### **Bulk Actions**
- **Select-all page:** ✅ **CÓ** - checkbox header
- **Actions:** 
  - ✅ Activate
  - ✅ Suspend  
  - ✅ Delete
  - ❌ Change plan
  - ❌ Export subset

### **Actions (per-row)**
- ✅ Edit (modal)
- ✅ Delete (modal)
- ❌ View (detail page)
- ❌ Disable/Enable
- ❌ Reset Secret
- ❌ Change Plan

### **Create Tenant**
- **Modal:** ❌ **CHƯA CÓ** - chỉ có button, chưa implement modal
- **Trường bắt buộc:** name, domain, owner, ownerEmail, plan
- **Validation:** ❌ **CHƯA**

### **Export/Import**
- **CSV/XLSX export:** ❌ **CHƯA** - chỉ có button, chưa implement
- **Import:** ❌ **CHƯA CÓ**
- **Rate-limit:** ❌ **CHƯA**

### **A11y & i18n**
- **Aria-labels:** ⚠️ **MỘT PHẦN** - có cho search, thiếu cho buttons
- **Focus:** ⚠️ **MỘT PHẦN** - có focus rings, thiếu keyboard navigation
- **i18n:** ❌ **CHƯA** - text hardcoded, chưa qua namespace `admin.tenants.*`

### **Responsive**
- **Sidebar collapse:** ✅ **OK**
- **Bảng cuộn ngang:** ✅ **OK** - overflow-x-auto
- **Presets dạng chips:** ❌ **CHƯA CÓ** presets

---

## 3) Data Contract (View Model)

### **TenantListItem (hiện có)**
```typescript
type TenantListItem = {
  id: number;                    // ✅ Có
  name: string;                  // ✅ Có
  domain?: string;               // ✅ Có
  owner?: string;                // ✅ Có (ownerName)
  ownerEmail?: string;           // ✅ Có
  plan?: string;                 // ✅ Có
  status: 'active'|'suspended';  // ✅ Có (thiếu 'disabled'|'trial')
  users?: number;                // ✅ Có (usersCount)
  projectsCount?: number;        // ❌ Thiếu
  lastActive?: string;           // ✅ Có (lastActiveAt)
  createdAt?: string;            // ✅ Có
}
```

### **TenantDetail (đề xuất)**
```typescript
type TenantDetail = {
  id: string;
  name: string;
  domain: string;
  owner: {
    name: string;
    email: string;
    phone?: string;
  };
  plan: {
    name: string;
    features: string[];
    limits: {
      users: number;
      projects: number;
      storage: number;
    };
  };
  status: 'active'|'disabled'|'trial'|'suspended';
  billing: {
    cycle: 'monthly'|'yearly';
    nextBilling: string;
    amount: number;
  };
  usage: {
    users: number;
    projects: number;
    storage: number;
  };
  settings: {
    timezone: string;
    language: string;
    features: string[];
  };
  createdAt: string;
  updatedAt: string;
  lastActiveAt: string;
}
```

---

## 4) API Endpoints liên quan

**Hiện tại:** ❌ **CHƯA CÓ** API endpoints thực tế

**Cần thiết:**
```
GET /api/admin/tenants?q=&status=&plan=&owner=&from=&to=&sort=&page=&per_page=
- Response: { data: TenantListItem[], meta: { total, page, per_page, last_page } }
- Status: 200 OK
- Cache: 60s per-tenant

POST /api/admin/tenants
- Body: { name, domain, owner, ownerEmail, plan }
- Response: { data: TenantListItem }
- Status: 201 Created
- Validation: name (required), domain (required, unique), ownerEmail (required, email)

PATCH /api/admin/tenants/{id}
- Body: { name?, domain?, owner?, ownerEmail?, plan? }
- Response: { data: TenantListItem }
- Status: 200 OK

POST /api/admin/tenants/{id}:disable
POST /api/admin/tenants/{id}:enable
- Response: { data: TenantListItem }
- Status: 200 OK

POST /api/admin/tenants/{id}:change-plan
- Body: { plan }
- Response: { data: TenantListItem }
- Status: 200 OK

DELETE /api/admin/tenants/{id}
- Response: { message: "Tenant deleted successfully" }
- Status: 200 OK

GET /api/admin/tenants/export?format=csv&status=&plan=&owner=
- Response: File download
- Status: 200 OK
- Rate-limit: 30 req/tenant/10min → 429 với Retry-After

Error Shape:
4xx/5xx → { error: { code: string, message: string, details?: any } }
```

---

## 5) Checklist chuẩn ZenaManage

| Nguyên lý | Trạng thái | Ghi chú |
|-----------|------------|---------|
| **3s hiểu – 1 click hành động** | ⚠️ **MỘT PHẦN** | Thiếu KPI strip, presets |
| **Search 250ms + filters + presets** | ⚠️ **MỘT PHẦN** | Search client-side, thiếu presets |
| **Dense table + sort + pagination** | ⚠️ **MỘT PHẦN** | Dense OK, sort/pagination client-side |
| **Bulk actions** | ✅ **CÓ** | Activate, Suspend, Delete |
| **Export-first** | ❌ **CHƯA** | Chỉ có button, chưa implement |
| **A11y (WCAG 2.1 AA) + i18n** | ⚠️ **MỘT PHẦN** | Thiếu aria-labels, i18n |
| **Performance: p95 page < 500ms; list API < 300ms** | ❌ **CHƯA TEST** | Chưa có API thực tế |

---

## 6) Rủi ro/Nợ kỹ thuật

- **Client-side filtering:** Search và sort đang dùng JavaScript, không scale
- **Mock data:** Tất cả data hardcoded, chưa có API integration
- **Thiếu KPI strip:** Không có overview metrics
- **Thiếu presets:** Không có quick filters (Active, New, Trial)
- **Pagination chưa implement:** Chỉ có UI placeholder
- **Thiếu validation:** Create/Edit forms chưa có validation
- **Thiếu loading states:** Không có skeleton/loading indicators
- **Thiếu error handling:** Không có error states
- **Thiếu i18n:** Text hardcoded, chưa qua translation layer
- **Thiếu keyboard navigation:** Chưa hỗ trợ điều hướng bằng bàn phím

---

## 7) Đề xuất ngắn

### **Quick Wins (Ưu tiên cao)**
- **Thêm KPI strip** với tổng tenants, active, disabled, new 30d
- **Implement presets** (Active, Disabled, New 30d, Trial Expiring)
- **Thêm loading states** (skeleton cho table, loading cho actions)

### **Medium Impact (Ưu tiên trung bình)**
- **Backend API integration** thay thế mock data
- **Server-side pagination** và sorting
- **Form validation** cho Create/Edit modals

### **Hard (Ưu tiên thấp)**
- **Advanced filtering** (date range, owner search)
- **Bulk operations** (change plan, export subset)
- **Tenant detail page** với full CRUD

---

**Tổng kết:** Tenants view có foundation tốt với UI/UX cơ bản, nhưng cần implement backend integration, KPI strip, presets, và cải thiện A11y/i18n để đạt chuẩn ZenaManage.
