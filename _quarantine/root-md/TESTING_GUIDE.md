# 🧪 TESTING GUIDE - Projects & Tasks Modules

**Ngày tạo:** 2025-01-19  
**Mục đích:** Hướng dẫn chi tiết để test các tính năng đã implement

---

## 🚀 QUICK START

### 1. Đảm bảo Servers đang chạy:
```bash
# Backend (Terminal 1)
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage
php artisan serve

# Frontend (Terminal 2)
cd /Applications/XAMPP/xamppfiles/htdocs/zenamanage/frontend
npm run dev
```

### 2. Truy cập ứng dụng:
- Frontend: http://localhost:5173
- Backend API: http://localhost:8000/api/v1/app

---

## 📋 MANUAL TESTING CHECKLIST

### ✅ Projects Module

#### Test 1: Projects List Page
**URL:** `http://localhost:5173/app/projects`

**Steps:**
1. Navigate to Projects List page
2. Verify KPI Strip displays at top
3. Verify Alert Bar displays (if any alerts)
4. Verify Activity Feed displays at bottom
5. Test Smart Filters:
   - Click "All" filter
   - Click "Active" filter
   - Click "On Hold" filter
   - Click "Completed" filter
   - Click "Cancelled" filter
6. Test Search:
   - Type in search box
   - Verify debounce (wait 300ms)
   - Verify results filter correctly
7. Test Pagination:
   - Click "Next" button
   - Click "Prev" button
   - Click page numbers
8. Test View Modes:
   - Switch to Table view
   - Switch to Card view
   - Switch to Kanban view
9. Test Create Button:
   - Click "Create Project" button
   - Verify navigates to `/app/projects/create`
10. Test Project Card Click:
    - Click on any project card
    - Verify navigates to `/app/projects/{id}`

**Expected Results:**
- ✅ All components load without errors
- ✅ Filters work correctly
- ✅ Search works with debounce
- ✅ Pagination works
- ✅ View modes switch correctly
- ✅ Navigation works

---

#### Test 2: Create Project Page
**URL:** `http://localhost:5173/app/projects/create`

**Steps:**
1. Navigate to Create Project page
2. Verify all form fields render:
   - Name (required)
   - Description
   - Status dropdown
   - Priority dropdown
   - Start Date
   - End Date
   - Budget Total
3. Test Client-side Validation:
   - Try submit without name → Should show error
   - Enter name > 255 chars → Should show error
   - Enter description > 1000 chars → Should show error
   - Enter end_date < start_date → Should show error
4. Fill form with valid data
5. Click "Create Project"
6. Verify loading state during submission
7. Verify success redirect to project detail page
8. Test Cancel button → Should navigate back

**Expected Results:**
- ✅ Form validation works
- ✅ API call succeeds
- ✅ Success redirect works
- ✅ Error messages display correctly

---

#### Test 3: Project Detail Page
**URL:** `http://localhost:5173/app/projects/{project_id}`

**Steps:**

**Overview Tab:**
1. Verify project name displays
2. Verify status badge displays with correct color
3. Verify description displays
4. Verify all project information fields display correctly

**Tasks Tab:**
1. Click "Tasks" tab
2. Verify tasks load from API
3. Verify tasks display with:
   - Title
   - Status badge
   - Priority badge
   - Due date
   - Assignee name
4. Click "Add Task" button
5. Verify navigates to `/app/tasks/create?project_id={id}`
6. Click "Edit" on a task
7. Verify navigates to `/app/tasks/{task_id}/edit`
8. Click "Delete" on a task
9. Verify confirmation dialog appears
10. Confirm deletion
11. Verify task removed from list

**Documents Tab:**
1. Click "Documents" tab
2. Verify documents load from API
3. Verify documents display with:
   - Name/Title
   - File type
   - File size
   - Upload date
4. Click "View" button (if URL available)
5. Click "Download" button (if URL available)
6. Click "Upload Document" button
7. Verify modal/form appears (functionality pending)

**Team Tab:**
1. Click "Team" tab
2. Verify team members load from `project.users`
3. Verify team members display with:
   - Avatar (initial)
   - Name
   - Email
   - Role (if available)
4. Click "Add Member" button
5. Verify modal opens
6. Verify dropdown shows only available users (not already in team)
7. Select a user
8. Click "Add Member"
9. Verify user added to team list
10. Verify modal closes
11. Click "Remove" button on a team member
12. Verify confirmation dialog
13. Confirm removal
14. Verify member removed from list

**Activity Tab:**
1. Click "Activity" tab
2. Verify activities load filtered by project_id
3. Verify activities display correctly

**Quick Actions:**
1. Click "Edit" button → Verify navigates to edit page
2. Click "Archive" button → Verify confirmation → Verify archives
3. Click "Delete" button → Verify confirmation modal → Verify deletes

**Expected Results:**
- ✅ All tabs work correctly
- ✅ Data loads from correct APIs
- ✅ Add/Remove team members work
- ✅ Quick actions work
- ✅ Navigation works

---

#### Test 4: Edit Project Page
**URL:** `http://localhost:5173/app/projects/{project_id}/edit`

**Steps:**
1. Navigate to Edit Project page
2. Verify form pre-fills with existing project data
3. Modify some fields
4. Click "Save Changes"
5. Verify loading state
6. Verify success redirect to project detail
7. Verify changes reflected in detail page
8. Test Cancel button → Should navigate back

**Expected Results:**
- ✅ Form pre-fills correctly
- ✅ Update works
- ✅ Changes persist

---

### ✅ Tasks Module

#### Test 5: Tasks List Page
**URL:** `http://localhost:5173/app/tasks`

**Steps:** (Similar to Projects List)
1. Verify KPI Strip, Alert Bar, Activity Feed
2. Test Smart Filters (Pending, In Progress, Completed, Overdue)
3. Test Search with debounce
4. Test Pagination
5. Test View Modes (Table, Card, Kanban)
6. Test Create Task button
7. Test Task card click navigation

**Expected Results:**
- ✅ All features work as Projects List

---

#### Test 6: Create Task Page
**URL:** `http://localhost:5173/app/tasks/create`

**Steps:**
1. Navigate to Create Task page
2. Verify all form fields:
   - Title (required)
   - Description
   - Status dropdown
   - Priority dropdown
   - Project dropdown (loads from API)
   - Assignee dropdown (loads from API)
   - Due Date
3. Test Project pre-fill:
   - Navigate with `?project_id={id}` param
   - Verify project pre-selected
4. Test Assignees dropdown:
   - Verify loads users from API
   - Verify displays name and email
5. Fill form and submit
6. Verify success redirect (to project if project_id, else to task detail)

**Expected Results:**
- ✅ All dropdowns load correctly
- ✅ Project pre-fill works
- ✅ Form submission works

---

#### Test 7: Task Detail Page
**URL:** `http://localhost:5173/app/tasks/{task_id}`

**Steps:**

**Overview Tab:**
1. Verify task title displays
2. Verify status badge with correct color
3. Verify priority badge with correct color
4. Verify all task information displays

**Comments Tab:**
1. Click "Comments" tab
2. Verify comments component loads
3. Verify comments display (if any)

**Attachments Tab:**
1. Click "Attachments" tab
2. Verify attachments component loads
3. Verify attachments display (if any)

**Activity Tab:**
1. Click "Activity" tab
2. Verify activities filtered by task_id

**Quick Actions:**
1. Click "Edit" → Verify navigates to edit page
2. Click "Delete" → Verify confirmation → Verify deletes

**Expected Results:**
- ✅ All tabs work
- ✅ Data displays correctly

---

#### Test 8: Edit Task Page
**URL:** `http://localhost:5173/app/tasks/{task_id}/edit`

**Steps:**
1. Navigate to Edit Task page
2. Verify form pre-fills with task data
3. Verify assignees dropdown loads
4. Verify project dropdown loads
5. Modify fields and save
6. Verify success redirect

**Expected Results:**
- ✅ Form pre-fills correctly
- ✅ Update works

---

## 🔍 API ENDPOINT TESTING

### Using Browser DevTools:

1. Open Browser DevTools (F12)
2. Go to Network tab
3. Perform actions in UI
4. Verify API calls:
   - Check request URL
   - Check request method
   - Check request payload
   - Check response status
   - Check response data structure

### Using curl:

```bash
# Test Projects KPIs
curl http://localhost:8000/api/v1/app/projects/kpis \
  -H "Accept: application/json"

# Test Tasks List
curl http://localhost:8000/api/v1/app/tasks \
  -H "Accept: application/json"

# Test Users List
curl http://localhost:8000/api/v1/app/users \
  -H "Accept: application/json"
```

---

## 🐛 ERROR SCENARIOS TO TEST

### 1. Network Errors
- Disconnect internet
- Verify error message displays
- Reconnect and verify recovery

### 2. API Errors
- Test with invalid project_id
- Test with unauthorized access
- Test with validation errors
- Verify error messages display correctly

### 3. Form Validation Errors
- Submit empty required fields
- Enter invalid data formats
- Verify validation messages display

---

## 📱 MOBILE RESPONSIVE TESTING

### Test on Mobile Viewport:
1. Open DevTools
2. Toggle device toolbar (Ctrl+Shift+M)
3. Select mobile device (iPhone, Android)
4. Test all pages:
   - Verify layout adapts
   - Verify navigation works
   - Verify forms usable
   - Verify buttons clickable

---

## ✅ TEST RESULTS TEMPLATE

```
Test Date: ___________
Tester: ___________
Browser: ___________
OS: ___________

Projects Module:
- [ ] Projects List: ✅/❌
- [ ] Create Project: ✅/❌
- [ ] Project Detail: ✅/❌
- [ ] Edit Project: ✅/❌

Tasks Module:
- [ ] Tasks List: ✅/❌
- [ ] Create Task: ✅/❌
- [ ] Task Detail: ✅/❌
- [ ] Edit Task: ✅/❌

Issues Found:
1. ___________
2. ___________
```

---

**Last Updated:** 2025-01-19

