# Frontend Guidelines - React/TypeScript Playbook

**Version**: 1.0  
**Last Updated**: 2025-01-XX  
**Status**: ✅ **ACTIVE** - Mandatory for all `/app/*` routes

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Technology Stack](#technology-stack)
3. [Project Structure](#project-structure)
4. [Code Standards](#code-standards)
5. [Component Guidelines](#component-guidelines)
6. [API Layer](#api-layer)
7. [State Management](#state-management)
8. [RBAC & Tenant Integration](#rbac--tenant-integration)
9. [Design System](#design-system)
10. [Testing Requirements](#testing-requirements)
11. [Performance Standards](#performance-standards)
12. [Accessibility](#accessibility)
13. [Error Handling](#error-handling)
14. [CI/CD Requirements](#cicd-requirements)

---

## Architecture Overview

### Technology Decision

**`/app/*` routes → React + TypeScript SPA (Official)**

- ✅ All tenant-scoped application routes use React
- ✅ TypeScript for type safety
- ✅ Component-based architecture
- ❌ No new Blade templates for `/app/*`
- ❌ No new Alpine.js for `/app/*`

See [ADR-007](../docs/adr/ADR-007-frontend-technology-split.md) for full architecture decision.

### Core Principles

1. **Component-Based**: Reusable, composable UI components
2. **Data-Driven**: All UI elements bound to real data sources
3. **RBAC-Aware**: Role-based access control throughout
4. **Tenant-Scoped**: Multi-tenant isolation at UI level
5. **Performance-First**: Optimized for speed and efficiency
6. **Accessibility-First**: WCAG 2.1 AA compliance
7. **Type-Safe**: TypeScript for all code
8. **API-First**: All business logic in backend API

---

## Technology Stack

### Core Technologies

- **React 18+**: UI library
- **TypeScript 5+**: Type safety
- **Vite**: Build tool
- **React Router**: Client-side routing
- **Tailwind CSS**: Styling
- **Design Tokens**: Shared design system

### State Management

- **React Query (TanStack Query)**: Server state
- **Zustand**: Client state (if needed)
- **React Context**: Theme, auth context

### API Integration

- **Axios**: HTTP client
- **Unified API Client**: `frontend/src/shared/api/client.ts`
- **ApiResponse Format**: Standard error handling

### Testing

- **Vitest**: Unit tests
- **React Testing Library**: Component tests
- **Playwright**: E2E tests

---

## Project Structure

```
frontend/
├── src/
│   ├── app/                    # App shell, routing
│   │   ├── router.tsx          # React Router config
│   │   ├── AppShell.tsx        # Main app shell
│   │   └── layouts/            # Layout components
│   │
│   ├── features/               # Feature modules (by domain)
│   │   ├── projects/           # Projects feature
│   │   │   ├── api.ts          # API calls
│   │   │   ├── components/     # Feature components
│   │   │   ├── hooks/          # Feature hooks
│   │   │   └── types.ts        # Feature types
│   │   ├── tasks/              # Tasks feature
│   │   ├── dashboard/          # Dashboard feature
│   │   └── reports/            # Reports feature
│   │
│   ├── components/             # Reusable components
│   │   ├── ui/                 # Base UI components
│   │   │   ├── Button.tsx
│   │   │   ├── Card.tsx
│   │   │   ├── Input.tsx
│   │   │   └── Modal.tsx
│   │   └── layout/             # Layout components
│   │       ├── HeaderShell.tsx
│   │       ├── Sidebar.tsx
│   │       └── Footer.tsx
│   │
│   ├── shared/                 # Shared utilities
│   │   ├── api/                # Unified API client
│   │   │   └── client.ts       # API client implementation
│   │   ├── tokens/             # Design tokens
│   │   │   ├── colors.ts
│   │   │   ├── spacing.ts
│   │   │   └── typography.ts
│   │   ├── hooks/              # Shared hooks
│   │   │   ├── usePermissions.ts
│   │   │   ├── useApi.ts
│   │   │   └── useTenant.ts
│   │   └── utils/              # Utility functions
│   │
│   ├── services/               # API services (if needed)
│   │   ├── projectService.ts
│   │   └── taskService.ts
│   │
│   ├── pages/                  # Page components
│   │   ├── DashboardPage.tsx
│   │   ├── ProjectsPage.tsx
│   │   └── TasksPage.tsx
│   │
│   ├── contexts/               # React contexts
│   │   ├── AuthContext.tsx
│   │   └── ThemeContext.tsx
│   │
│   ├── types/                  # Global types
│   │   └── index.ts
│   │
│   └── main.tsx                # Entry point
│
└── vite.config.ts              # Vite configuration
```

### Naming Conventions

- **Components**: PascalCase (`UserProfile.tsx`)
- **Hooks**: camelCase with `use` prefix (`usePermissions.ts`)
- **Utils**: camelCase (`formatDate.ts`)
- **Types**: PascalCase (`UserProfile.ts`)
- **Constants**: UPPER_SNAKE_CASE (`API_BASE_URL`)

---

## Code Standards

### TypeScript

- ✅ **Strict mode enabled**: `"strict": true` in `tsconfig.json`
- ✅ **No `any` types**: Use proper types or `unknown`
- ✅ **Explicit return types**: For functions exported from modules
- ✅ **Interface over type**: Prefer `interface` for object shapes

```typescript
// ✅ Good
interface User {
  id: string;
  name: string;
  email: string;
}

function getUser(id: string): Promise<User> {
  // ...
}

// ❌ Bad
function getUser(id: any): any {
  // ...
}
```

### ESLint/Prettier

- **Airbnb JavaScript Style Guide**: Base configuration
- **Prettier**: Code formatting
- **Import order**:
  1. Built-in modules (`react`, `react-dom`)
  2. Third-party modules (`axios`, `date-fns`)
  3. Local modules (`@/components`, `@/hooks`)

### Code Style

```typescript
// ✅ Good: Named exports
export function UserProfile({ user }: UserProfileProps) {
  // ...
}

// ✅ Good: Explicit types
interface UserProfileProps {
  user: User;
}

// ✅ Good: Destructuring props
function UserCard({ user, onEdit }: UserCardProps) {
  // ...
}

// ❌ Bad: Inline types
function UserCard(props: { user: any; onEdit: any }) {
  // ...
}
```

---

## Component Guidelines

### Component Structure

```typescript
// ✅ Standard component structure
import { useState } from 'react';
import type { User } from '@/types';

interface UserCardProps {
  user: User;
  onEdit?: (user: User) => void;
}

export function UserCard({ user, onEdit }: UserCardProps) {
  // 1. Hooks
  const [isEditing, setIsEditing] = useState(false);

  // 2. Event handlers
  const handleEdit = () => {
    setIsEditing(true);
    onEdit?.(user);
  };

  // 3. Render
  return (
    <div className="user-card">
      {/* Component content */}
    </div>
  );
}
```

### Component Types

1. **Presentational Components**: Pure UI, no API calls
2. **Container Components**: Handle data fetching, state management
3. **Layout Components**: Structure (Header, Sidebar, Footer)
4. **Feature Components**: Domain-specific (ProjectCard, TaskItem)

### Props Pattern

```typescript
// ✅ Good: Explicit interface
interface ButtonProps {
  label: string;
  onClick: () => void;
  variant?: 'primary' | 'secondary';
  disabled?: boolean;
}

export function Button({ label, onClick, variant = 'primary', disabled }: ButtonProps) {
  // ...
}

// ✅ Good: Compound components
export function Card({ children }: CardProps) {
  return <div className="card">{children}</div>;
}

Card.Header = function CardHeader({ children }: CardHeaderProps) {
  return <div className="card-header">{children}</div>;
};

Card.Body = function CardBody({ children }: CardBodyProps) {
  return <div className="card-body">{children}</div>;
};
```

### Component Composition

```typescript
// ✅ Good: Composition over inheritance
function Dashboard() {
  return (
    <PageLayout>
      <PageHeader title="Dashboard" />
      <KPISection />
      <ProjectsSection />
      <ActivitySection />
    </PageLayout>
  );
}
```

---

## API Layer

### Unified API Client

**Location**: `frontend/src/shared/api/client.ts`

**Usage**:

```typescript
import { apiClient, http } from '@/shared/api/client';

// ✅ Good: Use http helper
const users = await http.get<User[]>('/users');

// ✅ Good: Use apiClient for custom config
const response = await apiClient.get('/users', {
  params: { page: 1, limit: 10 }
});
```

### API Client Features

- ✅ **X-Request-ID propagation**: Automatic correlation ID
- ✅ **Tenant ID header**: Automatic `X-Tenant-ID`
- ✅ **CSRF token**: Automatic `X-CSRF-TOKEN`
- ✅ **Auth token**: Automatic `Authorization: Bearer`
- ✅ **Error handling**: Standard ApiError format
- ✅ **Retry logic**: For 429/503 errors (to be implemented)

### API Service Pattern

```typescript
// features/projects/api.ts
import { http } from '@/shared/api/client';
import type { Project, ProjectCreateInput, ProjectUpdateInput } from './types';

export const projectApi = {
  // List projects
  list: async (params?: { page?: number; limit?: number }) => {
    return http.get<{ data: Project[]; meta: PaginationMeta }>('/app/projects', {
      params,
    });
  },

  // Get single project
  get: async (id: string) => {
    return http.get<{ data: Project }>(`/app/projects/${id}`);
  },

  // Create project
  create: async (input: ProjectCreateInput) => {
    return http.post<{ data: Project }>('/app/projects', input);
  },

  // Update project
  update: async (id: string, input: ProjectUpdateInput) => {
    return http.put<{ data: Project }>(`/app/projects/${id}`, input);
  },

  // Delete project
  delete: async (id: string) => {
    return http.delete(`/app/projects/${id}`);
  },
};
```

### Error Handling

```typescript
import { ApiError } from '@/shared/api/client';

try {
  const project = await projectApi.get(id);
} catch (error) {
  if (error instanceof ApiError) {
    switch (error.status) {
      case 401:
        // Redirect to login
        break;
      case 403:
        // Show permission denied
        break;
      case 404:
        // Show not found
        break;
      case 422:
        // Show validation errors
        break;
      default:
        // Show generic error
    }
  }
}
```

---

## State Management

### Server State (React Query)

```typescript
// ✅ Good: Use React Query for server state
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { projectApi } from '../api';

export function useProjects(params?: { page?: number }) {
  return useQuery({
    queryKey: ['projects', params],
    queryFn: () => projectApi.list(params),
    staleTime: 60000, // 1 minute
  });
}

export function useCreateProject() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: projectApi.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] });
    },
  });
}
```

### Client State (useState, useReducer)

```typescript
// ✅ Good: Local state for UI
function ProjectFilters() {
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState<'all' | 'active' | 'completed'>('all');

  // ...
}
```

### Global State (Context/Zustand)

```typescript
// ✅ Good: Context for theme, auth
// contexts/ThemeContext.tsx
export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const [theme, setTheme] = useState<'light' | 'dark'>('light');
  // ...
}
```

---

## RBAC & Tenant Integration

### usePermissions Hook

```typescript
// hooks/usePermissions.ts
import { useAuth } from '@/contexts/AuthContext';

export function usePermissions() {
  const { user } = useAuth();

  const can = (permission: string, resource?: unknown) => {
    if (!user) return false;
    // Check permission logic
    return user.permissions?.includes(permission) ?? false;
  };

  return { can };
}
```

### PermissionGate Component

```typescript
// components/PermissionGate.tsx
interface PermissionGateProps {
  permission: string;
  resource?: unknown;
  fallback?: React.ReactNode;
  children: React.ReactNode;
}

export function PermissionGate({
  permission,
  resource,
  fallback = null,
  children,
}: PermissionGateProps) {
  const { can } = usePermissions();

  if (!can(permission, resource)) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}

// Usage
<PermissionGate permission="projects.create">
  <Button>Create Project</Button>
</PermissionGate>
```

### Tenant Scoping

```typescript
// ✅ Good: Tenant ID automatically included in API calls
// API client handles X-Tenant-ID header automatically

// ✅ Good: Tenant context
import { useTenant } from '@/hooks/useTenant';

function ProjectsPage() {
  const { tenantId } = useTenant();
  // tenantId is automatically included in API calls
}
```

---

## Design System

### Design Tokens

**Location**: `frontend/src/shared/tokens/`

```typescript
// ✅ Good: Use design tokens
import { spacingTokens, colorTokens } from '@/shared/tokens';

<div style={{ padding: spacingTokens.md, backgroundColor: colorTokens.primary[500] }}>
  Content
</div>
```

### Tailwind Classes

```typescript
// ✅ Good: Use Tailwind classes
<div className="p-4 bg-primary-500 text-white">
  Content
</div>
```

### Component Styling

```typescript
// ✅ Good: Tailwind + CSS modules for complex components
import styles from './ProjectCard.module.css';

export function ProjectCard() {
  return (
    <div className={`bg-white rounded-lg shadow-md p-6 ${styles.projectCard}`}>
      {/* ... */}
    </div>
  );
}
```

---

## Testing Requirements

### Unit Tests

```typescript
// ✅ Good: Component test
import { render, screen } from '@testing-library/react';
import { UserCard } from './UserCard';

describe('UserCard', () => {
  it('renders user name', () => {
    const user = { id: '1', name: 'John Doe', email: 'john@example.com' };
    render(<UserCard user={user} />);
    expect(screen.getByText('John Doe')).toBeInTheDocument();
  });
});
```

### Integration Tests

```typescript
// ✅ Good: API integration test
import { renderHook, waitFor } from '@testing-library/react';
import { useProjects } from './useProjects';

describe('useProjects', () => {
  it('fetches projects', async () => {
    const { result } = renderHook(() => useProjects());
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(result.current.data).toBeDefined();
  });
});
```

### E2E Tests

```typescript
// ✅ Good: Playwright E2E test
import { test, expect } from '@playwright/test';

test('create project', async ({ page }) => {
  await page.goto('/app/projects');
  await page.click('[data-testid="create-project-btn"]');
  await page.fill('[name="name"]', 'New Project');
  await page.click('[type="submit"]');
  await expect(page.locator('text=New Project')).toBeVisible();
});
```

---

## Performance Standards

### Performance Budgets

- **Page Load**: p95 < 500ms
- **API Response**: p95 < 300ms
- **Component Render**: < 100ms
- **Bundle Size**: < 1MB initial load

### Optimization Techniques

```typescript
// ✅ Good: Code splitting
const ProjectsPage = lazy(() => import('./ProjectsPage'));

// ✅ Good: Memoization
const MemoizedComponent = memo(ExpensiveComponent);

// ✅ Good: React Query caching
useQuery({
  queryKey: ['projects'],
  staleTime: 60000, // Cache for 1 minute
});
```

---

## Accessibility

### WCAG 2.1 AA Compliance

```typescript
// ✅ Good: Semantic HTML
<button aria-label="Close modal" onClick={handleClose}>
  <CloseIcon aria-hidden="true" />
</button>

// ✅ Good: Keyboard navigation
<button
  onKeyDown={(e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      handleClick();
    }
  }}
>
  Submit
</button>

// ✅ Good: ARIA labels
<input
  type="text"
  aria-label="Project name"
  aria-describedby="name-help"
/>
```

---

## Error Handling

### Error Boundaries

```typescript
// ✅ Good: Error boundary
class ErrorBoundary extends React.Component {
  // ...
}

// Usage
<ErrorBoundary fallback={<ErrorFallback />}>
  <App />
</ErrorBoundary>
```

### API Error Handling

```typescript
// ✅ Good: Centralized error handling
try {
  const project = await projectApi.get(id);
} catch (error) {
  if (error instanceof ApiError) {
    handleApiError(error);
  }
}
```

---

## CI/CD Requirements

### Pre-commit Checks

- ✅ TypeScript type checking
- ✅ ESLint
- ✅ Prettier formatting
- ✅ Unit tests

### CI Pipeline

- ✅ Build frontend
- ✅ Type checking
- ✅ Linting
- ✅ Unit tests
- ✅ Integration tests
- ✅ E2E tests (on merge)

---

## PR Checklist

Before submitting a PR:

- [ ] TypeScript compiles without errors
- [ ] ESLint passes
- [ ] Prettier formatted
- [ ] Unit tests pass
- [ ] Component follows design system
- [ ] API calls use unified client
- [ ] RBAC checks implemented
- [ ] Tenant scoping verified
- [ ] Accessibility tested
- [ ] Performance budget met
- [ ] Documentation updated

---

## Resources

- [ADR-007: Frontend Technology Split](../docs/adr/ADR-007-frontend-technology-split.md)
- [APP_UI_GUIDE.md](../docs/APP_UI_GUIDE.md)
- [Design Tokens](../frontend/src/shared/tokens/)
- [API Client](../frontend/src/shared/api/client.ts)

---

**Last Updated**: 2025-01-XX  
**Maintained By**: Development Team

