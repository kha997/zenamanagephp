# ZENAMANAGE UX/UI DESIGN RULES
## Product & UX Foundation - Universal View Standards

### 🎯 **A. Global Philosophy (All Pages)**

#### **Core Principles:**
- **Understand fast → act fast → with less scroll**
- **Big picture via KPI Strip**; immediate actions visible
- **Minimize scrolling**: group content, collapse long blocks, use drawers and sticky toolbars
- **Compact by default**: dense tables, compact paddings, collapsible panels
- **Smart focus tools**: Intelligent Search, Smart Filters, One-tap focus presets
- **Analysis & Export are first-class**: Every list has Analyze and Export capabilities
- **One consistent frame** on every page
- **Role-aware everywhere**: Show only what the role can do/see

#### **Universal Page Frame Structure:**
```
Header (fixed) → Global Navigation Row → Page Navigation Row → 
KPI Strip (1–2 rows, 4–8 cards) → Alert Bar → Main Content → Activity/History
```

---

### 🏗️ **B. Universal Page Frame (Shared, Reusable)**

#### **1) Header (Fixed; Identical Across App)**
**Left Side:**
- ZenaManage blue logo + "ZenaManage" + greeting: "Hello, {FirstName}"

**Right Side:**
- User avatar/name (dropdown: Profile, Settings, Switch Tenant, Logout)
- Notifications bell (actionable inbox)
- Theme toggle (light/dark, persisted)

**Behavior:**
- Sticky, compact height
- Keyboard accessible
- Present on all routes

#### **2) Global Navigation Row (Role-Aware)**
**Admin Users:**
- Dashboard, Users, Tenants, Projects (system-wide), Security, Alerts, Activities, Analytics, Settings

**Tenant Users:**
- Dashboard, Projects, Tasks, Calendar, Documents, Team, Templates, Settings

**Features:**
- Active state, numeric badges
- Keyboard navigation

#### **3) Page Navigation Row (Local)**
**Components:**
- Breadcrumbs (e.g., Dashboard ▸ Projects ▸ {Project})
- Local tabs (Overview | Documents | History | …)
- Primary contextual action(s) at right (e.g., Create Project, Start Focus)
- Compact with overflow "More ▾"

#### **4) KPI Strip (Above Alert Bar; 1–2 Rows)**
**Configuration:**
- 1 row (4 cards) by default
- 2 rows (6–8) for rich pages (Admin/Portfolio)
- Cards are tappable deep links to filtered views
- Cache 60s/tenant (real-time for critical)
- Compact visuals with delta/sparkline

#### **5) Alert Bar (Page-Scoped)**
**Content:**
- Up to 3 relevant Critical/High alerts
- Actions: Resolve / Acknowledge / Mute (time-boxed)
- Alerts also surface in the Header bell

#### **6) Main Content**
**Components:**
- Lists/Boards/Forms/Drawers
- Sticky bulk toolbar
- Inline row actions
- Smart Search + Smart Filters above the list (compact, collapsible)

#### **7) Activity / History**
**Features:**
- Recent 10 items, audit link, related changes
- Right rail or bottom
- Collapsed by default

**Role Reminder:** All layers (Nav, KPI, Alerts, Content) are role-aware

---

### 🧭 **D. Navigation Design Principles (Updated)**

#### **Core Navigation Philosophy:**
- **Always Visible**: Navigation should be accessible at all times
- **No Hidden Menus**: Avoid hamburger menus and hidden navigation
- **Consistent Experience**: Same navigation structure across all devices
- **Progressive Disclosure**: Show primary navigation, secondary in context

#### **Navigation Hierarchy:**
1. **Header Navigation**: Primary app sections (Dashboard, Projects, Tasks, etc.)
2. **App Navigation**: Contextual navigation within each section
3. **Breadcrumbs**: Current location and path
4. **Action Buttons**: Contextual actions (Create, Edit, Delete)

#### **Mobile Navigation Strategy:**
- **Horizontal Scroll**: Navigation items scroll horizontally when needed
- **Touch-Friendly**: Minimum 44px touch targets
- **No Hamburger**: Navigation remains visible and accessible
- **Responsive Breakpoints**: Adapt layout but maintain visibility

#### **Why No Hamburger Menu:**
- **Better UX**: Users can see all available options immediately
- **Faster Navigation**: No need to open/close menu
- **Consistent**: Same experience across desktop and mobile
- **Accessibility**: Easier for screen readers and keyboard navigation
- **Reduced Cognitive Load**: Less mental effort to find navigation

---

### 📱 **C. Mobile Optimization (Must)**

#### **Header:**
- Collapses greeting after first line
- Avatar/bell/toggle remain
- Brand logo keeps tap target ≥44px

#### **Global Nav:**
- **NO Hamburger Menu** - Navigation is always visible and accessible
- Show active section badge counts
- Horizontal scroll for overflow items on mobile
- Consistent navigation experience across all devices

#### **Page Nav:**
- Breadcrumbs truncate
- Tabs scroll horizontally
- Primary action as floating action button (FAB)

#### **KPI Strip:**
- Stack cards 2-per-row (or 1-per-row on small phones)
- If 2 rows configured, stack sequentially
- Allow "Show more KPIs" collapse

#### **Alert Bar:**
- Single-line, collapsible
- "View All" opens drawer

#### **Sticky Toolbars:**
- Collapse into bottom Action Bar
- FAB expands bulk actions

#### **Tables:**
- Switch to card list on mobile
- Same columns prioritized
- Column manager persists per user

#### **Performance:**
- Defer non-critical charts
- Prefetch on Wi-Fi only

---

### ♿ **D. Accessibility (WCAG 2.1 AA)**

#### **Keyboard Navigation:**
- All interactive elements tabbable
- Visible focus indicators
- Escape closes drawers
- Shortcuts: Search (/), Save (Ctrl/Cmd+S), Open Filters (F)

#### **Screen Reader:**
- KPI cards expose name, value, delta, period
- Use `aria-live=polite` for KPI updates
- Alert Bar has `role=status` + actionable buttons with labels
- Breadcrumbs use `nav[aria-label="breadcrumb"]`

#### **Color/Contrast:**
- Pass AA (4.5:1 text)
- Provide non-color cues (icons/labels)

#### **Testing:**
- Lighthouse/axe audits in CI
- No new page may ship with critical a11y issues

---

### ⚡ **E. Performance & Large Data**

#### **Server-Side Optimization:**
- Pagination for lists with stable sort (id/created_at)
- Smart Search results cached per query + tenant for 60s
- Autocomplete throttled (≥300ms)
- N+1 prevention in hot paths
- Projection queries for list views

#### **Performance Budgets:**
- API list p95 < 300ms
- Page p95 < 500ms

#### **Tooling:**
- Laravel Telescope (dev) + New Relic/Datadog (staging/prod)
- Dashboards tracking p95, error rate, QPS per tenant
- KPI precompute where needed (hourly jobs)

---

### 🚨 **F. Error & Empty States (UX)**

#### **Standard Error Envelope (i18n-ready):**
```json
{
  "error": {
    "id": "req_7f1a",
    "code": "E001.INVALID_INPUT",
    "message": "Invalid input",
    "details": {}
  }
}
```

#### **HTTP Status Codes:**
- 400/401/403/404/409/422/429/500/503
- Proper `Retry-After` on 429/503
- Do not leak internals; use error.id to correlate with logs

#### **UI Behavior:**
- **Empty state**: "No projects found." + primary CTA (Create Project) + secondary (Clear filters)
- **429/503**: friendly message + Retry (exponential backoff), show next retry time
- **500**: neutral message + Try Again + help link (only if authenticated)

#### **Localization:**
- Error messages translated by `Accept-Language` (fallback en)

---

### ⚙️ **G. User Customization (Per User, Persisted)**

#### **KPI Strip:**
- Users can choose which cards appear (from page's allowed set)
- Choose whether to show 1 or 2 rows

#### **Layout:**
- Choose right rail vs bottom for Activity
- Remember collapsed/expanded panels

#### **Saved Views:**
- Store search, filters, sort, visible columns, density, view type (table/kanban/gantt)

#### **Density & Theme:**
- Persist compact/comfortable density
- Light/dark mode persistence

#### **Storage:**
- Preferences persisted in DB (scoped by user + tenant)

---

### 📊 **H. Audit & Logging**

#### **Structured Logs (JSON):**
- timestamp, level, tenant_id, user_id, X-Request-Id, route, latency, outcome
- PII redacted

#### **Audited Actions (Examples):**
- Drag-drop due dates
- Publish/unpublish template
- Role changes
- Archive/unarchive
- Cross-tenant admin actions
- Focus start/pause/stop

#### **Retention:**
- Audit logs: 1 year (configurable)
- App logs: 90 days by default

#### **Incidents:**
- All 5xx trigger CRITICAL log + Slack/Email to on-call
- Include error.id, route, tenant, recent KPIs, runbook link

---

### 🧪 **I. Testing & CI/CD (Summary)**

#### **Test Strategy:**
- Unit + Integration + E2E (Cypress/Playwright)
- Critical flows: login→dashboard→projects→tasks
- Factories for deterministic test data
- Separate TestSeeder; never leak to prod

#### **CI Gates:**
- Lint → Unit → Integration → Build → OpenAPI gen → a11y audit (Lighthouse/axe) → E2E (staging) → Security checks → Manual approve → Deploy

#### **Deploy Safety:**
- Blue/green or canary deployment
- Automatic rollback on SLO breach
- Feature flags for dark-launch
- Zero-downtime migrations with backfills and safe drops

---

### 📋 **J. Module Snapshots (Mobile/A11y/Perf-Ready)**

#### **Tenant Dashboard /app/dashboard:**
- KPIs: 1 row default (allow 2)
- Mobile: stack KPIs; Now Panel becomes FAB menu

#### **Admin Dashboard /admin:**
- KPIs: 2 rows; mobile collapses second row
- Incidents drawer prioritized

#### **Projects /app/projects:**
- Table→card list on mobile (show Name/Code, Health, Progress, Due, PM)
- Analyze drawer lazy-loads charts

#### **Tasks /app/tasks:**
- Kanban preferred on mobile
- Focus FAB
- Screen reader labels for status & priority chips

#### **Admin Tasks /admin/tasks:**
- Tenant filter always visible
- Wide tables get horizontal scroll with sticky headers

#### **Documents /app/documents:**
- Content preview defers on mobile
- MIME icon + version speakable labels

#### **Team /app/team:**
- Invite flow as bottom sheet on mobile

#### **Users /admin/users:**
- Sensitive actions require confirm + audit note

#### **Tenants /admin/tenants:**
- Usage charts collapse on mobile
- CSV export emphasizes plan/usage

#### **Templates /app/templates:**
- Builder uses tabs on mobile
- Publish/unpublish prompts require reason (audited)

#### **Calendar /app/calendar:**
- Mobile defaults to Week with swipe
- A11y describes overlapping events

#### **Alerts / Activities / Settings / Invitations:**
- Follow frame; compact forms
- A11y labels on toggles

---

### 🏢 **K. Multi-Tenant Scalability (Recap)**

#### **Query Scoping:**
- All queries must scope by tenant_id
- Composite indexes on (tenant_id, fk) for hot tables

#### **Growth Path:**
- Read replicas → partitioning by tenant/time → sharding by tenant_id with routing layer

#### **Jobs:**
- Include tenant_id and are idempotent
- Isolation verified by negative tests

---

### ✅ **L. Definition of Done (DoD)**

#### **Code Quality:**
- No TODO/console/test routes left
- Legacy plan updated

#### **Accessibility:**
- A11y audits pass (no critical issues)

#### **Performance:**
- P95 meets budgets (page < 500ms; list API < 300ms)

#### **Documentation:**
- OpenAPI + versioned docs updated
- Mermaid map matches code

#### **Audit:**
- Audit entries present for sensitive actions

#### **UX:**
- Error/empty states implemented with friendly CTAs
- Preferences (views, density, theme, KPI selection) persist per user

---

## 🎯 **IMPLEMENTATION CHECKLIST**

### **Before Creating Any View:**
- [ ] Follows universal page frame structure
- [ ] Implements role-aware navigation
- [ ] Includes KPI strip configuration
- [ ] Has proper error/empty states
- [ ] Mobile-optimized layout
- [ ] A11y compliant (WCAG 2.1 AA)
- [ ] Performance budget met

### **During Development:**
- [ ] Smart search and filters implemented
- [ ] Analysis and export capabilities added
- [ ] User customization options included
- [ ] Audit logging for sensitive actions
- [ ] Responsive design tested
- [ ] Keyboard navigation working

### **Before Completion:**
- [ ] All accessibility tests pass
- [ ] Performance benchmarks met
- [ ] Mobile experience optimized
- [ ] Error handling complete
- [ ] User preferences persist
- [ ] Documentation updated

---

*This document is binding for all UI/UX development*
*Last Updated: September 24, 2025*
*Version: 1.0*
*Next Review: October 24, 2025*
