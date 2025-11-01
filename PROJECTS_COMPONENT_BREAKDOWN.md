# 🧩 PROJECTS MODULE - COMPONENT BREAKDOWN

## 📋 OVERVIEW
Component structure breakdown cho Projects page rebuild.

---

## 🎯 COMPONENT TREE

```
ProjectsPage
├── Header (from layout)
├── Primary Navigator (from layout)
├── ProjectsPageHeader
│   ├── Title
│   ├── Stats
│   └── Quick Actions
├── ProjectsActionBar
│   ├── SmartFilters
│   │   ├── FilterToggle
│   │   ├── Quick Presets
│   │   ├── Deep Filters
│   │   └── Saved Views
│   └── ViewToggle (Grid/Table)
├── ProjectsList
│   ├── Loading State
│   ├── Empty State
│   ├── ProjectCard (Grid View)
│   └── ProjectRow (Table View)
└── Pagination
```

---

## 📦 COMPONENTS DETAIL

### 1. ProjectsPage.tsx
**Responsibility**: Main page container, state management

```typescript
interface ProjectsPageProps {}

// State
- projects: ProjectDTO[]
- loading: boolean
- filters: FilterState
- pagination: PaginationState
- viewMode: 'grid' | 'table'

// Functions
- loadProjects()
- applyFilters()
- handleSearch()
- handleCreate()
- handleArchive()
- handleDelete()
```

**Location**: `resources/views/app/projects/index.blade.php`

---

### 2. ProjectsActionBar.tsx
**Responsibility**: Action bar với filters và quick actions

```typescript
interface ProjectsActionBarProps {
  onFilterChange: (filters: FilterState) => void;
  onSearchChange: (query: string) => void;
  onCreateClick: () => void;
}
```

**Location**: New component  
**Sub-components**: SmartFilters, QuickActions

---

### 3. SmartFilters.tsx
**Responsibility**: Advanced filtering component

```typescript
interface SmartFiltersProps {
  context: 'projects';
  presets: FilterPreset[];
  deepFilters: DeepFilter[];
  onFilterApply: (filters: FilterState) => void;
  onFilterClear: () => void;
}

// Features:
- Filter presets (1-click filtering)
- Deep filters (status, date range, progress range)
- Saved views
- Active filter count badge
- Clear all filters
```

**Location**: Reuse `resources/views/components/shared/filters/smart-filters.blade.php`

---

### 4. QuickActions.tsx
**Responsibility**: Quick action buttons

```typescript
interface QuickActionsProps {
  actions: QuickAction[];
  onActionClick: (action: QuickAction) => void;
}

// Available actions:
- Create Project
- Export Projects
- Import Projects
- Bulk Archive
```

**Location**: New component or reuse dashboard quick-actions

---

### 5. ProjectCard.tsx (Grid View)
**Responsibility**: Display project as card

```typescript
interface ProjectCardProps {
  project: ProjectDTO;
  onEdit: (id: string) => void;
  onArchive: (id: string) => void;
  onDelete: (id: string) => void;
}

// Displays:
- Project name
- Description (truncated)
- Status badge
- Progress bar
- Owner avatar
- Team members count
- Tasks count
- Actions menu (Edit, Archive, Delete)
```

---

### 6. ProjectRow.tsx (Table View)
**Responsibility**: Display project as table row

```typescript
interface ProjectRowProps {
  project: ProjectDTO;
  columns: string[];
  onEdit: (id: string) => void;
  onArchive: (id: string) => void;
  onDelete: (id: string) => void;
}

// Columns:
- Name (with avatar)
- Status
- Progress
- Owner
- Team
- Tasks
- Budget
- Created At
- Actions
```

---

### 7. ProjectList.tsx
**Responsibility**: Container for project cards/rows

```typescript
interface ProjectListProps {
  projects: ProjectDTO[];
  viewMode: 'grid' | 'table';
  loading: boolean;
  empty: boolean;
  onProjectClick: (id: string) => void;
}

// Handles:
- Loading skeleton
- Empty state
- Grid/Table view toggle
- Rendering ProjectCard or ProjectRow
```

---

### 8. Pagination.tsx
**Responsibility**: Pagination controls

```typescript
interface PaginationProps {
  meta: ProjectMetaDTO;
  onPageChange: (page: number) => void;
}

// Features:
- Page numbers
- Previous/Next buttons
- Items per page selector
- Jump to page
- Showing X-Y of Z results
```

**Location**: Reusable component `resources/views/components/shared/pagination.blade.php`

---

## 🔄 DATA FLOW

```
User Action
    ↓
ProjectsPage (state management)
    ↓
ProjectsActionBar (filter/search)
    ↓
API Call (/api/v1/projects?filters=...)
    ↓
Backend (Controller → Service → Repository)
    ↓
Response (ProjectDTO[])
    ↓
ProjectsPage (update state)
    ↓
ProjectList (re-render)
    ↓
ProjectCard/ProjectRow (display)
```

---

## 📂 FILE STRUCTURE

```
resources/views/app/projects/
├── index.blade.php (main page)
├── _action-bar.blade.php (filters + actions)
├── _filters.blade.php (smart filters)
├── _quick-actions.blade.php (quick actions)
├── _project-card.blade.php (grid view)
├── _project-row.blade.php (table view)
├── _empty-state.blade.php
└── _loading-skeleton.blade.php

frontend/src/pages/app/projects/
├── ProjectsPage.tsx
├── ProjectsActionBar.tsx
├── SmartFilters.tsx
├── QuickActions.tsx
├── ProjectCard.tsx
├── ProjectRow.tsx
├── ProjectList.tsx
└── EmptyState.tsx
```

---

## 🎨 UI/UX SPECIFICATIONS

### Grid View
- Card size: 320px × 400px
- Avatar: 40px
- Progress bar: Thick (8px height)
- Status badge: Pill shape
- Hover effect: Lift + shadow
- 3 columns on desktop, 2 on tablet, 1 on mobile

### Table View
- Row height: 64px
- Sortable columns
- Checkbox for bulk actions
- Row hover highlight
- Fixed header on scroll
- Export visible columns

### Smart Filters
- Collapsible panel
- Persistent saved views
- URL params synchronization
- Undo/Redo filter changes

---

## ✅ IMPLEMENTATION CHECKLIST

### Phase 1: Setup
- [ ] Create API contract document
- [ ] Create component breakdown
- [ ] Setup route for /app/projects
- [ ] Create blade view skeleton
- [ ] Create React components skeleton

### Phase 2: Backend
- [ ] Implement GET /api/v1/projects endpoint
- [ ] Implement filters logic
- [ ] Implement pagination
- [ ] Implement sorting
- [ ] Write PHPUnit tests
- [ ] Test multi-tenant isolation
- [ ] Test RBAC

### Phase 3: Frontend (Blade)
- [ ] Build main page structure
- [ ] Integrate SmartFilters component
- [ ] Integrate QuickActions component
- [ ] Build project card/row display
- [ ] Add pagination
- [ ] Add loading states
- [ ] Add empty states

### Phase 4: Integration
- [ ] Connect API calls
- [ ] Implement real-time updates
- [ ] Add error handling
- [ ] Add success notifications
- [ ] Add optimistic updates

### Phase 5: Testing
- [ ] Write E2E tests
- [ ] Write unit tests
- [ ] Manual testing
- [ ] Performance testing
- [ ] Accessibility testing

---

## 🎯 SUCCESS METRICS

### Performance
- Page load < 500ms (p95)
- Filter response < 300ms (p95)
- Smooth scrolling 60fps
- No layout shifts

### User Experience
- Filters work instantly
- Search with debounce
- Smooth pagination
- Clear visual feedback
- Mobile responsive

---

**Status**: 📋 Ready for Implementation

