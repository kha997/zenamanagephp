# Frontend Rebuild Verification Checklist

## ✅ Build & Typecheck

- [x] `npm ci` - Dependencies installed successfully
- [x] `npm run type-check` - TypeScript compilation passed
- [x] `npm run build` - Build completed successfully
  - Manifest: `public/build/.vite/manifest.json`
  - Entry: `frontend/src/main` → `assets/js/frontend/src/main-XBEardLd.js`
  - CSS: `assets/css/main-CL6fZ8YI.css`

## ✅ Dev Boot & Mount

- [x] SPA mount point: `resources/views/app/spa.blade.php` has `<div id="app"></div>`
- [x] Route: `/app/{any}` → `app.spa` view
- [x] Manifest lookup: Updated to check `frontend/src/main` first
- [x] No duplicate headers: `spa.blade.php` doesn't include `header-wrapper`, React renders its own header

## ✅ Auth Flow Verification

- [x] API Client: `withCredentials: true` in `frontend/src/shared/api/client.ts`
- [x] CSRF Token: Read from `meta[name="csrf-token"]` or `window.Laravel.csrfToken`
- [x] API Endpoints:
  - `POST /api/v1/auth/login` (with `X-Web-Login: true` header)
  - `GET /api/v1/auth/me` (requires `auth:sanctum`, `ability:tenant`)
  - `POST /api/v1/auth/logout` (requires `auth:sanctum`)
  - `GET /api/v1/auth/permissions` (requires `auth:sanctum`, `ability:tenant`)
- [x] AuthGuard: Redirects anonymous users to `/login` and preserves `redirectTo` in location state

## ✅ Projects/Tasks API Contracts

### Projects API
- `GET /api/v1/app/projects` - List projects
- `GET /api/v1/app/projects/{id}` - Get project detail
- `POST /api/v1/app/projects` - Create project
- `PUT /api/v1/app/projects/{id}` - Update project
- `DELETE /api/v1/app/projects/{id}` - Delete project
- `GET /api/v1/app/projects/kpis` - Get KPIs
- `GET /api/v1/app/projects/alerts` - Get alerts
- `GET /api/v1/app/projects/activity` - Get activity

### Tasks API
- `GET /api/v1/app/tasks` - List tasks
- `GET /api/v1/app/tasks/{id}` - Get task detail
- `POST /api/v1/app/tasks` - Create task
- `PUT /api/v1/app/tasks/{id}` - Update task
- `DELETE /api/v1/app/tasks/{id}` - Delete task
- `GET /api/v1/app/tasks/kpis` - Get KPIs
- `GET /api/v1/app/tasks/alerts` - Get alerts
- `GET /api/v1/app/tasks/activity` - Get activity

## ⏳ MSW & Tests Sync

- [x] Unit tests: Old tests in `src.backup` and `src.old` - new tests need to be created
- [ ] Update MSW handlers: `tests/msw/handlers/tasks.ts` - Update to match `/api/v1/app/tasks` endpoints
- [ ] Add MSW handlers for projects: `tests/msw/handlers/projects.ts` - Create new file
- [ ] Sync fixtures: `tests/msw/fixtures/tasks.json` - Update structure
- [ ] Run E2E tests: `npm run e2e:smoke` - Update selectors for new routes

## ⏳ CI Dry Run

- [ ] Validate `.github/workflows/ci-cd.yml`
- [ ] Run local CI checks
- [ ] Verify Node/crypto polyfills if needed

## ⏳ Docs & Developer Experience

- [ ] Update `INSTALLATION_GUIDE.md` with new structure
- [ ] Update `docs/Frontend-Rebuild-Notes.md` with final routes
- [ ] Document mount decisions

## ⏳ Cleanup (After Green Build + Smoke)

- [ ] Delete `frontend/src.old/`
- [ ] Delete `frontend/src.backup/`
- [ ] Update `.gitignore` if needed
- [ ] Commit changes

