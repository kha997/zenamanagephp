# Kế Hoạch Triển Khai UAT - ZenaManage

**Version**: 1.0  
**Last Updated**: January 2025  
**Status**: Planning  
**Purpose**: Kế hoạch chi tiết để hoàn thiện các tính năng trước UAT

---

## Tổng Quan

Kế hoạch này bao gồm 5 bước chính để chuẩn bị hệ thống cho UAT:

1. **Bước 1**: Khóa chặt các flow về mật khẩu (Auth – Security)
2. **Bước 2**: Hoàn thiện Project status/archive/delete (business rules)
3. **Bước 3**: Overdue & Notifications - polish cho UAT
4. **Bước 4**: Khoanh WebSocket thành "optional"
5. **Bước 5**: Đóng gói thành "UAT Playbook" và dừng review

---

## Bước 1 – Khóa Chặt Các Flow Về Mật Khẩu (Auth – Security)

### 1.1. Forgot + Reset Password

**Hiện trạng**: 
- ✅ BE rõ (PasswordController, PasswordResetController)
- ✅ FE API có (authApi.forgotPassword, authApi.resetPassword)
- ✅ UI React đã có (ForgotPasswordPage.tsx, ResetPasswordPage.tsx)
- ⚠️ Tests/Trạng thái: PARTIAL (có password-reset-flow.spec.ts nhưng cần review)

**Công việc cần làm**:

#### 1.1.1. Hoàn thiện UI React

**File**: `frontend/src/features/auth/pages/ForgotPasswordPage.tsx`

**Checklist**:
- [ ] Đảm bảo có 2 màn rõ ràng:
  - `/forgot-password` → form email → `authApi.forgotPassword`
  - `/reset-password?token=...&email=...` → form nhập mật khẩu mới → `authApi.resetPassword`
- [ ] Text rõ ràng:
  - Gửi mail: "Nếu email tồn tại, chúng tôi đã gửi link…" (tránh lộ user)
  - Token sai / hết hạn / reset thành công: thông báo bám theo error/response BE
- [ ] Error handling:
  - Hiển thị lỗi từ BE một cách rõ ràng
  - Validation errors hiển thị đúng field
  - Loading state khi submit

**File**: `frontend/src/features/auth/pages/ResetPasswordPage.tsx`

**Checklist**:
- [ ] Validate token và email từ URL params
- [ ] Hiển thị error nếu token/email thiếu hoặc invalid
- [ ] Form validation:
  - Password match validation
  - Password policy validation (nếu có)
- [ ] Success message và redirect về login
- [ ] Error messages rõ ràng cho các trường hợp:
  - Token invalid/expired
  - Password mismatch
  - Password policy violation

#### 1.1.2. Viết/Review Playwright Spec End-to-End

**File**: `tests/e2e/auth/password-reset-flow.spec.ts` (đã có, cần review)

**Checklist**:
- [ ] Flow đầy đủ:
  1. Vào `/forgot-password`, nhập email test
  2. Trong test: tạo token reset (factory/DB seed) hoặc lấy token từ DB
  3. Mở `/reset-password?token=...&email=...`
  4. Đặt password mới → login thành công bằng mật khẩu mới
  5. Mật khẩu cũ login fail
- [ ] Test cases:
  - [ ] User can reset password through full flow
  - [ ] Forgot password shows success message regardless of email existence (security)
  - [ ] Reset password validates password match
  - [ ] Reset password page shows error for invalid token
  - [ ] Reset password rejects expired token
  - [ ] Reset password rejects reused token

**Cần bổ sung**:
- [ ] Helper function để tạo reset token trong test (nếu chưa có)
- [ ] Helper function để expire token (nếu cần test expired token)

#### 1.1.3. (Optional) Vitest UI Tests

**File**: `frontend/src/features/auth/pages/__tests__/ForgotPasswordPage.test.tsx`

**Checklist**:
- [ ] Test validate form
- [ ] Test show error message
- [ ] Test loading state
- [ ] Test success state

**File**: `frontend/src/features/auth/pages/__tests__/ResetPasswordPage.test.tsx`

**Checklist**:
- [ ] Test validate form
- [ ] Test password match validation
- [ ] Test show error message
- [ ] Test loading state
- [ ] Test success state

**Sau khi làm xong**:
- [ ] Update bảng status → dòng "Forgot + Reset" chuyển thành **READY**

---

### 1.2. Change Password Sau Khi Login

**Hiện trạng**: 
- ✅ BE có (PasswordController@change)
- ✅ FE API có (authApi.changePassword)
- ✅ UI có (SecuritySettings.tsx)
- ⚠️ Tests: PARTIAL (có change-password.spec.ts nhưng cần review)

**Công việc cần làm**:

#### 1.2.1. Xác Định UI Chính Thức

**File**: `frontend/src/features/settings/components/SecuritySettings.tsx`

**Checklist**:
- [ ] Xác định route: `/app/settings/security` hoặc `/app/settings` với tab Security
- [ ] Form gắn với `authApi.changePassword()` (POST `/api/auth/password/change`)
- [ ] Form fields:
  - Current password
  - New password
  - Password confirmation
- [ ] Warning message: "Sau khi đổi mật khẩu, bạn sẽ phải đăng nhập lại trên tất cả thiết bị"
- [ ] Success message: "Mật khẩu đã được thay đổi. Vui lòng đăng nhập lại."

#### 1.2.2. Đảm Bảo Behavior BE

**File**: `app/Http/Controllers/Api/Auth/PasswordController.php`

**Checklist**:
- [ ] Sau khi đổi pass:
  - Token cũ không dùng được nữa (ít nhất là current token)
  - Login bằng mật khẩu cũ phải fail
- [ ] Code hiện tại:
  ```php
  // Invalidate all tokens for security (user must login again after password change)
  $user->tokens()->delete();
  ```
- [ ] Verify: Test `PasswordChangeTest` và `SecurityIntegrationTest` pass

#### 1.2.3. Viết/Review Playwright Spec

**File**: `tests/e2e/auth/change-password.spec.ts` (đã có, cần review)

**Checklist**:
- [ ] Flow đầy đủ:
  1. Login → vào trang Security → đổi mật khẩu → logout
  2. Login bằng mật khẩu cũ: lỗi
  3. Login bằng mật khẩu mới: ok
- [ ] Test cases:
  - [ ] User can change password after login
  - [ ] Change password validates current password
  - [ ] Change password validates password match
  - [ ] Old password cannot be used after change
  - [ ] Password change shows warning about token revocation
  - [ ] Password change revokes tokens on multiple tabs/devices

**Sau khi làm xong**:
- [ ] Update bảng status → dòng "Đổi mật khẩu" chuyển thành **READY**

---

## Bước 2 – Hoàn Thiện Project Status/Archive/Delete (Business Rules)

### 2.1. Status & Archive

**Hiện trạng**:
- ✅ Business rules đã document (PROJECT_STATUS_BUSINESS_RULES.md)
- ✅ Service có (ProjectStatusTransitionService)
- ⚠️ Tests: PARTIAL

**Công việc cần làm**:

#### 2.1.1. Rà Soát Và Ghi Rõ Rules Trong Code

**File**: `app/Services/ProjectStatusTransitionService.php`

**Checklist**:
- [ ] Các trạng thái hợp lệ: `planning`, `active`, `on_hold`, `completed`, `cancelled`, `archived`
- [ ] Cho phép chuyển:
  - Từ đâu → đâu? (xem PROJECT_STATUS_BUSINESS_RULES.md)
  - `completed` có được reopen về `active` không? (theo doc: không, chỉ archive)
- [ ] Conditional transitions:
  - `planning` → `completed`: chỉ nếu không có unfinished tasks
  - `active` → `planning`: chỉ nếu không có active tasks
- [ ] Archive rules:
  - Chỉ archive từ `completed` hoặc `cancelled`
  - `archived` là terminal state (không thể chuyển từ archived)

**File**: `app/Models/Project.php`

**Checklist**:
- [ ] Enum/Constants cho status values
- [ ] Validation rules cho status transitions (nếu có)

#### 2.1.2. Đảm Bảo API Chuẩn

**File**: `app/Http/Controllers/Api/V1/App/ProjectController.php`

**Checklist**:
- [ ] PUT `/api/v1/app/projects/{id}/status` hoặc PUT `/api/v1/app/projects/{id}` với field `status`
- [ ] Nếu có archive riêng: PUT `/api/v1/app/projects/{id}/archive` và restore
- [ ] Error responses:
  - 422 cho invalid transitions
  - Error code rõ ràng (ví dụ: `HAS_UNFINISHED_TASKS`, `HAS_ACTIVE_TASKS`)
  - Error message tiếng Việt rõ ràng

#### 2.1.3. Viết/Hoàn Thiện PHPUnit Feature Tests

**File**: `tests/Feature/Api/V1/App/ProjectStatusTest.php` (tạo mới hoặc update)

**Checklist**:
- [ ] Test status chuyển hợp lệ:
  - `planning` → `active`
  - `active` → `on_hold`
  - `on_hold` → `active`
  - `active` → `completed`
  - `completed` → `archived`
- [ ] Test status chuyển bị chặn:
  - `planning` → `completed` với unfinished tasks → 422
  - `active` → `planning` với active tasks → 422
  - `archived` → bất kỳ status nào → 422
- [ ] Test conditional transitions:
  - Rule liên quan task (ví dụ: không cho completed nếu còn task active)

#### 2.1.4. Thêm Playwright Spec

**File**: `tests/e2e/core/projects/project-status-flow.spec.ts` (tạo mới hoặc update)

**Checklist**:
- [ ] Flow: Tạo project → đổi `planning` → `active` → `on_hold` → `completed`
- [ ] Check list:
  - View "Open/Active": không thấy project đã completed
  - View "All": còn thấy
- [ ] Nếu có archive:
  - Archive → chỉ hiện ở filter Archived
  - Archived project không thể thay đổi status

---

### 2.2. Delete Project + Điều Kiện Chặn

**Hiện trạng**:
- ✅ Business rules đã document (PROJECT_STATUS_BUSINESS_RULES.md)
- ✅ Service có (ProjectManagementService@deleteProject)
- ⚠️ Tests: PARTIAL (có project-delete.spec.ts nhưng cần review)

**Công việc cần làm**:

#### 2.2.1. Chốt Rule Xóa Trong ProjectService

**File**: `app/Services/ProjectManagementService.php`

**Checklist**:
- [ ] Trường hợp được xóa: không có task? hay có nhưng đều done?
  - Theo doc: **không có task nào** (kể cả soft-deleted)
- [ ] Trường hợp phải bị chặn:
  - Có task active
  - Có task bất kỳ (kể cả soft-deleted)
- [ ] Code hiện tại:
  ```php
  if ($project->tasks()->withTrashed()->exists()) {
      // Block deletion
  }
  ```

#### 2.2.2. Chuẩn Hóa Lỗi BE

**File**: `app/Services/ProjectManagementService.php`

**Checklist**:
- [ ] Trả 422/409 với body kiểu:
  ```json
  {
    "ok": false,
    "code": "PROJECT_DELETE_BLOCKED_HAS_ACTIVE_TASKS",
    "message": "Không thể xoá dự án vì vẫn còn {count} công việc đang tồn tại. Vui lòng xoá hoặc hoàn thành tất cả công việc trước khi xoá dự án.",
    "traceId": "req_abc12345"
  }
  ```
- [ ] Error code: `PROJECT_HAS_TASKS` hoặc `PROJECT_DELETE_BLOCKED`
- [ ] Error message bao gồm số lượng task (nếu có thể)

#### 2.2.3. Playwright Spec

**File**: `tests/e2e/core/projects/project-delete.spec.ts` (đã có, cần review)

**Checklist**:
- [ ] Case 1: project "trống" → delete ok
- [ ] Case 2: project có task active → delete fail → FE show toast/alert rõ lý do
- [ ] Case 3: project có task done → delete fail (theo rule: không có task nào)
- [ ] Verify error message hiển thị rõ ràng trong UI

---

## Bước 3 – Overdue & Notifications: Polish Cho UAT

### 3.1. Overdue Flows

**Hiện trạng**:
- ✅ Logic đã có (CalculationService, ProjectManagementService)
- ⚠️ Cần confirm một chỗ duy nhất

**Công việc cần làm**:

#### 3.1.1. Confirm Một Chỗ Duy Nhất Tính Rule

**File**: `app/Services/CalculationService.php` hoặc tạo `app/Services/OverdueService.php`

**Checklist**:
- [ ] Task overdue: `end_date < today` AND `status NOT IN [completed, cancelled, done, canceled]`
- [ ] Project overdue: `end_date < today` AND `status IN [active, on_hold]`
- [ ] Tạo helper method:
  ```php
  public function isTaskOverdue(Task $task): bool
  public function isProjectOverdue(Project $project): bool
  ```
- [ ] Tất cả queries dùng chung logic này

**Files cần update**:
- `app/Services/ProjectManagementService.php` (getProjectKpis, getProjectAlertsById)
- `app/Repositories/TaskRepository.php` (nếu có query overdue)
- `app/Repositories/ProjectRepository.php` (nếu có query overdue)

#### 3.1.2. Đảm Bảo FE Dùng Chung Semantics

**File**: `frontend/src/features/dashboard/hooks.ts` (hoặc tương tự)

**Checklist**:
- [ ] KPI ở Dashboard dùng logic giống BE
- [ ] Filter `/app/tasks?status=overdue` cùng logic
- [ ] Tạo helper function:
  ```typescript
  export const isTaskOverdue = (task: Task): boolean => {
    if (!task.end_date) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const endDate = new Date(task.end_date);
    endDate.setHours(0, 0, 0, 0);
    return endDate < today && !['done', 'completed', 'canceled', 'cancelled'].includes(task.status);
  };
  ```

#### 3.1.3. Thêm E2E Step

**File**: `tests/e2e/dashboard/dashboard-overdue.spec.ts` (tạo mới hoặc update)

**Checklist**:
- [ ] Truy cập trực tiếp `/app/tasks?status=overdue` (không qua Dashboard)
- [ ] List chỉ hiện tasks quá hạn
- [ ] Completed/cancelled tasks không hiện
- [ ] Tenant isolation maintained

---

### 3.2. Notifications In-App

**Hiện trạng**:
- ✅ UI có (HeaderShell.tsx, NotificationsBell.tsx)
- ⚠️ Tests: PARTIAL (có notifications-basic.spec.ts nhưng cần review)

**Công việc cần làm**:

#### 3.2.1. Check HeaderShell.tsx

**File**: `frontend/src/components/layout/HeaderShell.tsx`

**Checklist**:
- [ ] Badge = số notification chưa đọc
- [ ] Mở bell → list notifications lấy từ `/api/v1/notifications`
- [ ] Mark read / mark all read → badge về 0
- [ ] Loading state khi fetch notifications
- [ ] Error handling

#### 3.2.2. Thêm/Review E2E Spec

**File**: `tests/e2e/core/notifications-basic.spec.ts` (đã có, cần review)

**Checklist**:
- [ ] Seed 2–3 notification → login → mở bell → mark read + mark all → verify
- [ ] Test cases:
  - [ ] View notifications list
  - [ ] Mark notification as read
  - [ ] Mark all notifications as read
  - [ ] Badge count updates correctly
  - [ ] Empty state when no notifications

---

## Bước 4 – Khoanh WebSocket Thành "Optional"

**Hiện trạng**:
- ✅ WebSocket đã được đánh rõ là EXPERIMENTAL
- ✅ Feature flags đã có (config/features.php)
- ⚠️ Cần đảm bảo không có màn hình nào phụ thuộc WS

**Công việc cần làm**:

#### 4.1. Đảm Bảo Feature Flag

**File**: `config/features.php`

**Checklist**:
- [ ] Feature flag: `env('WEBSOCKET_ENABLE_DASHBOARD', false)` (default: false)
- [ ] Feature flag: `env('WEBSOCKET_ENABLE_ALERTS', false)` (default: false)
- [ ] Document trong `docs/WEBSOCKET_STATUS.md` (đã có)

#### 4.2. Kiểm Lại Frontend

**File**: `frontend/src/features/dashboard/hooks.ts` (hoặc tương tự)

**Checklist**:
- [ ] Khi `FEATURE_WEBSOCKET_DASHBOARD=false`: Dashboard chỉ dùng HTTP hooks, không auto connect WS
- [ ] Khi `FEATURE_WEBSOCKET_DASHBOARD=true`: mới khởi tạo WS client
- [ ] Không có màn hình nào phụ thuộc WS để load data lần đầu
- [ ] HTTP phải luôn đủ

**Files cần check**:
- `frontend/src/features/dashboard/hooks.ts`
- `frontend/src/features/dashboard/DashboardPage.tsx`
- `frontend/src/components/RealtimeNotifications.tsx` (nếu có)

#### 4.3. Verify HTTP Fallback

**Checklist**:
- [ ] Dashboard load được hoàn toàn bằng HTTP
- [ ] Alerts load được hoàn toàn bằng HTTP
- [ ] Notifications load được hoàn toàn bằng HTTP
- [ ] Tất cả data fetching dùng React Query hooks với HTTP polling

**Test**:
- [ ] Set `WEBSOCKET_ENABLE_DASHBOARD=false` và `WEBSOCKET_ENABLE_ALERTS=false`
- [ ] Verify tất cả features hoạt động bình thường
- [ ] Run E2E tests với WebSocket disabled

---

## Bước 5 – Đóng Gói Thành "UAT Playbook" Và Dừng Review

**Công việc** (cho maintainer, không phải Cursor):

### 5.1. Tạo/Update File docs/UAT_PLAYBOOK.md

**Checklist**:
- [ ] Liệt kê các flow sẽ test (theo bảng READY/PARTIAL sau khi fix)
- [ ] Mỗi flow: bước thao tác + expected behavior + màn hình liên quan
- [ ] Update từ bảng status hiện tại:
  - Forgot + Reset: READY
  - Change Password: READY
  - Project Status/Archive: READY
  - Project Delete: READY
  - Overdue: READY
  - Notifications: READY
  - WebSocket: EXPERIMENTAL (optional)

### 5.2. Chạy Full CI

**Checklist**:
- [ ] PHPUnit tests: `php artisan test`
- [ ] Playwright tests: `npm run test:e2e`
- [ ] Vitest tests: `npm run test:unit`
- [ ] Lưu lại:
  - Commit hash
  - Kết quả test (pass/fail count)
  - Performance metrics (nếu có)
- [ ] Đây là "baseline UAT" để sau này track regression

### 5.3. Tạo Test Report

**File**: `docs/UAT_BASELINE_REPORT.md` (tạo mới)

**Nội dung**:
- Date: [date]
- Commit hash: [hash]
- Test results:
  - PHPUnit: X passed, Y failed
  - Playwright: X passed, Y failed
  - Vitest: X passed, Y failed
- Performance benchmarks:
  - API p95 latency: [ms]
  - Page p95 load time: [ms]
- Known issues: [list]
- Ready for UAT: Yes/No

---

## Checklist Tổng Quan

### Bước 1: Auth – Security
- [ ] 1.1.1: Hoàn thiện UI React cho Forgot/Reset password
- [ ] 1.1.2: Viết/Review Playwright spec cho forgot/reset password
- [ ] 1.1.3: (Optional) Vitest UI tests
- [ ] 1.2.1: Xác định UI chính thức cho change password
- [ ] 1.2.2: Đảm bảo behavior BE cho change password
- [ ] 1.2.3: Viết/Review Playwright spec cho change password

### Bước 2: Project Status/Archive/Delete
- [ ] 2.1.1: Rà soát và ghi rõ rules trong code
- [ ] 2.1.2: Đảm bảo API chuẩn cho status/archive
- [ ] 2.1.3: Viết/Hoàn thiện PHPUnit Feature tests
- [ ] 2.1.4: Thêm Playwright spec cho project status flow
- [ ] 2.2.1: Chốt rule xóa trong ProjectService
- [ ] 2.2.2: Chuẩn hóa lỗi BE cho delete blocked
- [ ] 2.2.3: Review Playwright spec cho project delete

### Bước 3: Overdue & Notifications
- [ ] 3.1.1: Confirm một chỗ duy nhất tính rule overdue
- [ ] 3.1.2: Đảm bảo FE dùng chung semantics
- [ ] 3.1.3: Thêm E2E step cho overdue
- [ ] 3.2.1: Check HeaderShell.tsx cho notifications
- [ ] 3.2.2: Review E2E spec cho notifications

### Bước 4: WebSocket Optional
- [ ] 4.1: Đảm bảo feature flag
- [ ] 4.2: Kiểm lại frontend
- [ ] 4.3: Verify HTTP fallback

### Bước 5: UAT Playbook
- [ ] 5.1: Tạo/Update docs/UAT_PLAYBOOK.md
- [ ] 5.2: Chạy full CI
- [ ] 5.3: Tạo test report

---

## Timeline Ước Tính

- **Bước 1**: 2-3 ngày
- **Bước 2**: 2-3 ngày
- **Bước 3**: 1-2 ngày
- **Bước 4**: 1 ngày
- **Bước 5**: 1 ngày (maintainer)

**Tổng**: ~7-10 ngày

---

## Notes

- Tất cả code changes phải tuân thủ PROJECT_RULES.md
- Tất cả tests phải pass trước khi merge
- Documentation phải được update theo DOCUMENTATION_INDEX.md
- Performance benchmarks phải được đảm bảo (API p95 < 300ms, Page p95 < 500ms)

---

*Kế hoạch này sẽ được update khi có thay đổi trong quá trình triển khai.*

