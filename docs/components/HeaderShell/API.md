# HeaderShell API

Canonical React shell for every authenticated header surface. Lives at `src/components/ui/header/HeaderShell.tsx`.

## Props

| Name | Type | Default | Required | Description |
| --- | --- | --- | --- | --- |
| `theme` | `'light' \| 'dark'` | `'light'` | No | Applies design tokens via `data-theme` on `<html>`. |
| `size` | `'sm' \| 'md' \| 'lg'` | `'md'` | No | Vertical padding scale for the container. |
| `sticky` | `boolean` | `true` | No | Applies `sticky top-0` and keeps header pinned. |
| `condensedOnScroll` | `boolean` | `true` | No | Enables scroll listener (via `useHeaderCondense`) that toggles the `condensed` CSS hook. |
| `withBorder` | `boolean` | `true` | No | Renders bottom border to delineate header from content. |
| `logo` | `ReactNode` | — | **Yes** | Always provide brand mark/wordmark. |
| `primaryNav` | `ReactNode` | — | No | Typically `<PrimaryNav>`; hidden on mobile and moved into the sheet. |
| `secondaryActions` | `ReactNode` | — | No | Right-aligned actions (e.g., create buttons, feature toggles). |
| `userMenu` | `ReactNode` | — | No | Authenticated user dropdown. |
| `notifications` | `ReactNode` | — | No | Notification bell/stack. |
| `breadcrumbs` | `ReactNode` | — | No | Optional breadcrumb row rendered below the main bar. |
| `className` | `string` | `''` | No | Appends extra utility classes on the `<header>`. |

## Behavioral notes

- Mobile menu state is internal, exposed via `aria-expanded` on the hamburger; body scroll is locked when open.
- Theme changes propagate to `document.documentElement` so downstream components should react to the CSS variable swap (tests assert persistence).
- The component logs dev-only diagnostics (`console.log` calls) and triggers the `debugger;` statement today. Remove these before production if not intentionally keeping them.

## Accessibility

- `<header role="banner">` plus `aria-label="Main navigation"`.
- Mobile menu uses `role="dialog"` with `aria-modal="true"` and `aria-controls` wiring from the toggle.
- Breadcrumb container uses semantic `<nav aria-label="Primary navigation">` when `primaryNav` is passed.

## Usage

```tsx
import { HeaderShell } from '@/components/ui/header/HeaderShell';
import { PrimaryNav } from '@/components/ui/header/PrimaryNav';
import { UserMenu } from '@/components/ui/header/UserMenu';

<HeaderShell
  logo={<ZenaLogo />}
  primaryNav={<PrimaryNav items={navItems} currentUser={user} />}
  secondaryActions={<QuickActions />}
  userMenu={<UserMenu user={user} onLogout={logout} />}
  notifications={<NotificationsBell items={alerts} />}
  breadcrumbs={<Breadcrumbs items={breadcrumbs} />}
  theme={user.prefersDark ? 'dark' : 'light'}
/>;
```

## Testing contract

- Unit: `__tests__/HeaderShell.spec.tsx` (Vitest) renders both desktop and mobile paths.
- E2E: Playwright specs tagged with `@header` must exercise hamburger toggle, condensation, and theme persistence on at least one viewport.
