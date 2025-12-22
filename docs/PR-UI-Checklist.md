# PR UI Checklist

Attach this checklist to every PR that touches UI code (Blade, React, CSS, tokens, or API docs).

- [ ] **Design system compliance** – Reused standardized components (`HeaderShell`, `PrimaryNav`, `x-shared.*`) where applicable. Added/updated `docs/components/<component>/API.md` links in the PR body.
- [ ] **Accessibility** – Verified keyboard focus order, aria labels, and color contrast for new UI. Added tests or manual evidence (screenshots/video) for screen-reader and tab navigation flows.
- [ ] **RBAC & tenancy** – Confirmed navigation items, dashboards, and API calls respect the current tenant + role. Added coverage for `currentUser.roles` / `tenant_id`.
- [ ] **Theming** – Tested light/dark permutations. Persisted user preference when toggles exist (`HeaderShell` data-theme attribute).
- [ ] **Performance budgets** – Ran `npm run build && npm run --prefix frontend build` followed by `npm run check:performance`. Attached output when budgets are near the threshold.
- [ ] **Tests & stories** – Added/updated unit specs (`vitest`/`phpunit`) and/or Storybook stories for every touched component. `npm run check:ui-standards` passes locally.
- [ ] **E2E tags** – Added or updated Playwright specs with `@header` / `@smoke` tags when header logic changes.
- [ ] **Docs & changelog** – Updated `docs/component-inventory.*`, relevant RFCs, and CHANGELOG sections if the user surface changed.
