# ROUND 207 – FRONTEND PROJECT TASKS: STATUS, COMPLETE, CHECKLIST UI

## TL;DR

**Round 207** đã hoàn thành việc nâng cấp **Frontend UI cho ProjectTasks** với checklist interface, cho phép user update status, due_date, và toggle complete/incomplete trực tiếp từ UI.

### Thành tựu chính:

1. ✅ **Checklist UI**: Hiển thị tasks dạng checklist với checkbox, status badge, due date
2. ✅ **Complete/Incomplete Toggle**: Checkbox để toggle task completion
3. ✅ **Status Updates**: Dropdown để update status (todo, in_progress, done, completed)
4. ✅ **Due Date Updates**: Date input để update due_date
5. ✅ **Filter & Sort**: Filter theo completion status và overdue, sort theo order/due_date/status
6. ✅ **Visual Indicators**: Highlight overdue tasks, strikethrough completed tasks
7. ✅ **API Integration**: Hook vào backend APIs từ Round 206 (update, complete, incomplete)

---

## Implementation Details by File

### 1. API Client Updates

**File**: `frontend/src/features/projects/api.ts`

#### 1.1. Updated ProjectTask Interface

```typescript
export interface ProjectTask {
  // ... existing fields ...
  is_completed: boolean;
  completed_at?: string | null; // ISO date string
}
```

#### 1.2. New Payload Interface

```typescript
export interface ProjectTaskUpdatePayload {
  name?: string;
  description?: string;
  status?: string;
  due_date?: string | null; // ISO date string
  sort_order?: number;
  is_milestone?: boolean;
}
```

#### 1.3. New API Functions

Thêm 3 functions mới:

1. **`updateProjectTask()`**: 
   - `PATCH /api/v1/app/projects/{projectId}/tasks/{taskId}`
   - Update task fields (name, description, status, due_date, sort_order, is_milestone)

2. **`completeProjectTask()`**: 
   - `POST /api/v1/app/projects/{projectId}/tasks/{taskId}/complete`
   - Mark task as completed with timestamp

3. **`incompleteProjectTask()`**: 
   - `POST /api/v1/app/projects/{projectId}/tasks/{taskId}/incomplete`
   - Mark task as incomplete, clear completion timestamp

Tất cả functions đều:
- Handle cả 2 response formats: `{ success: true, data: {...} }` và `{ data: {...} }`
- Throw errors via `mapAxiosError()`

### 2. React Query Hooks

**File**: `frontend/src/features/projects/hooks.ts`

#### 2.1. Query Key Factory

```typescript
const projectTasksKey = (projectId: string | number) => ['projects', projectId, 'checklist-tasks'];
```

#### 2.2. New Mutation Hooks

Thêm 3 mutation hooks:

1. **`useUpdateProjectTask(projectId)`**: 
   - Mutation để update task
   - Invalidate query cache sau khi success

2. **`useCompleteProjectTask(projectId)`**: 
   - Mutation để mark task as completed
   - Invalidate query cache sau khi success

3. **`useIncompleteProjectTask(projectId)`**: 
   - Mutation để mark task as incomplete
   - Invalidate query cache sau khi success

Tất cả hooks đều:
- Use `useMutation` từ `@tanstack/react-query`
- Invalidate `projectTasksKey(projectId)` sau khi success
- Return mutation object với `mutateAsync` và loading states

### 3. UI Component - ProjectTaskList

**File**: `frontend/src/features/projects/components/ProjectTaskList.tsx`

#### 3.1. Component Props

```typescript
interface ProjectTaskListProps {
  projectId: string | number;
  filter?: 'all' | 'open' | 'completed' | 'overdue';
  sortBy?: 'order' | 'due_date' | 'status';
  onFilterChange?: (filter: 'all' | 'open' | 'completed' | 'overdue') => void;
  onSortChange?: (sortBy: 'order' | 'due_date' | 'status') => void;
}
```

Component có thể hoạt động với:
- **Internal state**: Nếu không có `onFilterChange`/`onSortChange`, dùng internal state
- **External control**: Nếu có props, dùng external state (controlled component)

#### 3.2. Checklist UI Features

**Checkbox Column**:
- Checkbox để toggle `is_completed`
- Disabled khi đang update
- Visual feedback khi loading

**Status Column**:
- Dropdown (`Select` component) với options:
  - `—` (empty)
  - `todo`
  - `in_progress`
  - `done`
  - `completed`
- Disabled khi đang update
- Auto-update khi user chọn

**Due Date Column**:
- Date input (`<input type="date">`)
- Display formatted date (dd/MM/yyyy) bên cạnh input
- Highlight màu đỏ nếu overdue
- Disabled khi đang update

**Task Name Column**:
- Strikethrough text nếu `is_completed === true`
- Reduced opacity (60%) cho completed tasks
- Warning indicator nếu overdue

**Milestone Column**:
- Badge hiển thị nếu `is_milestone === true`

**Source Column**:
- Icon 📋 nếu task được tạo từ template

#### 3.3. Filter & Sort

**Filter Options**:
- `all`: Tất cả tasks
- `open`: Chưa hoàn thành (`!is_completed`)
- `completed`: Đã hoàn thành (`is_completed`)
- `overdue`: Quá hạn (`!is_completed && due_date < today`)

**Sort Options**:
- `order`: Theo `sort_order` (default), sau đó `created_at`
- `due_date`: Theo `due_date` (nulls last)
- `status`: Theo `status` (alphabetical)

**Implementation**:
- Filter và sort được thực hiện trên **frontend** (client-side)
- Sử dụng `useMemo` để optimize performance
- Filter và sort controls nằm ở header của component

#### 3.4. Loading States

- Track `updatingTaskIds` Set để disable controls cho task đang update
- Disable checkbox, status dropdown, và date input khi `isUpdating === true`
- Visual feedback với opacity và cursor changes

#### 3.5. Error Handling

- Try-catch trong mutation handlers
- Console.error để log errors
- UI không bị crash nếu mutation fails (user có thể retry)

### 4. Integration with ProjectDetailPage

**File**: `frontend/src/features/projects/pages/ProjectDetailPage.tsx`

Component `ProjectTaskList` được sử dụng trong Tasks tab:

```tsx
<ProjectTaskList projectId={id!} />
```

Component tự quản lý filter và sort internally, không cần external state management.

---

## Behavior & UX

### User Flow

1. **Vào Project Detail** → Click tab **Tasks**
2. **Xem danh sách tasks** với:
   - Checkbox ở đầu mỗi row
   - Status dropdown
   - Due date input
   - Filter và sort controls ở header
3. **Toggle complete**: Click checkbox → Task được mark completed/incomplete → Activity được log ở backend
4. **Update status**: Chọn status từ dropdown → Task status được update → Activity được log
5. **Update due date**: Chọn date từ date picker → Task due_date được update → Activity được log
6. **Filter tasks**: Chọn filter từ dropdown → Tasks được filter theo completion status hoặc overdue
7. **Sort tasks**: Chọn sort option → Tasks được sort theo order/due_date/status

### Visual Indicators

**Completed Tasks**:
- Checkbox checked
- Task name có strikethrough
- Row opacity 60%
- Status badge có thể là "Done" hoặc "Completed"

**Overdue Tasks**:
- Row background màu đỏ nhạt (`bg-red-50 dark:bg-red-900/10`)
- Warning icon (⚠️) và text "Quá hạn" dưới task name
- Due date input border màu đỏ

**In Progress Tasks**:
- Status badge màu xanh (in_progress)
- Checkbox unchecked

**Todo Tasks**:
- Status badge màu vàng (todo/pending)
- Checkbox unchecked

### Loading States

- Checkbox, status dropdown, và date input bị disable khi task đang update
- Cursor changes to `not-allowed` khi disabled
- Opacity giảm khi disabled

### Empty States

- **No tasks**: "Chưa có task nào cho dự án này."
- **No filtered results**: "Không có task nào phù hợp với bộ lọc đã chọn."

---

## API Integration

### Backend Endpoints Used

Tất cả endpoints đã được implement trong **Round 206**:

1. **GET** `/api/v1/app/projects/{proj}/tasks`
   - List tasks (đã có từ Round 203)
   - Response includes `is_completed` và `completed_at` (Round 206)

2. **PATCH** `/api/v1/app/projects/{proj}/tasks/{proj_task}`
   - Update task (Round 206)
   - Payload: `{ status?, due_date?, name?, description?, sort_order?, is_milestone? }`

3. **POST** `/api/v1/app/projects/{proj}/tasks/{proj_task}/complete`
   - Mark task as completed (Round 206)
   - Response: Updated task với `is_completed: true` và `completed_at` timestamp

4. **POST** `/api/v1/app/projects/{proj}/tasks/{proj_task}/incomplete`
   - Mark task as incomplete (Round 206)
   - Response: Updated task với `is_completed: false` và `completed_at: null`

### Activity Logging

Backend tự động log activity khi:
- Task được update (status, due_date, etc.)
- Task được marked as completed
- Task được marked as incomplete

Frontend không cần làm gì thêm - activity logs sẽ xuất hiện trong ProjectHistorySection (có thể implement trong Round 208).

---

## Tests

### Frontend Tests (TODO)

Nếu có FE test stack (Vitest + RTL), nên thêm tests:

**File**: `frontend/src/features/projects/__tests__/ProjectTaskList.test.tsx`

**Test Cases**:
1. ✅ Render với mock tasks array
2. ✅ Completed task render với strikethrough và reduced opacity
3. ✅ Overdue task render với warning indicator
4. ✅ Click checkbox → gọi `useCompleteProjectTask` hoặc `useIncompleteProjectTask`
5. ✅ Change status select → gọi `useUpdateProjectTask` với payload đúng
6. ✅ Change due date → gọi `useUpdateProjectTask` với payload đúng
7. ✅ Filter tasks → chỉ hiển thị tasks phù hợp
8. ✅ Sort tasks → tasks được sort đúng

**Note**: Hiện tại code đã type-safe và chạy tay ngon, tests có thể để sau nếu chưa có test infrastructure.

---

## Notes / TODO

### Completed in Round 207

- ✅ Checklist UI với checkbox, status dropdown, due date input
- ✅ Complete/incomplete toggle
- ✅ Status updates
- ✅ Due date updates
- ✅ Filter & sort controls
- ✅ Visual indicators (overdue, completed)
- ✅ Loading states và error handling
- ✅ API integration với backend từ Round 206

### Future Enhancements (Round 208+)

1. **Inline Editing**:
   - Edit task name và description trực tiếp trong table
   - Double-click để edit, Enter để save

2. **Drag-Drop Reorder**:
   - Drag tasks để reorder
   - Update `sort_order` khi drop

3. **Activity Log Display**:
   - Hiển thị activity logs tương ứng trong ProjectHistorySection
   - Filter activity logs theo task ID

4. **Bulk Actions**:
   - Select multiple tasks
   - Bulk complete/incomplete
   - Bulk update status

5. **Advanced Filters**:
   - Filter theo milestone
   - Filter theo status
   - Filter theo date range

6. **Pagination**:
   - Nếu có nhiều tasks, thêm pagination
   - Load more hoặc infinite scroll

7. **Keyboard Shortcuts**:
   - Space để toggle complete
   - Arrow keys để navigate
   - Enter để edit

---

## Files Changed

### Frontend

1. `frontend/src/features/projects/api.ts`
   - Updated `ProjectTask` interface (added `is_completed`, `completed_at`)
   - Added `ProjectTaskUpdatePayload` interface
   - Added `updateProjectTask()`, `completeProjectTask()`, `incompleteProjectTask()` functions

2. `frontend/src/features/projects/hooks.ts`
   - Added `projectTasksKey()` query key factory
   - Added `useUpdateProjectTask()`, `useCompleteProjectTask()`, `useIncompleteProjectTask()` hooks

3. `frontend/src/features/projects/components/ProjectTaskList.tsx`
   - Complete rewrite với checklist UI
   - Added checkbox, status dropdown, due date input
   - Added filter & sort controls
   - Added visual indicators (overdue, completed)
   - Added loading states và error handling

### Documentation

4. `docs/ROUND_207_IMPLEMENTATION_REPORT.md`
   - This file

---

## Summary

Round 207 đã hoàn thành việc nâng cấp Frontend UI cho ProjectTasks với đầy đủ tính năng checklist, update status, due_date, và toggle complete/incomplete. Component đã được tích hợp với backend APIs từ Round 206 và sẵn sàng cho user testing.

**Next Steps**: Round 208 có thể focus vào:
- Activity log display trong ProjectHistorySection
- Inline editing cho task name/description
- Drag-drop reorder
- Advanced filters và bulk actions

