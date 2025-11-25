# UI/UX Apple‑Style Rollout Execution Plan

Purpose: A precise, safe, Cursor‑friendly playbook to standardize the UI using the Apple‑style spec with minimal regression risk. Agents can pick up any phase and proceed.

## Objectives
- Apply Apple‑style minimal UI across the app without breaking behavior.
- Centralize design via tokens, theming, layout, and primitives.
- Enforce safety with CI + UI smoke tests and review gates.

## Constraints
- Keep PRs small and reversible. No wide renames.
- Only edit UI paths listed below unless a task explicitly expands scope.
- Every PR must pass lint, type, unit, and UI smoke.

## Allowed Paths (UI only)
- `frontend/src/shared/tokens/**`
- `frontend/src/shared/theme/**`
- `src/components/ui/header/HeaderShell.tsx`
- `frontend/src/components/ui/**`
- `frontend/src/features/**/pages/**`
- `docs/**`
- `frontend/tests/ui/**`

## Forbidden Paths (for this rollout)
- `app/**`, `routes/**`, `database/**`, `lang/**`
- `tests/**` (except adding under `frontend/tests/ui/**`)

## Standard Commands (from `frontend/`)
- Lint: `pnpm lint`
- Type: `pnpm type-check` (or `pnpm typecheck` if configured)
- Unit: `pnpm test -- --run`
- UI smoke: `pnpm test:ui:smoke`

## Acceptance Criteria (Global)
- Header is top navigation only; no sidebar re‑introduction.
- Tokens drive colors/spacing/radius/shadows/typography (no hard‑coded colors).
- Theme respects system preference; user choice persists; no flicker.
- Light/Dark snapshots pass; keyboard focus visible for header links.

## Phases & Checklists

### P0 – Verify Baseline (Done)
- [x] Spec: `docs/UIUX_APPLE_STYLE_SPEC.md`
- [x] Plan: `docs/UIUX_IMPLEMENTATION_PLAN.md`
- [x] PR template: `.github/PULL_REQUEST_TEMPLATE.md` (UI smoke included)
- [x] CODEOWNERS for UI paths
- [x] Frontend CI workflow
- [x] `.cursorrules` agent guard

### P1 – Tokens (Done)
- [x] Colors: `frontend/src/shared/tokens/colors.ts`
- [x] Spacing: `frontend/src/shared/tokens/spacing.ts`
- [x] Radius: `frontend/src/shared/tokens/radius.ts`
- [x] Shadows: `frontend/src/shared/tokens/shadows.ts`
- [x] Typography: `frontend/src/shared/tokens/typography.ts`
- Accept: imports compile; no hard-coded colors in new code.

### P2 – Theming (Done)
- [x] ThemeProvider: `frontend/src/shared/theme/ThemeProvider.tsx`
- [x] README usage: `frontend/src/shared/theme/README.md`
- Accept: toggling theme updates CSS vars instantly; persists choice; respects system.

### P3 – Frame & Navigation (Done)
- [x] HeaderShell top‑nav: `src/components/ui/header/HeaderShell.tsx`
- [x] Hover/active underline using `--accent`; scroll blur; theme toggle; ⌘K placeholder
- Accept: header visible, accessible; no layout regressions.

### P4 – Primitives (Done)
- [x] Button/Input/Card: `frontend/src/components/ui/primitives/*`
- Accept: focus ring via `--ring`; variants/states render correctly.

### P5 – Layout Application (Done)
- [x] Container: `frontend/src/components/ui/layout/Container.tsx`
- [x] Dashboard uses Container and a UI demo Card with Buttons
- Accept: spacing rhythm matches spec; no double padding.

### P6 – Tests (Done)
- [x] Header snapshots: `frontend/tests/ui/header.spec.ts`
- [x] Primitives snapshots: `frontend/tests/ui/primitives.spec.ts`
- [x] Script: `test:ui:smoke` in `frontend/package.json`
- Accept: UI smoke passes for light/dark.

### P7 – Adoption (Next)
Task: Apply Container + Card + Button/Input to one list page and one detail page.
- [ ] Select target pages (list + detail)
- [ ] Wrap with Container; replace local panels with Card
- [ ] Normalize buttons/inputs to primitives
- [ ] Ensure active nav uses `aria-current="page"`
- Accept: visuals align with spec; UI smoke green; no behavioral changes.

### P8 – Command Palette (Optional)
Task: Implement minimal ⌘K modal with focus trap and static actions.
- [ ] Modal opens with ⌘K and button; closes with Esc/click outside
- [ ] Keyboard navigation for actions; `role="dialog"` with aria labels
- Accept: accessible controls; no layout shift; smoke unchanged.

### P9 – Cleanup & Consolidation
- [ ] Add deprecation README to `frontend/src.old` and `frontend/src.backup` tokens
- [ ] Decide canonical HeaderShell path; add re‑export if moving
- [ ] Update CODEOWNERS if paths change
- Accept: no broken imports; guardrails updated.

## Handoff Template (per PR)
- Summary: What changed and why (link to spec/plan)
- Scope: Paths touched (must be in Allowed Paths)
- Tests: paste local outputs
  - `pnpm lint`:
  - `pnpm type-check`:
  - `pnpm test -- --run`:
  - `pnpm test:ui:smoke`:
- Screenshots: before/after (if visual)
- Next Step: what the following PR should do

## Rollback
- Revert the PR if CI/UI smoke fails post‑merge.
- Prefer tags per phase: `uiux-m1`, `uiux-m2`, … to return to safe points quickly.

---
This file is the operational source of truth for the UI/UX rollout. Agents must adhere to Allowed/Forbidden paths, acceptance criteria, and checklists to proceed safely.

