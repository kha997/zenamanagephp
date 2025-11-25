# Web Controllers JSON Response Violations

## Summary

This document lists Web controllers that return JSON responses instead of views, violating the architecture principle that Web controllers should only return views.

## Architecture Rule

**Web controllers should ONLY return views (Blade templates).**
- All business logic should be handled via API
- JSON responses should come from API controllers only
- Web controllers are for rendering UI only

## Violations Found

### 1. `InvitationController` (app/Http/Controllers/Web/InvitationController.php)

**Methods returning JSON:**
- `store()` - Returns JSON for invitation creation
- `processAcceptance()` - Returns JSON for invitation acceptance
- `resend()` - Returns JSON for resending invitations
- `cancel()` - Returns JSON for canceling invitations
- `bulkAction()` - Returns JSON for bulk actions

**Recommendation:**
- Move these methods to `Api/V1/App/InvitationsController`
- Keep only view-returning methods in Web controller:
  - `index()` - Returns view ✓
  - `create()` - Returns view ✓
  - `accept()` - Returns view ✓
  - `manage()` - Returns view ✓

### 2. `OptimizedProjectController` (app/Http/Controllers/Web/OptimizedProjectController.php)

**Methods returning JSON:**
- `show()` - Returns JSON for project details
- `update()` - Returns JSON for project updates
- `destroy()` - Returns JSON for project deletion

**Recommendation:**
- These methods should redirect to views or use API endpoints
- `show()` should return `view('app.projects.show')`
- `update()` should redirect after successful update
- `destroy()` should redirect after successful deletion

### 3. `OptimizedDashboardController` (app/Http/Controllers/Web/OptimizedDashboardController.php)

**Methods returning JSON:**
- `getMetrics()` - Returns JSON for dashboard metrics

**Recommendation:**
- Move to `Api/V1/App/DashboardController` (already exists)
- Remove from Web controller

### 4. `AlertController` (app/Http/Controllers/Web/AlertController.php)

**Methods returning JSON:**
- `getAlerts()` - Returns JSON for alerts list

**Recommendation:**
- Move to `Api/V1/App/AlertsController` or `Api/V1/App/DashboardController`
- Keep only `index()` method that returns view

## Controllers Following Best Practices ✓

These Web controllers correctly return only views:

- `UsersController` - All methods return views ✓
- `TasksController` - All methods return views ✓
- `ProjectsController` - All methods return views ✓
- `SubtasksController` - All methods return views ✓
- `TaskCommentsController` - All methods return views ✓
- `TaskAttachmentsController` - All methods return views ✓
- `ClientController` - All methods return views ✓
- `QuoteController` - All methods return views ✓
- `DocumentController` - All methods return views ✓
- `SettingsController` - All methods return views ✓
- `TeamController` - All methods return views ✓

## Action Items

1. **High Priority:**
   - [ ] Move `InvitationController` JSON methods to API controller
   - [ ] Move `OptimizedDashboardController::getMetrics()` to API controller
   - [ ] Move `AlertController::getAlerts()` to API controller

2. **Medium Priority:**
   - [ ] Refactor `OptimizedProjectController` to return views/redirects
   - [ ] Consider deprecating `OptimizedProjectController` in favor of `ProjectsController`

3. **Documentation:**
   - [ ] Update architecture docs to clarify Web vs API controller usage
   - [ ] Add code review checklist item for Web controller JSON responses

## Testing

See `tests/Feature/Web/WebRoutesReturnViewsTest.php` for automated tests verifying Web routes return views.

