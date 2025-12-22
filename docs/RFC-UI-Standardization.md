# RFC: UI Standardization

## Scope

This RFC outlines the plan to standardize the UI of the ZenaManage project by replacing all fragmented headers with the `HeaderShell` component or `x-shared.header-standardized` depending on the technology being used (React or Blade).

## Matrix

| Page/Context | Header Destination                  |
|--------------|---------------------------------------|
| Page A       | HeaderShell                           |
| Page B       | x-shared.header-standardized          |
| Page C       | HeaderShell                           |
| ...          | ...                                   |

## Priority & Rollback

1.  **Priority**: Standardize the most frequently used headers first.
2.  **Rollback**: Revert the changes and use the legacy header if any issues arise.  Ensure legacy headers are still functional.  Add feature flags to enable quick rollback.

## Timeline

-   **Phase 1**: Identify all instances of legacy headers. (1 week)
-   **Phase 2**: Replace legacy headers with `HeaderShell` or `x-shared.header-standardized`. (2 weeks)
-   **Phase 3**: Test the changes thoroughly. (1 week)

## Risks

-   Unexpected issues with the new header component.
-   Compatibility issues with existing code.

## Rollback Plan

1.  Revert the changes.
2.  Investigate the issues.
3.  Implement a fix.
4.  Redeploy the changes.

## Decisions (required for approval)

1. **Single source of truth** – Any Blade surface must render `<x-shared.header-standardized>` (alias for the React header wrapper). PR reviewers can reject changes that instantiate custom headers.
2. **Navigation policy** – Anchors rendered within `<nav>` must use `PrimaryNavLink` or `<Link>` with `aria-current`; CI (`npm run check:ui-standards`) enforces this.
3. **Test evidence** – Every header/layout change must ship with both a Vitest spec (`__tests__/HeaderShell.spec.tsx`) and a Playwright spec tagged `@header` covering mobile + desktop toggles.
4. **Migration path** – Legacy components (`LegacyHeader`, `OldTable`) stay in `legacy/deprecate` state until codemods + docs demonstrate parity. New work cannot target them directly.
