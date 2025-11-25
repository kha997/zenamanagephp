# Cursor Consistency Fix Guide – ZenaManage

Mục tiêu: chuẩn hóa các phần còn không nhất quán giữa kiến trúc, UI, API và test trong repo `zenamanage`, bám theo các chuẩn đã được mô tả trong các tài liệu như `docs/Frontend-Guidelines.md`, `docs/RFC-UI-Standardization.md`, `docs/component-inventory.md`, `docs/header-inventory.csv`, `docs/API_DOCUMENTATION.md`, v.v.

> Lưu ý: Mỗi cụm việc dưới đây nên đi kèm PR nhỏ, có test, và cập nhật docs nếu cần.

---

## 1. Header và Layout (React + Blade)

**Nguồn sự thật (theo RFC + inventory)**  
- Header chuẩn:  
  - React: `src/components/ui/header/HeaderShell.tsx` (cho Blade wrapper) và `frontend/src/components/layout/HeaderShell.tsx` (cho React routes)  
  - Blade: `<x-shared.header-wrapper>` → `resources/views/components/shared/header-wrapper.blade.php` (ACTIVE, đang được dùng)  
  - ⚠️ **Lưu ý**: `header-standardized.blade.php` KHÔNG TỒN TẠI trong codebase. RFC nói dùng `<x-shared.header-standardized>` nhưng implementation thực tế dùng `<x-shared.header-wrapper>`.  
- `docs/RFC-UI-Standardization.md`: mọi Blade surface phải dùng `<x-shared.header-standardized>` (cần tạo alias hoặc cập nhật RFC để phản ánh thực tế).  
- `docs/header-inventory.csv` + `docs/component-inventory.md`: liệt kê chuẩn hóa vs legacy.

**Vấn đề không nhất quán**  
1. Có hai "dòng" HeaderShell với API và mục đích khác nhau:  
   - `frontend/src/components/layout/HeaderShell.tsx` (Apple-style, dùng trong `frontend/src/app/layouts/MainLayout.tsx` cho React routes)  
   - `src/components/ui/header/HeaderShell.tsx` (Blade wrapper, dùng trong `header-wrapper.blade.php` cho Blade views)  
   - **Phân tích**: Hai components này phục vụ contexts khác nhau (React SPA vs Blade SSR), cần quyết định: giữ riêng với shared base hoặc merge.  
2. **Mismatch giữa RFC và Implementation**:  
   - RFC (`docs/RFC-UI-Standardization.md`) nói dùng `<x-shared.header-standardized>`  
   - Implementation thực tế dùng `<x-shared.header-wrapper>` (file `header-standardized.blade.php` không tồn tại)  
   - **Giải pháp**: Tạo alias `header-standardized.blade.php` → include `header-wrapper`, hoặc cập nhật RFC để phản ánh thực tế.  
3. Inventory (`header-inventory.csv`) đánh dấu:  
   - `header-standardized` là `UNUSED` (thực tế: KHÔNG TỒN TẠI)  
   - `header-wrapper` là ACTIVE và đang được dùng trong layouts  
   - `header` legacy vẫn tồn tại như mount point.

**Yêu cầu Cursor thực hiện**  

1. **Đồng nhất HeaderShell React canonical**  
   - Đặt `src/components/ui/header/HeaderShell.tsx` làm canonical.  
   - Nếu `frontend/src/components/layout/HeaderShell.tsx` khác API hoặc props, hãy:  
     - Hoặc refactor để nó trở thành thin wrapper dùng lại canonical HeaderShell.  
     - Hoặc migrate code về canonical component, sau đó loại bỏ bản trùng/legacy.  
   - Cập nhật test tương ứng:  
     - Đảm bảo `frontend/src/components/layout/__tests__/HeaderShell.test.tsx` và bất kỳ test khác trỏ về component canonical, không tạo thêm component thứ ba.

2. **Chuẩn hóa Blade header usage**  
   - **Thực trạng**: Tất cả layout Blade chính đã dùng `<x-shared.header-wrapper>` (đúng):  
     - `resources/views/layouts/app.blade.php`  
     - `resources/views/layouts/admin.blade.php`  
   - **Vấn đề**: RFC nói dùng `<x-shared.header-standardized>` nhưng component này không tồn tại.  
   - **Giải pháp**:  
     - Option A: Tạo alias `header-standardized.blade.php` → include `header-wrapper` (backward compatible với RFC)  
     - Option B: Cập nhật RFC để phản ánh thực tế dùng `header-wrapper`  
   - Kiểm tra các view app quan trọng (đảm bảo dùng `header-wrapper` hoặc alias mới):  
     - `resources/views/app/dashboard/index.blade.php`  
     - `resources/views/app/projects/index.blade.php`  
     - `resources/views/app/projects-new.blade.php`  
     - `resources/views/app/templates/index.blade.php`  
     - `resources/views/app/reports/index.blade.php`  
     - `resources/views/app/calendar.blade.php`  
   - Nếu view nào vẫn include header cũ (`header.blade.php`, `LegacyHeader`, hoặc custom header), migrate sang `<x-shared.header-wrapper>` (hoặc alias `header-standardized` nếu tạo).

3. **Dọn dẹp legacy header**  
   - `resources/views/components/shared/header.blade.php` và `frontend/src/components/LegacyHeader.jsx` cần được:  
     - Đặt rõ trạng thái `legacy`/`deprecate` đúng theo `docs/component-inventory.md`.  
     - Block việc sử dụng mới (có thể bằng comment cảnh báo và/hoặc eslint rule nếu có).  
   - Cập nhật `docs/header-inventory.csv` và `docs/component-inventory.md` sau khi:  
     - Tạo alias `header-standardized` (nếu chọn Option A) hoặc cập nhật RFC (nếu chọn Option B).  
     - Legacy header không còn gọi trong layout chính.

4. **Test & docs**  
   - Thêm/điều chỉnh test:  
     - Vitest: specs cho HeaderShell và integration với layout (`MainLayout`, etc.).  
     - Playwright: specs có tag `@header` cho các route chính (dashboard, projects, documents) kiểm tra:  
       - Header render đúng component standardized.  
       - Behavior: menu, theme toggle, notifications.  
   - Cập nhật `docs/RFC-UI-Standardization.md` (nếu cần) để phản ánh vị trí thực tế của canonical HeaderShell và Blade facade.

---

## 2. Frontend kiến trúc và duplication (React vs Blade, frontend vs src)

**Vấn đề không nhất quán**  
1. Hai “gốc” frontend song song:  
   - `frontend/` (modern React app, vitest, Playwright).  
   - `src/` chứa UI header canonical và một số libs (`src/lib/menu/filterMenu.ts`).  
2. Một số component “cùng domain” nhưng sống khác tree:  
   - HeaderShell và PrimaryNav xuất hiện dưới cả `frontend/src/components/layout` và `src/components/ui/header`.

**Yêu cầu Cursor thực hiện**  

1. **Xác định rõ canonical frontend root**  
   - Chọn một root canonical cho UI React (theo `docs/Frontend-Guidelines.md` và các config như `frontend/vitest.config.ts`, `frontend/playwright.config.ts`).  
   - Đặt `src/components/ui/header` cùng namespace với phần còn lại (nếu cần, move sang `frontend/src/components/...`) hoặc ngược lại, nhưng tránh hai “hệ” khác nhau.

2. **Di chuyển/alias component UI chuẩn**  
   - HeaderShell, PrimaryNav, và các header subcomponents (`Hamburger`, `MobileSheet`, `NotificationsOverlay`, `SearchOverlay`, `SearchToggle`, `UserMenu`) cần nằm trong cùng một system và path rõ ràng.  
   - Cập nhật import khắp nơi (HeaderShell usage, `header-wrapper.blade.php` nếu có integration).  
   - Đảm bảo `docs/component-inventory.md` trỏ đúng path mới.

3. **Cập nhật tooling**  
   - Kiểm tra `frontend/vitest.config.ts`, `frontend/playwright.config.ts`, `tsconfig.json`/`frontend/tsconfig.json` để đảm bảo alias/path mapping trỏ đúng nơi canonical.  
   - Thêm/điều chỉnh linter config `.eslintrc.cjs` nếu path thay đổi.

---

## 3. Alpine/JS duplication (resources vs public)

**Vấn đề không nhất quán**  
- `focus-mode.js` và `rewards.js` tồn tại cả ở:  
  - `resources/js/focus-mode.js`, `resources/js/rewards.js`  
  - `public/js/focus-mode.js`, `public/js/rewards.js`  
- Điều này dễ tạo hai nguồn sự thật: một bản qua Vite, một bản copy trực tiếp trong `public/js`.

**Yêu cầu Cursor thực hiện**  

1. **Chọn single-source cho scripts**  
   - Đặt canonical tại `resources/js/*.js` (build bằng Vite).  
   - Kiểm tra layout Blade (`resources/views/layouts/app.blade.php`, `admin.blade.php`, etc.) xem script được load từ đâu:  
     - Nếu còn include trực tiếp từ `public/js/...`, chuyển sang bundle Vite (hoặc ngược lại, nhưng nhất quán).  

2. **Dọn public duplicates**  
   - ✅ **Đã hoàn thành**: Đã xóa `public/js/focus-mode.js` và `public/js/rewards.js` vì:
     - Source of truth là `resources/js/focus-mode.js` và `resources/js/rewards.js` (imported via Vite trong `app.js`)
     - Không có tham chiếu trực tiếp đến `public/js/` versions
     - Vite build output đi vào `public/build/`, không phải `public/js/`
     - Files trong `public/js/` là legacy/manual copies
   - Xem chi tiết trong `docs/JS_DUPLICATION_AUDIT.md`

3. **Test**  
   - Smoke test trên các page dùng dropdown/focus/rewards (có liên quan tới `docs/DROPDOWN_DEBUG_TOOLS_REPORT.md`): đảm bảo behavior không đổi sau khi dọn duplication.

---

## 4. Routes & API consistency (web.php, app.php, api*.php)

**Vấn đề không nhất quán**  
1. `routes/web.php` hiện chứa rất nhiều debug routes (unauthenticated login, dropdown test, console check, css-conflict-check, etc.) chạy trực tiếp logic với `Auth::login`, query `Project`, v.v.  
   - Điều này trái với comment trong file (“Web routes only render views, all business logic handled via API endpoints”).  
2. `docs/API_DOCUMENTATION.md` định nghĩa chuẩn:  
   - Base URL: `/api/v1`  
   - Response format chuẩn với `success`, `data`, `error`, `timestamp`.  
3. Một số API debug/public endpoints trong routes có response không đúng format này (ví dụ các `public/auth` test endpoints).

**Yêu cầu Cursor thực hiện**  

1. **Tách debug routes ra môi trường develop**  
   - Di chuyển tất cả “Debug route for ...” trong `routes/web.php` sang file `routes/debug.php` (đã tồn tại) hoặc `routes/test.php` tùy convention, và:  
     - Bảo vệ bằng condition `app()->environment('local')` hoặc feature flag.  
     - Đảm bảo không được load trong production `RouteServiceProvider`.  
   - Cập nhật `app/Providers/RouteServiceProvider.php` nếu cần để chỉ load debug routes trong local/testing.

2. **Align web routes với kiến trúc**  
   - Đảm bảo các route trong `routes/web.php` chỉ:  
     - Render view.  
     - Ủy quyền logic sang API (via JS).  
   - Nếu route nào đang làm việc giống API (trả JSON, handle business logic), cân nhắc move sang `routes/api_v1.php` hoặc `routes/api.php` theo chuẩn.

3. **Chuẩn hóa response format API**  
   - Với các route dưới `Route::prefix('public/auth')` và các debug API (`/debug/ping`, `/debug/info`, etc.), nếu được expose trong môi trường không chỉ local, hãy:  
     - Trả về format JSON khớp với `docs/API_DOCUMENTATION.md` (hoặc rõ ràng đánh dấu là internal debug-only và bảo vệ bằng env).  

4. **Test**  
   - Cập nhật/viết thêm test trong các file đã tồn tại:  
     - `tests/Feature/ApiEndpointsTest.php`  
     - `tests/Feature/Api/Auth/AuthenticationModuleTest.php`  
     - `tests/Feature/Auth/EmailVerificationTest.php`, `PasswordChangeTest.php`  
   - Đảm bảo tests phản ánh:  
     - Web chỉ render views.  
     - API endpoints chính trả format JSON consistent.

---

## 5. Task/Project API & frontend contracts

**Vấn đề không nhất quán (nhìn từ file mở sẵn)**  
- Task & project API có nhiều lớp:  
  - Backend: `app/Services/TaskService.php`, `TaskManagementService.php`, `ProjectService.php`…  
  - Tests: `tests/Feature/Api/Tasks/TasksContractTest.php`, `MoveTaskEndpointTest.php`, `TaskStatusSyncTest.php`, `ProjectApiTest.php`, `ProjectRepositoryTest.php`.  
  - Frontend:  
    - `frontend/src/entities/tasks/api.ts`, `frontend/src/entities/tasks/hooks.ts`  
    - `frontend/src/entities/app/documents/__tests__/documents-api.test.ts` (dùng contract tương tự).  
- Docs: `docs/API_DOCUMENTATION.md` mô tả format chuẩn, nhưng frontend có thể đang dùng response shape riêng (có thể thiếu `success`, `timestamp`, hoặc field casing không đồng bộ).

**Yêu cầu Cursor thực hiện**  

1. **Đồng bộ data contract giữa backend & frontend**  
   - Dò tất cả API dùng trong:  
     - `frontend/src/entities/tasks/api.ts`  
     - `frontend/src/entities/dashboard/api.ts`  
     - `frontend/src/entities/app/documents/api` (nếu có).  
   - So sánh với các endpoint tương ứng được mô tả trong `docs/API_DOCUMENTATION.md` và test backend.  
   - Nếu có mismatch (vd: frontend assume `items` nhưng backend trả `data.tasks`, hoặc ngược lại):  
     - Chuẩn hóa theo contract chính thức trong docs/tests.  
     - Cập nhật TypeScript types (interfaces) để phản ánh cấu trúc JSON đúng.

2. **Cập nhật tests**  
   - Điều chỉnh `documents-api.test.ts`, `TasksContractTest.php` và các contract test khác để fail nếu:  
     - response thiếu trường bắt buộc (vd: `success`, `data`, `message`, `timestamp`).  
   - Đảm bảo msw fixtures `tests/msw/fixtures/tasks.json` khớp với contract mới.

---

## 6. E2E / UI test naming & structure

**Vấn đề không nhất quán**  
- Trước đây E2E tests tồn tại ở thư mục `tests/E2E/` (uppercase) trong khi configs bắt đầu chuyển sang `tests/e2e/` (lowercase), gây lệch giữa code và tài liệu.

**Yêu cầu Cursor thực hiện**  

1. **Tên thư mục & pattern nhất quán**  
   - ✅ **Đã hoàn thành**: Đã cập nhật configs Playwright để dùng `tests/e2e/` (lowercase):
     - `playwright.config.ts` - Updated `testDir` và `globalSetup`
     - `playwright.phase3.config.ts` - Updated `testDir` và `globalSetup`
     - `playwright.auth.config.ts` - Updated `testDir` và `globalSetup`
   - ✅ **Đã hoàn thành**: Thư mục thực tế đã được rename từ `tests/E2E/` sang `tests/e2e/` để đảm bảo cross-platform compatibility.
   - ✅ **Đã hoàn thành**: Đã cập nhật path trong tất cả scripts hoặc docs tham chiếu tìm thấy từ `tests/E2E/` sang `tests/e2e/`.

2. **Tag & grouping test**  
   - Áp dụng tag nhất quán (vd: `@smoke`, `@header`, `@tasks`, `@projects`) trong specs để:  
     - Dòng `pnpm -w frontend test:ui:smoke` chạy đúng subset.  
   - Cập nhật `tests/e2e/smoke/README.md` và `docs/E2E_TESTING_STRATEGY.md` nếu có.

---

## 7. Docs & index files sync

**Vấn đề**  
- Rất nhiều docs “index” và “summary” tồn tại:  
  - `DOCUMENTATION_INDEX.md`, `COMPLETE_SYSTEM_DOCUMENTATION.md`, `TEST_SUITE_SUMMARY.md`, `API_DOCUMENTATION.md`, `ComponentLibraryGuide.md`, `Frontend-Guidelines.md`, `AGENT_COORDINATION_HUB.md`, v.v.  
- Một số docs mô tả trạng thái “mục tiêu” (canonical) nhưng code hiện tại chưa theo kịp (vd: header standardization, component inventory).

**Yêu cầu Cursor thực hiện**  

1. **Cập nhật index docs sau khi chỉnh code**  
   - Mỗi khi hoàn thành một nhóm fix consistency ở trên, cập nhật:  
     - `DOCUMENTATION_INDEX.md` để link tới doc/section mới nhất.  
     - `COMPLETE_SYSTEM_DOCUMENTATION.md` với note ngắn về “UI header standardized”, “Routes refactored for architecture compliance”, etc.  

2. **Đảm bảo inventory “mirror”**  
   - Sau khi move/refactor component UI:  
     - Cập nhật cả `docs/component-inventory.md` và `docs/component-inventory.csv` như note ở cuối file inventory.  
   - Tương tự cho header inventory `.md` và `.csv`.

---

## 8. Checklist tóm tắt cho Cursor

1. Header & layout  
   - [ ] Phân tích và quyết định strategy cho 2 HeaderShell (merge vs keep separate với shared base).  
   - [ ] Tạo alias `header-standardized.blade.php` hoặc cập nhật RFC để phản ánh thực tế dùng `header-wrapper`.  
   - [ ] Legacy headers đánh dấu rõ và không được dùng mới.  

2. Frontend structure  
   - [ ] Gộp/alias giữa `frontend/src` và `src/components/ui/header` cho header & nav components.  
   - [ ] Cập nhật tsconfig, vitest, Playwright config theo path canonical.  

3. JS duplication  
   - [ ] Chọn single source cho `focus-mode.js`, `rewards.js`.  
   - [ ] Loại bỏ/bảo trì đúng cách các bản trong `public/js`.  

4. Routes & API  
   - [ ] Di chuyển debug web routes vào nhóm chỉ chạy ở local/test.  
   - [ ] Web routes chỉ render view / bootstrap frontend; business logic nằm ở API routes.  
   - [ ] Public / debug API align với `docs/API_DOCUMENTATION.md` hoặc được bảo vệ bởi env.  

5. Tasks/Projects contracts  
   - [ ] Đồng bộ `frontend/src/entities/*/api.ts` với backend + docs.  
   - [ ] Cập nhật contract tests (PHP + TS + MSW fixtures).  

6. E2E tests  
   - [x] Chuẩn hóa casing trong configs: Đã cập nhật Playwright configs để dùng `tests/e2e/` (lowercase).  
   - [ ] Cập nhật tất cả docs references từ `tests/E2E/` sang `tests/e2e/`.  
   - [ ] Đảm bảo tags (`@header`, `@smoke`, etc.) nhất quán với scripts và docs.  

7. Docs  
   - [ ] Sau mỗi nhóm fix, cập nhật `DOCUMENTATION_INDEX.md`, `COMPLETE_SYSTEM_DOCUMENTATION.md`, inventories liên quan.  
