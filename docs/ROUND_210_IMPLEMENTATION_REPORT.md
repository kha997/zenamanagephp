ROUND 210 – IMPLEMENTATION REPORT
Chủ đề: Drag & Drop reorder ProjectTasks trong từng phase

I. TL;DR
- Thêm drag & drop để PM kéo thả đổi thứ tự task trong cùng phase.
- Thứ tự mới được persist xuống DB qua sort_order.
- Bảo toàn:
  + Multi-tenant isolation.
  + Group-by phase UI (Round 209).
  + Toàn bộ behavior cũ (checkbox, status, due date, filter…).
- Dùng react-beautiful-dnd (có sẵn trong repo).
- Có đầy đủ test cho reorder API (happy path + edge cases).

II. Files Changed
1. Backend
   - `app/Http/Requests/ProjectTaskReorderRequest.php`
     + New form request cho reorder, validate payload chung.
   - `app/Services/ProjectTaskManagementService.php`
     + Thêm method `reorderTasksForProject()` chịu trách nhiệm core business logic.
   - `app/Http/Controllers/Api/V1/App/ProjectTaskController.php`
     + Thêm action `reorder()` làm handler endpoint.
   - `routes/api_v1.php`
     + Thêm route `POST /api/v1/app/projects/{proj}/tasks/reorder`.
   - `tests/Feature/Api/V1/App/ProjectTaskReorderApiTest.php`
     + New test cover đầy đủ các case reorder.
2. Frontend
   - `frontend/src/features/projects/api.ts`
     + Thêm function `reorderProjectTasks()`.
   - `frontend/src/features/projects/hooks.ts`
     + Thêm hook `useReorderProjectTasks()`.
   - `frontend/src/features/projects/components/ProjectTaskList.tsx`
     + Thêm logic drag & drop và wire kết API reorder.

III. Hành vi mới
1. Drag & drop reordering
   - PM có thể kéo một task trong cùng phase và thả vào vị trí khác để đổi thứ tự.
   - Sau khi thả:
     + FE build `orderedIds` theo thứ tự mới.
     + Gọi API reorder → `sort_order` được cập nhật xuống DB.
     + Nếu thành công → refetch lại task list.
2. Visual feedback
   - Mỗi task row có icon drag handle (VD: `GripVertical`) ở cột đầu tiên.
   - Hover → cursor đổi sang grab.
   - Khi đang drag:
     + Row có shadow effect rõ ràng.
     + Phase group đang là “drop target” được highlight.
3. Phase isolation
   - Chỉ cho phép reorder trong cùng phase.
   - Cross-phase drag bị ignore:
     + Không call API.
     + UI không áp dụng reorder cross-phase.
     + Đúng scope Round 210.
4. Loading & Error
   - Khi mutation reorder đang pending: disable drag hoặc hạn chế thao tác để tránh spam/race condition.
   - Nếu lỗi: tự động refetch tasks để trả UI về trạng thái đúng theo server.

IV. Technical Details
1. Backend
   - `ProjectTaskReorderRequest`
     + Validate payload:
       * `ordered_ids`: required, array, min:1.
       * `ordered_ids.*`: required, string (ULID), max:255.
       * Reject duplicate IDs → 422.
   - `ProjectTaskManagementService::reorderTasksForProject()`
     + Dùng DB transaction.
     + Fetch tasks theo tenant/project scope:
       * `tenant_id`, `project_id`, `id IN ordered_ids`, exclude soft-deleted.
     + Nếu số tasks fetch ≠ số `ordered_ids` → fail (task không hợp lệ).
     + Gán `sort_order` mới theo thứ tự `ordered_ids` (ví dụ 10, 20, 30 …).
     + Chỉ update tasks nằm trong payload, các task khác không đổi.
     + Tenant scope ngăn reorder task tenant khác.
2. Frontend
   - `react-beautiful-dnd`
     + Tạo `Droppable` theo từng phase và `Draggable` cho mỗi task row.
   - API
     + `reorderProjectTasks(projectId, { orderedIds })`.
   - Hook
     + `useReorderProjectTasks(projectId)` wrap mutation + invalidate query.
   - UI structure
     + Header vẫn dùng `<table><thead>`.
     + Body chuyển sang layout div-based với CSS table-row/table-cell để tương thích DnD và giữ alignment.

V. Important Caveats
1. Table structure
   - Body không dùng `<tbody><tr><td>` nữa mà dùng div + CSS table-like để hợp với `react-beautiful-dnd`.
2. Cross-phase drag
   - Đang ignore theo scope; không giúp user đổi phase bằng drag/drop.
   - Muốn đổi phase phải chỉnh field phase ở nơi khác (Round sau nếu có).
3. History logging
   - Chưa log riêng event “tasks reordered”.
   - Bổ sung activity type `project_tasks_reordered` có thể đưa vào Round 211.
4. Soft-deleted tasks
   - Không tham gia reorder.
   - Nếu ID soft-deleted lọt vào payload → validation/fetch fail để tránh kéo nhầm.

VI. Testing
1. Backend
   - `tests/Feature/Api/V1/App/ProjectTaskReorderApiTest.php` bao phủ các case:
     ✅ Reorder tasks successfully: `sort_order` cập nhật đúng theo `ordered_ids`.
     ✅ Reject tasks not in project: include ID của project khác → fail.
     ✅ Enforce tenant scope: không thể reorder task tenant khác.
     ✅ Reject duplicate IDs: payload có ID trùng → 422.
     ✅ Ignore soft-deleted tasks: soft-deleted tasks không được chấp nhận.
     ✅ Validate required fields: thiếu `ordered_ids` hoặc format sai → 422.
2. Frontend
   - Manual test + flow:
     * Drag trong phase → API call payload đúng → sort hiển thị đúng sau refetch.
     * Drag cross-phase → không send API, không phá UI.
     * Reorder fail → refetch trả về state đúng.

VII. Kết luận & Hướng tiếp theo
- Sau Round 210, task list không còn là “list cứng” theo `sort_order` cố định.
- PM có thể kéo thả để ưu tiên công việc trong từng phase và lưu bền vững ở backend.
- Phase grouping (Round 209) + drag & drop (Round 210) tạo nên mini task board trong view dự án.
