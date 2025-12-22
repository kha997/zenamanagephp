# PrimaryNav API

Authoritative navigation rail rendered inside `HeaderShell`. File: `src/components/ui/header/PrimaryNav.tsx`.

## Props

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `items` | `NavItem[]` | **Yes** | Collection of route definitions (see below). |
| `currentUser` | `{ id: string; roles: string[]; tenant_id: string; }` | No | Used for RBAC + tenancy filters. |
| `className` | `string` | No | Tailwind/utility overrides on the wrapper. |
| `mobile` | `boolean` | No | Switches layout and spacing for the mobile sheet. |

### `NavItem`

```ts
type NavItem = {
  id: string;
  label: string;
  to: string;
  icon?: string;
  roles?: string[];        // ['*'] ⇒ all roles.
  tenants?: string[];      // ['*'] ⇒ all tenants.
  children?: NavItem[];    // Recursively rendered.
  badge?: { text: string; variant?: 'default' | 'success' | 'warning' | 'error' };
};
```

## Behavior

- Active state uses `location.pathname.startsWith(item.to)` for nested sections.
- Permission guard returns `false` if `currentUser` is absent; pass a user object for protected nav.
- When `mobile` is `true`, the component renders block-level buttons tailored for the mobile sheet; otherwise it renders inline desktop nav items.

## Accessibility

- Each anchor is rendered via `react-router-dom`'s `<Link>` with `aria-current="page"` when active.
- Badges include contextual text (no icons-only).
- Children are wrapped inside a logical list with margin offsets so screen readers can traverse sequentially.

## Usage

```tsx
import { PrimaryNav, NavItem } from '@/components/ui/header/PrimaryNav';

const items: NavItem[] = [
  { id: 'dashboard', label: 'Dashboard', to: '/app/dashboard', icon: 'home', roles: ['Admin', 'Manager'] },
  { id: 'projects', label: 'Projects', to: '/app/projects', badge: { text: '3', variant: 'warning' } },
];

<PrimaryNav items={items} currentUser={currentUser} />;
```

## Testing contract

- Vitest coverage ensures RBAC filtering and badge rendering.
- Playwright specs tagged `@header` must assert at least one nav link uses `aria-current`.
