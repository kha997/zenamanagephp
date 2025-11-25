# Apple-Style UI/UX Implementation Plan (Safe & Complete)

Purpose: Execute the Apple-style minimal UI/UX (docs/UIUX_APPLE_STYLE_SPEC.md) safely, without regressions, with clear checkpoints, owners, and deliverables.

## Guiding Principles
- Small, reversible PRs; keep blast radius minimal.
- Backward compatible exports; avoid breaking imports.
- Tests guard visual + keyboard interactions.
- Document deviations in PR description.

## Milestones & Tasks

### M1 – Discovery & Baseline (0.5–1 day)
- Inventory current tokens in `frontend/src/shared/tokens/colors.ts`.
- Audit headers: `frontend/src/components/layout/HeaderShell.tsx` and `src/components/ui/header/HeaderShell.tsx` (duplication, dark mode, nav patterns).
- Identify pages to standardize: `frontend/src/pages/dashboard/DashboardPage.tsx` + top 2 list/detail pages.
- Deliverables
  - Baseline notes in PR description.
  - Decision on canonical HeaderShell path.

### M2 – Tokenization (1 day)
- Colors: add Apple-ish accent + neutral scale (light/dark maps).
- Spacing: 8px scale; `4, 8, 12, 16, 20, 24, 32`.
- Radius: `8, 10, 12`.
- Shadows: xs/sm/md.
- Typography: Inter/System; body 15–16; headings 20/24/28/32.
- Files
  - Extend `frontend/src/shared/tokens/colors.ts`.
  - Add `frontend/src/shared/tokens/{spacing.ts,radius.ts,shadows.ts,typography.ts}`.
- Deliverables
  - Tokens exported, unit-smoke import test compiles.

### M3 – Theming Infrastructure (1 day)
- ThemeProvider: maps tokens to CSS variables on `<html>`/root.
- Preference: detect `prefers-color-scheme`, override via localStorage.
- Hook: `useTheme()` with `theme`, `setTheme`, `toggleTheme`.
- Deliverables
  - Theme toggles without layout shift; basic tests for persistence.

### M4 – Header Rework (Top Nav, No Sidebar) (1–1.5 days)
- Choose canonical `HeaderShell.tsx` (prefer `frontend/src/components/layout/HeaderShell.tsx`).
- Implement left(nav) / center(title/breadcrumb) / right(search ⌘K, alerts, help, profile, theme toggle).
- Hover/active/scroll states per spec; keyboard navigation and `aria-current`.
- Deliverables
  - Updated header component + Playwright snapshot (light/dark).

### M5 – Primitives (Buttons, Input, Card) (1–1.5 days)
- Button: primary/secondary/tertiary; 36/40; radius 8; focus ring.
- Input: border 1px; radius 10; focus ring; error state; prefix/suffix icons.
- Card: surface, 1px border, shadow xs; header/body slots.
- Deliverables
  - Components + minimal usage examples; Playwright snapshot for states.

### M6 – Layout Application (0.5–1 day)
- Create `Container` (max-width 1200, padding 24). Apply to `DashboardPage.tsx` + one list/detail page.
- Normalize section spacing 24–32, heading scale.
- Deliverables
  - Before/after screenshots; snapshot updates.

### M7 – Tests & Accessibility (ongoing; finalize 0.5 day)
- Playwright: header, button states, card (light/dark) visual tests.
- Keyboard: nav tab order, focus ring presence, Esc behavior for overlays.
- Contrast audit: ensure AA.
- Deliverables
  - Passing tests; brief a11y checklist update.

### M8 – Documentation & Rollout (0.5 day)
- Reference spec from `docs/Frontend-Guidelines.md`.
- Add a short usage guide: tokens, theming API, header config, examples.
- Update PR checklist (`docs/PR-UI-Checklist.md`) to require tokens + light/dark + keyboard checks.
- Deliverables
  - Docs updated; checklist enforced.

## Risk Management
- Visual regressions: controlled by Playwright snapshots; limit scope per PR.
- Import breakage: preserve existing component names/paths; provide aliases if moving files.
- Theming flicker: set initial theme class before React mounts.

## Acceptance Criteria
- Header with top navigation (no sidebar) across targeted pages.
- Tokens in place and used by primitives; no hard-coded colors in new code.
- Light/dark switch persists and respects system preference.
- Playwright snapshots green for header and primitives (light/dark).
- Docs and checklist reflect new standard.

## Tracking Checklist (tick during execution)
- [ ] M1 Discovery complete; canonical HeaderShell chosen
- [ ] M2 Tokens added and imported without errors
- [ ] M3 ThemeProvider + `useTheme()` implemented
- [ ] M4 HeaderShell refactor merged + snapshots
- [ ] M5 Primitives (Button, Input, Card) merged + snapshots
- [ ] M6 Container applied to Dashboard + 1 page
- [ ] M7 Tests (visual + keyboard) passing; contrast AA verified
- [ ] M8 Docs + checklist updated

---
Owner: UI Platform
Reviewers: Design, Frontend Lead, QA

