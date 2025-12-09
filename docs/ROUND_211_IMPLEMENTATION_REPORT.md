ROUND 211 – IMPLEMENTATION REPORT
Chủ đề: Log activity khi reorder ProjectTasks

I. TL;DR
- Thêm activity logging cho hành động reorder tasks trong phase.
- Activity type: `project_tasks_reordered`
- Metadata bao gồm: phase_code, phase_label, task_ids_before, task_ids_after, task_count
- Description tự động: "Reordered {task_count} task(s) in phase '{phase_label}'"
- Bảo toàn multi-tenant isolation và tất cả behavior hiện có.

II. Files Changed
1. Backend
   - `app/Models/ProjectActivity.php`
     + Thêm constant `ACTION_PROJECT_TASKS_REORDERED = 'project_tasks_reordered'`
     + Thêm method `logProjectTasksReordered()` để log activity với đầy đủ metadata
   - `app/Services/ProjectTaskManagementService.php`
     + Gọi `ProjectActivity::logProjectTasksReordered()` sau khi reorder thành công
   - `tests/Feature/Api/V1/App/ProjectTaskReorderApiTest.php`
     + Thêm test `test_it_logs_project_tasks_reordered_activity()` để verify logging behavior

III. Hành vi mới
1. Activity logging
   - Khi reorder tasks thành công, hệ thống tự động log một ProjectActivity entry:
     + `action = 'project_tasks_reordered'`
     + `entity_type = 'project_task'`
     + `metadata` chứa:
       * `phase_code`: Mã phase (nếu có)
       * `phase_label`: Tên phase (nếu có)
       * `task_ids_before`: Mảng ID tasks theo thứ tự cũ
       * `task_ids_after`: Mảng ID tasks theo thứ tự mới
       * `task_count`: Số lượng tasks được reorder
     + `description`: Text mô tả tự động, ví dụ "Reordered 5 task(s) in phase 'TKKT'"

2. Integration với reorder flow
   - Logging được thực hiện trong transaction của reorder operation
   - Chỉ log khi reorder thành công (không có exception)
   - Tenant isolation được đảm bảo qua `tenant_id` trong activity

IV. Technical Details
1. Backend
   - `ProjectActivity::logProjectTasksReordered()`
     + Nhận parameters: tenantId, project, phaseCode, phaseLabel, taskIdsBefore, taskIdsAfter
     + Tự động tính `task_count` từ `taskIdsAfter.length`
     + Tạo description với format: "Reordered {count} task(s) in phase '{label}'"
     + Nếu phase_label không có, dùng phase_code hoặc 'Unknown Phase'
     + Lưu user_id từ Auth::id() hoặc fallback về project.created_by
   - Integration point
     + Gọi trong `ProjectTaskManagementService::reorderTasksForProject()` sau khi update sort_order thành công
     + Nằm trong DB transaction để đảm bảo atomicity

2. Metadata structure
   ```php
   [
       'phase_code' => string|null,
       'phase_label' => string|null,
       'task_ids_before' => array,
       'task_ids_after' => array,
       'task_count' => int
   ]
   ```

V. Testing
1. Backend
   - `test_it_logs_project_tasks_reordered_activity()` trong `ProjectTaskReorderApiTest.php`
     ✅ Verify activity được tạo với đúng action và entity_type
     ✅ Verify metadata chứa đầy đủ thông tin (phase_code, phase_label, task_count, etc.)
     ✅ Verify description format đúng
     ✅ Verify tenant_id và project_id đúng
     ✅ Verify user_id đúng (user thực hiện reorder)

VI. Kết luận & Hướng tiếp theo
- Sau Round 211, mọi lần reorder tasks đều được log vào ProjectActivity.
- History API đã trả về các entries `project_tasks_reordered`.
- Frontend cần được cập nhật (Round 212) để:
  + Hiển thị `project_tasks_reordered` trong action filter dropdown
  + Render custom message cho action này với phase_label và task_count
  + Test coverage cho FE rendering và filtering

---

## Frontend follow-up (Round 212)

Round 212 hoàn thành việc tích hợp frontend cho `project_tasks_reordered` activity:

- **Action filter**: Project history UI giờ đây hiển thị "Tasks Reordered" trong action filter dropdown, cho phép người dùng lọc timeline chỉ hiển thị các sự kiện reorder tasks.

- **Custom rendering**: Timeline entries cho `project_tasks_reordered` hiển thị message tùy chỉnh sử dụng `phase_label` và `task_count` từ metadata:
  - Nếu có `phase_label`: "Reordered {task_count} task(s) in phase '{phase_label}'"
  - Nếu không có `phase_label`: "Reordered {task_count} task(s) (no phase)"
  - Fallback về `description` nếu metadata bị thiếu hoặc malformed

- **Test coverage**: Đã thêm đầy đủ tests cho:
  - Rendering với phase label
  - Rendering không có phase label
  - Fallback khi metadata thiếu
  - Tính toán task_count từ `task_ids_after` khi `task_count` không có
  - Filtering theo action `project_tasks_reordered`

- **Files changed (Round 212)**:
  - `frontend/src/features/projects/components/ProjectHistorySection.tsx`
    + Thêm `project_tasks_reordered` vào `HISTORY_ACTIONS` array
    + Thêm logic render trong `renderTaskActivityText()` cho action này
    + Cập nhật điều kiện hiển thị để bao gồm `project_tasks_reordered`
  - `frontend/src/features/projects/components/__tests__/ProjectHistorySection.test.tsx`
    + Thêm test suite mới cho `project_tasks_reordered` với 5 test cases

Frontend giờ đây đã hoàn toàn đồng bộ với backend logging behavior được giới thiệu trong Round 211.

