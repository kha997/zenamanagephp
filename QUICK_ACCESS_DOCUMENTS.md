# 🚀 ZenaManage - Quick Access Guide

## ✅ System Status

- **Laravel Backend**: ✅ Running at http://localhost:8000
- **React Frontend**: ✅ Running at http://localhost:5173
- **API Base URL**: http://localhost:8000/api/v1

---

## 📍 Document Center - Access URLs

### Main Documents Page
```
http://localhost:5173/app/documents
```

### Document Detail Page (after selecting a document)
```
http://localhost:5173/app/documents/:id
```

---

## 🧪 Test Scenarios

### 1. List Documents
- Navigate to: `http://localhost:5173/app/documents`
- Actions to test:
  - ✅ View document list
  - ✅ Search documents
  - ✅ Filter by project/type
  - ✅ Click "Upload Document" (requires `document.create` permission)
  - ✅ Click "Download" (requires `document.download` permission)
  - ✅ Click "Delete" (requires `document.delete` permission)
  - ✅ Click "View" to open detail page

### 2. Document Detail Page
- Navigate from list: Click "View" on any document
- URL: `http://localhost:5173/app/documents/:id`
- Actions to test:
  - ✅ View current version info
  - ✅ Download current version
  - ✅ Upload new version (requires `document.update`)
  - ✅ Revert to previous version (requires `document.update`)
  - ✅ View version history
  - ✅ View activity log

### 3. File Upload Validation
**Trigger**: Click "Upload Document" button
**Tests to verify**:
- ✅ File size > 10MB → Shows error toast
- ✅ Invalid MIME type → Shows error toast
- ✅ Valid PDF/Word/Excel → Upload succeeds
- ✅ Valid image (JPEG/PNG) → Upload succeeds

### 4. RBAC (Role-Based Access Control)
**Test with different roles**:
- **Admin**: Can perform all actions
- **PM**: Can upload, download, update documents
- **Member**: Can download, view documents
- **Client**: Read-only access to shared documents

**Ways to test**:
1. Change user role in database
2. Reload page and verify buttons are hidden/shown based on permissions

---

## 🔍 Login Credentials

You'll need to log in first. Test credentials (if available):
- **Admin**: admin@zenamanage.local
- **PM**: pm@zenamanage.local
- **Member**: member@zenamanage.local

Or create a new account via registration.

---

## 📊 API Endpoints (Backend)

### Document List
```http
GET http://localhost:8000/api/v1/documents
```

### Document Detail with Versions
```http
GET http://localhost:8000/api/v1/documents/:id
```

### Download Document
```http
GET http://localhost:8000/api/v1/documents/:id/download
```

### Upload New Version
```http
POST http://localhost:8000/api/v1/documents/:id/versions
Content-Type: multipart/form-data

{
  "file": <File>,
  "change_description": "Updated document"
}
```

### Revert Version
```http
POST http://localhost:8000/api/v1/documents/:id/revert
Content-Type: application/json

{
  "version_id": 2,
  "comment": "Reverting to previous version"
}
```

---

## 🎯 Quick Test Checklist

### ✅ Document List Page
- [ ] Page loads without errors
- [ ] Documents display in cards
- [ ] Search input works
- [ ] Filter buttons functional
- [ ] Upload button visible (if has permission)
- [ ] Download button visible (if has permission)
- [ ] Delete button visible (if has permission)
- [ ] View button navigates to detail page

### ✅ Document Detail Page
- [ ] Current version info displays
- [ ] Version history table shows
- [ ] Activity log shows (if available)
- [ ] Download button works
- [ ] Upload new version modal opens
- [ ] Revert modal opens with version selection
- [ ] Upload validation works (10MB limit)
- [ ] Upload validation works (MIME type)

### ✅ RBAC Verification
- [ ] Login as Member → Only view/download buttons
- [ ] Login as PM → Can upload/update
- [ ] Login as Admin → All buttons visible
- [ ] Download requires `document.download` permission
- [ ] Upload requires `document.create` permission

---

## 🛠️ Troubleshooting

### Backend not responding?
```bash
# Check if Laravel is running
curl http://localhost:8000

# Or check process
ps aux | grep "php artisan serve"
```

### Frontend not loading?
```bash
# Check if Vite is running
curl http://localhost:5173

# Or check process
ps aux | grep "vite"
```

### Type errors in browser console?
```bash
# Run type-check to verify
cd frontend && npm run type-check
```

### Test failures?
```bash
# Run tests to verify
cd frontend && npx vitest run src/entities/app/documents/__tests__/documents-api.test.ts
```

---

## 📝 Notes

- Both servers are running in the background
- Use separate terminals to view logs:
  - Laravel logs: `tail -f storage/logs/laravel.log`
  - Frontend logs: Check browser console
- To stop servers, press `Ctrl+C` in each terminal or run: `./stop-system.sh`

---

**Last Updated**: 2024-10-05  
**Status**: ✅ Services Running  
**Ready for Manual QA**: Yes

