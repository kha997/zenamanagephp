# View Testing Report

## Status

- Report date: 2025-09-21
- Method: browser testing with Playwright
- Current classification: historical manual-test record only
- Current repo/runtime status verified on 2026-03-19

This document records a point-in-time manual browser check from September 21, 2025. It does not describe current runtime ownership. The public demo/test artifacts referenced below have since been removed from `public/` and should not be treated as active runtime surfaces.

Cross-reference: `docs/audits/2026-03-19-public-demo-artifact-audit.md`

## Historical Entries Reconciled

### 1. Projects Dashboard Test

- Historical file under test: `public/projects-dashboard-test.html`
- Current status: removed from `public/`
- Runtime-owner status: no route owner for `/projects-dashboard-test`
- Disposition: historical note retained; prior "working view" claim removed as stale

Historical scope captured on 2025-09-21:
- Header "Quản lý Dự án"
- Action buttons for report export and project creation
- Static statistics cards and sample project cards
- Search/filter controls and grid/list toggle
- General UI notes about responsive layout, color coding, progress indicators, and icon usage

Current repo truth on 2026-03-19:
- The file no longer exists in `public/`.
- There is no mounted `/projects-dashboard-test` route.
- The runtime-owned project dashboard surface is `/app/projects`, not this retired static artifact.

### 2. Logo Test

- Historical file under test: `public/logo-test.html`
- Current status: removed from `public/`
- Runtime-owner status: no route owner for `/logo-test`
- Disposition: historical note retained; prior "working view" claim removed as stale

Historical scope captured on 2025-09-21:
- Inline-style logo variant
- CSS-class logo variant
- Tailwind-style logo variant
- Combined styling variant
- Admin-dashboard-style branding check

Current repo truth on 2026-03-19:
- The file no longer exists in `public/`.
- There is no mounted `/logo-test` route.
- This was a manual branding check artifact, not a runtime-owned application surface.

### 3. API Demo

- Historical file under test: `public/api-demo.html`
- Current status: removed from `public/`
- Runtime-owner status: no active route owner for `/api-demo`
- Disposition: historical note retained; stale runtime claims removed

Historical scope captured on 2025-09-21:
- Header banner for the API integration demo
- Visual API status area
- References to `/test-api-admin-dashboard` and `/test-api-app-dashboard`
- Loading-state copy and refresh button
- Manual notes about server startup and `file://` CORS behavior

Current repo truth on 2026-03-19:
- The file no longer exists in `public/`.
- `routes/web.php` keeps `/api-demo` commented out, so there is no active mounted route.
- The retired `/test-api-app-dashboard` chain is not a current runtime flow.
- The historical UI observation that the page looked polished is kept only as a point-in-time design note, not as evidence of a live demo surface.

## Claims Kept

- A manual browser-testing pass happened on 2025-09-21.
- The three referenced artifacts existed at that time and were reviewed as static/manual test pages.
- The report remains useful as historical UI observation only.

## Claims Removed Or Reframed

- Removed: any claim that `public/projects-dashboard-test.html` is a currently working view.
- Removed: any claim that `public/logo-test.html` is a currently working view.
- Removed: any claim that `public/api-demo.html` is a current working demo surface.
- Removed: the aggregate claim that "3/3 Views" currently work well.
- Removed: current-state scorecards, browser-compatibility assertions, performance assertions, accessibility assertions, and forward recommendations that depended on those retired artifacts being live surfaces.
- Reframed as historical only: UI/UX observations recorded during the 2025-09-21 browser check.

## Verification Basis

- `public/` no longer contains `api-demo.html`, `projects-dashboard-test.html`, or `logo-test.html`.
- Repo search shows the surviving references are historical docs, a backup route file for the former `/api-demo` surface, the commented route in `routes/web.php`, and a regression test asserting `/api-demo` is unmounted.
- See `docs/audits/2026-03-19-public-demo-artifact-audit.md` for the narrow artifact audit and disposition rationale.
