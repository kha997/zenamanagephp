# ZenaManage Dashboard Design Principles
## Reusable Design Framework for Future Dashboards

### 🎯 **Core Philosophy: Information → Action (KPI-first)**

**"Understand in 3s → Act in 1 click"**

Every dashboard follows the principle that users should understand their current state within 3 seconds and be able to take meaningful action with a single click.

---

## A. **Information → Action (KPI-first)**

### **4 KPIs Maximum Above the Fold**
- **Rule**: Maximum 4 KPI cards visible without scrolling
- **Layout**: Header → Global Nav → Page Nav → KPI Strip (1-2 rows) → Alert Bar → Content
- **Each KPI Card Must Have**:
  - Primary metric value (large, prominent)
  - Secondary context (trend, comparison)
  - **Primary action button** (e.g., "View overdue tasks", "Create project")
  - Visual indicator (icon, color coding)

### **KPI Card Structure**
```html
<div class="kpi-card" data-kpi="metric-name">
    <div class="kpi-header">
        <div class="kpi-icon">
            <i class="fas fa-icon"></i>
        </div>
        <div class="kpi-info">
            <h3 class="kpi-label">Metric Label</h3>
            <p class="kpi-value kpi--metric-name">—</p>
            <p class="kpi-trend">+5% from last week</p>
        </div>
    </div>
    <div class="kpi-actions">
        <button class="btn btn-primary btn-sm" data-action="primary">
            Primary Action
        </button>
    </div>
</div>
```

### **CSS Hooks for KPI Numbers**
```css
/* KPI Value Hooks */
.kpi--projects-active { /* Active projects count */ }
.kpi--tasks-today { /* Tasks due today */ }
.kpi--tasks-overdue { /* Overdue tasks */ }
.kpi--focus-minutes { /* Focus minutes today */ }
.kpi--revenue-current { /* Current revenue */ }
.kpi--team-active { /* Active team members */ }
.kpi--completion-rate { /* Project completion rate */ }
.kpi--client-satisfaction { /* Client satisfaction score */ }
```

---

## B. **Smart Focus System**

### **Smart Search + Filters**
- **Debounced search**: 250ms delay, server-side processing
- **Always scoped**: Tenant + Role permissions
- **Search modal**: Global search accessible via `Ctrl+K` or search icon
- **Results structure**: Projects, Tasks, Users, Documents

```html
<!-- Search Modal Structure -->
<div id="search-modal" class="search-modal">
    <div class="search-header">
        <input type="text" id="kbdInput" placeholder="Search projects, tasks, users...">
    </div>
    <div class="search-results">
        <ul data-zena-search-results>
            <!-- Populated by JavaScript -->
        </ul>
    </div>
</div>
```

### **Saved Views & User Preferences**
- **Density settings**: Compact, Normal, Comfortable
- **Column visibility**: User-customizable table columns
- **Sort preferences**: Remember last sort order
- **Filter presets**: Save common filter combinations
- **Persistence**: Store in localStorage + sync to server

### **Focus Mode**
- **Hide noise**: Collapse non-essential elements
- **Pin active work**: Highlight current tasks/projects
- **Idle detection**: Auto-hide after 5 minutes of inactivity
- **WS fallback**: Graceful degradation when WebSocket unavailable

---

## C. **API-first & Predictable Architecture**

### **HTTP Method Standards**
- **GET**: Safe operations, no side effects
- **POST/PUT/PATCH/DELETE**: All mutations via `/api/v1/app/*`
- **Error envelope**: Consistent error structure
- **i18n support**: All error messages translatable

### **Error Handling Pattern**
```javascript
// Standardized error envelope
{
    "error": {
        "id": "unique-error-id",
        "code": "VALIDATION_ERROR",
        "message": "Invalid input provided",
        "details": {
            "field": "email",
            "reason": "Invalid email format"
        }
    }
}
```

### **Empty/Error States**
- **Empty state**: Clear CTA, helpful illustration
- **Error state**: Retry button, error details, fallback options
- **Loading state**: Skeleton screens, progress indicators

### **Pagination & Caching**
- **Server-side pagination**: All lists paginated
- **KPI caching**: 60 seconds per tenant
- **N+1 prevention**: Eager loading, query optimization
- **Cache invalidation**: Smart cache busting on mutations

---

## D. **Multi-tenant & RBAC Integration**

### **Tenant Scoping**
- **Every request**: Must include tenant context
- **Header method**: `X-Tenant-Id` header
- **Cookie method**: Tenant ID in session cookie
- **Backend enforcement**: All queries filtered by tenant_id

### **Role-Based Access Control**
- **UI adaptation**: Show/hide elements based on role
- **Action permissions**: Disable buttons for unauthorized actions
- **Data filtering**: Role-based data visibility
- **No cross-tenant leakage**: Strict isolation in search, KPIs, notifications

### **Permission Levels**
```javascript
const PERMISSIONS = {
    SUPER_ADMIN: ['*'],
    PROJECT_MANAGER: ['projects:read', 'projects:write', 'tasks:read', 'tasks:write'],
    TEAM_MEMBER: ['projects:read', 'tasks:read', 'tasks:write'],
    CLIENT: ['projects:read', 'tasks:read']
};
```

---

## E. **Mobile & Accessibility (WCAG 2.1 AA)**

### **Mobile-First Design**
- **KPI stacking**: Vertical layout on mobile
- **Sticky actions**: Floating action buttons
- **FAB positioning**: Doesn't cover inline actions
- **Touch targets**: Minimum 44px touch targets
- **Responsive breakpoints**: Mobile (320px), Tablet (768px), Desktop (1024px+)

### **Accessibility Requirements**
- **Keyboard navigation**: Full keyboard support
- **ARIA labels**: All interactive elements labeled
- **High contrast**: Support for high contrast mode
- **Screen readers**: Semantic HTML, proper headings
- **Focus management**: Visible focus indicators

### **Mobile Layout Structure**
```html
<!-- Mobile KPI Stack -->
<div class="kpi-stack-mobile">
    <div class="kpi-card-mobile" data-kpi="metric">
        <div class="kpi-content">
            <span class="kpi-label">Label</span>
            <span class="kpi-value">Value</span>
        </div>
        <button class="kpi-action-mobile" aria-label="Action">
            <i class="fas fa-arrow-right"></i>
        </button>
    </div>
</div>
```

---

## F. **Performance & Stability**

### **Performance Budgets**
- **Page load**: p95 < 500ms
- **API responses**: p95 < 300ms
- **KPI updates**: < 200ms
- **Search results**: < 150ms

### **Monitoring & Observability**
- **Telescope integration**: API request monitoring
- **New Relic**: Performance tracking
- **Error tracking**: Sentry integration
- **User analytics**: Heatmaps, user flows

### **WebSocket → Polling Fallback**
- **Primary**: WebSocket for real-time updates
- **Fallback**: 30-second polling when WS unavailable
- **Graceful degradation**: No functionality loss
- **Connection status**: Visual indicator of connection state

### **Defensive UX**
- **Retry logic**: Exponential backoff for 429/503 errors
- **Visible feedback**: Toast notifications for errors
- **"Try again" actions**: Clear recovery paths
- **Offline support**: Basic offline functionality

---

## G. **Code Quality & Hygiene**

### **Single Source of Truth**
- **Layout components**: Reusable layout templates
- **Component naming**: Consistent naming conventions
- **No duplication**: DRY principle enforced
- **Shared utilities**: Common functions in utilities

### **OpenAPI Contract**
- **API documentation**: Complete OpenAPI spec
- **Contract testing**: Validate API contracts
- **Client generation**: Auto-generate API clients
- **Versioning**: Semantic versioning for APIs

### **CI/CD Quality Gates**
- **Linting**: ESLint, Prettier, PHP CS Fixer
- **Unit tests**: 100% coverage for new code
- **Integration tests**: API + DB + Auth + RBAC
- **E2E tests**: Critical user paths
- **Lighthouse**: Performance and accessibility audits
- **Axe**: Accessibility testing

### **Debug & Development**
- **Debug routes**: Limited to `/_debug/*` namespace
- **DebugGate**: Environment + IP allowlist
- **Development tools**: Hot reload, source maps
- **Error boundaries**: Graceful error handling

---

## H. **Implementation Hooks & Examples**

### **HTML Hooks for JavaScript Integration**
```html
<!-- KPI Numbers with CSS Classes -->
<p class="text-2xl font-bold text-gray-900 kpi--projects-active">—</p>
<p class="text-2xl font-bold text-gray-900 kpi--tasks-today">—</p>
<p class="text-2xl font-bold text-gray-900 kpi--tasks-overdue">—</p>
<p class="text-2xl font-bold text-gray-900 kpi--focus-minutes">—</p>

<!-- Dynamic Content Containers -->
<ul class="flex h-full flex-col justify-between gap-6" data-zena-meetings></ul>
<div id="notification-dropdown" class="relative" data-zena-notifications></div>
<div class="search-results" data-zena-search-results></div>

<!-- Action Buttons with Data Attributes -->
<button class="btn btn-primary" data-action="view-overdue-tasks">
    View Overdue Tasks
</button>
<button class="btn btn-secondary" data-action="create-project">
    Create Project
</button>
```

### **JavaScript Integration Pattern**
```javascript
// Standard dashboard initialization
function initializeDashboard() {
    // Load KPIs
    loadKPIs();
    
    // Load dynamic content
    loadMeetings();
    loadNotifications();
    
    // Initialize search
    initializeSmartSearch();
    
    // Set up event listeners
    setupEventListeners();
}

// KPI rendering with hooks
function renderKPIs(data) {
    setText('.kpi--projects-active', data.projects_active ?? '—');
    setText('.kpi--tasks-today', data.tasks_due_today ?? '—');
    setText('.kpi--tasks-overdue', data.tasks_overdue ?? '—');
    setText('.kpi--focus-minutes', (data.focus_minutes_today ?? 0) + ' min');
}
```

### **CSS Framework Integration**
```css
/* KPI Card Base Styles */
.kpi-card {
    @apply bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300;
}

.kpi-value {
    @apply text-2xl font-bold text-gray-900;
}

.kpi-trend {
    @apply text-xs;
}

.kpi-trend.positive {
    @apply text-green-600;
}

.kpi-trend.negative {
    @apply text-red-600;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .kpi-stack-mobile {
        @apply space-y-4;
    }
    
    .kpi-card-mobile {
        @apply flex items-center justify-between p-4 bg-white rounded-lg shadow;
    }
}
```

---

## I. **Dashboard Types & Variations**

### **Executive Dashboard**
- **Focus**: High-level KPIs, trends, strategic metrics
- **KPIs**: Revenue, growth, team performance, client satisfaction
- **Actions**: View reports, schedule meetings, approve budgets

### **Project Manager Dashboard**
- **Focus**: Project status, team workload, deadlines
- **KPIs**: Active projects, overdue tasks, team utilization
- **Actions**: Create projects, assign tasks, review progress

### **Team Member Dashboard**
- **Focus**: Personal tasks, deadlines, collaboration
- **KPIs**: My tasks, focus time, collaboration score
- **Actions**: Update tasks, log time, join meetings

### **Client Dashboard**
- **Focus**: Project progress, deliverables, communication
- **KPIs**: Project completion, milestone status, response time
- **Actions**: View progress, request changes, communicate

---

## J. **Quality Assurance Checklist**

### **Before Launch**
- [ ] All KPIs load within 500ms
- [ ] Mobile layout tested on multiple devices
- [ ] Accessibility audit passed (WCAG 2.1 AA)
- [ ] All API endpoints documented
- [ ] Error handling tested
- [ ] Multi-tenant isolation verified
- [ ] Performance budgets met
- [ ] Security review completed

### **Ongoing Maintenance**
- [ ] Performance monitoring active
- [ ] Error tracking configured
- [ ] User feedback collected
- [ ] Analytics reviewed monthly
- [ ] Security updates applied
- [ ] Dependency updates managed

---

## K. **Future Enhancements**

### **Planned Features**
- **AI-powered insights**: Smart recommendations
- **Advanced analytics**: Predictive analytics
- **Custom dashboards**: User-configurable layouts
- **Real-time collaboration**: Live updates
- **Voice commands**: Hands-free interaction
- **Augmented reality**: AR project visualization

### **Technology Roadmap**
- **Progressive Web App**: Offline functionality
- **Micro-frontends**: Modular architecture
- **GraphQL**: Efficient data fetching
- **WebAssembly**: Performance optimization
- **Machine Learning**: Intelligent automation

---

*This document serves as the definitive guide for all ZenaManage dashboard development. All new dashboards must comply with these principles to ensure consistency, performance, and user experience.*

**Last Updated**: December 2024  
**Version**: 1.0  
**Maintainer**: ZenaManage Development Team
