# Testing Checklist - Sprint 3: Documents & Change-Requests

**Date:** 2025-01-17  
**Status:** Ready for Testing

---

## Pre-Testing Setup

### 1. Environment Variables
```env
# Enable feature flags for testing
FF_APP_DOCUMENTS=true
FF_APP_CHANGE_REQUESTS=true
```

### 2. Database
- Ensure `change_requests` table exists
- Ensure `documents` table exists
- Ensure test tenant has data

### 3. Routes Verification
```bash
# Check routes are registered
php artisan route:list --path=api/v1/app/documents
php artisan route:list --path=api/v1/app/change-requests
```

---

## Documents Module Testing

### 3.1 Documents List Page (`/app/documents`)

**Test Cases:**

- [ ] **Page Loads**
  - Navigate to `/app/documents`
  - Page loads without errors
  - KpiStrip displays (Total, Uploaded, Pending, Approved)
  - AlertBar displays (if alerts exist)
  - ActivityFeed displays recent activity

- [ ] **Search Functionality**
  - Type in search box
  - Results filter correctly
  - Debounce works (300ms delay)

- [ ] **Filters**
  - Filter by project
  - Filter by status
  - Filter by MIME type
  - Filters combine correctly

- [ ] **Pagination**
  - Navigate between pages
  - Page numbers update correctly
  - Per page selector works

- [ ] **Upload Document**
  - Click "Upload Document" button
  - Modal opens
  - Select file (valid types: PDF, Word, Excel, Images, Text)
  - File size validation (max 10MB)
  - Upload succeeds
  - Document appears in list

- [ ] **File Validation**
  - Try uploading file > 10MB → Error shown
  - Try uploading invalid file type → Error shown
  - Try uploading without file → Error shown

- [ ] **RBAC**
  - User without upload permission → Upload button hidden/disabled
  - User without delete permission → Delete button hidden/disabled

- [ ] **Empty State**
  - No documents → Empty state message shown
  - "Create First Document" button works

### 3.2 Document Detail Page (`/app/documents/{id}`)

**Test Cases:**

- [ ] **Page Loads**
  - Navigate to document detail
  - Document info displays correctly
  - KpiStrip shows file size, version, downloads, status
  - AlertBar shows document-specific alerts

- [ ] **Tabs**
  - Overview tab shows document details
  - Activity tab shows activity feed
  - Tab switching works

- [ ] **Download**
  - Click "Download" button
  - File downloads successfully

- [ ] **Delete**
  - Click "Delete" button
  - Confirmation modal appears
  - Confirm deletion
  - Document deleted, redirects to list

- [ ] **RBAC**
  - User without delete permission → Delete button hidden
  - User without download permission → Download button hidden

### 3.3 Create Document Page (`/app/documents/create`)

**Test Cases:**

- [ ] **Form Validation**
  - Submit without file → Error shown
  - Submit with invalid file → Error shown
  - Submit with valid file → Success

- [ ] **File Upload**
  - Select file
  - Add description
  - Add tags (comma-separated)
  - Select project (optional)
  - Toggle public/private
  - Submit form
  - Redirects to document detail

- [ ] **Cancel**
  - Click "Cancel" button
  - Redirects to documents list

### 3.4 Documents Approvals Page (`/app/documents/approvals`)

**Test Cases:**

- [ ] **Page Loads**
  - Navigate to `/app/documents/approvals`
  - Pending documents display
  - Approval actions work (if implemented)

---

## Change Requests Module Testing

### 4.1 Change Requests List Page (`/app/change-requests`)

**Test Cases:**

- [ ] **Page Loads**
  - Navigate to `/app/change-requests`
  - Page loads without errors
  - KpiStrip displays (Total, Pending, Approved, Rejected)
  - AlertBar displays (if alerts exist)
  - ActivityFeed displays recent activity

- [ ] **Search Functionality**
  - Type in search box
  - Results filter correctly
  - Debounce works (300ms delay)

- [ ] **Status Badges**
  - Draft → Gray badge
  - Awaiting Approval → Yellow badge
  - Approved → Green badge
  - Rejected → Red badge

- [ ] **Pagination**
  - Navigate between pages
  - Page numbers update correctly

- [ ] **Create Change Request**
  - Click "Create Change Request" button
  - Navigates to create page

- [ ] **Empty State**
  - No change requests → Empty state message shown
  - "Create First Change Request" button works

### 4.2 Change Request Detail Page (`/app/change-requests/{id}`)

**Test Cases:**

- [ ] **Page Loads**
  - Navigate to change request detail
  - Change request info displays correctly
  - KpiStrip shows status, priority, estimated cost, estimated days
  - AlertBar shows change request-specific alerts

- [ ] **Tabs**
  - Overview tab shows change request details
  - Timeline tab shows status timeline
  - Activity tab shows activity feed
  - Tab switching works

- [ ] **Status: Draft**
  - "Submit for Approval" button visible
  - "Delete" button visible
  - Click "Submit for Approval" → Status changes to "awaiting_approval"

- [ ] **Status: Awaiting Approval**
  - "Approve" button visible
  - "Reject" button visible
  - Click "Approve" → Modal opens
  - Enter approval notes → Submit → Status changes to "approved"
  - Click "Reject" → Modal opens
  - Enter rejection reason → Submit → Status changes to "rejected"

- [ ] **Status: Approved**
  - Timeline shows approval date and notes
  - No action buttons (read-only)

- [ ] **Status: Rejected**
  - Timeline shows rejection date and reason
  - No action buttons (read-only)

- [ ] **Delete (Draft Only)**
  - Change request in draft status
  - Click "Delete" button
  - Confirmation modal appears
  - Confirm deletion
  - Change request deleted, redirects to list

- [ ] **Delete (Non-Draft)**
  - Change request not in draft status
  - Delete button hidden or disabled

### 4.3 Create Change Request Page (`/app/change-requests/create`)

**Test Cases:**

- [ ] **Form Validation**
  - Submit without title → Error shown
  - Submit without description → Error shown
  - Submit without project → Error shown
  - Submit with all required fields → Success

- [ ] **Form Fields**
  - Title (required)
  - Description (required)
  - Project (required, dropdown)
  - Change Type (scope, schedule, budget, quality, other)
  - Priority (low, medium, high, urgent)
  - Due Date (optional, date picker)
  - Estimated Cost (optional, number)
  - Estimated Days (optional, number)

- [ ] **Submit**
  - Fill all required fields
  - Submit form
  - Redirects to change request detail
  - Status is "draft"

- [ ] **Cancel**
  - Click "Cancel" button
  - Redirects to change requests list

---

## API Endpoints Testing

### Documents API

**Base URL:** `/api/v1/app/documents`

- [ ] `GET /api/v1/app/documents` - List documents
  - Returns paginated list
  - Filters work (search, project_id, status, etc.)
  - Tenant isolation verified

- [ ] `GET /api/v1/app/documents/{id}` - Get document
  - Returns document details
  - Tenant isolation verified

- [ ] `POST /api/v1/app/documents` - Upload document
  - File upload works
  - Validation works (file size, MIME type)
  - Tenant isolation verified

- [ ] `PUT /api/v1/app/documents/{id}` - Update document
  - Update works
  - Tenant isolation verified

- [ ] `DELETE /api/v1/app/documents/{id}` - Delete document
  - Delete works
  - Tenant isolation verified

- [ ] `GET /api/v1/app/documents/kpis` - Get KPIs
  - Returns KPI data

- [ ] `GET /api/v1/app/documents/alerts` - Get alerts
  - Returns alerts

- [ ] `GET /api/v1/app/documents/activity` - Get activity
  - Returns activity feed

### Change Requests API

**Base URL:** `/api/v1/app/change-requests`

- [ ] `GET /api/v1/app/change-requests` - List change requests
  - Returns paginated list
  - Filters work (search, status, priority, project_id, etc.)
  - Tenant isolation verified

- [ ] `GET /api/v1/app/change-requests/{id}` - Get change request
  - Returns change request details
  - Tenant isolation verified

- [ ] `POST /api/v1/app/change-requests` - Create change request
  - Create works
  - Validation works
  - Change number auto-generated
  - Tenant isolation verified

- [ ] `PUT /api/v1/app/change-requests/{id}` - Update change request
  - Update works (draft only)
  - Validation works
  - Tenant isolation verified

- [ ] `DELETE /api/v1/app/change-requests/{id}` - Delete change request
  - Delete works (draft only)
  - Tenant isolation verified

- [ ] `POST /api/v1/app/change-requests/{id}/submit` - Submit for approval
  - Submit works (draft only)
  - Status changes to "awaiting_approval"
  - Tenant isolation verified

- [ ] `POST /api/v1/app/change-requests/{id}/approve` - Approve change request
  - Approve works (awaiting_approval only)
  - Status changes to "approved"
  - Approval notes saved
  - Tenant isolation verified

- [ ] `POST /api/v1/app/change-requests/{id}/reject` - Reject change request
  - Reject works (awaiting_approval only)
  - Status changes to "rejected"
  - Rejection reason required and saved
  - Tenant isolation verified

- [ ] `GET /api/v1/app/change-requests/kpis` - Get KPIs
  - Returns KPI data

- [ ] `GET /api/v1/app/change-requests/alerts` - Get alerts
  - Returns alerts

- [ ] `GET /api/v1/app/change-requests/activity` - Get activity
  - Returns activity feed

---

## Cross-Module Testing

### Tenant Isolation

- [ ] **Documents**
  - Tenant A cannot see Tenant B's documents
  - Tenant A cannot access Tenant B's document detail
  - Tenant A cannot delete Tenant B's documents

- [ ] **Change Requests**
  - Tenant A cannot see Tenant B's change requests
  - Tenant A cannot access Tenant B's change request detail
  - Tenant A cannot modify Tenant B's change requests

### RBAC Testing

- [ ] **Documents**
  - User without upload permission → Cannot upload
  - User without delete permission → Cannot delete
  - User without download permission → Cannot download

- [ ] **Change Requests**
  - User without create permission → Cannot create
  - User without update permission → Cannot update
  - User without delete permission → Cannot delete
  - User without approve permission → Cannot approve/reject

### Error Handling

- [ ] **Documents**
  - Invalid file type → Clear error message
  - File too large → Clear error message
  - Network error → Error message shown
  - 404 error → Not found message shown

- [ ] **Change Requests**
  - Invalid status transition → Clear error message
  - Missing required fields → Validation errors shown
  - Network error → Error message shown
  - 404 error → Not found message shown

---

## Performance Testing

- [ ] **Documents List**
  - Page load < 500ms (p95)
  - API response < 300ms (p95)

- [ ] **Change Requests List**
  - Page load < 500ms (p95)
  - API response < 300ms (p95)

- [ ] **File Upload**
  - Upload progress shown
  - Large files (> 10MB) handled gracefully

---

## Browser Compatibility

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

---

## Mobile Responsiveness

- [ ] **Documents**
  - List page responsive
  - Detail page responsive
  - Create page responsive

- [ ] **Change Requests**
  - List page responsive
  - Detail page responsive
  - Create page responsive

---

## Notes

- All tests should be performed in staging environment first
- Monitor error logs during testing
- Check browser console for JavaScript errors
- Verify all API calls return correct status codes
- Test with different user roles (admin, PM, member, client)

---

## Issues Found

(Record any issues found during testing)

1. 
2. 
3. 

---

## Sign-off

- [ ] All test cases passed
- [ ] No critical issues found
- [ ] Ready for canary rollout
- [ ] Documentation updated

**Tester:** _________________  
**Date:** _________________

