# ZenaManage Coding Guidelines
## Preventing Duplicates, Overrides, and Conflicts

### 🎯 **Core Principles**

#### **1. Single Source of Truth (SSOT)**
- **One function per purpose**: Never duplicate functionality
- **One component per UI element**: Avoid multiple implementations
- **One API endpoint per resource**: No duplicate routes
- **One CSS class per style**: Avoid conflicting styles

#### **2. Clear Separation of Concerns**
- **UI Layer**: Only rendering and user interaction
- **API Layer**: Business logic and data processing
- **Database Layer**: Data persistence and queries
- **Service Layer**: Complex business operations

#### **3. Consistent Naming Conventions**
- **Routes**: `kebab-case` (`/admin/users`, `/app/projects`)
- **Controllers**: `PascalCase` (`UserController`, `ProjectService`)
- **Methods**: `camelCase` (`getUserData`, `updateProject`)
- **Variables**: `camelCase` (`userData`, `projectList`)
- **CSS Classes**: `kebab-case` (`user-card`, `project-list`)
- **Database**: `snake_case` (`user_id`, `project_name`)

### 🚫 **Anti-Patterns to Avoid**

#### **JavaScript Anti-Patterns**
```javascript
// ❌ BAD: Duplicate function definitions
function loadUsers() { /* ... */ }
function loadUsers() { /* ... */ } // Duplicate!

// ❌ BAD: ES6 modules in regular scripts
import { getData } from './utils.js'; // Will cause conflicts

// ❌ BAD: Global variable conflicts
window.data = {}; // Multiple files doing this

// ✅ GOOD: Single function with parameters
function loadData(type, options = {}) { /* ... */ }

// ✅ GOOD: Window namespace
window.ZenaManage = window.ZenaManage || {};
window.ZenaManage.Users = { load: loadUsers };

// ✅ GOOD: Conditional loading
if (typeof Chart !== 'undefined') {
    // Chart.js code here
}
```

#### **Blade Template Anti-Patterns**
```blade
{{-- ❌ BAD: Duplicate x-data --}}
<div x-data="userList()">...</div>
<div x-data="userList()">...</div> {{-- Duplicate! --}}

{{-- ❌ BAD: Multiple Chart.js includes --}}
<script src="chart.js"></script>
<script src="chart.js"></script> {{-- Duplicate! --}}

{{-- ✅ GOOD: Unique x-data --}}
<div x-data="userList()">...</div>
<div x-data="projectList()">...</div>

{{-- ✅ GOOD: Conditional includes --}}
@if(request()->is('admin/users'))
<script src="{{ asset('js/users.js') }}"></script>
@endif
```

#### **CSS Anti-Patterns**
```css
/* ❌ BAD: Conflicting styles */
.user-card { background: blue; }
.user-card { background: red; } /* Override! */

/* ❌ BAD: Duplicate classes */
.btn-primary { /* ... */ }
.btn-primary { /* ... */ } /* Duplicate! */

/* ✅ GOOD: Specific selectors */
.user-card.primary { background: blue; }
.user-card.secondary { background: red; }

/* ✅ GOOD: Single definition */
.btn-primary { 
    background: blue; 
    /* All styles here */
}
```

### 🔧 **Best Practices**

#### **1. Function Organization**
```javascript
// ✅ GOOD: Namespace pattern
window.ZenaManage = window.ZenaManage || {};

window.ZenaManage.Users = {
    load: function() { /* ... */ },
    create: function() { /* ... */ },
    update: function() { /* ... */ }
};

// ✅ GOOD: Check before defining
if (!window.ZenaManage.Users) {
    window.ZenaManage.Users = { /* ... */ };
}
```

#### **2. Component Patterns**
```blade
{{-- ✅ GOOD: Unique component IDs --}}
<div id="users-list" x-data="userList()">
    <!-- User list content -->
</div>

<div id="projects-list" x-data="projectList()">
    <!-- Project list content -->
</div>

{{-- ✅ GOOD: Conditional rendering --}}
@if(request()->is('admin/users'))
    @include('admin.users._list')
@endif
```

#### **3. API Endpoint Patterns**
```php
// ✅ GOOD: RESTful routes
Route::get('/admin/users', [UserController::class, 'index']);
Route::get('/admin/users/{user}', [UserController::class, 'show']);
Route::post('/admin/users', [UserController::class, 'store']);

// ✅ GOOD: API versioning
Route::prefix('api/v1')->group(function () {
    Route::get('/users', [UserApiController::class, 'index']);
});
```

#### **4. CSS Organization**
```css
/* ✅ GOOD: BEM methodology */
.user-card { /* Block */ }
.user-card__title { /* Element */ }
.user-card--active { /* Modifier */ }

/* ✅ GOOD: Component-specific styles */
.users-list .user-card { /* Specific to users list */ }
.projects-list .user-card { /* Different styling */ }
```

### 🛠️ **Development Workflow**

#### **1. Before Starting Work**
```bash
# Check for existing functionality
grep -r "functionName" resources/views public/js
grep -r "className" public/css
grep -r "routeName" routes/

# Check for conflicts
./scripts/check-conflicts.sh
```

#### **2. During Development**
```bash
# Run conflict checks
./scripts/check-conflicts.sh

# Check for duplicates
find . -name "*.php" -o -name "*.js" -o -name "*.blade.php" | xargs grep -l "yourFunctionName"

# Validate changes
php artisan test
npm run test
```

#### **3. Before Committing**
```bash
# Run all checks
./scripts/check-conflicts.sh
pre-commit run --all-files

# Check for TODO/FIXME
grep -r "TODO\|FIXME" . --exclude-dir=vendor --exclude-dir=node_modules
```

### 🔍 **Conflict Detection Tools**

#### **1. Automated Scripts**
- `./scripts/check-conflicts.sh` - Comprehensive conflict detection
- `pre-commit hooks` - Automatic checks before commit
- `GitHub Actions` - CI/CD pipeline checks

#### **2. Manual Checks**
```bash
# Check for duplicate functions
find . -name "*.js" -o -name "*.blade.php" | xargs grep -h "function " | sort | uniq -d

# Check for duplicate CSS classes
find . -name "*.css" | xargs grep -h "\." | sort | uniq -d

# Check for duplicate routes
find routes/ -name "*.php" | xargs grep -h "Route::" | sort | uniq -d
```

#### **3. IDE Integration**
- **Cursor Rules**: `.cursorrules` file for AI assistant
- **ESLint**: JavaScript linting and conflict detection
- **PHP CS Fixer**: PHP code style and conflict detection
- **Prettier**: Code formatting consistency

### 📋 **Checklist for New Features**

#### **Before Implementation**
- [ ] Check if similar functionality exists
- [ ] Verify naming conventions
- [ ] Plan component structure
- [ ] Design API endpoints
- [ ] Consider performance impact

#### **During Implementation**
- [ ] Follow SSOT principle
- [ ] Use consistent naming
- [ ] Implement proper error handling
- [ ] Add logging and monitoring
- [ ] Write tests

#### **After Implementation**
- [ ] Run conflict detection
- [ ] Test all scenarios
- [ ] Update documentation
- [ ] Code review
- [ ] Performance testing

### 🚨 **Emergency Procedures**

#### **If Conflicts Are Detected**
1. **Stop development immediately**
2. **Run conflict detection script**
3. **Identify root cause**
4. **Plan resolution strategy**
5. **Implement fixes**
6. **Re-test everything**
7. **Update prevention measures**

#### **Resolution Strategies**
- **Merge conflicts**: Use proper Git merge tools
- **Code conflicts**: Refactor to eliminate duplicates
- **Style conflicts**: Use CSS specificity or BEM
- **JS conflicts**: Use namespaces or modules
- **Route conflicts**: Use proper RESTful design

### 📚 **Resources**

#### **Documentation**
- [Laravel Best Practices](https://laravel.com/docs/best-practices)
- [Vue.js Style Guide](https://vuejs.org/style-guide/)
- [CSS BEM Methodology](https://getbem.com/)
- [JavaScript ES6 Modules](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules)

#### **Tools**
- [Pre-commit Hooks](https://pre-commit.com/)
- [ESLint](https://eslint.org/)
- [PHP CS Fixer](https://cs.symfony.com/)
- [Git Hooks](https://git-scm.com/book/en/v2/Customizing-Git-Git-Hooks)

---

**Remember**: Prevention is better than cure. Follow these guidelines to avoid conflicts from the start!
