# 🚀 Staging Deployment Checklist - Routes Consolidation & Navbar Updates

**Date:** 2025-01-XX  
**Feature Branch:** `feature/repo-cleanup`  
**Target Branch:** `develop` (for staging)  
**Type:** Routes Consolidation + Navigation Updates

---

## 📋 Pre-Deployment Checklist

### ✅ Code Changes Review
- [x] Routes consolidated from Blade to React
- [x] Navbar component updated with all routes
- [x] RBAC implemented for Admin link
- [x] All tests passing (154/154)
- [x] Documentation updated

### 📝 Files Changed Summary

#### Frontend Changes (React)
1. **Navigation & Routing:**
   - `frontend/src/components/Navbar.tsx` - Updated with all routes + RBAC
   - `frontend/src/app/router.tsx` - Route configuration
   - `frontend/src/app/AppShell.tsx` - AuthProvider integration
   - `frontend/src/contexts/AuthContext.tsx` - Auth context wrapper

2. **Page Components:**
   - `frontend/src/pages/CalendarPage.tsx` - Updated to use hooks
   - `frontend/src/pages/SettingsPage.tsx` - Updated to use hooks
   - `frontend/src/pages/TeamPage.tsx` - Updated to use hooks

#### Backend Changes (Laravel)
3. **Route Configuration:**
   - `routes/app.php` - Disabled Blade routes (commented out)

#### Testing
4. **Test Files:**
   - `frontend/src/components/__tests__/Navbar.test.tsx` - New tests (16 tests)
   - `frontend/src/app/__tests__/router.test.tsx` - New tests (19 tests)
   - `frontend/e2e/navigation.spec.ts` - E2E tests (22 tests)

#### Documentation
5. **Documentation:**
   - `ROUTES_CONSOLIDATION_SUMMARY.md` - Routes consolidation summary
   - `ROUTES_TREE_MAP.md` - Routes tree map
   - `SYSTEM_PAGES_DIAGRAM.md` - System pages overview
   - `TESTING_SUMMARY.md` - Testing results and findings

---

## 🔄 Deployment Process

### Option 1: Automated CI/CD (Recommended)

**Steps:**
1. Merge feature branch to `develop`
2. Push to `develop` triggers automatic staging deployment
3. GitHub Actions handles:
   - Build Docker images
   - Deploy to staging server
   - Run migrations
   - Cache optimization
   - Health checks

### Option 2: Manual Workflow Dispatch

If manual trigger is needed:
1. Go to GitHub Actions
2. Select "Automated Deployment" workflow
3. Click "Run workflow"
4. Select:
   - Environment: `staging`
   - Branch: `develop` (or feature branch if merging first)

---

## ✅ Pre-Merge Checklist

Before merging to `develop`:

- [ ] **All tests passing:**
  ```bash
  cd frontend && npm test -- --run
  # Expected: 154 passed | 1 skipped | 3 todo (158)
  ```

- [ ] **No linter errors:**
  ```bash
  cd frontend && npm run lint
  ```

- [ ] **TypeScript compilation:**
  ```bash
  cd frontend && npm run type-check
  ```

- [ ] **Build succeeds:**
  ```bash
  cd frontend && npm run build
  ```

- [ ] **Code review completed:**
  - [ ] Routes consolidation verified
  - [ ] Navbar RBAC verified
  - [ ] Test coverage verified

- [ ] **Documentation updated:**
  - [ ] ROUTES_CONSOLIDATION_SUMMARY.md
  - [ ] TESTING_SUMMARY.md
  - [ ] Route changes documented

---

## 🔀 Merge Strategy

### Step 1: Prepare Feature Branch
```bash
# Ensure all changes are committed
git status

# If changes uncommitted, commit them
git add .
git commit -m "feat: Consolidate routes to React and update Navbar with RBAC

- Consolidate main app routes from Blade to React
- Update Navbar component with all routes and active states
- Implement RBAC for Admin link visibility
- Add comprehensive tests (35 tests: 16 Navbar + 19 Router)
- Update documentation

Closes #XXX"
```

### Step 2: Merge to Develop
```bash
# Switch to develop branch
git checkout develop

# Pull latest changes
git pull origin develop

# Merge feature branch
git merge feature/repo-cleanup --no-ff -m "Merge feature/repo-cleanup: Routes consolidation and Navbar updates"

# Push to trigger deployment
git push origin develop
```

### Alternative: Create Pull Request
1. Push feature branch to remote:
   ```bash
   git push origin feature/repo-cleanup
   ```
2. Create PR from `feature/repo-cleanup` → `develop`
3. Review and merge PR
4. Deployment triggers automatically

---

## 📊 Post-Deployment Verification

After deployment to staging, verify:

### 1. Application Health
- [ ] **Health endpoint:**
  ```bash
  curl -f https://staging.zenamanage.com/health
  ```

- [ ] **API health:**
  ```bash
  curl -f https://staging-api.zenamanage.com/health
  ```

### 2. Frontend Build
- [ ] **Build artifacts created:**
  - `frontend/dist/` directory exists
  - Assets properly compiled

- [ ] **React app loads:**
  - Navigate to `https://staging.zenamanage.com/app/dashboard`
  - Verify React app initializes

### 3. Route Functionality
- [ ] **All routes accessible:**
  - `/app/dashboard` - Dashboard page
  - `/app/projects` - Projects page
  - `/app/tasks` - Tasks page
  - `/app/documents` - Documents page
  - `/app/team` - Team page
  - `/app/calendar` - Calendar page
  - `/app/alerts` - Alerts page
  - `/app/preferences` - Preferences page
  - `/app/settings` - Settings page

### 4. Navigation
- [ ] **Navbar displays correctly:**
  - All navigation links visible
  - Active state highlighting works
  - Admin link only visible for admin users

### 5. RBAC Testing
- [ ] **Admin link visibility:**
  - Login as regular user → Admin link NOT visible
  - Login as admin user → Admin link visible
  - Test different role formats (admin, super_admin, Admin, SuperAdmin)

### 6. Routes Consolidation
- [ ] **Blade routes disabled:**
  - Verify old Blade routes return 404 or redirect
  - React routes work correctly

### 7. Performance
- [ ] **Response times:**
  - Dashboard loads < 500ms
  - API responses < 300ms

---

## 🧪 Manual Testing Checklist

### Navigation Testing
- [ ] Click Dashboard link → Navigates correctly
- [ ] Click Projects link → Navigates correctly
- [ ] Click Tasks link → Navigates correctly
- [ ] Click Documents link → Navigates correctly
- [ ] Click Team link → Navigates correctly
- [ ] Click Calendar link → Navigates correctly
- [ ] Click Alerts link → Navigates correctly
- [ ] Click Preferences link → Navigates correctly
- [ ] Click Settings link → Navigates correctly
- [ ] Active route highlighting works

### RBAC Testing
- [ ] Regular user: Admin link NOT visible
- [ ] Admin user: Admin link visible
- [ ] Super admin user: Admin link visible
- [ ] Click Admin link (if visible) → Navigates to admin dashboard

### Route Testing
- [ ] All `/app/*` routes load React components
- [ ] No Blade template conflicts
- [ ] 404 handling works for unknown routes

---

## 📝 Deployment Logging

### Deployment Information
- **Deployment Date:** TBD
- **Deployed By:** TBD
- **Deployment Method:** [Automated CI/CD | Manual]
- **Git Commit:** TBD
- **Git Branch:** `develop`
- **Staging URL:** `https://staging.zenamanage.com`

### Post-Deployment Checks
- [ ] Deployment logs reviewed
- [ ] Health checks passed
- [ ] Smoke tests passed
- [ ] Manual testing completed
- [ ] No errors in logs

---

## 🐛 Rollback Plan

If issues are detected:

### Quick Rollback
```bash
# SSH to staging server
ssh staging-server

# Navigate to app directory
cd /opt/zenamanage

# Rollback to previous commit
git reset --hard HEAD~1

# Rebuild and restart
docker-compose -f docker-compose.prod.yml up -d --build

# Clear caches
docker-compose -f docker-compose.prod.yml exec app php artisan cache:clear
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
```

### Automated Rollback
- GitHub Actions includes automatic rollback on failure
- Monitors health checks and smoke tests
- Rolls back if any check fails

---

## 📧 Stakeholder Notification

After successful deployment:

### Notify:
- [ ] QA Team - Ready for testing
- [ ] Product Owner - Feature deployed to staging
- [ ] Development Team - Deployment complete
- [ ] DevOps Team - Monitor staging environment

### Notification Template:
```
Subject: Routes Consolidation & Navbar Updates Deployed to Staging

The following changes have been deployed to staging:

Features:
- Consolidated routes from Blade to React
- Updated Navbar with all routes and active states
- Implemented RBAC for Admin link visibility
- Added comprehensive test coverage (35 tests)

Testing Status:
- All unit tests passing (154/154)
- All integration tests passing
- Manual testing ready

Staging URL: https://staging.zenamanage.com

Please verify:
1. All routes navigate correctly
2. Navbar displays all links
3. Admin link visibility based on user roles
4. No regressions in existing functionality

Documentation:
- ROUTES_CONSOLIDATION_SUMMARY.md
- TESTING_SUMMARY.md

Ready for UAT.
```

---

## 🔍 Monitoring

### Monitor for:
- [ ] Error rates (should be < 1%)
- [ ] Response times (p95 < 500ms)
- [ ] Memory usage (within limits)
- [ ] API response times (p95 < 300ms)
- [ ] User-reported issues

### Monitoring Duration:
- **First 24 hours:** Continuous monitoring
- **First week:** Daily checks
- **Then:** Regular monitoring

---

## ✅ Deployment Sign-off

- [ ] **Code Review:** Completed
- [ ] **Tests:** All passing
- [ ] **Documentation:** Updated
- [ ] **Pre-deployment Checks:** Completed
- [ ] **Deployment:** Successful
- [ ] **Post-deployment Verification:** Completed
- [ ] **Stakeholders:** Notified

**Deployment Status:** ⏳ Pending  
**Ready for Production:** ⏳ After UAT approval

---

**Last Updated:** 2025-01-XX  
**Maintained By:** Development Team

