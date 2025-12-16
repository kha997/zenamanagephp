# Route Architecture - ZenaManage

## Overview

ZenaManage follows a strict route architecture that separates operational features (React SPA) from governance features (Blade templates).

**Last Updated:** 2025-01-XX  
**Status:** Active

---

## Core Principles

### 1. `/app/*` = React SPA 100% (Tác nghiệp)
- All user-facing operational features
- React Router handles client-side routing
- Laravel serves single entry point: `view('app.spa')`
- No Blade views or controllers for `/app/*` pages

### 2. `/admin/*` = Blade Templates (Governance)
- System-wide administration and configuration
- Blade views in `resources/views/admin/`
- Controllers in `app/Http/Controllers/Web/Admin/`

### 3. `/api/*` = API Endpoints
- All business logic accessed via API
- Token-based authentication (Sanctum)
- Ability-based authorization (admin/tenant)

---

## Route Structure

### `/app/*` Routes (React SPA)

**Entry Point:**
```php
// routes/web.php
Route::middleware(['web', 'auth:web'])->group(function () {
    Route::get('/app/{any}', fn() => view('app.spa'))->where('any', '.*');
});
```

**React Router:** `frontend/src/app/router.tsx`

**Available Routes:**
- `/app/dashboard` - Dashboard
- `/app/projects` - Projects list
- `/app/projects/:id` - Project detail
- `/app/projects/create` - Create project
- `/app/projects/:id/edit` - Edit project
- `/app/tasks` - Tasks list
- `/app/tasks/kanban` - Tasks Kanban board
- `/app/tasks/:id` - Task detail
- `/app/tasks/create` - Create task
- `/app/tasks/:id/edit` - Edit task
- `/app/clients` - Clients list
- `/app/clients/create` - Create client
- `/app/clients/:id` - Client detail
- `/app/clients/:id/edit` - Edit client
- `/app/quotes` - Quotes list
- `/app/quotes/create` - Create quote
- `/app/quotes/:id` - Quote detail
- `/app/quotes/:id/edit` - Edit quote
- `/app/documents` - Documents list
- `/app/documents/create` - Create document
- `/app/documents/approvals` - Document approvals
- `/app/change-requests` - Change requests
- `/app/gantt` - Gantt chart
- `/app/qc` - QC module
- `/app/reports` - Reports
- `/app/settings` - Settings

### `/admin/*` Routes (Blade Templates)

**Entry Point:**
```php
// routes/admin.php
Route::prefix('admin')->name('admin.')->middleware(['web', 'auth:web', 'admin.access'])->group(function () {
    // Admin routes
});
```

**Available Routes:**
- `/admin/dashboard` - Admin dashboard
- `/admin/users` - User management
- `/admin/tenants` - Tenant management
- `/admin/projects` - Project portfolio
- `/admin/templates` - Template management
- `/admin/settings` - System settings
- `/admin/analytics` - Analytics
- `/admin/activities` - Audit log
- `/admin/security` - Security center
- `/admin/alerts` - System alerts
- `/admin/maintenance` - Maintenance

### `/app-legacy/*` Routes (Temporary)

**Purpose:** Legacy Blade routes during migration period

**Routes:**
- `/app-legacy/tasks/kanban` - Legacy Kanban (migrating to React)
- `/app-legacy/tasks/create` - Legacy create (migrating to React)
- `/app-legacy/tasks/{task}` - Legacy detail (migrating to React)
- `/app-legacy/tasks/{task}/edit` - Legacy edit (migrating to React)
- `/app-legacy/tasks/{task}/documents` - Legacy documents (migrating to React)
- `/app-legacy/tasks/{task}/history` - Legacy history (migrating to React)
- `/app-legacy/clients/*` - Legacy clients routes (migrating to React)
- `/app-legacy/quotes/*` - Legacy quotes routes (migrating to React)
- `/app-legacy/documents/*` - Legacy documents routes (migrating to React)

**Cleanup:** These routes will be removed after migration is complete.

---

## Server Configuration (1-Origin Routing)

Server-level routing ensures proper fallback and deep linking. All routing decisions are made at the infrastructure level, not in application code.

### Apache (.htaccess)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # SPA fallback for /app/* routes
    # All /app/* routes are handled by React Router
    # If file doesn't exist, serve /app/index.html for SPA routing
    # This ensures deep linking works (F5/refresh on /app/tasks/123)
    RewriteCond %{REQUEST_URI} ^/app/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^app/(.*)$ /app/index.html [L]
    
    # Admin and API routes go directly to Laravel
    # (No special rules needed - default Laravel routing handles these)
</IfModule>
```

### Nginx

```nginx
# Admin routes (Blade) - Laravel handles
location /admin/ {
    try_files $uri $uri/ /index.php?$query_string;
}

# API routes - Laravel handles
location /api/ {
    try_files $uri $uri/ /index.php?$query_string;
}

# React SPA routes - serve from public/app/ (if built)
location /app/ {
    alias /path/to/public/app/;
    try_files $uri /app/index.html;
    
    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}

# Default: Laravel routes (for Blade and other Laravel routes)
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Critical Rules

- **No FE-redirects**: Avoid client-side redirects; handle routing at server level
- **Deep linking**: F5/refresh on `/app/tasks/123` must work (server serves `index.html`, React Router handles routing)
- **Single origin**: All routing decisions made at server level, not in application code
- **Admin/API direct**: `/admin/*` and `/api/*` route directly to Laravel (no SPA fallback)

---

## Migration Status

### ✅ Completed Migrations
- Dashboard → React
- Projects (list, detail, create, edit) → React
- Tasks (list, detail, create, edit, kanban) → React
- Tasks Documents/History → React tabs
- Clients (list, detail, create, edit) → React
- Quotes (list, detail, create, edit) → React
- Documents (list, create, approvals) → React

### 🔄 In Progress
- Legacy routes cleanup (removing `/app-legacy/*` after verification)

### 📋 Pending
- None

---

## API Endpoints

All React pages access data via API endpoints:

### Base URL
- Development: `http://localhost:8000/api/v1/app`
- Production: `https://yourdomain.com/api/v1/app`

### Authentication
- Token-based (Sanctum)
- Get token: `GET /api/v1/auth/session-token` (when authenticated via web session)

### Common Endpoints
- `GET /api/v1/app/{resource}` - List resources
- `POST /api/v1/app/{resource}` - Create resource
- `GET /api/v1/app/{resource}/{id}` - Get resource
- `PUT /api/v1/app/{resource}/{id}` - Update resource
- `DELETE /api/v1/app/{resource}/{id}` - Delete resource

---

## Best Practices

### ✅ DO
- Use React Router for all `/app/*` routes
- Access data via API endpoints
- Use React Hook Form + Zod for form validation
- Follow type-safe patterns with OpenAPI generated types
- Implement proper error handling with error.id
- Include tenant isolation in all queries

### ❌ DON'T
- Create Blade views for `/app/*` routes
- Create controllers for `/app/*` page views
- Mix Blade and React in `/app/*` routes
- Bypass API layer for data access
- Hardcode tenant_id (use middleware/service layer)

---

## Troubleshooting

### React routes not working
1. Check `routes/web.php` has catch-all route
2. Check `.htaccess` or Nginx config for SPA fallback
3. Verify `view('app.spa')` exists
4. Check React build is up to date

### Legacy routes still accessible
1. Check `routes/app.php` for `/app-legacy/*` routes
2. Verify legacy routes are not in `/app/*` prefix
3. Test that `/app/*` routes serve React SPA

### API errors
1. Check authentication token is valid
2. Verify tenant isolation in API responses
3. Check API routes in `routes/api_v1.php`
4. Verify middleware is applied correctly

---

## Related Documentation

- [Routes Guide](./ROUTES_GUIDE.md) - Detailed route conventions
- [API Documentation](./api/API_DOCUMENTATION.md) - API endpoint reference
- [Frontend Architecture](../frontend/README.md) - React SPA structure

---

**Remember:** `/app/*` = React SPA only. Blade only for `/admin/*`. 🎯

