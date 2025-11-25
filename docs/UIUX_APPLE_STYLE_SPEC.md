# Apple-Style Minimal UI/UX Spec (No Sidebar)

Purpose: Single source of truth for Cursor/PRs to align UI with an Apple‑style minimal design. Scope covers tokens, layout, shared header + top navigation, theming (light/dark), and core components. Avoid pixel copy of any brand; this is inspiration, not imitation.

## Design Principles
- Intentional minimalism: show only what matters now.
- Calm hierarchy: generous white space, clear vertical rhythm, consistent grids.
- Clarity first: readable type, restrained color, subtle motion.
- Accessibility: WCAG AA+, keyboard‑first flows, visible focus.
- Performance: low elevation, light effects, instant theming via CSS variables.

## App Frame & Layout
- Header: shared, fixed, 56–64px height; translucent/white on light, subtle blur on scroll; bottom border 1px subtle.
- Navigation: top navigation only (no sidebar). Max 5–7 primary items. Primary on the left, utilities on the right.
- Container: max‑width 1200px; horizontal padding 24px; vertical section gaps 24–32px; 8px spacing grid.
- Footer: optional, 44–48px height, low‑contrast text.

## Top Navigation (No Sidebar)
- Left: logo (monochrome) + primary nav items.
- Center (optional): page title or lightweight breadcrumbs.
- Right: search/command (⌘K), notifications, help, profile menu, theme toggle.
- States
  - Hover: 2px underline, accent at 50% opacity.
  - Active: stronger text + 2px underline in accent; use `aria-current="page"`.
  - Scroll: add `backdrop-filter: blur(8px)` and increase background opacity slightly.

## Theming (Light/Dark)
- Accent (Apple‑ish): `#0A84FF` (hover `#006FE8`), focus ring `rgba(10,132,255,0.35)`.
- Light
  - `--bg`: `#FFFFFF`
  - `--surface`: `#F8FAFC`
  - `--text`: `#0B1220`
  - `--muted`: `#6B7280`
  - `--border`: `rgba(0,0,0,0.08)`
- Dark
  - `--bg`: `#0B1220`
  - `--surface`: `#0F172A`
  - `--text`: `#E5E7EB`
  - `--muted`: `#94A3B8`
  - `--border`: `rgba(255,255,255,0.08)`
- Implement with CSS variables at `:root` and `[data-theme="dark"]`; sync with `prefers-color-scheme` and store preference.

## Tokens (source of truth in code)
- Colors: cool neutral grayscale (50/100/200/300/600/900) + accent.
- Radius: 8, 10, 12.
- Shadows
  - xs: `0 1px 2px rgba(0,0,0,0.04)`
  - sm: `0 2px 8px rgba(0,0,0,0.06)`
  - md: `0 6px 20px rgba(0,0,0,0.08)` (use sparingly)
- Spacing: 8px grid; container gutters 24px.
- Typography: Inter/System; body 15–16px (1.5–1.6 lh); headings 20/24/28/32; weights 400/500/600.

## Core Pages
- Dashboard: short hero (title + key filters), content in 1 or 2 columns (8/4); cards use radius 12, shadow xs.
- List/Index: toolbar (filter/sort/view) above; table/list with sticky header, row 48px, subtle hover; strong empty states.
- Detail: title + metadata row; primary actions right; content as sections; tabs when needed.

## Components
- Button
  - Sizes: 36/40 height; radius 8; padding 12–16.
  - Variants: Primary (accent solid), Secondary (neutral with border), Tertiary (text).
  - Focus: 2px ring using accent at low alpha.
- Input/TextField
  - Border 1px neutral‑200 (light) / alpha border (dark); radius 10.
  - Focus: accent border + soft ring; placeholder neutral‑400; support prefix/suffix icons.
- Select/Dropdown
  - Floating surface with shadow sm; item height 40–44; hover subtle; full keyboard support.
- Tabs
  - 2px underline accent; spacing 16–20; 150ms fade for panels.
- Card/Panel
  - Surface background, 1px subtle border, shadow xs, header 16 semibold.
- Table/List
  - Sticky header; row height 48; hover bg neutral‑50; dividers neutral‑100.
- Toast/Alert
  - Subtle background, colored border per status; icon left, dismiss right; roles `status/alert`.
- Modal/Drawer
  - Radius 12; overlay dim; focus trap; Esc to close; avoid full‑screen unless necessary.

## Motion
- Duration 150–200ms; `cubic-bezier(0.2,0,0,1)` or ease‑out.
- Use fade/translate small distances; avoid large scale or heavy parallax.

## Accessibility
- Contrast: AA minimum, aim AAA for primary text.
- Keyboard: tab order logical; visible focus states; command palette (`⌘K`), `Esc` closes overlays.
- Hit targets: ≥ 40x40; semantic roles/labels; aria for interactive controls.

## Engineering Hand‑off (Repo Mapping)
- Tokens
  - Extend `frontend/src/shared/tokens/colors.ts` with accent + neutral scale and light/dark maps.
  - Add optional `spacing.ts`, `radius.ts`, `shadows.ts`, `typography.ts` under `frontend/src/shared/tokens/` for clarity.
  - Expose CSS variables via a ThemeProvider or global stylesheet; toggle `[data-theme]` on `<html>`.
- Layout
  - Implement shared header and top nav in `frontend/src/components/layout/HeaderShell.tsx` (no sidebar).
  - Apply a common container wrapper in pages like `frontend/src/pages/dashboard/DashboardPage.tsx`.
- Theming
  - Respect `prefers-color-scheme`; persist user choice; instant switch without layout shift.
- Testing
  - Playwright snapshots for header (light/dark), button states, and a card layout.
  - Unit tests for keyboard navigation (tabs, command palette, modal focus).

## Do & Don’t
- Do: keep surfaces clean; prefer borders to heavy shadows; reduce chrome; emphasize content.
- Don’t: add sidebars; over‑animate; use saturated backgrounds; crowd the header.

---
This spec is authoritative for the UI baseline. Deviations should be intentional and documented in PR descriptions.

