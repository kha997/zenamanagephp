# Phase 3 E2E Tests Documentation

## Overview

This directory contains comprehensive End-to-End (E2E) tests for Phase 3 features of the ZenaManage application. Phase 3 focuses on Frontend Integration & Advanced Features.

## Phase 3 Features Tested

### APP-FE-301: Frontend Comment UI Integration
- ✅ Comment creation, editing, and deletion
- ✅ Comment replies and threading
- ✅ Comment pagination
- ✅ Comment types and categorization
- ✅ Real-time comment updates

### APP-FE-302: Kanban React Board with ULID Schema
- ✅ React-based Kanban board display
- ✅ Drag and drop functionality
- ✅ Task status updates
- ✅ ULID schema compliance
- ✅ Task filtering and search
- ✅ Real-time task updates

### APP-BE-401: File Attachments System
- ✅ File upload with validation
- ✅ File categorization and tagging
- ✅ File download and preview
- ✅ File versioning
- ✅ Attachment management
- ✅ File type and size validation

### Real-time Updates
- ✅ WebSocket connection management
- ✅ Real-time comment updates
- ✅ Real-time task status changes
- ✅ Connection loss handling
- ✅ Multi-user collaboration testing

## Test Structure

```
tests/E2E/phase3/
├── phase3-features.spec.ts     # Main Phase 3 test suite
├── fixtures.ts                 # Test fixtures and helpers
├── helpers/                    # Helper classes
│   ├── comment-helper.ts       # Comment testing utilities
│   ├── attachment-helper.ts    # Attachment testing utilities
│   ├── auth-helper.ts          # Authentication utilities
│   └── task-helper.ts          # Task testing utilities
└── setup/
    └── phase3-global-setup.ts  # Global test setup
```

## Test Data

The Phase 3 tests use comprehensive test data created by the `Phase3TestDataSeeder`:

### Test Users
- **pm@phase3-test.local** - Project Manager (password: password)
- **dev@phase3-test.local** - Developer (password: password)
- **designer@phase3-test.local** - Designer (password: password)
- **client@phase3-test.local** - Client (password: password)

### Test Projects
- **Phase 3 Integration Project** - Main test project
- **Kanban Board Test Project** - Kanban-specific testing
- **Real-time Updates Test Project** - Real-time collaboration testing

### Test Tasks
- Tasks in all statuses (backlog, todo, in_progress, blocked, done)
- Various priorities (low, normal, high, urgent)
- Different assignees and creators
- Realistic progress percentages

### Test Comments
- Multiple comments per task
- Various comment types (general, question, suggestion, issue)
- Comment replies and threading
- Internal and external comments

### Test Attachments
- Various file types (PDF, images, documents)
- Different categories (design, report, code, other)
- File versions and metadata
- Download tracking

## Running Phase 3 Tests

### Prerequisites
1. Laravel application running on `http://127.0.0.1:8000`
2. Database seeded with Phase 3 test data
3. Pusher configuration for real-time tests
4. Playwright installed (`npm install`)

### Commands

```bash
# Run all Phase 3 tests
npx playwright test --config=playwright.phase3.config.ts

# Run Phase 3 tests on specific browser
npx playwright test --config=playwright.phase3.config.ts --project=chromium

# Run Phase 3 tests with UI
npx playwright test --config=playwright.phase3.config.ts --ui

# Run Phase 3 tests in headed mode
npx playwright test --config=playwright.phase3.config.ts --headed

# Debug Phase 3 tests
npx playwright test --config=playwright.phase3.config.ts --debug

# Run specific test file
npx playwright test tests/E2E/phase3/phase3-features.spec.ts

# Run specific test
npx playwright test tests/E2E/phase3/phase3-features.spec.ts -g "should create a new comment"

# View test report
npx playwright show-report
```

### Test Categories

```bash
# Run comment tests only
npx playwright test --config=playwright.phase3.config.ts -g "Frontend Comment UI Integration"

# Run Kanban tests only
npx playwright test --config=playwright.phase3.config.ts -g "Kanban React Board"

# Run attachment tests only
npx playwright test --config=playwright.phase3.config.ts -g "File Attachments System"

# Run real-time tests only
npx playwright test --config=playwright.phase3.config.ts -g "Real-time Updates"

# Run integration tests only
npx playwright test --config=playwright.phase3.config.ts -g "Integration Tests"
```

## Test Configuration

### Playwright Configuration
- **Base URL**: `http://127.0.0.1:8000`
- **Timeout**: 60 seconds per test
- **Action Timeout**: 15 seconds
- **Navigation Timeout**: 30 seconds
- **Retries**: 2 on CI, 0 locally
- **Workers**: 1 on CI, parallel locally

### Browser Support
- ✅ Chrome/Chromium
- ✅ Firefox
- ✅ Safari/WebKit
- ✅ Microsoft Edge
- ✅ Mobile Chrome
- ✅ Mobile Safari

### Test Artifacts
- **Screenshots**: On failure only
- **Videos**: On failure only
- **Traces**: On first retry
- **Output Directory**: `test-results/phase3-artifacts/`

## Test Helpers

### CommentHelper
- `createComment(content, type, isInternal)` - Create a new comment
- `replyToComment(commentText, replyContent)` - Reply to a comment
- `editComment(originalText, newText)` - Edit a comment
- `deleteComment(commentText)` - Delete a comment
- `verifyCommentExists(commentText)` - Verify comment exists
- `getCommentCount()` - Get total comment count

### AttachmentHelper
- `uploadAttachment(filePath, name, category)` - Upload a file
- `downloadAttachment(attachmentName)` - Download a file
- `deleteAttachment(attachmentName)` - Delete a file
- `verifyAttachmentExists(attachmentName)` - Verify file exists
- `getAttachmentDetails(attachmentName)` - Get file details

### TaskHelper
- `createTask(name, description, priority)` - Create a new task
- `updateTaskStatus(taskName, newStatus)` - Update task status
- `assignTask(taskName, assignee)` - Assign task to user
- `verifyTaskExists(taskName)` - Verify task exists

### AuthHelper
- `login(email, password)` - Login with credentials
- `logout()` - Logout from application
- `isLoggedIn()` - Check login status
- `switchUser(email, password)` - Switch to different user

## Real-time Testing

Phase 3 tests include comprehensive real-time functionality testing:

### Multi-user Testing
- Tests run with multiple browser contexts
- Simulates real-time collaboration
- Tests comment updates across users
- Tests task status changes across users

### Connection Management
- Tests WebSocket connection establishment
- Tests connection loss handling
- Tests reconnection logic
- Tests offline/online indicators

### Event Broadcasting
- Tests comment creation events
- Tests comment update events
- Tests comment deletion events
- Tests task status change events

## Test Data Management

### Seeding
```bash
# Seed Phase 3 test data
php artisan db:seed --class=Phase3TestDataSeeder

# Reset and seed
php artisan migrate:fresh --seed --seeder=Phase3TestDataSeeder
```

### Cleanup
```bash
# Clean up test data
php artisan tinker --execute="App\Models\Task::where('name', 'like', '%Phase 3%')->delete();"

# Reset database
php artisan migrate:fresh
```

## Troubleshooting

### Common Issues

1. **Tests fail with "Element not found"**
   - Ensure test data is seeded
   - Check if UI elements have correct test IDs
   - Verify page has loaded completely

2. **Real-time tests fail**
   - Check Pusher configuration
   - Verify WebSocket connection
   - Ensure Laravel Echo is properly configured

3. **File upload tests fail**
   - Check file permissions
   - Verify test files exist
   - Check storage configuration

4. **Database connection issues**
   - Verify database is running
   - Check database credentials
   - Ensure migrations are up to date

### Debug Mode

```bash
# Run tests in debug mode
npx playwright test --config=playwright.phase3.config.ts --debug

# Run specific test in debug mode
npx playwright test tests/E2E/phase3/phase3-features.spec.ts -g "should create a new comment" --debug
```

### Logs and Reports

```bash
# View test results
npx playwright show-report

# View traces
npx playwright show-trace test-results/phase3-artifacts/traces/trace.zip
```

## Performance Considerations

- Tests are designed to run in parallel when possible
- Real-time tests use separate browser contexts
- File upload tests use small test files
- Database operations are optimized for speed
- Cleanup is performed after each test

## Continuous Integration

Phase 3 tests are designed to run in CI environments:

- **Timeout**: 10 minutes for entire suite
- **Retries**: 2 retries on failure
- **Artifacts**: Screenshots, videos, and traces on failure
- **Reports**: HTML, JSON, and JUnit formats
- **Parallel**: Limited to 1 worker on CI

## Contributing

When adding new Phase 3 tests:

1. Follow the existing test structure
2. Use appropriate helper classes
3. Add test data to the seeder if needed
4. Update this documentation
5. Ensure tests are deterministic
6. Add proper cleanup

## Support

For issues with Phase 3 tests:

1. Check the troubleshooting section
2. Review test logs and artifacts
3. Verify test data is properly seeded
4. Check browser console for errors
5. Ensure all dependencies are installed
