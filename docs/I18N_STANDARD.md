# i18n Standard - Unified Translation Keys

## Overview

This document ensures that **Backend (Laravel)** and **Frontend (React)** use the **same translation keys** for consistency across the application.

**Last Updated:** 2025-01-XX  
**Status:** Active

---

## Architecture

### Backend (Laravel)
- **Location**: `lang/{locale}/{namespace}.php`
- **Format**: PHP array files
- **Usage**: `__('namespace.key')` or `trans('namespace.key')`

### Frontend (React)
- **API Endpoint**: `/api/i18n/translations?locale={locale}&namespaces={namespaces}`
- **Format**: JSON (nested structure matching PHP arrays)
- **Usage**: `t('namespace.key')` via `useI18n()` hook

### Single Source of Truth
- **Backend files** (`lang/{locale}/{namespace}.php`) are the **single source of truth**
- Frontend fetches translations via API endpoint
- Both use the **same namespace and key structure**

---

## Translation Key Structure

### Namespace Convention
Translation files are organized by feature/module:

```
lang/
  en/
    app.php          # General app translations
    auth.php         # Authentication
    dashboard.php    # Dashboard
    projects.php     # Projects
    tasks.php        # Tasks
    documents.php    # Documents
    settings.php     # Settings
    errors.php       # Error messages
    validation.php   # Validation messages
    ...
```

### Key Naming Convention

1. **Use dot notation for nested keys**:
   ```php
   // lang/en/app.php
   return [
       'nav' => [
           'dashboard' => 'Dashboard',
           'projects' => 'Projects',
       ],
       'greeting' => 'Hello',
   ];
   ```

2. **Access in Backend**:
   ```php
   __('app.nav.dashboard')  // "Dashboard"
   __('app.greeting')        // "Hello"
   ```

3. **Access in Frontend**:
   ```tsx
   const { t } = useI18n();
   t('app.nav.dashboard')  // "Dashboard"
   t('app.greeting')       // "Hello"
   ```

---

## Translation Key Guidelines

### 1. Consistent Naming
- Use **snake_case** for keys: `task_title`, `due_date`, `created_at`
- Use **camelCase** for nested groups: `nav`, `notifications`, `actions`

### 2. Namespace Organization
- Group related keys under a namespace (e.g., `tasks`, `projects`)
- Use nested arrays for logical grouping:
  ```php
  // lang/en/tasks.php
  return [
      'title' => 'Tasks',
      'kanban' => [
          'title' => 'Task Board',
          'view' => 'Board',
      ],
      'actions' => [
          'create' => 'Create Task',
          'edit' => 'Edit Task',
          'delete' => 'Delete Task',
      ],
  ];
  ```

### 3. Parameterized Translations
- Use `{param}` syntax for dynamic values:
  ```php
  // lang/en/tasks.php
  return [
      'task_assigned' => 'Task "{title}" assigned to {assignee}',
  ];
  ```

- Access in Backend:
  ```php
  __('tasks.task_assigned', ['title' => 'Task 1', 'assignee' => 'John'])
  ```

- Access in Frontend:
  ```tsx
  t('tasks.task_assigned', { title: 'Task 1', assignee: 'John' })
  ```

---

## Adding New Translation Keys

### Step 1: Add to Backend File
```php
// lang/en/tasks.php
return [
    // ... existing keys ...
    'new_feature' => [
        'title' => 'New Feature',
        'description' => 'Description of new feature',
    ],
];
```

### Step 2: Add to Other Locales
```php
// lang/vi/tasks.php
return [
    // ... existing keys ...
    'new_feature' => [
        'title' => 'Tính năng mới',
        'description' => 'Mô tả tính năng mới',
    ],
];
```

### Step 3: Use in Frontend
```tsx
const { t } = useI18n();
<h1>{t('tasks.new_feature.title')}</h1>
<p>{t('tasks.new_feature.description')}</p>
```

### Step 4: Use in Backend (Blade)
```blade
<h1>{{ __('tasks.new_feature.title') }}</h1>
<p>{{ __('tasks.new_feature.description') }}</p>
```

---

## Namespace Mapping

| Namespace | File | Usage |
|-----------|------|-------|
| `app` | `lang/{locale}/app.php` | General app translations, navigation, notifications |
| `auth` | `lang/{locale}/auth.php` | Authentication, login, registration |
| `dashboard` | `lang/{locale}/dashboard.php` | Dashboard-specific translations |
| `projects` | `lang/{locale}/projects.php` | Project management |
| `tasks` | `lang/{locale}/tasks.php` | Task management |
| `documents` | `lang/{locale}/documents.php` | Document management |
| `settings` | `lang/{locale}/settings.php` | Settings pages |
| `errors` | `lang/{locale}/errors.php` | Error messages |
| `validation` | `lang/{locale}/validation.php` | Validation messages |

---

## API Endpoint

### GET `/api/i18n/translations`

**Query Parameters:**
- `locale` (optional): Locale code (default: current locale)
- `namespaces` (optional): Comma-separated list (default: `['app', 'settings']`)
- `flat` (optional): Boolean, if true returns flat structure (default: `false`)

**Example Request:**
```bash
GET /api/i18n/translations?locale=en&namespaces=app,tasks,projects
```

**Example Response:**
```json
{
  "success": true,
  "locale": "en",
  "data": {
    "app": {
      "nav": {
        "dashboard": "Dashboard",
        "projects": "Projects"
      }
    },
    "tasks": {
      "title": "Tasks",
      "kanban": {
        "title": "Task Board"
      }
    }
  }
}
```

---

## Best Practices

### 1. Always Use Translation Keys
❌ **Don't:**
```tsx
<h1>Tasks</h1>
```

✅ **Do:**
```tsx
<h1>{t('tasks.title')}</h1>
```

### 2. Consistent Key Names
- Use the same key name across all locales
- Keep keys descriptive and self-documenting

### 3. Namespace Per Feature
- Each major feature should have its own namespace
- Avoid mixing unrelated keys in the same namespace

### 4. Reuse Common Keys
- Common UI elements (buttons, labels) should be in `app` namespace
- Feature-specific keys should be in their respective namespaces

### 5. Parameterized Messages
- Use parameters for dynamic content
- Keep parameter names consistent (e.g., `{name}`, `{count}`, `{date}`)

---

## Validation Checklist

Before committing translation changes:

- [ ] Added keys to **all supported locales** (en, vi)
- [ ] Keys follow **naming convention** (snake_case, nested structure)
- [ ] Keys are **used consistently** in both Backend and Frontend
- [ ] **Parameterized translations** use `{param}` syntax
- [ ] **Namespace** matches the feature/module
- [ ] **No hardcoded strings** in React components
- [ ] **No hardcoded strings** in Blade templates (use `__()` helper)

---

## Troubleshooting

### Missing Translation
If a translation key is missing:
1. Check if the key exists in `lang/{locale}/{namespace}.php`
2. Verify the namespace is loaded in Frontend (`I18nProvider` default namespaces)
3. Clear cache: `php artisan cache:clear`
4. Check browser console for translation loading errors

### Translation Not Updating
1. Clear Laravel cache: `php artisan cache:clear`
2. Hard refresh browser (Ctrl+Shift+R / Cmd+Shift+R)
3. Check ETag headers (translations are cached)

### Key Not Found
- Verify key path matches the nested structure in PHP file
- Check for typos in namespace or key name
- Ensure namespace is included in API request

---

## Examples

### Example 1: Simple Translation
**Backend (`lang/en/tasks.php`):**
```php
return [
    'title' => 'Tasks',
];
```

**Frontend:**
```tsx
const { t } = useI18n();
<h1>{t('tasks.title')}</h1>
```

**Blade:**
```blade
<h1>{{ __('tasks.title') }}</h1>
```

### Example 2: Nested Translation
**Backend (`lang/en/tasks.php`):**
```php
return [
    'kanban' => [
        'title' => 'Task Board',
        'view' => 'Board',
    ],
];
```

**Frontend:**
```tsx
<h1>{t('tasks.kanban.title')}</h1>
<button>{t('tasks.kanban.view')}</button>
```

### Example 3: Parameterized Translation
**Backend (`lang/en/tasks.php`):**
```php
return [
    'task_assigned' => 'Task "{title}" assigned to {assignee}',
];
```

**Frontend:**
```tsx
<p>{t('tasks.task_assigned', { title: 'Task 1', assignee: 'John' })}</p>
```

---

## Related Documentation

- [API Documentation](./api/README.md) - API endpoint details
- [Frontend i18n Setup](../frontend/README.md) - React i18n configuration
- [Laravel Localization](https://laravel.com/docs/localization) - Laravel translation docs

---

*This document ensures consistency between Backend and Frontend translations.*

