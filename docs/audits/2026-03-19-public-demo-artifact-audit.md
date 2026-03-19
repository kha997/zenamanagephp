# 2026-03-19 Public Demo Artifact Audit

Scope: narrow audit of public static demo/test artifacts, prioritizing `public/api-demo.html` and then `public/projects-dashboard-test.html`.

## Findings

1. `public/api-demo.html` no longer has a valid runtime owner.
   - `routes/web.php` keeps the `/api-demo` route commented out.
   - `php artisan route:list --path=api-demo` returns no matching route.
   - The file still presents itself as an "API Integration Demo" and remains directly web-accessible when present under `public/`.
2. The retired app dashboard chain is still embedded in `public/api-demo.html`.
   - The page references `/test-api-app-dashboard`.
   - `php artisan route:list --path=test-api-app-dashboard` returns no matching route.
   - The page hard-codes the retirement message, so it no longer represents a working demo flow.
3. `public/projects-dashboard-test.html` no longer has a valid runtime owner.
   - `php artisan route:list --path=projects-dashboard-test` returns no matching route.
   - Repo-wide search only finds the file itself, the historical `VIEW_TESTING_REPORT.md`, and this audit.
   - The file is a fully static mockup: it contains no script tag, no fetch/XHR call, and no form/action that ties it to a live runtime flow.
   - It hard-codes localhost CSS URLs (`http://localhost:8000/css/tailwind.css` and `http://localhost:8000/css/design-system.css`), which is manual-preview scaffolding rather than a mounted production/runtime surface.
4. The project dashboard now has a separate runtime owner in the app surface.
   - `php artisan route:list --path=app/projects` shows the mounted `/app/projects` route chain.
   - `frontend/src/pages/ProjectsDashboard.tsx` reproduces the same dashboard UI shape and loads live project data via `apiClient.get('/projects')`.
   - The static artifact therefore duplicates a runtime-owned page rather than backing it.
5. `public/logo-test.html` no longer has a valid runtime owner.
   - `php artisan route:list --path=logo-test` returns no matching route.
   - Repo-wide search only finds the file itself, `VIEW_TESTING_REPORT.md`, and this audit; no mounted view/controller/component consumes it.
   - `routes/web.php` and `routes/web.php.backup.20250924_212141` contain no `/logo-test` route, so there is no route-backed current or historical owner analogous to the former `/api-demo` surface.
   - The page is a self-contained manual branding check: it loads Tailwind from the CDN, uses only inline/CSS-class logo variants, and has no script bundle, fetch/XHR call, form/action, or app runtime dependency beyond direct static hosting under `public/`.
   - The only surviving non-file reference is `VIEW_TESTING_REPORT.md`, which records a browser test on September 21, 2025 and does not establish an ongoing runtime owner.

## Disposition

| Artifact | Classification | Decision | Evidence |
| --- | --- | --- | --- |
| `public/api-demo.html` | stale public demo artifact | remove safely | unmounted `/api-demo`; dead `/test-api-app-dashboard`; no remaining valid demo owner |
| `public/logo-test.html` | stale public test artifact | remove safely | no mounted route; no route history in current backup routes; no repo consumers beyond historical docs; no live app/API flow; direct static hosting under `public/` is the only exposure |
| `public/projects-dashboard-test.html` | stale public test artifact | remove safely | no mounted route; no repo consumers beyond historical docs; no live API/runtime flow; superseded by `/app/projects` runtime owner |

## Notes

- This round intentionally does not invent a replacement endpoint or re-home the removed artifact into a new archive surface.
- This round intentionally does not change runtime business behavior.
- `VIEW_TESTING_REPORT.md` is treated here as historical manual-test evidence, not as proof of a current runtime owner.
- `VIEW_TESTING_REPORT.md` was reconciled on 2026-03-19 to preserve the September 21, 2025 observations only as historical notes and to remove stale current-state/runtime claims tied to the retired artifacts.
