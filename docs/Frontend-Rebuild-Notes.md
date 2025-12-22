# Frontend Rebuild Notes

## Quyết Định Header

**Quyết định:** React sẽ render header riêng trong `MainLayout.tsx` và `AdminLayout.tsx`, không dùng Blade `header-wrapper.blade.php` để tránh trùng lặp.

**Lý do:**
- SPA React cần control hoàn toàn UI
- Blade header-wrapper được dùng cho các trang Blade thuần (nếu có)
- React header sẽ có notification bell, theme toggle, user menu tích hợp

## Nguồn Routes

### API Routes (từ `routes/api.php`)

#### Authentication (`/api/v1/auth/*`)
- `POST /api/v1/auth/login` - Login
- `POST /api/v1/auth/logout` - Logout (requires auth:sanctum)
- `GET /api/v1/auth/me` - Get current user (requires auth:sanctum, ability:tenant)
- `GET /api/v1/auth/permissions` - Get user permissions (requires auth:sanctum, ability:tenant)
- `POST /api/v1/auth/password/forgot` - Forgot password
- `POST /api/v1/auth/password/reset` - Reset password

#### Projects (`/api/v1/app/projects/*`)
- `GET /api/v1/app/projects` - List projects
- `GET /api/v1/app/projects/{id}` - Get project detail
- `POST /api/v1/app/projects` - Create project
- `PUT /api/v1/app/projects/{id}` - Update project
- `DELETE /api/v1/app/projects/{id}` - Delete project
- `GET /api/v1/app/projects/kpis` - Get KPIs
- `GET /api/v1/app/projects/alerts` - Get alerts
- `GET /api/v1/app/projects/activity` - Get activity

#### Tasks (`/api/v1/app/tasks/*`)
- `GET /api/v1/app/tasks` - List tasks
- `GET /api/v1/app/tasks/{id}` - Get task detail
- `POST /api/v1/app/tasks` - Create task
- `PUT /api/v1/app/tasks/{id}` - Update task
- `DELETE /api/v1/app/tasks/{id}` - Delete task
- `GET /api/v1/app/tasks/kpis` - Get KPIs
- `GET /api/v1/app/tasks/alerts` - Get alerts
- `GET /api/v1/app/tasks/activity` - Get activity

#### Notifications (`/api/v1/notifications/*`)
- `GET /api/v1/notifications` - List notifications (requires ability:tenant)
- `PUT /api/v1/notifications/{id}/read` - Mark as read
- `PUT /api/v1/notifications/read-all` - Mark all as read

### Web Routes (từ `routes/web.php`)

- `/app/{any}` → `app.spa` view (React SPA entry point)
- Mount React app vào `#app` element

## Modules Ưu Tiên

1. **Auth** - Authentication module (login, logout, forgot password)
2. **Layout** - MainLayout, AdminLayout, Navigation
3. **Projects** - Projects CRUD, KPIs, alerts, activity
4. **Tasks** - Tasks CRUD, KPIs, alerts, activity

## Cấu Hình Giữ Nguyên

- `frontend/vite.config.ts` - Build config (entry: `frontend/src/main.tsx`)
- `frontend/package.json` - Dependencies
- `frontend/tsconfig.json` - TypeScript config
- `frontend/playwright.config.ts` - E2E config

## Checklist Phases

### Phase 0: Chuẩn Bị ✅
- [x] Xác nhận mount SPA (`resources/views/app/spa.blade.php`)
- [x] Xác nhận header strategy (React header riêng)
- [x] Ghi nhận routes từ `routes/api.php`
- [x] Xác nhận cấu hình giữ nguyên

### Phase 1: Backup & Khung Thư Mục
- [ ] Backup `frontend/src` → `frontend/src.backup/`
- [ ] Tạo khung `frontend/src.new/` với cấu trúc đầy đủ

### Phase 2: Nền Tảng & Cấu Hình
- [ ] Copy shared code (ui/*, api/client.ts)
- [ ] Tạo main.tsx, AppShell.tsx, router.tsx, AuthGuard.tsx

### Phase 3: Module Authentication
- [ ] Tạo auth API client
- [ ] Tạo auth types, store (Zustand)
- [ ] Tạo LoginPage, LoginForm
- [ ] Thêm auth routes

### Phase 4: Layout & Navigation
- [ ] Tạo MainLayout, AdminLayout
- [ ] Tạo AppNavigator, AdminNavigator
- [ ] Tạo ThemeProvider, I18nProvider

### Phase 5: Module Projects
- [ ] Tạo projects API client
- [ ] Tạo projects types, hooks (React Query)
- [ ] Tạo ProjectsListPage, ProjectDetailPage, CreateProjectPage
- [ ] Tạo projects components

### Phase 6: Module Tasks
- [ ] Tạo tasks API client
- [ ] Tạo tasks types, hooks
- [ ] Tạo TasksListPage, TaskDetailPage, CreateTaskPage
- [ ] Tạo tasks components

### Phase 7: Cắt Chuyển & Xóa Cũ ✅
- [x] Verification (QA, E2E tests)
- [x] Migration (src → src.old, src.new → src)
- [x] Cleanup (src.old và src.backup giữ lại để backup, có thể xóa sau khi verify ổn định)

### Phase 8: Mở Rộng Modules
- [ ] Clients module
- [ ] Quotes module
- [ ] Documents module
- [ ] Users & Reports modules

### Phase 9: Testing, Hiệu Năng, Tài Liệu
- [ ] Unit tests
- [ ] Integration tests
- [ ] E2E tests
- [ ] Performance optimization
- [ ] Documentation

