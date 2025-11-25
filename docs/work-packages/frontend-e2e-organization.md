# Frontend E2E Organization Work Package

Last Updated: 2025-11-08 09:30

Owner: Codex (Reviewer)
Status: In Progress
Branch: `test-org/frontend-e2e-org`
ETA: 2h

## Goals
- Normalize Playwright configuration and projects.
- Consolidate E2E test locations under `tests/e2e`.
- Align MSW fixtures/handlers with app routes.
- Provide npm scripts and quick README for running.

## Scope
- Files under `frontend/` and `tests/e2e` only.
- No changes to locked backend files (`phpunit.xml`, `tests/Helpers/TestDataSeeder.php`).

## Tasks
1. Playwright config
   - [ ] Review `frontend/playwright.config.ts`
   - [ ] Define base projects (chromium, firefox, webkit) with common settings
   - [ ] Add per-domain projects placeholders (auth, dashboard) without touching backend
2. Test structure
   - [ ] Ensure tests reside under `tests/e2e` with clear folders: `smoke/`, `helpers/`, `setup/`
   - [ ] Update imports/paths if necessary
3. MSW alignment
   - [ ] Verify `tests/msw/fixtures` and `tests/msw/handlers` map to tested routes
   - [ ] Add notes for adding new fixtures
4. NPM scripts + docs
   - [ ] Add `e2e:smoke`, `e2e:all`, `e2e:ui` scripts in `frontend/package.json`
   - [ ] Create/extend `tests/e2e/README.md` with run instructions

## Deliverables
- Updated Playwright config with normalized structure.
- Clean folder layout under `tests/e2e`.
- MSW fixtures mapped and documented.
- NPM scripts for common E2E flows.

## Risks / Constraints
- Must not rely on backend changes blocked by Core Infrastructure.
- Keep changes additive and non-breaking for CI.

## Review Checklist
- Config builds locally.
- Tests discover and run from new locations.
- Scripts work on CI and local without extra env.

