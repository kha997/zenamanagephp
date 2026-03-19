# 2026-03-19 Public Demo Artifact Audit

Scope: narrow audit of public static demo/test artifacts, prioritizing `public/api-demo.html`.

## Findings

1. `public/api-demo.html` no longer has a valid runtime owner.
   - `routes/web.php` keeps the `/api-demo` route commented out.
   - `php artisan route:list --path=api-demo` returns no matching route.
   - The file still presents itself as an "API Integration Demo" and remains directly web-accessible when present under `public/`.
2. The retired app dashboard chain is still embedded in `public/api-demo.html`.
   - The page references `/test-api-app-dashboard`.
   - `php artisan route:list --path=test-api-app-dashboard` returns no matching route.
   - The page hard-codes the retirement message, so it no longer represents a working demo flow.
3. Other `public/` HTML artifacts exist, but this round found weaker ownership evidence for removal.
   - `public/logo-test.html` appears to be a one-off branding/manual test page documented in `VIEW_TESTING_REPORT.md`.
   - `public/projects-dashboard-test.html` appears to be a static UI test page documented in `VIEW_TESTING_REPORT.md`.
   - Neither file was tied in this round to the retired app dashboard demo chain or to a confirmed dead route alias comparable to `/api-demo`.

## Disposition

| Artifact | Classification | Decision | Evidence |
| --- | --- | --- | --- |
| `public/api-demo.html` | stale public demo artifact | remove safely | unmounted `/api-demo`; dead `/test-api-app-dashboard`; no remaining valid demo owner |
| `public/logo-test.html` | public test artifact | UNKNOWN | only evidence found was historical manual test documentation |
| `public/projects-dashboard-test.html` | public test artifact | UNKNOWN | only evidence found was historical manual test documentation |

## Notes

- This round intentionally does not invent a replacement endpoint or re-home the removed artifact into a new archive surface.
- This round intentionally does not change runtime business behavior.
