# ROUND 208 – PROJECT HISTORY: SHOW TASK ACTIVITIES IN TIMELINE

**Date:** January 2025  
**Status:** ✅ Complete  
**Purpose:** Display task-related activities in the project history timeline

---

## TL;DR

**Round 208** đã hoàn thành việc hiển thị **task activities** trong project history timeline. Backend đã có sẵn logging từ Round 206, Round 208 tập trung vào việc đảm bảo metadata đầy đủ và frontend rendering.

### Thành tựu chính:

1. ✅ **Enhanced metadata**: Bổ sung direct metadata fields (`status_before`, `status_after`, `is_completed_before`, `is_completed_after`, `due_date_before`, `due_date_after`, `completed_at_before`) cho dễ truy cập từ frontend
2. ✅ **Frontend rendering**: `ProjectHistorySection` giờ hiển thị 4 loại task events với text dễ đọc
3. ✅ **Filter support**: Thêm task-related actions và `ProjectTask` entity type vào filter dropdowns
4. ✅ **Backward compatible**: Giữ nguyên `changes` object trong metadata để đảm bảo backward compatibility

---

## Implementation Details by File

### 1. Backend – Enhanced Metadata

#### File: `app/Models/ProjectActivity.php`

**Updated method: `logProjectTaskUpdated()`**

- **Round 208 enhancement**: Thêm direct metadata fields cho dễ truy cập từ frontend:
  - `status_before`, `status_after`
  - `is_completed_before`, `is_completed_after`
  - `due_date_before`, `due_date_after` (nếu có thay đổi)
- **Backward compatibility**: Giữ nguyên `changes` object trong metadata
- **Logic**: Extract values từ `changes.before` và `changes.after` để tạo direct fields

**Updated method: `logProjectTaskMarkedIncomplete()`**

- **Round 208 enhancement**: Thêm `completed_at_before` vào metadata
- **Parameter change**: Thêm optional parameter `$completedAtBefore` để capture `completed_at` trước khi mark incomplete

#### File: `app/Http/Controllers/Api/V1/App/ProjectTaskController.php`

**Updated method: `incomplete()`**

- **Round 208 enhancement**: Capture `completed_at` trước khi gọi service method
- **Change**: Get task before update để lưu `completed_at_before`, sau đó pass vào logging method

### 2. Frontend – Task Activity Rendering

#### File: `frontend/src/features/projects/components/ProjectHistorySection.tsx`

**Added: Task activity filter options**

- Thêm 4 task-related actions vào `HISTORY_ACTIONS`:
  - `project_tasks_generated_from_template` → "Tasks Generated From Template"
  - `project_task_updated` → "Project Task Updated"
  - `project_task_completed` → "Project Task Completed"
  - `project_task_marked_incomplete` → "Project Task Marked Incomplete"

**Added: ProjectTask entity type**

- Thêm `ProjectTask` vào `ENTITY_TYPES` filter dropdown

**Added: `renderTaskActivityText()` function**

- **Purpose**: Render readable text cho task activities dựa trên `action` và `metadata`
- **Handles 4 actions**:
  1. `project_tasks_generated_from_template`:
     - Format: `"Generated {count} task(s) from template "{template_name}""`
  2. `project_task_completed`:
     - Format: `"Task "{task_name}" marked as completed"`
  3. `project_task_marked_incomplete`:
     - Format: `"Task "{task_name}" marked as incomplete"`
  4. `project_task_updated`:
     - Format: `"Task "{task_name}" status changed: {status_before} → {status_after}"` (nếu có status change)
     - Fallback: `"Task "{task_name}" updated"` (nếu chỉ đổi field khác)

**Updated: Activity rendering logic**

- **Priority**: Task activities hiển thị custom text từ `renderTaskActivityText()`
- **Fallback**: Non-task activities vẫn dùng `item.message` hoặc `item.description`
- **Condition**: Check `entity_type === 'ProjectTask'` hoặc `action.startsWith('project_task')`

---

## Behavior & UX

### User Flow

1. **User thao tác với Tasks** (trong tab Tasks):
   - Toggle complete/incomplete task
   - Update task status
   - Update task due date
   - Backend tự động log activity với metadata đầy đủ

2. **User mở tab History**:
   - Timeline hiển thị các task activities với text dễ đọc
   - Có thể filter theo action hoặc entity type
   - Mỗi activity hiển thị:
     - Action label
     - Entity type badge
     - Custom text (cho task activities) hoặc description (cho activities khác)
     - User name
     - Time ago

### Example Activity Texts

- **Generated from template**: `"Generated 5 task(s) from template "Hồ sơ thầu khách sạn""`
- **Task completed**: `"Task "Khảo sát hiện trạng" marked as completed"`
- **Task incomplete**: `"Task "Triển khai BVTC" marked as incomplete"`
- **Task updated (status change)**: `"Task "Triển khai BVTC" status changed: in_progress → done"`
- **Task updated (other fields)**: `"Task "Khảo sát hiện trạng" updated"`

### Filter Options

**Action Filter:**
- All Actions
- Created
- Updated
- Status Changed
- Document Uploaded
- Document Updated
- Document Deleted
- Document Downloaded
- Document Version Restored
- Task Created
- Task Completed
- **Tasks Generated From Template** (NEW)
- **Project Task Updated** (NEW)
- **Project Task Completed** (NEW)
- **Project Task Marked Incomplete** (NEW)
- Deleted

**Entity Type Filter:**
- All Entities
- Project
- Task
- **Project Task** (NEW)
- Document
- Team Member

---

## Metadata Structure

### `project_tasks_generated_from_template`

```json
{
  "template_id": "ulid",
  "template_name": "string",
  "task_count": 5,
  "task_ids": ["ulid1", "ulid2", ...]
}
```

### `project_task_completed`

```json
{
  "task_id": "ulid",
  "task_name": "string",
  "status": "completed",
  "completed_at": "2025-01-15T10:30:00.000000Z"
}
```

### `project_task_marked_incomplete`

```json
{
  "task_id": "ulid",
  "task_name": "string",
  "status": "in_progress",
  "completed_at_before": "2025-01-15T10:30:00.000000Z"  // NEW in Round 208
}
```

### `project_task_updated`

```json
{
  "task_id": "ulid",
  "task_name": "string",
  "status_before": "in_progress",        // NEW in Round 208
  "status_after": "done",                // NEW in Round 208
  "is_completed_before": false,          // NEW in Round 208
  "is_completed_after": true,            // NEW in Round 208
  "due_date_before": "2025-01-20",       // NEW in Round 208 (if changed)
  "due_date_after": "2025-01-25",        // NEW in Round 208 (if changed)
  "changes": {                            // Kept for backward compatibility
    "before": {
      "status": "in_progress",
      "is_completed": false
    },
    "after": {
      "status": "done",
      "is_completed": true
    }
  }
}
```

---

## Tests

### Manual Testing Flow

1. **Create Template + TaskTemplates**:
   - Tạo Template (type: project)
   - Tạo TaskTemplates cho template đó

2. **Create Project from Template**:
   - Tạo Project từ Template
   - Kiểm tra History:
     - ✅ Thấy event: `"Generated X tasks from template "..."`

3. **Update Tasks**:
   - Vào tab Tasks
   - Tick complete 1 task
   - Đổi status 1 task khác
   - Update due date 1 task khác

4. **Check History**:
   - Mở lại tab History
   - ✅ Thấy event: `"Task '...' marked as completed"`
   - ✅ Thấy event: `"Task '...' status changed: todo → in_progress"`
   - ✅ Thấy event: `"Task '...' updated"` (nếu chỉ đổi due date)

5. **Test Filters**:
   - Filter by action: `project_task_completed` → chỉ thấy completed events
   - Filter by entity type: `ProjectTask` → chỉ thấy task activities
   - Clear filters → thấy tất cả activities

---

## Notes / TODO

### Completed ✅

- Enhanced metadata với direct fields cho dễ truy cập
- Frontend rendering cho 4 loại task events
- Filter support cho task actions và entity type
- Backward compatibility với `changes` object
- Readable text cho task activities

### Pending / TODO ⏳

1. **Link from History → Task List** (Optional, future round):
   - Trong mỗi task activity, cho phép user click để "scroll tới task đó" trong tab Tasks
   - Cần state sharing giữa tab History & tab Tasks
   - Metadata đã có `task_id`, chỉ cần implement navigation logic

2. **Filter by Task** (Optional, future round):
   - Thêm filter để chỉ hiển thị activities của một task cụ thể
   - Có thể dùng `entity_id` filter

3. **Grouping by Date** (Optional, future round):
   - Group activities theo ngày trong timeline
   - Cải thiện UX khi có nhiều activities

4. **Activity Icons** (Optional, future round):
   - Thêm icon riêng cho task activities (✅ / ☑️)
   - Hiện tại dùng text label, có thể enhance với icon

---

## Architecture Compliance

✅ **Backward compatibility**: Giữ nguyên `changes` object trong metadata  
✅ **Metadata structure**: Direct fields cho dễ truy cập, không phá vỡ existing code  
✅ **Frontend rendering**: Tách biệt logic cho task activities vs other activities  
✅ **Filter support**: Đầy đủ filter options cho task-related actions  
✅ **Error handling**: Fallback text nếu metadata thiếu  
✅ **Code quality**: No linting errors, follows project conventions  

---

## Related Rounds

- **Round 206**: Project Task Status & Completion + Activity Logging (Backend)
- **Round 207**: Project Task List UI (Frontend)
- **Round 208**: Project History - Show Task Activities (This round)

---

*Round 208 - Complete. Task activities now visible in project history timeline with readable text and filter support.*

